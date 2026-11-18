<?php

namespace App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns;

use App\Models\ExtratoLancamento;
use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteExtrato;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Tef\TefLiquidacaoLoteOfxVinculoException;
use App\Support\Erp\Tef\TefLiquidacaoLoteOfxVinculoService;
use App\Support\Erp\Tef\TefMoney;
use Filament\Notifications\Notification;

trait ManagesTefLiquidacaoLoteOfxVinculo
{
    public bool $ofxVinculoModalOpen = false;

    public ?int $ofxVinculoLoteId = null;

    /** @var array<string, mixed> */
    public array $ofxVinculoLoteResumo = [];

    /** @var list<array<string, mixed>> */
    public array $ofxCandidatos = [];

    public ?int $ofxSelectedExtratoId = null;

    public string $ofxValorVinculo = '';

    public string $ofxDiferencaMotivo = '';

    public bool $ofxEncerrarCreditoComSobra = false;

    public string $ofxFiltroDe = '';

    public string $ofxFiltroAte = '';

    public string $ofxFiltroQ = '';

    public bool $ofxHistoricoOpen = false;

    /** @var list<array<string, mixed>> */
    public array $ofxHistorico = [];

    public bool $ofxReverterConfirmOpen = false;

    public ?int $ofxReverterVinculoId = null;

    public string $ofxReverterMotivo = '';

    public function abrirOfxVinculo(): void
    {
        if ($this->ofxVinculoModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('tef_liquidacao_lote.update')) {
            Notification::make()->title('Sem permissão para vincular OFX.')->warning()->send();

            return;
        }

        $id = $this->highlightedRecordIdOrNotify('edit');
        if (! $id) {
            return;
        }

        $lote = $this->findLoteEmpresaOfx($id);
        if (! $lote) {
            return;
        }

        if ($lote->situacao !== TefLiquidacaoLote::SIT_CONFERIDO) {
            Notification::make()->title('Somente lote conferido pode vincular OFX.')->warning()->send();

            return;
        }

        $this->ofxVinculoLoteId = (int) $lote->id;
        $this->ofxSelectedExtratoId = null;
        $this->ofxValorVinculo = '';
        $this->ofxDiferencaMotivo = '';
        $this->ofxEncerrarCreditoComSobra = false;
        $this->ofxFiltroDe = $lote->periodo_de?->toDateString() ?? '';
        $this->ofxFiltroAte = $lote->data_prevista_liquidacao?->toDateString()
            ?? $lote->periodo_ate?->toDateString()
            ?? '';
        $this->ofxFiltroQ = '';
        $this->refreshOfxVinculoResumo($lote);
        $this->ofxCandidatos = [];
        $this->ofxVinculoModalOpen = true;
    }

    public function closeOfxVinculoModal(): void
    {
        $this->ofxVinculoModalOpen = false;
        $this->ofxVinculoLoteId = null;
        $this->ofxVinculoLoteResumo = [];
        $this->ofxCandidatos = [];
        $this->ofxSelectedExtratoId = null;
        $this->ofxValorVinculo = '';
        $this->ofxDiferencaMotivo = '';
    }

    public function buscarOfxCandidatos(): void
    {
        $lote = $this->findLoteEmpresaOfx((int) $this->ofxVinculoLoteId);
        if (! $lote) {
            return;
        }

        $svc = app(TefLiquidacaoLoteOfxVinculoService::class);
        $rows = $svc->queryCreditosCandidatos($lote, [
            'data_de' => $this->ofxFiltroDe !== '' ? $this->ofxFiltroDe : null,
            'data_ate' => $this->ofxFiltroAte !== '' ? $this->ofxFiltroAte : null,
            'q' => $this->ofxFiltroQ !== '' ? $this->ofxFiltroQ : null,
        ])->get([
            'id', 'data_movimento', 'valor', 'historico', 'fitid', 'situacao',
        ]);

        $this->ofxCandidatos = $rows->map(function (ExtratoLancamento $e) use ($svc): array {
            $rest = $svc->restanteExtrato($e);
            $sit = $svc->situacaoExtratoTef($e);

            return [
                'id' => (int) $e->id,
                'data' => $e->data_movimento?->format('d/m/Y') ?? '—',
                'valor' => number_format((float) $e->valor, 2, ',', '.'),
                'restante' => number_format((float) $rest, 2, ',', '.'),
                'restante_raw' => $rest,
                'historico' => mb_substr((string) ($e->historico ?? ''), 0, 80),
                'fitid' => (string) ($e->fitid ?? '—'),
                'tef_sit' => TefLiquidacaoLote::ofxSituacaoLabel($sit),
            ];
        })->all();
    }

    public function selecionarOfxExtrato(int $extratoId): void
    {
        $this->ofxSelectedExtratoId = $extratoId > 0 ? $extratoId : null;
        $restLote = (string) ($this->ofxVinculoLoteResumo['restante_raw'] ?? '0');
        $cand = collect($this->ofxCandidatos)->firstWhere('id', $extratoId);
        $restExt = (string) ($cand['restante_raw'] ?? '0');
        $sug = TefMoney::min($restLote, $restExt);
        $this->ofxValorVinculo = number_format((float) $sug, 2, ',', '.');
    }

