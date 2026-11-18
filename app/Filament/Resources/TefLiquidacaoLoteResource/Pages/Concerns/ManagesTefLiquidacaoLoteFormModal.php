<?php

namespace App\Filament\Resources\TefLiquidacaoLoteResource\Pages\Concerns;

use App\Filament\Resources\TefLiquidacaoLoteResource;
use App\Models\TefIntencao;
use App\Models\TefLiquidacaoDestino;
use App\Models\TefLiquidacaoLote;
use App\Models\Terminal;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Tef\TefLiquidacaoDestinoResolveResult;
use App\Support\Erp\Tef\TefLiquidacaoDestinoResolver;
use App\Support\Erp\Tef\TefLiquidacaoLoteService;
use App\Support\Erp\Tef\TefProviderId;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesTefLiquidacaoLoteFormModal
{
    public bool $loteModalOpen = false;

    /** @var array<string, mixed> */
    public array $loteForm = [];

    /** @var list<int> */
    public array $loteSelectedIds = [];

    /** @var list<array<string, mixed>> */
    public array $loteCandidatas = [];

    public int $loteCandidatasPage = 1;

    public int $loteCandidatasTotal = 0;

    public string $loteDestinoStatus = '';

    public string $loteDestinoLabel = '';

    public function createLote(): void
    {
        if ($this->loteModalOpen) {
            return;
        }

        if (! ErpAccess::currentCan('tef_liquidacao_lote.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        if ((int) (ErpContext::currentEmpresaId() ?? 0) <= 0) {
            Notification::make()->title('Selecione uma empresa.')->warning()->send();

            return;
        }

        $this->loteForm = $this->defaultLoteForm();
        $this->loteSelectedIds = [];
        $this->loteCandidatas = [];
        $this->loteCandidatasPage = 1;
        $this->loteCandidatasTotal = 0;
        $this->loteDestinoStatus = '';
        $this->loteDestinoLabel = '';
        $this->loteModalOpen = true;
    }

    public function closeLoteModal(): void
    {
        $this->loteModalOpen = false;
        $this->loteForm = [];
        $this->loteSelectedIds = [];
        $this->loteCandidatas = [];
        $this->loteDestinoStatus = '';
        $this->loteDestinoLabel = '';
        $this->resetValidation();
    }

    public function updatedLoteFormTerminalId(): void
    {
        $this->refreshDestinoPreview();
        $this->loteSelectedIds = [];
        $this->loteCandidatas = [];
        $this->loteCandidatasTotal = 0;
    }

    public function updatedLoteFormAcquirerCnpj(): void
    {
        $this->refreshDestinoPreview();
    }

    public function buscarCandidatas(int $page = 1): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $terminalId = (int) ($this->loteForm['terminal_id'] ?? 0);
        if ($empresaId <= 0 || $terminalId <= 0) {
            Notification::make()->title('Informe o terminal.')->warning()->send();

            return;
        }

        $this->refreshDestinoPreview();
        $page = max(1, $page);
        $perPage = 25;

        $filtros = [
            'terminal_id' => $terminalId,
            'pago_de' => $this->blank($this->loteForm['pago_de'] ?? null),
            'pago_ate' => $this->blank($this->loteForm['pago_ate'] ?? null),
            'acquirer_cnpj' => TefLiquidacaoDestino::normalizeAcquirerCnpj($this->loteForm['acquirer_cnpj'] ?? ''),
            'somente_sem_acquirer' => (bool) ($this->loteForm['somente_sem_acquirer'] ?? false),
        ];

        $base = app(TefLiquidacaoLoteService::class)->queryCandidatas($empresaId, $filtros);
        $this->loteCandidatasTotal = (clone $base)->count();
        $this->loteCandidatasPage = $page;

        $rows = $base
            ->forPage($page, $perPage)
            ->get([
                'id', 'amount', 'pago_em', 'nsu', 'autorizacao', 'bandeira',
                'acquirer_cnpj', 'installment_count', 'transaction_type', 'pdv_venda_id',
            ]);

        $this->loteCandidatas = $rows->map(function (TefIntencao $i): array {
            return [
                'id' => (int) $i->id,
                'amount' => number_format((float) $i->amount, 2, ',', '.'),
                'amount_raw' => (float) $i->amount,
                'pago_em' => $i->pago_em?->format('d/m/Y H:i') ?? '—',
                'nsu' => (string) ($i->nsu ?? '—'),
                'bandeira' => (string) ($i->bandeira ?? '—'),
                'parcelas' => (int) ($i->installment_count ?: 1),
                'cnpj' => filled($i->acquirer_cnpj) ? (string) $i->acquirer_cnpj : '—',
                'venda' => (int) ($i->pdv_venda_id ?? 0),
            ];
        })->all();
    }

    public function toggleCandidata(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        if (in_array($id, $this->loteSelectedIds, true)) {
            $this->loteSelectedIds = array_values(array_filter(
                $this->loteSelectedIds,
                static fn (int $x): bool => $x !== $id
            ));

            return;
        }
        if (count($this->loteSelectedIds) >= TefLiquidacaoLoteService::MAX_ITENS_POR_LOTE) {
            Notification::make()->title('Limite de itens no lote.')->warning()->send();

            return;
        }
        $this->loteSelectedIds[] = $id;
    }

    public function resumoSelecao(): array
    {
        $bruto = 0.0;
        foreach ($this->loteCandidatas as $row) {
            if (in_array((int) $row['id'], $this->loteSelectedIds, true)) {
                $bruto += (float) ($row['amount_raw'] ?? 0);
            }
        }
        // Itens selecionados fora da página atual: não somamos amount (evita carregar tudo).
        // O service recalcula no save; UI mostra bruto da página + contagem total.
        $taxas = $this->parseMoney($this->loteForm['valor_taxas'] ?? '0');
        $bruto = round($bruto, 2);

        return [
            'qtd' => count($this->loteSelectedIds),
            'bruto_pagina' => $bruto,
            'taxas' => $taxas,
            'liquido_pagina' => round(max(0, $bruto - $taxas), 2),
        ];
    }

    public function saveLote(): void
    {
        if (! ErpAccess::currentCan('tef_liquidacao_lote.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $this->validate([
            'loteForm.terminal_id' => ['required', 'integer'],
            'loteForm.valor_taxas' => ['nullable'],
            'loteForm.data_prevista_liquidacao' => ['nullable', 'date'],
            'loteSelectedIds' => ['required', 'array', 'min:1'],
        ], [], [
            'loteForm.terminal_id' => 'terminal',
            'loteSelectedIds' => 'transações',
        ]);

        if ($this->loteDestinoStatus !== TefLiquidacaoDestinoResolveResult::STATUS_OK) {
            Notification::make()
                ->title('Destino bancário ausente ou ambíguo — configure em Destinos SuperTEF.')
                ->warning()
                ->send();

            return;
        }

        $selecao = array_map(
            static fn (int $id): array => ['tef_intencao_id' => $id, 'parcela_numero' => 1],
            $this->loteSelectedIds
        );

        // Parcelados: inclui parcela 1 por padrão (liquidações futuras por parcela).
        try {
            $taxas = $this->parseMoney($this->loteForm['valor_taxas'] ?? 0);
            $lote = app(TefLiquidacaoLoteService::class)->criarLote($empresaId, $selecao, [
                'terminal_id' => (int) $this->loteForm['terminal_id'],
                'acquirer_cnpj' => $this->loteForm['acquirer_cnpj'] ?? '',
                'valor_taxas' => $taxas,
                'taxa_origem' => $taxas > 0
                    ? TefLiquidacaoLote::TAXA_INFORMADA
                    : TefLiquidacaoLote::TAXA_NAO_INFORMADA,
                'data_prevista_liquidacao' => $this->blank($this->loteForm['data_prevista_liquidacao'] ?? null),
                'identificador_externo' => $this->blank($this->loteForm['identificador_externo'] ?? null),
                'observacao' => $this->blank($this->loteForm['observacao'] ?? null),
                'situacao' => TefLiquidacaoLote::SIT_AGUARDANDO,
                'periodo_de' => $this->blank($this->loteForm['pago_de'] ?? null),
                'periodo_ate' => $this->blank($this->loteForm['pago_ate'] ?? null),
            ]);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $id = (int) $lote->getKey();
        $this->closeLoteModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);

        Notification::make()->title('Lote #'.$id.' criado (controle — sem movimento financeiro).')->success()->send();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function loteTerminalOptions(): array
    {
        return TefLiquidacaoLoteResource::terminaisQuery()
            ->get(['id', 'nome', 'numero_logico_terminal'])
            ->map(fn (Terminal $t): array => [
                'id' => (int) $t->id,
                'label' => trim(($t->numero_logico_terminal ? $t->numero_logico_terminal.' — ' : '').$t->nome),
            ])
            ->values()
            ->all();
    }

    protected function refreshDestinoPreview(): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $terminalId = (int) ($this->loteForm['terminal_id'] ?? 0);
        $cnpj = TefLiquidacaoDestino::normalizeAcquirerCnpj($this->loteForm['acquirer_cnpj'] ?? '');

        if ($empresaId <= 0 || $terminalId <= 0) {
            $this->loteDestinoStatus = '';
            $this->loteDestinoLabel = 'Informe o terminal.';

            return;
        }

        $r = app(TefLiquidacaoDestinoResolver::class)->resolve(
            $empresaId,
            $terminalId,
            $cnpj !== '' ? $cnpj : null,
            TefProviderId::SUPERTEF,
        );
        $this->loteDestinoStatus = $r->status;
        if ($r->isOk()) {
            $conta = $r->destino?->caixaConta;
            $this->loteDestinoLabel = trim(
                ($conta?->codigo ? $conta->codigo.' — ' : '').($conta?->nome ?? 'Conta #'.$r->destino->caixa_conta_id)
            );
        } else {
            $this->loteDestinoLabel = ($r->motivo ?? $r->status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultLoteForm(): array
    {
        return [
            'terminal_id' => '',
            'acquirer_cnpj' => '',
            'somente_sem_acquirer' => false,
            'pago_de' => '',
            'pago_ate' => '',
            'valor_taxas' => '0,00',
            'data_prevista_liquidacao' => '',
            'identificador_externo' => '',
            'observacao' => '',
        ];
    }

    protected function blank(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $t = trim((string) $value);

        return $t === '' ? null : $t;
    }

    protected function parseMoney(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        $s = str_replace(['.', ' '], ['', ''], (string) $value);
        $s = str_replace(',', '.', $s);

        return round((float) $s, 2);
    }
}
