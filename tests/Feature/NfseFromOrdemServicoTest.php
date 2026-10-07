<?php

namespace Tests\Feature;

use App\Filament\Pages\NfsePage;
use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;

class NfseFromOrdemServicoTest extends TestCase
{
    use MigratesSqliteMemory;

    private Empresa $empresa;

    private Person $cliente;

    private int $seq = 0;

    public function test_desconto_geral_do_erp_e_rateado_e_total_igual_ao_da_os(): void
    {
        $this->contexto();
        $os = $this->os(['vl_desc_servicos' => 254, 'total_servicos' => 3546], [
            ['S', 'MAO DE OBRA', 1, 3500],
            ['S', 'PROGRAMACAO', 1, 300],
            ['P', 'KIT EMBREAGEM', 1, 8860],
        ]);

        $linhas = NfseFromOrdemServico::linhas($os);

        $this->assertCount(2, $linhas);
        $this->assertSame(['3500.00', '233.95', '3266.05'], [$linhas[0]['valor'], $linhas[0]['desconto'], $linhas[0]['total']]);
        $this->assertSame(['300.00', '20.05', '279.95'], [$linhas[1]['valor'], $linhas[1]['desconto'], $linhas[1]['total']]);
        $this->assertSame('3546.00', NfseFromOrdemServico::totalLiquido($os));
    }

    public function test_os_do_app_com_desconto_no_item_nao_desconta_duas_vezes(): void
    {
        $this->contexto();
        // App grava em vl_desc_servicos a soma dos descontos dos itens.
        $os = $this->os(['vl_desc_servicos' => 50, 'total_servicos' => 3750], [
            ['S', 'MAO DE OBRA', 1, 3500, 50],
            ['S', 'PROGRAMACAO', 1, 300],
        ]);

        $linhas = NfseFromOrdemServico::linhas($os);

        $this->assertSame(['50.00', '3450.00'], [$linhas[0]['desconto'], $linhas[0]['total']]);
        $this->assertSame(['0.00', '300.00'], [$linhas[1]['desconto'], $linhas[1]['total']]);
        $this->assertSame('3750.00', NfseFromOrdemServico::totalLiquido($os));
    }

    public function test_acrescimo_do_item_e_preco_com_quatro_casas(): void
    {
        $this->contexto();
        $os = $this->os(['vl_desc_servicos' => 0, 'total_servicos' => 120.01], [
            ['S', 'HORA TECNICA', 3, 33.335],
            ['S', 'DESLOCAMENTO', 1, 10, 0, 10],
        ]);

        $linhas = NfseFromOrdemServico::linhas($os);

        // 3 x 33,335 = 100,005 -> 100,01 na OS; a NFS-e usa 33,34 e desconta 0,01 para fechar.
        $this->assertSame(['33.34', '0.01', '0.00', '100.01'], [$linhas[0]['valor'], $linhas[0]['desconto'], $linhas[0]['acrescimo'], $linhas[0]['total']]);
        $this->assertSame(['10.00', '0.00', '10.00', '20.00'], [$linhas[1]['valor'], $linhas[1]['desconto'], $linhas[1]['acrescimo'], $linhas[1]['total']]);
        $this->assertSame('120.01', NfseFromOrdemServico::totalLiquido($os));
    }

    public function test_os_so_com_pecas_e_bloqueada(): void
    {
        $this->contexto();
        $os = $this->os(['total_servicos' => 0], [['P', 'OLEO', 1, 98]]);

        $this->assertSame('A OS não tem serviço para a NFS-e.', NfseFromOrdemServico::motivoBloqueio($os));
    }

