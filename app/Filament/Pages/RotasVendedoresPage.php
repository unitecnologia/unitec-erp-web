<?php

namespace App\Filament\Pages;

use App\Models\Person;
use App\Models\PersonVisitaDia;
use App\Models\Vendedor;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class RotasVendedoresPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Rotas de Vendedores';

    protected static ?string $title = '';

    protected static ?string $slug = 'rotas-vendedores';

    protected static bool $shouldRegisterNavigation = false;

    #[Url(as: 'vendedor')]
    public ?string $vendedorId = null;

    #[Url(as: 'dia')]
    public int $diaSemana = PersonVisitaDia::SEGUNDA;

    public string $searchClientes = '';

    public string $filtroLista = 'todos';

    public ?int $selectedClienteId = null;

    /** @var array<string, int> person_id => ordem (somente marcados no rascunho) */
    public array $draft = [];

    public bool $draftDirty = false;

    public function mount(): void
    {
        ErpScreen::set('Rotas de Vendedores');

        if (! array_key_exists($this->diaSemana, PersonVisitaDia::diasLabels())) {
            $this->diaSemana = PersonVisitaDia::SEGUNDA;
        }

        if (filled($this->vendedorId) && ! Vendedor::query()->whereKey((int) $this->vendedorId)->exists()) {
            $this->vendedorId = null;
        }

        $this->carregarDraft();
    }

    public static function canAccess(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'erp-list-page', 'erp-rotas-vendedores-page'];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.rotas-vendedores.screen'),
                View::make('filament.components.erp.rotas-vendedores.day-tabs'),
                View::make('filament.components.erp.rotas-vendedores.action-bar'),
            ]);
    }

    /**
     * @return Collection<int, Vendedor>
     */
    public function vendedoresOptions(): Collection
    {
        return Vendedor::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->get(['id', 'codigo', 'nome']);
    }

    public function vendedorSelecionado(): ?Vendedor
    {
        if (! filled($this->vendedorId)) {
            return null;
        }

        return Vendedor::query()->find((int) $this->vendedorId);
    }

    /**
     * @return Collection<int, Person>
     */
    public function clientesDoVendedor(): Collection
    {
        if (! filled($this->vendedorId)) {
            return collect();
        }

        $vendedorId = (int) $this->vendedorId;

        $query = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where('vendedor_fv_id', $vendedorId)
            ->orderBy('nome_razao');

        $term = mb_strtoupper(trim($this->searchClientes), 'UTF-8');

        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('endereco', 'like', $like)
                    ->orWhere('bairro', 'like', $like)
                    ->orWhere('cidade_nome', 'like', $like)
                    ->orWhere('fone1', 'like', $like)
                    ->orWhere('celular1', 'like', $like);
            });
        }

        $clientes = $query->get([
            'id',
            'codigo',
            'nome_razao',
            'endereco',
            'numero',
            'bairro',
            'cidade_nome',
            'uf',
            'fone1',
            'celular1',
            'whatsapp',
        ]);

        $mapped = $clientes->map(function (Person $cliente) {
            $key = (string) $cliente->id;
            $marcado = array_key_exists($key, $this->draft);
            $cliente->setAttribute('visita_id', $marcado ? $cliente->id : null);
            $cliente->setAttribute('visita_ordem', $marcado ? (int) $this->draft[$key] : null);

            return $cliente;
        });

        if ($this->filtroLista === 'marcados') {
            $mapped = $mapped->filter(fn (Person $c): bool => filled($c->visita_id));
        } elseif ($this->filtroLista === 'nao_marcados') {
            $mapped = $mapped->filter(fn (Person $c): bool => blank($c->visita_id));
        }

        return $mapped
            ->sortBy([
                fn (Person $c): int => filled($c->visita_id) ? 0 : 1,
                fn (Person $c): int => (int) ($c->visita_ordem ?? PHP_INT_MAX),
                fn (Person $c): string => mb_strtoupper((string) $c->nome_razao, 'UTF-8'),
            ])
            ->values();
    }

    public function contagemMarcados(): int
    {
        return count($this->draft);
    }

    public function updatedVendedorId(): void
    {
        $this->selectedClienteId = null;
        $this->searchClientes = '';
        $this->carregarDraft();
    }

    public function setDiaSemana(int $dia): void
    {
        if (! array_key_exists($dia, PersonVisitaDia::diasLabels())) {
            return;
        }

        $this->diaSemana = $dia;
        $this->selectedClienteId = null;
        $this->carregarDraft();
    }

    public function setFiltroLista(string $filtro): void
    {
        if (! in_array($filtro, ['todos', 'marcados', 'nao_marcados'], true)) {
            return;
        }

        $this->filtroLista = $filtro;
        $this->selectedClienteId = null;
    }

    public function selectCliente(int $clienteId): void
    {
        $this->selectedClienteId = $clienteId;
    }

    public function toggleCliente(int $clienteId, bool $marcado): void
    {
        if (! filled($this->vendedorId)) {
            Notification::make()
                ->title('Selecione o vendedor primeiro.')
                ->warning()
                ->send();

            return;
        }

        $key = (string) $clienteId;

        if ($marcado) {
            if (! array_key_exists($key, $this->draft)) {
                $this->draft[$key] = $this->proximaOrdemDraft();
            }
        } else {
            unset($this->draft[$key]);
            $this->renumerarDraft();
        }

        $this->draftDirty = true;
        $this->selectedClienteId = $clienteId;
    }

    public function moveCliente(int $clienteId, string $direction): void
    {
        $key = (string) $clienteId;

        if (! array_key_exists($key, $this->draft)) {
            return;
        }

        $ordenado = collect($this->draft)
            ->map(fn (int $ordem, string $id): array => ['id' => $id, 'ordem' => $ordem])
            ->sortBy('ordem')
            ->values();

        $index = $ordenado->search(fn (array $item): bool => $item['id'] === $key);

        if ($index === false) {
            return;
        }

        $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $ordenado->count()) {
            return;
        }

        $atual = $ordenado[$index];
        $outro = $ordenado[$swapIndex];

        $this->draft[$atual['id']] = (int) $outro['ordem'];
        $this->draft[$outro['id']] = (int) $atual['ordem'];
        $this->renumerarDraft();
        $this->draftDirty = true;
        $this->selectedClienteId = $clienteId;
    }

    public function setOrdem(int $clienteId, $ordem): void
    {
        $key = (string) $clienteId;

        if (! array_key_exists($key, $this->draft)) {
            return;
        }

        $this->draft[$key] = max(1, (int) $ordem);
        $this->renumerarDraft();
        $this->draftDirty = true;
        $this->selectedClienteId = $clienteId;
    }

    public function salvarRotas(): void
    {
        if (! filled($this->vendedorId)) {
            Notification::make()
                ->title('Selecione o vendedor primeiro.')
                ->warning()
                ->send();

            return;
        }

        $vendedorId = (int) $this->vendedorId;
        $dia = $this->diaSemana;

        $clienteIds = Person::query()
            ->where('is_cliente', true)
            ->where('vendedor_fv_id', $vendedorId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        DB::transaction(function () use ($vendedorId, $dia, $clienteIds): void {
            PersonVisitaDia::query()
                ->where('dia_semana', $dia)
                ->whereIn('person_id', $clienteIds)
                ->delete();

            $this->renumerarDraft();

            foreach ($this->draft as $personId => $ordem) {
                $pid = (int) $personId;

                if (! in_array($pid, $clienteIds, true)) {
                    continue;
                }

                PersonVisitaDia::query()->create([
                    'person_id' => $pid,
                    'dia_semana' => $dia,
                    'ordem' => (int) $ordem,
                ]);
            }
        });

        $this->draftDirty = false;

        Notification::make()
            ->title('Rota salva.')
            ->body(count($this->draft).' cliente(s) no dia.')
            ->success()
            ->send();
    }

    public function refreshRotas(): void
    {
        $this->selectedClienteId = null;
        $this->carregarDraft();

        Notification::make()
            ->title('Lista atualizada.')
            ->success()
            ->send();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    public function telefoneCliente(object $cliente): string
    {
        foreach (['fone1', 'celular1', 'whatsapp'] as $field) {
            $value = trim((string) ($cliente->{$field} ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '—';
    }

    public function enderecoCliente(object $cliente): string
    {
        if ($cliente instanceof Person) {
            $endereco = trim((string) $cliente->endereco_lista);

            return $endereco !== '' ? $endereco : '—';
        }

        $partes = array_filter([
            trim((string) ($cliente->endereco ?? '')),
            filled($cliente->numero ?? null) ? 'nº '.$cliente->numero : null,
            trim((string) ($cliente->bairro ?? '')),
            trim((string) ($cliente->cidade_nome ?? '')),
            trim((string) ($cliente->uf ?? '')),
        ]);

        return $partes !== [] ? implode(', ', $partes) : '—';
    }

    public function googleMapsUrl(object $cliente): ?string
    {
        $endereco = $this->enderecoCliente($cliente);

        if ($endereco === '—' || $endereco === '') {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($endereco);
    }

    private function carregarDraft(): void
    {
        $this->draft = [];
        $this->draftDirty = false;

        if (! filled($this->vendedorId)) {
            return;
        }

        $visitas = PersonVisitaDia::query()
            ->where('dia_semana', $this->diaSemana)
            ->whereHas('person', function ($q): void {
                $q->where('vendedor_fv_id', (int) $this->vendedorId)
                    ->where('is_cliente', true)
                    ->where('ativo', true);
            })
            ->orderBy('ordem')
            ->get(['person_id', 'ordem']);

        foreach ($visitas as $visita) {
            $this->draft[(string) $visita->person_id] = (int) $visita->ordem;
        }

        $this->renumerarDraft();
    }

    private function proximaOrdemDraft(): int
    {
        if ($this->draft === []) {
            return 1;
        }

        return max($this->draft) + 1;
    }

    private function renumerarDraft(): void
    {
        $ordenado = collect($this->draft)
            ->map(fn (int $ordem, string $id): array => ['id' => $id, 'ordem' => $ordem])
            ->sortBy([
                ['ordem', 'asc'],
                ['id', 'asc'],
            ])
            ->values();

        $novo = [];
        $ordem = 1;

        foreach ($ordenado as $item) {
            $novo[$item['id']] = $ordem;
            $ordem++;
        }

        $this->draft = $novo;
    }
}
