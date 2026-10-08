<?php

namespace App\Support\Erp\Queries;

use App\Models\CaixaConta;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Movimentação de uma conta tipo PDV na tela Caixa, montada só com leitura:
 *
 * - lançamentos reais do Livro Caixa nessa conta (manuais, baixas etc.);
 * - movimentos das sessões PDV da conta (abertura, vendas por forma, recebimentos,
 *   suprimentos, sangrias, estornos);
 * - no fechamento: espelho (saída) das transferências PDV-CX-* lançadas no destino,
 *   as sangrias incluídas nessa transferência e o retorno do fundo de troco
 *   (abertura/suprimento não é transferido).
 *
 * Nada é gravado: o CAIXA GERAL segue só com os próprios lançamentos.
 * Colunas iguais às de caixa_lancamentos (+ ordem e dados da sessão), para a mesma grade.
 */
final class CaixaPdvMovimentacaoQuery
{
    private const ID_TRANSFERENCIA = 1000000000;

    private const ID_FUNDO = 2000000000;

    private const ID_SANGRIA = 3000000000;

    public static function isContaPdv(int $contaId): bool
    {
        if ($contaId <= 0) {
            return false;
        }

        return CaixaConta::query()
            ->whereKey($contaId)
            ->whereIn('tipo', [CaixaConta::TIPO_PDV, 'CAIXA', 'X'])
            ->exists();
    }

    public function __construct(private readonly int $contaId) {}

    public function build(): QueryBuilder
    {
        return $this->lancamentosDaConta()
            ->unionAll($this->movimentosSessao())
            ->unionAll($this->transferenciasFechamento())
            ->unionAll($this->sangriasIncluidasFechamento())
            ->unionAll($this->fundoTrocoFechamento());
    }

    private function lancamentosDaConta(): QueryBuilder
    {
        $hasEmpresa = Schema::hasColumn('caixa_lancamentos', 'empresa_id');

        return DB::table('caixa_lancamentos as l')
            ->where('l.caixa_conta_id', $this->contaId)
            ->select([
                DB::raw($this->signed($this->w('l.id')).' as id'),
                'l.codigo',
                'l.emissao',
                'l.documento',
                'l.historico',
                'l.plano_contas',
                'l.plano_conta_id',
                'l.caixa_conta_id',
                'l.entrada',
                'l.saida',
                $hasEmpresa ? 'l.empresa_id' : DB::raw('NULL as empresa_id'),
                DB::raw('COALESCE('.$this->w('l.created_at').', '.$this->w('l.emissao').') as ordem'),
                DB::raw('NULL as pdv_sessao_id'),
                DB::raw('NULL as pdv_operador'),
                DB::raw('NULL as pdv_terminal'),
            ]);
    }

    private function movimentosSessao(): QueryBuilder
    {
        $tipo = $this->w('m.tipo');

        $query = DB::table('pdv_caixa_movimentos as m')
            ->join('pdv_caixa_sessoes as s', 's.id', '=', 'm.pdv_caixa_sessao_id')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('pdv_vendas as v', 'v.id', '=', 'm.pdv_venda_id');

        $this->joinTerminal($query);
        $this->filtrarSessoes($query);

        // Só vendas/estornos têm plano (plano de venda da empresa); abertura, suprimento,
        // sangria e recebimento não são receita de venda.
        $ehVenda = "{$tipo} IN ('venda', 'estorno')";
        $temPlanoVenda = Schema::hasColumn('empresas', 'param_plano_conta_venda_id');

        if ($temPlanoVenda) {
            $query->leftJoin('empresas as e', 'e.id', '=', 's.empresa_id')
                ->leftJoin('planos_contas as pv', function (JoinClause $join): void {
                    $join->on('pv.id', '=', 'e.param_plano_conta_venda_id')
                        ->where('pv.dc', '=', 'C')
                        ->where('pv.ativo', '=', true);
                });
        }

        return $query->select([
            DB::raw('(0 - '.$this->signed($this->w('m.id')).') as id'),
            DB::raw('NULL as codigo'),
            DB::raw($this->w('m.created_at').' as emissao'),
            DB::raw($this->w('v.numero').' as documento'),
            'm.historico',
            DB::raw($temPlanoVenda
                ? "CASE WHEN {$ehVenda} THEN UPPER(".$this->w('pv.descricao').') END as plano_contas'
                : 'NULL as plano_contas'),
            DB::raw($temPlanoVenda
                ? "CASE WHEN {$ehVenda} THEN ".$this->w('pv.id').' END as plano_conta_id'
                : 'NULL as plano_conta_id'),
            DB::raw($this->contaId.' as caixa_conta_id'),
            'm.entrada',
            'm.saida',
            's.empresa_id',
            DB::raw($this->w('m.created_at').' as ordem'),
            DB::raw($this->w('s.id').' as pdv_sessao_id'),
            DB::raw($this->w('u.name').' as pdv_operador'),
            DB::raw($this->terminalNome().' as pdv_terminal'),
        ]);
    }

