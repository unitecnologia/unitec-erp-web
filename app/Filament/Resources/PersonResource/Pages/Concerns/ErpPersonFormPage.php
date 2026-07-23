<?php

namespace App\Filament\Resources\PersonResource\Pages\Concerns;

use App\Filament\Concerns\InteractsWithErpFormReturnUrl;
use App\Filament\Concerns\EmbedsInPdvOverlay;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\PersonResource;
use App\Models\Person;
use App\Rules\CelularBrasileiroValido;
use App\Rules\DocumentoBrasileiroValido;
use App\Support\Erp\ErpFormReturnUrl;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\ErpUppercase;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

trait ErpPersonFormPage
{
    use EmbedsInPdvOverlay;
    use InteractsWithErpFormReturnUrl;
    use ManagesPersonContacts;
    use NormalizesErpUppercaseFormData;
    use ManagesPersonFormUi;
    use ManagesPersonLookup;
    use ManagesPersonPhoto;
    public function getHeading(): string | Htmlable | null
    {
        return null;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        $classes = [
            ...parent::getPageClasses(),
            'erp-form-page',
            'erp-pessoas-form-page',
        ];

        if ($this->embedsInPdv) {
            $classes[] = 'erp-pdv-embed';
        }

        if ($this->embedsInOrcamento) {
            $classes[] = 'erp-orcamento-embed';
        }

        return $classes;
    }

    public function content(Schema $schema): Schema
    {
        if ($this->embedsInPdv) {
            return $schema
                ->gap(false)
                ->components([
                    View::make('filament.components.erp.pessoas.form.shell'),
                    Form::make([EmbeddedSchema::make('form')])
                        ->id('form')
                        ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                        ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
                    View::make('filament.components.erp.pessoas.form.action-bar'),
                ]);
        }

        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.pessoas.form.window'),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler($this->getSubmitFormLivewireMethodName())
                    ->extraAttributes(['class' => 'erp-pcad__filament-hidden']),
            ]);
    }

    public function saveForm(): void
    {
        $pessoaTipo = (string) ($this->data['pessoa_tipo'] ?? Person::PESSOA_JURIDICA);

        $this->validate(
            [
                'data.cpf_cnpj' => ['nullable', 'string', 'max:20', new DocumentoBrasileiroValido($pessoaTipo)],
                'data.celular1' => ['nullable', 'string', 'max:20', new CelularBrasileiroValido()],
                'data.celular2' => ['nullable', 'string', 'max:20', new CelularBrasileiroValido()],
                'data.whatsapp' => ['nullable', 'string', 'max:20', new CelularBrasileiroValido()],
            ],
            [],
            [
                'data.cpf_cnpj' => 'CPF/CNPJ',
                'data.celular1' => 'Celular 1',
                'data.celular2' => 'Celular 2',
                'data.whatsapp' => 'WhatsApp',
            ],
        );

        if ($this instanceof EditRecord) {
            $this->save();
        } else {
            /** @var CreateRecord $this */
            $this->create();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeLivewireFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->mergeLivewireFormData($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeLivewireFormData(array $data): array
    {
        $merged = array_merge($data, $this->data ?? []);

        unset($merged['visita_dias'], $merged['vendedor_rota_id'], $merged['visita_ordem']);

        // Selects HTML enviam "" quando "— Selecione —"; MySQL rejeita '' em colunas inteiras.
        foreach ([
            'vendedor_fv_id',
            'vendedor_loja_id',
            'forma_pagamento_id',
            'tabela_prazo_id',
            'price_table_id',
            'dia_pgto',
        ] as $field) {
            if (! array_key_exists($field, $merged)) {
                continue;
            }

            $value = $merged[$field];
            if ($value === '' || $value === null) {
                $merged[$field] = null;
            } else {
                $merged[$field] = (int) $value;
            }
        }

        return ErpUppercase::normalizeFormData($merged);
    }

    protected function syncPersonVisitaDias(Person $person): void
    {
        $dias = collect($this->data['visita_dias'] ?? [])
            ->map(fn ($d) => (int) $d)
            ->filter(fn (int $d): bool => array_key_exists($d, \App\Models\PersonVisitaDia::diasLabels()))
            ->unique()
            ->values()
            ->all();

        $existentes = $person->visitaDias()->get()->keyBy('dia_semana');

        foreach ($dias as $dia) {
            if ($existentes->has($dia)) {
                continue;
            }

            $person->visitaDias()->create([
                'dia_semana' => $dia,
                'ordem' => \App\Models\PersonVisitaDia::nextOrdem($dia, $person->vendedor_fv_id ? (int) $person->vendedor_fv_id : null),
            ]);
        }

        foreach ($existentes as $dia => $visita) {
            if (! in_array((int) $dia, $dias, true)) {
                $visita->delete();
            }
        }
    }

    protected function loadPersonVisitaDias(?Person $person = null): void
    {
        $person ??= $this->record;

        $dias = $person
            ? $person->visitaDias()->pluck('dia_semana')->map(fn ($d) => (int) $d)->values()->all()
            : [];

        $this->data['visita_dias'] = $dias;
    }

    public function cancelForm(): void
    {
        if ($this->embedsInParentOverlay()) {
            $this->closeEmbedOverlay();

            return;
        }

        $this->redirectToErpFormReturnOr(
            $this->getPersonListRedirectUrl(),
            'Pessoas',
        );
    }

    protected function closePdvEmbedOverlay(): void
    {
        $this->closeEmbedOverlay();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->embedsInPdv) {
            return PersonResource::getUrl('create') . '?tipo=clientes&pdv=1';
        }

        if ($this->embedsInOrcamento) {
            return PersonResource::getUrl('create') . '?tipo=clientes&orcamento=1';
        }

        return $this->erpFormReturnRedirectUrl($this->getPersonListRedirectUrl());
    }

    protected function flashOrcamentoReturnContextAfterPersonSave(): void
    {
        $returnUrl = $this->resolveErpFormReturnUrl();

        if (! ErpFormReturnUrl::isOrcamentoUrl($returnUrl)) {
            return;
        }

        $personId = $this->record?->getKey();

        if ($personId) {
            session([ErpFormReturnUrl::SESSION_NEW_CLIENTE_ID => (int) $personId]);
        }
    }

    protected function getPersonListRedirectUrl(): string
    {
        return PersonResource::getUrl('index', [
            'tipo' => $this->resolveListTipoFilter(),
        ]);
    }

    protected function resolveListTipoFilter(): string
    {
        /** @var Person|null $person */
        $person = property_exists($this, 'record') ? $this->record : null;

        foreach ([
            'clientes' => 'is_cliente',
            'funcionarios' => 'is_funcionario',
            'fornecedores' => 'is_fornecedor',
            'administradoras' => 'is_administradora',
            'parceiros' => 'is_parceiro',
        ] as $tipo => $field) {
            if ($person?->{$field}) {
                return $tipo;
            }
        }

        $tipo = request()->query('tipo');

        if (is_string($tipo) && $tipo !== '') {
            return $tipo;
        }

        return 'clientes';
    }
}
