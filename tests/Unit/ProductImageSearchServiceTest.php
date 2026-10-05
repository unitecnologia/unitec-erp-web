<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Support\Erp\ProductImageSearchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductImageSearchServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'unitec.imagens_serper.url' => 'https://google.serper.dev/images',
            'unitec.imagens_serper.key' => 'serper-test-key',
            'unitec.imagens_serper.timeout' => 12,
        ]);
    }

    public function test_normaliza_termo_e_consulta_serper(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://google.serper.dev/images' => Http::response([
                'images' => [
                    [
                        'title' => 'Agua da Pedra lata',
                        'thumbnailUrl' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:agua',
                        'imageUrl' => 'https://exemplo.test/agua-da-pedra.jpg',
                    ],
                    [
                        'title' => 'Sem foto',
                    ],
                    [
                        'title' => 'Só miniatura',
                        'thumbnailUrl' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:mini',
                    ],
                ],
            ]),
        ]);

        $found = app(ProductImageSearchService::class)->search(
            'AGUA DA PEDRA LATA C/GAS 12X350ML',
            $this->empresa(),
        );

        $this->assertNull($found['message']);
        $this->assertSame([
            [
                'thumbnail' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:agua',
                'image_url' => 'https://exemplo.test/agua-da-pedra.jpg',
                'title' => 'Agua da Pedra lata',
            ],
        ], $found['results']);

        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $type = (string) ($request->header('Content-Type')[0] ?? '');

            return $request->method() === 'POST'
                && $request->url() === 'https://google.serper.dev/images'
                && $request->hasHeader('X-API-KEY', 'serper-test-key')
                && str_contains($type, 'application/json')
                && $request->data()['q'] === 'AGUA DA PEDRA LATA COM GAS 350ML'
                && $request->data()['gl'] === 'br'
                && $request->data()['hl'] === 'pt-br'
                && (int) $request->data()['num'] === 10
                && ! str_contains($request->url(), 'openverse.org')
                && ! str_contains($request->url(), 'openfoodfacts.org');
        });
    }

    public function test_normaliza_sem_gas_pack_e_espacos(): void
    {
        Http::fake([
            'https://google.serper.dev/images' => Http::response([
                'images' => [
                    $this->image(1),
                ],
            ]),
        ]);

        app(ProductImageSearchService::class)->search(
            '  REFRIGERANTE   S/GAS   6X2L  ',
            $this->empresa(),
        );

        Http::assertSent(fn ($request): bool => $request->data()['q'] === 'REFRIGERANTE SEM GAS 2L');
    }

    public function test_segunda_tentativa_remove_embalagem_quando_nao_ha_imagem(): void
    {
        Http::fake([
            'https://google.serper.dev/images' => Http::sequence()
                ->push(['images' => []])
                ->push(['images' => [$this->image(1)]]),
        ]);

        $found = app(ProductImageSearchService::class)->search(
            'AGUA DA PEDRA LATA C/GAS 12X350ML',
            $this->empresa(),
        );

        $this->assertNull($found['message']);
        $this->assertCount(1, $found['results']);
        Http::assertSentCount(2);

        $queries = [];
        Http::recorded(function ($request) use (&$queries): void {
            $queries[] = $request->data()['q'];
        });

        $this->assertSame([
            'AGUA DA PEDRA LATA COM GAS 350ML',
            'AGUA DA PEDRA COM GAS 350ML',
        ], $queries);
    }

    public function test_nao_repete_consulta_quando_o_termo_simplificado_e_igual(): void
    {
        Http::fake([
            'https://google.serper.dev/images' => Http::response(['images' => []]),
        ]);

        $found = app(ProductImageSearchService::class)->search('AGUA DA PEDRA 350ML', $this->empresa());

        $this->assertSame([], $found['results']);
        $this->assertStringContainsString('Nenhuma imagem', (string) $found['message']);
        Http::assertSentCount(1);
    }

    public function test_limita_a_10_resultados(): void
    {
        $rows = [];

        for ($i = 1; $i <= 12; $i++) {
            $rows[] = $this->image($i);
        }

        Http::fake([
            'https://google.serper.dev/images' => Http::response(['images' => $rows]),
        ]);

        $found = app(ProductImageSearchService::class)->search('cafe', $this->empresa());

        $this->assertCount(10, $found['results']);
        $this->assertSame('Item 1', $found['results'][0]['title']);
        $this->assertSame('https://exemplo.test/10.jpg', $found['results'][9]['image_url']);
    }

    public function test_serper_nao_configurado_nao_consulta(): void
    {
        Http::fake();
        config(['unitec.imagens_serper.url' => '']);

        $found = app(ProductImageSearchService::class)->search('AGUA', $this->empresa());

        $this->assertSame([], $found['results']);
        $this->assertStringContainsString('não está configurada', (string) $found['message']);
        Http::assertNothingSent();
    }

    public function test_api_key_vazia_nao_consulta(): void
    {
        Http::fake();
        config(['unitec.imagens_serper.key' => '   ']);

        $found = app(ProductImageSearchService::class)->search('AGUA', $this->empresa());

        $this->assertSame([], $found['results']);
        $this->assertStringContainsString('API Key Serper', (string) $found['message']);
        Http::assertNothingSent();
    }

    public function test_timeout_retorna_mensagem_sem_excecao(): void
    {
        $logged = null;
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged = $event;
        });

        Http::fake([
            'https://google.serper.dev/images' => function (): void {
                throw new ConnectionException('cURL error 28: Connection timed out');
            },
        ]);

        $timeout = app(ProductImageSearchService::class)->search('Acucar', $this->empresa());

        $this->assertSame([], $timeout['results']);
        $this->assertStringContainsString('demorou demais', (string) $timeout['message']);
        $this->assertInstanceOf(MessageLogged::class, $logged);
        $this->assertSame('warning', $logged->level);
        $this->assertSame('ACUCAR', $logged->context['termo'] ?? null);
        $this->assertSame(28, $logged->context['curl_errno'] ?? null);
        $this->assertArrayNotHasKey('key', $logged->context);
    }

    public function test_401_e_403_recusam_a_chave(): void
    {
        foreach ([401, 403] as $status) {
            Http::fake([
                'https://google.serper.dev/images' => Http::response('negado', $status),
            ]);

            $found = app(ProductImageSearchService::class)->search('Acucar', $this->empresa());

            $this->assertSame([], $found['results']);
            $this->assertStringContainsString('API Key Serper foi recusada', (string) $found['message']);
        }
    }

    public function test_limite_429_retorna_mensagem_sem_excecao(): void
    {
        Http::fake([
            'https://google.serper.dev/images' => Http::response('', 429),
        ]);

        $limited = app(ProductImageSearchService::class)->search('Acucar', $this->empresa());

        $this->assertSame([], $limited['results']);
        $this->assertStringContainsString('limitada', (string) $limited['message']);
    }

    public function test_resposta_invalida(): void
    {
        Http::fake([
            'https://google.serper.dev/images' => Http::response(['ok' => true], 200),
        ]);

        $invalid = app(ProductImageSearchService::class)->search('Acucar', $this->empresa());

        $this->assertSame([], $invalid['results']);
        $this->assertStringContainsString('resposta inválida', (string) $invalid['message']);
    }

    public function test_erro_http_retorna_mensagem_sem_excecao(): void
    {
        Http::fake([
            'https://google.serper.dev/images' => Http::response('erro', 500),
        ]);

        $failed = app(ProductImageSearchService::class)->search('Acucar', $this->empresa());

        $this->assertSame([], $failed['results']);
        $this->assertSame('Não foi possível pesquisar imagens agora.', $failed['message']);
    }

    private function empresa(): Empresa
    {
        $empresa = new Empresa();
        $empresa->forceFill([
            'param_api_servicos_serper_url' => 'https://empresa.invalida/images',
            'param_api_servicos_serper_key' => 'chave-da-empresa-ignorada',
        ]);

        return $empresa;
    }

    /**
     * @return array{title: string, thumbnailUrl: string, imageUrl: string}
     */
    private function image(int $index): array
    {
        return [
            'title' => 'Item ' . $index,
            'thumbnailUrl' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:' . $index,
            'imageUrl' => 'https://exemplo.test/' . $index . '.jpg',
        ];
    }
}
