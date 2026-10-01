<?php

namespace Tests\Unit;

use App\Support\Erp\ProductImageSearchDownloader;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageSearchDownloaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_jpeg_valido_grava_em_products_photos(): void
    {
        Storage::disk('public')->put('products-photos/atual.jpg', 'foto-antiga');
        $temps = $this->tempSnapshot();

        $seen = [];
        $downloader = new ProductImageSearchDownloader(function (string $host) use (&$seen): array {
            $seen[] = $host;

            return ['1.1.1.1'];
        });

        $this->fake('https://cdn.example.test/*', $this->image('jpg'), [
            'Content-Type' => 'application/octet-stream',
        ]);

        $result = $downloader->download('https://cdn.example.test/nome-da-url.jpg');

        $this->assertNull($result['message']);
        $this->assertMatchesRegularExpression(
            '#^products-photos/[0-9a-f-]{36}\.jpg$#',
            (string) $result['path'],
        );
        $this->assertStringNotContainsString('nome-da-url', (string) $result['path']);
        $this->assertSame(['cdn.example.test'], $seen);
        $this->assertSame('foto-antiga', Storage::disk('public')->get('products-photos/atual.jpg'));

        $stored = Storage::disk('public')->get((string) $result['path']);
        $info = getimagesizefromstring($stored);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertSame([], $this->newTempFiles($temps));
    }

    public function test_png_webp_e_gif_validos(): void
    {
        $bodies = [
            'png' => $this->image('png'),
            'webp' => $this->image('webp'),
            'gif' => $this->image('gif'),
        ];

        Http::preventStrayRequests();
        Http::fake(function ($request) use ($bodies) {
            foreach ($bodies as $format => $body) {
                if (str_ends_with($request->url(), '/foto.' . $format)) {
                    return Http::response($body, 200, ['Content-Type' => 'application/octet-stream']);
                }
            }

            return Http::response('inesperado', 500);
        });

        foreach (array_keys($bodies) as $format) {
            $result = $this->downloader()->download('https://cdn.example.test/foto.' . $format);

            $this->assertNull($result['message'], $format);
            $this->assertSame($format, pathinfo((string) $result['path'], PATHINFO_EXTENSION), $format);
            $this->assertTrue(Storage::disk('public')->exists((string) $result['path']), $format);
        }
    }

    public function test_html_com_extensao_jpg_e_rejeitado(): void
    {
        $this->fake('https://cdn.example.test/*', '<html><body>nao e imagem</body></html>', [
            'Content-Type' => 'text/html',
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/foto.jpg');

        $this->assertRejected($result, 'A resposta não é uma imagem válida.');
    }

    public function test_content_type_jpeg_com_conteudo_invalido_e_rejeitado(): void
    {
        $this->fake('https://cdn.example.test/*', '{"erro":"isto nao e jpeg"}', [
            'Content-Type' => 'image/jpeg',
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/foto.jpg');

        $this->assertRejected($result, 'A resposta não é uma imagem válida.');
    }

    public function test_arquivo_acima_de_2_mb_nao_e_gravado(): void
    {
        $body = str_repeat('B', ProductImageSearchDownloader::MAX_BYTES + 1);

        $this->fake('https://cdn.example.test/*', $body, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => '64',
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/grande.jpg');

        $this->assertRejected($result, 'Imagem acima de 2 MB.');
    }

    public function test_imagem_acima_de_2000_px_e_rejeitada(): void
    {
        $this->fake('https://cdn.example.test/*', $this->image('jpg', 2001, 1), [
            'Content-Type' => 'image/png',
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/larga.jpg');

        $this->assertRejected($result, 'Imagem acima de 2000 px.');
    }

    public function test_imagem_acima_do_limite_de_pixels_e_rejeitada(): void
    {
        $this->fake('https://cdn.example.test/*', $this->image('jpg', 1800, 1700), [
            'Content-Type' => 'text/plain',
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/muitos-pixels.jpg');

        $this->assertRejected($result, 'Imagem acima de 3 megapixels.');
        $this->assertGreaterThan(ProductImageSearchDownloader::MAX_PIXELS, 1800 * 1700);
        $this->assertLessThanOrEqual(ProductImageSearchDownloader::MAX_SIDE_PX, 1800);
        $this->assertLessThanOrEqual(ProductImageSearchDownloader::MAX_SIDE_PX, 1700);
    }

    public function test_url_invalida(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        foreach (['', 'foto.jpg', 'ftp://cdn.example.test/a.jpg', 'file:///C:/Windows/win.ini', 'http://user:pass@cdn.example.test/a.jpg'] as $url) {
            $result = $this->downloader()->download($url);

            $this->assertRejected($result, 'URL inválida.');
        }

        Http::assertNothingSent();
    }

    public function test_localhost_e_bloqueado(): void
    {
        $this->assertBlockedWithoutRequest('http://localhost/foto.jpg');
        $this->assertBlockedWithoutRequest('http://LOCALHOST/foto.jpg');
        $this->assertBlockedWithoutRequest('http://[::1]/foto.jpg');
    }

    public function test_loopback_127_e_bloqueado(): void
    {
        $this->assertBlockedWithoutRequest('http://127.0.0.1/foto.jpg');
        $this->assertBlockedWithoutRequest('http://127.0.0.1:8765/admin');
    }

    public function test_ip_privado_e_bloqueado(): void
    {
        $this->assertBlockedWithoutRequest('http://192.168.0.10/foto.jpg');
        $this->assertBlockedWithoutRequest('http://10.0.0.5/foto.jpg');
        $this->assertBlockedWithoutRequest('http://172.16.5.5/foto.jpg');
        $this->assertBlockedWithoutRequest('http://169.254.169.254/latest/meta-data');
    }

    public function test_hostname_que_resolve_ip_privado_nao_conecta(): void
    {
        Http::fake();
        Http::preventStrayRequests();

        $downloader = new ProductImageSearchDownloader(fn (): array => ['192.168.1.20', '1.1.1.1']);
        $result = $downloader->download('https://intranet.example.test/foto.jpg');

        $this->assertRejected($result, 'Endereço de imagem não permitido.');
        Http::assertNothingSent();
    }

    public function test_redirect_para_ip_privado_e_bloqueado(): void
    {
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/start.jpg')) {
                return Http::response('', 302, ['Location' => 'http://192.168.1.50/secret.jpg']);
            }

            return Http::response('nao deveria seguir', 500);
        });

        $result = $this->downloader()->download('https://cdn.example.test/start.jpg');

        $this->assertRejected($result, 'Endereço de imagem não permitido.');
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '192.168.1.50'));
    }

    public function test_redirect_publico_grava_somente_a_imagem_final(): void
    {
        $jpeg = $this->image('jpg');

        Http::preventStrayRequests();
        Http::fake(function ($request) use ($jpeg) {
            if (str_ends_with($request->url(), '/start.jpg')) {
                return Http::response('<html>redirect</html>', 302, [
                    'Location' => 'https://cdn.example.test/final.jpg',
                ]);
            }

            if (str_ends_with($request->url(), '/final.jpg')) {
                return Http::response($jpeg, 200, ['Content-Type' => 'text/html']);
            }

            return Http::response('inesperado', 500);
        });

        $result = $this->downloader()->download('https://cdn.example.test/start.jpg');

        $this->assertNull($result['message']);
        $this->assertMatchesRegularExpression('#^products-photos/[0-9a-f-]{36}\.jpg$#', (string) $result['path']);
        Http::assertSentCount(2);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_timeout_de_conexao_nao_grava_arquivo(): void
    {
        $before = $this->tempSnapshot();

        Http::preventStrayRequests();
        Http::fake([
            'cdn.example.test/*' => function (): void {
                throw new ConnectionException('cURL error 28: Connection timed out');
            },
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/foto.jpg');

        $this->assertRejected($result, 'Não foi possível baixar a imagem.');
        $this->assertSame([], $this->newTempFiles($before));
    }

    public function test_falha_nao_gera_arquivo_final(): void
    {
        Storage::disk('public')->put('products-photos/atual.jpg', 'foto-antiga');
        $before = $this->tempSnapshot();

        $this->fake('https://cdn.example.test/*', '<!DOCTYPE html><html></html>', [
            'Content-Type' => 'image/jpeg',
        ]);

        $result = $this->downloader()->download('https://cdn.example.test/quebrada.jpg');

        $this->assertNull($result['path']);
        $this->assertSame(['products-photos/atual.jpg'], Storage::disk('public')->allFiles());
        $this->assertSame('foto-antiga', Storage::disk('public')->get('products-photos/atual.jpg'));
        $this->assertSame([], $this->newTempFiles($before));
    }

    private function downloader(): ProductImageSearchDownloader
    {
        return new ProductImageSearchDownloader(fn (): array => ['1.1.1.1']);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function fake(string $pattern, string $body, array $headers): void
    {
        Http::preventStrayRequests();
        Http::fake([
            $pattern => Http::response($body, 200, $headers),
        ]);
    }

    private function assertBlockedWithoutRequest(string $url): void
    {
        $called = false;
        $downloader = new ProductImageSearchDownloader(function () use (&$called): array {
            $called = true;

            return ['1.1.1.1'];
        });

        Http::fake();
        Http::preventStrayRequests();

        $result = $downloader->download($url);

        $this->assertRejected($result, 'Endereço de imagem não permitido.');
        $this->assertFalse($called, $url);
        Http::assertNothingSent();
    }

    /**
     * @param  array{path: ?string, message: ?string}  $result
     */
    private function assertRejected(array $result, string $message): void
    {
        $this->assertNull($result['path']);
        $this->assertSame($message, $result['message']);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    private function image(string $format, int $width = 8, int $height = 8): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 20, 80, 140));

        ob_start();

        $ok = match ($format) {
            'jpg' => imagejpeg($image, null, 85),
            'png' => imagepng($image),
            'gif' => imagegif($image),
            'webp' => imagewebp($image),
            default => false,
        };

        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $this->assertNotFalse($ok);
        $this->assertNotSame('', $bytes);

        return $bytes;
    }

    /**
     * @return list<string>
     */
    private function tempSnapshot(): array
    {
        $files = glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'erpimg*');

        return is_array($files) ? $files : [];
    }

    /**
     * @param  list<string>  $before
     * @return list<string>
     */
    private function newTempFiles(array $before): array
    {
        return array_values(array_diff($this->tempSnapshot(), $before));
    }
}
