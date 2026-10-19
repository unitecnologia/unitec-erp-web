<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\OrdemServicoItem;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\Person;
use App\Models\VendasParametro;
use App\Support\Erp\DocumentoBrasileiroValidator;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\Nfe\NfeOrdemServicoService;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\Fiscal\FiscalEmitenteUf;
use App\Support\Fiscal\NfceTerminalSequencia;
use App\Support\Fiscal\PdvNfceEmissionService;
use App\Support\Fiscal\PdvNfceFiscalPayloadBuilder;
use App\Support\Fiscal\PdvNfceTransmissaoService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * NFC-e das peças da OS — exclusivamente fiscal.
 *
 * Reutiliza PdvNfceEmissionService / PdvNfceTransmissaoService (mesmo motor do PDV e da
 * Regularização F9). Não toca estoque, caixa, contas a receber nem situação da OS.
 */
final class NfceOrdemServicoService
{
    public const STATUS_AUTORIZADA = 'autorizada';

    public const STATUS_REJEITADA = 'rejeitada';

    /** Status que documentam as peças e bloqueiam NF-e / nova NFC-e. */
    public const STATUS_DOCUMENTAM_PECAS = [
        PdvVendaNfce::STATUS_AUTORIZADA,
        PdvVendaNfce::STATUS_CONTINGENCIA,
        PdvVendaNfce::STATUS_DUPLICIDADE,
    ];

    /** Tentativa em andamento: F11 retransmite sem criar outro documento. */
    public const STATUS_RETRANSMITIVEIS = [
        PdvVendaNfce::STATUS_PENDENTE,
        PdvVendaNfce::STATUS_REJEITADA,
        PdvVendaNfce::STATUS_DUPLICIDADE,
        PdvVendaNfce::STATUS_CONTINGENCIA,
    ];

    public function __construct(
        private readonly PdvNfceEmissionService $emissionService = new PdvNfceEmissionService(),
        private readonly PdvNfceTransmissaoService $transmissaoService = new PdvNfceTransmissaoService(),
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
    ) {}

    /**
     * @return list<string>
     */
    public function motivosBloqueioConfiguracao(?Empresa $empresa = null): array
    {
        $empresa ??= ErpContext::currentEmpresa();

        if ($empresa === null) {
            return ['Nenhuma empresa selecionada.'];
        }

        return $this->payloadBuilder->motivosBloqueioEmissaoReal(
            VendasParametro::forEmpresa((int) $empresa->id),
            $empresa,
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
        );
    }

    public function motivoBloqueioEmissao(OrdemServico $ordem): ?string
    {
        $ordem->loadMissing(['cliente', 'itens.product', 'empresa', 'nfcePecasEmitida', 'nfePecasEmitida']);

        if (! in_array((string) $ordem->situacao, [
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_ENTREGUE,
        ], true)) {
            return 'NFC-e das peças só para OS finalizada.';
        }

        if ($ordem->possuiNfePecasEmitida()) {
            $numero = ltrim((string) ($ordem->nfePecasEmitida?->numero ?? ''), '0');

            return 'Esta OS já possui NF-e das peças'
                .($numero !== '' ? ' (nº '.$numero.')' : '')
                .'. Não emita NFC-e para as mesmas peças.';
        }

        $nfce = $ordem->relationLoaded('nfcePecasEmitida')
            ? $ordem->nfcePecasEmitida
            : $ordem->nfcePecasEmitida()->first();

        if ($nfce instanceof PdvVendaNfce && ! (bool) $nfce->simulada) {
            $status = (string) $nfce->status;

            if ($status === PdvVendaNfce::STATUS_AUTORIZADA) {
                return 'Esta OS já possui NFC-e das peças (nº '.($nfce->numero ?: '—').').';
            }

            if ($status === PdvVendaNfce::STATUS_DENEGADA) {
                return 'NFC-e nº '.($nfce->numero ?: '—').' com uso denegado pela SEFAZ. Regularize o cadastro; não será criada outra NFC-e automaticamente.';
            }

            if (
                ! in_array($status, self::STATUS_RETRANSMITIVEIS, true)
                && $status !== PdvVendaNfce::STATUS_CANCELADA
                && $status !== PdvVendaNfce::STATUS_INUTILIZADA
            ) {
                return 'Já existe NFC-e ('.$status.') vinculada a esta OS.';
            }
        }

        if (! NfeOrdemServicoService::osTemPecasParaNfe($ordem)) {
            return 'A OS não possui peças com produto para emitir NFC-e.';
        }

        $empresa = $this->resolveEmpresa($ordem);

        if ($empresa === null) {
            return 'Empresa não identificada para a OS.';
        }

        $config = $this->motivosBloqueioConfiguracao($empresa);

        if ($config !== []) {
            return $config[0];
        }

        // Destinatário PJ da OS: NFC-e não aceita CNPJ — orientar NF-e (não substituir por consumidor).
        if ($this->clienteOsTemCnpj($ordem->cliente)) {
            return 'Cliente com CNPJ: NFC-e não aceita CNPJ. Emita NF-e Peças (F10).';
        }

        return null;
    }

