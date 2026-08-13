<?php



namespace App\Filament\Resources\NfeResource\Pages\Concerns;



use App\Support\Erp\Queries\NfeListQueryBuilder;

use Illuminate\Support\Facades\Auth;



trait ManagesNfeRelatorio

{

    public function printRelatorioNfe(): void

    {

        if (! $this->erpAuthorizeOrNotify('nfe.access')) {

            return;

        }



        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);



        $builder = new NfeListQueryBuilder(

            statusFilter: $this->statusFilter,

            searchColumn: $this->searchColumn,

            localSearch: $this->localSearch,

            localSearchDe: $this->localSearchDe,

            localSearchAte: $this->localSearchAte,

            empresaId: is_numeric($empresaId) ? (int) $empresaId : null,

        );



        $params = array_filter(

            $builder->reportFilters(),

            fn ($value): bool => filled($value),

        );



        $url = route('erp.reports.nfe-listagem', $params);



        $this->redirect($url, navigate: false);

    }

}

