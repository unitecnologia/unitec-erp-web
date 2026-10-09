<?php

namespace App\Support\Erp\License;

use App\Models\Empresa;
use App\Models\Terminal;
use App\Support\Erp\Pdv\TerminalResolver;
use Illuminate\Database\QueryException;
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
     * Identifica o terminal do navegador desktop pelo cookie persistente, sem criar terminal.
     * Ordem: vínculo do cookie (inclui reassociação por "Usar este terminal") → adoção única do
     * terminal legado do servidor, só em acesso local. IP nunca identifica PC (DHCP reaproveita e
     * terminais cadastrados pela tela gravam o IP de quem cadastrou).
     */
    public function identifyBrowserDevice(
        int $empresaId,
        string $deviceUuid,
        string $origin,
        ?string $platform = null,
    ): ?Terminal {
        if (! $this->isAvailable()) {
            return null;
        }

        [$deviceUuid, $origin] = $this->normalizeBrowserIdentity($deviceUuid, $origin);
        $resolver = TerminalResolver::make();

        $vinculado = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $deviceUuid)
            ->first();

        if ($vinculado !== null && $resolver->usavelNoNavegador($vinculado)) {
            if (! (bool) ($vinculado->ativo ?? true)) {
                throw new DeviceLicenseLimitExceeded(
                    'Este computador está desativado em Configurações → Terminais. Solicite a liberação ao administrador.'
                );
            }

            $this->touchBrowserBinding($vinculado, $origin, $platform, $resolver);

            return $vinculado;
        }

        return $this->adoptLegacyBrowserTerminal($empresaId, $deviceUuid, $origin, $platform, $resolver);
    }

    /**
     * Usado por register(): identifica e, se o navegador ainda não tem terminal, cria um próprio.
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

        return $this->identifyBrowserDevice($empresaId, $deviceUuid, $origin, $platform)
            ?? $this->createBrowserTerminal($empresaId, $deviceUuid, $origin, $platform);
    }

    /**
     * Terminal ERPn próprio deste navegador (ocupa uma vaga de computador).
     */
    public function createBrowserTerminal(
        int $empresaId,
        string $deviceUuid,
        string $origin = 'erp_web',
        ?string $platform = 'web-desktop',
    ): Terminal {
        [$deviceUuid, $origin] = $this->normalizeBrowserIdentity($deviceUuid, $origin);
        $resolver = TerminalResolver::make();

        $this->assertCapacity($empresaId, self::CATEGORY_COMPUTADOR);
        $this->reclaimDeviceUuid($empresaId, $deviceUuid, 0);

        for ($tentativa = 1; ; $tentativa++) {
            $payload = [
                ...Terminal::defaultAttributes($empresaId),
                'empresa_id' => $empresaId,
                'nome' => $resolver->nextErpTerminalName($empresaId),
                'ip' => $this->browserIp(null, $resolver),
                'velocidade' => 9600,
                'numero_logico_terminal' => $resolver->nextNumeroLogico($empresaId),
                'ativo' => true,
                'categoria_licenca' => self::CATEGORY_COMPUTADOR,
                'origens_dispositivo' => [$origin],
                'device_uuid' => $deviceUuid,
                'device_name' => $resolver->isServerRequest() ? $resolver->resolveMachineName() : null,
                'device_platform' => $this->cleanNullable($platform),
                'device_registered_at' => now(),
                'device_last_seen_at' => now(),
            ];

            try {
                $terminal = new Terminal;
                $terminal->forceFill($payload);
                $terminal->save();

                return $terminal;
            } catch (QueryException $e) {
                // Duas abas criando ao mesmo tempo: o índice único (empresa, device_uuid) segura.
                $existente = Terminal::query()
                    ->where('empresa_id', $empresaId)
                    ->where('device_uuid', $deviceUuid)
                    ->first();

                if ($existente !== null) {
                    return $existente;
                }

                if ($tentativa >= 3) {
                    throw $e;
                }
            }
        }
    }

    private function adoptLegacyBrowserTerminal(
        int $empresaId,
        string $deviceUuid,
        string $origin,
        ?string $platform,
        TerminalResolver $resolver,
    ): ?Terminal {
        $hostname = $resolver->resolveMachineName();

        if ($resolver->isServerRequest()) {
            $servidor = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->where(function ($q) use ($hostname): void {
                    $q->where('nome', $hostname)->orWhere('device_name', $hostname);
                })
                ->orderBy('id')
                ->first();

            if ($servidor === null || ! $resolver->usavelNoNavegador($servidor)) {
                return null;
            }

            $servidor = $resolver->ensureFriendlyWebTerminalName($servidor, $hostname);

            // Já é de outro navegador do próprio servidor: o chamador adota a mesma identidade. Se o vínculo ficou com
            // um PC da rede (modelo antigo usava o hostname do servidor para todos), volta ao servidor.
            if (trim((string) ($servidor->device_uuid ?? '')) !== '' && $resolver->isServerIp($servidor->ip)) {
                return $servidor;
            }

            return $this->bindBrowserTerminal($servidor, $deviceUuid, $origin, $platform, $resolver);
        }

        return $this->findMesmoComputadorNaRede($empresaId, $hostname, $resolver);
    }

    /**
     * Outro navegador do mesmo PC da rede: terminal já vinculado e visto há pouco pela conexão
     * direta deste mesmo IP privado. A janela curta evita confundir com outro PC que herdou o IP
     * pelo DHCP; IP público/túnel nunca agrupa (vários PCs saem pelo mesmo endereço).
     * O chamador faz o navegador adotar o device_uuid do terminal (um PC = um vínculo).
     */
    private function findMesmoComputadorNaRede(int $empresaId, string $hostname, TerminalResolver $resolver): ?Terminal
    {
        $ip = $resolver->directLanIp();

        if ($ip === null) {
            return null;
        }

        return Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('ativo', true)
            ->where('ip', $ip)
            ->whereNotNull('device_uuid')
            ->where('device_last_seen_at', '>=', now()->subHours(8))
            ->orderByDesc('device_last_seen_at')
            ->get()
            ->first(static fn (Terminal $t): bool => $resolver->usavelNoNavegador($t)
                && strcasecmp(trim((string) $t->nome), $hostname) !== 0
                && strcasecmp(trim((string) ($t->device_name ?? '')), $hostname) !== 0);
    }

    /**
     * Reassociação autorizada ("Usar este terminal"): o terminal passa a ser só deste navegador.
     * O antigo dono perde o vínculo (não compartilha); o terminal anterior deste navegador fica
     * livre para ser reassociado. Não cria terminal: a contagem de licença não aumenta, exceto
     * terminal legado fora da licença, que exige vaga.
     */
    public function reassociarNavegador(Terminal $terminal, string $deviceUuid, string $origin = 'erp_web'): Terminal
    {
        [$deviceUuid, $origin] = $this->normalizeBrowserIdentity($deviceUuid, $origin);
        $resolver = TerminalResolver::make();
        $empresaId = (int) $terminal->empresa_id;

        if (strtolower((string) ($terminal->categoria_licenca ?? '')) !== self::CATEGORY_COMPUTADOR) {
            $this->assertCapacity($empresaId, self::CATEGORY_COMPUTADOR);
        }

        $servidor = $resolver->isServerRequest();
        $hostname = $resolver->resolveMachineName();

        $this->reclaimDeviceUuid($empresaId, $deviceUuid, (int) $terminal->id);

        if ($servidor) {
            Terminal::query()
                ->where('empresa_id', $empresaId)
                ->whereKeyNot($terminal->id)
                ->where('device_name', $hostname)
                ->update(['device_name' => null]);
        } elseif (strcasecmp(trim((string) $terminal->nome), $hostname) === 0) {
            $terminal->forceFill(['nome' => $resolver->nextErpTerminalName($empresaId)]);
        }

        $terminal->forceFill([
            'categoria_licenca' => self::CATEGORY_COMPUTADOR,
            'origens_dispositivo' => $this->mergeOrigins($terminal, $origin),
            'device_uuid' => $deviceUuid,
            // Hostname só identifica o servidor: terminal reassociado a um PC da rede deixa de
            // ser "o terminal do servidor" e não é retomado no acesso local.
            'device_name' => $servidor ? $hostname : null,
            'device_platform' => 'web-desktop',
            'device_registered_at' => $terminal->device_registered_at ?? now(),
            'device_last_seen_at' => now(),
            'ip' => $this->browserIp($terminal->ip, $resolver),
        ])->save();

        return $terminal->fresh() ?? $terminal;
    }

    /**
     * Vincula o cookie a um terminal existente. Terminal fora da licença sem vaga não é vinculado.
     */
    private function bindBrowserTerminal(
        Terminal $terminal,
        string $deviceUuid,
        string $origin,
        ?string $platform,
        TerminalResolver $resolver,
    ): ?Terminal {
        $empresaId = (int) $terminal->empresa_id;

        if (strtolower((string) ($terminal->categoria_licenca ?? '')) !== self::CATEGORY_COMPUTADOR) {
            try {
                $this->assertCapacity($empresaId, self::CATEGORY_COMPUTADOR);
            } catch (DeviceLicenseLimitExceeded) {
                return null;
            }
        }

        $this->reclaimDeviceUuid($empresaId, $deviceUuid, (int) $terminal->id);
        $this->purgeBrowserOrphans($empresaId, (int) $terminal->id, $deviceUuid);

        $terminal->forceFill([
            'categoria_licenca' => self::CATEGORY_COMPUTADOR,
            'origens_dispositivo' => $this->mergeOrigins($terminal, $origin),
            'device_uuid' => $deviceUuid,
            'device_name' => $terminal->device_name
                ?: ($resolver->isServerRequest() ? $resolver->resolveMachineName() : null),
            'device_platform' => $this->cleanNullable($platform) ?? $terminal->device_platform,
            'device_registered_at' => $terminal->device_registered_at ?? now(),
            'device_last_seen_at' => now(),
            'ip' => $this->browserIp($terminal->ip, $resolver),
        ])->save();

        return $terminal->fresh() ?? $terminal;
    }

    private function touchBrowserBinding(Terminal $terminal, string $origin, ?string $platform, TerminalResolver $resolver): void
    {
        $origins = $this->mergeOrigins($terminal, $origin);
        $ip = $this->browserIp($terminal->ip, $resolver);
        $recente = $terminal->device_last_seen_at?->greaterThan(now()->subMinutes(4)) ?? false;

        if ($recente && $ip === $terminal->ip && $origins === ($terminal->origens_dispositivo ?? [])) {
            return;
        }

        $terminal->forceFill([
            'origens_dispositivo' => $origins,
            'device_platform' => $this->cleanNullable($platform) ?? $terminal->device_platform,
            'device_last_seen_at' => now(),
            'ip' => $ip,
        ])->save();
    }

    /** IP gravado no terminal: o da rede; no próprio servidor, o IP do servidor (nunca o de outro PC). */
    private function browserIp(?string $current, TerminalResolver $resolver): ?string
    {
        if ($resolver->isServerRequest()) {
            return $resolver->serverIps()[0] ?? '127.0.0.1';
        }

        return $resolver->resolveClientIp() ?? $current;
    }

    /**
     * @return list<string>
     */
    private function mergeOrigins(Terminal $terminal, string $origin): array
    {
        return collect($terminal->origens_dispositivo ?? [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->push($origin)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalizeBrowserIdentity(string $deviceUuid, string $origin): array
    {
        $deviceUuid = trim($deviceUuid);
        $origin = strtolower(trim($origin));

        if ($deviceUuid === '' || ! in_array($origin, ['erp_web', 'gestor_web'], true)) {
            throw new \InvalidArgumentException('Identificação do navegador inválida.');
        }

        return [$deviceUuid, $origin];
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

        // Caixa renomeado no ERP (não é mais "PDVn"): reaproveita em vez de criar outro PDVn.
        $renomeado = $this->findPdvOfflineRenomeado($empresaId, $numero, $deviceUuid);

        if ($renomeado !== null) {
            if (! (bool) ($renomeado->ativo ?? true)) {
                throw new DeviceLicenseLimitExceeded(
                    'Terminal "'.$renomeado->nome.'" está inativo. Ative-o em Configurações → Terminais ou exclua (F4) para liberar o número.'
                );
            }

            $ownedUuid = trim((string) ($renomeado->device_uuid ?? ''));

            if ($ownedUuid !== '' && $deviceUuid !== '' && ! $this->deviceUuidEquals($ownedUuid, $deviceUuid)) {
                throw new DeviceLicenseLimitExceeded(
                    $this->pdvOfflineConflictMessage($numero, (string) $renomeado->nome, $renomeado)
                );
            }

            $this->touchPdvOffline($renomeado, $ip, $deviceUuid !== '' ? $deviceUuid : null);

            return $renomeado->fresh() ?? $renomeado;
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

        $nome = $this->deviceTerminalName($deviceName, $deviceUuid);

        // Reinstalação do app: novo device_uuid, mesmo nome (unique empresa_id+nome).
        $byName = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('nome', $nome)
            ->when(
                Schema::hasColumn('terminais', 'categoria_licenca'),
                static fn ($q) => $q->where('categoria_licenca', $category)
            )
            ->first();

        if ($byName !== null) {
            if (! $byName->ativo) {
                throw new DeviceLicenseLimitExceeded(
                    'Este dispositivo está desativado em Configurações → Terminais. Solicite a liberação ao administrador.'
                );
            }

            $byName->forceFill([
                'device_uuid' => $deviceUuid,
                'categoria_licenca' => $category,
                'device_registered_at' => $byName->device_registered_at ?? now(),
            ])->save();
            $this->touchDevice($byName, $origin, $deviceName, $platform);

            return $byName->fresh() ?? $byName;
        }

        $limit = $this->limitFor($empresaId, $category);

        $this->assertCapacity($empresaId, $category, $limit);

        $terminal = new Terminal;
        $terminal->forceFill([
            'empresa_id' => $empresaId,
            'nome' => $nome,
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

    /**
     * PDV offline cujo nome foi alterado no ERP. Só considera terminais que já sincronizaram
     * como pdv_offline e cujo nome não é mais PDVn (um PDVm com outro número é outro caixa).
     * Prioridade: mesmo device_uuid; depois mesmo nº lógico.
     */
    private function findPdvOfflineRenomeado(int $empresaId, int $numero, string $deviceUuid): ?Terminal
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $candidatos = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('origens_dispositivo', 'like', '%pdv_offline%')
            ->where(function ($q): void {
                $q->whereNull('categoria_licenca')->orWhere('categoria_licenca', '!=', self::CATEGORY_TELEFONE);
            })
            ->where(function ($q) use ($numero, $deviceUuid): void {
                $q->where('numero_logico_terminal', $numero);

                if ($deviceUuid !== '') {
                    $q->orWhere('device_uuid', $deviceUuid);
                }
            })
            ->orderBy('id')
            ->get()
            ->reject(static fn (Terminal $t): bool => preg_match('/^(PDV|ERP)\s*\d+$/i', trim((string) $t->nome)) === 1)
            ->values();

        if ($candidatos->isEmpty()) {
            return null;
        }

        if ($deviceUuid !== '') {
            $porUuid = $candidatos->first(
                fn (Terminal $t): bool => $this->deviceUuidEquals((string) ($t->device_uuid ?? ''), $deviceUuid)
            );

            if ($porUuid !== null) {
                return $porUuid;
            }
        }

        return $candidatos->first(
            static fn (Terminal $t): bool => (int) $t->numero_logico_terminal === $numero
        );
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
