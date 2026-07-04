<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmpresaResource\Pages;
use App\Models\Empresa;
use App\Support\Erp\EmpresaParametros;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EmpresaResource extends Resource
{
    protected static ?string $model = Empresa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $modelLabel = 'empresa';

    protected static ?string $pluralModelLabel = 'empresas';

    protected static ?string $recordTitleAttribute = 'fantasia';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components(static::hiddenFormFields());
    }

    /**
     * @return array<int, TextInput|Checkbox|Textarea>
     */
    protected static function hiddenFormFields(): array
    {
        $strings = [
            'codigo', 'nome', 'fantasia', 'razao_social', 'pessoa_tipo', 'cidade', 'cnpj', 'ie', 'im', 'cnae',
            'regime_tributario', 'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade_codigo', 'uf',
            'pais_codigo', 'pais', 'email', 'site', 'telefone', 'responsavel', 'cnpj_representante', 'tipo_atividade',
            'obs_fisco', 'obs_carne', 'obs_nfce', 'msg_cobranca_whatsapp', 'logo_path',
        ];

        $fields = [];

        foreach ($strings as $field) {
            $input = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();

            if (in_array($field, ['codigo', 'fantasia', 'razao_social', 'cnpj'], true)) {
                $input->required();
            }

            $fields[] = $input;
        }

        foreach (EmpresaParametros::numericFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::permissionFields() as $field => $meta) {
            if (($meta['tri'] ?? false) === true) {
                $fields[] = TextInput::make($field)
                    ->hidden()
                    ->dehydratedWhenHidden();
            } else {
                $fields[] = Checkbox::make($field)
                    ->hidden()
                    ->dehydratedWhenHidden();
            }
        }

        foreach (EmpresaParametros::impostoFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        $fields[] = Textarea::make('param_imp_observacao')
            ->hidden()
            ->dehydratedWhenHidden();

        foreach (EmpresaParametros::difalFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::difalBooleanFields() as $field => $meta) {
            $fields[] = Checkbox::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::pixFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::pixBooleanFields() as $field => $meta) {
            $fields[] = Checkbox::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::apiServicosFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::apiServicosBooleanFields() as $field => $meta) {
            $fields[] = Checkbox::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::whatsAppFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::whatsAppBooleanFields() as $field => $meta) {
            $fields[] = Checkbox::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::portalContadorFields() as $field => $meta) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach (EmpresaParametros::portalContadorBooleanFields() as $field => $meta) {
            $fields[] = Checkbox::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        $fields[] = Checkbox::make('ativo')
            ->hidden()
            ->dehydratedWhenHidden();

        return $fields;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('codigo')
                    ->label('CÃ³digo')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('fantasia')
                    ->label('Fantasia')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('razao_social')
                    ->label('RazÃ£o')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cidade')
                    ->label('Cidade')
                    ->placeholder('â€”')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->placeholder('â€”')
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('ie')
                    ->label('IE')
                    ->placeholder('â€”')
                    ->alignCenter()
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
            ->emptyStateHeading('Nenhuma empresa encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmpresas::route('/'),
            'create' => Pages\CreateEmpresa::route('/create'),
            'edit' => Pages\EditEmpresa::route('/{record}/edit'),
        ];
    }
}
