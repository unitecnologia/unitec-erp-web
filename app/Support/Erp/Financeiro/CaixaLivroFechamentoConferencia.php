<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\FormaPagamento;
use App\Support\Erp\BrDecimal;

/**
 * Conferência por forma no Fechar Caixa (Livro) — espelha o espírito do PDV
 * (dinheiro contado + resumo), sem depender de sessão PDV.
 *
 * Lançamentos do Livro Caixa não têm forma_pagamento: esperado por modalidade
 * só quando for apurável com segurança (hoje: dinheiro físico = saldo do movimento).
 */
final class CaixaLivroFechamentoConferencia
{
    public const GRUPO_FISICO = 'fisico';

    public const GRUPO_ELETRONICO = 'eletronico';

    public const GRUPO_OUTROS = 'outros';

    /**
     * Ordem e rótulos canônicos (alinhados a FormaPagamento::tipoLabels).
     *
     * @return array<string, string>
     */
    public static function labelsCanonicos(): array
    {
        return FormaPagamento::tipoLabels();
    }

    /**
     * @return list<string>
     */
    public static function tiposCore(): array
    {
        return ['dinheiro', 'pix', 'cartao_debito', 'cartao_credito'];
    }

    public static function grupoDoTipo(string $tipo): string
    {
        return match ($tipo) {
            'dinheiro' => self::GRUPO_FISICO,
            'pix', 'cartao_debito', 'cartao_credito', 'tef' => self::GRUPO_ELETRONICO,
            default => self::GRUPO_OUTROS,
        };
    }

    /**
     * Modalidades para a UI de fechamento (empresa + conta).
     *
     * @return list<array{
     *     chave: string,
     *     label: string,
     *     grupo: string,
     *     esperado: ?float,
     *     esperado_disponivel: bool,
     *     esperado_nota: string,
     *     conta_destino_id: ?int,
     *     destino_outra_conta: bool
     * }>
     */
    public static function modalidades(
        CaixaConta $conta,
        float $saldoMovimento,
        bool $exigeTroco,
    ): array {
        $labels = self::labelsCanonicos();
        $tipos = [];

        foreach (self::tiposCore() as $t) {
            $tipos[$t] = true;
        }

        $ativos = FormaPagamento::query()
            ->where('ativo', true)
            ->whereNotNull('tipo')
            ->get(['tipo', 'conta_destino_id', 'descricao']);

        /** @var array<string, array{conta_destino_id: ?int}> $meta */
        $meta = [];
        foreach ($ativos as $fp) {
            $tipo = mb_strtolower(trim((string) $fp->tipo), 'UTF-8');
            if ($tipo === '' || $tipo === 'troca') {
                continue;
            }
            $tipos[$tipo] = true;
            if (! isset($meta[$tipo])) {
                $meta[$tipo] = [
                    'conta_destino_id' => $fp->conta_destino_id
                        ? (int) $fp->conta_destino_id
                        : null,
                ];
            }
        }

        // Core primeiro (ordem canônica), depois demais tipos ativos alfabeticamente.
        $core = self::tiposCore();
        $resto = array_values(array_diff(array_keys($tipos), $core, ['troca']));
        sort($resto);
        $ordem = array_merge($core, $resto);

        $contaId = (int) $conta->id;
        $linhas = [];

        foreach ($ordem as $tipo) {
            if (! isset($tipos[$tipo])) {
                continue;
            }

            $grupo = self::grupoDoTipo($tipo);
            $destinoId = $meta[$tipo]['conta_destino_id'] ?? null;
            $destinoOutra = $destinoId !== null && $destinoId !== $contaId;

            $esperado = null;
            $disponivel = false;
            $nota = 'Não apurável: lançamentos do Livro Caixa não registram forma de pagamento.';

            if ($tipo === 'dinheiro' && $exigeTroco) {
                // Saldo operacional do gaveteiro (troco + E − S). Troco não é receita.
                $esperado = round($saldoMovimento, 2);
                $disponivel = true;
                $nota = 'Saldo do movimento (troco inicial + entradas − saídas). O troco não é receita.';
            } elseif ($grupo === self::GRUPO_ELETRONICO) {
                $nota = 'Não apurável no Livro. PIX/cartões não devem ser somados ao dinheiro físico desta conta.';
                if ($destinoOutra) {
                    $nota .= ' Forma costuma destinar a outra conta.';
                }
            } elseif ($destinoOutra) {
                $nota = 'Não apurável. Forma costuma destinar a outra conta — conferência só informativa.';
            }

            $linhas[] = [
                'chave' => $tipo,
                'label' => $labels[$tipo] ?? mb_strtoupper($tipo, 'UTF-8'),
                'grupo' => $grupo,
                'esperado' => $esperado,
                'esperado_disponivel' => $disponivel,
                'esperado_nota' => $nota,
                'conta_destino_id' => $destinoId,
                'destino_outra_conta' => $destinoOutra,
            ];
        }

        return $linhas;
    }

