<?php

namespace App\Filament\Resources\ForcaVendasMonitorResource\Pages\Concerns;

use App\Filament\Resources\NfeResource;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Nfe\NfeMonitorEmitenteResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait ManagesForcaVendasMonitorNfeEmitenteModal
{
    public bool $nfeEmitenteModalOpen = false;

    public ?int $nfeEmpresaEmitenteId = null;

    /** @var list<int> */
    public array $nfeEmitentePendingVendaIds = [];

    public string $nfeEmitentePendingMode = '';

    /** @var list<array{id: int, nome: string, fantasia: string, cnpj: string, label: string}> */
    public array $nfeEmitenteOpcoes = [];

    /**
     * @param  list<int>  $vendaIds
     */
    public function abrirModalEmpresaEmitenteNfe(array $vendaIds, string $mode): void
    {
        $resolver = app(NfeMonitorEmitenteResolver::class);
        $opcoes = $resolver->opcoesParaUsuario(ErpContext::currentEmpresa(), Auth::user());

        if ($opcoes === []) {
            Notification::make()
                ->title('Nenhuma empresa emitente de NF-e disponível para este usuário.')
                ->warning()
                ->send();

            return;
        }

        $this->nfeEmitentePendingVendaIds = array_values(array_map('intval', $vendaIds));
        $this->nfeEmitentePendingMode = $mode === 'lote' ? 'lote' : 'single';
        $this->nfeEmitenteOpcoes = $opcoes;
        // Empresa logada vem primeiro em opcoesParaUsuario(); default = ela.
        $this->nfeEmpresaEmitenteId = (int) $opcoes[0]['id'];
        $this->nfeEmitenteModalOpen = true;
    }

    public function closeNfeEmitenteModal(): void
    {
        $this->nfeEmitenteModalOpen = false;
        $this->nfeEmpresaEmitenteId = null;
        $this->nfeEmitentePendingVendaIds = [];
        $this->nfeEmitentePendingMode = '';
        $this->nfeEmitenteOpcoes = [];
    }

    public function confirmarEmpresaEmitenteNfe(): void
    {
        $emitenteId = (int) ($this->nfeEmpresaEmitenteId ?? 0);
        $resolver = app(NfeMonitorEmitenteResolver::class);
        $matriz = ErpContext::currentEmpresa();

        if (! $resolver->emitentePermitida($emitenteId, $matriz, Auth::user())) {
            Notification::make()
                ->title('Empresa emitente inválida.')
                ->body('Selecione uma empresa autorizada e acessível para emitir a NF-e.')
                ->warning()
                ->send();

            return;
        }

        $vendaIds = $this->nfeEmitentePendingVendaIds;
        $mode = $this->nfeEmitentePendingMode;
        $matrizId = (int) ($matriz?->id ?? 0);
        // Empresa logada = fluxo normal (sem pin de emitente). Outra autorizada = comportamento atual.
        $emitentePin = ($matrizId > 0 && $emitenteId === $matrizId) ? null : $emitenteId;

        Log::info('monitor.nfe.emitente.selecionada', [
            'matriz_empresa_id' => $matriz?->id,
            'emitente_empresa_id' => $emitenteId,
            'emitente_pin' => $emitentePin,
            'venda_ids' => $vendaIds,
            'mode' => $mode,
            'user_id' => Auth::id(),
        ]);

        if ($mode === 'lote') {
            $this->closeNfeEmitenteModal();
            $this->iniciarNfeLote($vendaIds, $emitentePin);

            return;
        }

        if (count($vendaIds) !== 1) {
            Notification::make()
                ->title('Seleção inválida para emissão de NF-e.')
                ->warning()
                ->send();
            $this->closeNfeEmitenteModal();

            return;
        }

        $vendaId = (int) $vendaIds[0];
        $this->closeNfeEmitenteModal();
        $url = NfeResource::getUrl('index').'?venda_id='.$vendaId;
        if ($emitentePin !== null) {
            $url .= '&empresa_emitente_id='.$emitentePin;
        }
        $this->redirect($url);
    }
}