    /**
     * @param  array{person_id?: int|null, nome?: string, cpf?: string}|null  $consumidor
     *                                                                                     null/vazio = consumidor não identificado (regras do motor NFC-e)
     * @return array{
     *     status: string,
     *     mensagem: string,
     *     nfce_id: int|null,
     *     nfce_numero: int|null,
     *     pdv_venda_id: int|null,
     *     codigo: string|null
     * }
     */
    public function emitir(OrdemServico $ordem, ?array $consumidor = null, ?string $observacoes = null): array
    {
        $empresa = $this->resolveEmpresa($ordem);

        if ($empresa === null) {
            return $this->resultado(self::STATUS_REJEITADA, 'Empresa não identificada para a OS.');
        }

        try {
            $consumidorNorm = NfceRegularizacaoService::normalizarConsumidor([
                'person_id' => $consumidor['person_id'] ?? null,
                'nome' => $consumidor['nome'] ?? '',
                'cpf' => $consumidor['cpf'] ?? '',
            ]);
        } catch (DomainException $exception) {
            return $this->resultado(self::STATUS_REJEITADA, $exception->getMessage());
        }

        $obsUsuario = trim((string) ($observacoes ?? ''));

        try {
            // Trecho crítico curto: lock + prepara espelho fiscal. SEFAZ fora da transaction.
            $preparado = DB::transaction(function () use ($ordem, $consumidorNorm, $obsUsuario): array {
                $travada = OrdemServico::query()->whereKey($ordem->id)->lockForUpdate()->first();

                if (! $travada instanceof OrdemServico) {
                    throw new DomainException('Ordem de serviço não encontrada.');
                }

                $travada->load(['cliente', 'itens.product', 'empresa', 'nfcePecasEmitida', 'nfePecasEmitida']);

                $motivo = $this->motivoBloqueioEmissao($travada);

                if ($motivo !== null) {
                    throw new DomainException($motivo);
                }

                $existente = $this->nfceRetransmissivel($travada);
                $obsFiscal = $this->montarObservacoesFiscais($travada, $obsUsuario);

                if ($existente !== null) {
                    $existente->loadMissing('pdvVenda');
                    $this->aplicarConsumidorEObs($existente->pdvVenda, $consumidorNorm, $obsFiscal);

                    return [
                        'modo' => 'retransmitir',
                        'nfce_id' => (int) $existente->id,
                        'ordem_id' => (int) $travada->id,
                        'pdv_venda_id' => (int) $existente->pdv_venda_id,
                    ];
                }

                $pdvVenda = $this->criarVendaFiscalPecas($travada, $consumidorNorm, $obsFiscal);

                return [
                    'modo' => 'nova',
                    'pdv_venda_id' => (int) $pdvVenda->id,
                    'ordem_id' => (int) $travada->id,
                ];
            });
        } catch (DomainException $exception) {
            return $this->resultado(self::STATUS_REJEITADA, $exception->getMessage());
        }

        try {
            if (($preparado['modo'] ?? '') === 'retransmitir') {
                $nfce = PdvVendaNfce::query()->with('pdvVenda')->findOrFail((int) $preparado['nfce_id']);

                return $this->retransmitir($nfce, $empresa, (int) $preparado['ordem_id']);
            }

            $pdvVenda = PdvVenda::query()
                ->with(['itens.product', 'pagamentos', 'person'])
                ->findOrFail((int) $preparado['pdv_venda_id']);

            $parametros = VendasParametro::forEmpresa((int) $empresa->id);
            FiscalEmitenteUf::tryResolve($empresa, $parametros);

            try {
                $nfce = $this->emissionService->emitir(
                    $pdvVenda,
                    $empresa,
                    $parametros,
                    PdvFinalizarOperacao::NFCE_TRANSMITIR,
                    dataEmissao: now(),
                    permitirContingencia: false,
                );
            } catch (Throwable $exception) {
                // Garante vínculo OS↔NFC-e mesmo quando a SEFAZ rejeita com exception
                // (permite F11 retransmitir sem duplicar documento).
                $nfceExistente = PdvVendaNfce::query()->where('pdv_venda_id', (int) $pdvVenda->id)->first();
                if ($nfceExistente instanceof PdvVendaNfce) {
                    $this->vincularNfceAOrdem($nfceExistente, (int) $preparado['ordem_id']);
                }

                throw $exception;
            }

            $this->vincularNfceAOrdem($nfce, (int) $preparado['ordem_id']);

            return $this->finalizarResultado($nfce, (int) $pdvVenda->id);
        } catch (FiscalEngineException $exception) {
            $this->avancarNumeroAposDuplicidade($exception, $empresa);
            $uf = FiscalEmitenteUf::tryResolve($empresa);
            $resolvido = PdvNfceFiscalMensagens::resolver($exception, 'nfce', $uf);
            $nfceId = isset($preparado['pdv_venda_id'])
                ? (int) (PdvVendaNfce::query()->where('pdv_venda_id', (int) $preparado['pdv_venda_id'])->value('id') ?? 0)
                : 0;

            return $this->resultado(
                self::STATUS_REJEITADA,
                $this->mensagemFiscal($exception, $empresa),
                $nfceId > 0 ? $nfceId : null,
                null,
                isset($preparado['pdv_venda_id']) ? (int) $preparado['pdv_venda_id'] : null,
                $resolvido['codigo'] ?? $exception->sefazCodigo,
            );
        } catch (Throwable $exception) {
            Log::warning('NFC-e OS peças falhou', [
                'ordem_servico_id' => $ordem->id,
                'message' => $exception->getMessage(),
            ]);

            return $this->resultado(
                self::STATUS_REJEITADA,
                trim($exception->getMessage()) ?: $exception::class,
            );
        }
    }

