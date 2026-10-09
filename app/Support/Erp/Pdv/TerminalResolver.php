<?php

namespace App\Support\Erp\Pdv;

use App\Http\Middleware\EnsureBrowserDeviceCookie;
use App\Models\Terminal;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\License\DeviceLicenseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

final class TerminalResolver
{
    private static ?string $ultimoErro = null;

    /** @var list<string>|null */
    private static ?array $ipsServidor = null;

    public static function make(): self
    {
        return new self;
    }

    public function resolveEmpresaId(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return filled($empresaId) ? (int) $empresaId : null;
    }

    /**
     * Nome da estação — equivalente a Dados.GetComputer no Delphi (hostname do servidor PHP).
     * Só identifica o PC quando o navegador roda no próprio servidor.
     */
    public function resolveMachineName(): string
    {
        $nome = mb_strtoupper(trim((string) gethostname()), 'UTF-8');

        return $nome !== '' ? $nome : 'CAIXA-1';
    }

    public function resolveClientIp(): ?string
    {
        $ip = request()->ip();

        return filled($ip) ? (string) $ip : null;
    }

    /** Cookie persistente do navegador (erp_device_id). */
    public function deviceUuid(): ?string
    {
        $uuid = trim((string) (request()?->cookie(EnsureBrowserDeviceCookie::COOKIE) ?? ''));

        return $uuid !== '' ? $uuid : null;
    }

    /** Navegador aberto no próprio servidor do ERP (127.0.0.1 / ::1 / IP do servidor). */
    public function isServerRequest(): bool
    {
        $request = request();

        if ($request === null) {
            return false;
        }

        // Endereço real da conexão: trustProxies('*') faria request()->ip() aceitar X-Forwarded-For forjado.
        if (! $this->isServerIp((string) $request->server('REMOTE_ADDR', ''))) {
            return false;
        }

        // Proxy/túnel local repassando outro PC: não é o navegador do servidor.
        foreach (array_filter(array_map('trim', explode(',', (string) $request->headers->get('X-Forwarded-For', '')))) as $ip) {
            if (! $this->isServerIp($ip)) {
                return false;
            }
        }

        return true;
    }

