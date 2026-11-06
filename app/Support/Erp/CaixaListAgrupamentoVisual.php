<?php

namespace App\Support\Erp;

use App\Models\CaixaLancamento;
use App\Support\Erp\Financeiro\CaixaTransferencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Agrupa visualmente na grade do Caixa os pagamentos da mesma OS/TV/FV
 * (e PDV: sessão + identificação da venda), sem alterar lançamentos no banco.
 *
 * Só colapsa quando a chave está completa na página (evita totais parciais na paginação).
 */
final class CaixaListAgrupamentoVisual
{
    /**
     * @param  Collection<int, CaixaLancamento>  $items
     * @param  array<string, true>|null  $chavesCompletas  null = agrupa qualquer chave com 2+ na página
     * @param  array<int, string>  $pedidosMonitor  id FV => nº pedido (Monitor/venda)
     * @return Collection<int, CaixaLancamento>
     */
    public static function aplicar(
        Collection $items,
        ?array $chavesCompletas = null,
        array $pedidosMonitor = [],
    ): Collection {

        if ($items->count() < 2) {
            return $items;
        }

        /** @var array<string, list<CaixaLancamento>> $buckets */
        $buckets = [];
        /** @var list<array{0: string, 1: mixed}> $ordem */
        $ordem = [];

        foreach ($items as $item) {
            if (! $item instanceof CaixaLancamento) {
                $ordem[] = ['raw', $item];

                continue;
            }

            $chave = self::chaveGrupo($item);

            if ($chave === null) {
                $ordem[] = ['single', $item];

                continue;
            }

            if (! isset($buckets[$chave])) {
                $buckets[$chave] = [];
                $ordem[] = ['group', $chave];
            }

            $buckets[$chave][] = $item;
        }

        $out = collect();

        foreach ($ordem as [$tipo, $valor]) {
            if ($tipo === 'raw' || $tipo === 'single') {
                $out->push($valor);

                continue;
            }

            $membros = $buckets[$valor] ?? [];

            if (count($membros) <= 1) {
                foreach ($membros as $membro) {
                    $out->push($membro);
                }

                continue;
            }

            // Paginação: sem prova de completude, não colapsa (evita soma incompleta).
            if ($chavesCompletas !== null && ! isset($chavesCompletas[$valor])) {
                foreach ($membros as $membro) {
                    $out->push($membro);
                }

                continue;
            }

            $out->push(self::montarLinhaGrupo($membros, $pedidosMonitor));
        }

        return $out;
    }

    /**
     * Chaves cujo total no escopo filtrado == quantidade na página.
     *
     * @param  Collection<int, CaixaLancamento>  $items
     * @return array<string, true>
     */
    public static function chavesCompletasNaPagina(Collection $items, Builder $scopedQuery): array
    {
        $contagemPagina = [];

        foreach ($items as $item) {
            if (! $item instanceof CaixaLancamento) {
                continue;
            }

            $chave = self::chaveGrupo($item);
            if ($chave === null) {
                continue;
            }

            $contagemPagina[$chave] = ($contagemPagina[$chave] ?? 0) + 1;
        }

        $completas = [];

        foreach ($contagemPagina as $chave => $naPagina) {
            if ($naPagina < 2) {
                continue;
            }

            $total = self::contarNoEscopo($scopedQuery, $chave);

            if ($total === $naPagina) {
                $completas[$chave] = true;
            }
        }

        return $completas;
    }

