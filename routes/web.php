<?php

use App\Http\Controllers\Erp\AjusteEstoqueListagemReportController;
use App\Http\Controllers\Erp\ComissaoVendedoresReportController;
use App\Http\Controllers\Erp\CompraDanfeReportController;
use App\Http\Controllers\Erp\ErpUpdateController;
use App\Http\Controllers\Erp\OrcamentoReportController;
use App\Http\Controllers\Erp\NfceCupomReportController;
use App\Http\Controllers\Erp\PdvCupomReportController;
use App\Http\Controllers\Erp\PersonListagemReportController;
use App\Http\Controllers\Erp\ProductEstoqueReportController;
use App\Http\Controllers\Erp\VendaListagemReportController;
use App\Http\Controllers\Erp\PublicStorageFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])->group(function (): void {
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
    Route::get('/admin/reports/ajustes-estoque-listagem', AjusteEstoqueListagemReportController::class)
        ->middleware('erp.permission:ajuste_estoque.access')
        ->name('erp.reports.ajustes-estoque-listagem');
    Route::get('/admin/reports/comissao-vendedores', ComissaoVendedoresReportController::class)
        ->middleware('erp.permission:vendas.print')
        ->name('erp.reports.comissao-vendedores');
    Route::get('/admin/reports/pdv-cupom/{venda}', PdvCupomReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.pdv-cupom');
    Route::get('/admin/reports/nfce-cupom/{venda}', NfceCupomReportController::class)
        ->middleware('erp.permission:vendas.reprint_cupom')
        ->name('erp.reports.nfce-cupom');
    Route::get('/admin/reports/compra-danfe/{compra}', CompraDanfeReportController::class)
        ->name('erp.reports.compra-danfe');
    Route::get('/admin/reports/orcamento/{orcamento}', OrcamentoReportController::class)
        ->name('erp.reports.orcamento');
    Route::post('/admin/erp-update/launch', [ErpUpdateController::class, 'launch'])
        ->name('erp.update.launch');
    Route::get('/admin/erp-update/status', [ErpUpdateController::class, 'status'])
        ->name('erp.update.status');
    Route::post('/admin/erp-update/reset', [ErpUpdateController::class, 'reset'])
        ->name('erp.update.reset');
});
