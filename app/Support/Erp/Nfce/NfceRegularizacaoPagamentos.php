<?php

namespace App\Support\Erp\Nfce;

use App\Support\Erp\ErpMoney;
use App\Support\Fiscal\FormaPagamentoNfceTPagMap;
use DomainException;

/**
 * Resolve meios de pagamento para a Regularização Fiscal.
 *
 * Prioridade: detalhes (pdv_venda_pagamentos / payload FV) → cabeçalho utilizável.
 * "DIVERSOS" e rótulos genéricos do SCV não contam como forma fiscal segura.
 */
final class NfceRegularizacaoPagamentos
{
    /** @var list<string> */
    private const ROTULOS_INUTILIZAVEIS = [
        'DIVERSOS',
        'OUTROS',
        'OUTRO',
        'VARIOS',
        'VÁRIOS',
        'MISTO',
        'MULTIPLOS',
        'MÚLTIPLOS',
    ];

    /**
     * @param  list<array{forma?: mixed, valor?: mixed, descricao?: mixed, forma_pagamento?: mixed}>|null  $fvPagamentos
     * @param  list<array{forma: string, valor: float}>|null  $pdvPagamentos
     * @return list<array{forma: string, valor: float|null}>
     */
    public static function resolverLinhas(
        ?array $pdvPagamentos,
        ?array $fvPagamentos,
        ?string $cabecalho,
    ): array {
        $fromPdv = self::normalizarDetalhes($pdvPagamentos ?? []);
        if ($fromPdv !== []) {
            return $fromPdv;
        }

        $fromFv = self::normalizarDetalhes($fvPagamentos ?? [], ['descricao', 'forma_pagamento', 'forma']);
        if ($fromFv !== []) {
            return $fromFv;
        }

        return self::linhasFromCabecalhoUtilizavel((string) ($cabecalho ?? ''));
    }

    /**
     * Rótulo da coluna Pagamento (mesma ideia da listagem: detalhe > cabeçalho).
     *
     * @param  list<array{forma: string, valor: float|null}>  $linhas
     */
    public static function rotuloGrade(array $linhas): string
    {
        if ($linhas === []) {
            return '—';
        }

        return implode(' / ', array_map(
            static fn (array $l): string => $l['forma'],
            $linhas,
        ));
    }

    /**
     * Tooltip com valores quando existirem.
     *
     * @param  list<array{forma: string, valor: float|null}>  $linhas
     */
    public static function tooltipGrade(array $linhas): string
    {
        if ($linhas === []) {
            return '';
        }

        $temValor = false;
        foreach ($linhas as $linha) {
            if ($linha['valor'] !== null) {
                $temValor = true;
                break;
            }
        }

        $partes = [];
        foreach ($linhas as $linha) {
            if ($temValor && $linha['valor'] !== null) {
                $partes[] = $linha['forma'].' — R$ '.ErpMoney::formatBr((float) $linha['valor']);
            } else {
                $partes[] = $linha['forma'];
            }
        }

        return implode("\n", $partes);
    }

    public static function ehRotuloInutilizavel(string $forma): bool
    {
        $chave = self::normalizarChave($forma);

        return $chave === '' || in_array($chave, self::ROTULOS_INUTILIZAVEIS, true);
    }