    /**
     * @return array{status: string, mensagem: string, nfce_id: int|null, nfce_numero: int|null, pdv_venda_id: int|null, codigo: string|null}
     */
    private function retransmitir(PdvVendaNfce $nfce, Empresa $empresa, int $ordemId): array
    {
        try {
            $nfce = $this->transmissaoService->transmitir($nfce, $empresa);
        } catch (FiscalEngineException $exception) {
            $this->avancarNumeroAposDuplicidade($exception, $empresa);
            $uf = FiscalEmitenteUf::tryResolve($empresa);
            $resolvido = PdvNfceFiscalMensagens::resolver($exception, 'nfce', $uf);

            return $this->resultado(
                self::STATUS_REJEITADA,
                $this->mensagemFiscal($exception, $empresa),
                (int) $nfce->id,
                $nfce->numero !== null ? (int) $nfce->numero : null,
                (int) $nfce->pdv_venda_id,
                $resolvido['codigo'] ?? $exception->sefazCodigo,
            );
        }

        $this->vincularNfceAOrdem($nfce, $ordemId);

        return $this->finalizarResultado($nfce, (int) $nfce->pdv_venda_id);
    }

    private function vincularNfceAOrdem(PdvVendaNfce $nfce, int $ordemId): void
    {
        if ($ordemId <= 0 || ! ErpSchema::hasColumn('pdv_venda_nfce', 'ordem_servico_id')) {
            return;
        }

        if ((int) ($nfce->ordem_servico_id ?? 0) === $ordemId) {
            return;
        }

        $nfce->forceFill(['ordem_servico_id' => $ordemId])->save();
    }

