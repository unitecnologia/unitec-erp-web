<?php

namespace App\Support\Pix;

use App\Models\CaixaLancamento;
use App\Models\PdvVenda;
use App\Models\PdvVendaPagamento;
use App\Models\PixCobranca;
use App\Support\Erp\ContaReceberPedidoExibicao;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberBaixaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * PIX Ailos do PDV (QR na tela) → entrada individual na conta BANCO vinculada.
 * Idempotente. Não cobre PIX manual, offline nem SuperTEF.
 */
final class PixPdvLivroCaixaService
{
    public const DOC_PREFIX = 'PIX-PDV-';

    public static function documentoDoPagamento(int $pagamentoId): string
    {
        return mb_substr(self::DOC_PREFIX.$pagamentoId, 0, 40);
    }

    /**
     * Número da venda PDV só para a coluna Pedido.
     * O documento PIX-PDV-{pagamento} continua gravado.
     */
    public static function numeroPedidoExibicao(?string $documento, ?string $historico): ?string
    {
        $documento = trim((string) $documento);
        if ($documento === '' || ! str_starts_with($documento, self::DOC_PREFIX)) {
            return null;
        }

        if (preg_match('/^\s*VENDA PDV #(\d+)/iu', (string) $historico, $matches) !== 1) {
            return null;
        }

        return ContaReceberPedidoExibicao::formatarNumeroVenda($matches[1]);
    }

    public static function jaLancadoPagamento(int $pagamentoId): bool
    {
        if ($pagamentoId <= 0 || ! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return false;
        }

        return CaixaLancamento::query()
            ->where('documento', self::documentoDoPagamento($pagamentoId))
            ->exists();
    }

