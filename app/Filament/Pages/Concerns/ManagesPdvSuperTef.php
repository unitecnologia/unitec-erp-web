<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvVenda;
use App\Models\TefIntencao;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Tef\SuperTefPaymentService;
use App\Support\Erp\Tef\SuperTefPdvGate;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use Throwable;

/**
 * SuperTEF (débito e crédito) no PDV — modal leve "Aguardando pagamento na maquininha".
 * Abre o modal na 1ª resposta Livewire; envia a cobrança na 2ª (wire:init),
 * como o Pix — a view ativa é pdvui::modals.finalizar.
 * Crédito: transaction_type=2 + installment_count (mesma forma SUPERTEF CREDITO).
 */
trait ManagesPdvSuperTef
{
    public bool $finalizarSuperTefAberta = false;

    public bool $finalizarSuperTefConfirmado = false;

    public bool $finalizarSuperTefEnviando = false;

    /** Após status 4: wire:init dispara gravar venda fora do wire:poll (Sim/Não remonta). */
    public bool $finalizarSuperTefAguardandoConclusao = false;

    public ?int $finalizarSuperTefIntencaoId = null;

    public string $finalizarSuperTefValorLabel = '';

    public string $finalizarSuperTefStatus = '';

    public string $finalizarSuperTefErro = '';

    public string $finalizarSuperTefOrderId = '';

    public string $finalizarSuperTefPosLabel = '';

    /** Ex.: Débito | Crédito 3x — só para o título do modal. */
    public string $finalizarSuperTefModalidadeLabel = '';

    public ?string $finalizarSuperTefPendingOperacao = null;

    public int $finalizarSuperTefLastPollTs = 0;

    /** Trava wire:init / cliques duplicados no envio da cobrança. */
    public bool $finalizarSuperTefEnvioLock = false;

    /** Cancelamento de pendência em andamento (PUT + GET). */
    public bool $finalizarSuperTefCancelando = false;

    /** Confirmação compacta antes de nova cobrança com pagamento anterior em recon. */
    public bool $finalizarSuperTefConfirmNovaCobranca = false;

    public ?int $finalizarSuperTefConfirmOcupanteId = null;

    /** Só "Iniciar nova cobrança" autoriza POST após revalidar POS. */
    public bool $finalizarSuperTefNovaCobrancaAutorizada = false;

    /** Resolver cobrança anterior que ocupa a POS (cupom já alterado). */
    public bool $finalizarSuperTefResolverAberta = false;

    public ?int $finalizarSuperTefResolverIntencaoId = null;

    public bool $finalizarSuperTefResolverVerificado = false;

    public bool $finalizarSuperTefResolverConfirmCancelar = false;

    public bool $finalizarSuperTefResolverBusy = false;

    public string $finalizarSuperTefResolverStatusLabel = '';

    public string $finalizarSuperTefResolverDetalhe = '';

    /** Pagamento já liquidado no pedido importado — não gera novo POST SuperTEF. */
    public bool $finalizarSuperTefFromImport = false;

    protected const SUPERTEF_MSG_AGUARDANDO = 'Aguardando pagamento.';

    protected const SUPERTEF_MSG_CANCELANDO = 'Cancelando pagamento.';

    protected const SUPERTEF_MSG_CANCELADO = 'Pagamento cancelado.';

    protected const SUPERTEF_MSG_APROVADO = 'Pagamento aprovado.';

    protected const SUPERTEF_MSG_RECUPERANDO = 'Recuperando pagamento.';

    protected const SUPERTEF_MSG_VERIFICACAO = 'Pagamento pendente de verificação.';

    protected const SUPERTEF_MSG_FINALIZANDO = 'Finalizando pedido.';

    /**
     * @var array{nsu: ?string, autorizacao: ?string, bandeira: ?string, maquininha: string, order_id: string, payment_uniqueid: ?int}|null
     */
    public ?array $finalizarSuperTefImportPayload = null;

    protected function resetFinalizarSuperTef(): void
    {
        $this->finalizarSuperTefAberta = false;
        $this->finalizarSuperTefConfirmado = false;
        $this->finalizarSuperTefEnviando = false;
        $this->finalizarSuperTefAguardandoConclusao = false;
        $this->finalizarSuperTefIntencaoId = null;
        $this->finalizarSuperTefValorLabel = '';
        $this->finalizarSuperTefStatus = '';
        $this->finalizarSuperTefErro = '';
        $this->finalizarSuperTefOrderId = '';
        $this->finalizarSuperTefPosLabel = '';
        $this->finalizarSuperTefModalidadeLabel = '';
        $this->finalizarSuperTefPendingOperacao = null;
        $this->finalizarSuperTefLastPollTs = 0;
        $this->finalizarSuperTefEnvioLock = false;
        $this->finalizarSuperTefCancelando = false;
        $this->finalizarSuperTefConfirmNovaCobranca = false;
        $this->finalizarSuperTefConfirmOcupanteId = null;
        $this->finalizarSuperTefNovaCobrancaAutorizada = false;
        $this->resetResolverPagamentoAnteriorSuperTef();
        $this->finalizarSuperTefFromImport = false;
        $this->finalizarSuperTefImportPayload = null;
    }

    protected function resetResolverPagamentoAnteriorSuperTef(): void
    {
        $this->finalizarSuperTefResolverAberta = false;
        $this->finalizarSuperTefResolverIntencaoId = null;
        $this->finalizarSuperTefResolverVerificado = false;
        $this->finalizarSuperTefResolverConfirmCancelar = false;
        $this->finalizarSuperTefResolverBusy = false;
        $this->finalizarSuperTefResolverStatusLabel = '';
        $this->finalizarSuperTefResolverDetalhe = '';
    }

    protected function abrirResolverPagamentoAnteriorSuperTef(TefIntencao $ocupante): void
    {
        $this->finalizarSuperTefEnviando = false;
        $this->finalizarSuperTefEnvioLock = false;
        $this->finalizarSuperTefCancelando = false;
        $this->finalizarSuperTefConfirmado = false;
        $this->finalizarSuperTefAberta = false;
        $this->finalizarSuperTefErro = '';
        $this->finalizarSuperTefStatus = '';
        $this->finalizarSuperTefIntencaoId = null;
        $this->finalizarSuperTefConfirmNovaCobranca = false;
        $this->finalizarSuperTefConfirmOcupanteId = null;
        $this->finalizarSuperTefNovaCobrancaAutorizada = false;
        $this->finalizarAlertaTitulo = null;
        $this->finalizarAlertaDetalhe = null;

        $this->finalizarSuperTefResolverAberta = true;
        $this->finalizarSuperTefResolverIntencaoId = (int) $ocupante->id;
        $this->finalizarSuperTefResolverVerificado = false;
        $this->finalizarSuperTefResolverConfirmCancelar = false;
        $this->finalizarSuperTefResolverBusy = false;
        $this->finalizarSuperTefOrderId = (string) $ocupante->order_id;
        $this->finalizarSuperTefResolverStatusLabel = match ($ocupante->status) {
            TefIntencao::STATUS_PAGA => 'Local: pagamento aprovado (não sincronizado com este cupom).',
            TefIntencao::STATUS_CANCELADA => 'Local: cancelado.',
            TefIntencao::STATUS_INDETERMINADA => 'Local: pendente de verificação.',
            default => 'Local: cobrança pendente na maquininha.',
        };
        $this->finalizarSuperTefResolverDetalhe = 'Order '.$ocupante->order_id
            .($ocupante->payment_uniqueid ? ' · #'.$ocupante->payment_uniqueid : '');
    }

