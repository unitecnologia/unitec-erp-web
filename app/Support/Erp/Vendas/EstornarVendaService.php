<?php

namespace App\Support\Erp\Vendas;

use App\Models\ClienteCreditoMovimentacao;
use App\Models\DevolucaoVenda;
use App\Support\Erp\ClienteCreditoService;
use App\Models\Empresa;
use App\Models\EstoqueMovimentacao;
use App\Models\Nfe;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Product;
use App\Models\Venda;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\EstoqueMovimentacaoContext;
use App\Support\Erp\Pdv\PdvCaixaMovimentoService;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Pdv\PdvStockService;
use App\Support\Erp\Pdv\PdvVendaFinanceiroService;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use App\Support\Fiscal\PdvNfceCancelamentoService;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
use App\Support\Logistica\LogisticaVendaHookService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Estorno/cancelamento unificado de venda (lista, PDV, NFC-e).
 *
 * Efeito: estoque, CR, movimentos de caixa PDV, espelho retaguarda,
 * cancelamento NFC-e (quando aplicável), logística e log de operação.
 */
final class EstornarVendaService
{
    public const ORIGEM_LISTA_VENDAS = 'lista_vendas';

    public const ORIGEM_PDV = 'pdv';

    public const ORIGEM_NFCE_LISTA = 'nfce_lista';

    public const ORIGEM_NFE_LISTA = 'nfe_lista';

    public const ORIGEM_MONITOR_FV = 'monitor_fv';

    public const OPERACAO = 'ESTORNAR_VENDA';

    public function __construct(
        private readonly ErpOperacaoLogService $operacaoLog = new ErpOperacaoLogService(),
        private readonly PdvStockService $stockService = new PdvStockService(),
        private readonly PdvVendaFinanceiroService $financeiroService = new PdvVendaFinanceiroService(),
        private readonly PdvCaixaMovimentoService $caixaMovimentoService = new PdvCaixaMovimentoService(),
        private readonly PdvVendaRetaguardaMirrorService $retaguardaMirrorService = new PdvVendaRetaguardaMirrorService(),
        private readonly PdvNfceCancelamentoService $nfceCancelamentoService = new PdvNfceCancelamentoService(),
        private readonly LogisticaVendaHookService $logisticaHook = new LogisticaVendaHookService(),
        private readonly ForcaVendasFaturamentoService $forcaVendasFaturamento = new ForcaVendasFaturamentoService(),
    ) {}

    /**
     * @throws DomainException
     * @throws FiscalEngineException
     */
    public function fromVenda(
        Venda $venda,
        string $motivo,
        string $origem,
        ?Empresa $empresa = null,
        ?int $pdvCaixaSessaoId = null,
        bool $bloquearCancelamentoDocFiscal = false,
        ?callable $aoAvancar = null,
    ): EstornarVendaResult {
        $motivo = PdvEstornoMotivo::normalize($motivo);
        $minMotivo = $origem === self::ORIGEM_MONITOR_FV
            ? PdvEstornoMotivo::MIN_LENGTH_MONITOR_FV
            : PdvEstornoMotivo::MIN_LENGTH;
        $erroMotivo = PdvEstornoMotivo::validate($motivo, $minMotivo);

        if ($erroMotivo !== null) {
            throw new DomainException($erroMotivo);
        }

        $venda->loadMissing(['itens', 'pdvVenda.itens', 'pdvVenda.pagamentos', 'pdvVenda.nfce', 'forcaVendasOrder']);

        if ($venda->status === Venda::STATUS_CANCELADO) {
            return new EstornarVendaResult(
                vendaId: (int) $venda->id,
                pdvVendaId: $venda->pdvVenda?->id ? (int) $venda->pdvVenda->id : null,
                alreadyCancelled: true,
                plataforma: (string) ($venda->plataforma ?? 'erp'),
            );
        }

        if ($origem === self::ORIGEM_LISTA_VENDAS && $venda->temOrigemPdv()) {
            throw new DomainException(
                'Vendas do PDV devem ser canceladas no próprio PDV (Consulta de vendas → Estorno).',
            );
        }

        $this->assertSemDevolucaoQueBloqueiaEstorno($venda);
        $this->assertSemDocumentoFiscalAtivo($venda, $origem);

        if ($venda->forcaVendasOrder !== null && $venda->forcaVendasOrder->venda_id) {
            return $this->estornarForcaVendas($venda, $motivo, $origem, $aoAvancar);
        }

        if ($venda->pdvVenda !== null && ! $venda->pdvVenda->isRegularizacaoFiscal()) {
            return $this->estornarPdv(
                $venda->pdvVenda,
                $venda,
                $motivo,
                $origem,
                $empresa,
                $pdvCaixaSessaoId,
                $bloquearCancelamentoDocFiscal,
            );
        }

        return $this->estornarErpSimples($venda, $motivo, $origem);
    }

