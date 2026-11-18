<?php

namespace App\Support\Erp\Tef;

use App\Models\Terminal;
use App\Models\TefIntencao;
use App\Support\Erp\Tef\TefProviderId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Orquestra intenção → 1 POST → poll/recuperação. Nunca reenvia POST automaticamente.
 * Não estorna sozinho. Não grava venda (isso fica no PDV após status paga).
 */
final class SuperTefPaymentService
{
    public function __construct(
        private readonly ?SuperTefApiClient $client = null,
    ) {}

    /**
     * Bloqueia segunda cobrança aberta na mesma POS do terminal.
     * Paga sem venda ainda ocupa a POS (até reconciliação administrativa).
     */
    public function posOcupada(Terminal $terminal, int $posId, ?int $excetoIntencaoId = null): bool
    {
        $q = TefIntencao::query()
            ->where('terminal_id', (int) $terminal->id)
            ->where('pos_id', $posId)
            ->where('requer_reconciliacao', false)
            ->where(function ($w): void {
                $w->whereIn('status', [
                    TefIntencao::STATUS_CRIADA,
                    TefIntencao::STATUS_POST_ENVIADO,
                    TefIntencao::STATUS_PENDENTE,
                    TefIntencao::STATUS_INDETERMINADA,
                ])->orWhere(function ($paga): void {
                    $paga->where('status', TefIntencao::STATUS_PAGA)
                        ->whereNull('pdv_venda_id');
                })->orWhere(function ($falhaPos): void {
                    // POST já saiu: falha da API não prova ausência na Smart POS.
                    $falhaPos->where('status', TefIntencao::STATUS_FALHA)
                        ->where('post_enviado', true)
                        ->whereNull('pdv_venda_id');
                });
            });

        if ($excetoIntencaoId) {
            $q->where('id', '!=', $excetoIntencaoId);
        }

        return $q->exists();
    }

    /**
     * Reaproveita intenção EM ABERTO (sem novo POST) do MESMO cupom.
     * Nunca devolve paga: pagamento aprovado só vincula com fingerprint.
     * Sem fingerprint inequívoco → não reaproveita (evita colar em outro cupom de mesmo valor).
     */
    public function encontrarReaproveitavel(
        Terminal $terminal,
        float $amount,
        ?int $caixaSessaoId = null,
        ?string $cupomFingerprint = null,
    ): ?TefIntencao {
        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);
        $posId = (int) ($cfg['pos_id'] ?? 0);
        if ($posId <= 0) {
            return null;
        }

        $fp = trim((string) ($cupomFingerprint ?? ''));
        if ($fp === '') {
            return null;
        }

        $q = TefIntencao::query()
            ->where('terminal_id', (int) $terminal->id)
            ->where('pos_id', $posId)
            ->whereNull('pdv_venda_id')
            ->where('requer_reconciliacao', false)
            ->where('cupom_fingerprint', $fp)
            ->where(function ($st): void {
                $st->whereIn('status', [
                    TefIntencao::STATUS_CRIADA,
                    TefIntencao::STATUS_POST_ENVIADO,
                    TefIntencao::STATUS_PENDENTE,
                    TefIntencao::STATUS_INDETERMINADA,
                ])->orWhere(function ($falha): void {
                    $falha->where('status', TefIntencao::STATUS_FALHA)
                        ->where('post_enviado', true);
                });
            })
            ->whereRaw('ABS(amount - ?) < 0.005', [round($amount, 2)])
            ->orderByDesc('id');

        if ($caixaSessaoId) {
            $q->where(function ($s) use ($caixaSessaoId): void {
                $s->where('caixa_sessao_id', $caixaSessaoId)
                    ->orWhereNull('caixa_sessao_id');
            });
        }

