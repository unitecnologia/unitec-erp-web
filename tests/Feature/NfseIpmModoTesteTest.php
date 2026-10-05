<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Support\Erp\Nfse\Ipm\NfseIpmAssinador;
use App\Support\Erp\Nfse\Ipm\NfseIpmCliente;
use App\Support\Erp\Nfse\Ipm\NfseIpmEmitirService;
use App\Support\Erp\Nfse\Ipm\NfseIpmResposta;
use App\Support\Erp\Nfse\Ipm\NfseIpmXmlGerador;
use App\Support\Erp\Nfse\Ipm\NfseIpmXmlValidador;
use App\Support\Erp\Nfse\NfseNaoTransmitida;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\MigratesSqliteMemory;
use Tests\TestCase;
use Unitec\FiscalEngine\Certificate\Certificate;

class NfseIpmModoTesteTest extends TestCase
{
    use MigratesSqliteMemory;

    private const RETORNO_TESTE = '<GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"><ListaNfse><CompNfse><Nfse><InfNfse><Numero>77</Numero><CodigoVerificacao>TESTE</CodigoVerificacao></InfNfse></Nfse></CompNfse><ListaMensagemAlertaRetorno><MensagemRetorno><Codigo>L1079</Codigo><Mensagem>A nota foi enviada com o modo teste ativado.</Mensagem></MensagemRetorno></ListaMensagemAlertaRetorno></ListaNfse></GerarNfseResposta>';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_envio_teste_aceito_mantem_a_nota_aberta_e_sem_numero_de_nfse(): void
    {
        $nota = $this->nota();
        $cliente = $this->cliente(self::RETORNO_TESTE);

        $resultado = $this->servico($cliente)->transmitir($nota);
        $nota->refresh();

        $this->assertTrue($resultado->modoTeste);
        $this->assertFalse($resultado->autorizada);
        $this->assertSame([], $resultado->erros);
        $this->assertSame(['L1079 A nota foi enviada com o modo teste ativado.'], $resultado->alertas);
        $this->assertSame(Nfse::STATUS_ABERTA, $nota->status);
        $this->assertNull($nota->numero_nfse);
        $this->assertNull($nota->chave);
        $this->assertNull($nota->xml_nfse);
        $this->assertNull($nota->xml_dps);
        $this->assertNull($nota->transmitindo_em);
        $this->assertSame(5, $nota->numero_dps);
        $this->assertTrue($cliente->envioTeste);
        $this->assertStringContainsString('<EnvioTeste>1</EnvioTeste>', $cliente->xml);
        $this->assertSame([], app(NfseIpmXmlValidador::class)->erros($cliente->xml));
        $this->assertCount(2, Storage::disk('local')->allFiles('nfse/ipm/teste/'.$nota->empresa_id));

        $segunda = $this->servico($this->cliente(self::RETORNO_TESTE))->transmitir($nota->fresh());
        $this->assertTrue($segunda->modoTeste);
    }

    public function test_envio_teste_recusado_devolve_os_erros_sem_gravar_retorno_na_nota(): void
    {
        $nota = $this->nota();
        $retorno = '<GerarNfseResposta xmlns="http://www.abrasf.org.br/nfse.xsd"><ListaMensagemRetorno><MensagemRetorno><Codigo>L1027</Codigo><Mensagem>Inscrição municipal obrigatória.</Mensagem></MensagemRetorno></ListaMensagemRetorno></GerarNfseResposta>';

        $resultado = $this->servico($this->cliente($retorno))->transmitir($nota);
        $nota->refresh();

        $this->assertTrue($resultado->modoTeste);
        $this->assertSame('L1027', $resultado->erros[0]['codigo']);
        $this->assertSame(Nfse::STATUS_ABERTA, $nota->status);
        $this->assertNull($nota->xml_nfse);
    }

    public function test_producao_com_retorno_de_modo_teste_nao_autoriza(): void
    {
        $nota = $this->nota('producao');
        $cliente = $this->cliente(self::RETORNO_TESTE);

        $resultado = $this->servico($cliente)->transmitir($nota);
        $nota->refresh();

        $this->assertFalse($cliente->envioTeste);
        $this->assertStringNotContainsString('EnvioTeste', $cliente->xml);
        $this->assertFalse($resultado->autorizada);
        $this->assertTrue($resultado->modoTeste);
        $this->assertSame(Nfse::STATUS_ABERTA, $nota->status);
        $this->assertNull($nota->numero_nfse);
    }