    /**
     * @throws DomainException
     * @throws FiscalEngineException
     */
    public function fromPdvVenda(
        PdvVenda $pdvVenda,
        string $motivo,
        string $origem,
        ?Empresa $empresa = null,
        ?int $pdvCaixaSessaoId = null,
        bool $bloquearCancelamentoDocFiscal = false,
    ): EstornarVendaResult {
        $motivo = PdvEstornoMotivo::normalize($motivo);
        $erroMotivo = PdvEstornoMotivo::validate($motivo);

        if ($erroMotivo !== null) {
            throw new DomainException($erroMotivo);
        }

        $pdvVenda->loadMissing(['itens', 'pagamentos', 'nfce', 'venda.forcaVendasOrder']);

        if ($pdvVenda->situacao === 'C') {
            return new EstornarVendaResult(
                vendaId: (int) ($pdvVenda->venda_id ?? 0),
                pdvVendaId: (int) $pdvVenda->id,
                alreadyCancelled: true,
                plataforma: Venda::PLATAFORMA_PDV,
            );
        }

        if ($pdvVenda->isRegularizacaoFiscal()) {
            return $this->cancelarNfceRegularizacao($pdvVenda, $motivo, $origem, $empresa);
        }

        $venda = $pdvVenda->venda;

        if ($venda === null && $pdvVenda->venda_id) {
            $venda = Venda::query()->find($pdvVenda->venda_id);
        }

        if ($venda === null) {
            $venda = $this->retaguardaMirrorService->espelhar($pdvVenda);
            $pdvVenda->refresh();
            $venda = Venda::query()->find($pdvVenda->venda_id);
        }

        if ($venda === null) {
            throw new DomainException('Não foi possível localizar a venda de retaguarda para estorno.');
        }

        $this->assertSemDevolucaoQueBloqueiaEstorno($venda);
        $this->assertSemDocumentoFiscalAtivo($venda, $origem);

        if ($venda->forcaVendasOrder !== null && $venda->forcaVendasOrder->venda_id) {
            return $this->estornarForcaVendas($venda, $motivo, $origem);
        }

        return $this->estornarPdv(
            $pdvVenda,
            $venda,
            $motivo,
            $origem,
            $empresa,
            $pdvCaixaSessaoId,
            $bloquearCancelamentoDocFiscal,
        );
    }

    /**
     * Mesmas travas de devolução e documento fiscal usadas no cancelamento do Monitor.
     *
     * @throws DomainException
     */
    public function assertSemImpedimentoFiscalOuDevolucao(Venda $venda): void
    {
        $this->assertSemDevolucaoQueBloqueiaEstorno($venda);
        $this->assertSemDocumentoFiscalAtivo($venda, self::ORIGEM_MONITOR_FV);
    }

    private function estornarForcaVendas(
        Venda $venda,
        string $motivo,
        string $origem,
        ?callable $aoAvancar = null,
    ): EstornarVendaResult {
        $order = $venda->forcaVendasOrder;

        if ($order === null) {
            throw new DomainException('Pedido Força de Vendas não encontrado.');
        }

        try {
            $this->forcaVendasFaturamento->estornar($order, $motivo, $aoAvancar);
        } catch (\RuntimeException $exception) {
            throw new DomainException($exception->getMessage(), 0, $exception);
        }

        $this->registrarLog(
            $venda,
            null,
            $motivo,
            $origem,
            null,
            'ok',
            'Cancelamento Força de Vendas (estoque, CR, caixa FV, logística).',
        );

        return new EstornarVendaResult(
            vendaId: (int) $venda->id,
            plataforma: Venda::PLATAFORMA_MOBILE,
        );
    }

