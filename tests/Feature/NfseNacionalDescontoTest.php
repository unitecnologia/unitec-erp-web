<?php

namespace Tests\Feature;

use App\Filament\Pages\NfsePage;
use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfse\NfseDpsAssinador;
use App\Support\Erp\Nfse\NfseDpsBuilder;
use App\Support\Erp\Nfse\NfseDpsNaoMontada;
use App\Support\Erp\Nfse\NfseDpsXmlGerador;
use App\Support\Erp\Nfse\NfseDpsXmlValidador;
use DOMDocument;
use DOMXPath;
use Livewire\Livewire;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;

class NfseNacionalDescontoTest extends TestCase
{
    use MigratesSqliteMemory;

    private const CNPJ = '54644503000129';

    public function test_os_6_vai_com_vserv_bruto_e_desconto_incondicionado_validos_no_xsd(): void
    {
        $nfse = $this->nfseDaOs6();

        $this->assertSame('3800.00', (string) $nfse->valor_servicos);
        $this->assertSame('254.00', (string) $nfse->desconto);
        $this->assertSame('3546.00', (string) $nfse->total);

        $xml = (new NfseDpsXmlGerador)->gerar(app(NfseDpsBuilder::class)->montar($nfse));
        $validacao = (new NfseDpsXmlValidador)->validar($xml);
        $this->assertTrue($validacao['valido'], json_encode($validacao['erros'], JSON_UNESCAPED_UNICODE));

        $xpath = $this->xpath($xml);
        $this->assertSame(['vServPrest', 'vDescCondIncond', 'trib'], $this->filhos($xpath, '//n:valores'));
        $this->assertSame('3800.00', $xpath->evaluate('string(//n:valores/n:vServPrest/n:vServ)'));
        $this->assertSame('254.00', $xpath->evaluate('string(//n:valores/n:vDescCondIncond/n:vDescIncond)'));
        $this->assertSame(0.0, $xpath->evaluate('count(//n:vDescCond)'));
        $this->assertSame(1.0, $xpath->evaluate('count(//n:serv/n:cServ)'));
        $this->assertStringStartsWith('MAO DE OBRA | PROGRAMACAO | OS nº', $xpath->evaluate('string(//n:cServ/n:xDescServ)'));
        $this->assertSame(
            '3546.00',
            bcsub($xpath->evaluate('string(//n:vServ)'), $xpath->evaluate('string(//n:vDescIncond)'), 2),
        );

        $assinado = (new NfseDpsAssinador)->assinar($xml, $this->certificado());
        $validacaoAssinado = (new NfseDpsXmlValidador)->validar($assinado);
        $this->assertTrue($validacaoAssinado['valido'], json_encode($validacaoAssinado['erros'], JSON_UNESCAPED_UNICODE));
        $this->assertStringContainsString('<vDescIncond>254.00</vDescIncond>', $assinado);
    }

    public function test_nota_sem_desconto_nao_gera_grupo_de_desconto(): void
    {
        $nfse = $this->nfseDaOs6();
        $nfse->forceFill(['valor_servicos' => '3546.00', 'desconto' => '0.00'])->save();

        $xml = (new NfseDpsXmlGerador)->gerar(app(NfseDpsBuilder::class)->montar($nfse->fresh(['empresa', 'itens'])));

        $this->assertStringNotContainsString('vDescCondIncond', $xml);
        $this->assertTrue((new NfseDpsXmlValidador)->validar($xml)['valido']);
    }

    public function test_valores_incoerentes_nao_montam_a_dps(): void
    {
        $nfse = $this->nfseDaOs6();
        $nfse->forceFill(['desconto' => '508.00'])->save();

        $this->expectException(NfseDpsNaoMontada::class);
        app(NfseDpsBuilder::class)->montar($nfse->fresh(['empresa', 'itens']));
    }

    public function test_servicos_com_codigos_diferentes_nao_viram_um_servico(): void
    {
        $xml = (new NfseDpsXmlGerador)->gerar([
            'dps' => [],
            'prestador' => [],
            'tomador' => [],
            'municipio_prestacao' => ['codigo' => '4203204'],
            'servicos' => [
                ['descricao' => 'A', 'cTribNac' => '140101', 'cNBS' => '120013110'],
                ['descricao' => 'B', 'cTribNac' => '140201', 'cNBS' => '120013110'],
            ],
            'valores' => [],
        ]);

        $this->assertStringNotContainsString('cServ', $xml);
    }