    /**
     * IP privado da conexão direta (sem proxy). X-Forwarded-For é ignorado de propósito:
     * com trustProxies('*') ele seria forjável e permitiria assumir a identidade de outro PC.
     */
    public function directLanIp(): ?string
    {
        $request = request();

        if ($request === null || trim((string) $request->headers->get('X-Forwarded-For', '')) !== '') {
            return null;
        }

        $ip = trim((string) $request->server('REMOTE_ADDR', ''));

        if ($ip === '' || $this->isServerIp($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $publico = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

        return $publico ? null : $ip;
    }

    public function isServerIp(?string $ip): bool
    {
        $ip = trim((string) $ip);

        if ($ip === '') {
            return false;
        }

        if (str_starts_with($ip, '127.') || $ip === '::1') {
            return true;
        }

        return in_array($ip, $this->serverIps(), true);
    }

    /** @return list<string> */
    public function serverIps(): array
    {
        if (self::$ipsServidor === null) {
            $ips = @gethostbynamel((string) gethostname());
            self::$ipsServidor = array_values(array_filter(
                is_array($ips) ? $ips : [],
                static fn (string $ip): bool => ! str_starts_with($ip, '127.'),
            ));
        }

        return self::$ipsServidor;
    }

    public function current(): ?Terminal
    {
        $terminal = $this->findRememberedTerminal();

        if ($terminal) {
            return $terminal;
        }

        $empresaId = $this->resolveEmpresaId();

        if (! $empresaId) {
            return null;
        }

        if ($this->deviceUuid() !== null) {
            // Navegador identificado sem terminal: nunca usar o caixa/série de outro PC.
            $terminal = $this->findBrowserTerminal($empresaId);

            if ($terminal) {
                $this->remember($terminal);
            }

            return $terminal;
        }

        return Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('pdv', true)
            ->where('nome', '!=', '')
            ->orderBy('id')
            ->first();
    }

    public function remember(Terminal $terminal): void
    {
        session([
            'erp.terminal_id' => $terminal->id,
            'erp.terminal_nome' => $terminal->nome,
        ]);
    }

    public function forget(): void
    {
        session()->forget(['erp.terminal_id', 'erp.terminal_nome']);
    }

    /**
     * Terminal vinculado a este navegador (cookie erp_device_id), sem criar nada.
     */
    public function findBrowserTerminal(int $empresaId): ?Terminal
    {
        $uuid = $this->deviceUuid();

        if ($uuid === null || ! Schema::hasColumn('terminais', 'device_uuid')) {
            return null;
        }

        $vinculado = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('device_uuid', $uuid)
            ->first();

        return $vinculado !== null && $this->usavelNoNavegador($vinculado) ? $vinculado : null;
    }

    /** PDV offline e aparelhos móveis nunca são o terminal de um navegador desktop. */
    public function usavelNoNavegador(Terminal $terminal): bool
    {
        return ! $terminal->ehPdvOffline() && ! $terminal->ehAparelhoMovel();
    }

    public static function ultimoErro(): ?string
    {
        return self::$ultimoErro;
    }

    /**
     * Terminal desta estação para PDV/caixa. Navegador sem terminal ganha um ERPn próprio
     * (só se houver vaga de computador); sem cookie (console) mantém o legado por hostname.
     *
     * @param  bool  $touch  false = não grava na sessão.
     */
    public function resolveOrCreateDefault(?int $empresaId = null, bool $touch = true): ?Terminal
    {
        self::$ultimoErro = null;
        $empresaId ??= $this->resolveEmpresaId();

        if (! $empresaId) {
            return null;
        }

        $terminal = $this->findRememberedTerminal($empresaId);
        $uuid = $this->deviceUuid();

        if ($terminal === null && $uuid !== null) {
            $terminal = $this->findBrowserTerminal($empresaId);

            if ($terminal === null && Schema::hasColumn('terminais', 'device_uuid')) {
                try {
                    $terminal = app(DeviceLicenseService::class)->createBrowserTerminal($empresaId, $uuid);
                } catch (DeviceLicenseLimitExceeded $e) {
                    self::$ultimoErro = $e->getMessage();

                    return null;
                }
            }
        }

        $terminal ??= $this->locateLegacyMachineTerminal($empresaId);

        if ($terminal !== null && $touch && (int) session('erp.terminal_id', 0) !== (int) $terminal->id) {
            $this->remember($terminal);
        }

        return $terminal;
    }

    /** Legado sem cookie de navegador (console/rotinas): Locate NOME = GetComputer. */
    private function locateLegacyMachineTerminal(int $empresaId): ?Terminal
    {
        $machineName = $this->resolveMachineName();

        $terminal = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('nome', $machineName)
            ->first();

        if ($terminal) {
            return $this->ensureFriendlyWebTerminalName($terminal, $machineName);
        }

        if (Schema::hasColumn('terminais', 'device_name')) {
            $terminal = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->where('device_name', $machineName)
                ->first();

            if ($terminal) {
                return $terminal;
            }
        }

        $attrs = [
            ...Terminal::defaultAttributes($empresaId),
            'nome' => $this->nextErpTerminalName($empresaId),
            'ip' => $this->resolveClientIp(),
            'velocidade' => 9600,
            'numero_logico_terminal' => $this->nextNumeroLogico($empresaId),
            'ativo' => true,
        ];

        if (Schema::hasColumn('terminais', 'device_name')) {
            $attrs['device_name'] = $machineName;
        }

        if (Schema::hasColumn('terminais', 'categoria_licenca')) {
            $attrs['categoria_licenca'] = 'computador';
            $attrs['origens_dispositivo'] = ['erp_web'];
        }

        return Terminal::query()->create($attrs);
    }

    public function nextNumeroLogico(int $empresaId): int
    {
        return (int) (Terminal::query()
            ->where('empresa_id', $empresaId)
            ->max('numero_logico_terminal') ?? 0) + 1;
    }

    /**
     * Próximo nome amigável ERP1, ERP2… (não usa hostname do Windows).
     */
    public function nextErpTerminalName(int $empresaId): string
    {
        $max = 0;

        foreach (
            Terminal::query()
                ->where('empresa_id', $empresaId)
                ->where('nome', 'like', 'ERP%')
                ->pluck('nome') as $nome
        ) {
            if (preg_match('/^ERP(\d+)$/i', trim((string) $nome), $m) === 1) {
                $max = max($max, (int) $m[1]);
            }
        }

        return 'ERP'.($max + 1);
    }

    /**
     * Se o terminal ainda está com nome de PC (DESKTOP-…), troca para ERP1/ERP2
     * e guarda o hostname em device_name.
     */
    public function ensureFriendlyWebTerminalName(Terminal $terminal, ?string $machineName = null): Terminal
    {
        $machineName = $machineName ?: $this->resolveMachineName();
        $nome = trim((string) $terminal->nome);

        $isHostnameStyle = strtoupper($nome) === strtoupper($machineName)
            || str_starts_with(strtoupper($nome), 'DESKTOP-')
            || str_starts_with(strtoupper($nome), 'NOTEBOOK-')
            || str_starts_with(strtoupper($nome), 'WIN-');

        if (! $isHostnameStyle) {
            return $terminal;
        }

        $fill = ['nome' => $this->nextErpTerminalName((int) $terminal->empresa_id)];

        if (Schema::hasColumn('terminais', 'device_name')) {
            $fill['device_name'] = $terminal->device_name ?: $machineName;
        }

        $terminal->forceFill($fill)->saveQuietly();

        return $terminal->fresh() ?? $terminal;
    }

    public function touchMachineMetadata(Terminal $terminal): Terminal
    {
        $ip = $this->resolveClientIp();

        if ($ip !== null && $terminal->ip !== $ip) {
            $terminal->forceFill(['ip' => $ip])->saveQuietly();
            $terminal = $terminal->fresh() ?? $terminal;
        }

        $this->remember($terminal);

        return $terminal;
    }

    protected function findRememberedTerminal(?int $empresaId = null): ?Terminal
    {
        $empresaId ??= $this->resolveEmpresaId();

        if (! $empresaId) {
            return null;
        }

        $terminal = null;
        $terminalId = session('erp.terminal_id');

        if (filled($terminalId)) {
            $terminal = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->find((int) $terminalId);
        }

        $nome = session('erp.terminal_nome');

        if ($terminal === null && filled($nome)) {
            $terminal = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->where('nome', mb_strtoupper(trim((string) $nome), 'UTF-8'))
                ->first();
        }

        if ($terminal !== null && $this->deviceUuid() !== null && ! $this->pertenceAEsteNavegador($terminal)) {
            $this->forget();

            return null;
        }

        return $terminal;
    }

    /**
     * Terminal da sessão continua valendo só se ainda for deste navegador: reassociado a outro PC,
     * PDV offline ou aparelho móvel deixam de valer na hora (sem consulta extra).
     * Exceção: navegadores do próprio servidor compartilham o terminal do servidor.
     */
    private function pertenceAEsteNavegador(Terminal $terminal): bool
    {
        if (! $this->usavelNoNavegador($terminal)) {
            return false;
        }

        if (! array_key_exists('device_uuid', $terminal->getAttributes())) {
            return true;
        }

        $dono = trim((string) ($terminal->device_uuid ?? ''));

        if ($dono !== '' && strcasecmp($dono, (string) $this->deviceUuid()) === 0) {
            return true;
        }

        return $this->isServerRequest() && $this->isServerIp($terminal->ip);
    }
}
