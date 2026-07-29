<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NotaFornecedorResource\Pages;
use App\Models\NotaFornecedor;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;

class NotaFornecedorResource extends Resource
{
    protected static ?string $model = NotaFornecedor::class;

    protected static ?string $slug = 'notas-fornecedores';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static ?string $modelLabel = 'nota de fornecedor';

    protected static ?string $pluralModelLabel = 'notas de fornecedores';

    protected static ?string $recordTitleAttribute = 'numero';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('compras.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ViewColumn::make('selecionar')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('2.1rem')
                    ->view('filament.components.erp.notas-fornecedores.columns.select')
                    ->alignCenter()
                    ->disabledClick(),
                TextColumn::make('data_entrada')
                    ->label('Dt.Entrada')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('data_emissao')
                    ->label('Dt.Emissão')
                    ->date('d/m/Y')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('numero')
                    ->label('Número')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('chave')
                    ->label('Chave')
                    ->placeholder('—')
                    ->wrap(false)
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('cnpj')
                    ->label('CNPJ')
                    ->formatStateUsing(fn (?string $state): string => self::formatCnpj($state))
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('nome')
                    ->label('Fornecedor')
                    ->grow()
                    ->wrap(false)
                    ->tooltip(fn (?string $state): ?string => filled($state) ? $state : null)
                    ->weight(FontWeight::Bold),
                TextColumn::make('nsu')
                    ->label('NSU')
                    ->placeholder('—')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->formatStateUsing(fn (?string $state): string => self::formatNsu($state)),
                ViewColumn::make('status')
                    ->label('Situação')
                    ->view('filament.components.erp.notas-fornecedores.columns.status')
                    ->alignCenter()
                    ->disabledClick(),
                ViewColumn::make('total')
                    ->label('Total')
                    ->view('filament.components.erp.nfe.columns.total')
                    ->alignEnd()
                    ->disabledClick(),
                ViewColumn::make('visualizar')
                    ->label('')
                    ->state(fn (): bool => true)
                    ->width('1.35rem')
                    ->view('filament.components.erp.notas-fornecedores.columns.visualizar')
                    ->alignCenter()
                    ->disabledClick(),
            ])
            ->defaultSort('data_entrada', 'desc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhuma nota de fornecedor encontrada');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotasFornecedores::route('/'),
        ];
    }

    protected static function formatCnpj(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) !== 14) {
            return $value ?: '—';
        }

        return substr($digits, 0, 2) . '.'
            . substr($digits, 2, 3) . '.'
            . substr($digits, 5, 3) . '/'
            . substr($digits, 8, 4) . '-'
            . substr($digits, 12, 2);
    }

    protected static function formatNsu(?string $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if ($digits === '') {
            return '—';
        }

        $trimmed = ltrim($digits, '0');

        return $trimmed !== '' ? $trimmed : '0';
    }
}
