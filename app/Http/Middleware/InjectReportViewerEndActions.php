<?php

namespace App\Http\Middleware;

use App\Support\Erp\Reports\ReportViewerEndActionsInjector;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Padroniza o rodapé dos viewers de relatório sem editar cada Blade.
 */
final class InjectReportViewerEndActions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! ReportViewerEndActionsInjector::shouldInject($request, $response)) {
            return $response;
        }

        return ReportViewerEndActionsInjector::inject($response);
    }
}
