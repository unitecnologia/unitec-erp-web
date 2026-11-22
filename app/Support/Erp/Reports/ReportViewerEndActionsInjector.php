<?php

namespace App\Support\Erp\Reports;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Injeta o rodapé padrão (Voltar ao Topo / Fechar) nos viewers HTML de relatório.
 * Não altera PDF/CSV/anexos nem telas de filtro.
 */
final class ReportViewerEndActionsInjector
{
    private static ?string $snippetCache = null;

    public static function clearCache(): void
    {
        self::$snippetCache = null;
    }

    public static function snippet(): string
    {
        return self::$snippetCache ??= (string) view('reports.partials.viewer-end-actions')->render();
    }

    public static function shouldInject(Request $request, Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return false;
        }

        $disposition = strtolower((string) $response->headers->get('Content-Disposition', ''));
        if ($disposition !== '' && str_contains($disposition, 'attachment')) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType !== '' && ! str_contains($contentType, 'text/html') && ! str_contains($contentType, 'application/xhtml')) {
            return false;
        }

        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return false;
        }

        if (str_contains($content, 'id="erp-report-end-actions"')) {
            return false;
        }

        // Telas de filtro (não são o documento do relatório).
        if (str_contains($content, 'erp-rvg-page') || str_contains($content, 'erp-rvg-body-page')) {
            return false;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if ($routeName !== '') {
            if (! self::isEligibleReportRoute($routeName)) {
                return false;
            }

            // Qualquer HTML de tela em rota de relatório (viewer, DANFSe, etiqueta, cupom…).
            return str_contains(strtolower($content), '</body>');
        }

        // Sem nome de rota: só viewers reconhecíveis (evita páginas aleatórias).
        return self::htmlLooksLikeReportViewer($content);
    }

    public static function inject(Response $response, ?string $snippet = null): Response
    {
        $content = $response->getContent();
        if (! is_string($content) || $content === '') {
            return $response;
        }

        if (str_contains($content, 'id="erp-report-end-actions"')) {
            return $response;
        }

        $snippet ??= self::snippet();
        $updated = self::appendBeforeBodyClose($content, $snippet);
        if ($updated === $content) {
            return $response;
        }

        $response->setContent($updated);

        return $response;
    }

    public static function isEligibleReportRoute(string $routeName): bool
    {
        if (! str_starts_with($routeName, 'erp.reports.')) {
            return false;
        }

        foreach (['.filtros', '.lookup', '.chart', '.details'] as $deny) {
            if (str_contains($routeName, $deny)) {
                return false;
            }
        }

        return true;
    }

    public static function htmlLooksLikeReportViewer(string $html): bool
    {
        if (str_contains($html, 'id="erp-report-end-actions"')) {
            return false;
        }

        // Telas de filtro (não são o documento do relatório).
        if (str_contains($html, 'erp-rvg-page') || str_contains($html, 'erp-rvg-body-page')) {
            return false;
        }

        if (str_contains($html, 'class="viewer"') || str_contains($html, "class='viewer'")) {
            return true;
        }

        if (str_contains($html, 'viewer__toolbar')) {
            return true;
        }

        if (str_contains($html, 'class="toolbar"') || str_contains($html, "class='toolbar'")) {
            return true;
        }

        // Documentos de relatório sem toolbar (ex.: etiqueta de volume).
        if (str_contains($html, 'class="folha"') || str_contains($html, "class='folha'")) {
            return true;
        }

        return false;
    }

    public static function appendBeforeBodyClose(string $html, string $snippet): string
    {
        $pos = strripos($html, '</body>');
        if ($pos === false) {
            return $html.$snippet;
        }

        return substr($html, 0, $pos).$snippet.substr($html, $pos);
    }
}
