<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\VendasParametro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Grava AILOS_PRODUCTION_* no .env a partir dos dados já existentes
 * (empresa / certificado NF-e), sem imprimir segredos.
 */
final class AilosSyncPixProductionEnvCommand extends Command
{
    protected $signature = 'ailos:sync-pix-production-env {--empresa=1 : ID da empresa}';

    protected $description = 'Sincroniza Secrets Ailos Pix de produção no .env (sem exibir valores)';

    public function handle(): int
    {
        $empresaId = max(1, (int) $this->option('empresa'));
        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            $this->error('Empresa não encontrada.');

            return self::FAILURE;
        }

        $envPath = base_path('.env');
        if (! is_file($envPath) || ! is_writable($envPath)) {
            $this->error('.env ausente ou sem permissão de escrita.');

            return self::FAILURE;
        }

        $clientId = trim((string) ($empresa->param_pix_client_id ?? ''));
        $clientSecret = (string) ($empresa->param_pix_client_secret ?? '');
        $pixKey = trim((string) ($empresa->param_pix_chave ?? ''));

        $pfxBase64 = '';
        $pfxPassword = '';

        try {
            $params = VendasParametro::forEmpresa($empresaId);
            if ($params->hasStoredSenhaCertificado()) {
                $pfxPassword = (string) $params->senha_certificado;
            }
            if (filled($params->getRawOriginal('certificado_pfx'))) {
                $binary = $params->certificado_pfx;
                if (is_string($binary) && strlen($binary) >= 100) {
                    $pfxBase64 = base64_encode($binary);
                }
            }
        } catch (Throwable $e) {
            $this->warn('Certificado NF-e não lido: '.$e->getMessage());
        }

        $updates = [
            'AILOS_PRODUCTION_CLIENT_ID' => $clientId,
            'AILOS_PRODUCTION_CLIENT_SECRET' => $clientSecret,
            'AILOS_PRODUCTION_PIX_KEY' => $pixKey,
            'AILOS_PRODUCTION_PFX_PASSWORD' => $pfxPassword,
            'AILOS_PRODUCTION_PFX_BASE64' => $pfxBase64,
            'AILOS_PRODUCTION_BASE_URL' => 'https://pixcobranca.ailos.coop.br/ailos/pix-cobranca/api/v1',
        ];

        $missing = [];
        foreach (['AILOS_PRODUCTION_CLIENT_ID', 'AILOS_PRODUCTION_CLIENT_SECRET', 'AILOS_PRODUCTION_PIX_KEY'] as $key) {
            if ($updates[$key] === '') {
                $missing[] = $key;
            }
        }
        if ($updates['AILOS_PRODUCTION_PFX_BASE64'] === '' || $updates['AILOS_PRODUCTION_PFX_PASSWORD'] === '') {
            $missing[] = 'AILOS_PRODUCTION_PFX_BASE64/PASSWORD (certificado A1 NF-e)';
        }

        if ($missing !== []) {
            $this->error('Faltam dados para sincronizar: '.implode(', ', $missing));

            return self::FAILURE;
        }

        $content = File::get($envPath);
        foreach ($updates as $key => $value) {
            $content = $this->upsertEnvLine($content, $key, $value);
        }
        File::put($envPath, $content);

        // Produção não guarda mais esses segredos na empresa (fonte = .env).
        $empresa->forceFill([
            'param_pix_habilitar' => true,
            'param_pix_provedor' => 'ailos',
            'param_pix_ambiente' => 'producao',
            'param_pix_client_id' => null,
            'param_pix_client_secret' => null,
            'param_pix_chave' => null,
            'param_pix_certificado' => null,
            'param_pix_certificado_senha' => null,
        ])->save();

        $this->callSilent('config:clear');

        $this->info('Secrets Ailos de produção sincronizados no .env.');
        $this->info('Client ID/Secret/Chave: ok | PFX (NF-e): ok | Empresa limpa de segredos Pix.');

        return self::SUCCESS;
    }

    private function upsertEnvLine(string $content, string $key, string $value): string
    {
        $escaped = $this->escapeEnvValue($value);
        $line = $key.'='.$escaped;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $content) === 1) {
            return (string) preg_replace($pattern, $line, $content, 1);
        }

        return rtrim($content).PHP_EOL.PHP_EOL.$line.PHP_EOL;
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (preg_match('/\s|"|\'|#|\\\\/', $value) === 1 || str_contains($value, '=')) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