    /**
     * Chave estável: conta + vínculo da venda (não só sessão/período).
     */
    public static function chaveGrupo(CaixaLancamento $lancamento): ?string
    {
        if (self::ehVirtual($lancamento)) {
            return null;
        }

        $documento = trim((string) ($lancamento->documento ?? ''));
        $historico = mb_strtoupper(trim((string) ($lancamento->historico ?? '')), 'UTF-8');

        if ($documento === '' || $historico === '') {
            return null;
        }

        if (str_starts_with($historico, 'ESTORNO')) {
            return null;
        }

        if (CaixaTransferencia::isDocumentoTransferencia($documento)) {
            return null;
        }

        if (str_starts_with($historico, '[MANUAL]')) {
            return null;
        }

        $contaId = (int) ($lancamento->caixa_conta_id ?? 0);

        // OS à vista mista: documento exato OS-123 (não OS-123-1).
        if (preg_match('/^OS-(\d+)$/', $documento, $m) === 1) {
            return 'os:'.$contaId.':'.$m[1];
        }

        // TV/FV: FV-12 ou FV-12/P1… (não junta FV-12 com FV-120).
        if (preg_match('/^FV-(\d+)(?:\/|$)/', $documento, $m) === 1
            || preg_match('/^FV-(\d+)$/', $documento, $m) === 1) {
            return 'fv:'.$contaId.':'.$m[1];
        }

        // PDV: sessão + identificação da venda (número no histórico/documento).
        // Não agrupa PDV-CX / fechamento / sangria só por sessão.
        $sessaoId = (int) ($lancamento->getAttribute('pdv_sessao_id') ?? 0);
        if ($sessaoId > 0) {
            if (str_starts_with($documento, 'PDV-CX-')) {
                return null;
            }

            if (preg_match('/^VENDA PDV #(\S+)/', $historico, $hm) === 1) {
                return 'pdv:'.$sessaoId.':'.$hm[1];
            }

            return null;
        }

        return null;
    }

    public static function contarNoEscopo(Builder $scopedQuery, string $chave): int
    {
        $partes = explode(':', $chave, 3);
        if (count($partes) !== 3) {
            return 0;
        }

        [$tipo, $contaIdRaw, $ref] = $partes;
        $contaId = (int) $contaIdRaw;
        $query = $scopedQuery->clone();

        if ($contaId > 0) {
            $query->where('caixa_conta_id', $contaId);
        }

        $query->where('historico', 'not like', 'ESTORNO%');

        return match ($tipo) {
            'os' => $query->where('documento', 'OS-'.$ref)->count(),
            'fv' => $query->where(function (Builder $q) use ($ref): void {
                $q->where('documento', 'FV-'.$ref)
                    ->orWhere('documento', 'like', 'FV-'.$ref.'/%');
            })->count(),
            // pdv:{sessaoId}:{vendaRef} — sessão + venda, não só a sessão.
            'pdv' => $query
                ->where('pdv_sessao_id', $contaId)
                ->where(function (Builder $q) use ($ref): void {
                    $q->where('documento', $ref)
                        ->orWhere('historico', 'like', 'VENDA PDV #'.$ref.'%');
                })
                ->count(),
            default => 0,
        };
    }

    /**
     * @param  list<CaixaLancamento>  $membros
     * @param  array<int, string>  $pedidosMonitor
     */
    public static function montarLinhaGrupo(array $membros, array $pedidosMonitor = []): CaixaLancamento
    {
        $primary = $membros[0];
        $sumEntrada = 0.0;
        $sumSaida = 0.0;
        $linhas = [];

        foreach ($membros as $membro) {
            $entrada = round((float) ($membro->entrada ?? 0), 2);
            $saida = round((float) ($membro->saida ?? 0), 2);
            $sumEntrada += $entrada;
            $sumSaida += $saida;

            $histExibicao = CaixaHistoricoExibicao::paraExibicao(
                (string) ($membro->historico ?? ''),
                $pedidosMonitor,
            );
            $histExibicao = self::semSufixoSessao($histExibicao);
            $meio = self::extrairMeio($histExibicao);
            $valor = $entrada > 0.009 ? $entrada : $saida;

            $linhas[] = [
                'id' => (int) $membro->getKey(),
                'codigo' => (string) ($membro->codigo ?? ''),
                'meio' => $meio,
                'entrada' => $entrada,
                'saida' => $saida,
                'valor' => $valor,
                'historico' => $histExibicao,
                'plano_contas' => (string) ($membro->plano_contas ?? ''),
            ];
        }

        $rotulo = self::rotuloOperacao($primary, $linhas[0]['historico'] ?? '', $pedidosMonitor);
        $partes = [];
        foreach ($linhas as $linha) {
            $partes[] = $linha['meio'].' R$ '.ErpMoney::formatBr($linha['valor']);
        }

        $resumo = $rotulo.' — '.implode(' + ', $partes);
        $tooltip = [];
        foreach ($linhas as $linha) {
            $cod = $linha['codigo'] !== '' ? '#'.$linha['codigo'].' · ' : '';
            $tooltip[] = $cod.$linha['historico'].' — R$ '.ErpMoney::formatBr($linha['valor']);
        }

        $primary->setAttribute('entrada', round($sumEntrada, 2));
        $primary->setAttribute('saida', round($sumSaida, 2));
        $primary->setAttribute('caixa_grupo', true);
        $primary->setAttribute('caixa_grupo_ids', array_map(
            static fn (array $l): int => (int) $l['id'],
            $linhas,
        ));
        $primary->setAttribute('caixa_grupo_linhas', $linhas);
        $primary->setAttribute('caixa_grupo_resumo', $resumo);
        $primary->setAttribute('caixa_grupo_tooltip', implode("\n", $tooltip));
        $primary->setAttribute('caixa_grupo_qtd', count($linhas));

        return $primary;
    }

