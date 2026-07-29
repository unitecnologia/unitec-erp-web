<?php

namespace App\Support\Pdv;

use App\Models\Empresa;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Support\Erp\Pdv\PdvCaixaMovimentoService;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Pdv\PdvStockService;
use App\Support\Erp\Pdv\PdvVendaFinanceiroService;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Importa as vendas feitas no mini-PDV offline de volta para o ERP central
 * (Fase 3). A idempotência é garantida pelo `uuid` da venda (único em
 * pdv_vendas): reenvios não duplicam venda, itens, pagamentos, NFC-e nem
 * baixam estoque duas vezes.
 */
class PdvRetornoService
{
    public function __construct(private readonly PdvStockService $stock)
    {
    }

    /**
     * @param  array<int,array<string,mixed>>  $vendas
     * @return array<int,array<string,mixed>>
     */
    public function importar(array $vendas, int $empresaId, ?string $terminal = null): array
    {
        $empresa = Empresa::query()->find($empresaId);
        $sessaoId = $this->resolverSessao($empresaId, $terminal);

        $resultados = [];

        foreach ($vendas as $venda) {
            $uuid = (string) ($venda['uuid'] ?? '');

            if ($uuid === '') {
                $resultados[] = ['uuid' => null, 'status' => 'erro', 'mensagem' => 'uuid ausente'];

                continue;
            }

            try {
                $resultados[] = $this->importarVenda($venda, $uuid, $empresa, $sessaoId, $terminal);
            } catch (Throwable $e) {
                Log::error('Falha ao importar venda offline do PDV', [
                    'uuid' => $uuid,
                    'erro' => $e->getMessage(),
                ]);

                $resultados[] = [
                    'uuid' => $uuid,
                    'status' => 'erro',
                    'mensagem' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    /**
     * @param  array<string,mixed>  $venda
     * @return array<string,mixed>
     */
    private function importarVenda(array $venda, string $uuid, ?Empresa $empresa, int $sessaoId, ?string $terminal): array
    {
        $existente = PdvVenda::query()->where('uuid', $uuid)->first();

        if ($existente !== null) {
            return [
                'uuid' => $uuid,
                'status' => 'duplicado',
                'pdv_venda_id' => (int) $existente->id,
            ];
        }

        return DB::transaction(function () use ($venda, $uuid, $empresa, $sessaoId, $terminal): array {
            $userId = $this->resolverUsuario($empresa?->id);

            $itens = (array) ($venda['itens'] ?? []);
            $pagamentos = (array) ($venda['pagamentos'] ?? []);
            $nfce = $venda['nfce'] ?? null;
            $personId = $this->resolverPersonId($venda['cliente_central_id'] ?? null);
            $crediarioDias = $this->parseCrediarioDias($venda['crediario_dias'] ?? null);

            $pdvVenda = PdvVenda::query()->create([
                'pdv_caixa_sessao_id' => $sessaoId,
                'user_id' => $userId,
                'numero' => PdvVenda::nextNumero($sessaoId),
                'person_id' => $personId,
                'cpf_nota' => $this->str($venda['cliente_documento'] ?? null, 20),
                'subtotal' => $this->num($venda['subtotal'] ?? 0),
                'desconto' => $this->num($venda['desconto'] ?? 0),
                'acrescimo' => $this->num($venda['acrescimo'] ?? 0),
                'total' => $this->num($venda['total'] ?? 0),
                'forma_pagamento' => $this->resolverFormaPagamento($pagamentos),
                'fiscal' => $nfce !== null,
                'situacao' => 'F',
                'fechado_em' => $this->data($venda['fechado_em'] ?? $venda['created_at'] ?? null),
                'uuid' => $uuid,
                'origem' => 'pdv_offline',
                'terminal_offline' => $this->str($terminal ?? ($venda['terminal_id'] ?? null), 60),
                'numero_offline' => isset($venda['numero']) ? (int) $venda['numero'] : null,
                'serie_offline' => $this->str($venda['serie'] ?? null, 5),
            ]);

            foreach ($itens as $item) {
                $productId = isset($item['product_central_id']) && $item['product_central_id']
                    ? (int) $item['product_central_id']
                    : null;

                PdvVendaItem::query()->create([
                    'pdv_venda_id' => $pdvVenda->id,
                    'product_id' => $productId,
                    'codigo' => $this->str($item['codigo'] ?? null, 60),
                    'descricao' => (string) ($item['descricao'] ?? 'Item'),
                    'unidade' => $this->str($item['unidade'] ?? 'UN', 10) ?: 'UN',
                    'quantidade' => $this->num($item['quantidade'] ?? 1, 3),
                    'preco_unitario' => $this->num($item['preco_unitario'] ?? 0),
                    'desconto' => $this->num($item['desconto'] ?? 0),
                    'acrescimo' => $this->num($item['acrescimo'] ?? 0),
                    'total' => $this->num($item['total'] ?? 0),
                ]);

                if ($productId !== null) {
                    $product = Product::query()->find($productId);

                    if ($product !== null) {
                        $this->stock->baixaItemVenda(
                            $product,
                            (float) $this->num($item['quantidade'] ?? 1, 3),
                            null,
                            null,
                            'PDV-OFF-'.str_pad((string) $pdvVenda->numero, 6, '0', STR_PAD_LEFT),
                        );
                    }
                }
            }

            foreach ($pagamentos as $pagamento) {
                $valor = $this->num($pagamento['valor'] ?? 0);

                if ($valor <= 0) {
                    continue;
                }

                PdvVendaPagamento::query()->create([
                    'pdv_venda_id' => $pdvVenda->id,
                    'forma' => $this->str($pagamento['forma'] ?? 'DINHEIRO', 30) ?: 'DINHEIRO',
                    'valor' => $valor,
                ]);
            }

            if (is_array($nfce)) {
                $this->importarNfce($pdvVenda, $empresa, $nfce);
            }

            $this->gerarEfeitosColaterais(
                $pdvVenda,
                $pagamentos,
                $this->num($venda['troco'] ?? 0),
                $crediarioDias,
            );

            return [
                'uuid' => $uuid,
                'status' => 'importado',
                'pdv_venda_id' => (int) $pdvVenda->id,
                'numero' => (int) $pdvVenda->numero,
            ];
        });
    }

    /**
     * @param  array<string,mixed>  $nfce
     */
    private function importarNfce(PdvVenda $pdvVenda, ?Empresa $empresa, array $nfce): void
    {
        PdvVendaNfce::query()->create([
            'pdv_venda_id' => $pdvVenda->id,
            'empresa_id' => $empresa?->id,
            'operacao' => $this->str($nfce['operacao'] ?? 'VENDA', 32) ?: 'VENDA',
            'modelo' => $this->str($nfce['modelo'] ?? '65', 2) ?: '65',
            'serie' => $this->str($nfce['serie'] ?? '1', 3) ?: '1',
            'numero' => isset($nfce['numero']) ? (int) $nfce['numero'] : null,
            'cnf' => $this->str($nfce['cnf'] ?? null, 8),
            'chave' => $this->str($nfce['chave'] ?? null, 44),
            'protocolo' => $this->str($nfce['protocolo'] ?? null, 20),
            'status' => $this->str($nfce['status'] ?? 'contingencia', 20) ?: 'contingencia',
            'ambiente' => isset($nfce['ambiente']) ? (int) $nfce['ambiente'] : 2,
            'tipo_emissao' => $this->str($nfce['tipo_emissao'] ?? '9', 1) ?: '9',
            'simulada' => false,
            'qr_code_conteudo' => $nfce['qr_code_conteudo'] ?? null,
            'xml' => $nfce['xml'] ?? null,
            'motivo_contingencia' => $this->str($nfce['motivo_contingencia'] ?? null, 255),
            'autorizada_em' => $this->data($nfce['autorizada_em'] ?? null),
        ]);
    }

    /**
     * Gera os efeitos colaterais financeiros/gerenciais da venda importada
     * (contas a receber, movimento de caixa e espelho no ledger central). Roda
     * dentro da transação de import — como o import é guardado pelo uuid, cada
     * venda só passa por aqui uma vez (idempotente no conjunto).
     *
     * @param  array<int,array<string,mixed>>  $pagamentos
     * @param  list<int>|null  $crediarioDias  Dias de vencimento do crediário (offline).
     */
    private function gerarEfeitosColaterais(PdvVenda $pdvVenda, array $pagamentos, float $troco, ?array $crediarioDias = null): void
    {
        $pagServico = $this->pagamentosParaServicos($pagamentos);

        if ($pagServico !== [] && config('pdv_carga.retorno_gerar_financeiro', true)) {
            (new PdvVendaFinanceiroService())->gerarContasReceber(
                $pdvVenda,
                $pdvVenda->person_id ? (int) $pdvVenda->person_id : null,
                $pagServico,
                $crediarioDias,
            );
        }

        if ($pagServico !== [] && config('pdv_carga.retorno_gerar_caixa', true)) {
            (new PdvCaixaMovimentoService())->registrarEntradasVenda(
                (int) $pdvVenda->pdv_caixa_sessao_id,
                $pdvVenda,
                $pagServico,
                $troco,
            );
        }

        if (config('pdv_carga.retorno_gerar_espelho', true)) {
            (new PdvVendaRetaguardaMirrorService())->espelhar($pdvVenda->fresh(['itens', 'pagamentos']));
        }
    }

    /**
     * Enriquece cada pagamento com tipo/aparece_contas_receber do cadastro de
     * formas de pagamento, para a correta classificação (cartão a receber etc.).
     *
     * @param  array<int,array<string,mixed>>  $pagamentos
     * @return array<int,array<string,mixed>>
     */
    private function pagamentosParaServicos(array $pagamentos): array
    {
        $cadastro = FormaPagamento::query()->get(['descricao', 'tipo', 'aparece_contas_receber'])
            ->keyBy(fn ($f) => mb_strtoupper(trim((string) $f->descricao), 'UTF-8'));

        $result = [];

        foreach ($pagamentos as $p) {
            $forma = mb_strtoupper(trim((string) ($p['forma'] ?? '')), 'UTF-8');
            $valor = $this->num($p['valor'] ?? 0);

            if ($forma === '' || $valor <= 0) {
                continue;
            }

            $cad = $cadastro->get($forma);

            // Prioriza o tipo enviado pelo PDV offline; cai no cadastro do ERP.
            $tipo = trim((string) ($p['tipo'] ?? '')) !== ''
                ? (string) $p['tipo']
                : (string) ($cad->tipo ?? '');

            $result[] = [
                'forma' => $forma,
                'valor' => $valor,
                'tipo' => $tipo,
                'aparece_contas_receber' => (bool) ($cad->aparece_contas_receber ?? false),
            ];
        }

        return $result;
    }

    /**
     * Reaproveita a última sessão de caixa aberta do terminal/empresa; se não
     * existir, cria uma sessão dedicada às importações offline.
     */
    private function resolverSessao(int $empresaId, ?string $terminal): int
    {
        $terminalId = $this->resolverTerminalId($empresaId, $terminal);
        $userId = $this->resolverUsuario($empresaId);

        $query = PdvCaixaSessao::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('fechado_em');

        if ($terminalId !== null) {
            $query->where('terminal_id', $terminalId);
        }

        $sessao = $query->latest('id')->first();

        if ($sessao !== null) {
            return (int) $sessao->id;
        }

        $sessao = PdvCaixaSessao::query()->create([
            'user_id' => $userId,
            'empresa_id' => $empresaId,
            'terminal_id' => $terminalId,
            'valor_abertura' => 0,
            'aberto_em' => now(),
        ]);

        return (int) $sessao->id;
    }

    private function resolverTerminalId(int $empresaId, ?string $terminal): ?int
    {
        $terminal = trim((string) $terminal);

        if ($terminal === '') {
            return null;
        }

        $model = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($terminal): void {
                $q->where('numero_logico_terminal', $terminal)
                    ->orWhere('nome', $terminal);
            })
            ->first();

        return $model !== null ? (int) $model->id : null;
    }

    /**
     * O `central_id` enviado pelo PDV offline é o próprio id do Person no ERP.
     * Só vincula se o cliente ainda existir (evita FK órfã).
     */
    private function resolverPersonId(mixed $centralId): ?int
    {
        $id = (int) $centralId;

        if ($id <= 0) {
            return null;
        }

        return Person::query()->whereKey($id)->exists() ? $id : null;
    }

    /**
     * @return list<int>|null
     */
    private function parseCrediarioDias(mixed $raw): ?array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $dias = PdvFinalizarPagamentosHelper::diasDeString($raw);

        return $dias !== [] ? $dias : null;
    }

    private function resolverUsuario(?int $empresaId): int
    {
        $configured = config('pdv_carga.import_user_id');

        if ($configured && User::query()->whereKey((int) $configured)->exists()) {
            return (int) $configured;
        }

        $user = null;

        if ($empresaId !== null) {
            $user = User::query()->where('empresa_id', $empresaId)->orderBy('id')->first();
        }

        $user ??= User::query()->orderBy('id')->first();

        if ($user === null) {
            throw new \RuntimeException('Nenhum usuário disponível no ERP para vincular a venda importada.');
        }

        return (int) $user->id;
    }

    /**
     * @param  array<int,array<string,mixed>>  $pagamentos
     */
    private function resolverFormaPagamento(array $pagamentos): string
    {
        $formas = array_values(array_filter(array_map(
            fn ($p) => strtoupper(trim((string) ($p['forma'] ?? ''))),
            $pagamentos
        )));

        if ($formas === []) {
            return 'DINHEIRO';
        }

        if (count(array_unique($formas)) > 1) {
            return 'MISTO';
        }

        return $formas[0];
    }

    private function num(mixed $value, int $decimals = 2): float
    {
        return round((float) $value, $decimals);
    }

    private function str(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $max);
    }

    private function data(mixed $value): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (Throwable) {
            return null;
        }
    }
}