    public function voltarResolverPagamentoAnteriorSuperTef(): void
    {
        $this->resetResolverPagamentoAnteriorSuperTef();
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    public function verificarPagamentoAnteriorSuperTef(): void
    {
        if (! $this->finalizarSuperTefResolverAberta || ! $this->finalizarSuperTefResolverIntencaoId || $this->finalizarSuperTefResolverBusy) {
            return;
        }

        $terminal = $this->pdvConfig()->terminal();
        if ($terminal === null) {
            $this->finalizarSuperTefResolverDetalhe = 'Terminal não disponível.';

            return;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);
        if (! $intencao) {
            $this->finalizarSuperTefResolverDetalhe = 'Intenção não encontrada.';

            return;
        }

        if ((int) $intencao->terminal_id !== (int) $terminal->id
            || (int) $intencao->empresa_id !== (int) ($terminal->empresa_id ?? $intencao->empresa_id)
        ) {
            $this->finalizarSuperTefResolverDetalhe = 'Intenção não pertence a este terminal/empresa.';

            return;
        }

        $this->finalizarSuperTefResolverBusy = true;
        $this->finalizarSuperTefResolverConfirmCancelar = false;

        try {
            $service = app(SuperTefPaymentService::class);
            $antes = (string) $intencao->status;
            $intencao = $intencao->payment_uniqueid
                ? $service->consultar($intencao, $terminal)
                : $service->recuperarPorOrderId($intencao, $terminal);

            $this->finalizarSuperTefResolverVerificado = true;
            $this->finalizarSuperTefOrderId = (string) $intencao->order_id;
            $this->finalizarSuperTefResolverDetalhe = 'Order '.$intencao->order_id
                .($intencao->payment_uniqueid ? ' · #'.$intencao->payment_uniqueid : '');

            $msgApi = trim((string) ($intencao->payment_message ?? ''));
            $falhaConsulta = str_contains(mb_strtolower($msgApi, 'UTF-8'), 'falha de rede')
                || str_contains(mb_strtolower($msgApi, 'UTF-8'), 'falha ao consultar')
                || str_contains(mb_strtolower($msgApi, 'UTF-8'), 'falha ao recuperar');

            if ($falhaConsulta && $antes === (string) $intencao->status) {
                $this->finalizarSuperTefResolverStatusLabel = 'Falha ao consultar a API. Cobrança mantida — sem nova cobrança.';
                if ($msgApi !== '') {
                    $this->finalizarSuperTefResolverDetalhe = $msgApi;
                }
                $this->finalizarSuperTefResolverBusy = false;

                return;
            }

            if ($intencao->status === TefIntencao::STATUS_CANCELADA) {
                $this->finalizarSuperTefResolverStatusLabel = 'API: pagamento cancelado. POS liberada.';
                $this->finalizarSuperTefResolverBusy = false;

                return;
            }

            if ($intencao->isPaga()) {
                $this->finalizarSuperTefResolverStatusLabel = 'API: pagamento aprovado. Não será associado a este cupom.';
                $this->finalizarSuperTefResolverBusy = false;

                return;
            }

            if ($intencao->status === TefIntencao::STATUS_INDETERMINADA) {
                $this->finalizarSuperTefResolverStatusLabel = 'API: resultado indeterminado. Mantida proteção contra duplicidade.';
                $this->finalizarSuperTefResolverBusy = false;

                return;
            }

            $this->finalizarSuperTefResolverStatusLabel = $antes === (string) $intencao->status
                ? 'API: ainda pendente na maquininha.'
                : 'API: pendente (atualizado).';
        } catch (Throwable $e) {
            $this->finalizarSuperTefResolverVerificado = true;
            $this->finalizarSuperTefResolverStatusLabel = 'Falha ao consultar a API. Cobrança mantida — sem nova cobrança.';
            $this->finalizarSuperTefResolverDetalhe = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : $this->finalizarSuperTefResolverDetalhe;
        }

        $this->finalizarSuperTefResolverBusy = false;
    }

    public function solicitarCancelamentoPagamentoAnteriorSuperTef(): void
    {
        if (! $this->finalizarSuperTefResolverAberta || ! $this->finalizarSuperTefResolverVerificado) {
            return;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);
        if (! $intencao || $intencao->isPaga() || $intencao->status === TefIntencao::STATUS_CANCELADA) {
            return;
        }

        if (! $intencao->isPendenteAberta() || ! $intencao->payment_uniqueid) {
            $this->finalizarSuperTefResolverStatusLabel = 'Cancelamento indisponível para o status atual.';

            return;
        }

        $this->finalizarSuperTefResolverConfirmCancelar = true;
        $this->finalizarSuperTefResolverStatusLabel = 'Confirma cancelar a cobrança anterior na maquininha?';
    }

    public function confirmarCancelamentoPagamentoAnteriorSuperTef(): void
    {
        if (! $this->finalizarSuperTefResolverAberta
            || ! $this->finalizarSuperTefResolverConfirmCancelar
            || $this->finalizarSuperTefResolverBusy
        ) {
            return;
        }

        $terminal = $this->pdvConfig()->terminal();
        if ($terminal === null) {
            return;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);
        if (! $intencao) {
            return;
        }

        if ((int) $intencao->terminal_id !== (int) $terminal->id) {
            $this->finalizarSuperTefResolverStatusLabel = 'Intenção não pertence a este terminal.';

            return;
        }

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);
        $posCfg = (int) ($cfg['pos_id'] ?? 0);
        if ($posCfg <= 0 || $posCfg !== (int) $intencao->pos_id) {
            $this->finalizarSuperTefResolverStatusLabel = 'POS do terminal diverge da intenção.';

            return;
        }

        $this->finalizarSuperTefResolverBusy = true;

        try {
            $resultado = app(SuperTefPaymentService::class)->cancelarPendente($intencao, $terminal);
            $intencao = $resultado['intencao'];
            $this->finalizarSuperTefResolverBusy = false;
            $this->finalizarSuperTefResolverConfirmCancelar = false;
            $this->finalizarSuperTefResolverVerificado = true;

            if (! empty($resultado['already_paid']) && $intencao->isPaga()) {
                $this->finalizarSuperTefResolverStatusLabel = 'API: pagamento aprovado durante o cancelamento. Não cancelado. Encaminhar para reconciliação.';

                return;
            }

            if (! empty($resultado['ok']) && $intencao->status === TefIntencao::STATUS_CANCELADA) {
                $this->finalizarSuperTefResolverStatusLabel = 'API: pagamento cancelado. POS liberada. Pode finalizar o novo pedido.';
                $this->finalizarSuperTefResolverDetalhe = 'Order '.$intencao->order_id
                    .($intencao->payment_uniqueid ? ' · #'.$intencao->payment_uniqueid : '');

                return;
            }

            if (! empty($resultado['unknown'])) {
                $this->finalizarSuperTefResolverStatusLabel = 'Resultado do cancelamento indeterminado. POS mantida protegida.';

                return;
            }

            $this->finalizarSuperTefResolverStatusLabel = $resultado['message'] !== ''
                ? $resultado['message']
                : 'Não foi possível cancelar. Cobrança permanece.';
        } catch (Throwable $e) {
            $this->finalizarSuperTefResolverBusy = false;
            $this->finalizarSuperTefResolverConfirmCancelar = false;
            $this->finalizarSuperTefResolverStatusLabel = 'Falha ao cancelar. Cobrança mantida.';
            $this->finalizarSuperTefResolverDetalhe = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : $this->finalizarSuperTefResolverDetalhe;
        }
    }

