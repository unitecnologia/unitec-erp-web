<?php

namespace App\Support\Erp;

use App\Models\ForcaVendasOrder;
use App\Models\OrdemServico;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\Venda;

/**
 * Exibição compacta dos meios de pagamento na listagem de Vendas.
 * Fontes (já carregadas / eager em lote): payload FV, pdv_venda_pagamentos,
 * faturamento_pagamentos da OS e fallback do cabeçalho vendas.forma_pagamento.
 * Sem dependência de Monitor/OS e sem SQL por linha.
 */
final class VendaMeioPagamentoExibicao
{
    /**
     * @return list<array{nome: string, valor: float|null}>
     */
    public static function linhas(Venda $venda): array
    {
        $fromFv = self::linhasFromForcaVendas($venda);
        if ($fromFv !== []) {
            return $fromFv;
        }

        $fromPdv = self::linhasFromPdv($venda);
        if ($fromPdv !== []) {
            return $fromPdv;
        }

        $fromOs = self::linhasFromOrdemServico($venda);
        if ($fromOs !== []) {
            return $fromOs;
        }

        return self::linhasFromCabecalho((string) ($venda->forma_pagamento ?? ''));
    }

    /**
     * Grade: um meio; misto → "Dinheiro +". Tooltip: todos (+ valores se persistidos).
     *
     * @return array{visivel: string, tooltip: string}
     */
    public static function exibicao(Venda $venda): array
    {
        $linhas = self::linhas($venda);

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
    public static function htmlCelula(Venda $venda): string
    {
        $exibicao = self::exibicao($venda);
        $visivel = e($exibicao['visivel']);

        if ($exibicao['tooltip'] === '') {
            return '<span class="erp-vendas-meio-pag">'.$visivel.'</span>';
        }

        return '<span class="erp-vendas-meio-pag" title="'.e($exibicao['tooltip']).'">'.$visivel.'</span>';
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

    /**
     * @return list<array{nome: string, valor: float|null}>
     */
    private static function linhasFromForcaVendas(Venda $venda): array
    {
        if (! $venda->relationLoaded('forcaVendasOrder')) {
            return [];
        }

        $order = $venda->forcaVendasOrder;
        if (! $order instanceof ForcaVendasOrder) {
            return [];
        }

        $payload = is_array($order->payload) ? $order->payload : [];
        $raw = $payload['pagamentos'] ?? null;

        if (! is_array($raw) || $raw === []) {
            return [];
        }

        return self::mapItensPagamento($raw, [
            'descricao',
            'forma_pagamento',
            'forma',
        ]);
    }

    /**
     * @return list<array{nome: string, valor: float|null}>
     */
    private static function linhasFromPdv(Venda $venda): array
    {
        if (! $venda->relationLoaded('pdvVenda')) {
            return [];
        }

        $pdv = $venda->pdvVenda;
        if (! $pdv instanceof PdvVenda || ! $pdv->relationLoaded('pagamentos')) {
            return [];
        }

        $linhas = [];

        foreach ($pdv->pagamentos as $pag) {
            if (! $pag instanceof PdvVendaPagamento) {
                continue;
            }

            $nome = mb_strtoupper(trim((string) ($pag->forma ?? '')), 'UTF-8');
            $valor = null;

            if ($pag->valor !== null && is_numeric($pag->valor)) {
                $v = round((float) $pag->valor, 2);
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
     * @return list<array{nome: string, valor: float|null}>
     */
    private static function linhasFromOrdemServico(Venda $venda): array
    {
        if (! $venda->relationLoaded('ordemServico')) {
            return [];
        }

        $os = $venda->ordemServico;
        if (! $os instanceof OrdemServico) {
            return [];
        }

        $raw = $os->faturamento_pagamentos;
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        return self::mapItensPagamento($raw, ['forma', 'forma_pagamento', 'descricao']);
    }

    /**
     * Cabeçalho legado: "DINHEIRO / PIX" — só nomes, sem inventar valores.
     *
     * @return list<array{nome: string, valor: float|null}>
     */
    private static function linhasFromCabecalho(string $forma): array
    {
        $forma = trim($forma);
        if ($forma === '') {
            return [];
        }

        $partes = preg_split('/\s*(?:\/|\+)\s*/u', $forma) ?: [];
        $linhas = [];

        foreach ($partes as $parte) {
            $nome = mb_strtoupper(trim((string) $parte), 'UTF-8');
            if ($nome === '') {
                continue;
            }
            $linhas[] = [
                'nome' => $nome,
                'valor' => null,
            ];
        }

        return $linhas;
    }

    /**
     * @param  list<mixed>  $raw
     * @param  list<string>  $nomeKeys
     * @return list<array{nome: string, valor: float|null}>
     */
    private static function mapItensPagamento(array $raw, array $nomeKeys): array
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
}
