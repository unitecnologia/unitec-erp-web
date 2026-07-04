<?php

namespace App\Filament\Resources\PersonResource\Pages;

use App\Filament\Resources\PersonResource;
use App\Filament\Resources\PersonResource\Pages\Concerns\ErpPersonFormPage;
use App\Models\Person;
use App\Support\Erp\ErpScreen;
use Filament\Resources\Pages\CreateRecord;

class CreatePerson extends CreateRecord
{
    use ErpPersonFormPage;

    protected static string $resource = PersonResource::class;

    public function mount(): void
    {
        if (request()->boolean('pdv')) {
            $this->embedsInPdv = true;
        }

        if (request()->boolean('orcamento')) {
            $this->embedsInOrcamento = true;
        }

        parent::mount();

        ErpScreen::set('Cadastro de Pessoas');

        $tipo = request()->query('tipo', 'clientes');

        $flags = match ($tipo) {
            'funcionarios' => ['is_funcionario' => true],
            'fornecedores' => ['is_fornecedor' => true],
            'administradoras' => ['is_administradora' => true],
            'parceiros' => ['is_parceiro' => true],
            default => ['is_cliente' => true],
        };

        $defaults = [
            ...($this->data ?? []),
            'codigo' => Person::nextCodigo(),
            'pessoa_tipo' => Person::PESSOA_JURIDICA,
            'uf' => 'SC',
            'regime_tributario' => 'simples',
            'tipo_contribuinte' => 'nao_contribuinte',
            'limite_credito' => 0,
            'salario' => 0,
            'ativo' => true,
            ...$flags,
        ];

        $this->data = $defaults;
        $this->form->fill($defaults);

        $this->loadPersonContacts();
        $this->mountPersonPhoto();
    }

    protected function afterCreate(): void
    {
        $this->syncPersonContacts($this->record);

        if ($this->embedsInOrcamento) {
            $this->closeEmbedOverlay([
                'clienteId' => (int) $this->record->getKey(),
            ]);

            return;
        }

        $this->flashOrcamentoReturnContextAfterPersonSave();

        if ($this->embedsInPdv) {
            $this->closePdvEmbedOverlay();
        }
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return static::getResource()::getUrl('create') . '?tipo=clientes&pdv=1';
        }

        if ($this->embedsInOrcamento) {
            return static::getResource()::getUrl('create') . '?tipo=clientes&orcamento=1';
        }

        return $this->erpFormReturnRedirectUrl($this->getPersonListRedirectUrl());
    }
}
