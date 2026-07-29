<?php

namespace App\Filament\Resources\NfceResource\Pages\Concerns;

use App\Models\PdvVendaNfce;
use Filament\Notifications\Notification;

trait ManagesNfceBulkTransmit
{
    /** @var array<int, string> */
    public array $nfceSelecionadosTransmitir = [];

    public function toggleNfceTransmitirSelecionado(int $id): void
    {
        $key = (string) $id;

        if (in_array($key, $this->nfceSelecionadosTransmitir, true)) {
            $this->nfceSelecionadosTransmitir = array_values(array_filter(
                $this->nfceSelecionadosTransmitir,
                fn (string $value): bool => $value !== $key,
            ));

            return;
        }

        $this->nfceSelecionadosTransmitir[] = $key;
    }

    public function marcarDesmarcarNfcesContingencia(): void
    {
        $ids = $this->nfceContingenciaIdsNoFiltro();

        if ($ids === []) {
            $this->notifyNfceWarning('Nenhuma NFC-e em contingência no filtro atual.');

            return;
        }

        $marcadosNoFiltro = array_values(array_intersect($this->nfceSelecionadosTransmitir, $ids));
        $todosMarcados = count($marcadosNoFiltro) >= count($ids);

        if ($todosMarcados) {
            $this->nfceSelecionadosTransmitir = array_values(array_diff(
                $this->nfceSelecionadosTransmitir,
                $ids,
            ));

            return;
        }

        $this->nfceSelecionadosTransmitir = array_values(array_unique([
            ...$this->nfceSelecionadosTransmitir,
            ...$ids,
        ]));
    }

    protected function clearNfceTransmitirSelecao(): void
    {
        $this->nfceSelecionadosTransmitir = [];
    }

    /**
     * @return array<int, string>
     */
    protected function nfceContingenciaIdsNoFiltro(): array
    {
        if ($this->statusFilter !== PdvVendaNfce::TAB_CONTINGENCIA) {
            return [];
        }

        return $this->buildListQuery()
            ->where('status', PdvVendaNfce::STATUS_CONTINGENCIA)
            ->where('simulada', false)
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->all();
    }

    /**
     * @return array<int, int>
     */
    protected function resolveNfceIdsParaTransmitir(): array
    {
        $selected = array_values(array_filter(array_map(
            fn ($id): int => (int) $id,
            $this->nfceSelecionadosTransmitir,
        )));

        if ($selected !== []) {
            return $selected;
        }

        return $this->highlightedRecordId ? [(int) $this->highlightedRecordId] : [];
    }

    protected function notifyNfceTransmitirResumo(int $transmitidas, int $erros, ?string $ultimoProtocolo = null): void
    {
        if ($transmitidas > 0 && $erros === 0) {
            $notification = Notification::make()
                ->title($transmitidas === 1
                    ? 'NFC-e transmitida com sucesso.'
                    : "{$transmitidas} NFC-e transmitidas com sucesso.");

            if ($transmitidas === 1 && filled($ultimoProtocolo)) {
                $notification->body('Protocolo: ' . $ultimoProtocolo);
            }

            $notification->success()->send();

            return;
        }

        if ($transmitidas > 0 && $erros > 0) {
            Notification::make()
                ->title("{$transmitidas} transmitida(s), {$erros} com erro.")
                ->warning()
                ->send();

            return;
        }

        $this->notifyNfceWarning('Nenhuma NFC-e foi transmitida.');
    }
}
