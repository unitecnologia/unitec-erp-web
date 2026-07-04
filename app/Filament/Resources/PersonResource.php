<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonResource\Pages;
use App\Models\Person;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PersonResource extends Resource
{
    protected static ?string $model = Person::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $modelLabel = 'pessoa';

    protected static ?string $pluralModelLabel = 'pessoas';

    protected static ?string $recordTitleAttribute = 'nome_razao';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('pessoas.access');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::hiddenFormFields());
    }

    /**
     * @return array<int, TextInput|Checkbox>
     */
    protected static function hiddenFormFields(): array
    {
        $strings = [
            'codigo', 'pessoa_tipo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj', 'rg_ie',
            'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade_codigo', 'cidade_nome', 'uf',
            'email', 'email2', 'fone1', 'fone2', 'celular1', 'celular2', 'whatsapp',
            'regime_tributario', 'tipo_recebimento', 'tipo_contribuinte',
            'nome_mae', 'nome_pai', 'data_nascimento', 'estado_civil', 'sexo',
            'data_admissao', 'data_demissao', 'observacoes',
            'banco', 'agencia', 'gerente', 'fone_gerente',
            'foto_path',
        ];

        $numbers = ['limite_credito', 'dia_pgto', 'salario', 'forma_pagamento_id', 'tabela_prazo_id', 'vendedor_fv_id', 'vendedor_loja_id'];

        $booleans = [
            'is_cliente', 'is_fornecedor', 'is_funcionario', 'is_administradora',
            'is_parceiro', 'is_fabricante', 'is_transportadora', 'is_ccf_spc',
            'ativo', 'is_atendente', 'is_tecnico',
        ];

        $fields = [];

        foreach ($strings as $field) {
            $input = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();

            if (in_array($field, ['codigo', 'nome_razao'], true)) {
                $input->required();
            }

            $fields[] = $input;
        }

        foreach ($numbers as $field) {
            $fields[] = TextInput::make($field)
                ->numeric()
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach ($booleans as $field) {
            $fields[] = Checkbox::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        return $fields;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('_marker')
                    ->label('')
                    ->alignCenter()
                    ->sortable(false)
                    ->getStateUsing(function (Person $record, $livewire): string {
                        $selectedId = $livewire->highlightedRecordId ?? null;

                        return $selectedId !== null && (int) $selectedId === (int) $record->getKey() ? '›' : '';
                    }),
                TextColumn::make('codigo')
                    ->label('» Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome_razao')
                    ->label('Nome/Razão')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('apelido_fantasia')
                    ->label('Apelido/Fantasia')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cpf_cnpj')
                    ->label('CPF/CNPJ')
                    ->placeholder('—')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('rg_ie')
                    ->label('RG/IE')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('endereco_lista')
                    ->label('Endereço')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('codigo')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma pessoa encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPeople::route('/'),
            'create' => Pages\CreatePerson::route('/create'),
            'edit' => Pages\EditPerson::route('/{record}/edit'),
        ];
    }
}
