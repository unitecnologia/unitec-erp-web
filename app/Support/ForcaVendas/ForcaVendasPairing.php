<?php

namespace App\Support\ForcaVendas;

use App\Models\Empresa;
use App\Models\ForcaVendasSetting;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ForcaVendasPairing
{
    public const SERVE_PORT = 8765;

    /**
     * Tenta descobrir o IP da maquina na rede local (LAN).
     */
    public static function detectServerIp(): string
    {
        $candidates = [];

        $host = gethostname();

        if ($host !== false) {
            $resolved = gethostbynamel($host) ?: [];
            foreach ($resolved as $ip) {
                $candidates[] = $ip;
            }
        }

        foreach ($candidates as $ip) {
            if (self::isPrivateLanIp($ip)) {
                return $ip;
            }
        }

        return $candidates[0] ?? '127.0.0.1';
    }

    private static function isPrivateLanIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        if ($ip === '127.0.0.1') {
            return false;
        }

        return (bool) preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2[0-9]|3[0-1])\.)/', $ip);
    }

    public static function baseUrl(?string $ipOverride = null, ?int $port = null): string
    {
        $ip = $ipOverride !== null && $ipOverride !== '' ? $ipOverride : self::detectServerIp();
        $port ??= self::SERVE_PORT;

        return 'http://'.$ip.':'.$port;
    }

    /**
     * Conteudo (JSON compacto) embutido no QR Code de pareamento.
     *
     * @return array<string, mixed>
     */
    public static function payload(?Empresa $empresa = null, ?string $ipOverride = null): array
    {
        $setting = ForcaVendasSetting::current();

        return [
            'v' => 1,
            'app' => 'unitec-forca-vendas',
            'url' => self::baseUrl($ipOverride),
            'secret' => $setting->pairing_secret,
            'empresa_id' => $empresa?->id,
            'empresa' => $empresa?->nome,
        ];
    }

    public static function payloadJson(?Empresa $empresa = null, ?string $ipOverride = null): string
    {
        return json_encode(
            self::payload($empresa, $ipOverride),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ) ?: '{}';
    }

    /**
     * Gera o QR Code como SVG (sem dependencia de GD/Imagick).
     */
    public static function qrSvg(string $text, int $size = 320): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, 1),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($text);
    }
}
