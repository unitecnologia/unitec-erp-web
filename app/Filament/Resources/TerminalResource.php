<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TerminalResource\Pages;
use App\Models\Terminal;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TerminalResource extends Resource
{
    protected static ?string $model = Terminal::class;

    protected static ?string $slug = 'terminais';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedComputerDesktop;

    protected static ?string $modelLabel = 'terminal';

    protected static ?string $pluralModelLabel = 'terminais';

    protected static ?string $recordTitleAttribute = 'nome';

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
            'nome', 'ip', 'fab_impressora', 'modelo', 'porta', 'serie', 'tipo_impressora',
            'tipo_fechamento', 'impressora_nome', 'pagina_codigo',
            'balanca_porta', 'balanca_velocidade', 'balanca_marca', 'balanca_paridade',
            'balanca_databits', 'balanca_stopbits', 'balanca_handshaking',
            'caminho_sat_dll', 'modelo_sat_dll', 'tipo_sat_dll',
            'ip_servidor_tef', 'mensagem_pin_pad', 'mensagem_pdv',
            'caminho_cozinha', 'caminho_bar',
        ];

        $integers = [
            'empresa_id', 'numero_loja', 'empresa_ativa', 'numero_logico_terminal',
            'velocidade', 'nvias', 'numeracao_inicial', 'largura_bobina', 'tamanho_fonte',
            'qtd_tentativa_conect_bal', 'modelo_tef', 'tef_gerenciador', 'porta_pin_pad',
            'tef_max_cartoes', 'time_tela_caixa_livre',
        ];

        $decimals = [
            'margem_superior', 'margem_inferior', 'margem_esquerda', 'margem_direita',
            'tef_troco_maximo',
        ];

        $booleans = [
            'eh_caixa', 'pdv', 'restaurante', 'delivery', 'logado', 'usa_tef', 'usa_pos',
            'exibe_f3', 'exibe_f4', 'exibe_f5', 'exibe_f6', 'pesquisa_rapida', 'ler_peso',
            'busca_balanca_barras', 'mostrar_mensagem_pdv', 'mostrar_tela_caixa_livre',
            'imprime', 'usa_gaveta', 'usar_numero_inicial', 'meia_folha',
            'tef_via_reduzida', 'tef_multiplos_cartoes',
        ];

        $fields = [];

        foreach ($strings as $field) {
            $input = TextInput::make($field)->hidden()->dehydratedWhenHidden();

            if ($field === 'nome') {
                $input->required();
            }

            if ($field === 'mensagem_pdv') {
                $fields[] = Textarea::make($field)->hidden()->dehydratedWhenHidden();

                continue;
            }

            $fields[] = $input;
        }

        foreach ($integers as $field) {
            $fields[] = TextInput::make($field)
                ->numeric()
                ->hidden()
                ->dehydratedWhenHidden();
        }

        foreach ($decimals as $field) {
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
                TextColumn::make('nome')
                    ->label('Nome')
                    ->sortable()
                    ->weight(FontWeight::Bold),
                TextColumn::make('ip')
                    ->label('IP')
                    ->placeholder('â€”')
                    ->weight(FontWeight::SemiBold),
                IconColumn::make('eh_caixa')
                    ->label('Caixa')
                    ->boolean()
                    ->alignCenter(),
                IconColumn::make('pdv')
                    ->label('PDV')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('porta')
                    ->label('Porta Impressora')
                    ->placeholder('â€”')
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('nome')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum terminal cadastrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTerminais::route('/'),
            'create' => Pages\CreateTerminal::route('/create'),
            'edit' => Pages\EditTerminal::route('/{record}/edit'),
        ];
    }
}
