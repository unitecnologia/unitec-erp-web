<?php

namespace App\Support\Erp\Nfse;

use App\Models\Nfse;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;

final class NfseFromOrdemServico
{
    /**
     * NFS-e nesses status não vale mais: a OS pode gerar outra.
     *
     * @var list<string>
     */
    public const NFSE_SEM_VALIDADE = [Nfse::STATUS_CANCELADA, Nfse::STATUS_REJEITADA, Nfse::STATUS_SUBSTITUIDA];

    public static function estaFechada(OrdemServico $ordem): bool
    {
        return in_array($ordem->situacao, [
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_ENTREGUE,
        ], true);
    }

    public static function podeFaturar(OrdemServico $ordem): bool
    {
        return self::estaFechada($ordem) || $ordem->aguardandoFaturamento();
    }

    public static function motivoBloqueio(OrdemServico $ordem): ?string
    {
        if (! self::podeFaturar($ordem)) {
            if ($ordem->situacao === OrdemServico::SITUACAO_CANCELADA) {
                return 'OS cancelada não gera NFS-e.';
            }

            return 'A NFS-e fica disponível quando a OS estiver aberta para faturamento.';
        }

        if ($ordem->cliente_id === null || $ordem->cliente === null) {
            return 'A OS não tem cliente cadastrado para a NFS-e.';
        }

        $servicos = self::servicos($ordem);

        if ($servicos === []) {
            return 'A OS não tem serviço para a NFS-e.';
        }

        foreach ($servicos as $item) {
            if ($item->product === null || ! $item->product->is_servico) {
                $nome = trim((string) ($item->discriminacao ?: $item->nome ?: 'serviço'));

                return 'O serviço "'.$nome.'" precisa estar ligado a um serviço do cadastro.';
            }
        }

        if (bccomp(self::totalLiquido($ordem), '0', 2) !== 1) {
            return 'Os serviços da OS estão com valor zero.';
        }

        return null;
    }

