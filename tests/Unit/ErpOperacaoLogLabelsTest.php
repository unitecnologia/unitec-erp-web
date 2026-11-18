<?php

namespace Tests\Unit;

use App\Support\Erp\Audit\ErpOperacaoLogLabels;
use App\Support\Erp\ErpPermissionCatalog;
use Tests\TestCase;

class ErpOperacaoLogLabelsTest extends TestCase
{
    public function test_rotulos_amigaveis_das_operacoes_principais(): void
    {
        $this->assertSame('Estorno de venda', ErpOperacaoLogLabels::operacao('ESTORNAR_VENDA'));
        $this->assertSame('Reabertura de compra', ErpOperacaoLogLabels::operacao('REABRIR_LANCAMENTO_COMPRA'));
        $this->assertSame('Inutilização de NFC-e', ErpOperacaoLogLabels::operacao('NFCE_INUTILIZACAO'));
        $this->assertSame('Concluído', ErpOperacaoLogLabels::resultado('ok'));
        $this->assertSame('Erro', ErpOperacaoLogLabels::resultado('erro'));
        $this->assertSame('Venda #123', ErpOperacaoLogLabels::documento('venda', '123'));
        $this->assertSame('Alteração de permissões do usuário', ErpOperacaoLogLabels::operacao('ALTERAR_PERMISSOES_USUARIO'));
        $this->assertSame('Exclusão de lançamento do caixa', ErpOperacaoLogLabels::operacao('EXCLUIR_LANCAMENTO_CAIXA'));
        $this->assertSame('Alteração de TEF do terminal', ErpOperacaoLogLabels::operacao('ALTERAR_TERMINAL_TEF'));
        $this->assertSame('Exclusão de funcionário RH', ErpOperacaoLogLabels::operacao('EXCLUIR_RH_FUNCIONARIO'));
    }

    public function test_permissao_auditoria_access_existe_no_catalogo(): void
    {
        $this->assertContains('auditoria.access', ErpPermissionCatalog::allKeys());
        $this->assertArrayHasKey('auditoria', ErpPermissionCatalog::modules());
        $this->assertSame('Auditoria', ErpPermissionCatalog::modules()['auditoria']['group']);
    }
}
