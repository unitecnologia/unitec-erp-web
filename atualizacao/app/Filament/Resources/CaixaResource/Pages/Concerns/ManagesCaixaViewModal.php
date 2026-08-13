<?php

namespace App\Filament\Resources\CaixaResource\Pages\Concerns;

use App\Models\CaixaLancamento;
use Filament\Notifications\Notification;

trait ManagesCaixaViewModal
{
    public bool $viewModalOpen = false;

    /** @var array<string, string> */
    public array $viewModalData = [];

    public function openCaixaView(int $lancamentoId): void
    {
        $lancamento = CaixaLancamento::query()
            ->with(['conta'])
            ->find($lancamentoId);

        if (! $lancamento) {
            Notification::make()
                ->title('Lançamento não encontrado.')
                ->danger()
                ->send();

            return;
        }

        $this->viewModalData = [
            'codigo' => (string) $lancamento->codigo,
            'emissao' => $lancamento->emissao?->format('d/m/Y') ?? '—',
            'documento' => $lancamento->documento ?: '—',
            'historico' => mb_strtoupper($lancamento->historico, 'UTF-8'),
            'plano_contas' => mb_strtoupper((string) ($lancamento->plano_contas ?? ''), 'UTF-8') ?: '—',
            'conta' => mb_strtoupper($lancamento->conta?->nome ?? '—', 'UTF-8'),
            'entrada' => number_format((float) $lancamento->entrada, 2, ',', '.'),
            'saida' => number_format((float) $lancamento->saida, 2, ',', '.'),
        ];

        $this->viewModalOpen = true;
    }

    public function closeCaixaView(): void
    {
        $this->viewModalOpen = false;
        $this->viewModalData = [];
    }
}
