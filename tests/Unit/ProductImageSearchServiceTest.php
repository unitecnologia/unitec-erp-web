<?php

namespace Tests\Unit;

use App\Support\Erp\ProductImageSearchService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ProductImageSearchServiceTest extends TestCase
{
    public function test_normaliza_consulta_e_imagem_do_open_food_facts(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response([
                'products' => [
                    [
                        'product_name' => 'Cerveja Amstel 350ml',
                        'image_front_small_url' => 'https://images.openfoodfacts.org/images/products/1/front.200.jpg',
                        'image_url' => 'https://images.openfoodfacts.org/images/products/1/front.400.jpg',
                    ],
                    [
                        'product_name' => 'Sem foto',
                    ],
                    [
                        'product_name' => 'Só miniatura',
                        'image_front_small_url' => 'https://images.openfoodfacts.org/images/products/2/front.200.jpg',
                    ],
                ],
            ]),
        ]);

        $found = app(ProductImageSearchService::class)->search('AMSTEL LT 350ML C/12');

        $this->assertNull($found['message']);
        $this->assertSame([
            [
                'thumbnail' => 'https://images.openfoodfacts.org/images/products/1/front.200.jpg',
                'image_url' => 'https://images.openfoodfacts.org/images/products/1/front.400.jpg',
                'title' => 'Cerveja Amstel 350ml',
            ],
        ], $found['results']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'world.openfoodfacts.org/cgi/search.pl')
                && $request->data()['search_terms'] === 'AMSTEL 350ML'
                && (int) $request->data()['page_size'] === 20
                && ! str_contains($request->url(), 'openverse.org');
        });
    }

    public function test_limita_a_8_resultados_com_imagem(): void
    {
        $rows = [];

        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'product_name' => 'Item ' . $i,
                'image_front_small_url' => 'https://images.openfoodfacts.org/images/products/' . $i . '/front.200.jpg',
                'image_url' => 'https://images.openfoodfacts.org/images/products/' . $i . '/front.400.jpg',
            ];
        }

        Http::fake([
            'world.openfoodfacts.org/*' => Http::response(['products' => $rows]),
        ]);

        $found = app(ProductImageSearchService::class)->search('cafe');

        $this->assertCount(8, $found['results']);
        $this->assertSame('Item 1', $found['results'][0]['title']);
        $this->assertSame('https://images.openfoodfacts.org/images/products/8/front.400.jpg', $found['results'][7]['image_url']);
    }

    public function test_sem_imagem_informa_para_tentar_de_novo(): void
    {
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response(['products' => []]),
        ]);

        $found = app(ProductImageSearchService::class)->search('AMSTEL LT 350ML C/12');

        $this->assertSame([], $found['results']);
        $this->assertSame(
            'Nenhuma imagem de produto encontrada. Você pode alterar o texto da pesquisa e tentar novamente.',
            $found['message'],
        );
    }

    public function test_timeout_retorna_mensagem_sem_excecao(): void
    {
        $logged = null;
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged = $event;
        });

        Http::fake([
            'world.openfoodfacts.org/*' => function (): void {
                throw new ConnectionException('cURL error 28: Connection timed out');
            },
        ]);

        $timeout = app(ProductImageSearchService::class)->search('Acucar');

        $this->assertSame([], $timeout['results']);
        $this->assertStringContainsString('demorou demais', (string) $timeout['message']);
        $this->assertInstanceOf(MessageLogged::class, $logged);
        $this->assertSame('warning', $logged->level);
        $this->assertSame('Acucar', $logged->context['termo'] ?? null);
        $this->assertSame(28, $logged->context['curl_errno'] ?? null);
        $this->assertArrayHasKey('tempo_ms', $logged->context);
    }

    public function test_limite_429_retorna_mensagem_sem_excecao(): void
    {
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response('', 429),
        ]);

        $limited = app(ProductImageSearchService::class)->search('Acucar');

        $this->assertSame([], $limited['results']);
        $this->assertStringContainsString('limitada', (string) $limited['message']);
    }

    public function test_erro_da_api_retorna_mensagem_sem_excecao(): void
    {
        Http::fake([
            'world.openfoodfacts.org/*' => Http::response('erro', 500),
        ]);

        $failed = app(ProductImageSearchService::class)->search('Acucar');

        $this->assertSame([], $failed['results']);
        $this->assertSame('Não foi possível pesquisar imagens agora.', $failed['message']);
    }
}
