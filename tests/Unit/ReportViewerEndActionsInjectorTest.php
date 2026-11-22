<?php

namespace Tests\Unit;

use App\Support\Erp\Reports\ReportViewerEndActionsInjector;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class ReportViewerEndActionsInjectorTest extends TestCase
{
    public function test_rota_de_relatorio_elegivel_e_filtros_negados(): void
    {
        $this->assertTrue(ReportViewerEndActionsInjector::isEligibleReportRoute('erp.reports.tabular'));
        $this->assertTrue(ReportViewerEndActionsInjector::isEligibleReportRoute('erp.reports.orcamento'));
        $this->assertFalse(ReportViewerEndActionsInjector::isEligibleReportRoute('erp.reports.vendas-geral.filtros'));
        $this->assertFalse(ReportViewerEndActionsInjector::isEligibleReportRoute('erp.reports.tabular.chart'));
        $this->assertFalse(ReportViewerEndActionsInjector::isEligibleReportRoute('filament.admin.pages.dashboard'));
    }

    public function test_detecta_viewer_e_ignora_tela_de_filtros(): void
    {
        $viewer = '<!DOCTYPE html><html><body><div class="viewer"><div class="viewer__toolbar">x</div></div></body></html>';
        $filtros = '<!DOCTYPE html><html><body class="erp-rvg-body-page"><div class="erp-rvg-page"></div></body></html>';
        $pdfLike = '<!DOCTYPE html><html><body>só conteúdo</body></html>';

        $this->assertTrue(ReportViewerEndActionsInjector::htmlLooksLikeReportViewer($viewer));
        $this->assertFalse(ReportViewerEndActionsInjector::htmlLooksLikeReportViewer($filtros));
        $this->assertFalse(ReportViewerEndActionsInjector::htmlLooksLikeReportViewer($pdfLike));
    }

    public function test_injeta_antes_de_body_e_e_idempotente(): void
    {
        $html = "<!DOCTYPE html><html><body><div class=\"viewer\"></div></body></html>";
        $snippet = '<div id="erp-report-end-actions">FOOTER</div>';

        $once = ReportViewerEndActionsInjector::appendBeforeBodyClose($html, $snippet);
        $this->assertStringContainsString('</div><div id="erp-report-end-actions">FOOTER</div></body>', $once);

        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        ReportViewerEndActionsInjector::inject($response, $snippet);
        $this->assertStringContainsString('id="erp-report-end-actions"', (string) $response->getContent());

        $before = (string) $response->getContent();
        ReportViewerEndActionsInjector::inject($response, $snippet.'2');
        $this->assertSame($before, (string) $response->getContent());
    }

    public function test_should_inject_respeita_attachment_e_content_type(): void
    {
        $request = Request::create('/admin/reports/x', 'GET');
        $html = '<!DOCTYPE html><html><body><div class="viewer"></div></body></html>';

        $ok = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        $this->assertTrue(ReportViewerEndActionsInjector::shouldInject($request, $ok));

        $pdf = new Response('%PDF', 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="x.pdf"',
        ]);
        $this->assertFalse(ReportViewerEndActionsInjector::shouldInject($request, $pdf));

        $csv = new Response('a;b', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="x.csv"',
        ]);
        $this->assertFalse(ReportViewerEndActionsInjector::shouldInject($request, $csv));
    }

    public function test_rota_elegivel_injeta_mesmo_sem_classe_viewer(): void
    {
        $request = Request::create('/admin/reports/nfse-impressao/1', 'GET');
        $request->setRouteResolver(static function () {
            $route = new \Illuminate\Routing\Route(['GET'], '/admin/reports/nfse-impressao/{nfse}', []);
            $route->name('erp.reports.nfse-impressao');

            return $route;
        });

        $html = '<!DOCTYPE html><html><body><table class="danfse"></table></body></html>';
        $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        $this->assertTrue(ReportViewerEndActionsInjector::shouldInject($request, $response));
    }
}
