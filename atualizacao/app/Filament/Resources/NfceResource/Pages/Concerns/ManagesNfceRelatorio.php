<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Support\Erp\Queries\NfceListQueryBuilder;
use Illuminate\Support\Facades\Auth;

trait ManagesNfceRelatorio
{
    public function printNfceRelatorio(): void
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        $builder = new NfceListQueryBuilder(
            statusFilter: $this->statusFilter,
            searchColumn: $this->searchColumn,
            localSearch: $this->localSearch,
            periodoDe: $this->periodoDeApplied,
            periodoAte: $this->periodoAteApplied,
            chaveFilter: $this->chaveFilterApplied,
            empresaId: is_numeric($empresaId) ? (int) $empresaId : null,
        );

        $params = array_filter(
            $builder->reportFilters(),
            fn ($value): bool => filled($value),
        );

        $this->redirect(route('erp.reports.nfce-relatorio', $params), navigate: false);
    }
}
