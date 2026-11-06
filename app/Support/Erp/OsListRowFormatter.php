<?php

namespace App\Support\Erp;

use App\Models\OrdemServico;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Support\Erp\Nfe\NfeClienteCadastro;
use App\Support\Erp\Nfse\NfseTomadorCadastro;
use App\Support\Erp\Os\OsMeioPagamentoExibicao;
use Illuminate\Support\Carbon;

final class OsListRowFormatter
{
    /**
     * @return array<string, string>
     */
    public function format(OrdemServico $record): array
    {
        return [
            'numero' => e($this->formatNumero($record)),
            'nfse' => e($record->nfseNumeroLista()),
            'nfe' => e($record->nfeNumeroLista()),
            'nfce' => e($record->nfceNumeroLista()),
            'data' => e($this->formatData($record->data_inicio)),
            'hora_abertura' => e($record->horaInicioExibicao() ?? '—'),
            'hora_fechamento' => e($record->horaTerminoExibicao() ?? '—'),
            'cliente' => e(mb_strtoupper($record->clienteNome(), 'UTF-8')),
            'atendente' => e($record->atendente?->nome ?? '—'),
            'equipamento' => e($this->limit((string) ($record->descricao ?? ''), 40) ?: '—'),
            'placa' => e((string) ($record->placa ?: '—')),
            'meio_pagamento' => OsMeioPagamentoExibicao::htmlCelula($record),
            'envio' => $this->formatEnvio($record),
            'situacao' => $this->formatSituacao($record),
            'desconto' => $this->formatMoneyCell($record->descontoTotalLista()),
            'total' => $this->formatMoneyCell((float) ($record->total_geral ?? 0), emptyAsDash: false),
            'espelho' => $this->formatEspelhoButton((int) $record->getKey()),
        ];
    }

    /**
     * Classes de linha usadas pelo erp-list.js para habilitar/desabilitar botões.
     *
     * @return list<string>
     */
    public function rowClasses(OrdemServico $record): array
    {
        if (! $this->osFaturada($record)) {
            return [];
        }

        $classes = ['erp-os-row--faturada'];

        if (! $record->possuiNfseAutorizada()) {
            $classes[] = 'erp-os-row--nfse-pendente';

            if (NfseTomadorCadastro::camposFaltantesDoCliente($record->cliente) !== []) {
                $classes[] = 'erp-os-row--nfse-cliente-incompleto';
            }
        }

        if ($this->osListaIndicaPecas($record)) {
            if (! $record->possuiNfePecasEmitida() && ! $record->possuiNfcePecasDocumentada()) {
                $classes[] = 'erp-os-row--nfe-pendente';

                if (NfeClienteCadastro::camposFaltantesDoCliente($record->cliente) !== []) {
                    $classes[] = 'erp-os-row--nfe-cliente-incompleto';
                }
            }

            if (
                ! $record->possuiNfePecasEmitida()
                && ! $record->possuiNfceAutorizada()
                && ! $this->osClienteTemCnpj($record->cliente)
            ) {
                $nfce = $record->nfcePecasEmitida;
                $denegada = $nfce !== null && (string) $nfce->status === PdvVendaNfce::STATUS_DENEGADA;

                if (! $denegada) {
                    $classes[] = 'erp-os-row--nfce-pendente';
                }
            }
        }

        return $classes;
    }

    /**
     * Meta leve para a barra de ações (sem SQL no render do pai).
     *
     * @return array{
     *     faturada: bool,
     *     nfse_pendente: bool,
     *     nfse_cliente_incompleto: bool,
     *     nfe_pendente: bool,
     *     nfe_cliente_incompleto: bool,
     *     nfce_pendente: bool
     * }
     */
    public function selectionMeta(OrdemServico $record): array
    {
        $classes = $this->rowClasses($record);

        return [
            'faturada' => in_array('erp-os-row--faturada', $classes, true),
            'nfse_pendente' => in_array('erp-os-row--nfse-pendente', $classes, true),
            'nfse_cliente_incompleto' => in_array('erp-os-row--nfse-cliente-incompleto', $classes, true),
            'nfe_pendente' => in_array('erp-os-row--nfe-pendente', $classes, true),
            'nfe_cliente_incompleto' => in_array('erp-os-row--nfe-cliente-incompleto', $classes, true),
            'nfce_pendente' => in_array('erp-os-row--nfce-pendente', $classes, true),
        ];
    }