    /**
     * @return array{status: string, mensagem: string, nfce_id: int|null, nfce_numero: int|null, pdv_venda_id: int|null, codigo: string|null}
     */
    private function finalizarResultado(PdvVendaNfce $nfce, int $pdvVendaId): array
    {
        if ($nfce->status !== PdvVendaNfce::STATUS_AUTORIZADA) {
            $mensagem = trim((string) ($nfce->motivo_rejeicao ?? ''))
                ?: 'NFC-e não autorizada ('.$nfce->status.').';

            return $this->resultado(
                self::STATUS_REJEITADA,
                $mensagem,
                (int) $nfce->id,
                $nfce->numero !== null ? (int) $nfce->numero : null,
                $pdvVendaId,
            );
        }

        PdvVenda::query()->whereKey($pdvVendaId)->update([
            'fiscal' => true,
            'nfce_operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
        ]);

        return $this->resultado(
            self::STATUS_AUTORIZADA,
            'NFC-e nº '.$nfce->numero.' autorizada.',
            (int) $nfce->id,
            (int) $nfce->numero,
            $pdvVendaId,
        );
    }

    private function nfceRetransmissivel(OrdemServico $ordem): ?PdvVendaNfce
    {
        $nfce = $ordem->relationLoaded('nfcePecasEmitida')
            ? $ordem->nfcePecasEmitida
            : $ordem->nfcePecasEmitida()->first();

        if (! $nfce instanceof PdvVendaNfce) {
            return null;
        }

        if ((bool) $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA) {
            return null;
        }

        return in_array((string) $nfce->status, self::STATUS_RETRANSMITIVEIS, true) ? $nfce : null;
    }

