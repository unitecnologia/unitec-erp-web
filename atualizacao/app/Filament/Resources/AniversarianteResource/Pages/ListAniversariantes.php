<?php

namespace App\Filament\Resources\AniversarianteResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Resources\AniversarianteResource;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListAniversariantes extends ListRecords
{
    use InteractsWithErpListPage;

    protected static string $resource = AniversarianteResource::class;

    protected static ?string $title = '';

    #[Url(as: 'ordem')]
    public string $orderColumn = 'nome_razao';

    public bool $informarPeriodo = true;

    public string $periodoDe = '';

    public string $periodoAte = '';

    public string $periodoDeApplied = '';

    public string $periodoAteApplied = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Relação Aniversariantes');

        if ($this->periodoDe === '') {
            $this->periodoDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->periodoAte === '') {
            $this->periodoAte = now()->endOfMonth()->format('Y-m-d');
        }

        if ($this->periodoDeApplied === '') {
            $this->periodoDeApplied = $this->periodoDe;
        }

        if ($this->periodoAteApplied === '') {
            $this->periodoAteApplied = $this->periodoAte;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-aniversariantes-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um aniversariante';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'create' => null,
            'edit' => null,
            'delete' => null,
            'extraKeys' => [
                'F4' => ['method' => 'modulePending', 'params' => ['Imprimir']],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return AniversarianteResource::table($table)
            ->recordUrl(null)
            ->recordAction(null);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->whereNotNull('data_nascimento')
            ->where('ativo', true);

        if ($this->informarPeriodo) {
            $this->applyBirthdayPeriodFilter($query);
        }

        $orderColumn = in_array($this->orderColumn, ['codigo', 'nome_razao', 'apelido_fantasia'], true)
            ? $this->orderColumn
            : 'nome_razao';

        return $query->orderBy($orderColumn);
    }

    protected function applyBirthdayPeriodFilter(Builder $query): void
    {
        if (blank($this->periodoDeApplied) || blank($this->periodoAteApplied)) {
            return;
        }

        $de = date('m-d', strtotime($this->periodoDeApplied));
        $ate = date('m-d', strtotime($this->periodoAteApplied));

        if ($this->databaseDriver($query) === 'sqlite') {
            if ($de <= $ate) {
                $query->whereRaw("strftime('%m-%d', data_nascimento) >= ?", [$de])
                    ->whereRaw("strftime('%m-%d', data_nascimento) <= ?", [$ate]);

                return;
            }

            $query->where(function (Builder $birthdayQuery) use ($de, $ate): void {
                $birthdayQuery
                    ->whereRaw("strftime('%m-%d', data_nascimento) >= ?", [$de])
                    ->orWhereRaw("strftime('%m-%d', data_nascimento) <= ?", [$ate]);
            });

            return;
        }

        if ($de <= $ate) {
            $query->whereRaw("DATE_FORMAT(data_nascimento, '%m-%d') >= ?", [$de])
                ->whereRaw("DATE_FORMAT(data_nascimento, '%m-%d') <= ?", [$ate]);

            return;
        }

        $query->where(function (Builder $birthdayQuery) use ($de, $ate): void {
            $birthdayQuery
                ->whereRaw("DATE_FORMAT(data_nascimento, '%m-%d') >= ?", [$de])
                ->orWhereRaw("DATE_FORMAT(data_nascimento, '%m-%d') <= ?", [$ate]);
        });
    }

    protected function databaseDriver(Builder $query): string
    {
        return $query->getConnection()->getDriverName();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.aniversariantes.screen'),
                EmbeddedTable::make()
                    ->columnSpanFull(),
                View::make('filament.components.erp.aniversariantes.action-bar'),
            ]);
    }

    public function applyPeriodFilter(): void
    {
        $this->periodoDeApplied = $this->periodoDe;
        $this->periodoAteApplied = $this->periodoAte;
        $this->resetTable();

        Notification::make()
            ->title('Período filtrado.')
            ->success()
            ->send();
    }

    public function updatedInformarPeriodo(): void
    {
        $this->resetTable();
    }

    public function updatedOrderColumn(): void
    {
        $this->resetTable();
    }
}