    private function osFaturada(OrdemServico $ordem): bool
    {
        return in_array($ordem->situacao, [OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE], true);
    }

    private function osListaIndicaPecas(OrdemServico $ordem): bool
    {
        return (float) ($ordem->subtotal_pecas ?? 0) > 0.0001
            || (float) ($ordem->total_produtos ?? 0) > 0.0001;
    }

    private function osClienteTemCnpj(?Person $cliente): bool
    {
        if ($cliente === null) {
            return false;
        }

        $digits = preg_replace('/\D/', '', (string) ($cliente->cpf_cnpj ?? '')) ?? '';

        return strlen($digits) === 14;
    }

    private function formatNumero(OrdemServico $record): string
    {
        $state = $record->numero;

        if (filled($state)) {
            $digits = (int) preg_replace('/\D/', '', (string) $state);

            return $digits > 0 ? (string) $digits : (string) $state;
        }

        return $record->codigo_legado ? (string) $record->codigo_legado : '—';
    }

    private function formatData(mixed $state): string
    {
        if ($state === null || $state === '') {
            return '—';
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return '—';
        }
    }

    private function limit(string $value, int $max): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value, 'UTF-8') <= $max) {
            return $value;
        }

        return mb_substr($value, 0, $max, 'UTF-8').'…';
    }

    private function formatEnvio(OrdemServico $record): string
    {
        $label = $record->envioListaLabel();

        if ($label === '—') {
            return '<span class="erp-os-envio erp-os-envio--vazio">—</span>';
        }

        if ($label === 'E/W') {
            return '<span class="erp-os-envio" title="Enviado por e-mail e WhatsApp">'
                .'<span class="erp-os-envio--email">E</span>/<span class="erp-os-envio--whats">W</span>'
                .'</span>';
        }

        if ($label === 'E') {
            return '<span class="erp-os-envio erp-os-envio--email" title="Enviado por e-mail">E</span>';
        }

        return '<span class="erp-os-envio erp-os-envio--whats" title="Enviado por WhatsApp">W</span>';
    }

    private function formatSituacao(OrdemServico $record): string
    {
        $situacao = (string) ($record->situacao ?? '');
        $label = e(mb_strtoupper($record->situacaoLabel(), 'UTF-8'));
        $chip = match ($situacao) {
            OrdemServico::SITUACAO_ABERTA => 'aberta',
            OrdemServico::SITUACAO_ANDAMENTO => 'andamento',
            OrdemServico::SITUACAO_FINALIZADA => 'finalizada',
            OrdemServico::SITUACAO_ENTREGUE => 'entregue',
            OrdemServico::SITUACAO_CANCELADA => 'cancelada',
            default => 'aberta',
        };

        $html = '<span class="erp-os__status-chip erp-os__status-chip--'.$chip.'">'.$label.'</span>';

        if ($record->aguardandoFaturamento()) {
            $html .= ' <span class="erp-os__status-chip erp-os__status-chip--faturar">Faturar</span>';
        }

        return $html;
    }

    private function formatMoneyCell(float $valor, bool $emptyAsDash = true): string
    {
        if ($emptyAsDash && $valor <= 0) {
            return '<span class="erp-orc-total-cell erp-orc-total-cell--vazio">—</span>';
        }

        $formatted = number_format($valor, 2, ',', '.');

        return '<span class="erp-orc-total-cell">'
            .'<span class="erp-orc-total-cell__currency">R$</span>'
            .'<span class="erp-orc-total-cell__amount" title="R$ '.e($formatted).'">'.e($formatted).'</span>'
            .'</span>';
    }

    private function formatEspelhoButton(int $ordemId): string
    {
        return '<span role="button" tabindex="0"'
            .' wire:click.stop="$dispatch(\'erp-os-open-espelho\', { ordemId: '.$ordemId.' })"'
            .' wire:keydown.enter.stop="$dispatch(\'erp-os-open-espelho\', { ordemId: '.$ordemId.' })"'
            .' class="erp-orcamentos__eye-btn" title="Espelho da OS" aria-label="Espelho da OS">'
            .'<svg class="erp-orcamentos__eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"'
            .' stroke-width="1.75" stroke="currentColor" aria-hidden="true">'
            .'<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/>'
            .'<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>'
            .'</svg></span>';
    }
}
