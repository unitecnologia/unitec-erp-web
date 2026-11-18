<?php

namespace Tests\Unit;

use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\PlanoConta;
use App\Models\User;
use App\Support\Erp\Reports\Tabular\Definitions\PlanoContasReport;
use Illuminate\Http\Request;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class PlanoContasReportTest extends TestCase
{
    use MigratesSqliteMemory;

    public function test_filtro_de_plano_pai_inclui_subplanos_e_mantem_periodo(): void
    {
        $conta = CaixaConta::query()->create(['nome' => 'CAIXA REL PLANO', 'ativo' => true]);
        $pai = $this->plano(981101, '1', null, 'PAI REL PC');
        $filho = $this->plano(981102, '1.01', 981101, 'FILHO REL PC');
        $neto = $this->plano(981103, '1.01.01', null, 'NETO REL PC');
        $porPai = $this->plano(981104, '', 981101, 'LIGADO PELO PAI');
        $outro = $this->plano(981105, '2', null, 'OUTRO REL PC');

        $this->lancamento($conta, $pai, '2099-03-10', 10, 0);
        $this->lancamento($conta, $filho, '2099-03-11', 0, 4);
        $this->lancamento($conta, $neto, '2099-03-12', 3, 0);
        $this->lancamento($conta, $porPai, '2099-03-13', 0, 2);
        $this->lancamento($conta, $outro, '2099-03-14', 100, 0);
        $this->lancamento($conta, $filho, '2099-03-15', 0, 50, 'TRF-RELPC');
        $this->lancamento($conta, $filho, '2099-04-01', 80, 0);

        $paiBuilt = $this->report()->build($this->request(['plano' => $pai->id]));

        $this->assertSame(['PAI REL PC', 'FILHO REL PC', 'NETO REL PC', 'LIGADO PELO PAI'], $this->nomes($paiBuilt));
        $this->assertSame('13,00', $paiBuilt['totals']['entradas']);
        $this->assertSame('6,00', $paiBuilt['totals']['saidas']);
        $this->assertSame('7,00', $paiBuilt['totals']['saldo']);
        $this->assertTrue(collect($paiBuilt['summary'])->contains(fn (string $linha): bool => str_contains($linha, 'inclui subplanos')));

        $filhoBuilt = $this->report()->build($this->request(['plano' => $filho->id]));

        $this->assertSame(['FILHO REL PC', 'NETO REL PC'], $this->nomes($filhoBuilt));
        $this->assertSame('3,00', $filhoBuilt['totals']['entradas']);
        $this->assertSame('4,00', $filhoBuilt['totals']['saidas']);

        $netoBuilt = $this->report()->build($this->request(['plano' => $neto->id]));

        $this->assertSame(['NETO REL PC'], $this->nomes($netoBuilt));
        $this->assertSame('3,00', $netoBuilt['totals']['entradas']);
        $this->assertFalse(collect($netoBuilt['summary'])->contains(fn (string $linha): bool => str_contains($linha, 'inclui subplanos')));

        $todos = $this->report()->build($this->request([]));
        $nomesTodos = $this->nomes($todos);

        $this->assertContains('OUTRO REL PC', $nomesTodos);
        $this->assertContains('PAI REL PC', $nomesTodos);
        $this->assertSame('', $todos['filters']['plano']);

        $inexistente = $this->report()->build($this->request(['plano' => 2147483000]));

        $this->assertSame([], $inexistente['rows']);
        $this->assertTrue(collect($inexistente['summary'])->contains('PLANO: não encontrado'));
    }

    public function test_tela_traz_pesquisa_de_plano_com_padrao_todos(): void
    {
        $user = User::factory()->create(['is_admin' => true, 'ativo' => true]);
        $this->actingAs($user);

        $campos = $this->report()->filterFields();
        $plano = collect($campos)->firstWhere('key', 'plano');

        $this->assertSame('lookup', $plano['type'] ?? null);
        $this->assertSame('Todos os planos', $plano['placeholder'] ?? null);
        $this->assertSame('de', $campos[0]['key'] ?? null);
        $this->assertSame('ate', $campos[1]['key'] ?? null);

        $response = $this->get(route('erp.reports.tabular', ['slug' => 'plano-contas', 'de' => '2099-03-01', 'ate' => '2099-03-31']));

        $response->assertOk();
        $response->assertSee('Plano de Contas');
        $response->assertSee('Todos os planos');
        $response->assertSee('data-erp-lookup', false);
        $response->assertSee('Data de');
        $response->assertSee('Data até');
    }

    private function report(): PlanoContasReport
    {
        return new PlanoContasReport;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function request(array $query): Request
    {
        return Request::create('/admin/reports/r/plano-contas', 'GET', array_merge([
            'de' => '2099-03-01',
            'ate' => '2099-03-31',
        ], $query));
    }

    private function plano(int $codigo, string $conta, ?int $paiCodigo, string $descricao): PlanoConta
    {
        return PlanoConta::query()->create([
            'codigo' => $codigo,
            'conta_completa' => $conta !== '' ? $conta : null,
            'pai_codigo' => $paiCodigo,
            'descricao' => $descricao,
            'dc' => 'D',
            'ativo' => true,
        ]);
    }

    private function lancamento(
        CaixaConta $conta,
        PlanoConta $plano,
        string $emissao,
        float $entrada,
        float $saida,
        ?string $documento = null,
    ): void {
        CaixaLancamento::query()->create([
            'codigo' => 980000 + $plano->codigo + (int) str_replace('-', '', $emissao) + (int) ($saida * 10),
            'emissao' => $emissao,
            'documento' => $documento,
            'historico' => 'RELATORIO PLANO',
            'plano_contas' => $plano->descricao,
            'plano_conta_id' => $plano->id,
            'caixa_conta_id' => $conta->id,
            'entrada' => $entrada,
            'saida' => $saida,
        ]);
    }

    /**
     * @param  array{rows: list<array<string, mixed>>}  $built
     * @return list<string>
     */
    private function nomes(array $built): array
    {
        return array_map(static fn (array $row): string => (string) $row['plano'], $built['rows']);
    }
}
