<?php

namespace Tests\Feature;

use App\Filament\Resources\ExtratoLancamentoResource\Pages\ListExtratoLancamentos;
use App\Models\CaixaConta;
use App\Models\CaixaLancamento;
use App\Models\Empresa;
use App\Models\ExtratoImportacao;
use App\Models\ExtratoLancamento;
use App\Models\User;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Financeiro\Conciliacao\OfxImportService;
use App\Support\Erp\Financeiro\Conciliacao\OfxParser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ConciliacaoOfxImportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createMinimalSchema();
    }

    public function test_ofx_valido_importa_pendentes(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $caixaAntes = CaixaLancamento::query()->count();

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'ok.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', 'FIT-1', 'PIX A'],
                ['DEBIT', '20261111', '-20.00', 'FIT-2', 'TARIFA'],
            ]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame(2, $result->lidos);
        $this->assertSame(2, $result->importados);
        $this->assertSame(0, $result->jaExistentes);
        $this->assertSame('50.00', $result->totalEntradas);
        $this->assertSame('20.00', $result->totalSaidas);

        $rows = ExtratoLancamento::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows->every(fn ($r) => $r->situacao === ExtratoLancamento::SITUACAO_PENDENTE));
        $this->assertTrue($rows->every(fn ($r) => $r->origem_tipo === 'ofx'));
        $this->assertSame($caixaAntes, CaixaLancamento::query()->count());
        $this->assertNull($rows[0]->caixa_lancamento_id);
        $this->assertNull($rows[0]->conciliado_em);
    }

    public function test_ofx_com_hora_grava_datetime_sem_mudar_fingerprint_do_dia(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'hora.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110143000[-3:BRT]', '15.00', 'FIT-HORA', 'PIX HORA'],
            ]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $result->importados);
        $row = ExtratoLancamento::query()->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-11-10 14:30:00', $row->data_movimento?->format('Y-m-d H:i:s'));
        $this->assertSame(
            ExtratoLancamento::buildFingerprint('FIT-HORA', '2026-11-10', 'credito', '15.00', 'PIX HORA', null, 1, empresaId: (int) $empresa->id, caixaContaId: (int) $banco->id),
            $row->fingerprint
        );
        $this->assertSame('10/11/2026 14:30', ExtratoLancamento::formatarDataMovimento($row->data_movimento));
    }

    public function test_arquivo_invalido(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $this->expectException(InvalidArgumentException::class);
        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'x.ofx',
            'conteudo' => 'lixo',
            'user_id' => $admin->id,
        ]);
    }

    public function test_arquivo_vazio(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $this->expectException(InvalidArgumentException::class);
        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'vazio.ofx',
            'conteudo' => '',
            'user_id' => $admin->id,
        ]);
    }

    public function test_reimportacao_mesmo_arquivo_idempotente(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $ofx = $this->ofxSample([
            ['CREDIT', '20261110', '50.00', 'FIT-R1', 'PIX'],
        ]);

        $svc = app(OfxImportService::class);
        $a = $svc->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'a.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);
        $b = $svc->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'a-copy.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $a->importados);
        $this->assertTrue($b->reimportacaoIdempotente);
        $this->assertSame(1, ExtratoLancamento::query()->count());
        $this->assertSame(1, ExtratoImportacao::query()->count());
    }

    public function test_extratos_sobrepostos_com_fitid_repetido(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $svc = app(OfxImportService::class);

        $svc->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'p1.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', 'FIT-SAME', 'PIX'],
                ['CREDIT', '20261112', '30.00', 'FIT-NEW', 'PIX B'],
            ]),
            'user_id' => $admin->id,
        ]);

        $r2 = $svc->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'p2.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', 'FIT-SAME', 'PIX'],
                ['CREDIT', '20261113', '15.00', 'FIT-EXTRA', 'PIX C'],
            ]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $r2->importados);
        $this->assertSame(1, $r2->jaExistentes);
        $this->assertSame(3, ExtratoLancamento::query()->count());
    }

    public function test_dois_pix_iguais_mesmo_dia_sem_fitid_viram_dois(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'dup.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
            ]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame(2, $result->importados);
        $this->assertSame(2, ExtratoLancamento::query()->count());
        $fps = ExtratoLancamento::query()->pluck('fingerprint')->all();
        $this->assertNotSame($fps[0], $fps[1]);
    }

    public function test_fitid_ausente_importa_sem_descarte(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'nofit.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '7.00', null, 'SEM FITID'],
            ]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $result->importados);
        $this->assertSame(1, ExtratoLancamento::query()->count());
        $this->assertSame('pendente', ExtratoLancamento::query()->first()->situacao);
    }

    public function test_fitid_ausente_identidade_fraca_marca_ambiguo(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        // Sem NAME/MEMO/FITID → parser marca ambiguo.
        $ofx = "OFXHEADER:100\nDATA:OFXSGML\nCHARSET:UTF-8\n\n<OFX>\n<BANKACCTFROM>\n"
            ."<BANKID>085\n<ACCTID>12345\n</BANKACCTFROM>\n<BANKTRANLIST>\n"
            ."<STMTTRN>\n<TRNTYPE>CREDIT\n<DTPOSTED>20261110\n<TRNAMT>3.00\n</STMTTRN>\n"
            ."</BANKTRANLIST>\n</OFX>\n";

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'fraco.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $result->importados);
        $this->assertGreaterThanOrEqual(1, $result->ambiguos);
        $this->assertTrue((bool) ExtratoLancamento::query()->first()->ambiguo);
    }

    public function test_sobreposicao_sem_fitid_nao_duplica_e_sinaliza(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $svc = app(OfxImportService::class);

        $svc->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'a.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
            ]),
            'user_id' => $admin->id,
        ]);

        $r2 = $svc->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'b.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
                ['CREDIT', '20261110', '50.00', null, 'PIX RECEBIDO'],
            ]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame(2, $r2->jaExistentes);
        $this->assertSame(1, $r2->importados);
        $this->assertSame(3, ExtratoLancamento::query()->count());
        $this->assertTrue(collect($r2->warnings)->contains(
            fn ($w) => str_contains((string) $w, 'sem FITID')
        ));
    }

    public function test_conta_incompativel_bloqueia_importacao(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Importação bloqueada');

        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'outra.ofx',
            'conteudo' => $this->ofxSample(
                [['CREDIT', '20261110', '1.00', 'FIT-X', 'X']],
                bankId: '001',
                accountId: '99999',
            ),
            'user_id' => $admin->id,
        ]);
    }

    public function test_filtro_periodo_padrao_esconde_extrato_de_outro_mes(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'out2026.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261015', '10.00', 'FIT-OUT', 'PIX OUT'],
            ]),
            'user_id' => $admin->id,
        ]);

        // Sem DTSTART/DTEND no OFX sintético, o período vem do min/max das linhas.
        $this->assertSame('2026-10-15', $result->periodoDe);
        $this->assertSame('2026-10-15', $result->periodoAte);
        $this->assertSame(1, ExtratoLancamento::query()->count());

        // Filtro do mês atual (novembro) não enxerga outubro.
        $noMes = app(\App\Support\Erp\Financeiro\Conciliacao\ExtratoLancamentoListQuery::class)->query([
            'empresa_id' => (int) $empresa->id,
            'caixa_conta_id' => (int) $banco->id,
            'periodo_de' => '2026-11-01',
            'periodo_ate' => '2026-11-13',
            'situacao' => 'todos',
        ])->count();
        $this->assertSame(0, $noMes);

        // Com o período do extrato, a listagem encontra.
        $noPeriodo = app(\App\Support\Erp\Financeiro\Conciliacao\ExtratoLancamentoListQuery::class)->query([
            'empresa_id' => (int) $empresa->id,
            'caixa_conta_id' => (int) $banco->id,
            'periodo_de' => $result->periodoDe,
            'periodo_ate' => $result->periodoAte,
            'situacao' => 'pendente',
        ])->count();
        $this->assertSame(1, $noPeriodo);
    }

    public function test_apos_importar_livewire_ajusta_periodo_e_lista(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $ofx = $this->ofxSample([
            ['CREDIT', '20261015', '10.00', 'FIT-LW2', 'PIX OUT LW'],
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('out.ofx', $ofx);

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-13')
            ->set('situacao', 'todos')
            ->assertDontSee('PIX OUT LW')
            ->set('ofxUpload', $file)
            ->assertSet('periodoDe', '2026-10-15')
            ->assertSet('periodoAte', '2026-10-15')
            ->assertSee('PIX OUT LW')
            ->assertSee('Período do extrato importado');
    }

    public function test_botao_periodo_do_extrato_ajusta_filtro_e_lista(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'out2026.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261001', '10.00', 'FIT-MIN', 'PIX MIN'],
                ['DEBIT', '20261030', '-5.00', 'FIT-MAX', 'PIX MAX'],
            ]),
            'user_id' => $admin->id,
        ]);

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('periodoDe', '2026-11-01')
            ->set('periodoAte', '2026-11-13')
            ->set('situacao', 'todos')
            ->assertDontSee('PIX MIN')
            ->call('ajustarFiltroParaTodoExtratoDaConta')
            ->assertSet('periodoDe', '2026-10-01')
            ->assertSet('periodoAte', '2026-10-30')
            ->assertSee('PIX MIN')
            ->assertSee('PIX MAX');
    }

    public function test_ailos_acctid_composto_importa_na_conta_correta(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $banco->forceFill([
            'banco_codigo' => '085',
            'agencia' => '0109',
            'agencia_dv' => '0',
            'conta_numero' => '48346',
            'conta_dv' => '0',
        ])->save();

        $ofx = $this->ofxSample(
            [['CREDIT', '20261110', '10.00', 'AILOS-1', 'PIX AILOS']],
            bankId: '085',
            accountId: '010900483460',
            branchId: '0109',
        );

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'ailos.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $result->importados);
        $this->assertSame(1, ExtratoLancamento::query()->count());
    }

    public function test_ofx_sem_dados_bancarios_informa_limitacao_e_importa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $ofx = "OFXHEADER:100\nDATA:OFXSGML\nCHARSET:UTF-8\n\n<OFX>\n<BANKTRANLIST>\n"
            ."<STMTTRN>\n<TRNTYPE>CREDIT\n<DTPOSTED>20261110\n<TRNAMT>5.00\n"
            ."<FITID>NOACCT\n<NAME>SEM CONTA\n</STMTTRN>\n</BANKTRANLIST>\n</OFX>\n";

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'semconta.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);

        $this->assertSame(1, $result->importados);
        $this->assertFalse($result->alertaContaIncompativel);
        $this->assertTrue(collect($result->warnings)->contains(
            fn ($w) => str_contains((string) $w, 'não informou banco/conta')
        ));
    }

    public function test_ofx_com_entity_xxe_e_rejeitado(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('DOCTYPE/ENTITY');

        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'xxe.ofx',
            'conteudo' => "<!DOCTYPE foo [<!ENTITY xxe SYSTEM \"file:///etc/passwd\">]>\n<OFX>&xxe;<STMTTRN>"
                ."<TRNTYPE>CREDIT\n<DTPOSTED>20261110\n<TRNAMT>1\n</STMTTRN></OFX>",
            'user_id' => $admin->id,
        ]);
    }

    public function test_outra_empresa_nao_enxerga_conta(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $outra = Empresa::query()->create([
            'codigo' => 'OE',
            'nome' => 'OUTRA',
            'fantasia' => 'O',
            'ativo' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        app(OfxImportService::class)->import([
            'empresa_id' => $outra->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'x.ofx',
            'conteudo' => $this->ofxSample([['CREDIT', '20261110', '1.00', 'F1', 'X']]),
            'user_id' => $admin->id,
        ]);
    }

    public function test_importacao_5000_lancamentos_performance(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        $lines = [];
        for ($i = 1; $i <= 5000; $i++) {
            $day = str_pad((string) (($i % 28) + 1), 2, '0', STR_PAD_LEFT);
            $lines[] = ['CREDIT', '202611'.$day, '1.00', 'F5000-'.$i, 'LINHA '.$i];
        }
        $ofx = $this->ofxSample($lines);

        $mem0 = memory_get_usage(true);
        $t0 = hrtime(true);
        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'big.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);
        $ms = (hrtime(true) - $t0) / 1e6;
        $peak = memory_get_peak_usage(true);

        $this->assertSame(5000, $result->importados);
        $this->assertSame(5000, ExtratoLancamento::query()->count());
        $this->assertLessThan(120000, $ms, 'Importação 5k deve ficar < 120s no DEV');
        $this->assertLessThan(256 * 1048576, $peak, 'Pico de memória < 256 MB');

        fwrite(STDOUT, sprintf(
            "\n[PERF 5k] ms=%.1f peak_mb=%.1f mem0_mb=%.1f\n",
            $ms,
            $peak / 1048576,
            $mem0 / 1048576
        ));
    }

    public function test_retomada_apos_falha_parcial_nao_duplica(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $ofx = $this->ofxSample([
            ['CREDIT', '20261110', '10.00', 'FIT-P1', 'A'],
            ['CREDIT', '20261111', '20.00', 'FIT-P2', 'B'],
        ]);

        // Simula lote parcial: cria importação processando com 1 linha já gravada.
        $sha = hash('sha256', $ofx);
        $imp = ExtratoImportacao::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'parcial.ofx',
            'arquivo_sha256' => $sha,
            'formato' => 'ofx',
            'status' => ExtratoImportacao::STATUS_ERRO,
            'total_linhas' => 2,
            'total_importados' => 1,
            'ultimo_lote_ok' => 1,
        ]);

        ExtratoLancamento::query()->create([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'extrato_importacao_id' => $imp->id,
            'data_movimento' => '2026-11-10',
            'tipo' => 'credito',
            'valor' => 10,
            'historico' => 'A',
            'fitid' => 'FIT-P1',
            'fingerprint' => ExtratoLancamento::buildFingerprint(
                'FIT-P1', '2026-11-10', 'credito', '10.00', 'A', null, 1, null, null, null,
                (int) $empresa->id, (int) $banco->id
            ),
            'situacao' => 'pendente',
            'origem_tipo' => 'ofx',
            'origem_id' => $imp->id,
        ]);

        $result = app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'parcial.ofx',
            'conteudo' => $ofx,
            'user_id' => $admin->id,
        ]);

        $this->assertSame(2, ExtratoLancamento::query()->count());
        $this->assertSame(ExtratoImportacao::STATUS_CONCLUIDA, ExtratoImportacao::query()->find($imp->id)->status);
        $this->assertGreaterThanOrEqual(1, $result->importados + $result->jaExistentes);
    }

    public function test_livewire_filtros_e_permissao(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();

        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'lw.ofx',
            'conteudo' => $this->ofxSample([
                ['CREDIT', '20261110', '50.00', 'FIT-LW', 'PIX LW'],
            ]),
            'user_id' => $admin->id,
        ]);

        Livewire::test(ListExtratoLancamentos::class)
            ->set('caixaContaId', (string) $banco->id)
            ->set('situacao', 'pendente')
            ->call('consultar')
            ->assertSee('PIX LW')
            ->assertSee('Importar OFX');

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => false,
            'ativo' => true,
        ]);
        session(['erp_empresa_id' => $empresa->id]);
        ErpAccess::storeInSession($user, ['caixa.access']);
        $this->actingAs($user);

        Livewire::test(ListExtratoLancamentos::class)->assertForbidden();
    }

    public function test_isolamento_financeiro_sem_tocar_caixa(): void
    {
        [$empresa, $admin, $banco] = $this->seedEmpresaAdminEContaBanco();
        $antes = CaixaLancamento::query()->count();

        app(OfxImportService::class)->import([
            'empresa_id' => $empresa->id,
            'caixa_conta_id' => $banco->id,
            'arquivo_nome' => 'iso.ofx',
            'conteudo' => $this->ofxSample([['CREDIT', '20261110', '99.00', 'FIT-ISO', 'ISO']]),
            'user_id' => $admin->id,
        ]);

        $this->assertSame($antes, CaixaLancamento::query()->count());
        $this->assertSame(0, ExtratoLancamento::query()->whereNotNull('caixa_lancamento_id')->count());
        $this->assertSame(0, ExtratoLancamento::query()->where('situacao', 'conciliado')->count());
    }

    public function test_parser_unitario_ainda_ok(): void
    {
        $parsed = (new OfxParser)->parse($this->ofxSample([
            ['CREDIT', '20261101', '1.00', 'P', 'X'],
        ]), 'p.ofx');
        $this->assertCount(1, $parsed->transactions);
    }

    /**
     * @param  list<array{0:string,1:string,2:string,3:?string,4:string}>  $rows
     */
    private function ofxSample(array $rows, string $bankId = '085', string $accountId = '12345', ?string $branchId = '0001'): string
    {
        $trx = '';
        foreach ($rows as $r) {
            [$type, $date, $amt, $fitid, $name] = $r;
            $fit = $fitid !== null && $fitid !== '' ? "<FITID>{$fitid}\n" : '';
            $trx .= "<STMTTRN>\n<TRNTYPE>{$type}\n<DTPOSTED>{$date}\n<TRNAMT>{$amt}\n{$fit}<NAME>{$name}\n</STMTTRN>\n";
        }

        $branch = $branchId !== null && $branchId !== '' ? "<BRANCHID>{$branchId}\n" : '';

        return "OFXHEADER:100\nDATA:OFXSGML\nVERSION:102\nCHARSET:UTF-8\n\n"
            ."<OFX>\n<BANKACCTFROM>\n<BANKID>{$bankId}\n{$branch}<ACCTID>{$accountId}\n</BANKACCTFROM>\n"
            ."<BANKTRANLIST>\n{$trx}</BANKTRANLIST>\n</OFX>\n";
    }

    private function createMinimalSchema(): void
    {
        Schema::dropIfExists('extrato_lancamentos');
        Schema::dropIfExists('extrato_importacoes');
        Schema::dropIfExists('caixa_lancamentos');
        Schema::dropIfExists('caixa_contas');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresas');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');

        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->nullable();
            $table->string('nome', 120);
            $table->string('fantasia', 120)->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('name');
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->boolean('ativo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('caixa_contas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedInteger('codigo')->nullable();
            $table->string('nome', 120);
            $table->string('tipo', 20)->default('BANCO');
            $table->string('situacao', 20)->default('aberto');
            $table->boolean('ativo')->default(true);
            $table->boolean('sistema')->default(false);
            $table->string('banco_codigo', 10)->nullable();
            $table->string('agencia', 20)->nullable();
            $table->string('agencia_dv', 4)->nullable();
            $table->string('conta_numero', 30)->nullable();
            $table->string('conta_dv', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('caixa_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('caixa_conta_id')->nullable();
            $table->decimal('entrada', 14, 2)->default(0);
            $table->decimal('saida', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });
        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('extrato_importacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->string('arquivo_nome', 255);
            $table->char('arquivo_sha256', 64);
            $table->string('formato', 16)->default('ofx');
            $table->string('banco_id_arquivo', 20)->nullable();
            $table->string('agencia_arquivo', 20)->nullable();
            $table->string('conta_arquivo', 40)->nullable();
            $table->boolean('alerta_conta_incompativel')->default(false);
            $table->string('status', 20)->default('pendente')->index();
            $table->date('periodo_inicio')->nullable();
            $table->date('periodo_fim')->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('total_importados')->default(0);
            $table->unsignedInteger('total_duplicados')->default(0);
            $table->unsignedInteger('total_erros')->default(0);
            $table->unsignedInteger('total_ambiguos')->default(0);
            $table->unsignedInteger('ultimo_lote_ok')->default(0);
            $table->decimal('total_creditos', 14, 2)->default(0);
            $table->decimal('total_debitos', 14, 2)->default(0);
            $table->text('mensagem_erro')->nullable();
            $table->unsignedBigInteger('imported_by_user_id')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'caixa_conta_id', 'arquivo_sha256'], 'extrato_imp_emp_conta_sha_uq');
        });

        Schema::create('extrato_lancamentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('caixa_conta_id')->index();
            $table->unsignedBigInteger('extrato_importacao_id')->index();
            $table->dateTime('data_movimento')->index();
            $table->dateTime('data_posted')->nullable();
            $table->string('tipo', 16)->default('outro');
            $table->decimal('valor', 14, 2);
            $table->string('historico', 500)->nullable();
            $table->string('memo', 500)->nullable();
            $table->string('payee', 255)->nullable();
            $table->string('check_num', 40)->nullable();
            $table->string('ref_num', 80)->nullable();
            $table->string('fitid', 120)->nullable();
            $table->char('fingerprint', 64);
            $table->string('situacao', 20)->default('pendente')->index();
            $table->boolean('ambiguo')->default(false);
            $table->timestamp('conciliado_em')->nullable();
            $table->unsignedBigInteger('conciliado_por_user_id')->nullable();
            $table->string('origem_tipo', 40)->nullable();
            $table->unsignedBigInteger('origem_id')->nullable();
            $table->unsignedBigInteger('caixa_lancamento_id')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'caixa_conta_id', 'fingerprint'], 'extrato_lanc_emp_conta_fp_uq');
        });
    }

    /**
     * @return array{0: Empresa, 1: User, 2: CaixaConta}
     */
    private function seedEmpresaAdminEContaBanco(): array
    {
        $empresa = Empresa::query()->create([
            'codigo' => 'CBX',
            'nome' => 'EMPRESA OFX',
            'fantasia' => 'OFX',
            'ativo' => true,
        ]);

        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'is_admin' => true,
            'ativo' => true,
        ]);

        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($admin);

        $banco = CaixaConta::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 9201,
            'nome' => 'BANCO OFX',
            'tipo' => CaixaConta::TIPO_BANCO,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => true,
            'sistema' => false,
            'banco_codigo' => '085',
            'agencia' => '0001',
            'conta_numero' => '12345',
        ]);

        return [$empresa, $admin, $banco];
    }
}