    /**
     * @throws DomainException
     * @throws FiscalEngineException
     */
    private function estornarPdv(
        PdvVenda $pdvVenda,
        Venda $venda,
        string $motivo,
        string $origem,
        ?Empresa $empresa,
        ?int $pdvCaixaSessaoId,
        bool $bloquearCancelamentoDocFiscal,
    ): EstornarVendaResult {
        $pdvVenda->loadMissing(['itens', 'pagamentos', 'nfce']);

        $sessaoId = $pdvCaixaSessaoId
            ?? ($pdvVenda->pdv_caixa_sessao_id ? (int) $pdvVenda->pdv_caixa_sessao_id : null);

        if ($sessaoId === null || $sessaoId <= 0) {
            throw new DomainException('Sessão de caixa da venda não encontrada para registrar o estorno.');
        }

        // Valida e trava antes de qualquer HTTP à SEFAZ (evita nota cancelada + venda viva).
        DB::transaction(function () use ($pdvVenda, $venda): void {
            $pdvLocked = PdvVenda::query()->whereKey($pdvVenda->id)->lockForUpdate()->first();

            if ($pdvLocked === null) {
                throw new DomainException('Venda PDV não encontrada para estorno.');
            }

            if ($pdvLocked->situacao === 'C') {
                throw new DomainException('Esta venda já está cancelada.');
            }

            $vendaLocked = Venda::query()->whereKey($venda->id)->lockForUpdate()->first();

            if ($vendaLocked === null) {
                throw new DomainException('Venda de retaguarda não encontrada para estorno.');
            }

            if ($vendaLocked->status === Venda::STATUS_CANCELADO) {
                throw new DomainException('Esta venda já está cancelada.');
            }

            $this->assertSemDevolucaoQueBloqueiaEstorno($vendaLocked);

            $erroFinanceiro = $this->financeiroService->motivoBloqueioEstornoContasReceber($pdvLocked);

            if ($erroFinanceiro !== null) {
                throw new DomainException($erroFinanceiro);
            }
        });

        $protocoloCancelamento = null;

        try {
            $protocoloCancelamento = $this->cancelarNfceSeNecessario(
                $pdvVenda->fresh(['nfce']) ?? $pdvVenda,
                $empresa,
                $motivo,
                $bloquearCancelamentoDocFiscal,
            );

            DB::transaction(function () use ($pdvVenda, $venda, $motivo, $sessaoId): void {
                $pdvLocked = PdvVenda::query()->whereKey($pdvVenda->id)->lockForUpdate()->first();

                if ($pdvLocked === null) {
                    throw new DomainException('Venda PDV não encontrada para estorno.');
                }

                if ($pdvLocked->situacao === 'C') {
                    return;
                }

                $vendaLocked = Venda::query()->whereKey($venda->id)->lockForUpdate()->first();

                if ($vendaLocked === null) {
                    throw new DomainException('Venda de retaguarda não encontrada para estorno.');
                }

                $this->assertSemDevolucaoQueBloqueiaEstorno($vendaLocked);

                $pdvLocked->loadMissing(['itens', 'pagamentos']);

                $erroFinanceiro = $this->financeiroService->estornarContasReceber($pdvLocked);

                if ($erroFinanceiro !== null) {
                    throw new DomainException($erroFinanceiro);
                }

                (new ClienteCreditoService())->estornarUsosDaOrigem(
                    ClienteCreditoMovimentacao::ORIGEM_PDV,
                    (int) $pdvLocked->id,
                    Auth::id() ? (int) Auth::id() : null,
                    'Estorno da venda PDV #'.$pdvLocked->numero,
                );

                foreach ($pdvLocked->itens as $item) {
                    if (! $item->product_id) {
                        continue;
                    }

                    $product = Product::query()->find($item->product_id);

                    if ($product) {
                        $this->stockService->estornoItemVenda(
                            $product,
                            (float) $item->quantidade,
                            $item->product_grade_id ? (int) $item->product_grade_id : null,
                            $item->product_serial_id ? (int) $item->product_serial_id : null,
                            null,
                            EstoqueMovimentacaoContext::make(
                                EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                                empresaId: \App\Support\Erp\ErpContext::currentEmpresaId(),
                                origemTipo: 'venda',
                                origemId: (int) $vendaLocked->id,
                                origemNumero: $this->numeroVendaAmigavel($vendaLocked, $pdvLocked),
                            ),
                        );
                    }
                }

                $this->caixaMovimentoService->registrarSaidasEstornoFromModel(
                    $sessaoId,
                    $pdvLocked,
                    $pdvLocked->pagamentos,
                );

                $this->retaguardaMirrorService->estornar($pdvLocked);

                $pdvLocked->update([
                    'situacao' => 'C',
                    'motivo_estorno' => $motivo,
                ]);

                if ($vendaLocked->status !== Venda::STATUS_CANCELADO) {
                    $vendaLocked->update(['status' => Venda::STATUS_CANCELADO]);
                }

                $this->logisticaHook->onVendaCancelada($vendaLocked, $motivo);
            });
        } catch (DomainException $exception) {
            $msg = $exception->getMessage();

            if (filled($protocoloCancelamento)) {
                $msg .= ' Atenção: a NFC-e já foi cancelada na SEFAZ. Consulte o status fiscal antes de tentar novamente.';
            }

            $this->registrarLog(
                $venda,
                $pdvVenda,
                $motivo,
                $origem,
                $protocoloCancelamento,
                'erro',
                $msg,
            );

            throw new DomainException($msg, 0, $exception);
        }

        $pdvVenda->refresh();
        $venda->refresh();

        $this->registrarLog(
            $venda,
            $pdvVenda,
            $motivo,
            $origem,
            $protocoloCancelamento,
            'ok',
            'Estorno PDV (estoque, CR, caixa, NFC-e se aplicável, logística).',
        );

        return new EstornarVendaResult(
            vendaId: (int) $venda->id,
            pdvVendaId: (int) $pdvVenda->id,
            protocoloCancelamento: $protocoloCancelamento,
            plataforma: Venda::PLATAFORMA_PDV,
        );
    }

