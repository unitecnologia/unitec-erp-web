<?php

namespace App\Filament\Resources\CaixaResource\Pages\Concerns;

use App\Support\Erp\BrDecimal;
use App\Support\Erp\Financeiro\CaixaLivroMovimentoService;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use App\Support\Erp\Financeiro\CaixaTransferenciaService;
use Filament\Notifications\Notification;
use InvalidArgumentException;
use Livewire\Attributes\Computed;

trait ManagesCaixaTransferenciaModal
{
    public bool $caixaTransferOpen = false;

    /** @var array<string, string> */
    public array $caixaTransferForm = [];

    public string $caixaTransferAlert = '';

    public bool $caixaTransferDeleteOpen = false;

    public ?string $caixaTransferDeleteDocumento = null;

    public function openCaixaTransferencia(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.create')) {
            return;
        }

        $contas = $this->contasTransferenciaOptions;
        if (count($contas) < 2) {
            Notification::make()
                ->title('Transferência indisponível')
                ->body('É necessário ter ao menos duas contas financeiras ativas (exceto PDV operacional).')
                ->warning()
                ->send();

            return;
        }

        $origem = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        if ($origem <= 0 || ! isset($contas[$origem])) {
            $origem = (int) array_key_first($contas);
        }

        $destino = 0;
        foreach ($contas as $id => $_nome) {
            if ((int) $id !== $origem) {
                $destino = (int) $id;
                break;
            }
        }

        $this->caixaTransferForm = [
            'documento' => CaixaTransferencia::novoDocumento(),
            'origem_id' => (string) $origem,
            'destino_id' => (string) $destino,
            'valor' => '0,00',
            'emissao' => now()->format('Y-m-d'),
            'historico' => '',
        ];
        $this->caixaTransferAlert = '';
        $this->caixaTransferOpen = true;
    }

    public function closeCaixaTransferencia(): void
    {
        $this->caixaTransferOpen = false;
        $this->caixaTransferForm = [];
        $this->caixaTransferAlert = '';
    }

    public function confirmarCaixaTransferencia(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.create')) {
            return;
        }

        $origemId = (int) ($this->caixaTransferForm['origem_id'] ?? 0);
        $destinoId = (int) ($this->caixaTransferForm['destino_id'] ?? 0);
        $valor = BrDecimal::parse($this->caixaTransferForm['valor'] ?? 0, 2);
        $emissao = (string) ($this->caixaTransferForm['emissao'] ?? now()->toDateString());
        $historico = trim((string) ($this->caixaTransferForm['historico'] ?? ''));
        $documento = trim((string) ($this->caixaTransferForm['documento'] ?? ''));

        try {
            $movSvc = app(CaixaLivroMovimentoService::class);
            $movSvc->assertPodeOperarConta($origemId);
            $movSvc->assertPodeOperarConta($destinoId);
            $movSvc->assertPodeMutarLancamento($origemId, $emissao);
            $movSvc->assertPodeMutarLancamento($destinoId, $emissao);

            $resultado = app(CaixaTransferenciaService::class)->transferir(
                origemId: $origemId,
                destinoId: $destinoId,
                valor: $valor,
                emissao: $emissao,
                historico: $historico,
                documentoInformado: $documento !== '' ? $documento : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->caixaTransferAlert = $e->getMessage();

            return;
        }

        $this->closeCaixaTransferencia();
        $this->clearListSelection();
        $this->resetTable();

        $titulo = ! empty($resultado['reutilizado'])
            ? 'Transferência já registrada (sem duplicar).'
            : 'Transferência concluída.';

        Notification::make()
            ->title($titulo)
            ->body('Documento '.$resultado['documento'].' — R$ '.number_format((float) $resultado['valor'], 2, ',', '.'))
            ->success()
            ->send();
    }

    #[Computed]
    public function contasTransferenciaOptions(): array
    {
        return app(CaixaTransferenciaService::class)->contasTransferiveis();
    }

    protected function openDeleteTransferenciaPar(string $documento): void
    {
        $this->caixaTransferDeleteDocumento = $documento;
        $this->caixaTransferDeleteOpen = true;
    }

    public function cancelDeleteCaixaTransferencia(): void
    {
        $this->caixaTransferDeleteOpen = false;
        $this->caixaTransferDeleteDocumento = null;
    }

    public function confirmDeleteCaixaTransferencia(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.delete')) {
            return;
        }

        $documento = trim((string) ($this->caixaTransferDeleteDocumento ?? ''));
        $this->cancelDeleteCaixaTransferencia();

        if ($documento === '' || ! CaixaTransferencia::isDocumentoTransferencia($documento)) {
            return;
        }

        $apagados = app(CaixaTransferenciaService::class)->excluirPar($documento);

        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title($apagados > 0 ? 'Transferência estornada (par excluído).' : 'Nenhum lançamento encontrado.')
            ->success()
            ->send();
    }

    protected function mensagemBloqueioTransferenciaEdicao(): string
    {
        return 'Transferências entre contas não podem ser alteradas. Exclua o par completo (origem e destino) e lance novamente, se necessário.';
    }
}