    public function confirmarOfxVinculo(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $lote = $this->findLoteEmpresaOfx((int) $this->ofxVinculoLoteId);
        if (! $lote || ! $this->ofxSelectedExtratoId) {
            Notification::make()->title('Selecione o crédito OFX.')->warning()->send();

            return;
        }

        $extrato = ExtratoLancamento::query()
            ->where('empresa_id', (int) $lote->empresa_id)
            ->find((int) $this->ofxSelectedExtratoId);

        if (! $extrato) {
            Notification::make()->title('Extrato não encontrado.')->warning()->send();

            return;
        }

        try {
            $r = app(TefLiquidacaoLoteOfxVinculoService::class)->vincular(
                $lote,
                $extrato,
                $this->ofxValorVinculo,
                $this->ofxDiferencaMotivo !== '' ? $this->ofxDiferencaMotivo : null,
                null,
                $this->ofxEncerrarCreditoComSobra,
            );
        } catch (TefLiquidacaoLoteOfxVinculoException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->refreshOfxVinculoResumo($lote->fresh());
        $this->buscarOfxCandidatos();
        $this->ofxSelectedExtratoId = null;
        $this->ofxValorVinculo = '';
        $this->ofxDiferencaMotivo = '';
        $this->resetTable();

        Notification::make()
            ->title('Vínculo OFX #'.$r['vinculo_id'].' gravado ('.$r['lote_ofx_situacao'].') — sem movimento financeiro.')
            ->success()
            ->send();
    }

    public function abrirOfxHistorico(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.access')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('view');
        if (! $id) {
            return;
        }

        $lote = $this->findLoteEmpresaOfx($id);
        if (! $lote) {
            return;
        }

        $this->ofxVinculoLoteId = (int) $lote->id;
        $this->ofxHistorico = TefLiquidacaoLoteExtrato::query()
            ->where('lote_id', $lote->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(static fn (TefLiquidacaoLoteExtrato $v): array => [
                'id' => (int) $v->id,
                'extrato' => (int) $v->extrato_lancamento_id,
                'valor' => number_format((float) $v->valor_vinculado, 2, ',', '.'),
                'diferenca' => number_format((float) $v->diferenca, 2, ',', '.'),
                'motivo' => (string) ($v->diferenca_motivo ?? '—'),
                'status' => (string) $v->status,
                'em' => $v->vinculado_em?->format('d/m/Y H:i') ?? '—',
                'rev' => $v->revertido_em?->format('d/m/Y H:i') ?? '—',
                'ativo' => $v->isAtivo(),
            ])
            ->all();

        $this->ofxHistoricoOpen = true;
    }

    public function closeOfxHistorico(): void
    {
        $this->ofxHistoricoOpen = false;
        $this->ofxHistorico = [];
    }

    public function pedirReverterOfx(int $vinculoId): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.update')) {
            Notification::make()->title('Sem permissão para desfazer.')->warning()->send();

            return;
        }

        $this->ofxReverterVinculoId = $vinculoId;
        $this->ofxReverterMotivo = '';
        $this->ofxReverterConfirmOpen = true;
    }

    public function confirmReverterOfx(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.update')) {
            $this->cancelReverterOfx();

            return;
        }

        $v = TefLiquidacaoLoteExtrato::query()
            ->where('empresa_id', (int) (ErpContext::currentEmpresaId() ?? 0))
            ->find((int) $this->ofxReverterVinculoId);

        if (! $v) {
            Notification::make()->title('Vínculo não encontrado.')->warning()->send();
            $this->cancelReverterOfx();

            return;
        }

        try {
            app(TefLiquidacaoLoteOfxVinculoService::class)->reverter(
                $v,
                $this->ofxReverterMotivo !== '' ? $this->ofxReverterMotivo : 'Reversão manual',
            );
        } catch (TefLiquidacaoLoteOfxVinculoException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
            $this->cancelReverterOfx();

            return;
        }

        $this->cancelReverterOfx();
        if ($this->ofxHistoricoOpen) {
            $this->abrirOfxHistorico();
        }
        if ($this->ofxVinculoModalOpen && $this->ofxVinculoLoteId) {
            $lote = $this->findLoteEmpresaOfx((int) $this->ofxVinculoLoteId);
            if ($lote) {
                $this->refreshOfxVinculoResumo($lote);
                $this->buscarOfxCandidatos();
            }
        }
        $this->resetTable();
        Notification::make()->title('Vínculo revertido (auditoria preservada; sem estorno TEF/caixa).')->success()->send();
    }

    public function cancelReverterOfx(): void
    {
        $this->ofxReverterConfirmOpen = false;
        $this->ofxReverterVinculoId = null;
        $this->ofxReverterMotivo = '';
    }

    protected function refreshOfxVinculoResumo(TefLiquidacaoLote $lote): void
    {
        $svc = app(TefLiquidacaoLoteOfxVinculoService::class);
        $rest = $svc->restanteLote($lote);
        $soma = $svc->somaVinculadaLote((int) $lote->id);

        $this->ofxVinculoLoteResumo = [
            'id' => (int) $lote->id,
            'conta' => (string) ($lote->caixaConta?->nome ?? ('#'.$lote->caixa_conta_id)),
            'liquido' => number_format((float) $lote->valor_liquido, 2, ',', '.'),
            'vinculado' => number_format((float) $soma, 2, ',', '.'),
            'restante' => number_format((float) $rest, 2, ',', '.'),
            'restante_raw' => $rest,
            'ofx_situacao' => TefLiquidacaoLote::ofxSituacaoLabel(
                (string) ($lote->ofx_situacao ?? TefLiquidacaoLote::OFX_PENDENTE)
            ),
            'situacao' => TefLiquidacaoLote::situacaoLabel((string) $lote->situacao),
        ];
    }

    protected function findLoteEmpresaOfx(?int $id): ?TefLiquidacaoLote
    {
        if (! $id) {
            return null;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $lote = TefLiquidacaoLote::query()
            ->with(['caixaConta:id,codigo,nome'])
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (! $lote) {
            Notification::make()->title('Lote não encontrado.')->warning()->send();
        }

        return $lote;
    }
}
