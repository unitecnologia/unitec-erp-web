<?php

namespace App\Support\Erp\License;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Support\Erp\Pdv\TerminalResolver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DeviceLicenseService
{
    public const CATEGORY_COMPUTADOR = 'computador';

    public const CATEGORY_TELEFONE = 'telefone';

    public function __construct(
        private readonly LicencaRemotaService $licencas,
    ) {}

    public function isAvailable(): bool
    {
        return Schema::hasColumn('terminais', 'device_uuid');
    }

    /**
     * Vincula o cookie do navegador desktop ao terminal da máquina (nome do PC),
     * sem criar um segundo registro "Mozilla…" que consumiria outra vaga.
     */
    public function attachBrowserDevice(
        int $empresaId,
        string $deviceUuid,
        string $origin,
        ?string $deviceName = null,
        ?string $platform = null,
    ): Terminal {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Controle de dispositivos ainda não está disponível. Atualize o banco de dados.');
        }

        $deviceUuid = trim($deviceUuid);
        $origin = strtolower(trim($origin));

        if ($deviceUuid === '' || ! in_array($origin, ['erp_web', 'gestor_web'], true)) {
            throw new \InvalidArgumentException('Identificação do navegador inválida.');
        }

        $resolver = TerminalResolver::make();

        $machine = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($machine === null) {
            $machine = $resolver->resolveOrCreateDefault($empresaId);
        }

        if ($machine === null || (int) $machine->empresa_id !== $empresaId) {
            throw new \RuntimeException('Não foi possível identificar o terminal deste computador.');
        }

        $machine = $resolver->ensureFriendlyWebTerminalName($machine);

        if (! (bool) ($machine->ativo ?? true)) {
            throw new DeviceLicenseLimitExceeded(
                'Este computador está desativado em Configurações → Terminais. Solicite a liberação ao administrador.'
            );
        }

        $currentUuid = trim((string) ($machine->device_uuid ?? ''));

        if ($currentUuid !== '' && $currentUuid !== $deviceUuid) {
            // Mesmo PC físico: o cookie novo substitui o vínculo antigo neste terminal.
            // Outros PCs não compartilham o mesmo TerminalResolver/hostname.
        }

        $alreadyCounted = strtolower((string) ($machine->categoria_licenca ?? '')) === self::CATEGORY_COMPUTADOR;

        if (! $alreadyCounted) {
            $this->assertCapacity($empresaId, self::CATEGORY_COMPUTADOR);
        }

        $this->reclaimDeviceUuid($empresaId, $deviceUuid, (int) $machine->id);
        $this->purgeBrowserOrphans($empresaId, (int) $machine->id, $deviceUuid);

