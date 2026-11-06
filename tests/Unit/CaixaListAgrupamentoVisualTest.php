<?php

namespace Tests\Unit;

use App\Models\CaixaLancamento;
use App\Support\Erp\CaixaListAgrupamentoVisual;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CaixaListAgrupamentoVisualTest extends TestCase
{
    public function test_agrupa_os_mista_em_uma_linha(): void
    {
        $itens = collect([
            $this->lancamento('OS-74', 'OS 74 (POS DEBITO)', 80, 1),
            $this->lancamento('OS-74', 'OS 74 (PIX)', 50, 1),
            $this->lancamento('OS-74', 'OS 74 (DINHEIRO)', 100, 1),
        ]);

        $out = CaixaListAgrupamentoVisual::aplicar($itens);

        $this->assertCount(1, $out);
        $grupo = $out->first();
        $this->assertTrue((bool) $grupo->getAttribute('caixa_grupo'));
        $this->assertSame(230.0, (float) $grupo->entrada);
        $this->assertStringContainsString('OS 74 —', (string) $grupo->getAttribute('caixa_grupo_resumo'));
        $this->assertStringContainsString('DINHEIRO R$', (string) $grupo->getAttribute('caixa_grupo_resumo'));
        $this->assertStringContainsString('PIX R$', (string) $grupo->getAttribute('caixa_grupo_resumo'));
        $this->assertStringContainsString('POS DÉBITO R$', (string) $grupo->getAttribute('caixa_grupo_resumo'));
    }

    public function test_agrupa_tv_por_fv_base_mesmo_com_tag_p(): void
    {
        $itens = collect([
            $this->lancamento('FV-12/P1', 'VENDA ERP FV (DINHEIRO) [FV-12] #P1', 100, 2),
            $this->lancamento('FV-12/P2', 'VENDA ERP FV (PIX) [FV-12] #P2', 50, 2),
        ]);

        $out = CaixaListAgrupamentoVisual::aplicar($itens, null, [12 => '25']);

        $this->assertCount(1, $out);
        $resumo = (string) $out->first()->getAttribute('caixa_grupo_resumo');
        $this->assertStringStartsWith('TV 25 —', $resumo);
        $this->assertSame(150.0, (float) $out->first()->entrada);
    }

    public function test_nao_agrupa_contas_diferentes(): void
    {
        $itens = collect([
            $this->lancamento('OS-74', 'OS 74 (PIX)', 50, 1),
            $this->lancamento('OS-74', 'OS 74 (DINHEIRO)', 100, 2),
        ]);

        $out = CaixaListAgrupamentoVisual::aplicar($itens);

        $this->assertCount(2, $out);
        $this->assertFalse((bool) $out[0]->getAttribute('caixa_grupo'));
    }

    public function test_nao_agrupa_estorno_nem_parcela_os(): void
    {
        $itens = collect([
            $this->lancamento('OS-74', 'OS 74 (PIX)', 50, 1),
            $this->lancamento('OS-74', 'ESTORNO OS 74 (PIX)', 0, 1, 50),
            $this->lancamento('OS-74-1', 'OS 74 (CREDIARIO)', 30, 1),
        ]);

        $out = CaixaListAgrupamentoVisual::aplicar($itens);

        $this->assertCount(3, $out);
    }

    public function test_preserva_lancamento_avulso_entre_grupos(): void
    {
        $itens = collect([
            $this->lancamento('OS-74', 'OS 74 (PIX)', 50, 1),
            $this->lancamento('OS-74', 'OS 74 (DINHEIRO)', 100, 1),
            $this->lancamento('MAN-1', 'SANGRIA TESTE', 0, 1, 20),
        ]);

        $out = CaixaListAgrupamentoVisual::aplicar($itens);

        $this->assertCount(2, $out);
        $this->assertTrue((bool) $out[0]->getAttribute('caixa_grupo'));
        $this->assertFalse((bool) $out[1]->getAttribute('caixa_grupo'));
    }

    public function test_pdv_usa_venda_e_sessao_nao_agrupa_pdv_cx(): void
    {
        $vendaA = $this->lancamento('000012', 'VENDA PDV #000012 - PIX', 50, 1);
        $vendaA->setAttribute('pdv_sessao_id', 9);
        $vendaB = $this->lancamento('000013', 'VENDA PDV #000013 - PIX', 40, 1);
        $vendaB->setAttribute('pdv_sessao_id', 9);
        $fechamento = $this->lancamento('PDV-CX-9', 'FECHAMENTO DO CAIXA - SANGRIAS', 0, 1, 10);
        $fechamento->setAttribute('pdv_sessao_id', 9);

        $this->assertSame('pdv:9:000012', CaixaListAgrupamentoVisual::chaveGrupo($vendaA));
        $this->assertSame('pdv:9:000013', CaixaListAgrupamentoVisual::chaveGrupo($vendaB));
        $this->assertNull(CaixaListAgrupamentoVisual::chaveGrupo($fechamento));
        $this->assertNotSame(
            CaixaListAgrupamentoVisual::chaveGrupo($vendaA),
            CaixaListAgrupamentoVisual::chaveGrupo($vendaB),
        );
    }

    public function test_nao_colapsa_quando_chave_incompleta_na_pagina(): void
    {
        $itens = collect([
            $this->lancamento('OS-74', 'OS 74 (PIX)', 50, 1),
            $this->lancamento('OS-74', 'OS 74 (DINHEIRO)', 100, 1),
        ]);

        // Simula paginação: há mais membros fora da página.
        $out = CaixaListAgrupamentoVisual::aplicar($itens, []);

        $this->assertCount(2, $out);
        $this->assertFalse((bool) $out[0]->getAttribute('caixa_grupo'));
    }

    public function test_fv_12_nao_agrupa_com_fv_120(): void
    {
        $a = $this->lancamento('FV-12/P1', 'VENDA ERP FV (PIX) [FV-12] #P1', 10, 1);
        $b = $this->lancamento('FV-120', 'VENDA ERP FV (PIX) [FV-120]', 20, 1);

        $this->assertSame('fv:1:12', CaixaListAgrupamentoVisual::chaveGrupo($a));
        $this->assertSame('fv:1:120', CaixaListAgrupamentoVisual::chaveGrupo($b));
    }

    private function lancamento(
        string $documento,
        string $historico,
        float $entrada,
        int $contaId,
        float $saida = 0.0,
    ): CaixaLancamento {
        $l = new CaixaLancamento;
        $l->forceFill([
            'id' => random_int(1, 999999),
            'documento' => $documento,
            'historico' => $historico,
            'entrada' => $entrada,
            'saida' => $saida,
            'caixa_conta_id' => $contaId,
            'codigo' => (string) random_int(1, 9999),
        ]);
        $l->syncOriginal();

        return $l;
    }
}
