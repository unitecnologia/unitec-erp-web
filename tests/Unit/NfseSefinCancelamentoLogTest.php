<?php

namespace Tests\Unit;

use App\Models\Nfse;
use App\Support\Erp\Nfse\NfseSefinCancelamentoLog;
use App\Support\Erp\Nfse\NfseSefinCancelarService;
use App\Support\Erp\Nfse\NfseSefinEventoResposta;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NfseSefinCancelamentoLogTest extends TestCase
{
    public function test_contexto_inclui_id_numero_chave_e_erros_sem_xml(): void
    {
        $nfse = new Nfse;
        $nfse->id = 77;
        $nfse->forceFill([
            'numero_nfse' => '48',
            'chave_acesso' => '42032042254644503000129000000000004826110170089842',
        ]);

        $ctx = NfseSefinCancelamentoLog::contexto(
            NfseSefinCancelamentoLog::OPERACAO_POST_CANCELAMENTO,
            $nfse,
            '42032042254644503000129000000000004826110170089842',
            [
                'http' => 400,
                'registrado' => false,
                'erros' => [
                    ['codigo' => 'E999', 'descricao' => 'Rejeicao teste'],
                ],
                'motivo' => 'A SEFIN Nacional não confirmou o cancelamento.',
                'resultado_consulta' => 'nao_confirmado',
            ],
        );

        $this->assertSame('POST cancelamento', $ctx['operacao']);
        $this->assertSame(77, $ctx['nfse_id']);
        $this->assertSame('48', $ctx['numero_nfse']);
        $this->assertSame('42032042254644503000129000000000004826110170089842', $ctx['chave']);
        $this->assertSame(400, $ctx['http']);
        $this->assertFalse($ctx['registrado']);
        $this->assertSame('nao_confirmado', $ctx['resultado_consulta']);
        $this->assertSame('A SEFIN Nacional não confirmou o cancelamento.', $ctx['motivo']);
        $this->assertSame([['codigo' => 'E999', 'descricao' => 'Rejeicao teste']], $ctx['erros']);
        $this->assertArrayNotHasKey('xml', $ctx);
        $this->assertArrayNotHasKey('certificado', $ctx);
        $this->assertArrayNotHasKey('token', $ctx);
        $this->assertArrayNotHasKey('cpf', $ctx);
    }

    public function test_falha_grava_warning_no_canal_padrao(): void
    {
        Log::spy();

        $nfse = new Nfse;
        $nfse->id = 12;
        $nfse->forceFill(['numero_nfse' => '99']);

        NfseSefinCancelamentoLog::falha(
            NfseSefinCancelamentoLog::OPERACAO_GET_CONSULTA,
            $nfse,
            str_repeat('1', 50),
            [
                'http' => 404,
                'registrado' => false,
                'resultado_consulta' => 'nao_confirmado',
                'motivo' => 'A SEFIN Nacional não confirmou o cancelamento.',
            ],
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'NFS-e Nacional: diagnóstico de cancelamento.'
                    && ($context['operacao'] ?? null) === NfseSefinCancelamentoLog::OPERACAO_GET_CONSULTA
                    && ($context['nfse_id'] ?? null) === 12
                    && ($context['numero_nfse'] ?? null) === '99'
                    && ($context['http'] ?? null) === 404
                    && str_contains((string) ($context['motivo'] ?? ''), 'não confirmou o cancelamento');
            });
    }

    public function test_sanitiza_trecho_com_aparencia_de_xml_ou_certificado(): void
    {
        $limpo = NfseSefinCancelamentoLog::sanitizarTexto(
            "BEGIN CERTIFICATE\nABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"
        );

        $this->assertStringNotContainsString("\n", $limpo);
        $this->assertLessThanOrEqual(180, mb_strlen($limpo, 'UTF-8'));

        $xml = NfseSefinCancelamentoLog::sanitizarTexto('<evento>'.str_repeat('X', 400).'</evento>');
        $this->assertLessThanOrEqual(180, mb_strlen($xml, 'UTF-8'));
    }

    public function test_interpretacao_sem_confirmacao_gera_motivo_padrao_e_servico_loga_warning(): void
    {
        $resposta = NfseSefinEventoResposta::interpretar(200, json_encode([
            'tipoAmbiente' => 1,
            'erros' => [],
        ], JSON_THROW_ON_ERROR));

        $this->assertFalse($resposta->registrado);
        $this->assertStringContainsString('não confirmou o cancelamento', $resposta->mensagemErros());

        Log::spy();
        $nfse = new Nfse;
        $nfse->id = 5;
        $nfse->forceFill(['numero_nfse' => '7']);

        NfseSefinCancelamentoLog::falha(
            NfseSefinCancelamentoLog::OPERACAO_POST_CANCELAMENTO,
            $nfse,
            str_repeat('9', 50),
            [
                'http' => $resposta->http,
                'registrado' => false,
                'erros' => $resposta->erros,
                'motivo' => $resposta->erros[0]['descricao'] ?? '',
                'resultado_consulta' => 'nao_confirmado',
            ],
        );

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_diagnostico_registra_motivo_real_do_http_400_com_campo_erro(): void
    {
        $resposta = NfseSefinEventoResposta::interpretar(400, json_encode([
            'erro' => [[
                'Codigo' => 'E0822',
                'Descricao' => 'Prazo para cancelamento da NFS-e expirado.',
            ]],
        ], JSON_THROW_ON_ERROR));

        Log::spy();
        $nfse = new Nfse;
        $nfse->id = 14;
        $nfse->forceFill(['numero_nfse' => '48']);

        NfseSefinCancelamentoLog::falha(
            NfseSefinCancelamentoLog::OPERACAO_POST_CANCELAMENTO,
            $nfse,
            str_repeat('4', 50),
            [
                'http' => $resposta->http,
                'registrado' => false,
                'erros' => $resposta->erros,
                'motivo' => $resposta->mensagemErros(),
                'resultado_consulta' => 'nao_confirmado',
            ],
        );

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'NFS-e Nacional: diagnóstico de cancelamento.'
                    && ($context['nfse_id'] ?? null) === 14
                    && ($context['numero_nfse'] ?? null) === '48'
                    && ($context['http'] ?? null) === 400
                    && ($context['erros'][0]['codigo'] ?? null) === 'E0822'
                    && str_contains((string) ($context['motivo'] ?? ''), 'E0822')
                    && str_contains((string) ($context['motivo'] ?? ''), 'Prazo para cancelamento')
                    && ! str_contains((string) ($context['motivo'] ?? ''), 'não confirmou o cancelamento');
            });
    }

    public function test_cancelar_service_usa_diagnostico_e_warning_nas_falhas(): void
    {
        $src = (string) file_get_contents((new \ReflectionClass(NfseSefinCancelarService::class))->getFileName());

        $this->assertStringContainsString('NfseSefinCancelamentoLog::falha', $src);
        $this->assertStringContainsString('OPERACAO_POST_CANCELAMENTO', $src);
        $this->assertStringContainsString('OPERACAO_GET_CONSULTA', $src);
        $this->assertStringContainsString('não confirmou o cancelamento', $src);
        $this->assertGreaterThanOrEqual(3, substr_count($src, 'NfseSefinCancelamentoLog::falha'));

        $logSrc = (string) file_get_contents((new \ReflectionClass(NfseSefinCancelamentoLog::class))->getFileName());
        $this->assertStringContainsString('Log::warning(', $logSrc);
        $this->assertStringNotContainsString('xml_nfse', $logSrc);
        $this->assertStringNotContainsString("'certificado'", $logSrc);
        $this->assertStringNotContainsString("'cpf'", $logSrc);
        $this->assertStringNotContainsString("'token'", $logSrc);
    }
}

