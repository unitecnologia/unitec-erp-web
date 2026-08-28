<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\Product;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ProductEmpresaPrecoService;
use App\Support\Erp\ProductEstoqueSaldoService;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            'tipo_produto', 'marca', 'grupo', 'unidade', 'localizacao',
            'loc_corredor', 'loc_modulo', 'loc_prateleira', 'loc_gaveta', 'loc_legado',
            'ncm', 'ncm_descricao', 'cest', 'info_adicionais',
            'cfop_interno', 'cst_icms', 'csosn', 'cfop_externo', 'cst_externo', 'csosn_externo',
            'cst_entrada', 'cst_saida', 'cst_cofins', 'cst_ipi', 'cod_enq_ipi', 'cod_beneficio', 'anp_code', 'prefixo_balanca',
            'tipo_restaurante', 'complemento', 'aplicacao', 'tipo_tributacao', 'tipo_alimento',
            'foto_path', 'iva_cst', 'cclass_trib', 'cclass_trib_descricao',
            'nutri_porcao_unidade', 'nutri_medida_fracao', 'nutri_medida_tipo',
        ];

        $dates = ['validade', 'promo_data_inicio', 'promo_data_fim'];

        $numbers = [
            'preco_compra', 'pct_custos', 'preco_custo', 'preco_custo_anterior', 'e_medio', 'pct_lucro', 'preco_venda',
            'preco_venda_anterior', 'ult_compra', 'ult_compra_anterior',
            'qtd_atacado', 'preco_atacado', 'preco_especial', 'estoque', 'estoque_minimo', 'peso_kg',
            'origem', 'aliq_icms', 'aliq_icms_externo', 'aliq_pis', 'aliq_cofins', 'aliq_ipi',
            'fcp_pct', 'mva_pct', 'mva_normal', 'reducao_base_pct', 'icms_diferido', 'aliq_deson', 'motivo_desoneracao',
            'glp_pct', 'gnn_pct', 'gni_pct', 'peso_liq', 'issqn',
            'tempo_espera', 'principio_ativo_id', 'menu_id', 'qtd_sabores',
            'valor_pequena', 'valor_media', 'valor_grande',
            'promo_preco_venda', 'promo_preco_atacado',
            'aliq_ibs_uf', 'aliq_cbs', 'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs', 'reducao_ibs',
            'nutri_porcao_qtd', 'nutri_medida_inteiro',
            'nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas',
            'nutri_gorduras_totais', 'nutri_gorduras_saturadas', 'nutri_gorduras_trans',
            'nutri_fibra', 'nutri_sodio',
        ];

        // Campos monetários/% no formulário customizado usam máscara BR (ex.: 0,1000).
        // TextInput::numeric() interpreta vírgula como inválida e zera o valor no fill.
        $brDecimalFields = [
            'preco_compra', 'pct_custos', 'preco_custo', 'preco_custo_anterior', 'e_medio', 'pct_lucro', 'preco_venda',
            'preco_venda_anterior', 'ult_compra', 'ult_compra_anterior',
            'qtd_atacado', 'preco_atacado', 'preco_especial', 'estoque', 'estoque_minimo', 'peso_kg',
            'aliq_icms', 'aliq_icms_externo', 'aliq_pis', 'aliq_cofins', 'aliq_ipi',
            'fcp_pct', 'mva_pct', 'mva_normal', 'reducao_base_pct', 'icms_diferido', 'aliq_deson',
            'glp_pct', 'gnn_pct', 'gni_pct', 'peso_liq', 'issqn',
            'valor_pequena', 'valor_media', 'valor_grande',
            'promo_preco_venda', 'promo_preco_atacado',
            'aliq_ibs_uf', 'aliq_cbs', 'aliq_ibs_mun', 'aliq_adrem_ibs', 'aliq_adrem_cbs', 'reducao_cbs', 'reducao_ibs',
            'nutri_valor_energetico', 'nutri_carboidratos', 'nutri_proteinas',
            'nutri_gorduras_totais', 'nutri_gorduras_saturadas', 'nutri_gorduras_trans',
            'nutri_fibra', 'nutri_sodio',
        ];

        $booleans = [
            'ativo', 'is_fiscal', 'paga_comissao', 'preco_variavel', 'is_composicao',
            'is_servico', 'is_grade', 'usa_tab_preco', 'is_combustivel', 'usa_imei',
            'contr_est_grade', 'mostrar_no_app', 'is_restaurante', 'is_remedio', 'produto_pesado', 'tem_info_nutricional', 'tributacao_monofasica',
            'controla_lote_validade',
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
            $input = TextInput::make($field)
                ->nullable()
                ->hidden()
                ->dehydratedWhenHidden();

            if (! in_array($field, $brDecimalFields, true)) {
                $input->numeric();
            }

            $fields[] = $input;
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
            TextColumn::make('codigo')
                ->label('Código')
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    $query->orderByRaw('CAST(codigo AS UNSIGNED) '.$dir);

                    return $query;
                })
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
                ->label('Descrição')
                ->wrap(false)
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    $query->orderBy('descricao', $dir);

                    return $query;
                })
                ->weight(FontWeight::Bold),
            TextColumn::make('grupo')
                ->label('Grupo')
                ->alignCenter()
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    $query->orderBy('grupo', $dir);

                    return $query;
                })
                ->weight(FontWeight::SemiBold),
            TextColumn::make('preco_venda')
                ->label('Preço')
                ->getStateUsing(fn (Product $record): float => app(ProductEmpresaPrecoService::class)->resolvePrecoVenda($record))
                ->formatStateUsing(function ($state): string {
                    $valor = number_format((float) $state, 2, ',', '.');

                    return '<span class="erp-produtos-preco"><span class="erp-produtos-preco__rs">R$</span><span class="erp-produtos-preco__val">'.e($valor).'</span></span>';
                })
                ->html()
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    $query->orderBy('preco_venda', $dir);

                    return $query;
                })
                ->weight(FontWeight::SemiBold),
            TextColumn::make('estoque')
                ->label('Est. Atual')
                ->getStateUsing(fn (Product $record): float => static::resolveEstoqueEmpresaAtual($record))
                ->formatStateUsing(fn ($state): string => number_format((float) $state, 0, ',', '.'))
                ->alignEnd()
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                    $empresaId = (int) (session('erp_empresa_id') ?? 0);
                    $service = app(ProductEstoqueSaldoService::class);

                    if ($service->suportaEstoquePorEmpresa($empresaId > 0 ? $empresaId : null)) {
                        $service->applyEstoqueEmpresaSelect($query, $empresaId);

                        return $query->orderBy('estoque_empresa_atual', $dir);
                    }

                    return $query->orderBy('estoque', $dir);
                })
                ->weight(FontWeight::SemiBold),
            TextColumn::make('estoque_reservado_sum')
                ->label('Est. Reserv.')
                ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 0, ',', '.'))
                ->alignEnd()
                ->sortable(false)
                ->weight(FontWeight::SemiBold),
            TextColumn::make('estoque_disponivel')
                ->label('Est. Disp.')
                ->getStateUsing(fn (Product $record): float => static::resolveEstoqueEmpresaAtual($record) - (float) ($record->estoque_reservado_sum ?? 0))
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
                ->label('Validade')
                ->formatStateUsing(function ($state, Product $record): string {
                    if (! filled($state)) {
                        return '';
                    }

                    $date = \Illuminate\Support\Carbon::parse($state)->format('d/m/Y');
                    $status = $record->validadeStatus();
                    $dias = $record->validadeDiasRestantes();

                    if ($status === null || $dias === null) {
                        return e($date);
                    }

                    $diasLabel = match (true) {
                        $dias === 0 => 'vence hoje',
                        $dias === 1 => 'falta 1 dia',
                        $dias > 1 => 'faltam '.$dias.' dias',
                        $dias === -1 => 'vencido há 1 dia',
                        default => 'vencido há '.abs($dias).' dias',
                    };

                    $title = e($diasLabel.' — '.$record->validadeStatusLabel());
                    $vencidaClass = $status === 'vencido' ? ' is-vencida' : '';

                    return '<span class="erp-prod-validade'.$vencidaClass.'" title="'.$title.'">'
                        .e($date)
                        .'<span class="erp-prod-validade__dot erp-prod-validade__dot--'.$status.'" aria-hidden="true"></span>'
                        .'</span>';
                })
                ->html()
                ->placeholder('')
                ->alignCenter()
                ->sortable(query: function (Builder $query, string $direction): Builder {
                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';

                    // Sem validade no final ao ordenar do menor para o maior
                    $query->orderByRaw('validade IS NULL ASC, validade '.$dir);

                    return $query;
                })
                ->weight(FontWeight::SemiBold),
            TextColumn::make('lote')
                ->label('Lote')
                ->placeholder('')
                ->alignCenter()
                ->sortable()
                ->weight(FontWeight::SemiBold),
        ];

        return $table
            ->columns($columns)
            ->modifyQueryUsing(function (Builder $query): Builder {
                $empresaId = (int) (session('erp_empresa_id') ?? 0);
                if ($empresaId > 0) {
                    $query->with(['empresaPrecos' => static function ($precos) use ($empresaId): void {
                        $precos->where('empresa_id', $empresaId);
                    }]);
                }

                return $query;
            })
            ->defaultSort(function (Builder $query, string $direction, $livewire): Builder {
                    // Se o usuário já escolheu outra coluna, não força código (senão sobrescreve).
                    if (filled($livewire->getTableSortColumn())) {
                        return $query;
                    }

                    $dir = strtolower($direction) === 'desc' ? 'desc' : 'asc';
                    $query->orderByRaw('CAST(codigo AS UNSIGNED) '.$dir);

                    return $query;
                })
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

    protected static function resolveEstoqueEmpresaAtual(Product $record): float
    {
        if (isset($record->estoque_empresa_atual)) {
            return (float) $record->estoque_empresa_atual;
        }

        return app(ProductEstoqueSaldoService::class)->fisicoEmpresa((int) $record->id);
    }
}
