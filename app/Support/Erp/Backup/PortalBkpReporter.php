<?php

namespace App\Support\Erp\Backup;

use App\Models\Empresa;
use App\Support\Erp\ErpSystemConfig;
use App\Support\Erp\License\LicencaHttpClient;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PortalBkpReporter
{
    public function report(Empresa $empresa, string $status, ?string $details = null): void
    {
        $result = $this->send($empresa, $status, $details);

        if ($result['skipped'] || $result['ok']) {
            return;
        }

        Log::warning($result['http'] === null
            ? 'Não foi possível informar backup ao portal.'
            : 'Portal BKP recusou o resultado do backup.', [
            'empresa_id' => $empresa->id,
            'status_http' => $result['http'],
            'message' => $result['message'],
        ]);
    }

    /**
     * Envia um log de teste para cada empresa e devolve o resultado, um CNPJ por linha.
     *
     * @return array{ok: bool, linhas: list<string>}
     */
    public function testConnections(): array
    {
        $empresas = Empresa::query()->orderBy('id')->get();

        if ($empresas->isEmpty()) {
            return [
                'ok' => false,
                'linhas' => ['Nenhuma empresa cadastrada para enviar o log.'],
            ];
        }

        $linhas = [];
        $ok = true;

        foreach ($empresas as $empresa) {
            $result = $this->send($empresa, 'ok', 'Teste de conexão Log de BKP');
            $nome = trim((string) ($empresa->nome ?? ''));
            $rotulo = $nome !== '' ? $nome.' · '.$result['cnpj_mascarado'] : $result['cnpj_mascarado'];
            $linhas[] = $rotulo.': '.$result['message'];

            if (! $result['ok']) {
                $ok = false;
            }
        }

        return [
            'ok' => $ok,
            'linhas' => $linhas,
        ];
    }

    /**
     * @return array{ok: bool, skipped: bool, http: ?int, message: string, cnpj_mascarado: string}
     */
    private function send(Empresa $empresa, string $status, ?string $details): array
    {
        ErpSystemConfig::ensurePortalBkpToken();

        $token = ErpSystemConfig::portalBkpToken();
        $cnpj = preg_replace('/\D/', '', (string) ($empresa->cnpj ?? '')) ?: '';
        $cnpjMascarado = strlen($cnpj) === 14
            ? preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj)
            : ($cnpj !== '' ? $cnpj : 'sem CNPJ');

        if ($token === '') {
            return [
                'ok' => false,
                'skipped' => true,
                'http' => null,
                'message' => 'Token do portal não configurado.',
                'cnpj_mascarado' => $cnpjMascarado,
            ];
        }

        if (strlen($cnpj) !== 14) {
            return [
                'ok' => false,
                'skipped' => true,
                'http' => null,
                'message' => 'CNPJ inválido. Log não enviado.',
                'cnpj_mascarado' => $cnpjMascarado,
            ];
        }

        $baseUrl = rtrim((string) config('unitec.licenca_api.base_url', 'https://unitecnologiasc.digital'), '/');

        try {
            $response = LicencaHttpClient::make()
                ->timeout(8)
                ->connectTimeout(3)
                ->acceptJson()
                ->withHeader('X-BKP-Token', $token)
                ->post($baseUrl.'/api/bkp/'.$cnpj, [
                    'status' => $status === 'ok' ? 'sucesso' : 'falha',
                    'detalhes' => $details !== null ? mb_substr($details, 0, 1000) : null,
                ]);

            if ($response->successful()) {
                return [
                    'ok' => true,
                    'skipped' => false,
                    'http' => $response->status(),
                    'message' => 'Portal aceitou o log.',
                    'cnpj_mascarado' => $cnpjMascarado,
                ];
            }

            return [
                'ok' => false,
                'skipped' => false,
                'http' => $response->status(),
                'message' => 'Portal recusou o log (HTTP '.$response->status().').',
                'cnpj_mascarado' => $cnpjMascarado,
            ];
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'skipped' => false,
                'http' => null,
                'message' => 'Sem conexão com o portal.',
                'cnpj_mascarado' => $cnpjMascarado,
            ];
        }
    }
}
