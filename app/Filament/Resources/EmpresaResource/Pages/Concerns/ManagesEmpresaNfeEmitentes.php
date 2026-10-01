<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use Illuminate\Support\Facades\Schema;

trait ManagesEmpresaNfeEmitentes
{
    /** @var list<int|string> IDs das emitentes selecionadas (UI). */
    public array $empresaNfeEmitenteIds = [];

    protected function hydrateEmpresaNfeEmitentes(): void
    {
        $empresa = $this->resolveEmpresaRecordForNfeEmitentes();

        if (! $empresa || ! Schema::hasTable('empresa_nfe_emitente')) {
            $this->empresaNfeEmitenteIds = [];

            return;
        }

        $this->empresaNfeEmitenteIds = $empresa->nfeEmitentes()
            ->orderBy('empresas.nome')
            ->pluck('empresas.id')
            ->map(fn ($id): string => (string) $id)
            ->values()
            ->all();
    }

    protected function syncEmpresaNfeEmitentes(): void
    {
        $empresa = $this->resolveEmpresaRecordForNfeEmitentes();

        if (! $empresa || ! Schema::hasTable('empresa_nfe_emitente')) {
            return;
        }

        $ids = collect($this->empresaNfeEmitenteIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0 && $id !== (int) $empresa->id)
            ->unique()
            ->values()
            ->all();

        if ($ids !== []) {
            $validos = Empresa::query()
                ->where('ativo', true)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            $ids = array_values(array_intersect($ids, $validos));
        }

        $empresa->nfeEmitentes()->sync($ids);
    }

    /**
     * Empresas ativas candidatas a emitente (exceto a própria).
     *
     * @return list<array{id: int, codigo: string, nome: string}>
     */
    public function empresaNfeEmitentesCatalogo(): array
    {
        $empresa = $this->resolveEmpresaRecordForNfeEmitentes();
        $selfId = $empresa?->id ? (int) $empresa->id : 0;

        return Empresa::query()
            ->where('ativo', true)
            ->when($selfId > 0, fn ($q) => $q->where('id', '!=', $selfId))
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome'])
            ->map(fn (Empresa $e): array => [
                'id' => (int) $e->id,
                'codigo' => (string) ($e->codigo ?? ''),
                'nome' => (string) ($e->nome ?? ''),
            ])
            ->values()
            ->all();
    }

    public function empresaNfeEmitentesConfigVisivel(): bool
    {
        return filter_var(
            $this->data['param_monitor_vendas_escolher_empresa_emitente_nfe'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    protected function resolveEmpresaRecordForNfeEmitentes(): ?Empresa
    {
        if (! property_exists($this, 'record') || ! $this->record) {
            return null;
        }

        return $this->record instanceof Empresa ? $this->record : null;
    }
}