        return $q->first();
    }

    /**
     * Pagamento aprovado sem venda que NÃO pertence ao cupom atual e ainda
     * exige ciência do operador (sem ciencia_reconciliacao_em).
     * Intenções já reconhecidas permanecem em recon admin, mas não reavisam.
     */
    public function pagamentoAprovadoIncompativelNaPos(
        Terminal $terminal,
        int $posId,
        string $cupomFingerprint,
    ): ?TefIntencao {
        if ($posId <= 0) {
            return null;
        }

        $fp = trim($cupomFingerprint);

        $q = TefIntencao::query()
            ->where('terminal_id', (int) $terminal->id)
            ->where('empresa_id', (int) $terminal->empresa_id)
            ->where('pos_id', $posId)
            ->where('status', TefIntencao::STATUS_PAGA)
            ->whereNull('pdv_venda_id')
            ->whereNull('ciencia_reconciliacao_em');

        if ($fp !== '') {
            $q->where(function ($w) use ($fp): void {
                $w->whereNull('cupom_fingerprint')
                    ->orWhere('cupom_fingerprint', '')
                    ->orWhere('cupom_fingerprint', '!=', $fp);
            });
        }

        return $q->orderByDesc('id')->first();
    }

    /**
     * Intenção que ocupa a POS (pendente/indeterminada/paga sem venda), se houver.
     */
    public function intencaoOcupanteNaPos(Terminal $terminal, int $posId, ?int $excetoIntencaoId = null): ?TefIntencao
    {
        $q = TefIntencao::query()
            ->where('terminal_id', (int) $terminal->id)
            ->where('pos_id', $posId)
            ->where('requer_reconciliacao', false)
            ->where(function ($w): void {
                $w->whereIn('status', [
                    TefIntencao::STATUS_CRIADA,
                    TefIntencao::STATUS_POST_ENVIADO,
                    TefIntencao::STATUS_PENDENTE,
                    TefIntencao::STATUS_INDETERMINADA,
                ])->orWhere(function ($paga): void {
                    $paga->where('status', TefIntencao::STATUS_PAGA)
                        ->whereNull('pdv_venda_id');
                })->orWhere(function ($falhaPos): void {
                    $falhaPos->where('status', TefIntencao::STATUS_FALHA)
                        ->where('post_enviado', true)
                        ->whereNull('pdv_venda_id');
                });
            })
            ->orderByDesc('id');

        if ($excetoIntencaoId) {
            $q->where('id', '!=', $excetoIntencaoId);
        }

        return $q->first();
    }

    /**
     * Reserva intenção paga sem vínculo inequívoco para reconciliação (libera a POS).
     */
    public function marcarReconciliacaoAdministrativa(TefIntencao $intencao, string $motivo): TefIntencao
    {
        $intencao->requer_reconciliacao = true;
        $msg = trim((string) $intencao->payment_message);
        $prefix = 'RECONCILIAR: '.trim($motivo);
        $intencao->payment_message = $msg === '' || ! str_starts_with($msg, 'RECONCILIAR:')
            ? mb_substr($prefix, 0, 191)
            : $msg;
        $intencao->save();

        return $intencao->fresh();
    }

    /**
     * Registra ciência do operador sobre esta intenção órfã (empresa+terminal+id).
     * Não estorna, não apaga e não conclui a reconciliação administrativa.
     */
    public function marcarCienciaReconciliacao(TefIntencao $intencao): TefIntencao
    {
        if (! $intencao->requerReconciliacao()) {
            $this->marcarReconciliacaoAdministrativa(
                $intencao,
                'ciência do operador para continuar vendas (order '.$intencao->order_id.').',
            );
            $intencao = $intencao->fresh();
        }

        if ($intencao->ciencia_reconciliacao_em === null) {
            $intencao->ciencia_reconciliacao_em = now();
            $intencao->save();
        }

        return $intencao->fresh();
    }

    /**
     * Persiste intenção ANTES de qualquer chamada à API.
     *
     * @param  array{
     *   empresa_id: int,
     *   terminal: Terminal,
     *   amount: float,
     *   caixa_sessao_id?: int|null,
     *   cupom_fingerprint?: string|null,
     *   transaction_type?: string,
     *   installment_count?: int,
     *   installment_type?: int,
     *   order_id?: string|null
     * }  $input
     */
    public function criarIntencao(array $input): TefIntencao
    {
        $terminal = $input['terminal'];
        if (! $terminal instanceof Terminal) {
            throw new RuntimeException('Terminal inválido para SuperTEF.');
        }

        if (! SuperTefPdvGate::terminalPronto($terminal)) {
            throw new RuntimeException('SuperTEF não está habilitado ou configurado neste PDV.');
        }

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: true);
        $posId = (int) $cfg['pos_id'];
        $amount = round((float) $input['amount'], 2);

        if ($amount <= 0) {
            throw new RuntimeException('Valor SuperTEF inválido.');
        }

        $orderId = trim((string) ($input['order_id'] ?? ''));
        if ($orderId === '') {
            $orderId = 'UNI-TEF-'.Str::upper((string) Str::ulid());
        }

        $fingerprint = trim((string) ($input['cupom_fingerprint'] ?? ''));
        $caixaSessaoId = isset($input['caixa_sessao_id']) ? (int) $input['caixa_sessao_id'] : null;
        $empresaId = (int) $input['empresa_id'];
        $transactionType = (string) ($input['transaction_type'] ?? '1');
        $installmentCount = max(1, (int) ($input['installment_count'] ?? 1));
        $installmentType = max(1, (int) ($input['installment_type'] ?? 1));

        // Serializa criação por POS: evita duas abas criando cobrança no mesmo slot.
        return DB::transaction(function () use (
            $terminal,
            $posId,
            $amount,
            $orderId,
            $fingerprint,
            $caixaSessaoId,
            $empresaId,
            $transactionType,
            $installmentCount,
            $installmentType,
        ): TefIntencao {
            TefIntencao::query()
                ->where('terminal_id', (int) $terminal->id)
                ->where('pos_id', $posId)
                ->lockForUpdate()
                ->get(['id']);

            if ($this->posOcupada($terminal, $posId)) {
                throw new RuntimeException('Já existe pagamento SuperTEF pendente nesta POS. Aguarde ou recupere a intenção.');
            }

            return TefIntencao::query()->create([
                'empresa_id' => $empresaId,
                'terminal_id' => (int) $terminal->id,
                'caixa_sessao_id' => $caixaSessaoId,
                'requer_reconciliacao' => false,
                'provedor' => TefProviderId::SUPERTEF,
                'order_id' => $orderId,
                'cupom_fingerprint' => $fingerprint !== '' ? $fingerprint : null,
                'pos_id' => $posId,
                'amount' => $amount,
                'transaction_type' => $transactionType,
                'installment_count' => $installmentCount,
                'installment_type' => $installmentType,
                'status' => TefIntencao::STATUS_CRIADA,
                'post_enviado' => false,
            ]);
        });
    }

    /**
     * Envia um único POST. Se já enviou, não reenvia — use consultar/recuperar.
     *
     * @return array{intencao: TefIntencao, message: string, transport_error: bool}
     */
    public function enviarCobranca(TefIntencao $intencao, Terminal $terminal): array
    {
        $intencao->refresh();

        if (! $intencao->podeEnviarPost()) {
            return [
                'intencao' => $intencao,
                'message' => 'POST já enviado ou intenção não está em criada. Use consulta/recuperação.',
                'transport_error' => false,
            ];
        }

        if ((int) $intencao->terminal_id !== (int) $terminal->id) {
            throw new RuntimeException('Intenção SuperTEF não pertence a este terminal.');
        }

        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: true);
        $chave = (string) ($cfg['cliente_chave'] ?? '');
        $posCfg = (int) ($cfg['pos_id'] ?? 0);

        if ($chave === '' || $posCfg !== (int) $intencao->pos_id) {
            throw new RuntimeException('Configuração SuperTEF do terminal divergente da intenção.');
        }

        if ($this->posOcupada($terminal, (int) $intencao->pos_id, (int) $intencao->id)) {
            throw new RuntimeException('POS ocupada por outra intenção pendente.');
        }

        $client = $this->client();
        $payload = [
            'cliente_chave' => $chave,
            'pos_id' => (int) $intencao->pos_id,
            'transaction_type' => (string) $intencao->transaction_type,
            'installment_count' => (int) $intencao->installment_count,
            'installment_type' => (int) $intencao->installment_type,
            'amount' => (float) $intencao->amount,
            'order_id' => (string) $intencao->order_id,
            'description' => 'PDV '.$intencao->order_id,
            'print_receipt' => false,
        ];

        // Marca envio antes da resposta HTTP para não reenviar em timeout.
        $intencao->post_enviado = true;
        $intencao->post_enviado_em = now();
        $intencao->status = TefIntencao::STATUS_POST_ENVIADO;
        $intencao->save();

        $res = $client->createPayment($payload);

        if ($res['transport_error']) {
            $intencao->status = TefIntencao::STATUS_INDETERMINADA;
            $intencao->payment_message = $res['message'];
            $intencao->save();

            return [
                'intencao' => $intencao->fresh(),
                'message' => $res['message'].' Não houve reenvio. Recupere por order_id.',
                'transport_error' => true,
            ];
        }

        if (! $res['ok']) {
            // Após post_enviado, erro HTTP/negócio não prova que a Smart POS não recebeu.
            // Indeterminada + POS ocupada: só recupera/cancela — nunca 2º POST automático.
            $intencao->status = TefIntencao::STATUS_INDETERMINADA;
            $intencao->payment_message = $res['message'];
            $intencao->raw_last = is_array($res['body'] ?? null) ? $res['body'] : ['message' => $res['message']];
            $intencao->save();

            return [
                'intencao' => $intencao->fresh(),
                'message' => $res['message'].' Não reenvie. Recupere por order_id ou cancele a cobrança.',
                'transport_error' => false,
            ];
        }

        $this->aplicarRespostaApi($intencao, $res['body'], $terminal);

        return [
            'intencao' => $intencao->fresh(),
            'message' => $res['message'],
            'transport_error' => false,
        ];
    }

    /**
     * Consulta por payment_uniqueid (poll ≥ 4s fica a cargo do chamador).
     */
    public function consultar(TefIntencao $intencao, Terminal $terminal): TefIntencao
    {
        $this->assertPertence($intencao, $terminal);

        $unique = (int) ($intencao->payment_uniqueid ?? 0);
        if ($unique <= 0) {
            return $this->recuperarPorOrderId($intencao, $terminal);
        }

        $res = $this->client()->getPaymentByUniqueId($unique);
        $intencao->last_poll_at = now();

        if ($res['transport_error'] || ! $res['ok']) {
            $intencao->payment_message = $res['message'];
            $intencao->save();

            return $intencao->fresh();
        }

        $this->aplicarRespostaApi($intencao, $res['body'], $terminal);

        return $intencao->fresh();
    }

    /**
     * Recuperação sem novo POST.
     */
    public function recuperarPorOrderId(TefIntencao $intencao, Terminal $terminal): TefIntencao
    {
        $this->assertPertence($intencao, $terminal);
        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: true);
        $chave = (string) ($cfg['cliente_chave'] ?? '');

        $res = $this->client()->findPaymentsByOrderId($chave, (string) $intencao->order_id);
        $intencao->last_poll_at = now();

        if ($res['transport_error'] || ! $res['ok']) {
            $intencao->payment_message = $res['message'];
            $intencao->save();

            return $intencao->fresh();
        }

        $items = $res['items'];
        if (count($items) === 0) {
            if ($intencao->post_enviado) {
                $intencao->status = TefIntencao::STATUS_INDETERMINADA;
                $intencao->payment_message = 'Nenhuma cobrança encontrada para o order_id após o POST.';
            }
            $intencao->save();

            return $intencao->fresh();
        }

        if (count($items) > 1) {
            $intencao->status = TefIntencao::STATUS_INDETERMINADA;
            $intencao->payment_message = 'Mais de um pagamento para o mesmo order_id. Não reenvie.';
            $intencao->raw_last = ['items_count' => count($items)];
            $intencao->save();

            return $intencao->fresh();
        }

        $this->aplicarRespostaApi($intencao, $items[0], $terminal);

        return $intencao->fresh();
    }

    /**
     * Cancela pendente (status API 1/3). Não estorna pago.
     * Confirma com GET; status 5 sozinho não basta sem contexto (uniqueid/order/POS).
     *
     * @return array{
     *   intencao: TefIntencao,
     *   message: string,
     *   ok: bool,
     *   already_paid: bool,
     *   unknown: bool
     * }
     */
    public function cancelarPendente(TefIntencao $intencao, Terminal $terminal): array
    {
        $intencao = $this->consultar($intencao, $terminal);

        if ($intencao->isPaga()) {
            return [
                'intencao' => $intencao,
                'message' => 'Pagamento já aprovado. Cancelamento pendente não se aplica; estorno exige fluxo próprio.',
                'ok' => false,
                'already_paid' => true,
                'unknown' => false,
            ];
        }

        if ($intencao->status === TefIntencao::STATUS_CANCELADA) {
            return [
                'intencao' => $intencao,
                'message' => 'Pagamento já cancelado.',
                'ok' => true,
                'already_paid' => false,
                'unknown' => false,
            ];
        }

        $unique = (int) ($intencao->payment_uniqueid ?? 0);
        if ($unique <= 0) {
            return [
                'intencao' => $intencao,
                'message' => 'Sem payment_uniqueid para cancelar.',
                'ok' => false,
                'already_paid' => false,
                'unknown' => (bool) $intencao->post_enviado,
            ];
        }

        $expectedOrder = (string) $intencao->order_id;
        $expectedPos = (int) $intencao->pos_id;

        $res = $this->client()->cancelPayment($unique);

        if ($res['transport_error']) {
            $intencao->status = TefIntencao::STATUS_INDETERMINADA;
            $intencao->payment_message = 'Cancelamento sem confirmação (rede). '.$res['message'];
            $intencao->save();

            return [
                'intencao' => $intencao->fresh(),
                'message' => $res['message'],
                'ok' => false,
                'already_paid' => false,
                'unknown' => true,
            ];
        }

        if (! $res['ok']) {
            // Corrida aprovação × cancelamento: confirma pelo GET antes de descartar.
            $confirmFail = $this->consultar($intencao->fresh(), $terminal);
            if ($confirmFail->isPaga()) {
                return [
                    'intencao' => $confirmFail,
                    'message' => 'Pagamento aprovado durante o cancelamento.',
                    'ok' => false,
                    'already_paid' => true,
                    'unknown' => false,
                ];
            }

            return [
                'intencao' => $confirmFail,
                'message' => $res['message'],
                'ok' => false,
                'already_paid' => false,
                'unknown' => false,
            ];
        }

        // Aplica corpo do PUT só se o contexto bater; depois confirma com GET.
        if ($this->respostaCancelamentoCompativel($res['body'], $unique, $expectedOrder, $expectedPos)) {
            $this->aplicarRespostaApi($intencao, $res['body'], $terminal);
        }

        $confirm = $this->consultar($intencao->fresh(), $terminal);

        if ($confirm->isPaga()) {
            return [
                'intencao' => $confirm,
                'message' => 'Pagamento aprovado durante o cancelamento.',
                'ok' => false,
                'already_paid' => true,
                'unknown' => false,
            ];
        }

        if (
            $confirm->status === TefIntencao::STATUS_CANCELADA
            && (int) ($confirm->payment_uniqueid ?? 0) === $unique
            && (string) $confirm->order_id === $expectedOrder
            && (int) $confirm->pos_id === $expectedPos
        ) {
            return [
                'intencao' => $confirm,
                'message' => $res['message'] !== '' ? $res['message'] : 'Pagamento cancelado.',
                'ok' => true,
                'already_paid' => false,
                'unknown' => false,
            ];
        }

        $confirm->status = TefIntencao::STATUS_INDETERMINADA;
        $confirm->payment_message = 'Cancelamento sem confirmação inequívoca após PUT/GET.';
        $confirm->save();

        return [
            'intencao' => $confirm->fresh(),
            'message' => 'Resultado do cancelamento indeterminado. Mantido para verificação.',
            'ok' => false,
            'already_paid' => false,
            'unknown' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function respostaCancelamentoCompativel(
        array $body,
        int $expectedUnique,
        string $expectedOrder,
        int $expectedPos,
    ): bool {
        $unique = (int) ($body['payment_uniqueid'] ?? 0);
        if ($unique > 0 && $unique !== $expectedUnique) {
            return false;
        }

        $order = is_array($body['payment_order'] ?? null) ? $body['payment_order'] : [];
        $data = is_array($body['payment_data'] ?? null) ? $body['payment_data'] : [];
        $orderApi = trim((string) ($order['order_id'] ?? ''));
        $posApi = (int) ($order['pos_id'] ?? $data['pos_id'] ?? 0);

        if ($orderApi !== '' && $orderApi !== $expectedOrder) {
            return false;
        }

        if ($posApi > 0 && $posApi !== $expectedPos) {
            return false;
        }

        $apiStatus = (int) ($body['payment_status'] ?? 0);

        // Status 5 só é aceito junto com o contexto da mesma cobrança.
        return $apiStatus === 5 && ($unique === 0 || $unique === $expectedUnique);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function aplicarRespostaApi(TefIntencao $intencao, array $body, Terminal $terminal): void
    {
        $order = is_array($body['payment_order'] ?? null) ? $body['payment_order'] : [];
        $data = is_array($body['payment_data'] ?? null) ? $body['payment_data'] : [];

        $posApi = (int) ($order['pos_id'] ?? $data['pos_id'] ?? 0);
        $orderApi = trim((string) ($order['order_id'] ?? ''));
        $amountApi = $order['amount'] ?? null;

        // Anti-hijack: não aceitar pagamento de outra POS/order.
        if ($posApi > 0 && $posApi !== (int) $intencao->pos_id) {
            $intencao->status = TefIntencao::STATUS_INDETERMINADA;
            $intencao->payment_message = 'POS da API diverge da intenção.';
            $intencao->raw_last = $body;
            $intencao->save();

            return;
        }

        if ($orderApi !== '' && $orderApi !== (string) $intencao->order_id) {
            $intencao->status = TefIntencao::STATUS_INDETERMINADA;
            $intencao->payment_message = 'order_id da API diverge da intenção.';
            $intencao->raw_last = $body;
            $intencao->save();

            return;
        }

        if ($amountApi !== null && is_numeric($amountApi)) {
            if (abs((float) $amountApi - (float) $intencao->amount) >= 0.005) {
                $intencao->status = TefIntencao::STATUS_INDETERMINADA;
                $intencao->payment_message = 'Valor da API diverge da intenção.';
                $intencao->raw_last = $body;
                $intencao->save();

                return;
            }
        }

        if ((int) $intencao->empresa_id !== (int) ($terminal->empresa_id ?? $intencao->empresa_id)) {
            // Terminal sem empresa_id: mantém o da intenção.
        }

        $unique = (int) ($body['payment_uniqueid'] ?? 0);
        if ($unique > 0) {
            $intencao->payment_uniqueid = $unique;
        }

        $apiStatus = (int) ($body['payment_status'] ?? 0);
        $intencao->payment_message = trim((string) ($body['payment_message'] ?? ''));
        $intencao->nsu = $this->nullableText($data['nsu'] ?? null);
        $intencao->autorizacao = $this->nullableText($data['authorization_code'] ?? null);
        $intencao->bandeira = $this->nullableText($data['brand'] ?? null);
        $cnpjAcq = preg_replace('/\D/', '', (string) ($data['acquirer_cnpj'] ?? '')) ?: '';
        if (TefIntencao::isAcquirerCnpjValido($cnpjAcq)) {
            $intencao->acquirer_cnpj = $cnpjAcq;
        }
        $intencao->raw_last = $body;
        $intencao->last_poll_at = now();

        $intencao->status = match ($apiStatus) {
            1, 3 => TefIntencao::STATUS_PENDENTE,
            4 => TefIntencao::STATUS_PAGA,
            5 => TefIntencao::STATUS_CANCELADA,
            6 => TefIntencao::STATUS_PENDENTE, // estorno solicitado — não é venda
            8 => TefIntencao::STATUS_ESTORNADA,
            default => $intencao->post_enviado
                ? TefIntencao::STATUS_INDETERMINADA
                : $intencao->status,
        };

        if ($intencao->status === TefIntencao::STATUS_PAGA && $intencao->pago_em === null) {
            $intencao->pago_em = now();
        }

        $intencao->save();
    }

    private function assertPertence(TefIntencao $intencao, Terminal $terminal): void
    {
        if ((int) $intencao->terminal_id !== (int) $terminal->id
            || (int) $intencao->empresa_id !== (int) $terminal->empresa_id) {
            throw new RuntimeException('Intenção SuperTEF não pertence a este terminal/empresa.');
        }
    }

    private function nullableText(mixed $v): ?string
    {
        $t = trim((string) ($v ?? ''));

        return $t !== '' ? $t : null;
    }

    private function client(): SuperTefApiClient
    {
        if ($this->client instanceof SuperTefApiClient) {
            return $this->client;
        }

        $token = SuperTefSofthouseToken::get();
        if ($token === '') {
            throw new RuntimeException('Token SuperTEF da software house não configurado.');
        }

        return new SuperTefApiClient($token);
    }
}
