<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Support\Erp\Nfe\NfeImportacaoService;
use App\Support\Erp\Nfe\NfeImportacaoTipo;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;

trait ManagesNfeImportacao
{
    public bool $nfeImportMenuOpen = false;

    public string $nfeImportMenuNumero = '';

    public bool $nfeImportListOpen = false;

    public string $nfeImportTipo = '';

    /** @var array<int, array<string, mixed>> */
    public array $nfeImportResults = [];

    public ?int $nfeImportSelectedIndex = null;

    /** @var list<int> IDs marcados para importação (multi-seleção). */
    public array $nfeImportMarkedIds = [];

    public string $nfeImportNumero = '';

    public string $nfeImportCliente = '';

    public string $nfeImportDataDe = '';

    public string $nfeImportDataAte = '';

    /** @var array<string, mixed>|null */
    public ?array $nfeImportDetalhe = null;

    public function importNfeModal(): void
    {
        if (! $this->nfeModalOpen || $this->nfeImportMenuOpen || $this->nfeImportListOpen) {
            return;
        }

        if ($this->nfeModalStatus !== 'ABERTA') {
            Notification::make()
                ->title('Somente NF-e em aberto permite importação.')
                ->warning()
                ->send();

            return;
        }

        $this->nfeImportMenuNumero = '';
        $this->nfeImportMenuOpen = true;
        $this->dispatch('erp-nfe-focus-import-menu');
    }

    public function closeNfeImportMenu(): void
    {
        $this->nfeImportMenuOpen = false;
        $this->nfeImportMenuNumero = '';
    }

    public function closeNfeImportList(): void
    {
        $this->nfeImportListOpen = false;
        $this->nfeImportTipo = '';
        $this->nfeImportResults = [];
        $this->nfeImportSelectedIndex = null;
        $this->nfeImportMarkedIds = [];
        $this->nfeImportNumero = '';
        $this->nfeImportCliente = '';
        $this->nfeImportDataDe = '';
        $this->nfeImportDataAte = '';
        $this->nfeImportDetalhe = null;
    }

    public function closeNfeImportModals(): void
    {
        $this->closeNfeImportMenu();
        $this->closeNfeImportList();
    }

    public function openNfeImportList(string $tipo): void
    {
        if (! NfeImportacaoTipo::isImplemented($tipo)) {
            Notification::make()
                ->title(NfeImportacaoTipo::label($tipo) . ' em desenvolvimento.')
                ->warning()
                ->send();

            return;
        }

        $hoje = now()->toDateString();

        $this->nfeImportMenuOpen = false;
        $this->nfeImportTipo = $tipo;
        $this->nfeImportNumero = mb_strtoupper(trim($this->nfeImportMenuNumero), 'UTF-8');
        $this->nfeImportCliente = '';
        $this->nfeImportDataDe = $hoje;
        $this->nfeImportDataAte = $hoje;
        $this->nfeImportSelectedIndex = null;
        $this->nfeImportMarkedIds = [];
        $this->nfeImportDetalhe = null;
        $this->nfeImportListOpen = true;
        $this->refreshNfeImportResults();
        $this->dispatch('erp-nfe-focus-import-list');
    }

    public function openNfeImportListFromHotkey(string $hotkey): void
    {
        $tipo = NfeImportacaoTipo::fromHotkey($hotkey);

        if ($tipo === null) {
            return;
        }

        $this->openNfeImportList($tipo);
    }

    public function updatedNfeImportNumero(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->nfeImportNumero !== $upper) {
            $this->nfeImportNumero = $upper;
        }

