<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns;

use App\Filament\Resources\PersonResource;
use App\Support\Erp\ErpAccess;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;

/**
 * Modal de cliente incompleto para NF-e (mesmo padrão visual da OS).
 */
trait ManagesForcaVendasMonitorNfeClienteIncompleto
{
    public bool $osClienteFiscalIncompletoOpen = false;

    /** @var list<string> */
    public array $osClienteFiscalIncompletoCampos = [];

    #[Locked]
    public string $osClienteFiscalIncompletoNome = '';

    #[Locked]
    public ?int $osClienteFiscalIncompletoId = null;

    #[Locked]
    public string $osClienteFiscalIncompletoDocumento = 'NF-e';

    #[Locked]
    public string $osClienteFiscalIncompletoAtalho = 'Emitir NF-e';

    /** Linhas extras do aviso consolidado em lote (HTML já escapado). */
    /** @var list<string> */
    public array $osClienteFiscalIncompletoLoteLinhas = [];

    public function fecharOsClienteFiscalIncompleto(): void
    {
        $this->osClienteFiscalIncompletoOpen = false;
        $this->osClienteFiscalIncompletoCampos = [];
        $this->osClienteFiscalIncompletoNome = '';
        $this->osClienteFiscalIncompletoId = null;
        $this->osClienteFiscalIncompletoDocumento = 'NF-e';
        $this->osClienteFiscalIncompletoAtalho = 'Emitir NF-e';
        $this->osClienteFiscalIncompletoLoteLinhas = [];
    }

    public function abrirCadastroClienteFiscalIncompleto(): void
    {
        $clienteId = $this->osClienteFiscalIncompletoId;

        $this->fecharOsClienteFiscalIncompleto();

        if ($clienteId === null || $clienteId < 1) {
            Notification::make()->title('Cliente não encontrado para correção.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'pessoas.update')) {
            return;
        }

        $this->redirect(PersonResource::getUrl('edit', ['record' => $clienteId]), navigate: false);
    }

    /**
     * @param  list<array{cliente_id: ?int, nome: string, campos: list<string>}>  $pendencias
     */
    protected function abrirModalClienteNfeIncompleto(array $pendencias, string $mode): void
    {
        if ($pendencias === []) {
            return;
        }

        $lote = $mode === 'lote' || count($pendencias) > 1;
        $primeira = $pendencias[0];

        $this->osClienteFiscalIncompletoOpen = true;
        $this->osClienteFiscalIncompletoDocumento = 'NF-e';
        $this->osClienteFiscalIncompletoAtalho = $lote ? 'Emitir NF-e Lote' : 'Emitir NF-e';
        $this->osClienteFiscalIncompletoId = isset($primeira['cliente_id']) && (int) $primeira['cliente_id'] > 0
            ? (int) $primeira['cliente_id']
            : null;
        $this->osClienteFiscalIncompletoNome = trim((string) ($primeira['nome'] ?? '—')) ?: '—';
        $this->osClienteFiscalIncompletoCampos = array_values($primeira['campos'] ?? []);
        $this->osClienteFiscalIncompletoLoteLinhas = [];

        if (! $lote) {
            return;
        }

        $linhas = [
            'Não é possível emitir o lote enquanto houver cadastros com dados fiscais pendentes.',
        ];

        foreach ($pendencias as $item) {
            $nome = e(trim((string) ($item['nome'] ?? '—')) ?: '—');
            $campos = array_values($item['campos'] ?? []);
            $lista = $campos === [] ? '—' : e(implode(', ', $campos));
            $linhas[] = '<strong>'.$nome.'</strong>: '.$lista.'.';
        }

        $linhas[] = 'Corrija os cadastros e tente novamente pelo Emitir NF-e Lote.';

        $this->osClienteFiscalIncompletoLoteLinhas = $linhas;
        $this->osClienteFiscalIncompletoCampos = [];
    }
}
