<?php

namespace App\Filament\Resources\ErpOperacaoLogResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpPermissions;
use App\Filament\Resources\ErpOperacaoLogResource;
use App\Models\ErpOperacaoLog;
use App\Support\Erp\Audit\ErpOperacaoLogLabels;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListErpOperacaoLogs extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpPermissions;

    protected static string $resource = ErpOperacaoLogResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    public string $localSearchDe = '';

    public string $localSearchAte = '';

    public string $localSearchDeApplied = '';

    public string $localSearchAteApplied = '';

    #[Url(as: 'operacao')]
    public string $operacaoFilter = 'todas';

    #[Url(as: 'resultado')]
    public string $resultadoFilter = 'todos';

    #[Url(as: 'usuario')]
    public string $usuarioFilter = 'todos';

    public string $documentoFilter = '';

    public string $documentoFilterApplied = '';

    public bool $detalheModalOpen = false;

    /** @var array<string, mixed>|null */
    public ?array $detalheRegistro = null;

    public function mount(): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);

        parent::mount();

        ErpScreen::set('Log de Operações');

        if ($this->localSearchDe === '') {
            $this->localSearchDe = now()->startOfMonth()->format('Y-m-d');
        }

        if ($this->localSearchAte === '') {
            $this->localSearchAte = now()->format('Y-m-d');
        }

        if ($this->localSearchDeApplied === '') {
            $this->localSearchDeApplied = $this->localSearchDe;
        }

        if ($this->localSearchAteApplied === '') {
            $this->localSearchAteApplied = $this->localSearchAte;
        }
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-operacao-logs-page';
    }

    protected function erpListEntityName(): string
    {
        return 'um log de operação';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            'create' => null,
            'edit' => null,
            'delete' => null,
            'extraKeys' => [
                'F8' => ['method' => 'search'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return ErpOperacaoLogResource::table($table)
            ->recordUrl(null)
            ->recordAction(null)
            ->recordActions([
                Action::make('detalhes')
                    ->label('Ver detalhes')
                    ->link()
                    ->action(fn (ErpOperacaoLog $record) => $this->abrirDetalhe((int) $record->id)),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        $de = $this->parseDateStart($this->localSearchDeApplied);
        $ate = $this->parseDateEndExclusive($this->localSearchAteApplied);

        if ($de !== null) {
            $query->where('ocorrido_em', '>=', $de);
        }

        if ($ate !== null) {
            $query->where('ocorrido_em', '<', $ate);
        }

        if ($this->operacaoFilter !== 'todas' && $this->operacaoFilter !== '') {
            $query->where('operacao', mb_strtoupper($this->operacaoFilter, 'UTF-8'));
        }

        if ($this->resultadoFilter !== 'todos' && $this->resultadoFilter !== '') {
            $query->where('resultado', $this->resultadoFilter);
        }

        if ($this->usuarioFilter !== 'todos' && $this->usuarioFilter !== '') {
            $userId = (int) $this->usuarioFilter;
            if ($userId > 0) {
                $query->where('user_id', $userId);
            }
        }

        $documento = trim($this->documentoFilterApplied);
        if ($documento !== '') {
            // Prefixo (usa intervalo de data + igualdade parcial leve; evita %termo% em TEXT).
            $query->where('documento_numero', 'like', $documento.'%');
        }

        $term = trim($this->localSearch);
        if ($term !== '') {
            // Busca simples e limitada: documento ou usuário (prefixo), sem LIKE em resumo.
            $like = $term.'%';
            $query->where(function (Builder $inner) use ($like, $term): void {
                $inner->where('documento_numero', 'like', $like)
                    ->orWhere('user_nome', 'like', $like);

                $operacao = $this->resolverOperacaoPorTermo($term);
                if ($operacao !== null) {
                    $inner->orWhere('operacao', $operacao);
                }
            });
        }

        return $query->orderByDesc('ocorrido_em')->orderByDesc('id');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.operacao-logs.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.operacao-logs.action-bar'),
                View::make('filament.components.erp.operacao-logs.detail-modal'),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public function operacaoFiltroOptions(): array
    {
        return ErpOperacaoLogLabels::opcoesFiltroOperacao();
    }

    /**
     * @return array<string, string>
     */
    public function usuarioFiltroOptions(): array
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $opcoes = ['todos' => 'Todos'];

        if ($empresaId <= 0) {
            return $opcoes;
        }

        $rows = ErpOperacaoLog::query()
            ->daEmpresaAtiva($empresaId)
            ->whereNotNull('user_id')
            ->select(['user_id', 'user_nome'])
            ->distinct()
            ->orderBy('user_nome')
            ->limit(200)
            ->get();

        foreach ($rows as $row) {
            $id = (int) $row->user_id;
            if ($id <= 0) {
                continue;
            }
            $nome = trim((string) ($row->user_nome ?: 'Usuário #'.$id));
            $opcoes[(string) $id] = $nome;
        }

        return $opcoes;
    }

    public function search(): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);

        $this->localSearchDeApplied = $this->localSearchDe;
        $this->localSearchAteApplied = $this->localSearchAte;
        $this->documentoFilterApplied = trim($this->documentoFilter);
        $this->resetTable();

        Notification::make()
            ->title('Filtro aplicado.')
            ->success()
            ->send();
    }

    public function clearSearch(): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);

        $this->localSearch = '';
        $this->localSearchDe = now()->startOfMonth()->format('Y-m-d');
        $this->localSearchAte = now()->format('Y-m-d');
        $this->localSearchDeApplied = $this->localSearchDe;
        $this->localSearchAteApplied = $this->localSearchAte;
        $this->operacaoFilter = 'todas';
        $this->resultadoFilter = 'todos';
        $this->usuarioFilter = 'todos';
        $this->documentoFilter = '';
        $this->documentoFilterApplied = '';
        $this->resetTable();
    }

    public function updatedOperacaoFilter(): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);
        $this->resetTable();
    }

    public function updatedResultadoFilter(): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);
        $this->resetTable();
    }

    public function updatedUsuarioFilter(): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);
        $this->resetTable();
    }

    public function abrirDetalhe(int $id): void
    {
        abort_unless(ErpAccess::currentCan('auditoria.access'), 403);

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($id <= 0 || $empresaId <= 0) {
            return;
        }

        // Carga sob demanda do registro completo (inclui detalhes JSON).
        $registro = ErpOperacaoLog::query()
            ->daEmpresaAtiva($empresaId)
            ->whereKey($id)
            ->first(['id', 'ocorrido_em', 'user_id', 'user_nome', 'empresa_id', 'operacao', 'origem', 'documento_tipo', 'documento_id', 'documento_numero', 'resultado', 'resumo', 'detalhes', 'created_at', 'updated_at']);

        if (! $registro) {
            Notification::make()
                ->title('Registro não encontrado nesta empresa.')
                ->danger()
                ->send();

            return;
        }

        $detalhes = $registro->detalhes;
        $detalhesJson = is_array($detalhes)
            ? json_encode($detalhes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : null;

        $this->detalheRegistro = [
            'id' => (int) $registro->id,
            'ocorrido_em' => $registro->ocorrido_em?->format('d/m/Y H:i:s') ?? '—',
            'usuario' => $registro->user_nome ?: '—',
            'operacao' => ErpOperacaoLogLabels::operacao($registro->operacao),
            'operacao_codigo' => (string) $registro->operacao,
            'origem' => $registro->origem ?: '—',
            'documento' => ErpOperacaoLogLabels::documento(
                $registro->documento_tipo,
                $registro->documento_numero,
                $registro->documento_id ? (int) $registro->documento_id : null,
            ),
            'resultado' => ErpOperacaoLogLabels::resultado($registro->resultado),
            'resumo' => $registro->resumo ?: '—',
            'detalhes_json' => $detalhesJson,
        ];
        $this->detalheModalOpen = true;
    }

    public function fecharDetalhe(): void
    {
        $this->detalheModalOpen = false;
        $this->detalheRegistro = null;
    }

    private function parseDateStart(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseDateEndExclusive(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->addDay()->startOfDay()->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolverOperacaoPorTermo(string $term): ?string
    {
        $needle = mb_strtolower(trim($term), 'UTF-8');
        if ($needle === '') {
            return null;
        }

        $upper = mb_strtoupper($term, 'UTF-8');
        if (isset(ErpOperacaoLogLabels::operacoes()[$upper])) {
            return $upper;
        }

        foreach (ErpOperacaoLogLabels::operacoes() as $codigo => $label) {
            if (mb_strtolower($label, 'UTF-8') === $needle
                || str_starts_with(mb_strtolower($label, 'UTF-8'), $needle)) {
                return $codigo;
            }
        }

        return null;
    }
}
