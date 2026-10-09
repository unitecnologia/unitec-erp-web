<?php

namespace App\Support\Erp\Printing;

use App\Models\Terminal;
use App\Support\Erp\Dashboard\ErpDashboardCertificadoAlert;
use App\Support\Erp\Pdv\TerminalResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Preferências leves da tela Impressão de Etiquetas (modelo + impressora).
 * Arquivo local — sem tabela nova.
 * Chave: empresa + terminal/máquina; se sem terminal, empresa + usuário.
 */
final class EtiquetasPrintPrefs
{
    public static function path(): string
    {
        return storage_path('app/erp-etiquetas-print-prefs.json');
    }

    /**
     * @return array{modelo: string, impressora: string}|null
     */
    public static function load(?int $empresaId = null): ?array
    {
        $key = self::scopeKey($empresaId);
        if ($key === null) {
            return null;
        }

        $all = self::readAll();
        $row = $all[$key] ?? null;
        if (! is_array($row)) {
            return null;
        }

        $modelo = trim((string) ($row['modelo_etiqueta'] ?? $row['modelo'] ?? ''));
        $impressora = trim((string) ($row['impressora'] ?? ''));
        if ($modelo === '' && $impressora === '') {
            return null;
        }

        return [
            'modelo' => $modelo !== '' ? $modelo : 'gondola',
            'impressora' => $impressora,
        ];
    }

    public static function save(string $modelo, string $impressora, ?int $empresaId = null): void
    {
        $key = self::scopeKey($empresaId);
        if ($key === null) {
            return;
        }

        $all = self::readAll();
        $all[$key] = [
            'modelo_etiqueta' => trim($modelo),
            'impressora' => trim($impressora),
        ];

        $dir = dirname(self::path());
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        File::put(
            self::path(),
            json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    /**
     * empresa:{id}|terminal:{id}  ou  empresa:{id}|user:{id}
     */
    private static function scopeKey(?int $empresaId = null): ?string
    {
        $resolver = TerminalResolver::make();
        $empresaId ??= $resolver->resolveEmpresaId() ?? ErpDashboardCertificadoAlert::resolveEmpresaId();
        if (! $empresaId) {
            return null;
        }

        $terminalId = self::resolveIdentifiableTerminalId($empresaId, $resolver);
        if ($terminalId !== null) {
            return 'empresa:'.$empresaId.'|terminal:'.$terminalId;
        }

        $userId = Auth::id();
        if ($userId) {
            return 'empresa:'.$empresaId.'|user:'.(int) $userId;
        }

        return null;
    }

    private static function resolveIdentifiableTerminalId(int $empresaId, TerminalResolver $resolver): ?int
    {
        // Hostname é do servidor: PC da rede só usa o terminal vinculado ao próprio navegador.
        if ($resolver->deviceUuid() !== null && ! $resolver->isServerRequest()) {
            return $resolver->current()?->id;
        }

        $sessionId = session('erp.terminal_id');
        if (filled($sessionId)) {
            $exists = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->whereKey((int) $sessionId)
                ->exists();
            if ($exists) {
                return (int) $sessionId;
            }
        }

        $machineName = $resolver->resolveMachineName();
        if ($machineName === '') {
            return null;
        }

        $byNome = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('nome', $machineName)
            ->value('id');
        if ($byNome) {
            return (int) $byNome;
        }

        if (Schema::hasColumn('terminais', 'device_name')) {
            $byDevice = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->where('device_name', $machineName)
                ->value('id');
            if ($byDevice) {
                return (int) $byDevice;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{modelo_etiqueta?: string, modelo?: string, impressora?: string}>
     */
    private static function readAll(): array
    {
        $path = self::path();
        if (! File::exists($path)) {
            return [];
        }

        $raw = json_decode((string) File::get($path), true);

        return is_array($raw) ? $raw : [];
    }
}
