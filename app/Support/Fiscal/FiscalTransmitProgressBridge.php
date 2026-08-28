<?php

namespace App\Support\Fiscal;

use Livewire\Component;

/**
 * Progresso fiscal na UI: sem stream mid-request.
 * Stream Livewire corrompia a resposta final e o overlay de erro/sucesso (cStat)
 * não aparecia — a tela ficava presa em “Enviando à SEFAZ…”.
 *
 * Etapas reais continuam no servidor; a barra usa wire:loading + JS de abertura/fechamento.
 */
final class FiscalTransmitProgressBridge
{
    /**
     * @param  string  $jsSetter  Compatibilidade; não usado.
     * @return callable(int, string): void
     */
    public static function forLivewire(Component $component, string $streamName, string $jsSetter = ''): callable
    {
        return static function (int $step, string $label): void {
            // No-op de propósito: não chamar $component->stream() durante o transmit.
        };
    }
}
