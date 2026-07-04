<?php

namespace App\Support\Erp;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

final class ProductPhotoUrl
{
    public static function forPath(?string $path, bool $cacheBust = true): ?string
    {
        if (blank($path)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', (string) $path), '/');

        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        $url = Route::has('erp.storage.file')
            ? route('erp.storage.file', ['path' => $path], false)
            : asset('storage/' . $path);

        if (! $cacheBust || ! Storage::disk('public')->exists($path)) {
            return $url;
        }

        return $url . '?v=' . Storage::disk('public')->lastModified($path);
    }
}