    /**
     * Após verificar pago de outro cupom: abre confirmação explícita de nova cobrança.
     */
    public function tratarPagamentoAnteriorAprovadoSuperTef(): void
    {
        if (! $this->finalizarSuperTefResolverAberta || ! $this->finalizarSuperTefResolverIntencaoId) {
            return;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);
        if (! $intencao || ! $intencao->isPaga() || filled($intencao->pdv_venda_id)) {
            return;
        }

        $this->resetResolverPagamentoAnteriorSuperTef();
        $this->abrirConfirmacaoNovaCobrancaSuperTef($intencao);
    }

    public function finalizarSuperTefResolverPodeCancelar(): bool
    {
        if (! $this->finalizarSuperTefResolverAberta
            || ! $this->finalizarSuperTefResolverVerificado
            || $this->finalizarSuperTefResolverBusy
            || $this->finalizarSuperTefResolverConfirmCancelar
        ) {
            return false;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);
        if (! $intencao || $intencao->isPaga() || $intencao->status === TefIntencao::STATUS_CANCELADA) {
            return false;
        }

        return $intencao->isPendenteAberta() && (int) ($intencao->payment_uniqueid ?? 0) > 0;
    }

    public function finalizarSuperTefResolverPodeTratarPago(): bool
    {
        if (! $this->finalizarSuperTefResolverAberta || ! $this->finalizarSuperTefResolverVerificado) {
            return false;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);

        return $intencao
            && $intencao->isPaga()
            && blank($intencao->pdv_venda_id);
    }

    public function finalizarSuperTefResolverPosLiberada(): bool
    {
        if (! $this->finalizarSuperTefResolverAberta || ! $this->finalizarSuperTefResolverVerificado) {
            return false;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefResolverIntencaoId);

        return $intencao && $intencao->status === TefIntencao::STATUS_CANCELADA;
    }

    protected function abrirConfirmacaoNovaCobrancaSuperTef(TefIntencao $ocupante): void
    {
        $this->finalizarSuperTefEnviando = false;
        $this->finalizarSuperTefEnvioLock = false;
        $this->finalizarSuperTefCancelando = false;
        $this->finalizarSuperTefConfirmado = false;
        $this->finalizarSuperTefAberta = false;
        $this->finalizarSuperTefErro = '';
        $this->finalizarSuperTefStatus = '';
        $this->finalizarSuperTefIntencaoId = null;
        $this->finalizarSuperTefConfirmNovaCobranca = true;
        $this->finalizarSuperTefConfirmOcupanteId = (int) $ocupante->id;
        $this->finalizarSuperTefOrderId = (string) $ocupante->order_id;
        $this->finalizarSuperTefNovaCobrancaAutorizada = false;
    }

    public function voltarConfirmacaoNovaCobrancaSuperTef(): void
    {
        $this->finalizarSuperTefConfirmNovaCobranca = false;
        $this->finalizarSuperTefConfirmOcupanteId = null;
        $this->finalizarSuperTefNovaCobrancaAutorizada = false;
        $this->finalizarSuperTefEnviando = false;
        $this->finalizarSuperTefEnvioLock = false;
        $this->finalizarSuperTefAberta = false;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    /**
     * Confirmação explícita do operador: libera recon + nova cobrança do cupom atual.
     */
    public function confirmarNovaCobrancaSuperTef(): void
    {
        if (! $this->finalizarSuperTefConfirmNovaCobranca) {
            return;
        }

        $terminal = $this->pdvConfig()->terminal();
        if ($terminal === null || ! SuperTefPdvGate::terminalPronto($terminal)) {
            $this->notifyPdvError('SuperTEF', 'SuperTEF não configurado neste terminal.');
            $this->voltarConfirmacaoNovaCobrancaSuperTef();

            return;
        }

        $fingerprint = $this->pdvCupomFingerprintSuperTef();
        $service = app(SuperTefPaymentService::class);
        $ocupante = $this->finalizarSuperTefConfirmOcupanteId
            ? TefIntencao::query()->find($this->finalizarSuperTefConfirmOcupanteId)
            : null;

        if ($ocupante && $ocupante->isPaga() && blank($ocupante->pdv_venda_id)) {
            if ($ocupante->podeVincularAoCupom($fingerprint)) {
                $this->finalizarSuperTefConfirmNovaCobranca = false;
                $this->finalizarSuperTefConfirmOcupanteId = null;
                $this->finalizarSuperTefNovaCobrancaAutorizada = false;
                $this->aplicarSuperTefPago($ocupante);

                return;
            }

            // Ciência persistente desta intenção (não reexibe o aviso nas próximas vendas).
            $service->marcarCienciaReconciliacao($ocupante);
        }

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);
        $posId = (int) ($cfg['pos_id'] ?? 0);
        if ($posId <= 0) {
            $this->notifyPdvError('SuperTEF', 'POS SuperTEF não configurada neste terminal.');

            return;
        }

        if ($service->posOcupada($terminal, $posId)) {
            $bloqueio = $service->intencaoOcupanteNaPos($terminal, $posId);
            $this->finalizarSuperTefConfirmNovaCobranca = false;
            $this->finalizarSuperTefConfirmOcupanteId = null;
            $this->finalizarSuperTefNovaCobrancaAutorizada = false;
            if ($bloqueio) {
                $this->abrirResolverPagamentoAnteriorSuperTef($bloqueio);

                return;
            }
            $this->notifyPdvError('SuperTEF', 'POS ainda ocupada. Verifique a cobrança anterior antes de iniciar nova.');

            return;
        }

        // Autorização one-shot → abre modal e envia (wire:init / envio).
        $this->finalizarSuperTefConfirmNovaCobranca = false;
        $this->finalizarSuperTefConfirmOcupanteId = null;
        $this->finalizarSuperTefNovaCobrancaAutorizada = true;
        $this->finalizarSuperTefEnvioLock = false;
        $this->abrirSuperTefPdv();
    }

    /**
     * Poll só enquanto há operação aberta (sem estados finais).
     */
    public function finalizarSuperTefDevePollar(): bool
    {
        if (! $this->finalizarSuperTefAberta
            || ! $this->finalizarSuperTefIntencaoId
            || $this->finalizarSuperTefEnviando
            || $this->finalizarSuperTefCancelando
            || $this->finalizarSuperTefErro !== ''
            || $this->finalizarSuperTefConfirmado
        ) {
            return false;
        }

        return ! in_array($this->finalizarSuperTefStatus, [
            self::SUPERTEF_MSG_CANCELADO,
            self::SUPERTEF_MSG_APROVADO,
            self::SUPERTEF_MSG_FINALIZANDO,
            'Pedido concluído',
        ], true);
    }

