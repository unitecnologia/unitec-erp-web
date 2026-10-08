<?php

namespace App\Support\Erp\Os;

use App\Models\CaixaConta;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\FormaPagamento;
use App\Models\OrdemServico;
use App\Models\Product;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use App\Support\Erp\Financeiro\ContaReceberJurosCarteira;
use App\Support\Erp\Financeiro\FormaPagamentoDestino;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Pdv\PdvStockService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class OsFaturamentoService
{
    /**
     * @param  list<array{id: int, valor: string, descricao?: string, forma?: string, tipo?: string, tipo_movimento?: string}>  $pagamentos
     * @param  list<int>|null  $tabelaPrazoDias  Dias das parcelas (crediário/cheque/boleto), igual PDV.
     * @param  list<string>|null  $chequeNumeros
     * @return list<\App\Models\ContaReceber>
     */
    public function faturar(
        OrdemServico $os,
        array $pagamentos,
        ?array $tabelaPrazoDias = null,
        ?array $chequeNumeros = null,
    ): array {
        if (in_array($os->situacao, [OrdemServico::SITUACAO_FINALIZADA, OrdemServico::SITUACAO_ENTREGUE, OrdemServico::SITUACAO_CANCELADA], true)) {
            throw new \RuntimeException('Esta OS não pode ser faturada.');
        }

        if ($os->cliente_id === null) {
            throw new \RuntimeException('Informe o cliente da OS.');
        }

        $os->loadMissing('itens.product');
        $total = $this->money($os->total_geral);

        if (bccomp($total, '0.00', 2) !== 1) {
            throw new \RuntimeException('A OS não tem valor para faturar.');
        }

        $linhas = $this->linhas($pagamentos, $total);
        $contasCriadas = [];

        DB::transaction(function () use ($os, $linhas, $total, $pagamentos, $tabelaPrazoDias, $chequeNumeros, &$contasCriadas): void {
            $documento = 'OS-'.preg_replace('/\D/', '', (string) $os->numero);
            $hoje = ErpTimezone::toLocal()->toDateString();
            $empresaId = $os->empresa_id ? (int) $os->empresa_id : ErpContext::currentEmpresaId();
            $baixa = app(ContaReceberBaixaService::class);
            $caixaGeral = (int) CaixaConta::ensureCaixaGeral()->id;

            foreach ($linhas as $linha) {
                $meta = $this->metaPagamento($pagamentos, (int) $linha['forma']->id);

                $contasCriadas = array_merge($contasCriadas, $this->lancar(
                    $os,
                    $linha['forma'],
                    $linha['valor'],
                    $documento,
                    $hoje,
                    $empresaId,
                    $baixa,
                    $caixaGeral,
                    $meta,
                    $tabelaPrazoDias,
                    $chequeNumeros,
                ));
            }

            $this->baixarPecas($os, $documento, $empresaId);

            $snapshotPagamentos = $this->montarSnapshotPagamentos(
                $pagamentos,
                $linhas,
                $tabelaPrazoDias,
                $hoje,
            );

            $payload = [
                'situacao' => OrdemServico::SITUACAO_FINALIZADA,
                'data_termino' => $os->data_termino ?? $hoje,
                'hora_termino' => $os->hora_termino ?: ErpTimezone::toLocal()->format('H:i:s'),
                'total_geral' => $total,
            ];

            if (\Illuminate\Support\Facades\Schema::hasColumn($os->getTable(), 'faturamento_pagamentos')) {
                $payload['faturamento_pagamentos'] = $snapshotPagamentos;
            }

            $os->forceFill($payload)->save();
        });

        return $contasCriadas;
    }

    /**
     * @param  list<array{id: int, valor?: string, descricao?: string, forma?: string, tipo?: string, tipo_movimento?: string}>  $pagamentos
     * @return array{forma: string, tipo: string, tipo_movimento: string}
     */
    private function metaPagamento(array $pagamentos, int $formaId): array
    {
        foreach ($pagamentos as $pagamento) {
            if ((int) ($pagamento['id'] ?? 0) !== $formaId) {
                continue;
            }

            return [
                'forma' => (string) ($pagamento['descricao'] ?? $pagamento['forma'] ?? ''),
                'tipo' => (string) ($pagamento['tipo'] ?? ''),
                'tipo_movimento' => (string) ($pagamento['tipo_movimento'] ?? ''),
            ];
        }

        return ['forma' => '', 'tipo' => '', 'tipo_movimento' => ''];
    }

    /**
     * @param  list<array{id: int, valor: string}>  $pagamentos
     * @return list<array{forma: FormaPagamento, valor: string}>
     */
    private function linhas(array $pagamentos, string $total): array
    {
        $ids = [];
        $brutos = [];

        foreach ($pagamentos as $pagamento) {
            $id = (int) ($pagamento['id'] ?? 0);
            $valor = $this->money((string) ($pagamento['valor'] ?? '0'));

            if ($id <= 0 || bccomp($valor, '0.00', 2) !== 1) {
                continue;
            }

            $ids[] = $id;
            $brutos[] = ['id' => $id, 'valor' => $valor];
        }

        if ($brutos === []) {
            throw new \RuntimeException('Informe o valor nos meios de pagamento.');
        }

        $formas = FormaPagamento::query()->whereIn('id', $ids)->get()->keyBy('id');
        $soma = '0.00';
        $linhas = [];

        foreach ($brutos as $bruto) {
            $forma = $formas->get($bruto['id']);

            if (! $forma instanceof FormaPagamento) {
                throw new \RuntimeException('Forma de pagamento não encontrada.');
            }

            $soma = bcadd($soma, $bruto['valor'], 2);
            $linhas[] = ['forma' => $forma, 'valor' => $bruto['valor']];
        }

        if (bccomp($soma, $total, 2) === -1) {
            throw new \RuntimeException('Valor restante: R$ '.$this->formatBr(bcsub($total, $soma, 2)).'.');
        }

        $excesso = bcsub($soma, $total, 2);

        if (bccomp($excesso, '0.00', 2) === 1) {
            $linhas = $this->abaterTroco($linhas, $excesso);
        }

        return array_values(array_filter(
            $linhas,
            fn (array $linha): bool => bccomp($linha['valor'], '0.00', 2) === 1,
        ));
    }

    /**
     * @param  list<array{forma: FormaPagamento, valor: string}>  $linhas
     * @return list<array{forma: FormaPagamento, valor: string}>
     */
    private function abaterTroco(array $linhas, string $excesso): array
    {
        foreach ($linhas as $index => $linha) {
            if (! $this->eDinheiro($linha['forma'])) {
                continue;
            }

            if (bccomp($linha['valor'], $excesso, 2) === -1) {
                throw new \RuntimeException('O valor informado é maior que o total.');
            }

            $linhas[$index]['valor'] = bcsub($linha['valor'], $excesso, 2);

            return $linhas;
        }

        throw new \RuntimeException('O valor informado é maior que o total.');
    }

    /**
     * @param  list<int>|null  $tabelaPrazoDias
     * @param  list<string>|null  $chequeNumeros
     * @return list<ContaReceber>
     */
    private function lancar(
        OrdemServico $os,
        FormaPagamento $forma,
        string $valor,
        string $documento,
        string $hoje,
        ?int $empresaId,
        ContaReceberBaixaService $baixa,
        int $caixaGeral,
        array $meta = [],
        ?array $tabelaPrazoDias = null,
        ?array $chequeNumeros = null,
    ): array {
        $movimento = FormaPagamentoDestino::from($forma);
        $label = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');
        $historico = 'OS '.$os->numero.' ('.$label.')';
        $valorFloat = (float) $valor;
        $planoVenda = EmpresaParametros::planoVendaLancamento($empresaId);

        if (FormaPagamentoDestino::semLancamento($movimento)) {
            if ($this->formaAvistaSemMovimentoVaiParaCaixa($forma)) {
                $baixa->registrarEntradaCaixa(
                    valor: $valorFloat,
                    data: $hoje,
                    documento: $documento,
                    historico: $historico,
                    caixaContaId: $forma->conta_destino_id ? (int) $forma->conta_destino_id : $caixaGeral,
                    empresaId: $empresaId,
                    planoContaId: $planoVenda['id'] ?? null,
                    planoNome: $planoVenda['nome'] ?? null,
                );
            }

            return [];
        }

        if (FormaPagamentoDestino::vaiParaCaixa($movimento) || FormaPagamentoDestino::vaiParaDeposito($movimento)) {
            $baixa->registrarEntradaCaixa(
                valor: $valorFloat,
                data: $hoje,
                documento: $documento,
                historico: $historico,
                caixaContaId: $forma->conta_destino_id ? (int) $forma->conta_destino_id : $caixaGeral,
                empresaId: $empresaId,
                planoContaId: $planoVenda['id'] ?? null,
                planoNome: $planoVenda['nome'] ?? null,
            );

            return [];
        }

        $pdvMeta = [
            'forma' => $meta['forma'] !== '' ? $meta['forma'] : $label,
            'tipo' => $meta['tipo'] !== '' ? $meta['tipo'] : (string) ($forma->tipo ?? ''),
            'tipo_movimento' => $meta['tipo_movimento'] !== ''
                ? $meta['tipo_movimento']
                : (string) ($forma->tipo_movimento ?? ''),
        ];

        $precisaParcelas = PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdvMeta);

        if (! FormaPagamentoDestino::geraContasReceber($movimento) && ! $precisaParcelas) {
            return [];
        }

        $dias = $precisaParcelas
            ? $this->normalizarDias($tabelaPrazoDias)
            : $this->diasPadraoForma($forma);

        return $this->criarParcelasContaReceber(
            os: $os,
            forma: $forma,
            total: $valorFloat,
            dias: $dias,
            documentoBase: $documento,
            hoje: $hoje,
            empresaId: $empresaId,
            baixa: $baixa,
            chequeNumeros: PdvFinalizarPagamentosHelper::isFormaCheque($pdvMeta['forma'])
                || mb_strtolower($pdvMeta['tipo'], 'UTF-8') === 'cheque'
                ? ($chequeNumeros ?? [])
                : null,
        );
    }

    /**
     * @param  list<int>|null  $dias
     * @return list<int>
     */
    private function normalizarDias(?array $dias): array
    {
        $normalizados = collect($dias ?? [])
            ->map(fn ($d): int => (int) $d)
            ->filter(fn (int $d): bool => $d >= 0)
            ->values()
            ->all();

        return $normalizados !== [] ? $normalizados : [30];
    }

    /**
     * @return list<int>
     */
    private function diasPadraoForma(FormaPagamento $forma): array
    {
        $prazo = max(0, (int) ($forma->prazo_cartao ?: $forma->intervalo_parcelas ?: 0));

        return [$prazo > 0 ? $prazo : 30];
    }

    /**
     * @param  list<int>  $dias
     * @param  list<string>|null  $chequeNumeros
     * @return list<ContaReceber>
     */
    private function criarParcelasContaReceber(
        OrdemServico $os,
        FormaPagamento $forma,
        float $total,
        array $dias,
        string $documentoBase,
        string $hoje,
        ?int $empresaId,
        ContaReceberBaixaService $baixa,
        ?array $chequeNumeros = null,
    ): array {
        $n = count($dias);
        $parcelaBase = floor($total / $n * 100) / 100;
        $label = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');
        $emissao = Carbon::parse($hoje)->startOfDay();
        $criadas = [];

        foreach (array_values($dias) as $i => $dia) {
            $valor = $i === $n - 1
                ? round($total - $parcelaBase * ($n - 1), 2)
                : $parcelaBase;

            $parcelaLabel = $n > 1 ? ($i + 1).'/'.$n : '1/1';
            $historico = 'OS '.$os->numero.' ('.$label.')'
                .($n > 1 ? ' ('.$parcelaLabel.')' : '');

            $cheque = is_array($chequeNumeros)
                ? trim((string) ($chequeNumeros[$i] ?? ''))
                : '';

            if ($cheque !== '') {
                $historico .= ' CHQ '.$cheque;
            }

            $formaConta = $baixa->mapFormaConta($forma);

            $criadas[] = ContaReceber::query()->create([
                'empresa_id' => $empresaId,
                'numero' => ContaReceber::nextNumero(),
                'emissao' => $hoje,
                'historico' => $historico,
                'documento' => $n > 1 ? $documentoBase.'-'.($i + 1) : $documentoBase,
                'cliente_id' => (int) $os->cliente_id,
                'vencimento' => $emissao->copy()->addDays(max(0, (int) $dia))->toDateString(),
                'valor' => $valor,
                'forma' => $formaConta,
                ...ContaReceberJurosCarteira::atributosParaCreate($formaConta, $empresaId),
            ]);
        }

        return $criadas;
    }

    private function baixarPecas(OrdemServico $os, string $documento, ?int $empresaId): void
    {
        $stock = new PdvStockService();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        foreach ($os->itens as $item) {
            if (mb_strtoupper(trim((string) $item->tipo), 'UTF-8') !== 'P' || ! $item->product_id) {
                continue;
            }

            $product = $item->product ?? Product::query()->find($item->product_id);

            if (! $product instanceof Product) {
                continue;
            }

            $qtd = (float) $this->money((string) $item->qtd, 3);

            if ($qtd <= 0) {
                continue;
            }

            $stock->baixaItemVenda(
                $product,
                $qtd,
                null,
                null,
                $documento,
                null,
                $empresa,
                EstoqueMovimentacaoContext::make(
                    EstoqueMovimentacao::TIPO_VENDA,
                    empresaId: $empresaId,
                    origemTipo: 'ordem_servico',
                    origemId: (int) $os->id,
                    origemNumero: (string) $os->numero,
                ),
            );
        }
    }

    /**
     * PIX/dinheiro/deposito com tipo_movimento legado "nenhum" ainda entram no caixa (igual PDV à vista).
     */
    private function formaAvistaSemMovimentoVaiParaCaixa(FormaPagamento $forma): bool
    {
        $tipo = mb_strtolower(trim((string) ($forma->tipo ?? '')), 'UTF-8');
        $nome = mb_strtoupper(trim((string) ($forma->descricao ?? '')), 'UTF-8');

        return in_array($tipo, ['pix', 'dinheiro', 'deposito'], true)
            || str_contains($nome, 'PIX')
            || str_contains($nome, 'DINHEIRO')
            || str_contains($nome, 'ESPÉCIE')
            || str_contains($nome, 'ESPECIE')
            || str_contains($nome, 'DEPOSIT');
    }

    /**
     * @param  list<array{id: int, valor?: string, descricao?: string, forma?: string, tipo?: string, tipo_movimento?: string}>  $pagamentos
     * @param  list<array{forma: FormaPagamento, valor: string}>  $linhas
     * @param  list<int>|null  $tabelaPrazoDias
     * @return list<array{forma: string, valor: float, parcelas: list<array{dias: int, vencimento: string, valor: float}>|null}>
     */
    private function montarSnapshotPagamentos(array $pagamentos, array $linhas, ?array $tabelaPrazoDias, string $hoje): array
    {
        $out = [];
        $emissao = Carbon::parse($hoje)->startOfDay();

        foreach ($linhas as $linha) {
            /** @var FormaPagamento $forma */
            $forma = $linha['forma'];
            $label = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');
            $valor = (float) $linha['valor'];
            $meta = $this->metaPagamento($pagamentos, (int) $forma->id);
            $pdvMeta = [
                'forma' => $meta['forma'] !== '' ? $meta['forma'] : $label,
                'tipo' => $meta['tipo'] !== '' ? $meta['tipo'] : (string) ($forma->tipo ?? ''),
                'tipo_movimento' => $meta['tipo_movimento'] !== ''
                    ? $meta['tipo_movimento']
                    : (string) ($forma->tipo_movimento ?? ''),
            ];

            $entry = [
                'forma' => $label,
                'valor' => round($valor, 2),
                'parcelas' => null,
            ];

            if (PdvFinalizarPagamentosHelper::precisaParcelasCarne($pdvMeta)) {
                $dias = $this->normalizarDias($tabelaPrazoDias);
                $n = count($dias);
                $parcelaBase = floor($valor / $n * 100) / 100;
                $parcelas = [];

                foreach (array_values($dias) as $i => $dia) {
                    $valorParcela = $i === $n - 1
                        ? round($valor - $parcelaBase * ($n - 1), 2)
                        : $parcelaBase;
                    $parcelas[] = [
                        'dias' => max(0, (int) $dia),
                        'vencimento' => $emissao->copy()->addDays(max(0, (int) $dia))->format('d/m/Y'),
                        'valor' => $valorParcela,
                    ];
                }

                $entry['parcelas'] = $parcelas;
            }

            $out[] = $entry;
        }

        return $out;
    }

    private function eDinheiro(FormaPagamento $forma): bool
    {
        $tipo = mb_strtolower(trim((string) $forma->tipo), 'UTF-8');
        $nome = mb_strtoupper(trim((string) $forma->descricao), 'UTF-8');

        return $tipo === 'dinheiro' || str_contains($nome, 'DINHEIRO');
    }

    private function money(mixed $value, int $scale = 2): string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return $scale === 2 ? '0.00' : '0.'.str_repeat('0', $scale);
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        if (preg_match('/^-?\d+(\.\d+)?$/', $raw) !== 1) {
            return $scale === 2 ? '0.00' : '0.'.str_repeat('0', $scale);
        }

        return bcadd($raw, '0', $scale);
    }

    private function formatBr(string $value): string
    {
        $neg = str_starts_with($value, '-');
        $abs = $neg ? substr($value, 1) : $value;
        [$int, $frac] = array_pad(explode('.', $abs, 2), 2, '00');
        $int = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $int) ?? $int;

        return ($neg ? '-' : '').$int.','.str_pad(substr($frac, 0, 2), 2, '0');
    }
}