    /**
     * @param  array{person_id: int|null, nome: string, cpf: string}  $consumidor
     */
    private function criarVendaFiscalPecas(OrdemServico $ordem, array $consumidor, string $observacoesFiscais): PdvVenda
    {
        $userId = Auth::id();

        if (! $userId) {
            throw new DomainException('Usuário não identificado para emitir a NFC-e.');
        }

        $linhas = $this->montarLinhasPecas($ordem);
        $total = round(array_sum(array_map(static fn (array $l): float => $l['total'], $linhas)), 2);

        if ($total <= 0) {
            throw new DomainException('Total das peças inválido para NFC-e.');
        }

        $forma = $this->formaPagamentoPdv($ordem);
        $subtotal = round(array_sum(array_map(
            static fn (array $l): float => round($l['preco'] * $l['q'], 2),
            $linhas,
        )), 2);
        $desconto = max(0.0, round($subtotal - $total, 2));

        $dados = [
            'pdv_caixa_sessao_id' => null,
            'user_id' => $userId,
            'venda_id' => null,
            'person_id' => $consumidor['person_id'],
            'cpf_nota' => $consumidor['cpf'] !== '' ? $consumidor['cpf'] : null,
            'vendedor_id' => $ordem->vendedor_id,
            'vendedor_nome' => null,
            'numero' => max(1, (int) (preg_replace('/\D/', '', (string) ($ordem->numero ?? $ordem->id)) ?: $ordem->id)),
            'subtotal' => $subtotal,
            'desconto' => $desconto,
            'acrescimo' => 0,
            'total' => $total,
            'forma_pagamento' => $forma,
            'fiscal' => false,
            'nfce_operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
            'observacoes' => $observacoesFiscais !== '' ? $observacoesFiscais : null,
            'troco' => 0,
            'dinheiro' => 0,
            'situacao' => 'F',
            'fechado_em' => now(),
            'origem' => PdvVenda::ORIGEM_OS_NFCE_PECAS,
        ];

        if (ErpSchema::hasColumn('pdv_vendas', 'nome_nota')) {
            // Nome na nota só vale com CPF (mesmo critério do motor / Regularização).
            $dados['nome_nota'] = $consumidor['cpf'] !== '' && $consumidor['nome'] !== ''
                ? $consumidor['nome']
                : null;
        }

        $pdvVenda = PdvVenda::query()->create($dados);

        foreach ($linhas as $linha) {
            PdvVendaItem::query()->create([
                'pdv_venda_id' => $pdvVenda->id,
                'product_id' => $linha['product_id'],
                'product_grade_id' => null,
                'codigo' => $linha['codigo'],
                'descricao' => $linha['descricao'],
                'unidade' => $linha['unidade'],
                'quantidade' => $linha['q'],
                'preco_unitario' => $linha['preco'],
                'desconto' => $linha['desconto'],
                'acrescimo' => 0,
                'total' => $linha['total'],
            ]);
        }

        PdvVendaPagamento::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'forma' => $forma,
            'valor' => $total,
        ]);

        return $pdvVenda;
    }

    /**
     * @return list<array{product_id: int, codigo: string|null, descricao: string, unidade: string, q: float, preco: float, desconto: float, total: float}>
     */
    private function montarLinhasPecas(OrdemServico $ordem): array
    {
        $ordem->loadMissing('itens.product');
        $linhas = [];

        foreach ($ordem->itens as $item) {
            if (! $item instanceof OrdemServicoItem) {
                continue;
            }

            if (mb_strtoupper(trim((string) ($item->tipo ?? '')), 'UTF-8') !== 'P') {
                continue;
            }

            $product = $item->product;
            $productId = (int) ($item->product_id ?? 0);
            $q = round((float) ($item->qtd ?? 0), 3);

            if ($productId <= 0 || $q <= 0.0001 || $product === null) {
                continue;
            }

            $total = round((float) ($item->total ?? 0), 2);

            if ($total <= 0) {
                $total = round($q * (float) ($item->preco ?? 0), 2);
            }

            if ($total <= 0) {
                continue;
            }

            $preco = round($total / $q, 2);
            $descricao = trim((string) ($product->descricao ?? $item->discriminacao ?? $item->nome ?? ''));

            $linhas[] = [
                'product_id' => $productId,
                'codigo' => filled($product->codigo) ? (string) $product->codigo : null,
                'descricao' => mb_strtoupper($descricao !== '' ? $descricao : 'ITEM', 'UTF-8'),
                'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
                'q' => $q,
                'preco' => $preco,
                'desconto' => 0.0,
                'total' => $total,
            ];
        }

        if ($linhas === []) {
            throw new DomainException('Nenhuma peça da OS possui produto vinculado para NFC-e.');
        }

        return $linhas;
    }

    /**
     * Observações do usuário + referência da OS → infCpl via PdvNfceFiscalPayloadBuilder.
     */
    private function montarObservacoesFiscais(OrdemServico $ordem, string $obsUsuario): string
    {
        $referencia = 'NFC-E ORIGINADA DA OS Nº '.$this->formatNumeroOs($ordem).'.';
        $obsUsuario = trim($obsUsuario);

        return $obsUsuario !== '' ? $obsUsuario.' | '.$referencia : $referencia;
    }

    /**
     * @param  array{person_id: int|null, nome: string, cpf: string}  $consumidor
     */
    private function aplicarConsumidorEObs(?PdvVenda $pdvVenda, array $consumidor, string $observacoesFiscais): void
    {
        if (! $pdvVenda instanceof PdvVenda) {
            return;
        }

        $dados = [
            'person_id' => $consumidor['person_id'],
            'cpf_nota' => $consumidor['cpf'] !== '' ? $consumidor['cpf'] : null,
            'observacoes' => $observacoesFiscais !== '' ? $observacoesFiscais : null,
        ];

        if (ErpSchema::hasColumn('pdv_vendas', 'nome_nota')) {
            $dados['nome_nota'] = $consumidor['cpf'] !== '' && $consumidor['nome'] !== ''
                ? $consumidor['nome']
                : null;
        }

        $pdvVenda->forceFill($dados)->save();
    }

    private function clienteOsTemCnpj(?Person $cliente): bool
    {
        if ($cliente === null) {
            return false;
        }

        if (Person::isCodigoConsumidorFinal($cliente->codigo !== null ? (string) $cliente->codigo : null)) {
            return false;
        }

        return strlen(DocumentoBrasileiroValidator::digits((string) $cliente->cpf_cnpj)) === 14;
    }

    private function formaPagamentoPdv(OrdemServico $ordem): string
    {
        $raw = $ordem->faturamento_pagamentos;
        $forma = '';

        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $forma = trim((string) ($item['forma'] ?? ''));

                if ($forma !== '') {
                    break;
                }
            }
        }

        $n = mb_strtolower($forma, 'UTF-8');

        return match (true) {
            $n === '' => 'DINHEIRO',
            str_contains($n, 'pix') => 'PIX',
            str_contains($n, 'boleto') => 'BOLETO',
            str_contains($n, 'cheque') => 'CHEQUE',
            str_contains($n, 'débito') || str_contains($n, 'debito') => 'CARTAO DEBITO',
            str_contains($n, 'crédito') || str_contains($n, 'credito') || str_contains($n, 'cart') => 'CARTAO CREDITO',
            str_contains($n, 'prazo') || str_contains($n, 'credi') || str_contains($n, 'carteira') => 'CREDIARIO',
            default => mb_strtoupper($forma, 'UTF-8'),
        };
    }

    private function resolveEmpresa(OrdemServico $ordem): ?Empresa
    {
        if ($ordem->empresa instanceof Empresa) {
            return $ordem->empresa;
        }

        $empresaId = (int) ($ordem->empresa_id ?? ErpContext::currentEmpresaId() ?? 0);

        return $empresaId > 0 ? Empresa::query()->find($empresaId) : null;
    }

    private function formatNumeroOs(OrdemServico $ordem): string
    {
        $numero = trim((string) ($ordem->numero ?? ''));
        $digits = ltrim(preg_replace('/\D/', '', $numero) ?? '', '0');

        if ($digits !== '') {
            return $digits;
        }

        return $numero !== '' ? $numero : (string) ($ordem->codigo_legado ?: $ordem->id);
    }

    private function mensagemFiscal(FiscalEngineException $exception, ?Empresa $empresa = null): string
    {
        $resolvido = PdvNfceFiscalMensagens::resolver(
            $exception,
            'nfce',
            FiscalEmitenteUf::tryResolve($empresa),
        );
        $titulo = trim((string) $resolvido['titulo']);
        $corpo = trim((string) ($resolvido['corpo'] ?? ''));

        return $corpo !== '' && ! str_contains($titulo, $corpo) ? $titulo.' — '.$corpo : $titulo;
    }

    private function avancarNumeroAposDuplicidade(FiscalEngineException $exception, Empresa $empresa): void
    {
        $mensagem = mb_strtoupper($exception->getMessage(), 'UTF-8');

        if ($exception->sefazCodigo !== '539' && ! str_contains($mensagem, 'DUPLICIDADE')) {
            return;
        }

        $proximo = 2;
        $serie = null;

        if (preg_match('/CHNFE:\s*(\d{44})/i', $exception->getMessage().' '.$exception->sefazMotivo, $matches) === 1) {
            $proximo = ((int) substr($matches[1], 25, 9)) + 1;
            $serie = ((int) substr($matches[1], 22, 3)) ?: null;
        }

        try {
            NfceTerminalSequencia::ensureNumeroPeloMenos(
                TerminalResolver::make()->current(),
                $proximo,
                VendasParametro::forEmpresa((int) $empresa->id),
                $serie,
            );
        } catch (Throwable) {
        }
    }

    /**
     * @return array{status: string, mensagem: string, nfce_id: int|null, nfce_numero: int|null, pdv_venda_id: int|null, codigo: string|null}
     */
    private function resultado(
        string $status,
        string $mensagem,
        ?int $nfceId = null,
        ?int $nfceNumero = null,
        ?int $pdvVendaId = null,
        ?string $codigo = null,
    ): array {
        return [
            'status' => $status,
            'mensagem' => $mensagem,
            'nfce_id' => $nfceId,
            'nfce_numero' => $nfceNumero,
            'pdv_venda_id' => $pdvVendaId,
            'codigo' => $codigo,
        ];
    }
}