    /**
     * Pedido com documento fiscal ainda ativo (NF-e aberta/transmitida/contingência
     * ou NFC-e pendente/autorizada/contingência/simulada): tratar o fiscal antes.
     * Exceto quando a origem já é o cancelamento da NF-e/NFC-e.
     * No PDV a NFC-e é cancelada no próprio fluxo — não bloqueia NFC-e.
     *
     * @throws DomainException
     */
    private function assertSemDocumentoFiscalAtivo(Venda $venda, string $origem): void
    {
        if ($origem === self::ORIGEM_NFE_LISTA || $origem === self::ORIGEM_NFCE_LISTA) {
            return;
        }

        $nfe = Nfe::query()
            ->where('venda_id', (int) $venda->id)
            ->whereIn('status', [
                Nfe::STATUS_ABERTA,
                Nfe::STATUS_TRANSMITIDA,
                Nfe::STATUS_CONTINGENCIA,
            ])
            ->orderByDesc('id')
            ->first();

        if ($nfe !== null) {
            $rotulo = match ((string) $nfe->status) {
                Nfe::STATUS_ABERTA => 'aberta',
                Nfe::STATUS_CONTINGENCIA => 'em contingência',
                default => 'transmitida',
            };

            throw new DomainException(
                'Existe NF-e '.$rotulo.' (#'.($nfe->numero ?: $nfe->id).') vinculada a este pedido. '
                .'Cancele ou exclua o documento fiscal antes de cancelar o pedido.'
            );
        }

        // PDV cancela NFC-e dentro do estorno — não bloquear aqui.
        if ($origem === self::ORIGEM_PDV) {
            return;
        }

        $venda->loadMissing('pdvVenda.nfce');
        $nfce = $venda->pdvVenda?->nfce;

        if ($nfce === null) {
            return;
        }

        $statusNfce = (string) ($nfce->status ?? '');
        $bloqueantes = [
            PdvVendaNfce::STATUS_PENDENTE,
            PdvVendaNfce::STATUS_AUTORIZADA,
            PdvVendaNfce::STATUS_CONTINGENCIA,
            PdvVendaNfce::STATUS_SIMULADA,
        ];

        if (! in_array($statusNfce, $bloqueantes, true)) {
            return;
        }

        throw new DomainException(
            'Existe NFC-e ('.$statusNfce.') vinculada a este pedido. '
            .'Cancele ou exclua o documento fiscal antes de cancelar o pedido.'
        );
    }