        $this->refreshNfeImportResults();
    }

    public function updatedNfeImportCliente(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->nfeImportCliente !== $upper) {
            $this->nfeImportCliente = $upper;
        }

        $this->refreshNfeImportResults();
    }

    public function updatedNfeImportDataDe(): void
    {
        $this->refreshNfeImportResults();
    }

    public function updatedNfeImportDataAte(): void
    {
        $this->refreshNfeImportResults();
    }

    public function refreshNfeImportResults(): void
    {
        if (! $this->nfeImportListOpen || $this->nfeImportTipo === '') {
            return;
        }

        $service = app(NfeImportacaoService::class);
        $previousId = $this->resolveNfeImportFocusedId();
        $markedBefore = $this->nfeImportMarkedIds;

        $this->nfeImportResults = $service->listar(
            $this->nfeImportTipo,
            $this->resolveEmpresaId(),
            [
                'numero' => $this->nfeImportNumero,
                'cliente' => $this->nfeImportCliente,
                'data_de' => $this->parseNfeImportDate($this->nfeImportDataDe),
                'data_ate' => $this->parseNfeImportDate($this->nfeImportDataAte),
            ],
        );

        $visibleIds = collect($this->nfeImportResults)
            ->map(fn (array $row): int => (int) ($row['document_id'] ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->all();

        $this->nfeImportMarkedIds = array_values(array_intersect($markedBefore, $visibleIds));
        $this->nfeImportSelectedIndex = null;

        if ($previousId > 0) {
            foreach ($this->nfeImportResults as $idx => $row) {
                if ((int) ($row['document_id'] ?? 0) === $previousId) {
                    $this->nfeImportSelectedIndex = $idx;
                    break;
                }
            }
        }

        $this->loadNfeImportDetalhe();
    }

    public function selectNfeImportRow(int $index): void
    {
        if (! isset($this->nfeImportResults[$index])) {
            return;
        }

        $this->nfeImportSelectedIndex = $index;
        $this->toggleNfeImportMarkAt($index);
        $this->loadNfeImportDetalhe();
    }

    public function focusNfeImportRow(int $index): void
    {
        if (! isset($this->nfeImportResults[$index])) {
            return;
        }

        $this->nfeImportSelectedIndex = $index;
        $this->loadNfeImportDetalhe();
    }

    public function toggleNfeImportMarkAt(int $index): void
    {
        $row = $this->nfeImportResults[$index] ?? null;
        $documentId = (int) ($row['document_id'] ?? 0);

        if ($documentId <= 0) {
            return;
        }

        if (in_array($documentId, $this->nfeImportMarkedIds, true)) {
            $this->nfeImportMarkedIds = array_values(array_filter(
                $this->nfeImportMarkedIds,
                static fn (int $id): bool => $id !== $documentId,
            ));

            return;
        }

        $this->nfeImportMarkedIds[] = $documentId;
    }

    public function toggleNfeImportMarkFocused(): void
    {
        if ($this->nfeImportSelectedIndex === null) {
            return;
        }

        $this->toggleNfeImportMarkAt((int) $this->nfeImportSelectedIndex);
    }

    public function isNfeImportRowMarked(int $index): bool
    {
        $row = $this->nfeImportResults[$index] ?? null;
        $documentId = (int) ($row['document_id'] ?? 0);

        return $documentId > 0 && in_array($documentId, $this->nfeImportMarkedIds, true);
    }

    public function moveNfeImportSelection(int $delta): void
    {
        if ($this->nfeImportResults === []) {
            return;
        }

        $count = count($this->nfeImportResults);

        if ($this->nfeImportSelectedIndex === null) {
            $this->focusNfeImportRow($delta >= 0 ? 0 : $count - 1);

            return;
        }

        $next = (int) $this->nfeImportSelectedIndex + $delta;

        if ($next < 0) {
            $next = $count - 1;
        }

        if ($next >= $count) {
            $next = 0;
        }

        $this->focusNfeImportRow($next);
    }

    public function confirmNfeImportDocument(): void
    {
        if (! $this->nfeImportListOpen) {
            return;
        }

        $documentIds = $this->resolveNfeImportDocumentIdsForConfirm();

        if ($documentIds === []) {
            Notification::make()
                ->title('Selecione um ou mais pedidos para importar.')
                ->warning()
                ->send();

            return;
        }

        $empresaId = $this->resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        $payload = app(NfeImportacaoService::class)->montarPayloadMultiplo(
            $this->nfeImportTipo,
            $documentIds,
            $empresa,
            $this->nfeForm['uf'] ?? null,
        );

        if ($payload['rows'] === []) {
            Notification::make()
                ->title('Os documentos selecionados não possuem itens importáveis.')
                ->warning()
                ->send();

            return;
        }

        if (filled($payload['cliente_id'])) {
            $this->nfeForm['cliente_id'] = (int) $payload['cliente_id'];
            $this->updatedNfeFormClienteId();
        }

        if (filled($payload['numero_pedido'])) {
            $this->nfeForm['numero_pedido'] = (string) $payload['numero_pedido'];
        }

        $this->nfeForm['movimento'] = $payload['movimento'] ?? 'saida';
        $this->nfeForm['forma_pgto'] = $payload['forma_pgto'] ?? 'a_vista';
        $this->nfeForm['meio_pgto'] = $payload['meio_pgto'] ?? 'dinheiro';

        if (filled($payload['obs_contribuinte'])) {
            $existingObs = trim((string) ($this->nfeForm['obs_contribuinte'] ?? ''));
            $importedObs = mb_strtoupper((string) $payload['obs_contribuinte'], 'UTF-8');
            $this->nfeForm['obs_contribuinte'] = $existingObs !== ''
                ? mb_strtoupper(trim($existingObs . ' ' . $importedObs), 'UTF-8')
                : $importedObs;
        }

        foreach ($payload['referencias'] as $referencia) {
            $chave = trim((string) ($referencia['referencia'] ?? ''));

            if ($chave === '') {
                continue;
            }

            $exists = collect($this->nfeModalReferencias)
                ->contains(fn (array $ref): bool => ($ref['referencia'] ?? '') === $chave);

            if (! $exists) {
                $this->nfeModalReferencias[] = ['referencia' => $chave];
            }
        }

        $this->nfeModalRows = $payload['rows'];
        $this->nfeSelectedRowIndex = 0;
        $this->nfeModalMainTab = 'itens';
        $this->clearNfeItemEntryRow();
        $this->recalculateNfeTotais();

        $qtd = count($payload['numeros'] ?? $documentIds);
        $label = NfeImportacaoTipo::label($this->nfeImportTipo);

        $this->closeNfeImportModals();

        Notification::make()
            ->title($qtd > 1
                ? $qtd . ' ' . mb_strtolower($label, 'UTF-8') . 's importados para a NF-e.'
                : $label . ' importado para a NF-e.')
            ->body($qtd > 1
                ? 'Números: ' . implode(', ', $payload['numeros'] ?? [])
                : null)
            ->success()
            ->send();

        $this->dispatch('erp-nfe-focus-item-codigo');
    }

    public function nfeImportTipoLabel(): string
    {
        return $this->nfeImportTipo !== ''
            ? NfeImportacaoTipo::label($this->nfeImportTipo)
            : '';
    }

    public function nfeImportMarkedCount(): int
    {
        return count($this->nfeImportMarkedIds);
    }

    protected function loadNfeImportDetalhe(): void
    {
        if ($this->nfeImportSelectedIndex === null || $this->nfeImportTipo === '') {
            $this->nfeImportDetalhe = null;

            return;
        }

        $row = $this->nfeImportResults[$this->nfeImportSelectedIndex] ?? null;
        $documentId = (int) ($row['document_id'] ?? 0);

        if ($documentId <= 0) {
            $this->nfeImportDetalhe = null;

            return;
        }

        $this->nfeImportDetalhe = app(NfeImportacaoService::class)->detalhe($this->nfeImportTipo, $documentId);
    }

    protected function resolveNfeImportFocusedId(): int
    {
        if ($this->nfeImportSelectedIndex === null) {
            return 0;
        }

        $row = $this->nfeImportResults[$this->nfeImportSelectedIndex] ?? null;

        return (int) ($row['document_id'] ?? 0);
    }

    /**
     * @return list<int>
     */
    protected function resolveNfeImportDocumentIdsForConfirm(): array
    {
        if ($this->nfeImportMarkedIds !== []) {
            return array_values(array_unique(array_map('intval', $this->nfeImportMarkedIds)));
        }

        $focused = $this->resolveNfeImportFocusedId();

        return $focused > 0 ? [$focused] : [];
    }

    protected function parseNfeImportDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            if (str_contains($value, '/')) {
                return Carbon::createFromFormat('d/m/Y', $value)->toDateString();
            }

            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
