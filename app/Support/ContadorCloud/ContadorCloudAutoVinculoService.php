<?php

namespace App\Support\ContadorCloud;

use App\Models\Contador;
use App\Models\Empresa;
use App\Support\Erp\DocumentoBrasileiroValidator;
use Illuminate\Support\Facades\Log;

/**
 * Vínculo automático ao salvar parâmetros do Portal do Contador.
 * Idempotente: só chama a API quando habilitar + contador exigem credenciais novas.
 */
final class ContadorCloudAutoVinculoService
{
    public function __construct(
        private readonly ContadorCloudPairingClient $pairingClient = new ContadorCloudPairingClient(),
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     attempted: bool,
     *     message: string,
     *     code: ?string,
     *     http_status: ?int
     * }
     */
    public function syncAfterSave(Empresa $empresa, ?int $contadorIdAntesDoSave = null, bool $force = false): array
    {
        $empresa->refresh();

        $habilitar = (bool) $empresa->param_portal_contador_habilitar;
        $contadorId = self::nullableInt($empresa->param_portal_contador_contador_id);
        $config = ContadorCloudConfig::fromEmpresa($empresa);

        if (! $habilitar) {
            return [
                'ok' => true,
                'attempted' => false,
                'message' => 'Portal do Contador desabilitado — envio pausado (credenciais preservadas).',
                'code' => 'disabled',
                'http_status' => null,
            ];
        }

        if ($contadorId === null) {
            return [
                'ok' => false,
                'attempted' => false,
                'message' => 'Selecione o contador vinculado antes de habilitar o Portal do Contador.',
                'code' => 'contador_obrigatorio',
                'http_status' => null,
            ];
        }

        $contadorMudou = $contadorIdAntesDoSave !== null
            && $contadorIdAntesDoSave > 0
            && $contadorIdAntesDoSave !== $contadorId;

        $precisaVincular = $force
            || ! $config->isConfigured()
            || $contadorMudou;

        // Já vinculado ao mesmo contador: não consulta cadastro nem chama o portal.
        if (! $precisaVincular) {
            return [
                'ok' => true,
                'attempted' => false,
                'message' => 'Vínculo já ativo — nenhuma chamada ao portal.',
                'code' => 'idempotente',
                'http_status' => null,
            ];
        }

        $contador = Contador::query()->find($contadorId);

        if (! $contador) {
            return [
                'ok' => false,
                'attempted' => false,
                'message' => 'Contador selecionado não encontrado no cadastro.',
                'code' => 'contador_inexistente',
                'http_status' => null,
            ];
        }

        $pendencias = $this->pendenciasCadastroContador($contador);

        if ($pendencias !== []) {
            return [
                'ok' => false,
                'attempted' => false,
                'message' => 'Pendências no cadastro do contador: '.implode('; ', $pendencias).'.',
                'code' => 'pendencia_cadastro',
                'http_status' => 400,
            ];
        }

        // Troca de contador: impede envio ao anterior antes do novo token.
        if ($contadorMudou) {
            $this->limparCredenciaisEnvio($empresa, preservarHabilitar: true, preservarContadorId: true);
            $empresa->refresh();
        }

        $resultado = $this->pairingClient->vincularAutomatico($empresa, $contador);

        if (! $resultado['ok']) {
            // Falha temporária com mesmo contador e credenciais ainda válidas: não apagar.
            if (! $contadorMudou && $config->isConfigured()) {
                Log::warning('Portal do Contador: falha ao renovar vínculo; credenciais anteriores preservadas.', [
                    'empresa_id' => $empresa->id,
                    'code' => $resultado['code'] ?? null,
                    'http_status' => $resultado['http_status'] ?? null,
                ]);

                return [
                    'ok' => false,
                    'attempted' => true,
                    'message' => $resultado['message'].' Credenciais anteriores foram mantidas.',
                    'code' => $resultado['code'] ?? 'vinculo_falhou',
                    'http_status' => $resultado['http_status'] ?? null,
                ];
            }

            return [
                'ok' => false,
                'attempted' => true,
                'message' => $resultado['message'],
                'code' => $resultado['code'] ?? 'vinculo_falhou',
                'http_status' => $resultado['http_status'] ?? null,
            ];
        }

        $credenciais = is_array($resultado['data']['credenciais'] ?? null)
            ? $resultado['data']['credenciais']
            : null;

        if (! is_array($credenciais) || blank($credenciais['token'] ?? null)) {
            // Sem token não grava “conectado”; se já havia credenciais do mesmo contador, mantém.
            $preservou = ! $contadorMudou && $config->isConfigured();

            return [
                'ok' => false,
                'attempted' => true,
                'message' => 'Portal respondeu sem token. O vínculo não foi concluído — tente novamente após a correção (ex.: SMTP).'
                    .($preservou ? ' Credenciais anteriores foram mantidas.' : ''),
                'code' => 'sem_token',
                'http_status' => $resultado['http_status'] ?? null,
            ];
        }

        $this->aplicarCredenciais($empresa, $resultado['data'], $contador);

        return [
            'ok' => true,
            'attempted' => true,
            'message' => $resultado['message'],
            'code' => 'authorized',
            'http_status' => $resultado['http_status'] ?? 201,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function aplicarCredenciais(Empresa $empresa, array $payload, ?Contador $contador = null): void
    {
        $credenciais = is_array($payload['credenciais'] ?? null) ? $payload['credenciais'] : [];
        $contadorPortal = is_array($credenciais['contador'] ?? null) ? $credenciais['contador'] : [];
        $portalBaseUrl = ContadorCloudHttpHelper::resolvePortalBaseUrl();

        $fields = [
            'param_portal_contador_habilitar' => true,
            'param_portal_contador_url' => $portalBaseUrl,
            'param_portal_contador_token' => trim((string) ($credenciais['token'] ?? '')),
            'param_portal_contador_empresa_id' => trim((string) ($credenciais['empresaId'] ?? '')),
            'param_portal_contador_vinculo_id' => trim((string) (
                $payload['vinculoId']
                ?? $credenciais['vinculoId']
                ?? $empresa->param_portal_contador_vinculo_id
                ?? ''
            )),
            'param_portal_contador_contador_nome_portal' => trim((string) ($contadorPortal['nome'] ?? '')),
            'param_portal_contador_vinculado_em' => now(),
            'param_portal_contador_ambiente' => 'producao',
            'param_portal_contador_timeout' => (int) config('contador-cloud.default_timeout', 30),
        ];

        if ($contador instanceof Contador) {
            $fields['param_portal_contador_contador_id'] = (int) $contador->getKey();
        }

        if (filled($contadorPortal['email'] ?? null)) {
            $fields['param_portal_contador_email'] = trim((string) $contadorPortal['email']);
        } elseif ($contador instanceof Contador && filled($contador->email)) {
            $fields['param_portal_contador_email'] = trim((string) $contador->email);
        }

        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $empresa->refresh();
    }

    public function limparCredenciaisEnvio(
        Empresa $empresa,
        bool $preservarHabilitar = false,
        bool $preservarContadorId = false,
    ): void {
        $fields = [
            'param_portal_contador_token' => '',
            'param_portal_contador_empresa_id' => '',
            'param_portal_contador_vinculo_id' => '',
            'param_portal_contador_contador_nome_portal' => '',
            'param_portal_contador_vinculado_em' => null,
        ];

        if (! $preservarHabilitar) {
            $fields['param_portal_contador_habilitar'] = false;
        }

        if (! $preservarContadorId) {
            $fields['param_portal_contador_contador_id'] = null;
        }

        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $empresa->refresh();
    }

    /**
     * @return list<string>
     */
    public function pendenciasCadastroContador(Contador $contador): array
    {
        $pendencias = [];

        if (blank($contador->nome)) {
            $pendencias[] = 'nome';
        }

        if (blank($contador->email)) {
            $pendencias[] = 'e-mail';
        }

        $doc = DocumentoBrasileiroValidator::digits((string) $contador->cnpj_cpf);

        if (strlen($doc) === 11) {
            $pendencias[] = 'CNPJ do escritório (CPF não é aceito)';
        } elseif (! DocumentoBrasileiroValidator::isValidCnpj($doc)) {
            $pendencias[] = 'CNPJ do escritório inválido (dígitos verificadores)';
        }

        return $pendencias;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }
}