        $origins = collect($machine->origens_dispositivo ?? [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->push($origin)
            ->unique()
            ->values()
            ->all();

        $machine->forceFill([
            'categoria_licenca' => self::CATEGORY_COMPUTADOR,
            'origens_dispositivo' => $origins,
            'device_uuid' => $deviceUuid,
            // device_name guarda o hostname do PC; não sobrescrever com label amigável.
            'device_name' => $machine->device_name ?: $resolver->resolveMachineName(),
            'device_platform' => $this->cleanNullable($platform) ?? $machine->device_platform,
            'device_registered_at' => $machine->device_registered_at ?? now(),
            'device_last_seen_at' => now(),
            'ip' => $this->preferredLanIp($machine->ip),
        ])->save();

        return $machine->fresh() ?? $machine;
    }

    /**
     * PDV offline: encontra por nome/nº ou cria automaticamente se houver vaga de computador.
     * Terminal inativo não é reaberto sozinho (admin precisa ativar em Terminais).
     */
    public function registerPdvOffline(int $empresaId, string $terminalKey, ?string $ip = null, ?string $deviceUuid = null): Terminal
    {
        $terminalKey = trim($terminalKey);
        $deviceUuid = $deviceUuid !== null ? trim($deviceUuid) : '';

        if ($empresaId < 1 || $terminalKey === '') {
            throw new \InvalidArgumentException('Empresa ou terminal inválido para PDV offline.');
        }

        if (! Empresa::query()->whereKey($empresaId)->exists()) {
            throw new \InvalidArgumentException(
                'Empresa id '.$empresaId.' não encontrada no ERP. Ajuste PDV_EMPRESA_ID no PDV.'
            );
        }

        $nome = $this->normalizePdvOfflineName($terminalKey);
        $numero = $this->extractPdvOfflineNumero($terminalKey);

        if ($nome === null || $numero === null) {
            throw new \InvalidArgumentException(
                'Informe só o número do PDV (ex.: 1, 2, 3). O nome no ERP será PDV1, PDV2…'
            );
        }

        $existing = $this->findPdvOfflineByNome($empresaId, $nome);

        if ($existing !== null) {
            if (! (bool) ($existing->ativo ?? true)) {
                throw new DeviceLicenseLimitExceeded(
                    'Terminal "'.$existing->nome.'" está inativo. Ative-o em Configurações → Terminais ou exclua (F4) para liberar o número.'
                );
            }

            $ownedUuid = trim((string) ($existing->device_uuid ?? ''));

            if ($ownedUuid !== '' && $deviceUuid !== '' && ! $this->deviceUuidEquals($ownedUuid, $deviceUuid)) {
                if ($this->isPdvOfflinePreCadastro($existing)) {
                    $this->reclaimDeviceUuid($empresaId, $deviceUuid, (int) $existing->id);
                    $existing->forceFill(['device_uuid' => $deviceUuid])->saveQuietly();
                    $this->touchPdvOffline($existing, $ip, $deviceUuid);

                    return $existing->fresh() ?? $existing;
                }

                throw new DeviceLicenseLimitExceeded(
                    $this->pdvOfflineConflictMessage($numero, $nome, $existing)
                );
            }

            $this->touchPdvOffline($existing, $ip, $deviceUuid !== '' ? $deviceUuid : null);

            return $existing->fresh() ?? $existing;
        }

        $this->assertCapacity($empresaId, self::CATEGORY_COMPUTADOR);

        if ($deviceUuid !== '' && $this->isAvailable()) {
            $this->reclaimDeviceUuid($empresaId, $deviceUuid, 0);
        }

        $payload = [
            ...Terminal::defaultAttributes($empresaId),
            'empresa_id' => $empresaId,
            'nome' => $nome,
            'numero_logico_terminal' => $numero,
            'ip' => $this->preferredLanIp($ip),
            'ativo' => true,
            'eh_caixa' => true,
            'pdv' => true,
            'imprime' => true,
        ];

        if ($this->isAvailable()) {
            $payload['categoria_licenca'] = self::CATEGORY_COMPUTADOR;
            $payload['origens_dispositivo'] = ['pdv_offline'];
            $payload['device_registered_at'] = now();
            $payload['device_last_seen_at'] = now();
            $payload['device_platform'] = 'pdv-offline';
            $payload['device_name'] = $nome;
            if ($deviceUuid !== '') {
                $payload['device_uuid'] = $deviceUuid;
            }
        }

        $terminal = new Terminal;
        $terminal->forceFill($payload);
        $terminal->save();

        return $terminal;
    }

    /**
     * Antes de excluir terminal PDV: libera device_uuid preso em outros registros da empresa.
     */
    public function releasePdvOfflineTerminalBeforeDelete(Terminal $terminal): void
    {
        if (! $this->isAvailable()) {
            return;
        }

        $uuid = trim((string) ($terminal->device_uuid ?? ''));
        $empresaId = (int) $terminal->empresa_id;
        $terminalId = (int) $terminal->id;

        if ($uuid === '' || $empresaId < 1 || $terminalId < 1) {
            return;
        }

        Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $uuid)
            ->where('id', '!=', $terminalId)
            ->update(['device_uuid' => null]);
    }

