<?php

namespace App\Filament\Resources\ForcaVendasDeviceResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\ForcaVendasDeviceResource;
use App\Models\ForcaVendasDevice;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ListForcaVendasAparelhos extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = ForcaVendasDeviceResource::class;

    protected static ?string $title = '';

    #[Url(as: 'status')]
    public string $statusFilter = 'pendentes';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Aparelhos Força de Venda');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-fv-aparelhos-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um aparelho';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'searchInput' => '.erp-entregadores__select',
            'create' => null,
            'edit' => null,
            'delete' => 'revogarAparelho',
            'extraKeys' => [
                'F2' => ['method' => 'autorizarAparelho'],
                'F4' => ['method' => 'revogarAparelho'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(ForcaVendasDeviceResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()->with('user');

        return match ($this->statusFilter) {
            'pendentes' => $query->whereNull('revoked_at')->where('status', '!=', ForcaVendasDevice::STATUS_APROVADO),
            'ativos' => $query->whereNull('revoked_at')->where('status', ForcaVendasDevice::STATUS_APROVADO),
            'revogados' => $query->whereNotNull('revoked_at'),
            default => $query,
        };
    }

    public function updatedStatusFilter(): void
    {
        $this->clearListSelection();
        $this->resetTable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.forca-vendas.aparelhos-screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.forca-vendas.aparelhos-action-bar'),
            ]);
    }

    public function autorizarAparelho(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'forca_vendas.config')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('authorize');

        if (! $recordId) {
            return;
        }

        $device = ForcaVendasDevice::query()->find($recordId);

        if (! $device) {
            return;
        }

        if ($device->isApproved()) {
            Notification::make()
                ->title('Este aparelho já está autorizado.')
                ->info()
                ->send();

            return;
        }

        $device->forceFill([
            'status' => ForcaVendasDevice::STATUS_APROVADO,
            'revoked_at' => null,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ])->save();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Aparelho autorizado. O vendedor já pode entrar no app.')
            ->success()
            ->send();
    }

    public function revogarAparelho(): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'forca_vendas.delete')) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $device = ForcaVendasDevice::query()->find($recordId);

        if (! $device) {
            return;
        }

        if ($device->current_token_id) {
            DB::table('personal_access_tokens')->where('id', $device->current_token_id)->delete();
        }

        $device->forceFill([
            'current_token_id' => null,
            'revoked_at' => now(),
        ])->save();

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Aparelho revogado. O vendedor precisará entrar novamente.')
            ->success()
            ->send();
    }

    protected function erpListSelectPrompt(string $action): string
    {
        return match ($action) {
            'delete' => 'um aparelho para revogar',
            'authorize' => 'um aparelho para autorizar',
            default => parent::erpListSelectPrompt($action),
        };
    }
}
