<?php

namespace Tests\Unit;

use App\Filament\Resources\ProductResource\Pages\Concerns\ManagesProductPrecificacao;
use App\Support\Erp\BrDecimal;
use Tests\TestCase;

/**
 * Stub mínimo para exercitar Enter/commit da precificação sem Livewire.
 */
class PrecificacaoNivelStub
{
    use ManagesProductPrecificacao;

    /** @var array<string, mixed> */
    public array $precificacao = [];

    public bool $productPrecificacaoOpen = false;

    public int $precificacaoUiEpoch = 0;

    public function formatBrDecimal(mixed $value, int $decimals = 2): string
    {
        $number = is_numeric($value) && ! is_string($value)
            ? (float) $value
            : BrDecimal::parse($value, $decimals);

        return number_format($number, $decimals, ',', '.');
    }

    public function exposeRecalcular(string $nivel, string $origem): void
    {
        $this->recalcularNivelPrecificacao($nivel, $origem);
    }

    public function exposeSet(string $path, string $value): void
    {
        $this->setPrecificacaoFieldValue($path, $value);
    }

    public function exposeCommit(string $fieldId, string $value): void
    {
        $this->productPrecificacaoOpen = true;
        $this->precificacaoCommitField($fieldId, $value);
    }

    /**
     * @return array{comissao: string, comissao_rs: string, desconto: string, desconto_rs: string, margem: string, sugerido: string, praticado: string}
     */
    protected function emptyNivel(): array
    {
        return [
            'comissao' => '0,00',
            'comissao_rs' => '0,00',
            'desconto' => '0,00',
            'desconto_rs' => '0,00',
            'margem' => '0,00',
            'sugerido' => '0,00',
            'praticado' => '0,00',
        ];
    }

    public function seedCustoBase(string $compra = '47,15'): void
    {
        $this->productPrecificacaoOpen = true;
        $this->precificacao = [
            'preco_compra' => $compra,
            'pct_custos' => '0,00',
            'custos_rs' => '0,00',
            'frete_pct' => '0,00',
            'frete_rs' => '0,00',
            'seguro_pct' => '0,00',
            'seguro_rs' => '0,00',
            'outras_pct' => '0,00',
            'outras_desp' => '0,00',
            'custo_pct_total' => '0,00',
            'preco_custo' => $compra,
            'niveis' => [
                'varejo' => array_merge($this->emptyNivel(), [
                    'margem' => '100,00',
                    'sugerido' => '94,30',
                    'praticado' => '94,30',
                ]),
                'atacado' => $this->emptyNivel(),
                'especial' => $this->emptyNivel(),
            ],
        ];
    }
}

class PrecificacaoComissaoRsEnterTest extends TestCase
{
    public function test_enter_em_comissao_rs_nao_zera_o_valor_digitado(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->productPrecificacaoOpen = true;
        $stub->precificacao = [
            'preco_custo' => '51,85',
            'niveis' => [
                'varejo' => [
                    'comissao' => '0,00',
                    'comissao_rs' => '0,00',
                    'desconto' => '0,00',
                    'desconto_rs' => '0,00',
                    'margem' => '100,00',
                    'sugerido' => '103,70',
                    'praticado' => '103,70',
                ],
            ],
        ];

        $stub->exposeSet('niveis.varejo.comissao_rs', '1,56');
        $stub->exposeRecalcular('varejo', 'comissao_rs');

        $this->assertSame('1,56', $stub->precificacao['niveis']['varejo']['comissao_rs']);
        $this->assertSame('3,01', $stub->precificacao['niveis']['varejo']['comissao']);
    }

    public function test_enter_em_comissao_pct_atualiza_rs_pelo_custo(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->productPrecificacaoOpen = true;
        $stub->precificacao = [
            'preco_custo' => '51,85',
            'niveis' => [
                'varejo' => [
                    'comissao' => '0,00',
                    'comissao_rs' => '0,00',
                    'desconto' => '0,00',
                    'desconto_rs' => '0,00',
                    'margem' => '100,00',
                    'sugerido' => '103,70',
                    'praticado' => '103,70',
                ],
            ],
        ];

        $stub->exposeSet('niveis.varejo.comissao', '3,00');
        $stub->exposeRecalcular('varejo', 'comissao');

        $this->assertSame('3,00', $stub->precificacao['niveis']['varejo']['comissao']);
        $this->assertSame('1,56', $stub->precificacao['niveis']['varejo']['comissao_rs']);
    }

