<?php

use App\Http\Controllers\Api\ForcaVendas\AuthController as FvAuthController;
use App\Http\Controllers\Api\ForcaVendas\CnpjController;
use App\Http\Controllers\Api\ForcaVendas\ComissaoController;
use App\Http\Controllers\Api\ForcaVendas\DashboardController;
use App\Http\Controllers\Api\ForcaVendas\DeviceController as FvDeviceController;
use App\Http\Controllers\Api\ForcaVendas\InfoController as FvInfoController;
use App\Http\Controllers\Api\ForcaVendas\OrcamentoController;
use App\Http\Controllers\Api\ForcaVendas\PixController;
use App\Http\Controllers\Api\ForcaVendas\ProductEstoqueFiliaisController;
use App\Http\Controllers\Api\ForcaVendas\ProductPhotoController;
use App\Http\Controllers\Api\ForcaVendas\SyncController as FvSyncController;
use App\Http\Controllers\Api\Pdv\CargaController as PdvCargaController;
use App\Http\Controllers\Api\Pdv\PedidosImportaveisController as PdvPedidosImportaveisController;
use App\Http\Controllers\Api\Pdv\RetornoController as PdvRetornoController;
use App\Http\Controllers\Api\UnitecOs\AuthController as UnitecOsAuthController;
use App\Http\Controllers\Api\UnitecOs\CepController as UnitecOsCepController;
use App\Http\Controllers\Api\UnitecOs\ClienteController as UnitecOsClienteController;
use App\Http\Controllers\Api\UnitecOs\DeviceController as UnitecOsDeviceController;
use App\Http\Controllers\Api\UnitecOs\InfoController as UnitecOsInfoController;
use App\Http\Controllers\Api\UnitecOs\OrdemServicoController as UnitecOsOrdemServicoController;
use App\Http\Controllers\Api\UnitecOs\OrdemServicoMidiaController as UnitecOsOrdemServicoMidiaController;
use App\Http\Controllers\Api\UnitecOs\ProdutoController as UnitecOsProdutoController;
use App\Http\Controllers\Api\UnitecOs\SyncController as UnitecOsSyncController;
use App\Http\Controllers\Api\VendasInternas\AuthController as ViAuthController;
use App\Http\Controllers\Api\VendasInternas\DeviceController as ViDeviceController;
use App\Http\Controllers\Api\VendasInternas\InfoController as ViInfoController;
use App\Http\Controllers\Api\VendasInternas\SyncController as ViSyncController;
use App\Http\Controllers\Api\MeliHubPairController;
use App\Http\Controllers\Webhooks\MercadoLivreWebhookController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

// Health check do launcher/serviço — JSON simples, sem Blade/Livewire/DB.
Route::get('health', static fn () => response()->json([
    'status' => 'ok',
    'version' => (string) config('unitec.versao'),
]))->name('api.health');

Route::post('webhooks/mercadolivre', [MercadoLivreWebhookController::class, 'handle'])
    ->name('webhooks.mercadolivre');

Route::prefix('meli/hub')->middleware('throttle:60,1')->group(function (): void {
    Route::post('pair', [MeliHubPairController::class, 'store'])->name('meli.hub.pair.store');
    Route::get('pair/{uuid}', [MeliHubPairController::class, 'show'])->name('meli.hub.pair.show');
});

/*
|--------------------------------------------------------------------------
| API — Força de Vendas (app dos vendedores)
|--------------------------------------------------------------------------
| O app conecta ao servidor na rede (IP/porta ou busca automática) e se
| registra enviando o nome do aparelho. O administrador autoriza o aparelho
| no ERP (tela de Aparelhos). A partir daí o vendedor faz login com a senha
| do app (users.senha_app_forca_vendas). Token por Sanctum.
*/

