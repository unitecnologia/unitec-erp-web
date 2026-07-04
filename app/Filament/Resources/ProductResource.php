<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
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

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $modelLabel = 'produto';

    protected static ?string $pluralModelLabel = 'produtos';

    protected static ?string $recordTitleAttribute = 'descricao';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('produtos.access');
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
            'codigo', 'referencia', 'codigo_barras', 'codigo_barras_caixa', 'descricao',
            'tipo_produto', 'marca', 'grupo', 'unidade', 'localizacao', 'ncm', 'ncm_descricao', 'cest',
            'cfop_interno', 'cst_icms', 'csosn', 'cfop_externo', 'cst_externo', 'csosn_externo',
            'cst_entrada', 'cst_saida', 'cst_ipi', 'cod_enq_ipi', 'cod_beneficio', 'anp_code', 'prefixo_balanca',
            'tipo_restaurante', 'complemento', 'aplicacao', 'tipo_tributacao', 'tipo_alimento',
            'foto_path', 'iva_cst', 'iva_classificacao',
        ];

        $dates = ['validade', 'promo_data_inicio', 'promo_data_fim'];

        $numbers = [
            'preco_compra', 'pct_custos', 'preco_custo', 'preco_custo_anterior', 'e_medio', 'pct_lucro', 'preco_venda',
            'preco_venda_prazo', 'preco_venda_anterior', 'ult_compra', 'ult_compra_anterior',
            'qtd_atacado', 'preco_atacado', 'comissao_pct', 'desconto_pct',
            'estoque', 'estoque_minimo', 'estoque_inicial', 'peso_kg',
            'origem', 'aliq_icms', 'aliq_icms_externo', 'aliq_pis', 'aliq_cofins', 'aliq_ipi',
            'fcp_pct', 'mva_pct', 'mva_normal', 'reducao_base_pct', 'icms_diferido', 'aliq_deson', 'motivo_desoneracao',
            'glp_pct', 'gnn_pct', 'gni_pct', 'peso_liq', 'issqn',
            'tempo_espera', 'principio_ativo_id', 'menu_id', 'qtd_sabores',
            'valor_pequena', 'valor_media', 'valor_grande',
            'promo_preco_venda', 'promo_preco_atacado',
            'iva_aliq', 'iva_red_base',
        ];

        $booleans = [
            'ativo', 'is_fiscal', 'paga_comissao', 'preco_variavel', 'is_composicao',
            'is_servico', 'is_grade', 'usa_tab_preco', 'is_combustivel', 'usa_imei',
            'contr_est_grade', 'mostrar_no_app', 'is_restaurante', 'is_remedio', 'produto_pesado', 'tributacao_monofasica',
        ];

        $fields = [];

        foreach ($strings as $field) {
            $input = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();

            if (in_array($field, ['codigo', 'descricao'], true)) {
                $input->required();
            }

            $fields[] = $input;
        }

        foreach ($dates as $field) {
            $fields[] = TextInput::make($field)
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach ($numbers as $field) {
            $fields[] = TextInput::make($field)
                ->numeric()
                ->nullable()
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
        $columns = [
            TextColumn::make('_marker')
                ->label('')
                ->alignCenter()
                ->sortable(false)
                ->getStateUsing(function (Product $record, $livewire): string {
                    $selectedId = $livewire->highlightedRecordId ?? null;

                    return $selectedId !== null && (int) $selectedId === (int) $record->getKey() ? '›' : '';
                }),
            TextColumn::make('codigo')
                ->label('Código')
                ->sortable()
                ->alignCenter()
                ->weight(FontWeight::SemiBold),
            TextColumn::make('referencia')
                ->label('Referência')
                ->placeholder('—')
                ->alignCenter()
                ->weight(FontWeight::SemiBold),
            TextColumn::make('codigo_barras')
                ->label('Cód. Barras')
                ->placeholder('—')
                ->weight(FontWeight::SemiBold),
            TextColumn::make('descricao')
                ->label('» Descrição')
                ->wrap(false)
                ->weight(FontWeight::Bold),
            TextColumn::make('grupo')
                ->label('Grupo')
                ->alignCenter()
                ->sortable()
                ->weight(FontWeight::SemiBold),
            TextColumn::make('preco_venda')
                ->label('Preço')
                ->formatStateUsing(fn ($state): string => 'R$ ' . number_format((float) $state, 2, ',', '.'))
                ->alignEnd()
                ->sortable()
                ->weight(FontWeight::SemiBold),
            TextColumn::make('estoque')
                ->label('Est. Atual')
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.'))
                ->alignEnd()
                ->sortable()
                ->weight(FontWeight::SemiBold),
            TextColumn::make('estoque_reservado_sum')
                ->label('Est. Reserv.')
                ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 0, ',', '.'))
                ->alignEnd()
                ->sortable(false)
                ->weight(FontWeight::SemiBold),
            TextColumn::make('estoque_disponivel')
                ->label('Est. Disp.')
                ->getStateUsing(fn (Product $record): float => (float) $record->estoque - (float) ($record->estoque_reservado_sum ?? 0))
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.'))
                ->alignEnd()
                ->sortable(false)
                ->weight(FontWeight::SemiBold),
            TextColumn::make('localizacao')
                ->label('Localização')
                ->placeholder('')
                ->alignCenter()
                ->weight(FontWeight::SemiBold),
            TextColumn::make('validade')
                ->label('VALIDADE')
                ->formatStateUsing(fn ($state): string => filled($state) ? \Illuminate\Support\Carbon::parse($state)->format('d/m/Y') : '')
                ->placeholder('')
                ->alignCenter()
                ->color(fn ($state, Product $record): ?string => $record->validadeVencida() ? 'danger' : null)
                ->weight(fn ($state, Product $record): FontWeight => $record->validadeVencida() ? FontWeight::Bold : FontWeight::SemiBold),
        ];

        return $table
            ->columns($columns)
            ->defaultSort('codigo')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum produto encontrado');
    }

    public static function serialsTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.descricao')
                    ->label('Descrição')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('numero_serie')
                    ->label('Nº Série')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('situacao')
                    ->label('Situação')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('doc_saida')
                    ->label('Doc. Saída')
                    ->placeholder('—')
                    ->alignCenter(),
                TextColumn::make('data_baixa')
                    ->label('Data Baixa')
                    ->formatStateUsing(fn ($state): string => $state ? \Illuminate\Support\Carbon::parse($state)->format('d/m/Y') : '')
                    ->placeholder('—')
                    ->alignCenter(),
            ])
            ->defaultSort('numero_serie')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum serial encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
            'cardex' => Pages\ViewProductCardex::route('/{record}/cardex'),
        ];
    }
}