    public function test_xml_fora_do_xsd_nao_e_transmitido(): void
    {
        $nota = $this->nota();
        $cliente = $this->cliente(self::RETORNO_TESTE);
        $gerador = new class extends NfseIpmXmlGerador
        {
            public function gerar(Nfse $nfse, bool $envioTeste = false): string
            {
                return str_replace('<IncentivoFiscal>2</IncentivoFiscal>', '<IncentivoFiscal>9</IncentivoFiscal>', parent::gerar($nfse, $envioTeste));
            }
        };

        try {
            (new NfseIpmEmitirService($gerador, $cliente, $this->assinador()))->transmitir($nota);
            $this->fail('O XML inválido não poderia ser transmitido.');
        } catch (NfseNaoTransmitida $exception) {
            $this->assertStringContainsString('XSD oficial IPM', $exception->getMessage());
        }

        $this->assertSame('', $cliente->xml);
        $this->assertNull($nota->fresh()->transmitindo_em);
    }

    private function servico(NfseIpmCliente $cliente): NfseIpmEmitirService
    {
        return new NfseIpmEmitirService(new NfseIpmXmlGerador, $cliente, $this->assinador());
    }

    private function cliente(string $retorno): NfseIpmCliente
    {
        return new class($retorno) extends NfseIpmCliente
        {
            public string $xml = '';

            public ?bool $envioTeste = null;

            public function __construct(private readonly string $retorno) {}

            public function enviar(Empresa $empresa, string $xmlRps, bool $envioTeste = false): NfseIpmResposta
            {
                $this->xml = $xmlRps;
                $this->envioTeste = $envioTeste;

                return NfseIpmResposta::interpretar($this->retorno, $envioTeste);
            }
        };
    }

    private function assinador(): NfseIpmAssinador
    {
        return new class extends NfseIpmAssinador
        {
            public function certificadoDaNota(Nfse $nfse): Certificate
            {
                $config = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA, 'digest_alg' => 'sha256'];
                $cnf = base_path('tools/php/extras/ssl/openssl.cnf');

                if (is_file($cnf)) {
                    $config['config'] = $cnf;
                }

                $chave = openssl_pkey_new($config);
                $x509 = openssl_csr_sign(openssl_csr_new(['commonName' => 'EMPRESA TESTE:54644503000129'], $chave, $config), null, $chave, 1, $config);
                openssl_x509_export($x509, $pem);
                openssl_pkey_export($chave, $chavePem, null, $config);

                return new Certificate($chavePem, $pem, '54644503000129');
            }
        };
    }

    private function nota(string $ambiente = 'producao_restrita'): Nfse
    {
        $empresa = Empresa::query()->create([
            'codigo' => (string) random_int(1000, 9999),
            'nome' => 'EMPRESA IPM',
            'fantasia' => 'EMPRESA IPM',
            'ativo' => true,
        ]);
        $empresa->forceFill([
            'cnpj' => '54644503000129',
            'razao_social' => 'EMPRESA IPM LTDA',
            'im' => '230780',
            'cnae' => '4520001',
            'cidade_codigo' => '4101804',
            'uf' => 'PR',
            'regime_tributario' => 'simples',
            'nfse_provedor' => 'ipm',
            'nfse_ambiente' => $ambiente,
            'nfse_tipo_rps' => '1',
            'nfse_serie_rps' => 'NE',
            'nfse_ws_usuario' => '54644503000129',
            'nfse_ws_senha' => 'segredo',
            'nfse_url_producao' => 'https://ipm.invalid/?pg=services',
        ])->save();

        $nota = Nfse::query()->forceCreate([
            'empresa_id' => $empresa->id,
            'status' => Nfse::STATUS_ABERTA,
            'serie_dps' => 'NE',
            'numero_dps' => 5,
            'competencia' => '2026-10-01',
            'data_emissao' => '2026-10-05',
            'tomador_nome' => 'CLIENTE TESTE',
            'tomador_cpf_cnpj' => '12345678909',
            'tomador_endereco' => 'RUA TESTE',
            'tomador_numero' => '10',
            'tomador_bairro' => 'CENTRO',
            'tomador_cep' => '83702000',
            'tomador_cidade_codigo' => '4101804',
            'tomador_uf' => 'PR',
            'municipio_prestacao_codigo' => '4101804',
            'trib_issqn' => '1',
            'tp_ret_issqn' => '1',
            'valor_servicos' => '100.00',
            'desconto' => '0.00',
            'iss' => '0.00',
            'total' => '100.00',
        ]);
        NfseItem::query()->forceCreate([
            'nfse_id' => $nota->id,
            'ordem' => 1,
            'codigo' => 'S1',
            'unidade' => 'UN',
            'descricao' => 'TROCA DE OLEO DE CAMBIO',
            'quantidade' => '1.000',
            'valor' => '100.00',
            'total' => '100.00',
            'c_trib_nac' => '140101',
            'c_nbs' => '120013110',
        ]);

        return $nota->fresh(['empresa', 'itens']);
    }
}