    public function test_f7_monta_nota_pelo_liquido_e_sem_pecas(): void
    {
        $this->contexto();
        $os = $this->os([
            'vl_desc_servicos' => 254,
            'total_servicos' => 3546,
            'descricao' => 'FIAT FIORINO',
            'placa' => 'LZK6311',
            'problema' => 'CAMBIO RASPANDO',
            'laudo' => 'KIT EMBREAGEM TROCADO',
        ], [
            ['S', 'MAO DE OBRA', 1, 3500],
            ['S', 'PROGRAMACAO', 1, 300],
            ['P', 'KIT EMBREAGEM', 1, 8860],
        ]);

        $lw = Livewire::withQueryParams(['os' => $os->id])->test(NfsePage::class);

        $lw->assertSet('nfseModalOpen', true)->assertSet('nfseId', null)->assertSet('nfseTomadorId', (int) $this->cliente->id);
        $servicos = $lw->get('nfseServicos');
        $this->assertCount(2, $servicos);
        $this->assertSame('233,95', $servicos[0]['desconto']);
        $this->assertSame('3.546,00', $lw->instance()->nfseServicosSomaFormatada());

        $payload = $this->chamar($lw->instance(), 'montarPayloadNfse');
        $this->assertSame('3800.00', $payload['cabecalho']['valor_servicos']);
        $this->assertSame('254.00', $payload['cabecalho']['desconto']);
        $this->assertSame('3546.00', $payload['cabecalho']['total']);
        $this->assertSame((int) $os->id, $payload['cabecalho']['ordem_servico_id']);
        $this->assertSame([(int) $os->id, (int) $os->id], array_column($payload['itens'], 'ordem_servico_id'));
        $this->assertSame(['MAO DE OBRA', 'PROGRAMACAO'], array_column($payload['itens'], 'descricao'));
        $this->assertSame(
            "OS nº {$os->numero}\nEquipamento/Veículo: FIAT FIORINO | Placa: LZK6311\nProblema: CAMBIO RASPANDO\nLaudo: KIT EMBREAGEM TROCADO",
            $payload['cabecalho']['discriminacao'],
        );
    }

    public function test_servico_prestado_da_os_vai_na_linha_e_fica_so_para_visualizar(): void
    {
        $this->contexto();
        $os = $this->os(['total_servicos' => 3], [
            ['S', 'RETIFICA CABECOTE', 1, 2],
            ['S', 'TROCA DE JUNTA', 1, 1],
        ]);
        $os->itens->firstWhere('discriminacao', 'RETIFICA CABECOTE')->forceFill(['servico_prestado' => 'PLAINA E TESTE'])->save();

        $lw = Livewire::withQueryParams(['os' => $os->id])->test(NfsePage::class);
        $servicos = $lw->get('nfseServicos');
        $indice = array_search('RETIFICA CABECOTE', array_column($servicos, 'descricao'), true);

        $this->assertSame('PLAINA E TESTE', $servicos[$indice]['servico_prestado']);
        $this->assertTrue($lw->instance()->nfseServicoPrestadoSomenteLeitura($indice));

        $lw->call('abrirNfseServicoPrestado', $indice)
            ->assertSet('nfseServicoPrestadoTexto', 'PLAINA E TESTE')
            ->assertSee('Veio da OS')
            ->set('nfseServicoPrestadoTexto', 'ALTERADO NA NOTA')
            ->call('confirmarNfseServicoPrestado');

        $this->assertSame('PLAINA E TESTE', $lw->get('nfseServicos')[$indice]['servico_prestado']);

        $payload = $this->chamar($lw->instance(), 'montarPayloadNfse');
        $this->assertSame('PLAINA E TESTE', $payload['itens'][$indice]['servico_prestado']);
        $this->assertStringNotContainsString('PLAINA E TESTE', (string) $payload['cabecalho']['discriminacao']);

        $item = new NfseItem(['descricao' => 'RETIFICA CABECOTE', 'servico_prestado' => 'PLAINA E TESTE']);
        $this->assertSame('RETIFICA CABECOTE: PLAINA E TESTE', $item->descricaoComServicoPrestado());
    }

