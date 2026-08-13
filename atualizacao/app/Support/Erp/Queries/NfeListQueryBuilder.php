<?php



namespace App\Support\Erp\Queries;



use App\Models\Nfe;

use Illuminate\Database\Eloquent\Builder;

use Illuminate\Http\Request;



class NfeListQueryBuilder

{

    public function __construct(

        public string $statusFilter = 'todas',

        public string $searchColumn = 'cliente',

        public string $localSearch = '',

        public string $localSearchDe = '',

        public string $localSearchAte = '',

        public string $orderBy = 'numero',

        public string $orderDirection = 'desc',

        public ?int $empresaId = null,

    ) {}



    public static function fromRequest(Request $request): self

    {

        $allowedStatus = [

            'todas',

            Nfe::STATUS_ABERTA,

            Nfe::STATUS_TRANSMITIDA,

            Nfe::STATUS_CANCELADA,

            Nfe::STATUS_DUPLICIDADE,

            Nfe::STATUS_INUTILIZADA,

            Nfe::STATUS_DENEGADA,

            Nfe::STATUS_CONTINGENCIA,

        ];

        $allowedCampo = ['numero', 'data_emissao', 'data_saida', 'cliente', 'chave', 'protocolo', 'total'];

        $allowedOrder = ['numero', 'data_emissao', 'data_saida', 'total', 'cliente'];

        $allowedDir = ['asc', 'desc'];



        $status = (string) $request->query('status', 'todas');

        $campo = (string) $request->query('campo', 'cliente');

        $q = trim((string) $request->query('q', ''));

        $de = trim((string) $request->query('de', ''));

        $ate = trim((string) $request->query('ate', ''));

        $ordenar = (string) $request->query('ordenar', 'numero');

        $dir = (string) $request->query('dir', 'desc');

        $empresaId = $request->query('empresa_id');



        if (! in_array($campo, $allowedCampo, true)) {

            $campo = 'cliente';

        }



        $legacyChave = trim((string) $request->query('chave', ''));

        if (filled($legacyChave) && blank($q)) {

            $campo = 'chave';

            $q = $legacyChave;

        }



        if ((filled($de) || filled($ate)) && ! in_array($campo, ['data_emissao', 'data_saida'], true)) {

            $campo = 'data_emissao';

        }



        return new self(

            statusFilter: in_array($status, $allowedStatus, true) ? $status : 'todas',

            searchColumn: $campo,

            localSearch: $q,

            localSearchDe: $de,

            localSearchAte: $ate,

            orderBy: in_array($ordenar, $allowedOrder, true) ? $ordenar : 'numero',

            orderDirection: in_array($dir, $allowedDir, true) ? $dir : 'desc',

            empresaId: is_numeric($empresaId) ? (int) $empresaId : null,

        );

    }



    public function build(): Builder

    {

        $query = Nfe::query()->with(['cliente']);



        if ($this->empresaId) {

            $query->where('empresa_id', $this->empresaId);

        }



        if ($this->statusFilter !== 'todas') {

            $query->where('status', $this->statusFilter);

        }



        if ($this->isDateSearchColumn()) {

            $this->applyLocalSearchByDateRange($query);

        } elseif (filled($this->localSearch)) {

            $this->applyLocalSearch($query, $this->localSearch);

        }



        $orderBy = in_array($this->orderBy, ['numero', 'data_emissao', 'data_saida', 'total'], true)

            ? $this->orderBy

            : 'numero';

        $direction = $this->orderDirection === 'asc' ? 'asc' : 'desc';



        if ($orderBy === 'cliente') {

            return $query

                ->leftJoin('people', 'people.id', '=', 'nfes.cliente_id')

                ->orderBy('people.nome_razao', $direction)

                ->select('nfes.*');

        }



        return $query->orderBy($orderBy, $direction);

    }



    protected function isDateSearchColumn(): bool

    {

        return in_array($this->searchColumn, ['data_emissao', 'data_saida'], true);

    }



    protected function applyLocalSearchByDateRange(Builder $query): void

    {

        if (! filled($this->localSearchDe) && ! filled($this->localSearchAte)) {

            return;

        }



        $column = $this->searchColumn === 'data_saida' ? 'data_saida' : 'data_emissao';



        if (filled($this->localSearchDe)) {

            $query->whereDate($column, '>=', $this->localSearchDe);

        }



        if (filled($this->localSearchAte)) {

            $query->whereDate($column, '<=', $this->localSearchAte);

        }

    }



    /**

     * @return array<int, string>

     */

    protected function localSearchColumns(): array

    {

        return ['numero', 'data_emissao', 'data_saida', 'cliente', 'chave', 'protocolo', 'total'];

    }



    protected function applyLocalSearch(Builder $query, string $term): void

    {

        $term = mb_strtoupper(trim($term), 'UTF-8');



        if ($term === '') {

            return;

        }



        $column = in_array($this->searchColumn, $this->localSearchColumns(), true)

            ? $this->searchColumn

            : 'cliente';



        $like = '%' . $term . '%';



        match ($column) {

            'numero' => $query->where('numero', 'like', $like),

            'cliente' => $query->whereHas('cliente', fn (Builder $clienteQuery): Builder => $clienteQuery->where('nome_razao', 'like', $like)),

            'chave' => $query->where('chave', 'like', '%' . (preg_replace('/\D/', '', $term) ?? '') . '%'),

            'protocolo' => $query->where('protocolo', 'like', $like),

            'total' => $this->applyLocalSearchByTotal($query, $term),

            default => null,

        };

    }



    protected function applyLocalSearchByTotal(Builder $query, string $term): void

    {

        $normalized = str_replace(['R$', ' '], '', $term);



        if (str_contains($normalized, ',')) {

            $normalized = str_replace('.', '', $normalized);

            $normalized = str_replace(',', '.', $normalized);

        }



        if (is_numeric($normalized)) {

            if ($this->databaseDriver($query) === 'sqlite') {

                $query->whereRaw('CAST(total AS TEXT) LIKE ?', ['%' . $normalized . '%']);



                return;

            }



            $query->where('total', 'like', '%' . $normalized . '%');



            return;

        }



        if ($this->databaseDriver($query) === 'sqlite') {

            $query->whereRaw("REPLACE(printf('%.2f', total), '.', ',') LIKE ?", ['%' . $term . '%']);



            return;

        }



        $query->whereRaw("REPLACE(FORMAT(total, 2), '.', ',') LIKE ?", ['%' . $term . '%']);

    }



    protected function databaseDriver(Builder $query): string

    {

        return $query->getConnection()->getDriverName();

    }



    /**

     * @return array<string, string|null>

     */

    public function reportFilters(): array

    {

        $isDateSearch = $this->isDateSearchColumn();



        return [

            'status' => $this->statusFilter !== 'todas' ? $this->statusFilter : null,

            'campo' => $this->searchColumn !== 'cliente' ? $this->searchColumn : null,

            'q' => ! $isDateSearch && filled($this->localSearch) ? $this->localSearch : null,

            'de' => $isDateSearch && filled($this->localSearchDe) ? $this->localSearchDe : null,

            'ate' => $isDateSearch && filled($this->localSearchAte) ? $this->localSearchAte : null,

            'ordenar' => $this->orderBy !== 'numero' ? $this->orderBy : null,

            'dir' => $this->orderDirection !== 'desc' ? $this->orderDirection : null,

            'empresa_id' => $this->empresaId ? (string) $this->empresaId : null,

        ];

    }

}