    /**
     * Vendas PDV cujo PIX Ailos já está no Livro da conta BANCO.
     *
     * @param  list<int>  $pdvVendaIds
     * @return list<int>
     */
    public static function pdvVendaIdsJaNoBanco(array $pdvVendaIds): array
    {
        $pdvVendaIds = array_values(array_unique(array_filter(
            array_map(static fn ($id): int => (int) $id, $pdvVendaIds),
            static fn (int $id): bool => $id > 0,
        )));

        if ($pdvVendaIds === [] || ! Schema::hasTable('pdv_venda_pagamentos')) {
            return [];
        }

        $pags = PdvVendaPagamento::query()
            ->whereIn('pdv_venda_id', $pdvVendaIds)
            ->where('forma', 'like', '%PIX%')
            ->limit(500)
            ->get(['id', 'pdv_venda_id']);

        if ($pags->isEmpty()) {
            return [];
        }

        $docs = $pags
            ->map(fn (PdvVendaPagamento $p): string => self::documentoDoPagamento((int) $p->id))
            ->all();

        $lancados = CaixaLancamento::query()
            ->whereIn('documento', $docs)
            ->limit(500)
            ->pluck('documento')
            ->map(fn ($d): string => (string) $d)
            ->all();

        if ($lancados === []) {
            return [];
        }

        $ids = [];
        foreach ($pags as $pag) {
            if (in_array(self::documentoDoPagamento((int) $pag->id), $lancados, true)) {
                $ids[] = (int) $pag->pdv_venda_id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function vincularELancar(
        int $cobrancaId,
        PdvVenda $venda,
        PdvVendaPagamento $pagamento,
        ?int $vendaRetaguardaId = null,
    ): ?CaixaLancamento {
        if ($cobrancaId <= 0) {
            return null;
        }

        $cobranca = PixCobranca::query()->find($cobrancaId);
        if (! $cobranca) {
            return null;
        }

        $updates = [];
        if (Schema::hasColumn('pix_cobrancas', 'pdv_venda_id')) {
            $updates['pdv_venda_id'] = (int) $venda->id;
        }
        if (Schema::hasColumn('pix_cobrancas', 'pdv_venda_pagamento_id')) {
            $updates['pdv_venda_pagamento_id'] = (int) $pagamento->id;
        }
        if ($vendaRetaguardaId && (int) ($cobranca->venda_id ?? 0) <= 0) {
            $updates['venda_id'] = (int) $vendaRetaguardaId;
        }

        if ($updates !== []) {
            $cobranca->forceFill($updates)->save();
        }

        return $this->lancarSeConfirmado($cobranca->fresh() ?? $cobranca);
    }

    public function lancarSeConfirmado(PixCobranca $cobranca): ?CaixaLancamento
    {
        if (! $cobranca->isPago()) {
            return null;
        }

        if (mb_strtolower(trim((string) ($cobranca->provedor ?? '')), 'UTF-8') !== 'ailos') {
            return null;
        }

        $pagamentoId = (int) ($cobranca->pdv_venda_pagamento_id ?? 0);
        if ($pagamentoId <= 0) {
            return null;
        }

        $empresaId = (int) ($cobranca->empresa_id ?? 0);
        $contaId = PixContaBancariaVinculo::contaIdDaEmpresa($empresaId);
        if ($contaId === null) {
            return null;
        }

        $documento = self::documentoDoPagamento($pagamentoId);

        return DB::transaction(function () use ($cobranca, $pagamentoId, $empresaId, $contaId, $documento): ?CaixaLancamento {
            PixCobranca::query()->whereKey($cobranca->id)->lockForUpdate()->first();

            $existente = CaixaLancamento::query()
                ->where('documento', $documento)
                ->first();

            if ($existente) {
                return $existente;
            }

            $valor = round((float) $cobranca->valor, 2);
            if ($valor <= 0) {
                return null;
            }

            $pagamento = PdvVendaPagamento::query()->find($pagamentoId);
            $venda = $pagamento?->venda;
            $numero = (string) ($venda?->numero ?? $pagamento?->pdv_venda_id ?? '');
            $txid = trim((string) ($cobranca->txid ?: $cobranca->provider_ref ?: ''));
            $historico = 'VENDA PDV #'.$numero.' PIX';
            if ($txid !== '') {
                $historico .= ' '.$txid;
            }

            $emissao = $cobranca->pago_em
                ? ErpTimezone::toLocal($cobranca->pago_em)->toDateString()
                : ErpTimezone::toLocal()->toDateString();

            $plano = EmpresaParametros::planoVendaLancamento($empresaId > 0 ? $empresaId : null);

            try {
                app(ContaReceberBaixaService::class)->registrarEntradaCaixa(
                    valor: $valor,
                    data: $emissao,
                    documento: $documento,
                    historico: $historico,
                    caixaContaId: $contaId,
                    empresaId: $empresaId > 0 ? $empresaId : null,
                    planoContaId: $plano['id'] ?? null,
                    planoNome: $plano['nome'] ?? null,
                );
            } catch (InvalidArgumentException $e) {
                if (! str_contains($e->getMessage(), 'CAIXA FECHADO')) {
                    throw $e;
                }

                $this->lancarDiretoNoBanco(
                    $valor,
                    $emissao,
                    $documento,
                    $historico,
                    $contaId,
                    $empresaId,
                    $plano,
                );
            }

            return CaixaLancamento::query()->where('documento', $documento)->first();
        });
    }

    /**
     * @param  array{id: int, nome: string}|null  $plano
     */
    private function lancarDiretoNoBanco(
        float $valor,
        string $emissao,
        string $documento,
        string $historico,
        int $contaId,
        int $empresaId,
        ?array $plano,
    ): void {
        if (! Schema::hasTable((new CaixaLancamento)->getTable())) {
            return;
        }

        $payload = [
            'codigo' => CaixaLancamento::nextCodigo(),
            'emissao' => $emissao,
            'documento' => mb_substr($documento, 0, 40),
            'historico' => mb_substr($historico, 0, 180),
            'plano_contas' => $plano['nome'] ?? null,
            'plano_conta_id' => $plano['id'] ?? null,
            'caixa_conta_id' => $contaId,
            'entrada' => $valor,
            'saida' => 0,
        ];

        if ($empresaId > 0 && Schema::hasColumn((new CaixaLancamento)->getTable(), 'empresa_id')) {
            $payload['empresa_id'] = $empresaId;
        }

        CaixaLancamento::query()->create($payload);
    }
}