    /**
     * Transferência do fechamento vista do lado do PDV: entrada no destino = saída aqui.
     * Sangrias (-SG*) já aparecem como movimento da sessão.
     */
    private function transferenciasFechamento(): QueryBuilder
    {
        $doc = $this->w('tl.documento');
        $base = $this->concatDocumento('s');

        $query = DB::table('caixa_lancamentos as tl')
            ->join('pdv_caixa_sessoes as s', function (JoinClause $join) use ($doc, $base): void {
                $join->whereRaw(
                    "({$doc} = {$base} OR ({$doc} LIKE ".$this->concat($base, "'-%'")
                    ." AND {$doc} NOT LIKE ".$this->concat($base, "'-SG%'").'))'
                );
            })
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('tl.documento', 'like', 'PDV-CX-%')
            ->whereNotNull('s.fechado_em');

        $this->joinTerminal($query);
        $this->filtrarSessoes($query);

        return $query->select([
            DB::raw('(0 - ('.self::ID_TRANSFERENCIA.' + '.$this->signed($this->w('tl.id')).')) as id'),
            DB::raw('NULL as codigo'),
            DB::raw($this->w('s.fechado_em').' as emissao'),
            'tl.documento',
            'tl.historico',
            DB::raw('NULL as plano_contas'),
            DB::raw('NULL as plano_conta_id'),
            DB::raw($this->contaId.' as caixa_conta_id'),
            DB::raw($this->w('tl.saida').' as entrada'),
            DB::raw($this->w('tl.entrada').' as saida'),
            's.empresa_id',
            DB::raw($this->w('s.fechado_em').' as ordem'),
            DB::raw($this->w('s.id').' as pdv_sessao_id'),
            DB::raw($this->w('u.name').' as pdv_operador'),
            DB::raw($this->terminalNome().' as pdv_terminal'),
        ]);
    }

