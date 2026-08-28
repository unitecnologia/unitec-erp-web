<?php



namespace App\Support\Erp\Pdv;



use App\Models\Terminal;

use Illuminate\Support\Facades\Auth;



final class TerminalResolver

{

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

     */

    public function resolveMachineName(): string

    {

        $hostname = trim((string) gethostname());

        $nome = mb_strtoupper($hostname, 'UTF-8');



        if ($nome === '') {

            return 'CAIXA-1';

        }



        return $nome;

    }



    public function resolveClientIp(): ?string

    {

        $ip = request()->ip();



        return filled($ip) ? (string) $ip : null;

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

     * Localiza ou cria o terminal desta estação (Delphi: Locate NOME = GetComputer).

     */

    public function resolveOrCreateDefault(?int $empresaId = null): ?Terminal

    {

        $empresaId ??= $this->resolveEmpresaId();



        if (! $empresaId) {

            return null;

        }



        $remembered = $this->findRememberedTerminal($empresaId);

        if ($remembered) {
            return $this->touchMachineMetadata(
                $this->ensureFriendlyWebTerminalName($remembered, $this->resolveMachineName())
            );
        }

        $machineName = $this->resolveMachineName();

        // Legado: terminal criado com hostname do PC.
        $terminal = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where('nome', $machineName)
            ->first();

        if ($terminal) {
            return $this->touchMachineMetadata($this->ensureFriendlyWebTerminalName($terminal, $machineName));
        }

        // Após renomear para ERP1, o hostname fica em device_name.
        if (\Illuminate\Support\Facades\Schema::hasColumn('terminais', 'device_name')) {
            $terminal = Terminal::query()
                ->where('empresa_id', $empresaId)
                ->where('device_name', $machineName)
                ->first();

            if ($terminal) {
                return $this->touchMachineMetadata($terminal);
            }
        }

        $nextNumero = (int) (Terminal::query()
            ->where('empresa_id', $empresaId)
            ->max('numero_logico_terminal') ?? 0) + 1;

        $friendlyName = $this->nextErpTerminalName($empresaId);

        $attrs = [
            ...Terminal::defaultAttributes($empresaId),
            'nome' => $friendlyName,
            'ip' => $this->resolveClientIp(),
            'velocidade' => 9600,
            'numero_logico_terminal' => $nextNumero,
            'ativo' => true,
        ];

        if (\Illuminate\Support\Facades\Schema::hasColumn('terminais', 'device_name')) {
            $attrs['device_name'] = $machineName;
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('terminais', 'categoria_licenca')) {
            $attrs['categoria_licenca'] = 'computador';
            $attrs['origens_dispositivo'] = ['erp_web'];
        }

        $terminal = Terminal::query()->create($attrs);

        return $this->touchMachineMetadata($terminal);
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

        $friendly = $this->nextErpTerminalName((int) $terminal->empresa_id);
        $fill = ['nome' => $friendly];

        if (\Illuminate\Support\Facades\Schema::hasColumn('terminais', 'device_name')) {
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



        $terminalId = session('erp.terminal_id');



        if (filled($terminalId)) {

            $terminal = Terminal::query()

                ->where('empresa_id', $empresaId)

                ->find((int) $terminalId);



            if ($terminal) {

                return $terminal;

            }

        }



        $nome = session('erp.terminal_nome');



        if (filled($nome)) {

            $terminal = Terminal::query()

                ->where('empresa_id', $empresaId)

                ->where('nome', mb_strtoupper(trim((string) $nome), 'UTF-8'))

                ->first();



            if ($terminal) {

                return $terminal;

            }

        }



        return null;

    }

}


