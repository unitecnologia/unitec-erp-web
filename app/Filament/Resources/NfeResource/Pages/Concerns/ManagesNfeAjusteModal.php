<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Nfe;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfe\NfeEstornoAjusteService;
use Carbon\Carbon;
use Filament\Notifications\Notification;

trait ManagesNfeAjusteModal
{
    public bool $nfeAjusteTipoOpen = false;

    public bool $nfeAjusteEstornoBuscaOpen = false;

    public string $nfeAjusteBuscaTermo = '';

    public string $nfeAjusteBuscaDataDe = '';

    public string $nfeAjusteBuscaDataAte = '';

    public string $nfeAjusteBuscaCliente = '';

    public int $nfeAjusteBuscaPage = 1;

    public int $nfeAjusteBuscaLastPage = 1;

    public int $nfeAjusteBuscaTotal = 0;

    /** @var list<array<string, mixed>> */
    public array $nfeAjusteBuscaResults = [];

    public ?int $nfeAjusteBuscaSelectedIndex = null;

    public ?int $nfeAjusteBuscaSelectedId = null;

    public string $nfeAjusteFinalidadeAnterior = 'normal';

    public string $nfeAjusteFinalidadeTracked = 'normal';

    public bool $nfeAjusteApplyingEstorno = false;

    public function updatedNfeFormFinalidade(?string $value = null): void
    {
        $novo = strtolower(trim((string) ($value ?? ($this->nfeForm['finalidade'] ?? 'normal'))));
        $anterior = $this->nfeAjusteFinalidadeTracked !== ''
            ? $this->nfeAjusteFinalidadeTracked
            : 'normal';
        $this->nfeAjusteFinalidadeTracked = $novo !== '' ? $novo : 'normal';

        if ($this->nfeAjusteApplyingEstorno) {
            return;
        }

        if ($novo !== 'ajuste') {
            $this->closeNfeAjusteModals(revertFinalidade: false);

            return;
        }

        if (! ($this->nfeModalOpen ?? false)) {
            return;
        }

        $this->nfeAjusteFinalidadeAnterior = $anterior === 'ajuste' ? 'normal' : $anterior;
        $this->openNfeAjusteTipoModal();
    }

    public function openNfeAjusteTipoModal(): void
    {
        $this->nfeAjusteEstornoBuscaOpen = false;
        $this->nfeAjusteTipoOpen = true;
    }

    public function closeNfeAjusteTipoModal(bool $revertFinalidade = true): void
    {
        $this->nfeAjusteTipoOpen = false;

        if ($revertFinalidade && ($this->nfeForm['finalidade'] ?? '') === 'ajuste' && ! $this->nfeAjusteApplyingEstorno) {
            $prev = $this->nfeAjusteFinalidadeAnterior !== '' ? $this->nfeAjusteFinalidadeAnterior : 'normal';
            $this->nfeAjusteFinalidadeTracked = $prev;
            $this->nfeForm['finalidade'] = $prev;
        }
    }

    public function closeNfeAjusteModals(bool $revertFinalidade = true): void
    {
        $this->nfeAjusteTipoOpen = false;
        $this->nfeAjusteEstornoBuscaOpen = false;
        $this->resetNfeAjusteBuscaState();

        if ($revertFinalidade && ($this->nfeForm['finalidade'] ?? '') === 'ajuste' && ! $this->nfeAjusteApplyingEstorno) {
            $prev = $this->nfeAjusteFinalidadeAnterior !== '' ? $this->nfeAjusteFinalidadeAnterior : 'normal';
            $this->nfeAjusteFinalidadeTracked = $prev;
            $this->nfeForm['finalidade'] = $prev;
        }
    }

    public function selecionarNfeAjusteTipoEstorno(): void
    {
        $this->nfeAjusteTipoOpen = false;
        $this->nfeAjusteEstornoBuscaOpen = true;
        $this->nfeAjusteBuscaTermo = '';
        $this->nfeAjusteBuscaCliente = '';
        $this->nfeAjusteBuscaPage = 1;
        $this->nfeAjusteBuscaSelectedIndex = null;
        $this->nfeAjusteBuscaSelectedId = null;
        $this->aplicarPeriodoNfeAjusteMesAtual(pesquisar: false);
        $this->pesquisarNfeAjusteEstorno();
    }

