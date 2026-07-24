<?php

namespace App\Filament\Resources\FormaPagamentoResource\Pages\Concerns;

use App\Models\CartaoBandeira;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;

trait ManagesFormaPagamentoBandeiras
{
    public bool $showBandeiras = false;

    public bool $showBandeiraForm = false;

    public ?int $bandeiraFormId = null;

    /** @var array{codigo: int|string, nome: string, ativo: bool} */
    public array $bandeiraForm = [
        'codigo' => 1,
        'nome' => '',
        'ativo' => true,
    ];

    /**
     * @return list<array{id: int, codigo: int, nome: string, ativo: bool}>
     */
    public function getBandeirasListaProperty(): array
    {
        return CartaoBandeira::query()
            ->orderByPopularidade()
            ->get(['id', 'codigo', 'nome', 'ativo'])
            ->map(fn (CartaoBandeira $b): array => [
                'id' => (int) $b->id,
                'codigo' => (int) $b->codigo,
                'nome' => (string) $b->nome,
                'ativo' => (bool) $b->ativo,
            ])
            ->all();
    }

    public function openBandeiras(): void
    {
        // Cadastro fica no formulário da forma (cartão/TEF).
        $this->createBandeira();
    }

    public function closeBandeiras(): void
    {
        $this->showBandeiras = false;
        $this->closeBandeiraForm();
    }

    public function createBandeira(): void
    {
        if (method_exists($this, 'closeMaquininhaForm')) {
            $this->closeMaquininhaForm();
        }

        $this->bandeiraFormId = null;
        $this->bandeiraForm = [
            'codigo' => CartaoBandeira::nextCodigo(),
            'nome' => '',
            'ativo' => true,
        ];
        $this->showBandeiraForm = true;
    }

    public function editBandeira(int $id): void
    {
        $record = CartaoBandeira::query()->find($id);

        if (! $record) {
            Notification::make()
                ->title('Bandeira não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if (method_exists($this, 'closeMaquininhaForm')) {
            $this->closeMaquininhaForm();
        }

        $this->bandeiraFormId = (int) $record->id;
        $this->bandeiraForm = [
            'codigo' => (int) $record->codigo,
            'nome' => (string) $record->nome,
            'ativo' => (bool) $record->ativo,
        ];
        $this->showBandeiraForm = true;
    }

    public function closeBandeiraForm(): void
    {
        $this->showBandeiraForm = false;
        $this->bandeiraFormId = null;
        $this->bandeiraForm = [
            'codigo' => 1,
            'nome' => '',
            'ativo' => true,
        ];
        $this->resetErrorBag('bandeiraForm.*');
    }

    public function saveBandeira(): void
    {
        $data = $this->validate([
            'bandeiraForm.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('cartao_bandeiras', 'codigo')->ignore($this->bandeiraFormId),
            ],
            'bandeiraForm.nome' => [
                'required',
                'string',
                'max:60',
                Rule::unique('cartao_bandeiras', 'nome')->ignore($this->bandeiraFormId),
            ],
        ], [], [
            'bandeiraForm.codigo' => 'código',
            'bandeiraForm.nome' => 'bandeira',
        ])['bandeiraForm'];

        $payload = [
            'codigo' => (int) $data['codigo'],
            'nome' => mb_strtoupper(trim((string) $data['nome']), 'UTF-8'),
            'ativo' => (bool) ($this->bandeiraForm['ativo'] ?? true),
        ];

        if ($this->bandeiraFormId) {
            CartaoBandeira::query()->whereKey($this->bandeiraFormId)->update($payload);
        } else {
            CartaoBandeira::query()->create($payload);
        }

        $this->closeBandeiraForm();

        Notification::make()
            ->title('Bandeira gravada.')
            ->success()
            ->send();
    }

    public function deleteBandeira(int $id): void
    {
        $record = CartaoBandeira::query()->find($id);

        if (! $record) {
            return;
        }

        $record->delete();

        if ($this->bandeiraFormId === $id) {
            $this->closeBandeiraForm();
        }

        Notification::make()
            ->title('Bandeira excluída.')
            ->success()
            ->send();
    }
}
