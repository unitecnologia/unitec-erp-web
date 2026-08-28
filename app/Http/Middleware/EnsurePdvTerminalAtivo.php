<?php

namespace App\Http\Middleware;

use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\License\DeviceLicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza carga/retorno do PDV offline pelo terminal (empresa_id + nome/nº).
 * Se o terminal ainda não existir, cria automaticamente quando houver vaga de computador.
 */
class EnsurePdvTerminalAtivo
{
    public function __construct(
        private readonly DeviceLicenseService $devices,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $empresaId = (int) (
            $request->query('empresa_id')
            ?: $request->input('empresa_id')
            ?: config('pdv_carga.default_empresa_id')
            ?: 0
        );

        $terminalKey = trim((string) (
            $request->query('terminal')
            ?: $request->input('terminal')
            ?: ''
        ));

        if ($empresaId < 1) {
            return response()->json([
                'message' => 'Informe empresa_id do ERP.',
            ], 422);
        }

        if ($terminalKey === '') {
            return response()->json([
                'message' => 'Informe o número do PDV (ex.: 1 → PDV1).',
            ], 422);
        }

        $reportedIp = $this->resolveReportedIp($request);
        $deviceUuid = trim((string) (
            $request->query('device_uuid')
            ?: $request->input('device_uuid')
            ?: $request->header('X-Pdv-Device-Uuid')
            ?: ''
        ));

        try {
            $terminal = $this->devices->registerPdvOffline(
                $empresaId,
                $terminalKey,
                $reportedIp,
                $deviceUuid !== '' ? $deviceUuid : null,
            );
        } catch (DeviceLicenseLimitExceeded $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Não foi possível liberar o terminal do PDV: '.$e->getMessage(),
            ], 500);
        }

        // Preferência: IP de rede reportado pelo caixa (DHCP).
        if ($reportedIp !== null && (string) $terminal->ip !== $reportedIp) {
            $terminal->forceFill(['ip' => $reportedIp])->saveQuietly();
            $terminal = $terminal->fresh() ?? $terminal;
        }

        $printer = $this->resolveReportedPrinter($request);
        if ($printer !== null) {
            $fill = [];
            foreach ($printer as $key => $value) {
                if ((string) ($terminal->{$key} ?? '') !== (string) ($value ?? '')) {
                    $fill[$key] = $value;
                }
            }
            if ($fill !== []) {
                $terminal->forceFill($fill)->saveQuietly();
                $terminal = $terminal->fresh() ?? $terminal;
            }
        }

        $request->attributes->set('pdv_empresa_id', $empresaId);
        $request->attributes->set('pdv_terminal', $terminal);

        return $next($request);
    }

    private function resolveReportedIp(Request $request): ?string
    {
        $reported = trim((string) (
            $request->query('terminal_ip')
            ?: $request->input('terminal_ip')
            ?: $request->header('X-Pdv-Terminal-Ip')
            ?: ''
        ));

        $ip = $this->normalizeLanIp($reported);

        if ($ip !== null) {
            return $ip;
        }

        return $this->normalizeLanIp(trim((string) ($request->ip() ?: '')));
    }

    /**
     * Impressora local do PDV (engrenagem). Ausente = PDV antigo, não zera o cadastro.
     *
     * @return array{tipo_impressora: string, nvias: int, modelo: string, porta: string, impressora_nome: ?string}|null
     */
    private function resolveReportedPrinter(Request $request): ?array
    {
        if (! $request->exists('tipo_impressora') && ! $request->headers->has('X-Pdv-Tipo-Impressora')) {
            return null;
        }

        $tipo = $request->query('tipo_impressora');
        if ($tipo === null || $tipo === '') {
            $tipo = $request->input('tipo_impressora');
        }
        if ($tipo === null || $tipo === '') {
            $tipo = $request->header('X-Pdv-Tipo-Impressora');
        }
        $tipo = (string) ($tipo ?? '1');
        if (! in_array($tipo, ['0', '1', '2', '3'], true)) {
            $tipo = '1';
        }

        $nviasRaw = $request->query('nvias', $request->input('nvias'));
        $nvias = max(1, min(5, (int) ($nviasRaw !== null && $nviasRaw !== '' ? $nviasRaw : 1)));

        $modelo = strtoupper(trim((string) (
            $request->query('modelo')
            ?: $request->input('modelo')
            ?: 'ELGIN'
        )));

        $porta = trim((string) (
            $request->query('porta')
            ?: $request->input('porta')
            ?: ''
        ));

        $nome = trim((string) (
            $request->query('impressora_nome')
            ?: $request->input('impressora_nome')
            ?: ''
        ));

        if ($nome === '' && preg_match('/^RAW:(.+)$/iu', $porta, $m) === 1) {
            $nome = trim((string) $m[1]);
        }

        return [
            'tipo_impressora' => $tipo,
            'nvias' => $nvias,
            'modelo' => $modelo !== '' ? $modelo : 'ELGIN',
            'porta' => $porta,
            'impressora_nome' => $nome !== '' ? $nome : null,
        ];
    }

    private function normalizeLanIp(string $ip): ?string
    {
        if ($ip === '' || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return null;
        }

        if (str_starts_with($ip, '127.') || str_starts_with($ip, '169.254.')) {
            return null;
        }

        return $ip;
    }
}