    public function updatedNfeAjusteBuscaTermo(): void
    {
        $this->nfeAjusteBuscaPage = 1;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function updatedNfeAjusteBuscaDataDe(): void
    {
        $this->nfeAjusteBuscaPage = 1;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function updatedNfeAjusteBuscaDataAte(): void
    {
        $this->nfeAjusteBuscaPage = 1;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function updatedNfeAjusteBuscaCliente(): void
    {
        $this->nfeAjusteBuscaPage = 1;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function aplicarPeriodoNfeAjusteMesAtual(bool $pesquisar = true): void
    {
        $hoje = Carbon::parse(ErpTimezone::today(), ErpTimezone::DEFAULT);
        $this->nfeAjusteBuscaDataDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->nfeAjusteBuscaDataAte = $hoje->copy()->endOfMonth()->toDateString();
        $this->nfeAjusteBuscaPage = 1;

        if ($pesquisar) {
            $this->pesquisarNfeAjusteEstorno();
        }
    }

    public function aplicarPeriodoNfeAjusteMesAnterior(): void
    {
        $base = Carbon::parse(ErpTimezone::today(), ErpTimezone::DEFAULT)->subMonthNoOverflow();
        $this->nfeAjusteBuscaDataDe = $base->copy()->startOfMonth()->toDateString();
        $this->nfeAjusteBuscaDataAte = $base->copy()->endOfMonth()->toDateString();
        $this->nfeAjusteBuscaPage = 1;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function aplicarPeriodoNfeAjusteTodo(): void
    {
        $this->nfeAjusteBuscaDataDe = '';
        $this->nfeAjusteBuscaDataAte = '';
        $this->nfeAjusteBuscaPage = 1;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function pesquisarNfeAjusteEstorno(): void
    {
        $empresaId = $this->resolveEmpresaIdForAjuste();

        if ($empresaId <= 0) {
            $this->nfeAjusteBuscaResults = [];
            $this->nfeAjusteBuscaTotal = 0;
            $this->nfeAjusteBuscaLastPage = 1;
            $this->nfeAjusteBuscaSelectedIndex = null;

            return;
        }

        $resultado = app(NfeEstornoAjusteService::class)->buscarSaidasAutorizadas(
            empresaId: $empresaId,
            termo: $this->nfeAjusteBuscaTermo,
            dataDe: $this->nfeAjusteBuscaDataDe !== '' ? $this->nfeAjusteBuscaDataDe : null,
            dataAte: $this->nfeAjusteBuscaDataAte !== '' ? $this->nfeAjusteBuscaDataAte : null,
            cliente: $this->nfeAjusteBuscaCliente,
            page: $this->nfeAjusteBuscaPage,
        );

        $this->nfeAjusteBuscaResults = $resultado['rows'];
        $this->nfeAjusteBuscaPage = $resultado['page'];
        $this->nfeAjusteBuscaLastPage = $resultado['last_page'];
        $this->nfeAjusteBuscaTotal = $resultado['total'];

        $this->sincronizarSelecaoNfeAjusteBusca();
    }

    public function irParaPaginaNfeAjusteBusca(int $page): void
    {
        $page = max(1, min($page, max(1, $this->nfeAjusteBuscaLastPage)));

        if ($page === $this->nfeAjusteBuscaPage) {
            return;
        }

        $this->nfeAjusteBuscaPage = $page;
        $this->pesquisarNfeAjusteEstorno();
    }

    public function selectNfeAjusteBuscaRow(int $index): void
    {
        if (! isset($this->nfeAjusteBuscaResults[$index])) {
            return;
        }

        $this->nfeAjusteBuscaSelectedIndex = $index;
        $this->nfeAjusteBuscaSelectedId = (int) ($this->nfeAjusteBuscaResults[$index]['id'] ?? 0) ?: null;
    }

    public function moveNfeAjusteBuscaSelection(int $delta): void
    {
        if ($this->nfeAjusteBuscaResults === []) {
            return;
        }

        $atual = $this->nfeAjusteBuscaSelectedIndex ?? 0;
        $novo = max(0, min(count($this->nfeAjusteBuscaResults) - 1, $atual + $delta));
        $this->selectNfeAjusteBuscaRow($novo);
    }

    public function confirmarNfeAjusteEstorno(): void
    {
        $origemId = (int) ($this->nfeAjusteBuscaSelectedId ?? 0);

        if ($origemId <= 0) {
            $index = $this->nfeAjusteBuscaSelectedIndex;
            if ($index !== null && isset($this->nfeAjusteBuscaResults[$index])) {
                $origemId = (int) ($this->nfeAjusteBuscaResults[$index]['id'] ?? 0);
            }
        }

        if ($origemId <= 0) {
            Notification::make()
                ->title('Selecione a NF-e de saída autorizada para estornar.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = $this->resolveEmpresaIdForAjuste();

        $origem = Nfe::query()
            ->with(['itens.product', 'cliente'])
            ->whereKey($origemId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $origem) {
            Notification::make()
                ->title('NF-e original não encontrada na empresa logada.')
                ->danger()
                ->send();

            return;
        }

        if ($this->nfeModalRecordId && (int) $this->nfeModalRecordId === (int) $origem->id) {
            Notification::make()
                ->title('Não é possível estornar a própria NF-e aberta.')
                ->warning()
                ->send();

            return;
        }

        try {
            $payload = app(NfeEstornoAjusteService::class)->prepararLancamento($origem);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível preparar o estorno.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->aplicarPayloadEstornoAjuste($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function aplicarPayloadEstornoAjuste(array $payload): void
    {
        $this->nfeAjusteApplyingEstorno = true;

        try {
            $this->nfeModalVendaId = null;
            $this->nfeModalPdvVendaId = null;
            $this->nfeModalDevolucaoCompraId = null;
            $this->nfeModalOrdemServicoId = null;
            $this->nfeModalOrcamentoIds = [];

            $this->nfeForm['finalidade'] = 'ajuste';
            $this->nfeAjusteFinalidadeTracked = 'ajuste';
            $this->nfeForm['movimento'] = 'entrada';
            $this->nfeForm['natureza_operacao'] = (string) $payload['natureza_operacao'];
            $this->nfeForm['obs_fisco'] = (string) $payload['obs_fisco'];
            $this->nfeForm['forma_pgto'] = (string) ($payload['forma_pgto'] ?? 'outros');
            $this->nfeForm['meio_pgto'] = $this->normalizeNfeMeioPgto($payload['meio_pgto'] ?? 'sem_pagamento');
            $this->nfeForm['data_emissao'] = (string) $payload['data_emissao'];
            $this->nfeForm['data_saida'] = (string) $payload['data_saida'];
            $this->nfeForm['natureza_operacao'] = (string) $payload['natureza_operacao'];

            if (filled($payload['cliente_id'] ?? null)) {
                $this->nfeForm['cliente_id'] = (string) (int) $payload['cliente_id'];
                $this->updatedNfeFormClienteId();
            }

            $this->nfeModalReferencias = $payload['referencias'] ?? [];
            $this->nfeModalRows = $payload['rows'] ?? [];
            // Estorno AJUSTE: sem duplicatas/cobrança (XML com tPag=90 / vPag=0).
            $this->nfeModalFaturas = [];
            $this->nfeSelectedRowIndex = $this->nfeModalRows === [] ? null : 0;
            $this->nfeModalMainTab = 'itens';
            $this->nfeModalDetailTab = 'fisco';
            $this->clearNfeItemEntryRow();
            $this->recalculateNfeTotais();
            $this->syncNfeSelectedRowFotoFromGrid();

            $this->nfeAjusteTipoOpen = false;
            $this->nfeAjusteEstornoBuscaOpen = false;
            $this->resetNfeAjusteBuscaState();
        } finally {
            $this->nfeAjusteApplyingEstorno = false;
        }

        Notification::make()
            ->title('Estorno preparado para revisão.')
            ->body('NF-e original nº '.($payload['origem_numero'] ?? '—').'. Confira os dados e grave antes de transmitir.')
            ->success()
            ->send();
    }

    protected function sincronizarSelecaoNfeAjusteBusca(): void
    {
        if ($this->nfeAjusteBuscaResults === []) {
            $this->nfeAjusteBuscaSelectedIndex = null;

            return;
        }

        if ($this->nfeAjusteBuscaSelectedId) {
            foreach ($this->nfeAjusteBuscaResults as $index => $row) {
                if ((int) ($row['id'] ?? 0) === (int) $this->nfeAjusteBuscaSelectedId) {
                    $this->nfeAjusteBuscaSelectedIndex = (int) $index;

                    return;
                }
            }
        }

        $this->selectNfeAjusteBuscaRow(0);
    }

    protected function resetNfeAjusteBuscaState(): void
    {
        $this->nfeAjusteBuscaTermo = '';
        $this->nfeAjusteBuscaDataDe = '';
        $this->nfeAjusteBuscaDataAte = '';
        $this->nfeAjusteBuscaCliente = '';
        $this->nfeAjusteBuscaPage = 1;
        $this->nfeAjusteBuscaLastPage = 1;
        $this->nfeAjusteBuscaTotal = 0;
        $this->nfeAjusteBuscaResults = [];
        $this->nfeAjusteBuscaSelectedIndex = null;
        $this->nfeAjusteBuscaSelectedId = null;
    }

    protected function resolveEmpresaIdForAjuste(): int
    {
        if (method_exists($this, 'resolveEmpresaId')) {
            return (int) ($this->resolveEmpresaId() ?? 0);
        }

        return (int) (ErpContext::currentEmpresaId() ?? 0);
    }

    protected function syncNfeAjusteFinalidadeTrackedFromForm(): void
    {
        $this->nfeAjusteFinalidadeTracked = strtolower(trim((string) ($this->nfeForm['finalidade'] ?? 'normal'))) ?: 'normal';
    }
}
