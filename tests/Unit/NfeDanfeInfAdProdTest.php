<?php

namespace Tests\Unit;

use App\Models\NfeItem;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use Illuminate\Support\Facades\Blade;
use ReflectionMethod;
use Tests\TestCase;

class NfeDanfeInfAdProdTest extends TestCase
{
    public function test_item_sem_inf_ad_prod_nao_gera_linha_adicional(): void
    {
        $row = $this->buildItemRow([
            'descricao' => 'COCADA C/ LEITE CONDESADO 60 NGR',
            'info_adicionais' => null,
            'quantidade' => 1,
            'valor_unitario' => 1.99,
            'total' => 1.99,
        ]);

        $this->assertSame('', $row['info_adicionais']);
        $this->assertStringNotContainsString(
            'Informação adicional do produto',
            $this->renderDescCell($row),
        );
    }

    public function test_item_com_inf_ad_prod_aparece_logo_abaixo_da_descricao(): void
    {
        $row = $this->buildItemRow([
            'descricao' => 'COCADA C/ LEITE CONDESADO 60 NGR',
            'info_adicionais' => 'teste de nota fiscal santa catarina',
            'quantidade' => 1,
            'valor_unitario' => 1.99,
            'total' => 1.99,
        ]);

        $html = $this->renderDescCell($row);

        $this->assertSame('teste de nota fiscal santa catarina', $row['info_adicionais']);
        $this->assertStringContainsString('COCADA C/ LEITE CONDESADO 60 NGR', $html);
        $this->assertStringContainsString(
            'Informação adicional do produto: teste de nota fiscal santa catarina',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/COCADA C\/ LEITE CONDESADO 60 NGR[\s\S]*Informação adicional do produto: teste de nota fiscal santa catarina/',
            $html,
        );
    }

    public function test_dois_itens_exibem_info_ad_prod_no_item_correto(): void
    {
        $itens = [
            $this->buildItemRow([
                'item' => 1,
                'descricao' => 'PRODUTO A',
                'info_adicionais' => 'Info do item A',
                'quantidade' => 1,
                'valor_unitario' => 10,
                'total' => 10,
            ]),
            $this->buildItemRow([
                'item' => 2,
                'descricao' => 'PRODUTO B',
                'info_adicionais' => 'Info do item B',
                'quantidade' => 2,
                'valor_unitario' => 5,
                'total' => 10,
            ]),
            $this->buildItemRow([
                'item' => 3,
                'descricao' => 'PRODUTO C',
                'info_adicionais' => '',
                'quantidade' => 1,
                'valor_unitario' => 3,
                'total' => 3,
            ]),
        ];

        $html = Blade::render(<<<'BLADE'
            @foreach ($itens as $item)
                @php $infoAdProd = trim((string) ($item['info_adicionais'] ?? '')); @endphp
                <tr data-item="{{ $item['item'] }}">
                    <td class="danfe__item-cell danfe__item-cell--desc">
                        {{ $item['descricao'] }}
                        @if ($infoAdProd !== '')
                            <div class="danfe__item-infadprod">Informação adicional do produto: {{ $infoAdProd }}</div>
                        @endif
                    </td>
                </tr>
            @endforeach
        BLADE, ['itens' => $itens]);

        $this->assertMatchesRegularExpression(
            '/data-item="1"[\s\S]*PRODUTO A[\s\S]*Informação adicional do produto: Info do item A[\s\S]*data-item="2"/',
            $html,
        );
        $this->assertMatchesRegularExpression(
            '/data-item="2"[\s\S]*PRODUTO B[\s\S]*Informação adicional do produto: Info do item B[\s\S]*data-item="3"/',
            $html,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/data-item="3"[\s\S]*Informação adicional do produto/',
            $html,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, string>
     */
    private function buildItemRow(array $attributes): array
    {
        $item = new NfeItem($attributes);
        $method = new ReflectionMethod(NfeDanfeReportService::class, 'buildItemRow');

        return $method->invoke(new NfeDanfeReportService, $item);
    }

    /**
     * @param  array<string, string>  $item
     */
    private function renderDescCell(array $item): string
    {
        return Blade::render(<<<'BLADE'
            @php $infoAdProd = trim((string) ($item['info_adicionais'] ?? '')); @endphp
            <td class="danfe__item-cell danfe__item-cell--desc">
                {{ $item['descricao'] }}
                @if ($infoAdProd !== '')
                    <div class="danfe__item-infadprod">Informação adicional do produto: {{ $infoAdProd }}</div>
                @endif
            </td>
        BLADE, ['item' => $item]);
    }
}
