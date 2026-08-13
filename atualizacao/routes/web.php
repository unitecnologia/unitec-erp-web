<?php

use App\Http\Controllers\Erp\AjusteEstoqueListagemReportController;
use App\Http\Controllers\Erp\ComissaoVendedoresReportController;
use App\Http\Controllers\Erp\CompraDanfeReportController;
use App\Http\Controllers\Erp\NotaFornecedorDanfeReportController;
use App\Http\Controllers\Erp\NfeCartaCorrecaoReportController;
use App\Http\Controllers\Erp\NfeDanfeReportController;
use App\Http\Controllers\Erp\NfeEspelhoReportController;
use App\Http\Controllers\Erp\NfeEtiquetaVolumeReportController;
use App\Http\Controllers\Erp\NfeListagemReportController;
use App\Http\Controllers\Erp\ExpedicaoRetiradaReportController;
use App\Http\Controllers\Erp\ExpedicaoSeparacaoReportController;
use App\Http\Controllers\Erp\ErpWarmUrlsController;
use App\Http\Controllers\Erp\ErpBrowserResetController;
use App\Http\Controllers\Erp\DeviceServiceEnsureController;
use App\Http\Controllers\Erp\ErpUpdateController;
use App\Http\Controllers\Erp\OrcamentoReportController;
use App\Http\Controllers\Erp\NfceCancelamentoProtocoloReportController;
use App\Http\Controllers\Erp\NfceCupomReportController;
use App\Http\Controllers\Erp\NfceEscPosPrintController;
use App\Http\Controllers\Erp\NfceRelatorioReportController;
use App\Http\Controllers\Erp\QzTraySignController;
use App\Http\Controllers\Erp\PdvCupomReportController;
use App\Http\Controllers\Erp\PdvEscPosPrintController;
use App\Http\Controllers\Erp\PersonListagemReportController;
use App\Http\Controllers\Erp\ProductEstoqueReportController;
use App\Http\Controllers\Erp\ContaReceberCartoesReportController;
use App\Http\Controllers\Erp\ReciboEscPosPrintController;
use App\Http\Controllers\Erp\ReciboReportController;
use App\Http\Controllers\Erp\TabularReportController;
use App\Http\Controllers\Erp\VendaListagemReportController;
use App\Http\Controllers\Erp\PublicStorageFileController;
use App\Http\Controllers\OAuth\MeliHubOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Pós-instalação / reinstalação: limpa cookie+storage do navegador e vai ao login.
Route::middleware('web')->get('/admin/sessao-limpa', ErpBrowserResetController::class)
    ->name('erp.browser.reset');

// Hub Mercado Livre (público) — OAuth central Unitec para clientes sem domínio.
Route::middleware('web')->group(function (): void {
    Route::get('/meli/hub/connect', [MeliHubOAuthController::class, 'connect'])
        ->name('meli.hub.connect');
    Route::get('/meli/hub/oauth/callback', [MeliHubOAuthController::class, 'callback'])
        ->name('meli.hub.oauth.callback');
});