    /**
     * Mesmos dados da OS nº 6 do banco de DEV, gravada pelo fluxo F7 → F2.
     */
    private function nfseDaOs6(): Nfse
    {
        $empresa = Empresa::query()->create([
            'codigo' => '1',
            'nome' => 'EMPRESA NACIONAL',
            'tipo_atividade' => Empresa::TIPO_ATIVIDADE_PRESTADOR_SERVICOS,
        ]);
        $empresa->forceFill([
            'cnpj' => '54.644.503/0001-29',
            'razao_social' => 'EMPRESA NACIONAL LTDA',
            'im' => '230780',
            'cidade' => 'CAMBORIU',
            'cidade_codigo' => '4203204',
            'uf' => 'SC',
            'regime_tributario' => 'normal',
            'nfse_reg_esp_trib' => '0',
            'nfse_ambiente' => 'producao_restrita',
        ])->save();

        $user = User::factory()->create(['empresa_id' => $empresa->id, 'is_admin' => true, 'ativo' => true]);
        session(['erp_empresa_id' => $empresa->id]);
        $this->actingAs($user);
        ErpContext::clearMemo();

        $cliente = Person::query()->create([
            'codigo' => 'C-6',
            'pessoa_tipo' => Person::PESSOA_FISICA,
            'nome_razao' => 'CLIENTE OS 6',
            'cpf_cnpj' => '123.456.789-09',
            'is_cliente' => true,
            'ativo' => true,
        ]);

        $os = new OrdemServico();
        $os->forceFill([
            'empresa_id' => $empresa->id,
            'numero' => 6,
            'situacao' => OrdemServico::SITUACAO_FINALIZADA,
            'cliente_id' => $cliente->id,
            'subtotal_servicos' => 3800,
            'vl_desc_servicos' => 254,
            'total_servicos' => 3546,
            'subtotal_pecas' => 9154,
            'total_produtos' => 8954,
            'total_geral' => 12500,
        ])->save();

        foreach ([
            ['S', 'MAO DE OBRA', 1, 3500],
            ['S', 'PROGRAMACAO', 1, 300],
            ['P', 'OLEO CAMBIO MECANICO', 3, 98],
            ['P', 'KIT EMBREAGEM COMPLETO', 1, 8860],
        ] as $indice => [$tipo, $nome, $qtd, $preco]) {
            $produto = Product::query()->forceCreate([
                'codigo' => 'P6-'.$indice,
                'descricao' => $nome,
                'is_servico' => $tipo === 'S',
                'c_trib_nac' => '140101',
                'c_nbs' => '120013110',
            ]);

            OrdemServicoItem::query()->forceCreate([
                'ordem_servico_id' => $os->id,
                'empresa_id' => $empresa->id,
                'product_id' => $produto->id,
                'tipo' => $tipo,
                'discriminacao' => $nome,
                'qtd' => $qtd,
                'preco' => $preco,
                'desconto' => 0,
                'acrescimo' => 0,
                'total' => $qtd * $preco,
            ]);
        }

        Livewire::withQueryParams(['os' => $os->id])
            ->test(NfsePage::class)
            ->assertSet('nfseModalOpen', true)
            ->call('gravarNfse')
            ->assertHasNoErrors();

        return Nfse::query()->where('ordem_servico_id', $os->id)->sole()->load(['empresa', 'itens']);
    }

    private function xpath(string $xml): DOMXPath
    {
        $doc = new DOMDocument;
        $doc->loadXML($xml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('n', NfseDpsXmlGerador::NS);

        return $xpath;
    }

    /**
     * @return list<string>
     */
    private function filhos(DOMXPath $xpath, string $caminho): array
    {
        $nomes = [];

        foreach ($xpath->query($caminho.'/*') ?: [] as $no) {
            $nomes[] = $no->localName;
        }

        return $nomes;
    }

    private function certificado(): Certificate
    {
        $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
        $cnf = base_path('tools/php/extras/ssl/openssl.cnf');

        if (is_file($cnf)) {
            $config['config'] = $cnf;
        }

        $chave = openssl_pkey_new($config);
        $csr = openssl_csr_new(['commonName' => 'EMPRESA TESTE:'.self::CNPJ], $chave, $config);
        $x509 = openssl_csr_sign($csr, null, $chave, 1, $config);
        openssl_x509_export($x509, $pem);
        openssl_pkey_export($chave, $chavePem, null, $config);

        return new Certificate($chavePem, $pem, self::CNPJ);
    }
}