    /**
     * NFS-e ainda válida (aberta, transmitida ou autorizada) ligada à OS.
     */
    public static function nfseValida(int $ordemId, ?int $empresaId = null, ?int $ignorarNfseId = null): ?Nfse
    {
        return Nfse::query()
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->when($ignorarNfseId !== null, fn ($query) => $query->whereKeyNot($ignorarNfseId))
            ->daOrdemServico($ordemId)
            ->whereNotIn('status', self::NFSE_SEM_VALIDADE)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return list<OrdemServicoItem>
     */
    public static function servicos(OrdemServico $ordem): array
    {
        return $ordem->itens
            ->filter(function (OrdemServicoItem $item): bool {
                if (mb_strtoupper(trim((string) $item->tipo), 'UTF-8') !== 'S') {
                    return false;
                }

                $quantidade = self::quantidade($item);

                return $quantidade !== null && bccomp($quantidade, '0', 3) === 1;
            })
            ->values()
            ->all();
    }

    /**
     * Linhas da NFS-e pelo líquido dos serviços da OS: o total das linhas é sempre o total de serviços gravado na OS.
     * O desconto que falta além dos descontos do item (desconto geral) é rateado pelo valor de cada serviço.
     *
     * @return list<array{item: OrdemServicoItem, quantidade: string, valor: string, desconto: string, acrescimo: string, total: string}>
     */
    public static function linhas(OrdemServico $ordem): array
    {
        $servicos = self::servicos($ordem);

        if ($servicos === []) {
            return [];
        }

        $dados = [];
        $somaBrutoComAcrescimo = '0.00';
        $somaBase = '0.00';

        foreach ($servicos as $item) {
            $quantidade = self::quantidade($item) ?? '0.000';
            $bruto = self::arredondar(bcmul($quantidade, self::decimal($item->preco, 4) ?? '0', 8), 2);
            $acrescimo = self::positivo(self::decimal($item->acrescimo, 2));
            $desconto = self::positivo(self::decimal($item->desconto, 2));
            $brutoComAcrescimo = bcadd($bruto, $acrescimo, 2);
            $base = self::positivo(bcsub($brutoComAcrescimo, $desconto, 2));

            $dados[] = compact('item', 'quantidade', 'acrescimo', 'brutoComAcrescimo', 'base');
            $somaBrutoComAcrescimo = bcadd($somaBrutoComAcrescimo, $brutoComAcrescimo, 2);
            $somaBase = bcadd($somaBase, $base, 2);
        }

        $alvo = self::alvo($ordem, $somaBase, $somaBrutoComAcrescimo);

        // Total gravado acima do líquido dos itens: o desconto do item já está fora do total da OS.
        $campoBase = bccomp($somaBase, $alvo, 2) >= 0 ? 'base' : 'brutoComAcrescimo';
        $somaReferencia = $campoBase === 'base' ? $somaBase : $somaBrutoComAcrescimo;
        $rateios = self::ratear(
            bcsub($somaReferencia, $alvo, 2),
            array_map(fn (array $linha): string => $linha[$campoBase], $dados),
        );

        $linhas = [];

        foreach ($dados as $indice => $linha) {
            $total = bcsub($linha[$campoBase], $rateios[$indice], 2);
            $valor = self::valor($linha['item']) ?? '0.00';
            $brutoNfse = self::arredondar(bcmul($linha['quantidade'], $valor, 8), 2);
            $acrescimo = $linha['acrescimo'];
            $desconto = bcsub(bcadd($brutoNfse, $acrescimo, 2), $total, 2);

            if (bccomp($desconto, '0', 2) === -1) {
                $acrescimo = bcsub($acrescimo, $desconto, 2);
                $desconto = '0.00';
            }

            $linhas[] = [
                'item' => $linha['item'],
                'quantidade' => $linha['quantidade'],
                'valor' => $valor,
                'desconto' => $desconto,
                'acrescimo' => $acrescimo,
                'total' => $total,
            ];
        }

        return $linhas;
    }

    public static function totalLiquido(OrdemServico $ordem): string
    {
        $total = '0.00';

        foreach (self::linhas($ordem) as $linha) {
            $total = bcadd($total, $linha['total'], 2);
        }

        return $total;
    }

    public static function quantidade(OrdemServicoItem $item): ?string
    {
        return self::decimal($item->qtd, 3);
    }

    public static function valor(OrdemServicoItem $item): ?string
    {
        return self::decimal($item->preco, 2);
    }

    public static function descricao(OrdemServicoItem $item): string
    {
        $texto = trim((string) ($item->discriminacao ?: $item->nome ?: $item->product?->descricao ?: ''));

        return $texto;
    }

    /**
     * O app grava em vl_desc_servicos a soma dos descontos dos itens e o ERP grava só o desconto geral;
     * por isso o alvo é o total_servicos gravado, e não itens menos vl_desc_servicos.
     */
    private static function alvo(OrdemServico $ordem, string $somaBase, string $somaBrutoComAcrescimo): string
    {
        $gravado = self::decimal($ordem->total_servicos, 2);

        if ($gravado === null) {
            $gravado = self::positivo(bcsub($somaBase, self::positivo(self::decimal($ordem->vl_desc_servicos, 2)), 2));
        }

        $gravado = self::positivo($gravado);

        return bccomp($gravado, $somaBrutoComAcrescimo, 2) === 1 ? $somaBrutoComAcrescimo : $gravado;
    }

    /**
     * @param  list<string>  $bases
     * @return list<string>
     */
    private static function ratear(string $valor, array $bases): array
    {
        $rateios = array_fill(0, count($bases), '0.00');
        $soma = array_reduce($bases, fn (string $carry, string $base): string => bcadd($carry, $base, 2), '0.00');

        if (bccomp($valor, '0', 2) !== 1 || bccomp($soma, '0', 2) !== 1) {
            return $rateios;
        }

        $restante = $valor;
        $ultimo = null;

        foreach ($bases as $indice => $base) {
            if (bccomp($base, '0', 2) === 1) {
                $ultimo = $indice;
            }
        }

        foreach ($bases as $indice => $base) {
            if (bccomp($base, '0', 2) !== 1) {
                continue;
            }

            $parte = $indice === $ultimo
                ? $restante
                : self::arredondar(bcdiv(bcmul($valor, $base, 8), $soma, 8), 2);
            $parte = bccomp($parte, $base, 2) === 1 ? $base : $parte;
            $parte = bccomp($parte, $restante, 2) === 1 ? $restante : $parte;
            $rateios[$indice] = $parte;
            $restante = bcsub($restante, $parte, 2);
        }

        // Sobra de arredondamento quando o último serviço não comporta o resto.
        foreach ($bases as $indice => $base) {
            if (bccomp($restante, '0', 2) !== 1) {
                break;
            }

            $folga = bcsub($base, $rateios[$indice], 2);

            if (bccomp($folga, '0', 2) !== 1) {
                continue;
            }

            $parte = bccomp($folga, $restante, 2) === 1 ? $restante : $folga;
            $rateios[$indice] = bcadd($rateios[$indice], $parte, 2);
            $restante = bcsub($restante, $parte, 2);
        }

        return $rateios;
    }

    private static function positivo(?string $valor): string
    {
        if ($valor === null || bccomp($valor, '0', 2) !== 1) {
            return '0.00';
        }

        return bcadd($valor, '0', 2);
    }

    private static function arredondar(string $valor, int $scale): string
    {
        $negativo = str_starts_with($valor, '-');
        $absoluto = $negativo ? substr($valor, 1) : $valor;
        $arredondado = bcadd($absoluto, '0.'.str_repeat('0', $scale).'5', $scale);

        return $negativo && bccomp($arredondado, '0', $scale) !== 0 ? '-'.$arredondado : $arredondado;
    }

    private static function decimal(mixed $value, int $scale): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $raw) !== 1) {
            return null;
        }

        return self::arredondar($raw, $scale);
    }
}
