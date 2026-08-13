<?php

namespace App\Http\Middleware;

use App\Models\Terminal;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autoriza carga/retorno do PDV offline pelo terminal ativo no ERP
 * (empresa_id + terminal = nº lógico ou nome). Sem token Bearer.
 */
class EnsurePdvTerminalAtivo
{
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
                'message' => 'Informe o terminal (nº lógico ou nome cadastrado em Terminais).',
            ], 422);
        }

        $terminal = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where(function (Builder $q) use ($terminalKey): void {
                $q->where('numero_logico_terminal', $terminalKey)
                    ->orWhere('nome', $terminalKey);

                if (ctype_digit($terminalKey)) {
                    $q->orWhere('id', (int) $terminalKey);
                }
            })
            ->first();

        if (! $terminal) {
            $disponiveis = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->orderBy('numero_logico_terminal')
                ->orderBy('id')
                ->get(['id', 'nome', 'numero_logico_terminal', 'ativo'])
                ->map(function (Terminal $t): string {
                    $num = $t->numero_logico_terminal !== null && $t->numero_logico_terminal !== ''
                        ? (string) $t->numero_logico_terminal
                        : '#'.$t->id;

                    return $num.' = '.$t->nome.((bool) ($t->ativo ?? true) ? '' : ' (inativo)');
                })
                ->implode('; ');

            return response()->json([
                'message' => 'Terminal "'.$terminalKey.'" não encontrado para esta empresa.'
                    .($disponiveis !== ''
                        ? ' Disponíveis: '.$disponiveis.'. Use o nome (ex.: DESKTOP-...) ou o nº lógico.'
                        : ' Cadastre o terminal em Configurações → Terminais.'),
            ], 404);
        }

        if (! (bool) ($terminal->ativo ?? true)) {
            return response()->json([
                'message' => 'Terminal inativo. Ative-o em Configurações → Terminais.',
            ], 403);
        }

        $terminal = $this->syncTerminalIp($request, $terminal);

        $request->attributes->set('pdv_empresa_id', $empresaId);
        $request->attributes->set('pdv_terminal', $terminal);

        return $next($request);
    }

    /**
     * Atualiza Terminais.ip com o IP de rede reportado pelo PDV (DHCP).
     * Preferência: terminal_ip enviado pelo caixa; fallback: IP da requisição
     * (exceto loopback, comum quando URL do ERP é 127.0.0.1).
     */
    private function syncTerminalIp(Request $request, Terminal $terminal): Terminal
    {
        $reported = trim((string) (
            $request->query('terminal_ip')
            ?: $request->input('terminal_ip')
            ?: $request->header('X-Pdv-Terminal-Ip')
            ?: ''
        ));

        $ip = $this->normalizeLanIp($reported);

        if ($ip === null) {
            $fromRequest = trim((string) ($request->ip() ?: ''));
            $ip = $this->normalizeLanIp($fromRequest);
        }

        if ($ip === null || (string) $terminal->ip === $ip) {
            return $terminal;
        }

        $terminal->forceFill(['ip' => $ip])->saveQuietly();

        return $terminal->fresh() ?? $terminal;
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
