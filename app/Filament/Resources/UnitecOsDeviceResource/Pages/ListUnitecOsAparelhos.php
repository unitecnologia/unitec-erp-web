<?php

namespace App\Filament\Resources\UnitecOsDeviceResource\Pages;

use App\Filament\Resources\UnitecOsDeviceResource;
use App\Models\UnitecOsDevice;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ListUnitecOsAparelhos extends ListRecords
{
    public function mount(): void
    {
        $this->redirect(\App\Filament\Resources\TerminalResource::getUrl('index').'?tab=aparelhos', navigate: true);
    }

    protected static string $resource = UnitecOsDeviceResource::class;

    protected static ?string $title = 'Aparelhos Unitec OS';

    #[Url(as: 'status')]
    public string $statusFilter = 'pendentes';

    public function table(Table $table): Table
    {
        return UnitecOsDeviceResource::table($table)
            ->recordActions([
                Action::make('autorizar')
                    ->label('Autorizar')
                    ->color('success')
                    ->visible(fn (UnitecOsDevice $record): bool => ! $record->isApproved())
                    ->action(fn (UnitecOsDevice $record) => $this->autorizarAparelho($record)),
                Action::make('revogar')
                    ->label('Revogar')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (UnitecOsDevice $record): bool => $record->isApproved())
                    ->action(fn (UnitecOsDevice $record) => $this->revogarAparelho($record)),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with('user');

        return match ($this->statusFilter) {
            'pendentes' => $query->whereNull('revoked_at')->where('status', '!=', UnitecOsDevice::STATUS_APROVADO),
            'ativos' => $query->whereNull('revoked_at')->where('status', UnitecOsDevice::STATUS_APROVADO),
            'revogados' => $query->whereNotNull('revoked_at'),
            default => $query,
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('filtrarPendentes')
                ->label('Pendentes')
                ->color($this->statusFilter === 'pendentes' ? 'warning' : 'gray')
                ->action(fn () => $this->setStatusFilter('pendentes')),
            Action::make('filtrarAtivos')
                ->label('Ativos')
                ->color($this->statusFilter === 'ativos' ? 'success' : 'gray')
                ->action(fn () => $this->setStatusFilter('ativos')),
            Action::make('filtrarRevogados')
                ->label('Revogados')
                ->color($this->statusFilter === 'revogados' ? 'danger' : 'gray')
                ->action(fn () => $this->setStatusFilter('revogados')),
            Action::make('filtrarTodos')
                ->label('Todos')
                ->color($this->statusFilter === 'todos' ? 'primary' : 'gray')
                ->action(fn () => $this->setStatusFilter('todos')),
        ];
    }

    private function setStatusFilter(string $status): void
    {
        $this->statusFilter = $status;
        $this->resetTable();
    }

    private function autorizarAparelho(UnitecOsDevice $device): void
    {
        $user = Auth::user();
        if (! ErpAccess::can($user, 'ordens_servico.access') && ! ErpAccess::can($user, 'terminais.update')) {
            Notification::make()->title('Sem permissão para autorizar aparelhos.')->danger()->send();

            return;
        }

        if ($device->isApproved()) {
            Notification::make()->title('Este aparelho já está autorizado.')->info()->send();

            return;
        }

        $empresaId = (int) ($device->empresa_id ?: ErpContext::currentEmpresaId() ?: 0);

        $device->forceFill([
            'empresa_id' => $empresaId ?: $device->empresa_id,
            'status' => UnitecOsDevice::STATUS_APROVADO,
            'revoked_at' => null,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ])->save();

        $this->resetTable();

        Notification::make()
            ->title('Aparelho autorizado. O técnico já pode entrar no Unitec OS.')
            ->success()
            ->send();
    }

    private function revogarAparelho(UnitecOsDevice $device): void
    {
        $user = Auth::user();
        if (! ErpAccess::can($user, 'ordens_servico.access') && ! ErpAccess::can($user, 'terminais.update')) {
            Notification::make()->title('Sem permissão para revogar aparelhos.')->danger()->send();

            return;
        }

        if ($device->current_token_id) {
            DB::table('personal_access_tokens')->where('id', $device->current_token_id)->delete();
        }

        $device->forceFill([
            'status' => UnitecOsDevice::STATUS_REVOGADO,
            'current_token_id' => null,
            'revoked_at' => now(),
        ])->save();

        $this->resetTable();

        Notification::make()
            ->title('Aparelho revogado.')
            ->success()
            ->send();
    }
}
