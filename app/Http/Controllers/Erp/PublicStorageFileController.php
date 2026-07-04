<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PublicStorageFileController
{
    public function __invoke(Request $request, string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }
}