Route::middleware(['web', 'auth'])->group(function (): void {
    Route::get('/admin/erp/warm-urls', ErpWarmUrlsController::class)
        ->name('erp.warm.urls');
    Route::post('/admin/erp/device-service/ensure', DeviceServiceEnsureController::class)
        ->name('erp.device-service.ensure');
    Route::get('/admin/erp/files/{path}', PublicStorageFileController::class)
        ->where('path', '.*')
        ->name('erp.storage.file');
    Route::get('/admin/reports/produtos-estoque', ProductEstoqueReportController::class)
        ->middleware('erp.permission:produtos.print')
        ->name('erp.reports.produtos-estoque');
    Route::get('/admin/reports/pessoas-listagem', PersonListagemReportController::class)
        ->middleware('erp.permission:pessoas.print')
        ->name('erp.reports.pessoas-listagem');
    Route::get('/admin/reports/vendas-listagem', VendaListagemReportController::class)
        ->middleware('erp.permission:vendas.print')
        ->name('erp.reports.vendas-listagem');
    Route::get('/admin/reports/contas-receber-cartoes', ContaReceberCartoesReportController::class)
        ->middleware('erp.permission:contas_receber.print')
        ->name('erp.reports.contas-receber-cartoes');
    Route::get('/admin/reports/recibo/{recibo}', ReciboReportController::class)
        ->middleware('erp.permission:recibos.print')
        ->name('erp.reports.recibo');
    Route::get('/admin/print/recibo-escpos/{recibo}', ReciboEscPosPrintController::class)
        ->middleware('erp.permission:recibos.print')
        ->name('erp.print.recibo-escpos');
    Route::get('/admin/reports/ajustes-estoque-listagem', AjusteEstoqueListagemReportController::class)
        ->middleware('erp.permission:ajuste_estoque.access')
        ->name('erp.reports.ajustes-estoque-listagem');
    Route::get('/admin/reports/comissao-vendedores', ComissaoVendedoresReportController::class)
        ->middleware('erp.permission:vendas.print')
        ->name('erp.reports.comissao-vendedores');
    Route::get('/admin/reports/r/{slug}', TabularReportController::class)
        ->where('slug', '[a-z0-9\-]+')
        ->name('erp.reports.tabular');
    Route::get('/admin/reports/pdv-cupom/{venda}', PdvCupomReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.pdv-cupom');
    Route::get('/admin/reports/pdv-carne-bobina', \App\Http\Controllers\Erp\PdvCarneBobinaReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.pdv-carne-bobina');
    Route::get('/admin/reports/pdv-carne-a4', \App\Http\Controllers\Erp\PdvCarneA4ReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.pdv-carne-a4');
    Route::get('/admin/print/pdv-escpos/{venda}', PdvEscPosPrintController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.print.pdv-escpos');
    Route::get('/admin/reports/nfce-relatorio', NfceRelatorioReportController::class)
        ->middleware('erp.permission:nfce.access')
        ->name('erp.reports.nfce-relatorio');
    Route::get('/admin/reports/nfce-cupom/{venda}', NfceCupomReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.nfce-cupom');
    Route::get('/admin/print/nfce-escpos/{venda}', NfceEscPosPrintController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.print.nfce-escpos');
    Route::get('/admin/print/qz/certificate', [QzTraySignController::class, 'certificate'])
        ->name('erp.print.qz.certificate');
    Route::get('/admin/print/qz/sign', [QzTraySignController::class, 'sign'])
        ->name('erp.print.qz.sign');
    Route::get('/admin/reports/nfce-cancelamento-protocolo/{venda}', NfceCancelamentoProtocoloReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.nfce-cancelamento-protocolo');
    Route::get('/admin/reports/compra-danfe/{compra}', CompraDanfeReportController::class)
        ->name('erp.reports.compra-danfe');
    Route::get('/admin/reports/nota-fornecedor-danfe/{nota}', NotaFornecedorDanfeReportController::class)
        ->middleware('erp.permission:compras.access')
        ->name('erp.reports.nota-fornecedor-danfe');
    Route::get('/admin/reports/nfe-danfe/{nfe}', NfeDanfeReportController::class)
        ->name('erp.reports.nfe-danfe');
    Route::get('/admin/reports/nfe-espelho/{nfe}', NfeEspelhoReportController::class)
        ->name('erp.reports.nfe-espelho');
    Route::get('/admin/reports/nfe-etiqueta-volume/{nfe}', NfeEtiquetaVolumeReportController::class)
        ->name('erp.reports.nfe-etiqueta-volume');
    Route::get('/admin/reports/nfe-listagem', NfeListagemReportController::class)
        ->middleware('erp.permission:nfe.access')
        ->name('erp.reports.nfe-listagem');
    Route::get('/admin/reports/nfe-carta-correcao/{carta}', NfeCartaCorrecaoReportController::class)
        ->name('erp.reports.nfe-carta-correcao');
    Route::get('/admin/reports/orcamento/{orcamento}', OrcamentoReportController::class)
        ->name('erp.reports.orcamento');
    Route::get('/admin/reports/expedicao-separacao', ExpedicaoSeparacaoReportController::class)
        ->middleware('erp.permission:logistica.print')
        ->name('erp.reports.expedicao-separacao');
    Route::get('/admin/reports/expedicao-retirada/{entrega}', ExpedicaoRetiradaReportController::class)
        ->middleware('erp.permission:logistica.print')
        ->name('erp.reports.expedicao-retirada');
    Route::post('/admin/erp-update/launch', [ErpUpdateController::class, 'launch'])
        ->name('erp.update.launch');
    Route::post('/admin/erp-update/download', [ErpUpdateController::class, 'download'])
        ->name('erp.update.download');
    Route::post('/admin/erp-update/run-manual', [ErpUpdateController::class, 'runManual'])
        ->name('erp.update.run-manual');
});

// Status/reset sem sessão e sem CSRF: durante o update a pasta sessions pode sumir
// e o PreventRequestForgery estoura "Session store not set" (Server Error no poll).
Route::middleware(['web'])->group(function (): void {
    Route::get('/admin/erp-update/status', [ErpUpdateController::class, 'status'])
        ->withoutMiddleware([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])
        ->name('erp.update.status');
    Route::post('/admin/erp-update/reset', [ErpUpdateController::class, 'reset'])
        ->withoutMiddleware([
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class,
        ])
        ->name('erp.update.reset');
});
