<?php

namespace App\Support\Mesas;

use App\Models\Empresa;
use App\Models\PdvMesa;
use App\Support\Erp\Pdv\PdvMesaService;

/**
 * Leitura das mesas do PDV para o garçom (mesmas regras do painel de mesas do PDV).
 */
final class MesasPainelService
{
    /** Situação exibida no app. */
    public const LIVRE = 0;

    public const OCUPADA = 1;

    public const AGUARDANDO_FECHAMENTO = 2;

    /**
     * Só as mesas ocupadas ou reservadas vêm na lista; as demais estão livres.
     * Linha: [numero, qtd_itens, total, situacao, no_caixa (0/1), nome_no_caixa, aberta_em]
     *
     * @return array{quantidade: int, mesas: list<array{0: int, 1: int, 2: float, 3: int, 4: int, 5: string, 6: string|null}>, s: string}
     */
    public function lista(Empresa $empresa): array
    {
        $agora = now()->format('Y-m-d H:i:s');

        $mesas = PdvMesa::query()
            ->toBase()
            ->where('empresa_id', $empresa->id)
            ->where(function ($query) use ($agora): void {
                $query->where('qtd_itens', '>', 0)
                    ->orWhere('reservado_ate', '>', $agora);
            })
            ->orderBy('numero')
            ->get(['numero', 'qtd_itens', 'total', 'situacao', 'reserva_token', 'reservado_nome', 'reservado_ate', 'aberta_em'])
            ->map(function (object $row) use ($agora): array {
                $qtd = (int) $row->qtd_itens;
                $noCaixa = $row->reserva_token !== null
                    && $row->reservado_ate !== null
                    && (string) $row->reservado_ate > $agora;

                return [
                    (int) $row->numero,
                    $qtd,
                    round((float) $row->total, 2),
                    $this->situacao($qtd, (int) $row->situacao),
                    $noCaixa ? 1 : 0,
                    $noCaixa ? (string) ($row->reservado_nome ?? '') : '',
                    $qtd > 0 && $row->aberta_em !== null ? substr((string) $row->aberta_em, 0, 19) : null,
                ];
            })
            ->values()
            ->all();

        $quantidade = PdvMesaService::quantidade($empresa);

        return [
            'quantidade' => $quantidade,
            'mesas' => $mesas,
            's' => substr(md5($quantidade.'|'.json_encode($mesas)), 0, 16),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function comanda(Empresa $empresa, int $numero): array
    {
        $mesa = PdvMesa::query()
            ->where('empresa_id', $empresa->id)
            ->where('numero', $numero)
            ->first();

        if ($mesa === null) {
            return $this->resumo($numero, null) + ['itens' => []];
        }

        $itens = array_map(static function (array $item): array {
            $nivel = trim((string) ($item['tabela_nivel'] ?? $item['nivel'] ?? ''));
            $selecao = \App\Support\Erp\Pdv\PdvComposicaoSelecao::fromArray($item);
            $linhas = \App\Support\Erp\Pdv\PdvComposicaoLinhas::textosDoItem($item);

            return [
                'product_id' => (int) ($item['product_id'] ?? 0),
                'descricao' => (string) ($item['descricao'] ?? ''),
                'unidade' => (string) ($item['unidade'] ?? 'UN'),
                'quantidade' => round((float) ($item['quantidade'] ?? 0), 3),
                'preco' => round((float) ($item['preco'] ?? 0), 2),
                'total' => round((float) ($item['total'] ?? 0), 2),
                'acrescimo' => round((float) ($item['acrescimo'] ?? 0), 2),
                'obs' => \App\Support\Erp\Pdv\PdvComposicaoLinhas::limparObsManual((string) ($item['obs'] ?? '')),
                'tabela_nivel' => $nivel !== '' ? $nivel : null,
                'price_table_id' => isset($item['price_table_id']) ? (int) $item['price_table_id'] : null,
                'composicao_removidos' => $selecao->removidos,
                'composicao_adicionais' => $selecao->adicionais,
                'composicao_linhas' => $linhas,
            ];
        }, PdvMesaService::make()->itens($mesa));

        return $this->resumo($numero, $mesa) + ['itens' => $itens];
    }

    /**
     * @return array<string, mixed>
     */
    public function resumo(int $numero, ?PdvMesa $mesa): array
    {
        $qtd = (int) ($mesa?->qtd_itens ?? 0);
        $noCaixa = $mesa !== null
            && $mesa->reserva_token !== null
            && $mesa->reservado_ate !== null
            && $mesa->reservado_ate->isFuture();

        return [
            'numero' => $numero,
            'rotulo' => $mesa?->rotulo() ?? PdvMesaService::rotuloPadrao($numero),
            'qtd_itens' => $qtd,
            'total' => round((float) ($mesa?->total ?? 0), 2),
            'situacao' => $this->situacao($qtd, (int) ($mesa?->situacao ?? 0)),
            'no_caixa' => $noCaixa,
            'nome_no_caixa' => $noCaixa ? (string) ($mesa->reservado_nome ?? '') : '',
            'aberta_em' => $qtd > 0 ? $mesa?->aberta_em?->format('Y-m-d H:i:s') : null,
        ];
    }

    private function situacao(int $qtd, int $situacao): int
    {
        if ($qtd <= 0) {
            return self::LIVRE;
        }

        return $situacao === PdvMesa::SITUACAO_AGUARDANDO_FECHAMENTO
            ? self::AGUARDANDO_FECHAMENTO
            : self::OCUPADA;
    }
}
