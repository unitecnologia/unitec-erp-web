<?php

namespace App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns;

use App\Models\TefLiquidacaoLote;
use App\Models\TefLiquidacaoLoteItem;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Tef\TefLiquidacaoLoteService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesTefLiquidacaoLoteActions
{
    public bool $loteDetalheOpen = false;

    public ?int $loteDetalheId = null;

    /** @var list<array<string, mixed>> */
    public array $loteDetalheItens = [];

    /** @var array<string, mixed> */
    public array $loteDetalheResumo = [];

    public bool $cancelConfirmOpen = false;

    public ?int $cancelConfirmId = null;

    public string $cancelConfirmNome = '';

    public function verLote(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.access')) {
            return;
        }

        $id = $this->highlightedRecordIdOrNotify('view');
        if (! $id) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $lote = TefLiquidacaoLote::query()
            ->with(['terminal:id,nome', 'caixaConta:id,codigo,nome'])
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (! $lote) {
            Notification::make()->title('Lote não encontrado.')->warning()->send();

            return;
        }

        $this->loteDetalheId = (int) $lote->id;
        $this->loteDetalheResumo = [
            'id' => (int) $lote->id,
            'terminal' => (string) ($lote->terminal?->nome ?? '—'),
            'conta' => trim(($lote->caixaConta?->codigo ? $lote->caixaConta->codigo.' — ' : '').($lote->caixaConta?->nome ?? '—')),
            'adquirente' => filled($lote->acquirer_cnpj) ? (string) $lote->acquirer_cnpj : 'NÃO IDENTIFICADA',
            'bruto' => number_format((float) $lote->valor_bruto, 2, ',', '.'),
            'taxas' => number_format((float) $lote->valor_taxas, 2, ',', '.'),
            'liquido' => number_format((float) $lote->valor_liquido, 2, ',', '.'),
            'taxa_origem' => (string) $lote->taxa_origem,
            'situacao' => TefLiquidacaoLote::situacaoLabel((string) $lote->situacao),
            'prevista' => $lote->data_prevista_liquidacao?->format('d/m/Y') ?? '—',
        ];

        $this->loteDetalheItens = TefLiquidacaoLoteItem::query()
            ->where('lote_id', $lote->id)
            ->orderBy('id')
            ->limit(200)
            ->get([
                'id', 'tef_intencao_id', 'valor_bruto', 'valor_taxa', 'valor_liquido',
                'nsu', 'parcela_numero', 'installment_count', 'pago_em',
            ])
            ->map(static fn (TefLiquidacaoLoteItem $i): array => [
                'id' => (int) $i->id,
                'intencao' => (int) $i->tef_intencao_id,
                'bruto' => number_format((float) $i->valor_bruto, 2, ',', '.'),
                'taxa' => number_format((float) $i->valor_taxa, 2, ',', '.'),
                'liquido' => number_format((float) $i->valor_liquido, 2, ',', '.'),
                'nsu' => (string) ($i->nsu ?? '—'),
                'parcela' => ((int) $i->parcela_numero).'/'.((int) $i->installment_count),
                'pago_em' => $i->pago_em?->format('d/m/Y') ?? '—',
            ])
            ->all();

        $this->loteDetalheOpen = true;
    }

    public function closeLoteDetalhe(): void
    {
        $this->loteDetalheOpen = false;
        $this->loteDetalheId = null;
        $this->loteDetalheItens = [];
        $this->loteDetalheResumo = [];
    }

    public function marcarConferido(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $id = $this->highlightedRecordIdOrNotify('edit');
        if (! $id) {
            return;
        }

        $lote = $this->findLoteEmpresa($id);
        if (! $lote) {
            return;
        }

        try {
            app(TefLiquidacaoLoteService::class)->alterarSituacao($lote, TefLiquidacaoLote::SIT_CONFERIDO);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->resetTable();
        Notification::make()->title('Lote marcado como conferido (sem movimento financeiro).')->success()->send();
    }

    public function cancelarLoteAsk(): void
    {
        if ($this->cancelConfirmOpen) {
            return;
        }

        if (! ErpAccess::currentCan('tef_liquidacao_lote.cancel')) {
            Notification::make()->title('Sem permissão para cancelar.')->warning()->send();

            return;
        }

        $id = $this->highlightedRecordIdOrNotify('delete');
        if (! $id) {
            return;
        }

        $lote = $this->findLoteEmpresa($id);
        if (! $lote) {
            return;
        }

        if ($lote->situacao === TefLiquidacaoLote::SIT_CANCELADO) {
            Notification::make()->title('Lote já cancelado.')->warning()->send();

            return;
        }

        $this->cancelConfirmId = (int) $lote->id;
        $this->cancelConfirmNome = 'Lote #'.$lote->id.' — R$ '.number_format((float) $lote->valor_bruto, 2, ',', '.');
        $this->cancelConfirmOpen = true;
    }

    public function confirmCancelarLote(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.cancel')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->cancelCancelarLote();

            return;
        }

        $lote = $this->findLoteEmpresa((int) $this->cancelConfirmId);
        if ($lote) {
            try {
                app(TefLiquidacaoLoteService::class)->cancelarLote($lote);
            } catch (InvalidArgumentException $e) {
                Notification::make()->title($e->getMessage())->danger()->send();
                $this->cancelCancelarLote();

                return;
            }
        }

        $this->cancelCancelarLote();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Lote cancelado (intenções liberadas; sem movimento financeiro).')->success()->send();
    }

    public function cancelCancelarLote(): void
    {
        $this->cancelConfirmOpen = false;
        $this->cancelConfirmId = null;
        $this->cancelConfirmNome = '';
    }

    protected function findLoteEmpresa(?int $id): ?TefLiquidacaoLote
    {
        if (! $id) {
            return null;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $lote = TefLiquidacaoLote::query()
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (! $lote) {
            Notification::make()->title('Lote não encontrado.')->warning()->send();
        }

        return $lote;
    }
}