    /**
     * Devolução finalizada já devolveu estoque/financeiro — estornar a venda novamente inflaria o estoque.
     *
     * @throws DomainException
     */
    private function assertSemDevolucaoQueBloqueiaEstorno(Venda $venda): void
    {
        $query = DevolucaoVenda::query()
            ->where('situacao', DevolucaoVenda::SITUACAO_FINALIZADA)
            ->where(function ($q) use ($venda): void {
                $q->where('venda_id', (int) $venda->id);

                $numero = trim((string) ($venda->numero ?? ''));
                if ($numero !== '') {
                    $q->orWhere(function ($q2) use ($numero): void {
                        $q2->whereNull('venda_id')->where('venda_numero', $numero);
                    });
                }
            });

        $dev = $query->orderByDesc('id')->first();

        if ($dev === null) {
            return;
        }

        throw new DomainException(
            'Não é possível cancelar esta venda: existe devolução #'.$dev->numero
            .' finalizada. Use a devolução para o ajuste de estoque/financeiro; cancelar a venda novamente duplicaria o estoque.'
        );
    }

    private function estornarErpSimples(Venda $venda, string $motivo, string $origem): EstornarVendaResult
    {
        DB::transaction(function () use ($venda, $motivo): void {
            $venda->loadMissing('itens');

            foreach ($venda->itens as $item) {
                if (! $item->product_id) {
                    continue;
                }

                $product = Product::query()->find($item->product_id);

                if ($product) {
                    $this->stockService->estornoItemVenda(
                        $product,
                        (float) $item->quantidade,
                        null,
                        null,
                        null,
                        EstoqueMovimentacaoContext::make(
                            EstoqueMovimentacao::TIPO_CANCELAMENTO_ESTORNO,
                            empresaId: $venda->empresa_id
                                ? (int) $venda->empresa_id
                                : \App\Support\Erp\ErpContext::currentEmpresaId(),
                            origemTipo: 'venda',
                            origemId: (int) $venda->id,
                            origemNumero: $venda->numero !== null ? (string) $venda->numero : null,
                        ),
                    );
                }
            }

            $venda->update(['status' => Venda::STATUS_CANCELADO]);
            $this->logisticaHook->onVendaCancelada($venda, $motivo);
        });

        $this->registrarLog(
            $venda,
            null,
            $motivo,
            $origem,
            null,
            'ok',
            'Cancelamento ERP (estoque e logística).',
        );

        return new EstornarVendaResult(
            vendaId: (int) $venda->id,
            plataforma: (string) ($venda->plataforma ?? Venda::PLATAFORMA_ERP),
        );
    }

    /**
     * @throws DomainException
     * @throws FiscalEngineException
     */
    private function cancelarNfceSeNecessario(
        PdvVenda $pdvVenda,
        ?Empresa $empresa,
        string $motivo,
        bool $bloquearCancelamentoDocFiscal,
    ): ?string {
        if (! $pdvVenda->fiscal) {
            return null;
        }

        $nfce = $pdvVenda->nfce;

        if ($nfce === null) {
            if ($bloquearCancelamentoDocFiscal) {
                throw new DomainException('Venda com documento fiscal não pode ser estornada sem NFC-e vinculada.');
            }

            return null;
        }

        if ($nfce->status === PdvVendaNfce::STATUS_CANCELADA) {
            return filled($nfce->protocolo_cancelamento)
                ? (string) $nfce->protocolo_cancelamento
                : null;
        }

        if ($nfce->status === PdvVendaNfce::STATUS_AUTORIZADA || $nfce->simulada) {
            $empresa ??= $this->resolveEmpresa($nfce);

            if ($empresa === null) {
                throw new DomainException('Empresa não configurada para cancelamento fiscal.');
            }

            $nfce = $this->nfceCancelamentoService->cancelar($pdvVenda, $empresa, $motivo);

            return filled($nfce->protocolo_cancelamento)
                ? (string) $nfce->protocolo_cancelamento
                : null;
        }

        if ($bloquearCancelamentoDocFiscal) {
            throw new DomainException('Venda com documento fiscal não pode ser estornada pelo PDV web.');
        }

        return null;
    }