    private static function ehVirtual(CaixaLancamento $lancamento): bool
    {
        $evento = (string) ($lancamento->getAttribute('livro_evento') ?? '');

        return $evento === 'abertura'
            || $evento === 'fechamento'
            || (bool) $lancamento->getAttribute('livro_abertura')
            || (bool) $lancamento->getAttribute('livro_fechamento');
    }

    private static function semSufixoSessao(string $historico): string
    {
        $limpo = preg_replace('/\s·\s.*$/u', '', $historico);

        return is_string($limpo) ? trim($limpo) : trim($historico);
    }

    private static function extrairMeio(string $historicoExibicao): string
    {
        if (preg_match('/\(([^)]+)\)\s*$/u', $historicoExibicao, $m) === 1) {
            return CaixaHistoricoExibicao::formatarMeio($m[1]);
        }

        if (preg_match('/-\s*([A-ZÁÉÍÓÚÂÊÔÃÕÇ0-9 ]+)$/u', $historicoExibicao, $m) === 1) {
            return CaixaHistoricoExibicao::formatarMeio($m[1]);
        }

        // DINHEIRO no PDV: "VENDA PDV #12" sem sufixo de forma.
        if (preg_match('/^VENDA PDV #\S+$/u', $historicoExibicao) === 1) {
            return 'DINHEIRO';
        }

        return $historicoExibicao !== '' ? $historicoExibicao : '—';
    }

    /**
     * @param  array<int, string>  $pedidosMonitor
     */
    private static function rotuloOperacao(
        CaixaLancamento $primary,
        string $primeiroHistorico,
        array $pedidosMonitor = [],
    ): string {
        if (preg_match('/^(ESTORNO\s+)?(OS|TV|FV)\s+(\d+)/u', $primeiroHistorico, $m) === 1) {
            return $m[2].' '.$m[3];
        }

        $documento = trim((string) ($primary->documento ?? ''));
        $historicoRaw = mb_strtoupper((string) ($primary->historico ?? ''), 'UTF-8');

        if (preg_match('/^OS-(\d+)$/', $documento, $m) === 1) {
            return 'OS '.$m[1];
        }

        if (preg_match('/^FV-(\d+)/', $documento, $m) === 1) {
            $orderId = (int) $m[1];
            $numero = ContaReceberPedidoExibicao::numeroDoMapa($pedidosMonitor[$orderId] ?? null)
                ?? (string) $orderId;
            if ($numero === '') {
                $numero = '0';
            }

            return (str_contains($historicoRaw, 'VENDA ERP') ? 'TV ' : 'FV ').$numero;
        }

        if (preg_match('/^VENDA PDV #(\S+)/', $historicoRaw, $m) === 1) {
            $num = ltrim($m[1], '0');

            return 'PDV '.($num !== '' ? $num : '0');
        }

        return $documento !== '' ? $documento : 'VENDA';
    }
}
