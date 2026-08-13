<?php

namespace App\Http\Controllers\Api\ForcaVendas;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serve a foto de um produto para o app de força de vendas.
 *
 * Rota pública (sem login/token) porque o `Image.network` do app não envia
 * cabeçalhos de autenticação. Expõe apenas a imagem de produtos que possuem
 * foto cadastrada — nada além do catálogo que o app já recebe na sincronização.
 */
class ProductPhotoController
{
    public function __invoke(Product $product): Response
    {
        if (blank($product->foto_path)) {
            abort(404);
        }

        $path = ltrim(str_replace('\\', '/', (string) $product->foto_path), '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path, null, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