    /**
     * Botão "Cancelar pagamento" — só com pendência aberta e uniqueid.
     */
    public function finalizarSuperTefPodeCancelarPagamento(): bool
    {
        if (! $this->finalizarSuperTefAberta
            || ! $this->finalizarSuperTefIntencaoId
            || $this->finalizarSuperTefEnviando
            || $this->finalizarSuperTefCancelando
            || $this->finalizarSuperTefConfirmado
        ) {
            return false;
        }

        if (in_array($this->finalizarSuperTefStatus, [
            self::SUPERTEF_MSG_CANCELANDO,
            self::SUPERTEF_MSG_CANCELADO,
            self::SUPERTEF_MSG_APROVADO,
            self::SUPERTEF_MSG_FINALIZANDO,
            'Pedido concluído',
        ], true)) {
            return false;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
        if (! $intencao || filled($intencao->pdv_venda_id) || $intencao->requerReconciliacao()) {
            return false;
        }

        return $intencao->isPendenteAberta() && (int) ($intencao->payment_uniqueid ?? 0) > 0;
    }

    /**
     * Hash do cupom atual — único vínculo inequívoco para recuperar pagamento pago.
     */
    protected function pdvCupomFingerprintSuperTef(): string
    {
        $itens = [];
        foreach ($this->cupomItens as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itens[] = implode(':', [
                (int) ($item['product_id'] ?? 0),
                (int) ($item['product_grade_id'] ?? 0),
                number_format((float) ($item['quantidade'] ?? 0), 3, '.', ''),
                number_format((float) ($item['total'] ?? 0), 2, '.', ''),
            ]);
        }
        sort($itens);

        $params = $this->finalizarSuperTefParametrosCobranca();
        $payload = [
            'empresa' => (int) (ErpContext::currentEmpresaId() ?: 0),
            'terminal' => (int) ($this->pdvConfig()->terminal()?->id ?? 0),
            'caixa' => (int) ($this->caixaSessaoId ?? 0),
            'total' => number_format($this->finalizarTotalVendaValor(), 2, '.', ''),
            'tef' => number_format($this->finalizarSuperTefTotalValor(), 2, '.', ''),
            'tef_type' => (string) ($params['transaction_type'] ?? '1'),
            'tef_parcelas' => (int) ($params['installment_count'] ?? 1),
            'itens' => $itens,
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    protected function finalizarSuperTefTotalValor(): float
    {
        $total = 0.0;

        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::isFormaSuperTef($pagamento)) {
                continue;
            }

            $total += ErpMoney::parseBr($pagamento['valor'] ?? '0');
        }

        return round($total, 2);
    }

    protected function finalizarTemSuperTefComValor(): bool
    {
        return $this->pdvConfig()->usaSuperTefDebito()
            && $this->finalizarSuperTefTotalValor() > 0;
    }

    /**
     * Uma única linha SuperTEF com valor (débito OU crédito — não misturar).
     *
     * @return array{pagamento: array, erro: ?string}
     */
    protected function finalizarSuperTefLinhaAtiva(): array
    {
        $linhas = [];
        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::isFormaSuperTef($pagamento)) {
                continue;
            }
            if (ErpMoney::parseBr($pagamento['valor'] ?? '0') <= 0) {
                continue;
            }
            $linhas[] = $pagamento;
        }

        if ($linhas === []) {
            return ['pagamento' => [], 'erro' => null];
        }

        if (count($linhas) > 1) {
            return [
                'pagamento' => [],
                'erro' => 'Use apenas uma linha SuperTEF por venda (débito ou crédito).',
            ];
        }

