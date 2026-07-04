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

            return $this->touchMachineMetadata($remembered);

        }



        $machineName = $this->resolveMachineName();



        $terminal = Terminal::query()

            ->where('empresa_id', $empresaId)

            ->where('nome', $machineName)

            ->first();



        if ($terminal) {

            return $this->touchMachineMetadata($terminal);

        }



        $terminal = Terminal::query()->firstOrCreate(

            [

                'empresa_id' => $empresaId,

                'nome' => $machineName,

            ],

            [

                ...Terminal::defaultAttributes($empresaId),

                'nome' => $machineName,

                'ip' => $this->resolveClientIp(),

                'velocidade' => 9600,

            ],

        );



        return $this->touchMachineMetadata($terminal);

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


