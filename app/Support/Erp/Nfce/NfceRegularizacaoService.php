<?php

namespace App\Support\Erp\Nfce;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\PedidoItem;
use App\Models\Person;
use App\Models\Venda;
use App\Models\VendaItem;
use App\Models\VendasParametro;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\DocumentoBrasileiroValidator;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvFinalizarOperacao;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\Fiscal\NfceTerminalSequencia;
use App\Support\Fiscal\PdvNfceEmissionService;
use App\Support\Fiscal\PdvNfceFiscalPayloadBuilder;
use App\Support\Fiscal\VendaFiscalLock;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Regularização fiscal: emite NFC-e (1 venda = 1 NFC-e) para venda já finalizada.
 *
 * Exclusivamente fiscal — não toca estoque, caixa, contas a receber, comissão nem valores
 * da venda. A emissão usa o mesmo PdvNfceEmissionService do PDV (montagem, tributação,
 * assinatura, transmissão e gravação); aqui só se entrega a venda original a esse fluxo.
 */
final class NfceRegularizacaoService
{
    public const STATUS_AUTORIZADA = 'autorizada';

    public const STATUS_REJEITADA = 'rejeitada';

    public const STATUS_IGNORADA = 'ignorada';

    private const OPERACAO_LOG = 'REGULARIZAR_NFCE';

    private const ESPERA_TRAVA_SEGUNDOS = 2;

    /** NFC-e encerradas em registro de regularização: o registro é desvinculado (preservado) e outro é criado. */
    private const STATUS_NFCE_ENCERRADAS = [
        PdvVendaNfce::STATUS_CANCELADA,
        'inutilizada',
    ];

    public function __construct(
        private readonly PdvNfceEmissionService $emissionService = new PdvNfceEmissionService(),
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
    ) {}

    /**
     * @return list<string>
     */
    public function motivosBloqueioConfiguracao(Empresa $empresa): array
    {
        return $this->payloadBuilder->motivosBloqueioEmissaoReal(
            VendasParametro::forEmpresa((int) $empresa->id),
            $empresa,
            PdvFinalizarOperacao::NFCE_TRANSMITIR,
        );
    }

    /**
     * Validação antes da transmissão (sem SEFAZ). null = apta.
     */
    public function validar(int $vendaId, ?int $empresaId, bool $consumidorInformado = false): ?string
    {
        $venda = Venda::query()
            ->select(['id', 'numero', 'status', 'empresa_id', 'total', 'data', 'cliente_id'])
            ->find($vendaId);

        return $this->motivoBloqueioVenda($venda, $empresaId, $consumidorInformado);
    }

