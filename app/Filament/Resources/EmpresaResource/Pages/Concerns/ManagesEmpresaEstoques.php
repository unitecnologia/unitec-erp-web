<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Estoque;
use App\Models\Vendedor;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

trait ManagesEmpresaEstoques
{
    public bool $empresaEstoqueModalOpen = false;

    public ?int $empresaEstoqueModalId = null;

    public ?int $empresaEstoqueSelectedId = null;

    /** @var array{codigo: string, nome: string, vendedor_id: string, ativo: bool} */
    public array $empresaEstoqueForm = [
        'codigo' => '',
        'nome' => '',
        'vendedor_id' => '',
        'ativo' => true,
    ];

    /**
     * @return list<array{id: int, codigo: string, nome: string, vendedor: string, ativo: bool}>
     */
    public function getEmpresaEstoquesProperty(): array
    {
        $empresa = $this->resolveEmpresaRecordForEstoques();

        if (! $empresa || ! Schema::hasTable('estoques')) {
            return [];
        }

        return Estoque::query()
            ->with('vendedor:id,codigo,nome')
            ->where('empresa_id', $empresa->id)
            ->orderByRaw('CAST(codigo AS UNSIGNED)')
            ->orderBy('codigo')
            ->get()
            ->map(function (Estoque $estoque): array {
                $vendedor = $estoque->vendedor;

                return [
                    'id' => (int) $estoque->id,
                    'codigo' => (string) $estoque->codigo,
                    'nome' => (string) $estoque->nome,
                    'vendedor' => $vendedor
                        ? trim(($vendedor->codigo ? $vendedor->codigo.' — ' : '').$vendedor->nome)
                        : '—',
                    'ativo' => (bool) $estoque->ativo,
                ];
            })
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public function empresaEstoqueVendedorOptions(): array
    {
        $empresa = $this->resolveEmpresaRecordForEstoques();

        if (! $empresa) {
            return [];
        }

        return Vendedor::query()
            ->where('ativo', true)
            ->where(function ($query) use ($empresa): void {
                $query->where('empresa_id', $empresa->id)
                    ->orWhereHas('empresas', fn ($q) => $q->where('empresas.id', $empresa->id));
            })
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome'])
            ->mapWithKeys(fn (Vendedor $vendedor): array => [
                $vendedor->id => trim(($vendedor->codigo ? $vendedor->codigo.' — ' : '').$vendedor->nome),
            ])
            ->all();
    }

    public function selectEmpresaEstoque(int $id): void
    {
        $this->empresaEstoqueSelectedId = $id > 0 ? $id : null;
    }

    public function createEmpresaEstoque(): void
    {
        $empresa = $this->resolveEmpresaRecordForEstoques();

        if (! $empresa || ! Schema::hasTable('estoques')) {
            Notification::make()
                ->title('Cadastro de Estoque')
                ->body('Salve a empresa e rode a migration de estoques antes de cadastrar.')
                ->warning()
                ->send();

            return;
        }

        $this->empresaEstoqueModalId = null;
        $this->empresaEstoqueForm = [
            'codigo' => Estoque::nextCodigo((int) $empresa->id),
            'nome' => '',
            'vendedor_id' => '',
            'ativo' => true,
        ];
        $this->empresaEstoqueModalOpen = true;
    }

    public function editEmpresaEstoque(): void
    {
        $empresa = $this->resolveEmpresaRecordForEstoques();

        if (! $empresa) {
            Notification::make()
                ->title('Cadastro de Estoque')
                ->body('Salve a empresa antes de editar estoques.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->empresaEstoqueSelectedId) {
            Notification::make()
                ->title('Cadastro de Estoque')
                ->body('Selecione um estoque na lista.')
                ->warning()
                ->send();

            return;
        }

        $estoque = Estoque::query()
            ->where('empresa_id', $empresa->id)
            ->find($this->empresaEstoqueSelectedId);

        if (! $estoque) {
            Notification::make()
                ->title('Estoque não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $this->empresaEstoqueModalId = (int) $estoque->id;
        $this->empresaEstoqueForm = [
            'codigo' => (string) $estoque->codigo,
            'nome' => (string) $estoque->nome,
            'vendedor_id' => $estoque->vendedor_id ? (string) $estoque->vendedor_id : '',
            'ativo' => (bool) $estoque->ativo,
        ];
        $this->empresaEstoqueModalOpen = true;
    }

    public function closeEmpresaEstoqueModal(): void
    {
        $this->empresaEstoqueModalOpen = false;
        $this->empresaEstoqueModalId = null;
        $this->empresaEstoqueForm = [
            'codigo' => '',
            'nome' => '',
            'vendedor_id' => '',
            'ativo' => true,
        ];
    }

    public function saveEmpresaEstoque(): void
    {
        $empresa = $this->resolveEmpresaRecordForEstoques();

        if (! $empresa) {
            Notification::make()
                ->title('Cadastro de Estoque')
                ->body('Salve a empresa antes de gravar estoques.')
                ->warning()
                ->send();

            return;
        }

        $this->validate([
            'empresaEstoqueForm.codigo' => [
                'required',
                'string',
                'max:20',
                Rule::unique('estoques', 'codigo')
                    ->where(fn ($query) => $query->where('empresa_id', $empresa->id))
                    ->ignore($this->empresaEstoqueModalId),
            ],
            'empresaEstoqueForm.nome' => ['required', 'string', 'max:120'],
            'empresaEstoqueForm.vendedor_id' => [
                'nullable',
                'integer',
                Rule::exists('vendedores', 'id'),
            ],
            'empresaEstoqueForm.ativo' => ['boolean'],
        ], [], [
            'empresaEstoqueForm.codigo' => 'código',
            'empresaEstoqueForm.nome' => 'nome',
            'empresaEstoqueForm.vendedor_id' => 'vendedor',
            'empresaEstoqueForm.ativo' => 'ativo',
        ]);

        $vendedorId = trim((string) ($this->empresaEstoqueForm['vendedor_id'] ?? ''));
        $vendedorId = $vendedorId !== '' ? (int) $vendedorId : null;

        $data = [
            'empresa_id' => (int) $empresa->id,
            'codigo' => trim((string) $this->empresaEstoqueForm['codigo']),
            'nome' => mb_strtoupper(trim((string) $this->empresaEstoqueForm['nome']), 'UTF-8'),
            'vendedor_id' => $vendedorId,
            'ativo' => (bool) ($this->empresaEstoqueForm['ativo'] ?? true),
        ];

        if ($this->empresaEstoqueModalId) {
            $estoque = Estoque::query()
                ->where('empresa_id', $empresa->id)
                ->find($this->empresaEstoqueModalId);

            if (! $estoque) {
                Notification::make()
                    ->title('Estoque não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $estoque->update($data);
            $message = 'Estoque alterado.';
        } else {
            $estoque = Estoque::query()->create($data);
            $message = 'Estoque incluído.';
        }

        $this->syncVendedorEstoqueLink($estoque, $vendedorId);

        Notification::make()
            ->title($message)
            ->success()
            ->send();

        $this->closeEmpresaEstoqueModal();
        $this->empresaEstoqueSelectedId = (int) $estoque->id;
    }

    public function deleteEmpresaEstoque(): void
    {
        $empresa = $this->resolveEmpresaRecordForEstoques();

        if (! $empresa) {
            Notification::make()
                ->title('Cadastro de Estoque')
                ->body('Salve a empresa antes de excluir estoques.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->empresaEstoqueSelectedId) {
            Notification::make()
                ->title('Cadastro de Estoque')
                ->body('Selecione um estoque na lista.')
                ->warning()
                ->send();

            return;
        }

        $estoque = Estoque::query()
            ->where('empresa_id', $empresa->id)
            ->find($this->empresaEstoqueSelectedId);

        if (! $estoque) {
            Notification::make()
                ->title('Estoque não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $estoque->delete();
        $this->empresaEstoqueSelectedId = null;

        Notification::make()
            ->title('Estoque excluído.')
            ->success()
            ->send();
    }

    protected function syncVendedorEstoqueLink(Estoque $estoque, ?int $vendedorId): void
    {
        if ($vendedorId) {
            Estoque::query()
                ->where('vendedor_id', $vendedorId)
                ->where('id', '!=', $estoque->id)
                ->update(['vendedor_id' => null]);
        }

        Vendedor::query()
            ->where('estoque_id', $estoque->id)
            ->when($vendedorId, fn ($query) => $query->where('id', '!=', $vendedorId))
            ->update([
                'estoque_id' => null,
                'estoque' => '',
            ]);

        if ($vendedorId === null) {
            return;
        }

        $vendedor = Vendedor::query()->find($vendedorId);

        if (! $vendedor) {
            return;
        }

        $vendedor->estoque_id = $estoque->id;
        $vendedor->estoque = $estoque->label();
        $vendedor->save();
    }

    protected function resolveEmpresaRecordForEstoques(): ?Empresa
    {
        if (! property_exists($this, 'record') || ! $this->record instanceof Empresa) {
            return null;
        }

        if (! filled($this->record->getKey())) {
            return null;
        }

        return $this->record;
    }
}
