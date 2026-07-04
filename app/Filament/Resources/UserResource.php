<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'usuário';

    protected static ?string $pluralModelLabel = 'usuários';

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('acesso.usuarios.access');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('» Código')
                    ->sortable()
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('name')
                    ->label('Nome')
                    ->wrap(false)
                    ->weight(FontWeight::Bold),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('erpProfile.nome')
                    ->label('Perfil')
                    ->placeholder('—'),
                TextColumn::make('empresa.nome')
                    ->label('Empresa')
                    ->placeholder('—'),
                IconColumn::make('is_admin')
                    ->label('ADM')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('ativo')
                    ->label('Ativo')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'S' : 'N')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold),
            ])
            ->defaultSort('name', 'asc')
            ->striped()
            ->searchable(false)
            ->defaultPaginationPageOption(50)
            ->paginationPageOptions([25, 50, 100])
            ->selectable(false)
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('Nenhum usuário encontrado');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