Route::prefix('v1/forca-vendas')->group(function (): void {
    // Públicas (descoberta + registro/autorização do aparelho).
    Route::middleware('throttle:120,1')->group(function (): void {
        Route::get('ping', [FvInfoController::class, 'ping']);
        Route::post('devices/register', [FvDeviceController::class, 'register']);
        Route::get('devices/status', [FvDeviceController::class, 'status']);
    });

    // Foto do produto (pública: Image.network não envia token).
    Route::get('produtos/{product}/foto', ProductPhotoController::class)
        ->name('forcavendas.produto.foto');

    // Webhook público do Mercado Pago (sem auth; valida consultando a API do MP).
    Route::post('webhooks/mercadopago', [MercadoPagoWebhookController::class, 'handle'])
        ->name('forcavendas.webhooks.mercadopago');

    // Exigem aparelho autorizado pelo administrador.
    Route::middleware('forcavendas.device')->group(function (): void {
        Route::get('info', [FvInfoController::class, 'index']);
        Route::get('users', [FvInfoController::class, 'users']);
        Route::post('auth/login', [FvAuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('auth/me', [FvAuthController::class, 'me']);
            Route::post('auth/logout', [FvAuthController::class, 'logout']);
            Route::get('dashboard', DashboardController::class);
            Route::get('comissao', ComissaoController::class);
            Route::get('orcamentos/{orcamento}', [OrcamentoController::class, 'show']);
            Route::get('sync/pull', [FvSyncController::class, 'pull']);
            Route::post('sync/push', [FvSyncController::class, 'push']);
            Route::get('cnpj/{cnpj}', [CnpjController::class, 'show'])
                ->where('cnpj', '\d{14}');
            Route::get('produtos/{product}/estoque-filiais', ProductEstoqueFiliaisController::class);

            // Cobranças Pix (Mercado Pago). Confirmação por polling no status.
            Route::post('pix', [PixController::class, 'store']);
            Route::get('pix/{cobranca}/status', [PixController::class, 'status']);
            Route::post('pix/{cobranca}/cancelar', [PixController::class, 'cancelar']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| API — Mini-PDV offline (carga do ERP central)
|--------------------------------------------------------------------------
| Cada caixa baixa a carga (produtos, clientes, formas de pagamento e config
| fiscal) para operar localmente mesmo com o servidor indisponível. Auth pelo
| terminal ativo (empresa_id + terminal = nº lógico ou nome em Terminais).
*/

Route::prefix('v1/pdv')->group(function (): void {
    Route::get('ping', [PdvCargaController::class, 'ping'])->middleware('throttle:120,1');

    Route::middleware('pdv.terminal.ativo')->group(function (): void {
        Route::get('licenca', [PdvCargaController::class, 'licenca']);
        Route::get('carga/pull', [PdvCargaController::class, 'pull']);
        Route::get('carga/certificado', [PdvCargaController::class, 'certificado']);
        Route::get('pedidos', [PdvPedidosImportaveisController::class, 'index']);
        Route::get('pedidos/{id}', [PdvPedidosImportaveisController::class, 'show'])->whereNumber('id');
        Route::post('retorno', [PdvRetornoController::class, 'store']);
    });
});

/*
|--------------------------------------------------------------------------
| API — Vendas Internas (app da loja)
|--------------------------------------------------------------------------
| Orçamentos abertos enviados ao ERP; o PDV importa e finaliza a venda.
*/

Route::prefix('v1/vendas-internas')->group(function (): void {
    Route::middleware('throttle:120,1')->group(function (): void {
        Route::get('ping', [ViInfoController::class, 'ping']);
        Route::post('devices/register', [ViDeviceController::class, 'register']);
        Route::get('devices/status', [ViDeviceController::class, 'status']);
    });

    Route::get('produtos/{product}/foto', ProductPhotoController::class)
        ->name('vendasinternas.produto.foto');

    Route::middleware('vendasinternas.device')->group(function (): void {
        Route::get('info', [ViInfoController::class, 'index']);
        Route::get('users', [ViInfoController::class, 'users']);
        Route::post('auth/login', [ViAuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('auth/me', [ViAuthController::class, 'me']);
            Route::post('auth/logout', [ViAuthController::class, 'logout']);
            Route::get('sync/pull', [ViSyncController::class, 'pull']);
            Route::post('sync/push', [ViSyncController::class, 'push']);
        });
    });
});

/*
|--------------------------------------------------------------------------
| API — Unitec OS (app de ordens de serviço)
|--------------------------------------------------------------------------
| Auth: Sanctum + senha_app_forca_vendas + aparelho aprovado (X-OS-Device).
| Empresa obrigatória na sessão. Lista/detalhe: mesma empresa + técnico.
| Aparelhos separados da Força de Vendas (unitec_os_devices).
*/

Route::prefix('v1/unitec-os')->group(function (): void {
    Route::middleware('throttle:60,1')->group(function (): void {
        Route::get('ping', [UnitecOsInfoController::class, 'ping']);
        Route::post('devices/register', [UnitecOsDeviceController::class, 'register']);
        Route::get('devices/status', [UnitecOsDeviceController::class, 'status']);
    });

    // Foto do produto (pública: Image.network não envia token).
    Route::get('produtos/{product}/foto', ProductPhotoController::class)
        ->name('unitecos.produto.foto');

    Route::middleware('unitecos.device')->group(function (): void {
        Route::get('info', [UnitecOsInfoController::class, 'index']);
        Route::get('users', [UnitecOsInfoController::class, 'users']);
        Route::post('auth/login', [UnitecOsAuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function (): void {
            Route::get('auth/me', [UnitecOsAuthController::class, 'me']);
            Route::post('auth/logout', [UnitecOsAuthController::class, 'logout']);
            Route::get('clientes', [UnitecOsClienteController::class, 'index']);
            Route::get('produtos', [UnitecOsProdutoController::class, 'index']);
            Route::get('grupos', [UnitecOsProdutoController::class, 'grupos']);
            Route::get('sync/pull', [UnitecOsSyncController::class, 'pull']);
            Route::get('cnpj/{cnpj}', [CnpjController::class, 'show'])
                ->where('cnpj', '\d{14}');
            Route::get('cep/{cep}', [UnitecOsCepController::class, 'show'])
                ->where('cep', '\d{8}');
            Route::get('ordens', [UnitecOsOrdemServicoController::class, 'index']);
            Route::post('ordens', [UnitecOsOrdemServicoController::class, 'store']);
            Route::put('ordens/{id}', [UnitecOsOrdemServicoController::class, 'update'])->whereNumber('id');
            Route::get('ordens/{id}', [UnitecOsOrdemServicoController::class, 'show'])->whereNumber('id');
            Route::get('ordens/{id}/midias', [UnitecOsOrdemServicoMidiaController::class, 'index'])->whereNumber('id');
            Route::post('ordens/{id}/fotos', [UnitecOsOrdemServicoMidiaController::class, 'storeFoto'])->whereNumber('id');
            Route::post('ordens/{id}/assinatura', [UnitecOsOrdemServicoMidiaController::class, 'storeAssinatura'])->whereNumber('id');
        });
    });
});
