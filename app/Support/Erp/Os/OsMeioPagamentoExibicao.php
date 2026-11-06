<?php

namespace App\Support\Erp\Os;

use App\Models\OrdemServico;
use App\Support\Erp\ErpMoney;

/**
 * Exibição compacta dos meios de pagamento da OS (listagens).
 * Fonte: coluna JSON faturamento_pagamentos — sem consultas extras.
 */
final class OsMeioPagamentoExibicao
{
    /**
     * @return list<array{nome: string, valor: float|null}>
     */
    public static function linhas(OrdemServico $ordem): array
    {
        $raw = $ordem->faturamento_pagamentos;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $linhas = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $nome = mb_strtoupper(trim((string) ($item['forma'] ?? '')), 'UTF-8');
            $valor = null;

            if (isset($item['valor']) && is_numeric($item['valor'])) {
                $v = round((float) $item['valor'], 2);
                if ($v > 0.009) {
                    $valor = $v;
                } elseif ($nome === '') {
                    continue;
                }
            } elseif ($nome === '') {
                continue;
            }

            if ($nome === '') {
                continue;
            }

            $linhas[] = [
                'nome' => $nome,
                'valor' => $valor,
            ];
        }

        return $linhas;
    }

    /**
     * Grade: um meio; misto → "Dinheiro +". Tooltip: todos (+ valores se persistidos).
     *
     * @return array{visivel: string, tooltip: string}
     */
    public static function exibicao(OrdemServico $ordem): array
    {
        $linhas = self::linhas($ordem);

        if ($linhas === []) {
            return ['visivel' => '—', 'tooltip' => ''];
        }

        $primeiro = self::rotuloAmigavel($linhas[0]['nome']);
        $misto = count($linhas) > 1;
        $visivel = $misto ? $primeiro.' +' : $primeiro;

        $temValor = false;
        foreach ($linhas as $linha) {
            if ($linha['valor'] !== null) {
                $temValor = true;
                break;
            }
        }

        $tooltipLinhas = [];
        foreach ($linhas as $linha) {
            $nome = mb_strtoupper(trim($linha['nome']), 'UTF-8');
            if ($temValor && $linha['valor'] !== null) {
                $tooltipLinhas[] = $nome.' — R$ '.ErpMoney::formatBr((float) $linha['valor']);
            } else {
                $tooltipLinhas[] = $nome;
            }
        }

        return [
            'visivel' => $visivel,
            'tooltip' => implode("\n", $tooltipLinhas),
        ];
    }

    /**
     * Célula HTML da listagem rápida (title nativo = tooltip).
     */
    public static function htmlCelula(OrdemServico $ordem): string
    {
        $exibicao = self::exibicao($ordem);
        $visivel = e($exibicao['visivel']);

        if ($exibicao['tooltip'] === '') {
            return '<span class="erp-os-meio-pag">'.$visivel.'</span>';
        }

        return '<span class="erp-os-meio-pag" title="'.e($exibicao['tooltip']).'">'.$visivel.'</span>';
    }

    public static function rotuloAmigavel(string $nome): string
    {
        $chave = mb_strtoupper(trim($nome), 'UTF-8');
        $chave = str_replace(
            ['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
            ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
            $chave,
        );

        return match ($chave) {
            'DINHEIRO' => 'Dinheiro',
            'PIX' => 'PIX',
            'POS DEBITO' => 'POS Débito',
            'POS CREDITO' => 'POS Crédito',
            'CREDIARIO' => 'Crediário',
            default => trim($nome) !== '' ? trim($nome) : '—',
        };
    }
}