    /**
     * Consumidor informado na grade (cliente da base ou nome + CPF digitados).
     * Lista vazia = consumidor não identificado. NFC-e não aceita CNPJ.
     *
     * @param  array<string, mixed>  $dados
     * @return array{person_id: int|null, nome: string, cpf: string}
     *
     * @throws DomainException
     */
    public static function normalizarConsumidor(array $dados): array
    {
        $personId = (int) ($dados['person_id'] ?? 0);
        $nome = mb_substr(mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) ($dados['nome'] ?? '')) ?? ''), 'UTF-8'), 0, 60, 'UTF-8');
        $cpf = DocumentoBrasileiroValidator::digits((string) ($dados['cpf'] ?? ''));

        if (strlen($cpf) === 14) {
            throw new DomainException('NFC-e não aceita CNPJ. Para pessoa jurídica emita NF-e.');
        }

        if ($mensagem = DocumentoBrasileiroValidator::mensagemCpf($cpf)) {
            throw new DomainException($mensagem);
        }

        $person = $personId > 0
            ? Person::query()->whereKey($personId)->first(['id', 'codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'])
            : null;

        if ($personId > 0 && $person === null) {
            throw new DomainException('Cliente não encontrado no cadastro.');
        }

        if ($person !== null && Person::isCodigoConsumidorFinal($person->codigo !== null ? (string) $person->codigo : null)) {
            $person = null;
        }

        if ($person !== null) {
            $documento = DocumentoBrasileiroValidator::digits((string) $person->cpf_cnpj);

            if (strlen($documento) === 14) {
                throw new DomainException('Cliente com CNPJ: NFC-e não aceita CNPJ. Emita NF-e.');
            }

            if ($cpf === '' && DocumentoBrasileiroValidator::isValidCpf($documento)) {
                $cpf = $documento;
            }

            if ($nome === '') {
                $nome = mb_substr(mb_strtoupper(trim((string) ($person->nome_razao ?: $person->apelido_fantasia)), 'UTF-8'), 0, 60, 'UTF-8');
            }
        }

        if ($cpf === '' && ($nome !== '' || $person !== null)) {
            throw new DomainException('Informe o CPF do consumidor (NFC-e não identifica só pelo nome).');
        }

        return ['person_id' => $person?->id ? (int) $person->id : null, 'nome' => $nome, 'cpf' => $cpf];
    }

    /**
     * @param  array{person_id: int|null, nome: string, cpf: string}|null  $consumidor  null = mantém o cliente da venda
     * @return array{status: string, mensagem: string, nfce_numero: int|null}
     */
    public function emitir(int $vendaId, Empresa $empresa, ?array $consumidor = null): array
    {
        try {
            return VendaFiscalLock::executar(
                $vendaId,
                fn (): array => $this->emitirComTrava($vendaId, $empresa, $consumidor),
                self::ESPERA_TRAVA_SEGUNDOS,
            );
        } catch (FiscalEngineException $exception) {
            return $this->resultado(self::STATUS_IGNORADA, $exception->getMessage());
        }
    }

    /**
     * @param  array{person_id: int|null, nome: string, cpf: string}|null  $consumidor
     * @return array{status: string, mensagem: string, nfce_numero: int|null}
     */
    private function emitirComTrava(int $vendaId, Empresa $empresa, ?array $consumidor): array
    {
        try {
            // Trecho crítico curto: revalida no banco e prepara o registro fiscal. Sem SEFAZ aqui.
            $pdvVendaId = DB::transaction(fn (): int => $this->prepararVendaFiscal($vendaId, (int) $empresa->id, $consumidor));

            $venda = Venda::query()->select(['id', 'numero', 'data'])->find($vendaId);
            $pdvVenda = PdvVenda::query()
                ->with(['itens.product', 'pagamentos', 'person'])
                ->findOrFail($pdvVendaId);

            // Só em memória (vai para infCpl); a venda original não é alterada.
            $pdvVenda->observacoes = $this->observacoesComReferencia($pdvVenda, $venda);

            $nfce = $this->emissionService->emitir(
                $pdvVenda,
                $empresa,
                VendasParametro::forEmpresa((int) $empresa->id),
                PdvFinalizarOperacao::NFCE_TRANSMITIR,
                dataEmissao: now(),
                permitirContingencia: false,
            );
        } catch (DomainException $exception) {
            return $this->resultado(self::STATUS_REJEITADA, $exception->getMessage());
        } catch (FiscalEngineException $exception) {
            $this->avancarNumeroAposDuplicidade($exception, $empresa);
            $mensagem = $this->mensagemFiscal($exception);
            $this->registrarLog($vendaId, $empresa, 'erro', $mensagem);

            return $this->resultado(self::STATUS_REJEITADA, $mensagem);
        } catch (Throwable $exception) {
            Log::warning('Regularização NFC-e falhou', [
                'venda_id' => $vendaId,
                'message' => $exception->getMessage(),
            ]);
            $mensagem = trim($exception->getMessage()) ?: $exception::class;
            $this->registrarLog($vendaId, $empresa, 'erro', $mensagem);

            return $this->resultado(self::STATUS_REJEITADA, $mensagem);
        }

        if ($nfce->status !== PdvVendaNfce::STATUS_AUTORIZADA) {
            $mensagem = trim((string) ($nfce->motivo_rejeicao ?? '')) ?: 'NFC-e não autorizada ('.$nfce->status.').';

            return $this->resultado(self::STATUS_REJEITADA, $mensagem);
        }

        PdvVenda::query()->whereKey($pdvVendaId)->update(['fiscal' => true]);
        PdvVenda::query()->whereKey($pdvVendaId)->whereNull('nfce_operacao')
            ->update(['nfce_operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR]);

        $this->registrarLog(
            $vendaId,
            $empresa,
            'ok',
            'NFC-e '.$nfce->numero.' autorizada (protocolo '.($nfce->protocolo ?: '—').').',
            ['nfce_id' => $nfce->id, 'chave' => $nfce->chave],
        );

        return $this->resultado(
            self::STATUS_AUTORIZADA,
            'NFC-e nº '.$nfce->numero.' autorizada.',
            (int) $nfce->numero,
        );
    }

    /**
     * Executa dentro de transação com lock da venda: revalida e devolve o PdvVenda que alimenta o emissor.
     *
     * @throws DomainException
     */
    private function prepararVendaFiscal(int $vendaId, int $empresaId, ?array $consumidor = null): int
    {
        $venda = Venda::query()->whereKey($vendaId)->lockForUpdate()->first();

        if ($motivo = $this->motivoBloqueioVenda($venda, $empresaId, $consumidor !== null)) {
            throw new DomainException($motivo);
        }

        $pdvVendas = PdvVenda::query()
            ->where('venda_id', $vendaId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $alvo = $pdvVendas->first(fn (PdvVenda $pdv): bool => ! $pdv->isRegularizacaoFiscal())
            ?? $pdvVendas->first(fn (PdvVenda $pdv): bool => $pdv->isRegularizacaoFiscal());

        if ($alvo !== null) {
            $nfce = PdvVendaNfce::query()->where('pdv_venda_id', $alvo->id)->lockForUpdate()->first();

            // Nunca exclui NFC-e: registro, XML e chave permanecem como histórico fiscal.
            if ($nfce !== null && ! $this->isSimulada($nfce)) {
                $status = (string) $nfce->status;

                if (in_array($status, self::STATUS_NFCE_ENCERRADAS, true) && $alvo->isRegularizacaoFiscal()) {
                    // Mantém a NFC-e encerrada (XML/protocolo) no registro antigo, desvinculado da venda.
                    $alvo->forceFill([
                        'venda_id' => null,
                        'situacao' => 'C',
                        'observacoes' => 'Regularização '.($status === PdvVendaNfce::STATUS_CANCELADA ? 'cancelada' : 'inutilizada')
                            .' da venda nº '.$venda->numero.' (NFC-e nº '.$nfce->numero.').',
                    ])->save();
                    $alvo = null;
                } else {
                    throw new DomainException($this->mensagemNfceExistente($nfce));
                }
            }
        }

        $alvo ??= $this->criarVendaFiscal($venda);

        if ($consumidor !== null) {
            $this->aplicarConsumidor($alvo, $consumidor);
        }

        return (int) $alvo->id;
    }

    /**
     * Identificação do consumidor só no registro fiscal (pdv_vendas); a venda original não é alterada.
     *
     * @param  array{person_id: int|null, nome: string, cpf: string}  $consumidor
     */
    private function aplicarConsumidor(PdvVenda $pdvVenda, array $consumidor): void
    {
        $dados = ['cpf_nota' => $consumidor['cpf'] !== '' ? $consumidor['cpf'] : null];

        // Venda real do PDV mantém o cliente do caixa; só troca quando outro cliente da base foi escolhido.
        if ($pdvVenda->isRegularizacaoFiscal() || $consumidor['person_id'] !== null) {
            $dados['person_id'] = $consumidor['person_id'];
        }

        if (ErpSchema::hasColumn('pdv_vendas', 'nome_nota')) {
            $dados['nome_nota'] = $consumidor['cpf'] !== '' && $consumidor['nome'] !== '' ? $consumidor['nome'] : null;
        }

        $pdvVenda->forceFill($dados)->save();
    }

    /** NFC-e não identifica destinatário CNPJ: venda para pessoa jurídica requer NF-e. */
    private function clienteTemCnpj(Venda $venda, ?PdvVenda $pdvReal): bool
    {
        if ($pdvReal !== null && strlen(preg_replace('/\D/', '', (string) $pdvReal->cpf_nota) ?? '') === 14) {
            return true;
        }

        $ids = array_values(array_filter([(int) $venda->cliente_id, (int) ($pdvReal?->person_id ?? 0)]));

        if ($ids === []) {
            return false;
        }

        return Person::query()
            ->whereIn('id', $ids)
            ->get(['id', 'codigo', 'cpf_cnpj'])
            ->contains(fn (Person $person): bool => NfceRegularizacaoQuery::documentoRequerNfe(
                $person->cpf_cnpj,
                $person->codigo !== null ? (string) $person->codigo : null,
            ));
    }

    /** Situação fiscal anterior que precisa ser resolvida na tela NFC-e antes de qualquer nova emissão. */
    private function mensagemNfceExistente(PdvVendaNfce $nfce): string
    {
        $numero = 'NFC-e nº '.($nfce->numero ?: '—');

        return match ((string) $nfce->status) {
            PdvVendaNfce::STATUS_PENDENTE => $numero.' gravada com situação não confirmada na SEFAZ. Consulte (F4) ou transmita (F5) na aba Gravados; não será criada outra NFC-e.',
            PdvVendaNfce::STATUS_REJEITADA => $numero.' rejeitada. Corrija e transmita (F5) na aba Denegado da tela NFC-e; não será criada outra NFC-e.',
            PdvVendaNfce::STATUS_DUPLICIDADE => $numero.' em duplicidade (539). Resolva (F5) na aba Duplicidade da tela NFC-e; não será criada outra NFC-e.',
            PdvVendaNfce::STATUS_DENEGADA => $numero.' com uso denegado pela SEFAZ (aba Denegado). Regularize o cadastro; não será criada outra NFC-e automaticamente.',
            PdvVendaNfce::STATUS_CONTINGENCIA => $numero.' em contingência. Transmita (F5) na tela NFC-e.',
            PdvVendaNfce::STATUS_CANCELADA => 'Venda PDV com NFC-e cancelada. Use NF-e ou estorne a venda no PDV.',
            'inutilizada' => 'Venda PDV com NFC-e inutilizada. Emita NF-e para esta venda.',
            default => 'Já existe NFC-e ('.$nfce->status.') vinculada a esta venda.',
        };
    }

    /** Cupom simulado nunca foi à SEFAZ: não é documento fiscal e não impede a emissão real. */
    private function isSimulada(PdvVendaNfce $nfce): bool
    {
        return (bool) $nfce->simulada || (string) $nfce->status === PdvVendaNfce::STATUS_SIMULADA;
    }

    /**
     * @param  bool  $consumidorInformado  consumidor trocado na grade (já validado como CPF): ignora o CNPJ do cliente original
     */
    private function motivoBloqueioVenda(?Venda $venda, ?int $empresaId, bool $consumidorInformado = false): ?string
    {
        if ($venda === null) {
            return 'Venda não encontrada.';
        }

        if (! in_array((string) $venda->status, NfceRegularizacaoQuery::statusVendaRegularizaveis(), true)) {
            return 'Venda '.mb_strtolower(Venda::statusLabels()[(string) $venda->status] ?? (string) $venda->status, 'UTF-8')
                .' não pode ser regularizada.';
        }

        [$inicioMes, $fimMes] = NfceRegularizacaoQuery::periodoPermitido();
        $dataVenda = $venda->data?->toDateString();

        if ($dataVenda === null || $dataVenda < $inicioMes || $dataVenda > $fimMes) {
            return 'Só é permitido regularizar vendas do mês corrente.';
        }

        if (
            $empresaId
            && $venda->empresa_id
            && ErpSchema::hasColumn('vendas', 'empresa_id')
            && (int) $venda->empresa_id !== $empresaId
        ) {
            return 'Venda pertence a outra empresa.';
        }

        if (! NfceRegularizacaoQuery::semDocumentoValido(Venda::query()->whereKey($venda->id))->exists()) {
            return 'Venda já possui NFC-e/NF-e autorizada.';
        }

        if (Nfe::query()->where('venda_id', $venda->id)->where('status', Nfe::STATUS_ABERTA)->exists()) {
            return 'Existe NF-e em aberto vinculada. Exclua ou transmita a NF-e antes.';
        }

        if ((float) $venda->total <= 0) {
            return 'Venda sem valor.';
        }

        $pdvReal = PdvVenda::query()->comercial()->where('venda_id', $venda->id)->first(['id', 'situacao', 'person_id', 'cpf_nota']);

        if (! $consumidorInformado && $this->clienteTemCnpj($venda, $pdvReal)) {
            return NfceRegularizacaoQuery::LABEL_REQUER_NFE.': cliente com CNPJ. Emita NF-e para esta venda.';
        }

        $nfceStatus = DB::table('pdv_venda_nfce as nf')
            ->join('pdv_vendas as pv', 'pv.id', '=', 'nf.pdv_venda_id')
            ->where('pv.venda_id', $venda->id)
            ->where(fn ($q) => $q->where('nf.simulada', false)->orWhereNull('nf.simulada'))
            ->pluck('nf.status', 'pv.id')
            ->all();

        if (in_array(PdvVendaNfce::STATUS_PENDENTE, $nfceStatus, true)) {
            return 'NFC-e gravada aguardando transmissão. Transmita pela aba Gravados (F5); não será criada outra NFC-e.';
        }

        if (in_array(PdvVendaNfce::STATUS_DUPLICIDADE, $nfceStatus, true)) {
            return 'NFC-e em duplicidade (539). Resolva (F5) na aba Duplicidade da tela NFC-e; não será criada outra NFC-e.';
        }

        if (in_array(PdvVendaNfce::STATUS_DENEGADA, $nfceStatus, true)) {
            return 'NFC-e com uso denegado pela SEFAZ (aba Denegado). Regularize o cadastro antes de emitir outro documento.';
        }

        if (in_array(PdvVendaNfce::STATUS_REJEITADA, $nfceStatus, true)) {
            return 'NFC-e rejeitada. Corrija e transmita (F5) na aba Denegado da tela NFC-e; não será criada outra NFC-e.';
        }

        if (in_array(PdvVendaNfce::STATUS_CONTINGENCIA, $nfceStatus, true)) {
            return 'NFC-e em contingência. Transmita (F5) na tela NFC-e.';
        }

        if ($pdvReal !== null) {
            if ((string) $pdvReal->situacao === 'C') {
                return 'Venda PDV estornada.';
            }

            if (($nfceStatus[$pdvReal->id] ?? null) === PdvVendaNfce::STATUS_CANCELADA) {
                return 'NFC-e do PDV cancelada: emita NF-e ou estorne a venda no PDV.';
            }

            return PdvVendaItem::query()->where('pdv_venda_id', $pdvReal->id)->exists()
                ? null
                : 'Venda PDV sem itens.';
        }

        $itens = VendaItem::query()->where('venda_id', $venda->id);

        if (! (clone $itens)->exists()) {
            return 'Venda sem itens.';
        }

        if ((clone $itens)->whereNull('product_id')->exists()) {
            return 'Venda com item sem produto cadastrado.';
        }

        return null;
    }

    /**
     * Registro só fiscal (sem caixa) com a cópia exata da venda: itens, quantidades, preços,
     * descontos/acréscimos e total. Não baixa estoque nem gera financeiro.
     */
    private function criarVendaFiscal(Venda $venda): PdvVenda
    {
        $userId = Auth::id();

        if (! $userId) {
            throw new DomainException('Usuário não identificado para a regularização.');
        }

        $linhas = $this->montarLinhas($venda);
        $forma = trim((string) ($venda->forma_pagamento ?? '')) ?: 'DINHEIRO';
        $total = round((float) $venda->total, 2);

        $pdvVenda = PdvVenda::query()->create([
            'pdv_caixa_sessao_id' => null,
            'user_id' => $userId,
            'venda_id' => $venda->id,
            'person_id' => $venda->cliente_id,
            'cpf_nota' => null,
            'vendedor_id' => $venda->vendedor_id,
            'vendedor_nome' => $venda->vendedor_nome,
            'numero' => $this->numeroReferencia($venda),
            'subtotal' => round(array_sum(array_map(fn (array $l): float => $this->totalUnitario($l['g'], $l['q']), $linhas)), 2),
            'desconto' => 0,
            'acrescimo' => 0,
            'total' => $total,
            'forma_pagamento' => $forma,
            'fiscal' => false,
            'nfce_operacao' => PdvFinalizarOperacao::NFCE_TRANSMITIR,
            'troco' => 0,
            'dinheiro' => 0,
            'situacao' => 'F',
            'fechado_em' => $this->momentoOriginal($venda),
            'origem' => PdvVenda::ORIGEM_REGULARIZACAO_FISCAL,
        ]);

        foreach ($linhas as $linha) {
            PdvVendaItem::query()->create([
                'pdv_venda_id' => $pdvVenda->id,
                'product_id' => $linha['product_id'],
                'product_grade_id' => $linha['grade_id'],
                'codigo' => $linha['codigo'],
                'descricao' => $linha['descricao'],
                'unidade' => $linha['unidade'],
                'quantidade' => $linha['q'],
                'preco_unitario' => round($linha['g'] - $linha['d'] + $linha['a'], 2),
                'desconto' => $linha['d'],
                'acrescimo' => $linha['a'],
                'total' => $this->liquido($linha),
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
     * Itens de venda_itens (o que foi efetivamente vendido/baixado). Preço cheio e descrição/grade
     * vêm do pedido de origem quando existir; a diferença vira desconto/acréscimo do item.
     *
     * @return list<array{product_id: int, grade_id: int|null, codigo: string|null, descricao: string, unidade: string, q: float, g: float, d: float, a: float}>
     */
    private function montarLinhas(Venda $venda): array
    {
        $itens = VendaItem::query()
            ->where('venda_id', $venda->id)
            ->with('product:id,codigo,descricao,unidade')
            ->orderBy('id')
            ->get();

        $pedidoItens = [];
        $pedidoId = ErpSchema::hasTable('forca_vendas_orders')
            ? DB::table('forca_vendas_orders')->where('venda_id', $venda->id)->value('pedido_id')
            : null;

        if ($pedidoId) {
            $pedidoItens = PedidoItem::query()
                ->where('pedido_id', $pedidoId)
                ->orderBy('item')
                ->get(['id', 'product_id', 'product_grade_id', 'quantidade', 'preco_unitario', 'descricao'])
                ->all();
        }

        $linhas = [];
        $alvos = [];

        foreach ($itens as $item) {
            $product = $item->product;

            if (! $product) {
                throw new DomainException('Venda com item sem produto cadastrado.');
            }

            $q = round((float) $item->quantidade, 3);

            if ($q <= 0) {
                throw new DomainException('Venda com item de quantidade zerada.');
            }

            $liquido = round((float) $item->total, 2);
            if ($liquido <= 0) {
                $liquido = round($q * (float) $item->valor_item, 2);
            }

            $pedidoItem = null;
            foreach ($pedidoItens as $index => $candidato) {
                if ((int) $candidato->product_id === (int) $item->product_id
                    && abs((float) $candidato->quantidade - $q) < 0.0005) {
                    $pedidoItem = $candidato;
                    unset($pedidoItens[$index]);
                    break;
                }
            }

            $g = round((float) ($pedidoItem?->preco_unitario ?? $item->valor_item), 2);
            if ($g <= 0) {
                $g = round($liquido / $q, 2);
            }

            $descricao = trim((string) ($pedidoItem?->descricao ?? '')) ?: (string) $product->descricao;

            $linhas[] = [
                'product_id' => (int) $product->id,
                'grade_id' => $pedidoItem?->product_grade_id ? (int) $pedidoItem->product_grade_id : null,
                'codigo' => filled($product->codigo) ? (string) $product->codigo : null,
                'descricao' => mb_strtoupper($descricao, 'UTF-8'),
                'unidade' => mb_strtoupper((string) ($product->unidade ?: 'UN'), 'UTF-8'),
                'q' => $q,
                'g' => $g,
                'd' => 0.0,
                'a' => 0.0,
            ];
            $alvos[] = $liquido;
        }

        foreach ($linhas as $i => &$linha) {
            $diferenca = round($this->totalUnitario($linha['g'], $linha['q']) - $alvos[$i], 2);

            if ($diferenca > 0) {
                $linha['d'] = min($linha['g'], $this->unitarioMaisProximo($diferenca, $linha['q']));
            } elseif ($diferenca < 0) {
                $linha['a'] = $this->unitarioMaisProximo(-$diferenca, $linha['q']);
            }
        }
        unset($linha);

        $this->fecharTotal($linhas, round((float) $venda->total, 2));

        return $linhas;
    }

    /**
     * O XML da NFC-e exige vNF = Σ(vProd − vDesc + vOutro) dos itens. Distribui centavos de
     * arredondamento/desconto geral da venda nos itens até bater exatamente com o total original.
     *
     * @param  list<array<string, mixed>>  $linhas
     */
    private function fecharTotal(array &$linhas, float $totalVenda): void
    {
        $ordem = array_keys($linhas);
        usort($ordem, function (int $a, int $b) use ($linhas): int {
            $peso = fn (float $q): int => abs($q - 1.0) < 0.0005 ? 0 : (abs($q - round($q)) < 0.0005 ? 1 : 2);

            return [$peso($linhas[$a]['q']), $linhas[$a]['q']] <=> [$peso($linhas[$b]['q']), $linhas[$b]['q']];
        });

        for ($passada = 0; $passada < 3; $passada++) {
            foreach ($ordem as $i) {
                $residuo = round($totalVenda - array_sum(array_map(fn (array $l): float => $this->liquido($l), $linhas)), 2);

                if (abs($residuo) < 0.005) {
                    return;
                }

                $l = &$linhas[$i];
                $q = (float) $l['q'];

                if ($residuo < 0) {
                    if ($l['a'] > 0) {
                        $l['a'] = $this->unitarioMaisProximo(max(0.0, round($this->totalUnitario($l['a'], $q) + $residuo, 2)), $q);
                    } else {
                        $l['d'] = min($l['g'], $this->unitarioMaisProximo(round($this->totalUnitario($l['d'], $q) - $residuo, 2), $q));
                    }
                } elseif ($l['d'] > 0) {
                    $l['d'] = $this->unitarioMaisProximo(max(0.0, round($this->totalUnitario($l['d'], $q) - $residuo, 2)), $q);
                } else {
                    $l['a'] = $this->unitarioMaisProximo(round($this->totalUnitario($l['a'], $q) + $residuo, 2), $q);
                }
                unset($l);
            }
        }

        $residuo = round($totalVenda - array_sum(array_map(fn (array $l): float => $this->liquido($l), $linhas)), 2);

        if (abs($residuo) >= 0.005) {
            throw new DomainException(
                'Não foi possível reproduzir o total exato da venda nos itens (diferença R$ '
                .ErpMoney::formatBr(abs($residuo)).').'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function liquido(array $linha): float
    {
        $q = (float) $linha['q'];

        return round(
            $this->totalUnitario((float) $linha['g'], $q)
            - $this->totalUnitario((float) $linha['d'], $q)
            + $this->totalUnitario((float) $linha['a'], $q),
            2,
        );
    }

    private function totalUnitario(float $unitario, float $quantidade): float
    {
        return round($unitario * $quantidade, 2);
    }

    /**
     * Valor unitário (2 casas, como gravado em pdv_venda_itens) cujo total na quantidade fica mais perto do alvo.
     */
    private function unitarioMaisProximo(float $alvo, float $quantidade): float
    {
        if ($alvo <= 0 || $quantidade <= 0) {
            return 0.0;
        }

        $base = round($alvo / $quantidade, 2);
        $melhor = $base;
        $melhorErro = INF;

        foreach ([0, -0.01, 0.01, -0.02, 0.02, -0.03, 0.03] as $passo) {
            $unitario = round($base + $passo, 2);

            if ($unitario < 0) {
                continue;
            }

            $erro = abs($this->totalUnitario($unitario, $quantidade) - $alvo);

            if ($erro < 0.005) {
                return $unitario;
            }

            if ($erro < $melhorErro) {
                $melhorErro = $erro;
                $melhor = $unitario;
            }
        }

        return $melhor;
    }

    private function numeroReferencia(Venda $venda): int
    {
        $digits = preg_replace('/\D/', '', (string) $venda->numero) ?? '';
        $numero = $digits !== '' ? (int) ltrim($digits, '0') : 0;

        return $numero > 0 && $numero <= 4294967295 ? $numero : (int) $venda->id;
    }

    private function momentoOriginal(Venda $venda): Carbon
    {
        $data = $venda->data?->toDateString() ?? ErpTimezone::today();
        $hora = filled($venda->hora) ? substr((string) $venda->hora, 0, 8) : '12:00:00';

        try {
            $local = Carbon::parse($data.' '.$hora, ErpTimezone::DEFAULT);
        } catch (Throwable) {
            $local = Carbon::parse($data.' 12:00:00', ErpTimezone::DEFAULT);
        }

        return $local->setTimezone((string) config('app.timezone', 'UTC'));
    }

    private function observacoesComReferencia(PdvVenda $pdvVenda, ?Venda $venda): string
    {
        $numero = $venda?->numero ?? (string) $pdvVenda->numero;
        $data = $venda?->data?->format('d/m/Y')
            ?? ($pdvVenda->fechado_em ? ErpTimezone::toLocal($pdvVenda->fechado_em)->format('d/m/Y') : '');
        $referencia = 'Regularização fiscal ref. venda nº '.$numero.($data !== '' ? ' de '.$data : '').'.';
        $obs = trim((string) ($pdvVenda->observacoes ?? ''));

        return $obs !== '' ? $obs.' | '.$referencia : $referencia;
    }

    private function mensagemFiscal(FiscalEngineException $exception): string
    {
        $resolvido = PdvNfceFiscalMensagens::resolver($exception);
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
     * @param  array<string, mixed>  $detalhes
     */
    private function registrarLog(int $vendaId, Empresa $empresa, string $resultado, string $resumo, array $detalhes = []): void
    {
        try {
            (new ErpOperacaoLogService())->registrar(
                operacao: self::OPERACAO_LOG,
                resumo: mb_substr($resumo, 0, 250, 'UTF-8'),
                resultado: $resultado,
                origem: 'nfce_regularizacao',
                documentoTipo: 'venda',
                documentoId: $vendaId,
                documentoNumero: (string) (Venda::query()->whereKey($vendaId)->value('numero') ?? ''),
                detalhes: $detalhes,
                empresaId: (int) $empresa->id,
            );
        } catch (Throwable) {
        }
    }

    /**
     * @return array{status: string, mensagem: string, nfce_numero: int|null}
     */
    private function resultado(string $status, string $mensagem, ?int $nfceNumero = null): array
    {
        return ['status' => $status, 'mensagem' => $mensagem, 'nfce_numero' => $nfceNumero];
    }
}