    /**
     * Garante linhas seguras para emitir NFC-e. Lança DomainException se não houver.
     *
     * @param  list<array{forma?: mixed, valor?: mixed, descricao?: mixed, forma_pagamento?: mixed}>|null  $fvPagamentos
     * @param  list<array{forma: string, valor: float}>|null  $pdvPagamentos
     * @return list<array{forma: string, valor: float}>
     */
    public static function garantirParaEmissao(
        ?array $pdvPagamentos,
        ?array $fvPagamentos,
        ?string $cabecalho,
        float $totalVenda,
        ?string $numeroVenda = null,
    ): array {
        $linhas = self::resolverLinhas($pdvPagamentos, $fvPagamentos, $cabecalho);
        $ref = $numeroVenda !== null && $numeroVenda !== '' ? ' (venda nº '.$numeroVenda.')' : '';

        if ($linhas === []) {
            throw new DomainException(
                'Não foi possível determinar o meio de pagamento da venda'.$ref
                .'. O cabeçalho está genérico (ex.: DIVERSOS) e não há pagamentos detalhados.'
                .' Corrija os pagamentos da venda antes de emitir a NFC-e.'
            );
        }

        $uteis = array_values(array_filter(
            $linhas,
            static fn (array $l): bool => ! self::ehRotuloInutilizavel($l['forma']),
        ));

        if ($uteis === []) {
            throw new DomainException(
                'Não foi possível determinar o meio de pagamento da venda'.$ref
                .'. O cabeçalho está genérico (ex.: DIVERSOS) e não há pagamentos detalhados.'
                .' Corrija os pagamentos da venda antes de emitir a NFC-e.'
            );
        }

        $comValor = [];
        $semValor = 0;
        foreach ($uteis as $linha) {
            if ($linha['valor'] !== null && $linha['valor'] > 0.009) {
                $comValor[] = [
                    'forma' => $linha['forma'],
                    'valor' => round((float) $linha['valor'], 2),
                ];
            } else {
                $semValor++;
            }
        }

        // Uma forma só no cabeçalho (sem valor): usa o total da venda.
        if ($comValor === [] && count($uteis) === 1 && $semValor === 1 && $totalVenda > 0.009) {
            return [[
                'forma' => $uteis[0]['forma'],
                'valor' => round($totalVenda, 2),
            ]];
        }

        if ($semValor > 0) {
            throw new DomainException(
                'Pagamento misto sem valores detalhados'.$ref
                .'. Não é seguro emitir a NFC-e sem os valores de cada meio.'
            );
        }

        if ($comValor === []) {
            throw new DomainException(
                'Não foi possível determinar valores de pagamento seguros para a NFC-e'.$ref.'.'
            );
        }

        $soma = round(array_sum(array_map(static fn (array $l): float => $l['valor'], $comValor)), 2);
        $total = round($totalVenda, 2);

        if ($total > 0.009 && abs($soma - $total) > 0.02) {
            throw new DomainException(
                'Soma dos pagamentos (R$ '.ErpMoney::formatBr($soma)
                .') diverge do total da venda (R$ '.ErpMoney::formatBr($total).')'.$ref.'.'
            );
        }

        return $comValor;
    }

    /**
     * tPag que seria usado para cada linha (diagnóstico / testes).
     *
     * @param  list<array{forma: string, valor: float}>  $linhas
     * @return list<string>
     */
    public static function tPags(array $linhas): array
    {
        return array_map(
            static fn (array $l): string => FormaPagamentoNfceTPagMap::fromDescricao($l['forma']),
            $linhas,
        );
    }

    /**
     * Cabeçalho resumido a gravar só no registro fiscal novo (não altera vendas).
     *
     * @param  list<array{forma: string, valor: float}>  $linhas
     */
    public static function cabecalhoFromLinhas(array $linhas): string
    {
        return self::rotuloGrade(array_map(
            static fn (array $l): array => ['forma' => $l['forma'], 'valor' => $l['valor']],
            $linhas,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @param  list<string>  $nomeKeys
     * @return list<array{forma: string, valor: float|null}>
     */
    private static function normalizarDetalhes(array $raw, array $nomeKeys = ['forma', 'descricao', 'forma_pagamento']): array
    {
        $linhas = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $nome = '';
            foreach ($nomeKeys as $key) {
                $candidato = trim((string) ($item[$key] ?? ''));
                if ($candidato !== '') {
                    $nome = mb_strtoupper($candidato, 'UTF-8');
                    break;
                }
            }

            if ($nome === '' || self::ehRotuloInutilizavel($nome)) {
                continue;
            }

            $valor = null;
            if (isset($item['valor']) && is_numeric($item['valor'])) {
                $v = round((float) $item['valor'], 2);
                if ($v > 0.009) {
                    $valor = $v;
                } else {
                    continue;
                }
            }

            $linhas[] = [
                'forma' => $nome,
                'valor' => $valor,
            ];
        }

        return $linhas;
    }

    /**
     * @return list<array{forma: string, valor: float|null}>
     */
    private static function linhasFromCabecalhoUtilizavel(string $forma): array
    {
        $forma = trim($forma);
        if ($forma === '') {
            return [];
        }

        // Cabeçalho inteiro genérico ("DIVERSOS") — sem inventar meio.
        if (! str_contains($forma, '/') && ! str_contains($forma, '+') && self::ehRotuloInutilizavel($forma)) {
            return [];
        }

        $partes = preg_split('/\s*(?:\/|\+)\s*/u', $forma) ?: [];
        $linhas = [];

        foreach ($partes as $parte) {
            $nome = mb_strtoupper(trim((string) $parte), 'UTF-8');
            if ($nome === '' || self::ehRotuloInutilizavel($nome)) {
                continue;
            }
            $linhas[] = [
                'forma' => $nome,
                'valor' => null,
            ];
        }

        return $linhas;
    }

    private static function normalizarChave(string $forma): string
    {
        $chave = mb_strtoupper(trim($forma), 'UTF-8');

        return str_replace(
            ['Á', 'À', 'Ã', 'Â', 'É', 'Ê', 'Í', 'Ó', 'Ô', 'Õ', 'Ú', 'Ç'],
            ['A', 'A', 'A', 'A', 'E', 'E', 'I', 'O', 'O', 'O', 'U', 'C'],
            $chave,
        );
    }
}