    public function isPdvOfflineTerminal(Terminal $terminal): bool
    {
        if ((bool) ($terminal->pdv ?? false)) {
            return true;
        }

        $origins = collect($terminal->origens_dispositivo ?? [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->all();

        if (in_array('pdv_offline', $origins, true)) {
            return true;
        }

        return preg_match('/^PDV\s*\d+$/i', trim((string) ($terminal->nome ?? ''))) === 1;
    }

    public function register(
        int $empresaId,
        string $deviceUuid,
        string $category,
        string $origin,
        ?string $deviceName = null,
        ?string $platform = null,
    ): Terminal {
        if (! $this->isAvailable()) {
            throw new \RuntimeException('Controle de dispositivos ainda não está disponível. Atualize o banco de dados.');
        }

        $deviceUuid = trim($deviceUuid);
        $category = strtolower(trim($category));
        $origin = strtolower(trim($origin));

        if ($deviceUuid === '' || ! in_array($category, [self::CATEGORY_COMPUTADOR, self::CATEGORY_TELEFONE], true)) {
            throw new \InvalidArgumentException('Identificação do dispositivo inválida.');
        }

        $existing = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($existing !== null) {
            if (! $existing->ativo) {
                throw new DeviceLicenseLimitExceeded(
                    'Este dispositivo está desativado em Configurações → Terminais. Solicite a liberação ao administrador.'
                );
            }

            // Órfão de browser desktop: consolidar no terminal da máquina.
            if (
                $category === self::CATEGORY_COMPUTADOR
                && in_array($origin, ['erp_web', 'gestor_web'], true)
                && $this->isBrowserOrphan($existing)
            ) {
                return $this->attachBrowserDevice($empresaId, $deviceUuid, $origin, $deviceName, $platform);
            }

            $this->touchDevice($existing, $origin, $deviceName, $platform);

            return $existing;
        }

        if (
            $category === self::CATEGORY_COMPUTADOR
            && in_array($origin, ['erp_web', 'gestor_web'], true)
        ) {
            return $this->attachBrowserDevice($empresaId, $deviceUuid, $origin, $deviceName, $platform);
        }

        $limit = $this->limitFor($empresaId, $category);

        $this->assertCapacity($empresaId, $category, $limit);

        $terminal = new Terminal;
        $terminal->forceFill([
            'empresa_id' => $empresaId,
            'nome' => $this->deviceTerminalName($deviceName, $deviceUuid),
            'ip' => request()?->ip(),
            'ativo' => true,
            'eh_caixa' => false,
            'pdv' => false,
            'imprime' => false,
            'categoria_licenca' => $category,
            'origens_dispositivo' => [$origin],
            'device_uuid' => $deviceUuid,
            'device_name' => $this->cleanNullable($deviceName),
            'device_platform' => $this->cleanNullable($platform),
            'device_registered_at' => now(),
            'device_last_seen_at' => now(),
        ]);
        $terminal->save();

        return $terminal;
    }

    public function limitFor(int $empresaId, string $category): ?int
    {
        $empresa = Empresa::query()->find($empresaId);
        $cnpj = preg_replace('/\D/', '', (string) ($empresa?->cnpj ?? '')) ?: '';

        if (strlen($cnpj) !== 14 || ! $this->licencas->isEnabled()) {
            return null;
        }

        $snapshot = $this->licencas->checkCnpj($cnpj);

        return $category === self::CATEGORY_TELEFONE
            ? $snapshot->quantidadeTelefones
            : $snapshot->quantidadeComputadores;
    }

    public function countInUse(int $empresaId, string $category): int
    {
        if (! Schema::hasColumn('terminais', 'categoria_licenca')) {
            return 0;
        }

        return Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('categoria_licenca', $category)
            ->count();
    }

    /**
     * @return array{
     *     computador: array{limit: ?int, in_use: int},
     *     telefone: array{limit: ?int, in_use: int}
     * }
     */
    public function usageForEmpresa(int $empresaId): array
    {
        return [
            self::CATEGORY_COMPUTADOR => [
                'limit' => $this->limitFor($empresaId, self::CATEGORY_COMPUTADOR),
                'in_use' => $this->countInUse($empresaId, self::CATEGORY_COMPUTADOR),
            ],
            self::CATEGORY_TELEFONE => [
                'limit' => $this->limitFor($empresaId, self::CATEGORY_TELEFONE),
                'in_use' => $this->countInUse($empresaId, self::CATEGORY_TELEFONE),
            ],
        ];
    }

    public function assertCapacity(int $empresaId, string $category, ?int $limit = null): void
    {
        $limit ??= $this->limitFor($empresaId, $category);

        if ($limit === null) {
            return;
        }

        $inUse = $this->countInUse($empresaId, $category);

        if ($inUse >= $limit) {
            $singular = $category === self::CATEGORY_TELEFONE ? 'telefone' : 'computador';

            throw new DeviceLicenseLimitExceeded(
                "Não há vaga de {$singular} disponível nesta licença (em uso: {$inUse} de {$limit}). "
                .'Peça ao administrador para desativar um terminal em Configurações → Terminais e tente novamente.'
            );
        }
    }

    private function findTerminalByKey(int $empresaId, string $terminalKey): ?Terminal
    {
        $pdvNome = $this->normalizePdvOfflineName($terminalKey);
        if ($pdvNome !== null) {
            $byPdv = $this->findPdvOfflineByNome($empresaId, $pdvNome);
            if ($byPdv !== null) {
                return $byPdv;
            }
        }

        return Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($terminalKey): void {
                $q->where('numero_logico_terminal', $terminalKey)
                    ->orWhere('nome', $terminalKey);

                if (ctype_digit($terminalKey)) {
                    $q->orWhere('id', (int) $terminalKey);
                }
            })
            ->first();
    }

    private function findPdvOfflineByNome(int $empresaId, string $nome): ?Terminal
    {
        return Terminal::query()
            ->where('empresa_id', $empresaId)
            ->whereRaw('UPPER(TRIM(nome)) = ?', [strtoupper($nome)])
            ->first();
    }

    private function normalizePdvOfflineName(string $terminalKey): ?string
    {
        $numero = $this->extractPdvOfflineNumero($terminalKey);

        return $numero !== null ? 'PDV'.$numero : null;
    }

    private function extractPdvOfflineNumero(string $terminalKey): ?int
    {
        $terminalKey = trim($terminalKey);

        if ($terminalKey === '') {
            return null;
        }

        if (preg_match('/^PDV\s*(\d+)$/i', $terminalKey, $m) === 1) {
            $n = (int) $m[1];

            return $n > 0 ? $n : null;
        }

        if (ctype_digit($terminalKey)) {
            $n = (int) $terminalKey;

            return $n > 0 ? $n : null;
        }

        return null;
    }

    private function deviceUuidEquals(string $a, string $b): bool
    {
        return strcasecmp(trim($a), trim($b)) === 0;
    }

    private function isPdvOfflinePreCadastro(Terminal $terminal): bool
    {
        $origins = collect($terminal->origens_dispositivo ?? [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->all();

        if (in_array('pdv_offline', $origins, true)) {
            return false;
        }

        return $terminal->device_last_seen_at === null;
    }

    private function pdvOfflineConflictMessage(int $numero, string $nome, Terminal $existing): string
    {
        $details = [];

        if ($existing->device_last_seen_at !== null) {
            $details[] = 'último acesso '.$existing->device_last_seen_at->format('d/m/Y H:i:s');
        }

        $ip = trim((string) ($existing->ip ?? ''));
        if ($ip !== '') {
            $details[] = 'IP '.$ip;
        }

        $suffix = $details !== [] ? ' ('.implode(', ', $details).')' : '';

        return 'O número '.$numero.' já está em uso (terminal '.$nome.$suffix.'). '
            .'Exclua o terminal em Configurações → Terminais (F4) para liberar o número, ou escolha outro número. '
            .'Bloquear não libera o número.';
    }

    private function touchPdvOffline(Terminal $terminal, ?string $ip = null, ?string $deviceUuid = null): void
    {
        $origins = collect($terminal->origens_dispositivo ?? [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->push('pdv_offline')
            ->unique()
            ->values()
            ->all();

        $fill = [
            'ip' => $this->preferredLanIp($ip ?? $terminal->ip),
        ];

        if ($this->isAvailable()) {
            $fill['categoria_licenca'] = $terminal->categoria_licenca ?: self::CATEGORY_COMPUTADOR;
            $fill['origens_dispositivo'] = $origins;
            $fill['device_last_seen_at'] = now();
            $fill['device_platform'] = $terminal->device_platform ?: 'pdv-offline';

            $deviceUuid = $deviceUuid !== null ? trim($deviceUuid) : '';
            if ($deviceUuid !== '' && trim((string) ($terminal->device_uuid ?? '')) === '') {
                $this->reclaimDeviceUuid((int) $terminal->empresa_id, $deviceUuid, (int) $terminal->id);
                $fill['device_uuid'] = $deviceUuid;
            }
        }

        $terminal->forceFill($fill)->saveQuietly();
    }

    private function reclaimDeviceUuid(int $empresaId, string $deviceUuid, int $keepTerminalId): void
    {
        $rows = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $deviceUuid)
            ->where('id', '!=', $keepTerminalId)
            ->get();

        foreach ($rows as $orphan) {
            if ($this->isBrowserOrphan($orphan)) {
                $orphan->delete();

                continue;
            }

            $orphan->forceFill([
                'device_uuid' => null,
            ])->saveQuietly();
        }
    }

    private function purgeBrowserOrphans(int $empresaId, int $keepTerminalId, string $deviceUuid): void
    {
        Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('id', '!=', $keepTerminalId)
            ->where(function ($q) use ($deviceUuid): void {
                $q->where('device_uuid', $deviceUuid)
                    ->orWhere('nome', 'like', 'Mozilla/%')
                    ->orWhere('nome', 'like', 'DISPOSITIVO %');
            })
            ->get()
            ->each(function (Terminal $orphan): void {
                if ($this->isBrowserOrphan($orphan)) {
                    $orphan->delete();
                }
            });
    }

    private function isBrowserOrphan(Terminal $terminal): bool
    {
        if ((bool) ($terminal->pdv ?? false) || (bool) ($terminal->eh_caixa ?? false)) {
            return false;
        }

        if (filled($terminal->numero_logico_terminal)) {
            return false;
        }

        $nome = trim((string) $terminal->nome);
        $origins = collect($terminal->origens_dispositivo ?? [])
            ->map(static fn (mixed $v): string => strtolower(trim((string) $v)))
            ->all();

        $looksLikeUa = str_starts_with($nome, 'Mozilla/')
            || str_starts_with($nome, 'DISPOSITIVO ');

        $fromBrowser = in_array('erp_web', $origins, true)
            || in_array('gestor_web', $origins, true);

        return $looksLikeUa && ($fromBrowser || filled($terminal->device_uuid));
    }

    private function preferredLanIp(?string $current): ?string
    {
        $ip = trim((string) (request()?->ip() ?? ''));

        if ($ip === '' || str_starts_with($ip, '127.') || str_starts_with($ip, '::1')) {
            return $current ?: ($ip !== '' ? $ip : null);
        }

        return $ip;
    }

    private function touchDevice(Terminal $terminal, string $origin, ?string $deviceName, ?string $platform): void
    {
        $origins = collect($terminal->origens_dispositivo ?? [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->push($origin)
            ->unique()
            ->values()
            ->all();

        $changed = $origins !== ($terminal->origens_dispositivo ?? []);
        $lastSeenRecently = $terminal->device_last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false;

        if (! $changed && $lastSeenRecently) {
            return;
        }

        $terminal->forceFill([
            'origens_dispositivo' => $origins,
            'device_name' => $this->cleanNullable($deviceName) ?? $terminal->device_name,
            'device_platform' => $this->cleanNullable($platform) ?? $terminal->device_platform,
            'device_last_seen_at' => now(),
            'ip' => $this->preferredLanIp($terminal->ip),
        ])->save();
    }

    private function deviceTerminalName(?string $deviceName, string $uuid): string
    {
        $name = trim((string) $deviceName);

        if ($name !== '') {
            return Str::limit($name, 70, '');
        }

        return 'DISPOSITIVO '.Str::upper(Str::substr(hash('sha256', $uuid), 0, 10));
    }

    private function cleanNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::limit($value, 120, '') : null;
    }
}
