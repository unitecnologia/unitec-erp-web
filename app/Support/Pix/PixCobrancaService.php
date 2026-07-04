<?php

namespace App\Support\Pix;

use App\Models\ContaReceber;
use App\Models\PixCobranca;
use App\Support\Erp\ErpTimezone;
use App\Support\Pix\Contracts\PixProvider;
use App\Support\Pix\Data\PixCobrancaInput;
use Illuminate\Support\Str;
use Throwable;

/**
 * Orquestra a criação e a baixa de cobranças Pix, independente do provedor.
 *
 * Confirmação: o app faz polling em `atualizarStatus()`, que consulta o provedor
 * (o servidor do ERP tem internet de saída). O webhook é um atalho opcional.
 */
class PixCobrancaService
{
    /** Tempo de expiração do QR, em minutos. */
    public const EXPIRA_MINUTOS = 5;

    public function __construct(private readonly PixProviderManager $providers)
    {
    }

    /**
     * Cria uma cobrança Pix para um pedido do app (a venda ainda não existe no
     * ERP; amarra-se pelo uuid do pedido).
     */
    public function criarParaPedido(
        string $orderUuid,
        float $valor,
        ?string $payerEmail = null,
        ?int $empresaId = null,
        ?string $descricao = null,
    ): PixCobranca {
        $provider = $this->providers->paraEmpresa($empresaId);

        $cobranca = PixCobranca::query()->create([
            'empresa_id' => $empresaId,
            'origem' => PixCobranca::ORIGEM_PEDIDO,
            'order_uuid' => $orderUuid,
            'provedor' => $provider->nome(),
            'txid' => (string) Str::uuid(),
            'valor' => round($valor, 2),
            'status' => PixCobranca::STATUS_PENDENTE,
            'payer_email' => $payerEmail,
            'expira_em' => ErpTimezone::toLocal()->addMinutes(self::EXPIRA_MINUTOS),
        ]);

        return $this->emitirNoProvedor($cobranca, $provider, $descricao ?? ('Pedido '.$orderUuid));
    }

    /**
     * Cria uma cobrança Pix para um título (conta a receber já existente).
     */
    public function criarParaTitulo(
        ContaReceber $conta,
        ?string $payerEmail = null,
        ?int $empresaId = null,
    ): PixCobranca {
        $valor = round((float) $conta->saldo, 2);
        $provider = $this->providers->paraEmpresa($empresaId);

        $cobranca = PixCobranca::query()->create([
            'empresa_id' => $empresaId,
            'origem' => PixCobranca::ORIGEM_TITULO,
            'conta_receber_id' => $conta->id,
            'provedor' => $provider->nome(),
            'txid' => (string) Str::uuid(),
            'valor' => $valor,
            'status' => PixCobranca::STATUS_PENDENTE,
            'payer_email' => $payerEmail ?? $conta->cliente?->email,
            'expira_em' => ErpTimezone::toLocal()->addMinutes(self::EXPIRA_MINUTOS),
        ]);

        return $this->emitirNoProvedor(
            $cobranca,
            $provider,
            'Título '.($conta->documento ?: $conta->numero),
        );
    }

    /**
     * Chama o provedor para gerar o QR e persiste o resultado na cobrança.
     */
    private function emitirNoProvedor(
        PixCobranca $cobranca,
        PixProvider $provider,
        string $descricao,
    ): PixCobranca {
        try {
            $webhookUrl = (string) config('services.mercadopago.webhook_url');

            $result = $provider->criarCobranca(new PixCobrancaInput(
                valor: (float) $cobranca->valor,
                descricao: $descricao,
                txid: $cobranca->txid,
                expiraEm: $cobranca->expira_em,
                payerEmail: $cobranca->payer_email,
                externalReference: (string) $cobranca->id,
                notificationUrl: $webhookUrl !== '' ? $webhookUrl : null,
            ));

            $cobranca->forceFill([
                'provider_ref' => $result->providerRef,
                'qr_copia_cola' => $result->qrCopiaCola,
                'qr_imagem_base64' => $result->qrImagemBase64,
                'status' => $result->status,
                'raw' => $result->raw,
            ])->save();

            return $cobranca;
        } catch (Throwable $e) {
            // Não deixa cobrança órfã sem QR.
            $cobranca->delete();

            throw $e;
        }
    }

    /**
     * Atualiza o status consultando o provedor (usado no polling). Dá baixa
     * automática quando confirmado pago.
     */
    public function atualizarStatus(PixCobranca $cobranca): PixCobranca
    {
        if (! $cobranca->isPendente()) {
            return $cobranca;
        }

        // Expirou localmente antes de pagar.
        if ($cobranca->isExpirada()) {
            $cobranca->forceFill(['status' => PixCobranca::STATUS_EXPIRADO])->save();

            return $cobranca;
        }

        try {
            $status = $this->providers
                ->paraCobranca($cobranca)
                ->consultarStatus((string) $cobranca->provider_ref);
        } catch (Throwable) {
            // Mantém pendente em caso de falha transitória de rede.
            return $cobranca;
        }

        if ($status === PixCobranca::STATUS_PAGO) {
            return $this->registrarPagamento($cobranca);
        }

        if ($status === PixCobranca::STATUS_CANCELADO) {
            $cobranca->forceFill(['status' => PixCobranca::STATUS_CANCELADO])->save();
        }

        return $cobranca;
    }

    /**
     * Marca a cobrança como paga e dá a baixa correspondente. Idempotente.
     */
    public function registrarPagamento(PixCobranca $cobranca): PixCobranca
    {
        if ($cobranca->isPago()) {
            return $cobranca;
        }

        $cobranca->forceFill([
            'status' => PixCobranca::STATUS_PAGO,
            'pago_em' => now(),
        ])->save();

        // Título já existente: baixa direto a conta a receber.
        if ($cobranca->origem === PixCobranca::ORIGEM_TITULO && $cobranca->conta_receber_id) {
            $this->baixarTitulo($cobranca);
        }

        // Pedido: a venda ainda não existe; o faturamento (Monitor) lê o flag
        // de Pix pago no payload e gera o título já baixado.

        return $cobranca;
    }

    private function baixarTitulo(PixCobranca $cobranca): void
    {
        $conta = $cobranca->contaReceber;

        if ($conta === null || (float) $conta->saldo <= 0) {
            return;
        }

        $recebido = (float) $conta->valor_recebido + (float) $cobranca->valor;
        $maximo = (float) $conta->valor - (float) $conta->desconto + (float) $conta->juros;

        $conta->forceFill([
            'valor_recebido' => min($recebido, $maximo),
            'recebido_em' => $cobranca->pago_em ?? now(),
            'forma' => ContaReceber::FORMA_PIX,
        ])->save(); // saldo é recalculado no saving()
    }

    public function cancelar(PixCobranca $cobranca): PixCobranca
    {
        if ($cobranca->isPendente()) {
            $cobranca->forceFill(['status' => PixCobranca::STATUS_CANCELADO])->save();
        }

        return $cobranca;
    }
}