    /**
     * Monta payload persistível a partir dos valores digitados (BR ou float).
     *
     * @param  list<array<string, mixed>>  $modalidades
     * @param  array<string, mixed>  $informadosPorChave
     * @return array{
     *     linhas: list<array<string, mixed>>,
     *     total_informado: float,
     *     total_informado_fisico: float,
     *     total_informado_eletronico: float,
     *     total_informado_outros: float,
     *     valor_contado: float,
     *     diferenca: ?float,
     *     diferenca_disponivel: bool
     * }
     */
    public static function montarPayload(
        array $modalidades,
        array $informadosPorChave,
        bool $exigeTroco,
    ): array {
        $linhas = [];
        $total = 0.0;
        $totFisico = 0.0;
        $totEletronico = 0.0;
        $totOutros = 0.0;
        $informadoDinheiro = 0.0;
        $esperadoDinheiro = null;
        $esperadoDinheiroOk = false;

        foreach ($modalidades as $mod) {
            $chave = (string) ($mod['chave'] ?? '');
            if ($chave === '') {
                continue;
            }

            $raw = $informadosPorChave[$chave] ?? 0;
            $informado = is_numeric($raw)
                ? round((float) $raw, 2)
                : BrDecimal::parse($raw, 2);
            $informado = round(max(0, $informado), 2);

            $esperadoDisponivel = (bool) ($mod['esperado_disponivel'] ?? false);
            $esperado = $esperadoDisponivel ? round((float) ($mod['esperado'] ?? 0), 2) : null;
            $diferenca = $esperadoDisponivel && $esperado !== null
                ? round($informado - $esperado, 2)
                : null;

            $grupo = (string) ($mod['grupo'] ?? self::GRUPO_OUTROS);
            $total = round($total + $informado, 2);
            if ($grupo === self::GRUPO_FISICO) {
                $totFisico = round($totFisico + $informado, 2);
            } elseif ($grupo === self::GRUPO_ELETRONICO) {
                $totEletronico = round($totEletronico + $informado, 2);
            } else {
                $totOutros = round($totOutros + $informado, 2);
            }

            if ($chave === 'dinheiro') {
                $informadoDinheiro = $informado;
                if ($esperadoDisponivel) {
                    $esperadoDinheiro = $esperado;
                    $esperadoDinheiroOk = true;
                }
            }

            $linhas[] = [
                'chave' => $chave,
                'label' => (string) ($mod['label'] ?? $chave),
                'grupo' => $grupo,
                'informado' => $informado,
                'esperado' => $esperado,
                'esperado_disponivel' => $esperadoDisponivel,
                'esperado_nota' => (string) ($mod['esperado_nota'] ?? ''),
                'diferenca' => $diferenca,
                'conta_destino_id' => $mod['conta_destino_id'] ?? null,
                'destino_outra_conta' => (bool) ($mod['destino_outra_conta'] ?? false),
            ];
        }

        // Contagem física (estilo PDV): dinheiro. BANCO: total informado como referência.
        $valorContado = $exigeTroco ? $informadoDinheiro : $total;
        $diferenca = null;
        $diferencaOk = false;
        if ($exigeTroco && $esperadoDinheiroOk && $esperadoDinheiro !== null) {
            $diferenca = round($informadoDinheiro - $esperadoDinheiro, 2);
            $diferencaOk = true;
        }

        return [
            'linhas' => $linhas,
            'total_informado' => $total,
            'total_informado_fisico' => $totFisico,
            'total_informado_eletronico' => $totEletronico,
            'total_informado_outros' => $totOutros,
            'valor_contado' => $valorContado,
            'diferenca' => $diferenca,
            'diferenca_disponivel' => $diferencaOk,
        ];
    }

    /**
     * Prefill dos campos "contado" na abertura do modal.
     * Sempre 0,00 — o operador informa a conferência (padrão PDV).
     *
     * @param  list<array<string, mixed>>  $modalidades
     * @return array<string, string>
     */
    public static function prefillInformados(array $modalidades): array
    {
        $out = [];
        foreach ($modalidades as $mod) {
            $chave = (string) ($mod['chave'] ?? '');
            if ($chave === '') {
                continue;
            }
            $out[$chave] = '0,00';
        }

        return $out;
    }
}
