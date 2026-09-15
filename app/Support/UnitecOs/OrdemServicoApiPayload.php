<?php

namespace App\Support\UnitecOs;

use App\Models\OrdemServico;
use App\Models\ProductImei;

/**
 * Payload leve para o app Unitec OS.
 */
final class OrdemServicoApiPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function from(OrdemServico $os): array
    {
        $os->loadMissing(['atendente', 'cliente', 'itens.product']);

        $data = $os->data_inicio?->format('d/m/Y');
        $hora = $os->horaInicioExibicao();
        $dataHora = trim(($data ?? '').' '.($hora ?? ''));

        $imeis = self::primeiroImeiPorProduto($os);

        $pecas = $os->itens
            ->filter(static fn ($item): bool => ($item->tipo ?? 'P') === 'P')
            ->map(static function ($item) use ($imeis): array {
                $descricao = mb_strtoupper(trim((string) ($item->discriminacao ?: $item->nome ?: '')), 'UTF-8');
                $ean = trim((string) ($item->product?->codigo_barras ?? ''));
                if ($ean === '') {
                    $ean = trim((string) ($item->product?->codigo_barras_caixa ?? ''));
                }
                $productId = $item->product_id ? (int) $item->product_id : 0;

                return [
                    'produto_id' => $productId > 0 ? $productId : null,
                    'codigo' => (string) ($item->product?->codigo ?? ''),
                    'codigo_barras' => $ean,
                    'imei' => $productId > 0 ? (string) ($imeis[$productId] ?? '') : '',
                    'descricao' => $descricao,
                    'preco' => (float) ($item->preco ?? 0),
                    'qtd' => (float) ($item->qtd ?? 1),
                    'desconto' => (float) ($item->desconto ?? 0),
                ];
            })
            ->filter(static fn (array $row): bool => ($row['descricao'] ?? '') !== '')
            ->values()
            ->all();

        $servicos = $os->itens
            ->filter(static fn ($item): bool => ($item->tipo ?? '') === 'S')
            ->map(static function ($item): array {
                $descricao = mb_strtoupper(trim((string) ($item->discriminacao ?: $item->nome ?: '')), 'UTF-8');

                return [
                    'produto_id' => $item->product_id ? (int) $item->product_id : null,
                    'codigo' => (string) ($item->product?->codigo ?? ''),
                    'descricao' => $descricao,
                    'preco' => (float) ($item->preco ?? 0),
                    'qtd' => (float) ($item->qtd ?? 1),
                    'desconto' => (float) ($item->desconto ?? 0),
                ];
            })
            ->filter(static fn (array $row): bool => ($row['descricao'] ?? '') !== '')
            ->values()
            ->all();

        return [
            'id' => (int) $os->id,
            'empresa_id' => (int) ($os->empresa_id ?? 0),
            'numero_os' => (string) $os->numero,
            'cliente' => $os->clienteNome(),
            'telefone' => (string) ($os->fone1 ?: $os->fone2 ?: ''),
            'endereco' => self::endereco($os),
            'equipamento' => (string) ($os->descricao ?: ''),
            'problema' => (string) ($os->problema ?: ''),
            'status' => self::statusApp($os),
            'status_codigo' => (string) $os->situacao,
            'data_hora' => $dataHora !== '' ? $dataHora : null,
            'hora_inicio' => $hora,
            'tecnico' => (string) ($os->atendente?->nome ?? ''),
            'servico_realizado' => trim((string) ($os->laudo ?: '')),
            'observacoes' => (string) ($os->observacoes ?: ''),
            'pecas' => $pecas,
            'servicos' => $servicos,
        ];
    }

    /**
     * Só para exibir no app. Não entra no cálculo da OS.
     *
     * @return array<int, string>
     */
    private static function primeiroImeiPorProduto(OrdemServico $os): array
    {
        $ids = $os->itens
            ->pluck('product_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return ProductImei::query()
            ->whereIn('product_id', $ids)
            ->orderBy('id')
            ->get(['product_id', 'imei'])
            ->groupBy('product_id')
            ->map(static fn ($rows): string => trim((string) ($rows->first()->imei ?? '')))
            ->all();
    }

    public static function statusApp(OrdemServico $os): string
    {
        if ($os->aguardandoFaturamento()) {
            return 'Em faturamento';
        }

        return match ((string) $os->situacao) {
            OrdemServico::SITUACAO_ABERTA => 'Pendente',
            OrdemServico::SITUACAO_ANDAMENTO => 'Em andamento',
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_ENTREGUE => 'Finalizada',
            OrdemServico::SITUACAO_CANCELADA => 'Cancelada',
            default => $os->situacaoLabel(),
        };
    }

    public static function situacaoFromApp(string $status): ?string
    {
        return match ($status) {
            'Pendente', 'Em faturamento' => OrdemServico::SITUACAO_ABERTA,
            'Em andamento' => OrdemServico::SITUACAO_ANDAMENTO,
            'Finalizada' => OrdemServico::SITUACAO_FINALIZADA,
            'Cancelada' => OrdemServico::SITUACAO_CANCELADA,
            default => null,
        };
    }

    private static function endereco(OrdemServico $os): string
    {
        $partes = array_filter([
            trim((string) ($os->endereco ?? '')),
            trim((string) ($os->bairro ?? '')),
            trim((string) ($os->cidade ?? '')),
            trim((string) ($os->uf ?? '')),
        ], static fn (string $p): bool => $p !== '');

        return implode(' — ', $partes);
    }
}