    /**
     * NFC-e de regularização: a venda comercial pertence à origem (Tela de Venda, Força de Vendas…).
     * Cancela só o documento fiscal; a venda volta para a pendência de regularização.
     *
     * @throws DomainException
     * @throws FiscalEngineException
     */
    private function cancelarNfceRegularizacao(
        PdvVenda $pdvVenda,
        string $motivo,
        string $origem,
        ?Empresa $empresa,
    ): EstornarVendaResult {
        $nfce = $pdvVenda->nfce;

        if ($nfce === null) {
            throw new DomainException('NFC-e não encontrada para esta venda.');
        }

        $empresa ??= $this->resolveEmpresa($nfce);

        if ($empresa === null) {
            throw new DomainException('Empresa não configurada para cancelamento fiscal.');
        }

        $nfce = $this->nfceCancelamentoService->cancelar($pdvVenda, $empresa, $motivo);
        $protocolo = filled($nfce->protocolo_cancelamento) ? (string) $nfce->protocolo_cancelamento : null;
        $venda = $pdvVenda->venda_id ? Venda::query()->find($pdvVenda->venda_id) : null;

        if ($venda !== null) {
            $this->registrarLog(
                $venda,
                $pdvVenda,
                $motivo,
                $origem,
                $protocolo,
                'ok',
                'Cancelamento só fiscal (NFC-e de regularização). Venda, estoque e financeiro mantidos.',
            );
        }

        return new EstornarVendaResult(
            vendaId: (int) ($pdvVenda->venda_id ?? 0),
            pdvVendaId: (int) $pdvVenda->id,
            protocoloCancelamento: $protocolo,
            plataforma: (string) ($venda?->plataforma ?? Venda::PLATAFORMA_ERP),
            somenteFiscal: true,
        );
    }

    private function numeroVendaAmigavel(Venda $venda, ?PdvVenda $pdvVenda = null): string
    {
        $numero = ltrim((string) ($venda->numero ?? ''), '0');
        if ($numero !== '') {
            return $numero;
        }

        if ($pdvVenda?->numero !== null && (string) $pdvVenda->numero !== '') {
            return (string) $pdvVenda->numero;
        }

        return (string) $venda->id;
    }

    private function resolveEmpresa(?PdvVendaNfce $nfce): ?Empresa
    {
        if ($nfce?->empresa_id) {
            return Empresa::query()->find($nfce->empresa_id);
        }

        $empresaId = session('erp_empresa_id');

        if ($empresaId) {
            return Empresa::query()->find((int) $empresaId);
        }

        return null;
    }

    private function registrarLog(
        Venda $venda,
        ?PdvVenda $pdvVenda,
        string $motivo,
        string $origem,
        ?string $protocoloCancelamento,
        string $resultado,
        string $resumo,
    ): void {
        $this->operacaoLog->registrar(
            operacao: self::OPERACAO,
            resumo: $resumo,
            resultado: $resultado,
            origem: $origem,
            documentoTipo: 'venda',
            documentoId: (int) $venda->id,
            documentoNumero: (string) $venda->numero,
            detalhes: [
                'motivo' => $motivo,
                'pdv_venda_id' => $pdvVenda?->id,
                'pdv_numero' => $pdvVenda?->numero,
                'protocolo_cancelamento' => $protocoloCancelamento,
                'plataforma' => $venda->plataforma,
            ],
        );
    }
}