    public function test_aba_pagamento_mostra_o_pagamento_do_faturamento_da_os(): void
    {
        $this->contexto();
        $os = $this->os([
            'total_servicos' => 2.8,
            'total_geral' => 4.6,
            'data_termino' => '2026-10-06',
            'faturamento_pagamentos' => [
                ['forma' => 'PIX', 'valor' => 1.6, 'parcelas' => null],
                ['forma' => 'CREDIARIO', 'valor' => 3.0, 'parcelas' => [
                    ['dias' => 30, 'vencimento' => '05/11/2026', 'valor' => 1.5],
                    ['dias' => 60, 'vencimento' => '05/12/2026', 'valor' => 1.5],
                ]],
            ],
        ], [['S', 'RETIFICA CABECOTE', 1, 2.8]]);

        $lw = Livewire::withQueryParams(['os' => $os->id])->test(NfsePage::class);
        $pagamentos = $lw->instance()->nfsePagamentosOs;

        $this->assertSame([
            ['os' => (string) $os->numero, 'forma' => 'PIX', 'parcela' => 'À vista', 'vencimento' => '06/10/2026', 'valor' => '1,60'],
            ['os' => (string) $os->numero, 'forma' => 'CREDIARIO', 'parcela' => '1/2', 'vencimento' => '05/11/2026', 'valor' => '1,50'],
            ['os' => (string) $os->numero, 'forma' => 'CREDIARIO', 'parcela' => '2/2', 'vencimento' => '05/12/2026', 'valor' => '1,50'],
        ], $pagamentos['linhas']);
        $this->assertStringContainsString('inclui peças', $pagamentos['avisos'][0]);
        $lw->assertSee('CREDIARIO')
            ->assertSee('OS nº '.$os->numero)
            ->assertSee($lw->instance()->nfseAmbienteBadge()['rotulo']);

        $nota = (new \App\Models\Nfse(['ordem_servico_id' => $os->id]))->setRelation('itens', collect());
        $this->assertSame([
            'PIX - À vista - Venc. 06/10/2026',
            'CREDIARIO - 1/2 - Venc. 05/11/2026',
            'CREDIARIO - 2/2 - Venc. 05/12/2026',
        ], \App\Support\Erp\Nfse\NfsePagamentosOs::linhasImpressao($nota));
    }

    public function test_servico_lancado_na_nota_aceita_servico_prestado(): void
    {
        $this->contexto();
        $lw = Livewire::test(NfsePage::class)
            ->set('nfseModalOpen', true)
            ->set('nfseServicos', [[
                'key' => 'nfse-1',
                'rev' => 0,
                'product_id' => null,
                'codigo' => '47',
                'descricao' => 'RETIFICA CABECOTE',
                'servico_prestado' => '',
                'quantidade' => '1,000',
                'valor' => '2,00',
                'desconto' => '0,00',
                'acrescimo' => '0,00',
                'total' => '2,00',
                'total_decimal' => '2.00',
            ]]);

        $this->assertFalse($lw->instance()->nfseServicoPrestadoSomenteLeitura(0));

        $lw->call('abrirNfseServicoPrestado', 0)
            ->set('nfseServicoPrestadoTexto', 'plaina feita')
            ->call('confirmarNfseServicoPrestado')
            ->assertSet('nfseServicoPrestadoIndex', null);

        $this->assertSame('PLAINA FEITA', $lw->get('nfseServicos')[0]['servico_prestado']);
    }

    public function test_f6_anexa_o_bloco_de_cada_os_uma_vez(): void
    {
        $this->contexto();
        $primeira = $this->os(['total_servicos' => 100, 'laudo' => 'REVISAO OK'], [['S', 'MAO DE OBRA', 1, 100]]);
        $segunda = $this->os(['total_servicos' => 50, 'placa' => 'ABC1D23'], [['S', 'REVISAO', 1, 50]]);

        $lw = Livewire::test(NfsePage::class)->set('nfseModalOpen', true);
        $this->chamar($lw->instance(), 'aplicarImportacaoOsNaNfse', (int) $primeira->id, false);
        $this->chamar($lw->instance(), 'aplicarImportacaoOsNaNfse', (int) $segunda->id, false);
        $this->chamar($lw->instance(), 'aplicarImportacaoOsNaNfse', (int) $primeira->id, true);

        $this->assertSame(
            "OS nº {$primeira->numero}\nLaudo: REVISAO OK\n\nOS nº {$segunda->numero}\nPlaca: ABC1D23",
            $lw->instance()->nfseDiscriminacao,
        );
    }