    public function test_enter_em_desconto_rs_nao_zera(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->productPrecificacaoOpen = true;
        $stub->precificacao = [
            'preco_custo' => '51,85',
            'niveis' => [
                'varejo' => [
                    'comissao' => '0,00',
                    'comissao_rs' => '0,00',
                    'desconto' => '0,00',
                    'desconto_rs' => '0,00',
                    'margem' => '100,00',
                    'sugerido' => '103,70',
                    'praticado' => '103,70',
                ],
            ],
        ];

        $stub->exposeSet('niveis.varejo.desconto_rs', '2,00');
        $stub->exposeRecalcular('varejo', 'desconto_rs');

        $this->assertSame('2,00', $stub->precificacao['niveis']['varejo']['desconto_rs']);
        $this->assertGreaterThan(0, BrDecimal::parse($stub->precificacao['niveis']['varejo']['desconto'], 2));
    }

    public function test_enter_em_frete_rs_nao_zera_mesmo_com_pct_ainda_zero(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->seedCustoBase('47,15');

        // Simula corrida: R$ gravado pelo commit, % ainda 0,00 no estado.
        $stub->exposeCommit('precif-frete-rs', '1,41');

        $this->assertSame('1,41', $stub->precificacao['frete_rs']);
        $this->assertSame('2,99', $stub->precificacao['frete_pct']);
        $this->assertSame('48,56', $stub->precificacao['preco_custo']);
    }

    public function test_enter_em_custos_rs_seguro_e_outras_nao_zeram(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->seedCustoBase('100,00');

        $stub->exposeCommit('precif-custos-rs', '5,00');
        $this->assertSame('5,00', $stub->precificacao['custos_rs']);
        $this->assertSame('5,00', $stub->precificacao['pct_custos']);

        $stub->exposeCommit('precif-seguro-rs', '2,50');
        $this->assertSame('2,50', $stub->precificacao['seguro_rs']);

        $stub->exposeCommit('precif-outras', '1,00');
        $this->assertSame('1,00', $stub->precificacao['outras_desp']);

        $this->assertSame('108,50', $stub->precificacao['preco_custo']);
    }

    public function test_enter_em_frete_pct_atualiza_rs(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->seedCustoBase('100,00');

        $stub->exposeCommit('precif-frete', '3,00');

        $this->assertSame('3,00', $stub->precificacao['frete_pct']);
        $this->assertSame('3,00', $stub->precificacao['frete_rs']);
        $this->assertSame('103,00', $stub->precificacao['preco_custo']);
    }

    public function test_enter_em_margem_mantem_comissao_rs(): void
    {
        $stub = new PrecificacaoNivelStub();
        $stub->productPrecificacaoOpen = true;
        $stub->precificacao = [
            'preco_custo' => '50,00',
            'niveis' => [
                'varejo' => [
                    'comissao' => '2,00',
                    'comissao_rs' => '1,00',
                    'desconto' => '0,00',
                    'desconto_rs' => '0,00',
                    'margem' => '100,00',
                    'sugerido' => '100,00',
                    'praticado' => '101,00',
                ],
            ],
        ];

        $stub->exposeSet('niveis.varejo.margem', '80,00');
        $stub->exposeRecalcular('varejo', 'margem');

        $this->assertSame('80,00', $stub->precificacao['niveis']['varejo']['margem']);
        $this->assertSame('2,00', $stub->precificacao['niveis']['varejo']['comissao']);
        $this->assertSame('1,00', $stub->precificacao['niveis']['varejo']['comissao_rs']);
        $this->assertSame('90,00', $stub->precificacao['niveis']['varejo']['sugerido']);
        $this->assertSame('91,00', $stub->precificacao['niveis']['varejo']['praticado']);
    }
}