        return ['pagamento' => $linhas[0], 'erro' => null];
    }

    /**
     * Parâmetros de cobrança da linha SuperTEF ativa.
     *
     * @return array{transaction_type: string, installment_count: int, installment_type: int, label: string}|null
     */
    protected function finalizarSuperTefParametrosCobranca(): ?array
    {
        $ativo = $this->finalizarSuperTefLinhaAtiva();
        if ($ativo['erro'] !== null || $ativo['pagamento'] === []) {
            return null;
        }

        $pagamento = $ativo['pagamento'];
        $terminal = $this->pdvConfig()->terminal();
        $cfg = $terminal
            ? SuperTefTerminalConfig::read($terminal, withSecret: false)
            : ['credito_max_parcelas' => SuperTefTerminalConfig::CREDITO_MAX_PARCELAS_DEFAULT, 'credito_installment_type' => SuperTefTerminalConfig::INSTALLMENT_TYPE_LOJA];

        if (PdvFinalizarPagamentosHelper::isFormaSuperTefCredito($pagamento)) {
            $max = (int) ($cfg['credito_max_parcelas'] ?? SuperTefTerminalConfig::CREDITO_MAX_PARCELAS_DEFAULT);
            if (PdvFinalizarPagamentosHelper::deveAssumirParcelaUnicaSemSelecao($pagamento)) {
                $parcelas = 1;
            } else {
                $parcelas = max(1, (int) preg_replace('/\D/', '', (string) ($pagamento['supertef_parcelas'] ?? '1')) ?: 1);
                if (
                    property_exists($this, 'finalizarCartaoCanhotoConfirmado')
                    && $this->finalizarCartaoCanhotoConfirmado
                    && property_exists($this, 'finalizarCartaoModoSuperTef')
                    && $this->finalizarCartaoModoSuperTef
                    && ! empty($this->finalizarCartaoParcelasRows)
                ) {
                    $parcelas = max(1, count($this->finalizarCartaoParcelasRows));
                }
                $parcelas = min($parcelas, max(1, $max));
            }
            $tipo = (int) ($cfg['credito_installment_type'] ?? SuperTefTerminalConfig::INSTALLMENT_TYPE_LOJA);

            return [
                'transaction_type' => '2',
                'installment_count' => $parcelas,
                'installment_type' => $tipo,
                'label' => $parcelas <= 1 ? 'Crédito à vista' : ('Crédito '.$parcelas.'x'),
                'condicao_key' => (string) ($pagamento['supertef_condicao'] ?? ('fin:'.$parcelas)),
                'condicao_origem' => (string) ($pagamento['modo_prazo'] ?? 'financeiro'),
            ];
        }

        return [
            'transaction_type' => '1',
            'installment_count' => 1,
            'installment_type' => SuperTefTerminalConfig::INSTALLMENT_TYPE_LOJA,
            'label' => 'Débito',
        ];
    }

    public function finalizarSuperTefMaxParcelasUi(): int
    {
        $terminal = $this->pdvConfig()->terminal();
        if (! $terminal) {
            return SuperTefTerminalConfig::CREDITO_MAX_PARCELAS_DEFAULT;
        }
        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);

        return (int) ($cfg['credito_max_parcelas'] ?? SuperTefTerminalConfig::CREDITO_MAX_PARCELAS_DEFAULT);
    }

    /**
     * Abre espera SuperTEF se necessário. false = ainda aguardando.
     */
    protected function ensureSuperTefPdv(): bool
    {
        if (! $this->finalizarTemSuperTefComValor()) {
            $this->finalizarSuperTefConfirmado = false;
            $this->finalizarSuperTefAberta = false;

            return true;
        }

        if ($this->finalizarSuperTefConfirmado) {
            return true;
        }

        // Já aguardando maquininha: não reenvia POST. Se já pagou, tenta concluir.
        if ($this->finalizarSuperTefAberta) {
            if ($this->finalizarSuperTefIntencaoId && ! $this->finalizarSuperTefEnviando) {
                $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
                if ($intencao && $intencao->isPaga() && blank($intencao->pdv_venda_id)) {
                    $this->aplicarSuperTefPago($intencao);

                    return $this->finalizarSuperTefConfirmado && $this->finalizarSuperTefErro === '';
                }
            }

            return false;
        }

        // Pedido importado já pago no SuperTEF: não cobra de novo (ex.: transformar em NFC-e).
        if ($this->aplicarSuperTefDoPedidoImportadoSeExistir()) {
            return true;
        }

        return $this->abrirSuperTefPdv();
    }

    /**
     * Reusa NSU/auth do pedido de origem sem novo POST.
     * session('erp.pdv.venda_id') = id da venda retaguarda (F3); intenção/liga em pdv_vendas.id.
     */
    protected function aplicarSuperTefDoPedidoImportadoSeExistir(): bool
    {
        $vendaRetaguardaId = (int) session('erp.pdv.venda_id', 0);
        if ($vendaRetaguardaId <= 0) {
            return false;
        }

        $valor = $this->finalizarSuperTefTotalValor();

        $pdvVenda = PdvVenda::query()
            ->with('pagamentos')
            ->where('venda_id', $vendaRetaguardaId)
            ->where(function ($q): void {
                $q->whereNull('origem')
                    ->orWhere('origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
            })
            ->orderByDesc('id')
            ->first();

        // Compat: alguns fluxos antigos podem ter gravado o id do PDV na sessão.
        if ($pdvVenda === null) {
            $pdvVenda = PdvVenda::query()->with('pagamentos')->find($vendaRetaguardaId);
        }

        if ($pdvVenda === null) {
            return false;
        }

        $empresaId = (int) ($this->pdvConfig()->terminal()?->empresa_id ?? 0);
        $intencaoQuery = TefIntencao::query()
            ->where('pdv_venda_id', (int) $pdvVenda->id)
            ->where('status', TefIntencao::STATUS_PAGA)
            ->orderByDesc('id');
        if ($empresaId > 0) {
            $intencaoQuery->where('empresa_id', $empresaId);
        }

        $intencao = $intencaoQuery->first();

        if ($intencao && abs((float) $intencao->amount - $valor) < 0.009) {
            $unique = $intencao->payment_uniqueid ? (int) $intencao->payment_uniqueid : null;
            $maq = 'SUPERTEF POS '.(int) $intencao->pos_id;
            if ($unique) {
                $maq .= ' #'.$unique;
            }

            $this->finalizarSuperTefFromImport = true;
            $this->finalizarSuperTefIntencaoId = (int) $intencao->id;
            $this->finalizarSuperTefOrderId = (string) $intencao->order_id;
            $this->finalizarSuperTefImportPayload = [
                'nsu' => $intencao->nsu,
                'autorizacao' => $intencao->autorizacao,
                'bandeira' => $intencao->bandeira,
                'maquininha' => $maq,
                'order_id' => (string) $intencao->order_id,
                'payment_uniqueid' => $unique,
                'parcelas' => max(1, (int) $intencao->installment_count),
                'acquirer_cnpj' => $intencao->acquirerCnpjFiscal(),
            ];
            $this->finalizarSuperTefConfirmado = true;
            $this->finalizarSuperTefAberta = false;
            $this->finalizarSuperTefStatus = 'Pago (pedido)';

            return true;
        }

        foreach ($pdvVenda->pagamentos as $pag) {
            $maq = mb_strtoupper(trim((string) ($pag->cartao_maquininha ?? '')), 'UTF-8');
            $forma = mb_strtoupper(trim((string) ($pag->forma ?? '')), 'UTF-8');
            if (! str_starts_with($maq, 'SUPERTEF') && ! str_contains($forma, 'SUPERTEF')) {
                continue;
            }
            if (abs((float) $pag->valor - $valor) >= 0.009) {
                continue;
            }

            $this->finalizarSuperTefFromImport = true;
            $this->finalizarSuperTefIntencaoId = null;
            $parcelaLabel = trim((string) ($pag->cartao_parcela ?? '1x'));
            $parcelas = (int) preg_replace('/\D/', '', $parcelaLabel) ?: 1;
            $this->finalizarSuperTefImportPayload = [
                'nsu' => $pag->cartao_nsu ? (string) $pag->cartao_nsu : null,
                'autorizacao' => $pag->cartao_autorizacao ? (string) $pag->cartao_autorizacao : null,
                'bandeira' => $pag->cartao_bandeira ? (string) $pag->cartao_bandeira : null,
                'maquininha' => filled($pag->cartao_maquininha) ? (string) $pag->cartao_maquininha : 'SUPERTEF',
                'order_id' => '',
                'payment_uniqueid' => null,
                'parcelas' => max(1, $parcelas),
            ];
            $this->finalizarSuperTefConfirmado = true;
            $this->finalizarSuperTefAberta = false;
            $this->finalizarSuperTefStatus = 'Pago (pedido)';

            return true;
        }

        return false;
    }

    protected function abrirSuperTefPdv(): bool
    {
        $valor = $this->finalizarSuperTefTotalValor();
        if ($valor <= 0) {
            return true;
        }

        $posLabel = '';
        $terminal = $this->pdvConfig()->terminal();
        if ($terminal) {
            $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);
            $posId = (int) ($cfg['pos_id'] ?? 0);
            $posLabel = $posId > 0 ? (string) $posId : '';
        }

        // 1ª resposta: só abre o modal (rápido). Cobrança via wire:init → enviarSuperTefPdvAgora().
        $this->finalizarSuperTefAberta = true;
        $this->finalizarSuperTefConfirmado = false;
        $this->finalizarSuperTefErro = '';
        $this->finalizarSuperTefStatus = 'Iniciando cobrança…';
        $this->finalizarSuperTefValorLabel = ErpMoney::formatBr($valor);
        $this->finalizarSuperTefPosLabel = $posLabel;
        $params = $this->finalizarSuperTefParametrosCobranca();
        $this->finalizarSuperTefModalidadeLabel = (string) ($params['label'] ?? 'SuperTEF');
        $this->finalizarSuperTefEnviando = true;
        $this->finalizarSuperTefEnvioLock = false;
        $this->dispatch('erp-pdv-supertef-opened');
        $this->dispatch('erp-pdv-hide-fiscal-progress');

        return false;
    }

    public function enviarSuperTefPdvAgora(): void
    {
        if ($this->finalizarSuperTefConfirmado) {
            return;
        }

        if ($this->finalizarSuperTefEnvioLock) {
            return;
        }
        $this->finalizarSuperTefEnvioLock = true;

        if (! $this->finalizarSuperTefAberta) {
            // Chamada direta (recuperação): abre estado mínimo.
            $this->finalizarSuperTefAberta = true;
        }

        $valor = $this->finalizarSuperTefTotalValor();
        if ($valor <= 0) {
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefErro = 'Informe o valor SuperTEF.';
            $this->finalizarSuperTefStatus = 'Erro';

            return;
        }

        $linha = $this->finalizarSuperTefLinhaAtiva();
        if ($linha['erro'] !== null) {
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefEnvioLock = false;
            $this->finalizarSuperTefErro = $linha['erro'];
            $this->finalizarSuperTefStatus = 'Erro';
            $this->notifyPdvError('SuperTEF', $this->finalizarSuperTefErro);

            return;
        }

        $params = $this->finalizarSuperTefParametrosCobranca();
        if ($params === null) {
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefErro = 'Forma SuperTEF inválida.';
            $this->finalizarSuperTefStatus = 'Erro';

            return;
        }
        $this->finalizarSuperTefModalidadeLabel = $params['label'];

        $terminal = $this->pdvConfig()->terminal();
        if ($terminal === null || ! SuperTefPdvGate::terminalPronto($terminal)) {
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefErro = 'SuperTEF não configurado neste terminal.';
            $this->finalizarSuperTefStatus = 'Erro';

            return;
        }

        try {
            $service = app(SuperTefPaymentService::class);
            $caixaId = $this->caixaSessaoId ? (int) $this->caixaSessaoId : null;

            $fingerprint = $this->pdvCupomFingerprintSuperTef();

            if ($this->finalizarSuperTefIntencaoId) {
                $existente = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
                if ($existente && filled($existente->pdv_venda_id)) {
                    $this->finalizarSuperTefEnviando = false;
                    $this->finalizarSuperTefConfirmado = false;
                    $this->finalizarSuperTefIntencaoId = null;
                    $this->finalizarSuperTefEnvioLock = false;
                    $this->finalizarSuperTefErro = 'Este pagamento SuperTEF já foi usado em outra venda.';
                    $this->finalizarSuperTefStatus = 'Erro';

                    return;
                }
                if ($existente && $existente->requerReconciliacao()) {
                    if ($existente->temCienciaReconciliacao()) {
                        // Já reconhecida: não reutiliza e não reexibe aviso — segue nova cobrança do cupom.
                        $this->finalizarSuperTefIntencaoId = null;
                    } else {
                        $this->abrirConfirmacaoNovaCobrancaSuperTef($existente);

                        return;
                    }
                } elseif ($existente && $existente->isPaga()) {
                    if (! $existente->podeVincularAoCupom($fingerprint)) {
                        if ($existente->temCienciaReconciliacao()) {
                            $this->finalizarSuperTefIntencaoId = null;
                        } else {
                            $this->abrirConfirmacaoNovaCobrancaSuperTef($existente);

                            return;
                        }
                    } else {
                        $this->aplicarSuperTefPago($existente);

                        return;
                    }
                } elseif ($existente && $existente->post_enviado) {
                    // Nunca re-POST: só consulta/recupera.
                    $this->finalizarSuperTefEnviando = false;
                    $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_RECUPERANDO;
                    $this->pollFinalizarSuperTef();

                    return;
                }
            }

            // Reaproveita só intenção EM ABERTO do MESMO cupom (fingerprint).
            $reuso = $service->encontrarReaproveitavel($terminal, $valor, $caixaId, $fingerprint);
            if ($reuso) {
                $this->finalizarSuperTefIntencaoId = (int) $reuso->id;
                $this->finalizarSuperTefOrderId = (string) $reuso->order_id;
                if ($reuso->post_enviado) {
                    $this->finalizarSuperTefEnviando = false;
                    $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_RECUPERANDO;
                    $this->pollFinalizarSuperTef();

                    return;
                }
                // criada sem POST: continua para envio único abaixo
                $intencao = $reuso;
            } else {
                $cfgPos = SuperTefTerminalConfig::read($terminal, withSecret: false);
                $posId = (int) ($cfgPos['pos_id'] ?? 0);

                // Qualquer ocupante de outro cupom: resolver (verificar/cancelar) antes de nova cobrança.
                if (! $this->finalizarSuperTefNovaCobrancaAutorizada && $posId > 0) {
                    $ocupante = $service->intencaoOcupanteNaPos($terminal, $posId);
                    if ($ocupante) {
                        $fpOcupante = trim((string) ($ocupante->cupom_fingerprint ?? ''));
                        if ($fpOcupante !== $fingerprint) {
                            $this->abrirResolverPagamentoAnteriorSuperTef($ocupante);

                            return;
                        }
                    }

                    // Já em reconciliação (POS livre): confirmação explícita de nova cobrança.
                    $incompativel = $service->pagamentoAprovadoIncompativelNaPos($terminal, $posId, $fingerprint);
                    if ($incompativel) {
                        $this->abrirConfirmacaoNovaCobrancaSuperTef($incompativel);

                        return;
                    }
                }

                if ($this->finalizarSuperTefNovaCobrancaAutorizada && $posId > 0) {
                    $aindaIncompativel = $service->pagamentoAprovadoIncompativelNaPos($terminal, $posId, $fingerprint);
                    if ($aindaIncompativel && ! $aindaIncompativel->requerReconciliacao()) {
                        $service->marcarReconciliacaoAdministrativa(
                            $aindaIncompativel,
                            'operador confirmou nova cobrança; pagamento anterior (order '.$aindaIncompativel->order_id.').',
                        );
                    }
                    if ($service->posOcupada($terminal, $posId)) {
                        $bloqueio = $service->intencaoOcupanteNaPos($terminal, $posId);
                        $this->finalizarSuperTefNovaCobrancaAutorizada = false;
                        if ($bloqueio) {
                            $this->abrirResolverPagamentoAnteriorSuperTef($bloqueio);

                            return;
                        }
                        $this->finalizarSuperTefEnviando = false;
                        $this->finalizarSuperTefEnvioLock = false;
                        $this->finalizarSuperTefErro = 'POS ainda ocupada. Verifique a cobrança anterior.';
                        $this->finalizarSuperTefStatus = 'Erro';
                        $this->notifyPdvError('SuperTEF', $this->finalizarSuperTefErro);

                        return;
                    }
                }

                $intencao = $service->criarIntencao([
                    'empresa_id' => (int) (ErpContext::currentEmpresaId() ?: $terminal->empresa_id),
                    'terminal' => $terminal,
                    'amount' => $valor,
                    'caixa_sessao_id' => $caixaId,
                    'cupom_fingerprint' => $fingerprint,
                    'transaction_type' => $params['transaction_type'],
                    'installment_count' => $params['installment_count'],
                    'installment_type' => $params['installment_type'],
                ]);
                // Autorização one-shot consumida após criar a intenção deste cupom.
                $this->finalizarSuperTefNovaCobrancaAutorizada = false;
            }

            $this->finalizarSuperTefIntencaoId = (int) $intencao->id;
            $this->finalizarSuperTefOrderId = (string) $intencao->order_id;
            $this->finalizarSuperTefStatus = 'Iniciando cobrança…';

            $envio = $service->enviarCobranca($intencao, $terminal);
            $intencao = $envio['intencao'];
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefLastPollTs = time();
            $this->finalizarSuperTefOrderId = (string) $intencao->order_id;

            if ($intencao->isPaga()) {
                $this->aplicarSuperTefPago($intencao);

                return;
            }

            if ($intencao->status === TefIntencao::STATUS_FALHA) {
                $this->finalizarSuperTefErro = $envio['message'] !== '' ? $envio['message'] : 'Falha ao solicitar pagamento.';
                $this->finalizarSuperTefStatus = 'Erro';

                return;
            }

            if ($envio['transport_error'] || $intencao->status === TefIntencao::STATUS_INDETERMINADA) {
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_RECUPERANDO;
                $this->finalizarSuperTefErro = '';
                // Recuperação sem novo POST.
                $intencao = $service->recuperarPorOrderId($intencao, $terminal);
                if ($intencao->isPaga()) {
                    $this->aplicarSuperTefPago($intencao);

                    return;
                }
                if ($intencao->status === TefIntencao::STATUS_INDETERMINADA) {
                    $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_VERIFICACAO;

                    return;
                }
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_AGUARDANDO;

                return;
            }

            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_AGUARDANDO;
            $this->finalizarSuperTefErro = '';
        } catch (Throwable $e) {
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefErro = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'Não foi possível iniciar o SuperTEF.';
            $this->finalizarSuperTefStatus = 'Erro';
            $this->notifyPdvError('SuperTEF', $this->finalizarSuperTefErro);
        }
    }

    public function pollFinalizarSuperTef(): void
    {
        if (! $this->finalizarSuperTefDevePollar()) {
            $this->skipRender();

            return;
        }

        $minGap = SuperTefPdvGate::pollSeconds();
        if ($this->finalizarSuperTefLastPollTs > 0 && (time() - $this->finalizarSuperTefLastPollTs) < $minGap) {
            $this->skipRender();

            return;
        }

        $terminal = $this->pdvConfig()->terminal();
        if ($terminal === null) {
            $this->skipRender();

            return;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
        if (! $intencao) {
            $this->finalizarSuperTefErro = 'Intenção SuperTEF não encontrada.';
            $this->finalizarSuperTefStatus = 'Erro';

            return;
        }

        $statusAntes = (string) $intencao->status;

        try {
            $service = app(SuperTefPaymentService::class);
            if ($this->finalizarSuperTefStatus !== self::SUPERTEF_MSG_RECUPERANDO
                && $intencao->status === TefIntencao::STATUS_INDETERMINADA
            ) {
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_RECUPERANDO;
            }
            $intencao = $intencao->payment_uniqueid
                ? $service->consultar($intencao, $terminal)
                : $service->recuperarPorOrderId($intencao, $terminal);
            $this->finalizarSuperTefLastPollTs = time();
        } catch (Throwable) {
            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_VERIFICACAO;
            $this->skipRender();

            return;
        }

        $this->finalizarSuperTefOrderId = (string) $intencao->order_id;
        $novoStatus = match ($intencao->status) {
            TefIntencao::STATUS_PAGA => self::SUPERTEF_MSG_APROVADO,
            TefIntencao::STATUS_CANCELADA => self::SUPERTEF_MSG_CANCELADO,
            TefIntencao::STATUS_FALHA => 'Erro',
            TefIntencao::STATUS_INDETERMINADA => self::SUPERTEF_MSG_VERIFICACAO,
            default => self::SUPERTEF_MSG_AGUARDANDO,
        };

        if ($intencao->isPaga()) {
            $this->aplicarSuperTefPago($intencao);

            return;
        }

        if ($intencao->status === TefIntencao::STATUS_CANCELADA) {
            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_CANCELADO;
            $this->finalizarSuperTefErro = '';

            return;
        }

        // Sem mudança relevante: não remonta o PDV (Celeron / 2 GB).
        if ($statusAntes === (string) $intencao->status && $this->finalizarSuperTefStatus === $novoStatus) {
            $this->skipRender();

            return;
        }

        $this->finalizarSuperTefStatus = $novoStatus;
    }

    /**
     * Fecha a janela SEM cancelar a cobrança na API.
     * Pendência/indeterminada permanece para recuperação no próximo Concluir.
     */
    public function cancelFinalizarSuperTef(): void
    {
        if (! $this->finalizarSuperTefAberta || $this->finalizarSuperTefCancelando) {
            return;
        }

        if ($this->finalizarSuperTefIntencaoId) {
            $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);

            // Pagamento aprovado sem venda: só conclui se o cupom bater; senão preserva p/ reconciliação.
            if ($intencao && $intencao->isPaga() && blank($intencao->pdv_venda_id)) {
                if ($intencao->podeVincularAoCupom($this->pdvCupomFingerprintSuperTef())) {
                    $this->aplicarSuperTefPago($intencao);

                    return;
                }

                app(SuperTefPaymentService::class)->marcarReconciliacaoAdministrativa(
                    $intencao,
                    'fechamento da janela com pagamento aprovado sem vínculo inequívoco do cupom.',
                );
            }

            // Fechar ≠ cancelar: não chama PUT /pagamentos/cancelar.
        }

        $this->resetFinalizarSuperTef();
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    /**
     * Cancela cobrança pendente via PUT /pagamentos/cancelar/{uniqueid} + confirmação GET.
     */
    public function cancelarPagamentoSuperTef(): void
    {
        if (! $this->finalizarSuperTefPodeCancelarPagamento()) {
            return;
        }

        $terminal = $this->pdvConfig()->terminal();
        if ($terminal === null || ! $this->finalizarSuperTefIntencaoId) {
            return;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
        if (! $intencao) {
            return;
        }

        $this->finalizarSuperTefCancelando = true;
        $this->finalizarSuperTefErro = '';
        $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_CANCELANDO;

        try {
            $resultado = app(SuperTefPaymentService::class)->cancelarPendente($intencao, $terminal);
            $intencao = $resultado['intencao'];
            $this->finalizarSuperTefCancelando = false;
            $this->finalizarSuperTefOrderId = (string) $intencao->order_id;

            if (! empty($resultado['already_paid']) && $intencao->isPaga()) {
                // Corrida: aprovou antes do cancelamento — não descartar.
                $this->aplicarSuperTefPago($intencao);

                return;
            }

            if (! empty($resultado['ok']) && $intencao->status === TefIntencao::STATUS_CANCELADA) {
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_CANCELADO;
                $this->finalizarSuperTefErro = '';
                // Libera o operador para a finalização (nova tentativa / outro meio).
                $this->resetFinalizarSuperTef();
                $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);

                return;
            }

            if (! empty($resultado['unknown'])) {
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_VERIFICACAO;
                $this->finalizarSuperTefErro = $resultado['message'] !== ''
                    ? $resultado['message']
                    : 'Resultado indeterminado. Cobrança mantida para verificação.';

                return;
            }

            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_AGUARDANDO;
            $this->finalizarSuperTefErro = $resultado['message'] !== ''
                ? $resultado['message']
                : 'Não foi possível cancelar. Cobrança permanece pendente.';
        } catch (Throwable $e) {
            $this->finalizarSuperTefCancelando = false;
            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_VERIFICACAO;
            $this->finalizarSuperTefErro = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'Falha ao cancelar. Cobrança mantida para verificação.';
        }
    }

    protected function aplicarSuperTefPago(TefIntencao $intencao): void
    {
        if ($intencao->requerReconciliacao() || filled($intencao->pdv_venda_id)) {
            $this->finalizarSuperTefAberta = false;
            $this->finalizarSuperTefEnviando = false;
            $this->finalizarSuperTefConfirmado = false;
            $this->finalizarSuperTefIntencaoId = null;
            $this->finalizarSuperTefEnvioLock = false;
            $this->finalizarSuperTefStatus = 'Erro';
            $this->finalizarSuperTefErro = filled($intencao->pdv_venda_id)
                ? 'Este pagamento SuperTEF já está vinculado a outro pedido.'
                : 'Pagamento em reconciliação administrativa — não pode fechar esta venda.';
            $this->notifyPdvError('SuperTEF', $this->finalizarSuperTefErro);

            return;
        }

        $fingerprint = $this->pdvCupomFingerprintSuperTef();
        if (! $intencao->podeVincularAoCupom($fingerprint)) {
            $this->abrirConfirmacaoNovaCobrancaSuperTef($intencao);

            return;
        }

        $this->finalizarSuperTefConfirmado = true;
        $this->finalizarSuperTefEnviando = false;
        $this->finalizarSuperTefCancelando = false;
        $this->finalizarSuperTefErro = '';
        $this->finalizarSuperTefIntencaoId = (int) $intencao->id;
        $this->finalizarSuperTefOrderId = (string) $intencao->order_id;
        // Mantém o modal visível com o estado de finalização (não some a UI).
        $this->finalizarSuperTefAberta = true;
        $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_APROVADO;

        $operacao = $this->finalizarSuperTefPendingOperacao
            ?: $this->finalizarPixPendingOperacao
            ?: $this->finalizarOperacaoPendente
            ?: $this->pdvFinalizarOperacaoUnica;

        $this->dispatch('erp-pdv-supertef-paid');

        if (! filled($operacao)) {
            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_APROVADO;
            $this->finalizarSuperTefErro = 'Pagamento aprovado. Clique em Concluir para gravar o pedido (sem nova cobrança).';

            return;
        }

        // Não grava Pedido/NFC-e dentro do wire:poll — a resposta do poll não remonta o
        // modal Sim/Não de impressão. Flag + wire:init abre request Livewire dedicado.
        $this->finalizarSuperTefPendingOperacao = (string) $operacao;
        $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_FINALIZANDO;
        $this->finalizarSuperTefAguardandoConclusao = true;
    }

    /**
     * Request próprio (fora do poll) para gravar a venda e abrir a pergunta de impressão.
     * Disparado por wire:init quando finalizarSuperTefAguardandoConclusao fica true.
     */
    public function concluirVendaAposSuperTefPago(): void
    {
        if (! $this->finalizarSuperTefAguardandoConclusao) {
            return;
        }

        $this->finalizarSuperTefAguardandoConclusao = false;

        if (! $this->finalizarSuperTefConfirmado) {
            return;
        }

        $operacao = $this->finalizarSuperTefPendingOperacao
            ?: $this->finalizarPixPendingOperacao
            ?: $this->finalizarOperacaoPendente
            ?: $this->pdvFinalizarOperacaoUnica;

        if (! filled($operacao)) {
            return;
        }

        $this->finalizarPedidoAposSuperTefPago((string) $operacao);
    }

    /**
     * Grava o PEDIDO/NFC-e após status 4 e só então pergunta impressão (após a venda).
     * Não usa a confirmação de impressão pré-gravação (que deixava intenção paga órfã).
     */
    protected function finalizarPedidoAposSuperTefPago(string $operacao): void
    {
        try {
            // Pagamento SuperTEF já confirmado; NFC-e automática (forma.nfce) força transmitir.
            $operacao = $this->resolverOperacaoFinalizarPdv($operacao);

            if (! $this->operacaoFinalizarPermitidaPdv($operacao)) {
                $this->finalizarSuperTefErro = 'Operação não permitida para este terminal. Pagamento preservado.';
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_APROVADO;

                return;
            }

            // confirmado=true → ensureSuperTef libera; não reabre cobrança.
            if (! $this->validarPreCondicoesFinalizarVenda()) {
                $this->finalizarSuperTefErro = 'Não foi possível validar o pedido após o pagamento. A intenção paga foi preservada — clique em Concluir para tentar de novo.';
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_APROVADO;

                return;
            }

            $this->finalizarPixPendingOperacao = null;
            $this->finalizarSuperTefPendingOperacao = null;

            $perguntarImprimir = $this->pdvConfig()->perguntarImprimir();

            if (PdvFinalizarOperacao::isFiscal($operacao)) {
                // Mesmo padrão do PEDIDO: grava NFC-e e pergunta impressão depois (Sim/Não).
                $askPrintAfter = PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
                    $operacao,
                    $perguntarImprimir,
                );
                $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_FINALIZANDO;
                $this->finalizarSuperTefAberta = false;
                $this->executarFinalizarVendaNfce($operacao, imprimir: ! $askPrintAfter);

                return;
            }

            $askPrintAfter = PdvFinalizarOperacao::solicitaConfirmacaoImpressaoApos(
                $operacao,
                $perguntarImprimir,
            );
            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_FINALIZANDO;
            $this->finalizarSuperTefAberta = false;
            $this->executarFinalizarVendaPedido(imprimir: ! $askPrintAfter, askPrintAfter: $askPrintAfter);
            $this->finalizarSuperTefStatus = 'Pedido concluído';
        } catch (Throwable $e) {
            $this->finalizarSuperTefAberta = true;
            $this->finalizarSuperTefConfirmado = true;
            $this->finalizarSuperTefStatus = self::SUPERTEF_MSG_APROVADO;
            $this->finalizarSuperTefErro = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'Falha ao gravar o pedido. Pagamento preservado — sem nova cobrança.';
            $this->notifyPdvError('SuperTEF', $this->finalizarSuperTefErro);
        }
    }

    protected function validaSuperTefFinalizar(): ?string
    {
        if (! $this->finalizarTemSuperTefComValor()) {
            return null;
        }

        if ($this->finalizarSuperTefFromImport && $this->finalizarSuperTefConfirmado && is_array($this->finalizarSuperTefImportPayload)) {
            return null;
        }

        if (! $this->finalizarSuperTefConfirmado || ! $this->finalizarSuperTefIntencaoId) {
            return 'Aguarde a confirmação do pagamento na maquininha (SuperTEF).';
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
        if (! $intencao || ! $intencao->isPaga()) {
            return 'Pagamento SuperTEF ainda não confirmado.';
        }

        if (filled($intencao->pdv_venda_id)) {
            return 'Este pagamento SuperTEF já está vinculado a outra venda.';
        }

        $valorCupom = $this->finalizarSuperTefTotalValor();
        if (abs((float) $intencao->amount - $valorCupom) >= 0.005) {
            return 'Valor SuperTEF aprovado diverge do cupom. Não conclua — recupere ou reconcilie sem nova cobrança automática.';
        }

        if (! $intencao->podeVincularAoCupom($this->pdvCupomFingerprintSuperTef())) {
            return 'Pagamento SuperTEF não corresponde a este cupom.';
        }

        return null;
    }

    /**
     * Dados de cartão para gravar no pagamento da venda (NSU / auth / bandeira).
     *
     * @return array{nsu: ?string, autorizacao: ?string, bandeira: ?string, maquininha: string, order_id: string, payment_uniqueid: ?int, parcelas?: int}|null
     */
    protected function finalizarSuperTefPayload(): ?array
    {
        if (! $this->finalizarSuperTefConfirmado) {
            return null;
        }

        if ($this->finalizarSuperTefFromImport && is_array($this->finalizarSuperTefImportPayload)) {
            return $this->finalizarSuperTefImportPayload;
        }

        if (! $this->finalizarSuperTefIntencaoId) {
            return null;
        }

        $intencao = TefIntencao::query()->find($this->finalizarSuperTefIntencaoId);
        if (! $intencao || ! $intencao->isPaga() || filled($intencao->pdv_venda_id)) {
            return null;
        }

        $unique = $intencao->payment_uniqueid ? (int) $intencao->payment_uniqueid : null;
        $maq = 'SUPERTEF POS '.(int) $intencao->pos_id;
        if ($unique) {
            $maq .= ' #'.$unique;
        }

        return [
            'nsu' => $intencao->nsu,
            'autorizacao' => $intencao->autorizacao,
            'bandeira' => $intencao->bandeira,
            'maquininha' => $maq,
            'order_id' => (string) $intencao->order_id,
            'payment_uniqueid' => $unique,
            'parcelas' => max(1, (int) $intencao->installment_count),
        ];
    }

    protected function vincularSuperTefAposVenda(int $pdvVendaId, ?int $pdvVendaPagamentoId = null): void
    {
        // Conversão Pedido→NFC-e: intenção permanece no pedido original (sem 2º vínculo / sem 2º POST).
        if ($this->finalizarSuperTefFromImport || ! $this->finalizarSuperTefIntencaoId) {
            return;
        }

        $updated = TefIntencao::query()
            ->where('id', $this->finalizarSuperTefIntencaoId)
            ->whereNull('pdv_venda_id')
            ->update([
                'pdv_venda_id' => $pdvVendaId,
                'pdv_venda_pagamento_id' => $pdvVendaPagamentoId,
            ]);

        if ($updated === 0) {
            throw new \RuntimeException('Pagamento SuperTEF já vinculado a outra venda.');
        }
    }
}