    public function test_f7_com_nota_cancelada_gera_nova_e_com_nota_aberta_reabre(): void
    {
        $this->contexto();
        $os = $this->os(['total_servicos' => 100], [['S', 'MAO DE OBRA', 1, 100]]);
        $cancelada = $this->nfse($os, Nfse::STATUS_CANCELADA, '55');

        Livewire::withQueryParams(['os' => $os->id])->test(NfsePage::class)
            ->assertSet('nfseModalOpen', true)
            ->assertSet('nfseId', null);

        $aberta = $this->nfse($os, Nfse::STATUS_ABERTA);

        Livewire::withQueryParams(['os' => $os->id])->test(NfsePage::class)
            ->assertSet('nfseId', (int) $aberta->id);

        $this->assertNotSame((int) $cancelada->id, (int) $aberta->id);
    }

    public function test_f6_bloqueia_os_com_nfse_valida_e_libera_cancelada(): void
    {
        $this->contexto();
        $os = $this->os(['total_servicos' => 100], [['S', 'MAO DE OBRA', 1, 100]]);
        $nota = $this->nfse($os, Nfse::STATUS_AUTORIZADA, '321');

        $lw = Livewire::test(NfsePage::class)->set('nfseModalOpen', true);
        $this->assertSame('A OS nº '.$os->numero.' já tem NFS-e nº 321.', $this->chamar($lw->instance(), 'motivoBloqueioImportacaoOs', $os));

        $nota->forceFill(['status' => Nfse::STATUS_CANCELADA])->save();
        $this->assertNull($this->chamar($lw->instance(), 'motivoBloqueioImportacaoOs', $os));
    }

    public function test_f6_bloqueia_os_ligada_por_item_de_outra_nota(): void
    {
        $this->contexto();
        $principal = $this->os(['total_servicos' => 100], [['S', 'MAO DE OBRA', 1, 100]]);
        $segunda = $this->os(['total_servicos' => 50], [['S', 'REVISAO', 1, 50]]);
        $nota = $this->nfse($principal, Nfse::STATUS_ABERTA);
        NfseItem::query()->create([
            'nfse_id' => $nota->id,
            'ordem_servico_id' => $segunda->id,
            'ordem' => 2,
            'codigo' => 'S2',
            'descricao' => 'REVISAO',
            'quantidade' => 1,
            'valor' => 50,
            'total' => 50,
        ]);

        $lw = Livewire::test(NfsePage::class)->set('nfseModalOpen', true);

        $this->assertStringContainsString('já tem NFS-e', (string) $this->chamar($lw->instance(), 'motivoBloqueioImportacaoOs', $segunda));
        $this->assertSame((int) $nota->id, (int) NfseFromOrdemServico::nfseValida((int) $segunda->id)?->id);
    }

    public function test_f6_tomador_vem_da_primeira_os_e_bloqueia_outro_cliente(): void
    {
        $this->contexto();
        $os = $this->os(['total_servicos' => 100], [['S', 'MAO DE OBRA', 1, 100]]);
        $outroCliente = Person::query()->create([
            'codigo' => 'C-2',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'OUTRO CLIENTE',
            'is_cliente' => true,
            'ativo' => true,
        ]);
        $osOutro = $this->os(['total_servicos' => 80, 'cliente_id' => $outroCliente->id], [['S', 'REVISAO', 1, 80]]);

        $lw = Livewire::test(NfsePage::class)->set('nfseModalOpen', true);
        $this->chamar($lw->instance(), 'aplicarImportacaoOsNaNfse', (int) $os->id, false);

        $this->assertSame((int) $this->cliente->id, $lw->instance()->nfseTomadorId);
        $this->assertSame((int) $os->id, $lw->instance()->nfseOsOrigemId);
        $this->assertCount(1, $lw->instance()->nfseServicos);

        $this->chamar($lw->instance(), 'aplicarImportacaoOsNaNfse', (int) $osOutro->id, false);

        $this->assertCount(1, $lw->instance()->nfseServicos);
        $this->assertSame((int) $os->id, $lw->instance()->nfseOsOrigemId);

        $lw->instance()->nfseTomadorId = (int) $outroCliente->id;
        $this->assertStringContainsString('é de outro cliente', (string) $this->chamar($lw->instance(), 'motivoBloqueioOsDaNfse', (int) $outroCliente->id));
    }

