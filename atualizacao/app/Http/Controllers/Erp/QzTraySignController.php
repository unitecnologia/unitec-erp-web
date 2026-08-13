<?php

namespace App\Http\Controllers\Erp;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Assinatura QZ Tray (impressão 100% silenciosa, sem popup "Allow").
 * Coloque digital-certificate.txt + private-key.pem em storage/app/qz/
 * (gerados em QZ Tray → Advanced → Site Manager → Create New, no PC do caixa).
 */
class QzTraySignController
{
    public function certificate(): Response
    {
        abort_unless(Auth::check(), 403);

        $path = $this->certificatePath();
        abort_unless(is_file($path), 404, 'Certificado QZ não encontrado em storage/app/qz/digital-certificate.txt');

        return response(file_get_contents($path) ?: '', 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function sign(Request $request): Response
    {
        abort_unless(Auth::check(), 403);

        $toSign = (string) $request->query('request', $request->input('request', ''));
        abort_unless($toSign !== '', 422);

        $keyPath = $this->privateKeyPath();
        abort_unless(is_file($keyPath), 404, 'Chave privada QZ não encontrada em storage/app/qz/private-key.pem');

        $privateKey = openssl_pkey_get_private((string) file_get_contents($keyPath));
        abort_unless($privateKey !== false, 500, 'Chave privada QZ inválida');

        $signature = '';
        $ok = openssl_sign($toSign, $signature, $privateKey, OPENSSL_ALGO_SHA512);
        abort_unless($ok, 500, 'Falha ao assinar mensagem QZ');

        return response(base64_encode($signature), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function certificatePath(): string
    {
        return (string) config('unitec.qz.certificate', storage_path('app/qz/digital-certificate.txt'));
    }

    private function privateKeyPath(): string
    {
        return (string) config('unitec.qz.private_key', storage_path('app/qz/private-key.pem'));
    }
}
