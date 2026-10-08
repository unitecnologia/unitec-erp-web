<?php

namespace App\Support\Pix;

use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\PixCobranca;
use App\Models\PlanoConta;
use App\Support\Erp\Boleto\Api\BoletoApi;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Pix\Ailos\AilosPixService;
use App\Support\Pix\Contracts\PixProvider;
use App\Support\Pix\Data\PixCobrancaInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
    /** Tempo de expiração do QR Mercado Pago, em minutos. */
    public const EXPIRA_MINUTOS = 5;

    /** Expiração padrão Ailos (segundos) — alinhada ao kit / config. */
    public const AILOS_EXPIRA_SEGUNDOS = 86400;

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
        ?string $debtorName = null,
        ?string $debtorDocument = null,
    ): PixCobranca {
        $provider = $this->providers->paraEmpresa($empresaId);
        $valor = round($valor, 2);
        $descricaoFinal = $descricao ?? ('Pedido '.$orderUuid);
        $expiraEm = $this->expiraEmParaProvedor($provider->nome());
        $txid = $this->txidParaProvedor(
            $provider->nome(),
            $orderUuid,
            $valor,
            $descricaoFinal,
            (string) $debtorDocument,
        );

        $cobranca = PixCobranca::query()->create([
            'empresa_id' => $empresaId,
            'origem' => PixCobranca::ORIGEM_PEDIDO,
            'order_uuid' => $orderUuid,
            'provedor' => $provider->nome(),
            'txid' => $txid,
            'valor' => $valor,
            'status' => PixCobranca::STATUS_PENDENTE,
            'payer_email' => $payerEmail,
            'expira_em' => $expiraEm,
        ]);

        return $this->emitirNoProvedor(
            $cobranca,
            $provider,
            $descricaoFinal,
            $debtorName,
            $debtorDocument,
        );
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
        $descricao = 'Título '.($conta->documento ?: $conta->numero);
        $cliente = $conta->cliente;
        $debtorName = $cliente?->nome_razao;
        $debtorDocument = $cliente?->cpf_cnpj;
        $expiraEm = $this->expiraEmParaProvedor($provider->nome());
        $txid = $this->txidParaProvedor(
            $provider->nome(),
            'CR'.$conta->id,
            $valor,
            $descricao,
            (string) $debtorDocument,
        );

        $cobranca = PixCobranca::query()->create([
            'empresa_id' => $empresaId,
            'origem' => PixCobranca::ORIGEM_TITULO,
            'conta_receber_id' => $conta->id,
            'provedor' => $provider->nome(),
            'txid' => $txid,
            'valor' => $valor,
            'status' => PixCobranca::STATUS_PENDENTE,
            'payer_email' => $payerEmail ?? $cliente?->email,
            'expira_em' => $expiraEm,
        ]);

        return $this->emitirNoProvedor(
            $cobranca,
            $provider,
            $descricao,
            $debtorName,
            $debtorDocument,
        );
    }

    /**
     * Chama o provedor para gerar o QR e persiste o resultado na cobrança.
     */
    private function emitirNoProvedor(
        PixCobranca $cobranca,
        PixProvider $provider,
        string $descricao,
        ?string $debtorName = null,
        ?string $debtorDocument = null,
    ): PixCobranca {
        try {
            $webhookUrl = $provider->nome() === 'ailos'
                ? (string) (Empresa::query()->find($cobranca->empresa_id)?->param_pix_webhook_url
                    ?: \App\Support\Erp\EmpresaParametros::pixAilosWebhookUrl())
                : (string) config('services.mercadopago.webhook_url');

            $result = $provider->criarCobranca(new PixCobrancaInput(
                valor: (float) $cobranca->valor,
                descricao: $descricao,
                txid: (string) $cobranca->txid,
                expiraEm: $cobranca->expira_em,
                payerEmail: $cobranca->payer_email,
                externalReference: (string) ($cobranca->conta_receber_id ?: $cobranca->order_uuid ?: $cobranca->id),
                notificationUrl: $webhookUrl !== '' ? $webhookUrl : null,
                debtorName: $debtorName,
                debtorDocument: $debtorDocument,
            ));

            $updates = [
                'provider_ref' => $result->providerRef,
                'qr_copia_cola' => $result->qrCopiaCola,
                'qr_imagem_base64' => $result->qrImagemBase64,
                'status' => $result->status,
                'raw' => $result->raw,
            ];

            // Ailos: txid estável do provedor (pode diferir do UUID local antigo).
            if ($provider->nome() === 'ailos' && $result->providerRef !== '') {
                $updates['txid'] = $result->providerRef;
                if (isset($result->raw['expirationDate']) && is_string($result->raw['expirationDate'])) {
                    try {
                        $updates['expira_em'] = \Illuminate\Support\Carbon::parse($result->raw['expirationDate']);
                    } catch (Throwable) {
                        // mantém expira_em local
                    }
                }
            }

            $cobranca->forceFill($updates)->save();

            return $cobranca;
        } catch (Throwable $e) {
            // Não deixa cobrança órfã sem QR.
            $cobranca->delete();

            throw $e;
        }
    }

    private function expiraEmParaProvedor(string $provedor): \Carbon\Carbon
    {
        if ($provedor === 'ailos') {
            $seconds = max(60, (int) config('ailos.expiration_seconds', self::AILOS_EXPIRA_SEGUNDOS));

            return ErpTimezone::toLocal()->addSeconds($seconds);
        }

        return ErpTimezone::toLocal()->addMinutes(self::EXPIRA_MINUTOS);
    }

    private function txidParaProvedor(
        string $provedor,
        string $reference,
        float $valor,
        string $descricao,
        string $debtorDocument,
    ): string {
        if ($provedor === 'ailos') {
            return AilosPixService::buildTxid(
                $reference,
                number_format($valor, 2, '.', ''),
                $descricao,
                $debtorDocument,
            );
        }

        return (string) Str::uuid();
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
     * Marca a cobrança como paga e dá a baixa correspondente. Idempotente (lock).
     */
    public function registrarPagamento(PixCobranca $cobranca): PixCobranca
    {
        $locked = DB::transaction(function () use ($cobranca): PixCobranca {
            $locked = PixCobranca::query()->whereKey($cobranca->id)->lockForUpdate()->firstOrFail();

            if ($locked->isPago()) {
                return $locked;
            }

            $locked->forceFill([
                'status' => PixCobranca::STATUS_PAGO,
                'pago_em' => now(),
            ])->save();

            // Título já existente: baixa direto a conta a receber.
            if ($locked->origem === PixCobranca::ORIGEM_TITULO && $locked->conta_receber_id) {
                $this->baixarTitulo($locked);
            }

            // Pedido: a venda ainda não existe; o faturamento (Monitor) lê o flag
            // de Pix pago no payload e gera o título já baixado.

            return $locked;
        });

        // Fora da transação: API do banco (não desfaz CR/Pix se falhar; reexecuta se já pago).
        if ($locked->origem === PixCobranca::ORIGEM_TITULO && $locked->conta_receber_id) {
            $this->tentarBaixaBoletosApi((int) $locked->conta_receber_id);
        }

        return $locked;
    }

    /**
     * Se o título tiver boleto aberto com driver de API, solicita baixa no banco.
     */
    private function tentarBaixaBoletosApi(int $contaReceberId): void
    {
        try {
            app(BoletoApi::class)->baixarAbertosDaContaReceber($contaReceberId);
        } catch (Throwable $e) {
            Log::warning('Boleto API: não foi possível baixar boletos após Pix do título.', [
                'conta_receber_id' => $contaReceberId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function baixarTitulo(PixCobranca $cobranca): void
    {
        $conta = ContaReceber::query()
            ->whereKey((int) $cobranca->conta_receber_id)
            ->lockForUpdate()
            ->first();

        if ($conta === null || (float) $conta->saldo <= 0) {
            return;
        }

        $valorBaixa = min((float) $cobranca->valor, (float) $conta->saldo);

        if ($valorBaixa <= 0) {
            return;
        }

        $recebido = (float) $conta->valor_recebido + $valorBaixa;
        $maximo = (float) $conta->valor - (float) $conta->desconto + (float) $conta->juros;

        $conta->forceFill([
            'valor_recebido' => min($recebido, $maximo),
            'recebido_em' => $cobranca->pago_em ?? now(),
            'forma' => ContaReceber::FORMA_PIX,
        ])->save(); // saldo é recalculado no saving()

        // PIX de título: baixa o CR e gera entrada no Livro Caixa (paridade FV).
        $data = ($cobranca->pago_em ?? now())->toDateString();
        $documento = (string) ($conta->documento ?: $conta->numero ?: ('CR-'.$conta->id));
        $plano = $conta->plano_conta_id
            ? PlanoConta::query()
                ->whereKey((int) $conta->plano_conta_id)
                ->where('ativo', true)
                ->where('dc', 'C')
                ->first(['id', 'descricao'])
            : null;

        app(ContaReceberBaixaService::class)->registrarEntradaCaixa(
            valor: $valorBaixa,
            data: $data,
            documento: $documento,
            historico: 'Recebimento PIX #'.($conta->numero ?: $conta->id),
            caixaContaId: $this->resolveCaixaContaIdPix($cobranca),
            empresaId: $conta->empresa_id ? (int) $conta->empresa_id : null,
            planoContaId: $plano?->id ? (int) $plano->id : null,
            planoNome: $plano ? mb_substr(mb_strtoupper((string) $plano->descricao, 'UTF-8'), 0, 120) : null,
        );
    }

    private function resolveCaixaContaIdPix(PixCobranca $cobranca): ?int
    {
        $formaPix = FormaPagamento::query()
            ->where('ativo', true)
            ->where(function ($q): void {
                $q->where('tipo', 'pix')
                    ->orWhere('descricao', 'like', '%PIX%');
            })
            ->orderBy('codigo')
            ->first();

        $caixaId = (int) ($formaPix?->conta_destino_id ?? 0);

        return $caixaId > 0 ? $caixaId : null;
    }

    public function cancelar(PixCobranca $cobranca): PixCobranca
    {
        if ($cobranca->isPendente()) {
            $cobranca->forceFill(['status' => PixCobranca::STATUS_CANCELADO])->save();
        }

        return $cobranca;
    }
}