    public function test_lista_de_os_mostra_so_nfse_autorizada(): void
    {
        $this->contexto();
        $os = $this->os(['total_servicos' => 100], [['S', 'MAO DE OBRA', 1, 100]]);

        $this->nfse($os, Nfse::STATUS_ABERTA);
        $this->assertSame('—', $os->fresh()->nfseNumeroLista());

        $this->nfse($os, Nfse::STATUS_CANCELADA, '10');
        $this->assertSame('—', $os->fresh()->nfseNumeroLista());

        $this->nfse($os, Nfse::STATUS_AUTORIZADA, '11');
        $this->assertSame('11', $os->fresh()->nfseNumeroLista());

        $segunda = $this->os(['total_servicos' => 50], [['S', 'REVISAO', 1, 50]]);
        $nota = Nfse::query()->where('numero_nfse', '11')->sole();
        NfseItem::query()->create([
            'nfse_id' => $nota->id,
            'ordem_servico_id' => $segunda->id,
            'ordem' => 2,
            'codigo' => 'S2',
            'descricao' => 'REVISAO',
            'quantidade' => 1,
            'valor' => 50,
            'total' => 50,
        ]);

        $this->assertSame('11', $segunda->fresh()->nfseNumeroLista());
    }

    private function chamar(object $objeto, string $metodo, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($objeto, $metodo);

        return $ref->invoke($objeto, ...$args);
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  list<array{0: string, 1: string, 2: float|int, 3: float|int, 4?: float|int, 5?: float|int}>  $itens
     */
    private function os(array $atributos, array $itens): OrdemServico
    {
        $this->seq++;
        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $this->empresa->id,
            'numero' => 900 + $this->seq,
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'cliente_id' => $this->cliente->id,
            ...$atributos,
        ])->save();

        foreach ($itens as $item) {
            [$tipo, $nome, $qtd, $preco] = $item;
            $desconto = $item[4] ?? 0;
            $acrescimo = $item[5] ?? 0;

            $produto = Product::query()->forceCreate([
                'codigo' => 'P'.$this->seq.'-'.mb_substr($nome, 0, 4),
                'descricao' => $nome,
                'is_servico' => $tipo === 'S',
                'c_trib_nac' => '140101',
                'c_nbs' => '120013110',
            ]);

            OrdemServicoItem::query()->forceCreate([
                'ordem_servico_id' => $os->id,
                'empresa_id' => $this->empresa->id,
                'product_id' => $produto->id,
                'tipo' => $tipo,
                'discriminacao' => $nome,
                'qtd' => $qtd,
                'preco' => $preco,
                'desconto' => $desconto ?? 0,
                'acrescimo' => $acrescimo ?? 0,
                'total' => round(round($qtd * $preco, 2) + ($acrescimo ?? 0) - ($desconto ?? 0), 2),
            ]);
        }

        return $os->fresh(['itens.product', 'cliente']);
    }

    private function nfse(OrdemServico $os, string $status, ?string $numero = null): Nfse
    {
        $nfse = new Nfse();
        $nfse->forceFill([
            'empresa_id' => $this->empresa->id,
            'ordem_servico_id' => $os->id,
            'status' => $status,
            'serie_dps' => '1',
            'numero_dps' => 100 + (++$this->seq),
            'numero_nfse' => $numero,
            'tomador_id' => $this->cliente->id,
            'tomador_nome' => 'CLIENTE OS',
            'competencia' => '2026-10-01',
            'data_emissao' => '2026-10-06',
            'valor_servicos' => 100,
            'desconto' => 0,
            'iss' => 0,
            'total' => 100,
        ])->save();

        return $nfse;
    }

    private function contexto(): void
    {
        $this->empresa = Empresa::query()->create([
            'codigo' => '1',
            'nome' => 'EMPRESA NFSE',
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
            'cidade_codigo' => '4203204',
            'cidade' => 'CAMBORIU',
            'uf' => 'SC',
        ]);

        $user = User::factory()->create([
            'empresa_id' => $this->empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $this->empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $this->cliente = Person::query()->create([
            'codigo' => 'C-1',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS',
            'is_cliente' => true,
            'ativo' => true,
        ]);
    }
}
