<?php

namespace Tests\Unit;

use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\Nfe\NfeOrdemServicoService;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

/**
 * F10 NF-e Peças: CFOP/natureza vêm de Operação Fiscal (sem CFOP fixo no serviço).
 * Sem DB — espelhos em memória + reflexão do resolveCfop só quando OperacaoFiscal real não é necessária.
 */
class NfeOrdemServicoOperacaoFiscalTest extends TestCase
{
    public function test_servico_nao_tem_cfop_5102_ou_6102_fixos(): void
    {
        $src = file_get_contents(app_path('Support/Erp/Nfe/NfeOrdemServicoService.php'));

        $this->assertIsString($src);
        $this->assertStringContainsString('cfopVendaMercadoria($interestadual)', $src);
        $this->assertStringContainsString("!== 'P'", $src);
        $this->assertStringNotContainsString('5102', $src);
        $this->assertStringNotContainsString('6102', $src);
    }

    public function test_f10_monitor_redireciona_para_nfe_com_ordem_servico_id(): void
    {
        $page = file_get_contents(app_path('Filament/Resources/OrdemServicoResource/Pages/ListOrdensServico.php'));
        $list = file_get_contents(app_path('Filament/Resources/NfeResource/Pages/ListNfes.php'));

        $this->assertIsString($page);
        $this->assertStringContainsString("'F10' => ['method' => 'emitirNfePecasDaOs']", $page);
        $this->assertStringContainsString('ordem_servico_id=', $page);

        $this->assertIsString($list);
        $this->assertStringContainsString('NfeOrdemServicoService', $list);
        $this->assertStringContainsString('->montarPayload($ordem)', $list);
        $this->assertStringContainsString("nfeModalRows = \$payload['rows']", $list);
        $this->assertStringContainsString("natureza_operacao'] = \$payload['natureza_operacao']", $list);
    }

    public function test_montar_payload_inclui_somente_pecas_e_cfop_da_operacao(): void
    {
        $svc = new class extends NfeOrdemServicoService
        {
            public int $cfopForcado = 5102;

            protected function resolveCfop(Empresa $empresa, ?Person $cliente): int
            {
                $empresaUf = strtoupper(trim((string) ($empresa->uf ?? '')));
                $clienteUf = strtoupper(trim((string) ($cliente?->uf ?? '')));
                $interestadual = $clienteUf !== '' && $empresaUf !== '' && $clienteUf !== $empresaUf;

                return $interestadual ? 6102 : $this->cfopForcado;
            }

            protected function resolveEmpresa(OrdemServico $ordem): ?Empresa
            {
                return $ordem->empresa;
            }

            protected function formatNaturezaOperacao(int $cfop): string
            {
                return (string) $cfop.' - TESTE';
            }
        };

        $empresa = $this->pessoaEmpresa(1, 'SC', 'EMP TEST');
        $clienteSc = $this->pessoaCliente(10, 'SC', 'CLI SC');
        $clientePr = $this->pessoaCliente(11, 'PR', 'CLI PR');

        $ordem = $this->ordem($empresa, $clienteSc, [
            $this->item('S', null, 1, 400, 400, 'MAO DE OBRA'),
            $this->item('P', 5, 2, 102.95, 205.90, 'BIELETA'),
            $this->item('P', 6, 1, 168, 168, 'PASTILHA'),
        ]);

        $this->assertSame(10, (int) $ordem->cliente_id);

        $payloadSc = $svc->montarPayload($ordem);
        $this->assertCount(2, $payloadSc['rows']);
        $this->assertSame(['5102', '5102'], array_column($payloadSc['rows'], 'cfop'));
        $this->assertStringStartsWith('5102', $payloadSc['natureza_operacao']);
        $this->assertEqualsWithDelta(102.95, $payloadSc['rows'][0]['valor_unitario'], 0.001);

        $ordemPr = $this->ordem($empresa, $clientePr, $ordem->itens->all());
        $payloadPr = $svc->montarPayload($ordemPr);
        $this->assertCount(2, $payloadPr['rows']);
        $this->assertSame(['6102', '6102'], array_column($payloadPr['rows'], 'cfop'));
        $this->assertStringStartsWith('6102', $payloadPr['natureza_operacao']);

        // CFOP configurável (não hardcoded): troca o estadual e o payload acompanha.
        $svc->cfopForcado = 5405;
        $payloadAlt = $svc->montarPayload($ordem);
        $this->assertSame(['5405', '5405'], array_column($payloadAlt['rows'], 'cfop'));
        $this->assertStringStartsWith('5405', $payloadAlt['natureza_operacao']);
    }

    public function test_resolve_cfop_marca_interestadual_por_uf(): void
    {
        $svc = new class extends NfeOrdemServicoService
        {
            /** @var list<bool> */
            public array $flags = [];

            protected function resolveCfop(Empresa $empresa, ?Person $cliente): int
            {
                $empresaUf = strtoupper(trim((string) ($empresa->uf ?? '')));
                $clienteUf = strtoupper(trim((string) ($cliente?->uf ?? '')));
                $interestadual = $clienteUf !== ''
                    && $empresaUf !== ''
                    && $clienteUf !== $empresaUf;
                $this->flags[] = $interestadual;

                return $interestadual ? 6102 : 5102;
            }
        };

        $empresa = new Empresa(['uf' => 'SC']);
        $ref = new ReflectionMethod($svc, 'resolveCfop');
        $ref->setAccessible(true);

        $ref->invoke($svc, $empresa, new Person(['uf' => 'SC']));
        $ref->invoke($svc, $empresa, new Person(['uf' => 'PR']));
        $ref->invoke($svc, $empresa, new Person(['uf' => '']));
        $ref->invoke($svc, $empresa, new Person(['uf' => 'sc']));

        $this->assertSame([false, true, false, false], $svc->flags);
    }

    private function pessoaEmpresa(int $id, string $uf, string $nome): Empresa
    {
        $empresa = new Empresa();
        $empresa->forceFill(['uf' => $uf, 'nome' => $nome]);
        $empresa->id = $id;

        return $empresa;
    }

    private function pessoaCliente(int $id, string $uf, string $nome): Person
    {
        $pessoa = new Person();
        $pessoa->forceFill([
            'uf' => $uf,
            'nome_razao' => $nome,
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $pessoa->id = $id;

        return $pessoa;
    }

    /**
     * @param  list<OrdemServicoItem>  $itens
     */
    private function ordem(Empresa $empresa, Person $cliente, array $itens): OrdemServico
    {
        $ordem = new OrdemServico();
        $ordem->forceFill([
            'numero' => '108',
            'empresa_id' => (int) $empresa->id,
            'cliente_id' => (int) $cliente->id,
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
        ]);
        $ordem->id = 108;
        $ordem->setRelation('empresa', $empresa);
        $ordem->setRelation('cliente', $cliente);
        $ordem->setRelation('itens', new Collection($itens));

        return $ordem;
    }

    private function item(
        string $tipo,
        ?int $productId,
        float $qtd,
        float $preco,
        float $total,
        string $descricao,
    ): OrdemServicoItem {
        $item = new OrdemServicoItem([
            'tipo' => $tipo,
            'product_id' => $productId,
            'qtd' => $qtd,
            'preco' => $preco,
            'desconto' => 0,
            'total' => $total,
            'discriminacao' => $descricao,
        ]);

        if ($productId) {
            $item->setRelation('product', new Product([
                'id' => $productId,
                'codigo' => (string) $productId,
                'descricao' => $descricao,
                'unidade' => 'UN',
            ]));
        }

        return $item;
    }
}
