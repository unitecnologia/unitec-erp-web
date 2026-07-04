<?php

use App\Http\Controllers\Api\ForcaVendas\AuthController as FvAuthController;
use App\Http\Controllers\Api\ForcaVendas\CnpjController;
use App\Http\Controllers\Api\ForcaVendas\DeviceController as FvDeviceController;
use App\Http\Controllers\Api\ForcaVendas\InfoController as FvInfoController;
use App\Http\Controllers\Api\ForcaVendas\PixController;
use App\Http\Controllers\Api\ForcaVendas\ProductPhotoController;
use App\Http\Controllers\Api\ForcaVendas\SyncController as FvSyncController;
use App\Http\Controllers\Api\VendasInternas\AuthController as ViAuthController;
use App\Http\Controllers\Api\VendasInternas\DeviceController as ViDeviceController;
use App\Http\Controllers\Api\VendasInternas\InfoController as ViInfoController;
use App\Http\Controllers\Api\VendasInternas\SyncController as ViSyncController;
use App\Http\Controllers\Webhooks\MercadoPagoWebhookController;
use Illuminate\Support\Facades\Route;

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
            Route::get('sync/pull', [FvSyncController::class, 'pull']);
            Route::post('sync/push', [FvSyncController::class, 'push']);
            Route::get('cnpj/{cnpj}', [CnpjController::class, 'show'])
                ->where('cnpj', '\d{14}');

            // Cobranças Pix (Mercado Pago). Confirmação por polling no status.
            Route::post('pix', [PixController::class, 'store']);
            Route::get('pix/{cobranca}/status', [PixController::class, 'status']);
            Route::post('pix/{cobranca}/cancelar', [PixController::class, 'cancelar']);
        });
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
