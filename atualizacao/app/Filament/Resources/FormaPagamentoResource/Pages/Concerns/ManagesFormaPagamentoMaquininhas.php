<?php

namespace App\Filament\Resources\FormaPagamentoResource\Pages\Concerns;

use App\Models\CartaoMaquininha;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesFormaPagamentoMaquininhas
{
    public bool $showMaquininhaForm = false;

    public ?int $maquininhaFormId = null;

    /** @var array{codigo: int|string, nome: string, ativo: bool} */
    public array $maquininhaForm = [
        'codigo' => 1,
        'nome' => '',
        'ativo' => true,
    ];

    /**
     * @return list<array{id: int, codigo: int, nome: string, ativo: bool}>
     */
    public function getMaquininhasListaProperty(): array
    {
        return CartaoMaquininha::query()
            ->orderByPopularidade()
            ->get(['id', 'codigo', 'nome', 'ativo'])
            ->map(fn (CartaoMaquininha $m): array => [
                'id' => (int) $m->id,
                'codigo' => (int) $m->codigo,
                'nome' => (string) $m->nome,
                'ativo' => (bool) $m->ativo,
            ])
            ->all();
    }

    public function createMaquininha(): void
    {
        $this->closeBandeiraForm();
        $this->maquininhaFormId = null;
        $this->maquininhaForm = [
            'codigo' => CartaoMaquininha::nextCodigo(),
            'nome' => '',
            'ativo' => true,
        ];
        $this->showMaquininhaForm = true;
    }

    public function editMaquininha(int $id): void
    {
        $record = CartaoMaquininha::query()->find($id);

        if (! $record) {
            Notification::make()
                ->title('Maquininha não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->closeBandeiraForm();
        $this->maquininhaFormId = (int) $record->id;
        $this->maquininhaForm = [
            'codigo' => (int) $record->codigo,
            'nome' => (string) $record->nome,
            'ativo' => (bool) $record->ativo,
        ];
        $this->showMaquininhaForm = true;
    }

    public function closeMaquininhaForm(): void
    {
        $this->showMaquininhaForm = false;
        $this->maquininhaFormId = null;
        $this->maquininhaForm = [
            'codigo' => 1,
            'nome' => '',
            'ativo' => true,
        ];
        $this->resetErrorBag('maquininhaForm.*');
    }

    public function saveMaquininha(): void
    {
        $data = $this->validate([
            'maquininhaForm.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('cartao_maquininhas', 'codigo')->ignore($this->maquininhaFormId),
            ],
            'maquininhaForm.nome' => [
                'required',
                'string',
                'max:60',
                Rule::unique('cartao_maquininhas', 'nome')->ignore($this->maquininhaFormId),
            ],
        ], [], [
            'maquininhaForm.codigo' => 'código',
            'maquininhaForm.nome' => 'maquininha',
        ])['maquininhaForm'];

        $payload = [
            'codigo' => (int) $data['codigo'],
            'nome' => mb_strtoupper(trim((string) $data['nome']), 'UTF-8'),
            'ativo' => (bool) ($this->maquininhaForm['ativo'] ?? true),
        ];

        if ($this->maquininhaFormId) {
            CartaoMaquininha::query()->whereKey($this->maquininhaFormId)->update($payload);
        } else {
            CartaoMaquininha::query()->create($payload);
        }

        $this->closeMaquininhaForm();

        Notification::make()
            ->title('Maquininha gravada.')
            ->success()
            ->send();
    }

    public function deleteMaquininha(int $id): void
    {
        $record = CartaoMaquininha::query()->find($id);

        if (! $record) {
            return;
        }

        $record->delete();

        if ($this->maquininhaFormId === $id) {
            $this->closeMaquininhaForm();
        }

        Notification::make()
            ->title('Maquininha excluída.')
            ->success()
            ->send();
    }
}