    /**
     * Sangria sai da gaveta durante a sessão, mas o fechamento transfere as vendas integrais
     * ao CAIXA GERAL (o valor sangrado vai junto). Esta linha devolve a sangria ao saldo da
     * conta PDV para a transferência não deixá-lo negativo. Fechamentos antigos (sangria
     * deduzida em -SD ou saldo líquido transferido) resultam em zero e não aparecem.
     */
    private function sangriasIncluidasFechamento(): QueryBuilder
    {
        $sessaoId = $this->w('s.id');
        $incluida = $this->expressoesFechamento()['incluida'];

        $query = DB::table('pdv_caixa_sessoes as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->whereNotNull('s.fechado_em');

        $this->joinTerminal($query);
        $this->filtrarSessoes($query);

        return $query->select([
            DB::raw('(0 - ('.self::ID_SANGRIA.' + '.$this->signed($sessaoId).')) as id'),
            DB::raw('NULL as codigo'),
            DB::raw($this->w('s.fechado_em').' as emissao'),
            DB::raw($this->concat("'PDV-CX-'", $sessaoId).' as documento'),
            DB::raw("'FECHAMENTO DO CAIXA - SANGRIAS INCLUIDAS NA TRANSFERENCIA AO CAIXA GERAL' as historico"),
            DB::raw('NULL as plano_contas'),
            DB::raw('NULL as plano_conta_id'),
            DB::raw($this->contaId.' as caixa_conta_id'),
            DB::raw("{$incluida} as entrada"),
            DB::raw('0 as saida'),
            's.empresa_id',
            DB::raw($this->w('s.fechado_em').' as ordem'),
            DB::raw($sessaoId.' as pdv_sessao_id'),
            DB::raw($this->w('u.name').' as pdv_operador'),
            DB::raw($this->terminalNome().' as pdv_terminal'),
        ]);
    }

    /**
     * Saldo da gaveta que não foi transferido no fechamento (abertura + suprimentos).
     * Fechamentos antigos que transferiram tudo resultam em zero e não aparecem.
     */
    private function fundoTrocoFechamento(): QueryBuilder
    {
        $sessaoId = $this->w('s.id');
        $expr = $this->expressoesFechamento();

        $retido = "({$expr['saldo']} - {$expr['transferido']} + {$expr['incluida']})";

        $query = DB::table('pdv_caixa_sessoes as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->whereNotNull('s.fechado_em');

        $this->joinTerminal($query);
        $this->filtrarSessoes($query);

        return $query->select([
            DB::raw('(0 - ('.self::ID_FUNDO.' + '.$this->signed($sessaoId).')) as id'),
            DB::raw('NULL as codigo'),
            DB::raw($this->w('s.fechado_em').' as emissao'),
            DB::raw($this->concat("'PDV-CX-'", $sessaoId).' as documento'),
            DB::raw("'FECHAMENTO DO CAIXA - FUNDO DE TROCO (ABERTURA/SUPRIMENTO) NAO TRANSFERIDO' as historico"),
            DB::raw('NULL as plano_contas'),
            DB::raw('NULL as plano_conta_id'),
            DB::raw($this->contaId.' as caixa_conta_id'),
            DB::raw("CASE WHEN {$retido} < 0 THEN 0 - {$retido} ELSE 0 END as entrada"),
            DB::raw("CASE WHEN {$retido} > 0 THEN {$retido} ELSE 0 END as saida"),
            's.empresa_id',
            DB::raw($this->w('s.fechado_em').' as ordem'),
            DB::raw($sessaoId.' as pdv_sessao_id'),
            DB::raw($this->w('u.name').' as pdv_operador'),
            DB::raw($this->terminalNome().' as pdv_terminal'),
        ]);
    }

    /**
     * Subconsultas por sessão (alias "s"):
     * - saldo: resultado da gaveta (todos os movimentos);
     * - transferido: PDV-CX-{s}* no Livro Caixa, exceto -SG (sangria antiga na subcaixa);
     * - incluida: sangrias da sessão quando o fechamento transferiu vendas/recebimentos
     *   integrais (modelo atual). Sem -SD e com transferido = movimentos transferíveis.
     *
     * @return array{saldo: string, transferido: string, incluida: string}
     */
    private function expressoesFechamento(): array
    {
        $sessaoId = $this->w('s.id');
        $base = $this->concatDocumento('s');
        $doc = $this->w('fl.documento');
        $tiposFora = "('abertura', 'suprimento', 'sangria')";

        $somaMovimentos = fn (string $alias, string $filtro): string => '(SELECT COALESCE(SUM('
            .$this->w($alias.'.entrada').' - '.$this->w($alias.'.saida').'), 0)'
            .' FROM '.$this->wt('pdv_caixa_movimentos').' AS '.$this->wt($alias)
            .' WHERE '.$this->w($alias.'.pdv_caixa_sessao_id')." = {$sessaoId}{$filtro})";

        $saldo = $somaMovimentos('pm', '');
        $transferivel = $somaMovimentos('pn', ' AND '.$this->w('pn.tipo')." NOT IN {$tiposFora}");
        $sangrias = '(0 - '.$somaMovimentos('ps', ' AND '.$this->w('ps.tipo')." = 'sangria'").')';

        $transferido = '(SELECT COALESCE(SUM('.$this->w('fl.entrada').' - '.$this->w('fl.saida').'), 0)'
            .' FROM '.$this->wt('caixa_lancamentos').' AS '.$this->wt('fl')
            ." WHERE {$doc} = {$base} OR ({$doc} LIKE ".$this->concat($base, "'-%'")
            ." AND {$doc} NOT LIKE ".$this->concat($base, "'-SG%'").'))';

        $temDeducaoSangria = 'EXISTS (SELECT 1 FROM '.$this->wt('caixa_lancamentos').' AS '.$this->wt('sd')
            .' WHERE '.$this->w('sd.documento').' LIKE '.$this->concat($base, "'-SD-%'").')';

        $incluida = "(CASE WHEN NOT {$temDeducaoSangria}"
            ." AND ABS({$transferido} - {$transferivel}) < 0.01"
            ." AND {$sangrias} > 0 THEN {$sangrias} ELSE 0 END)";

        return ['saldo' => $saldo, 'transferido' => $transferido, 'incluida' => $incluida];
    }

    /**
     * Sessões da conta: caixa_conta_id gravado na abertura. Sessões antigas (sem conta)
     * caem no caixa PDV padrão do operador ou, havendo um só caixa PDV, nele.
     */
    private function filtrarSessoes(QueryBuilder $query): void
    {
        $temColuna = Schema::hasColumn('pdv_caixa_sessoes', 'caixa_conta_id');
        $unicoPdv = CaixaConta::query()->assignable()->count() === 1;

        $query->where(function (QueryBuilder $sessoes) use ($temColuna, $unicoPdv): void {
            if ($temColuna) {
                $sessoes->where('s.caixa_conta_id', $this->contaId);
            }

            $sessoes->orWhere(function (QueryBuilder $legado) use ($temColuna, $unicoPdv): void {
                if ($temColuna) {
                    $legado->whereNull('s.caixa_conta_id');
                }

                if ($unicoPdv) {
                    return;
                }

                $legado->whereExists(function (QueryBuilder $padrao): void {
                    $padrao->from('caixa_conta_user as ccu')
                        ->whereColumn('ccu.user_id', 's.user_id')
                        ->whereColumn('ccu.empresa_id', 's.empresa_id')
                        ->where('ccu.caixa_conta_id', $this->contaId)
                        ->where('ccu.is_padrao', true);
                });
            });
        });
    }

    private function joinTerminal(QueryBuilder $query): void
    {
        if (Schema::hasColumn('pdv_caixa_sessoes', 'terminal_id')) {
            $query->leftJoin('terminais as t', 't.id', '=', 's.terminal_id');
        }
    }

    private function terminalNome(): string
    {
        return Schema::hasColumn('pdv_caixa_sessoes', 'terminal_id') ? $this->w('t.nome') : 'NULL';
    }

    private function concatDocumento(string $sessaoAlias): string
    {
        return $this->concat("'PDV-CX-'", $this->w($sessaoAlias.'.id'));
    }

    /** ids são BIGINT UNSIGNED no MySQL: negar sem CAST estoura. */
    private function signed(string $expressao): string
    {
        $tipo = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'SIGNED';

        return "CAST({$expressao} AS {$tipo})";
    }

    private function concat(string ...$partes): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return '('.implode(' || ', $partes).')';
        }

        return 'CONCAT('.implode(', ', $partes).')';
    }

    private function w(string $coluna): string
    {
        return DB::connection()->getQueryGrammar()->wrap($coluna);
    }

    /** Tabela/alias com prefixo do banco, igual ao que o Query Builder gera para "tabela as alias". */
    private function wt(string $tabela): string
    {
        return DB::connection()->getQueryGrammar()->wrapTable($tabela);
    }
}
