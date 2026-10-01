<?php

namespace App\Support\Erp\Nfse;

use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;

final class NfseFromOrdemServico
{
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

        return null;
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

        return bcadd($raw, '0', $scale);
    }
}
