<?php

namespace App\Support\Pdv;

use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\ForcaVendasOrder;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaNfce;
use App\Models\PdvVendaPagamento;
use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\Product;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\Pdv\PdvCaixaMovimentoService;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Erp\Pdv\PdvStockService;
use App\Support\Erp\Pdv\PdvVendaFinanceiroService;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use App\Support\Erp\Vendas\EstornarVendaService;
use App\Support\ForcaVendas\ForcaVendasFaturamentoService;
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
            $nfce = $venda['nfce'] ?? null;

            if (is_array($nfce) && $nfce !== []) {
                $this->importarNfce($existente, $empresa, $nfce);
            }

            if ($this->isEstornoPayload($venda)) {
                return $this->aplicarEstornoOffline($existente->fresh() ?? $existente, $venda, $empresa, $uuid);
            }

            $this->completarContasReceberSeFaltar($existente, $venda);

            $forcaOrderId = isset($venda['forca_order_id']) ? (int) $venda['forca_order_id'] : 0;
            if ($forcaOrderId > 0) {
                $this->concluirForcaViaPdv($forcaOrderId, $existente->fresh());
            }

            return [
                'uuid' => $uuid,
                'status' => 'duplicado',
                'pdv_venda_id' => (int) $existente->id,
            ];
        }

        $resultado = DB::transaction(function () use ($venda, $uuid, $empresa, $sessaoId, $terminal): array {
            $userId = $this->resolverUsuario($empresa?->id, $venda['user_central_id'] ?? null);

            $itens = (array) ($venda['itens'] ?? []);
            $pagamentos = (array) ($venda['pagamentos'] ?? []);
            $nfce = $venda['nfce'] ?? null;
            $personId = $this->resolverPersonId($venda['cliente_central_id'] ?? null);
            $crediarioDias = $this->parseCrediarioDias($venda['crediario_dias'] ?? null);
            $fechadoEm = $this->data($venda['fechado_em'] ?? $venda['created_at'] ?? null);
            $abertoEm = $this->data($venda['aberto_em'] ?? null) ?? $fechadoEm;
            $vendedorNome = $this->str($venda['vendedor_nome'] ?? null, 120);
            $vendedorId = isset($venda['vendedor_central_id']) && $venda['vendedor_central_id']
                ? (int) $venda['vendedor_central_id']
                : null;

            if ($vendedorId !== null && ! Vendedor::query()->whereKey($vendedorId)->exists()) {
                $vendedorId = null;
            }

            $pdvVenda = PdvVenda::query()->create([
                'pdv_caixa_sessao_id' => $sessaoId,
                'user_id' => $userId,
                'numero' => PdvVenda::nextNumero($sessaoId),
                'person_id' => $personId,
                'cpf_nota' => $this->str($venda['cliente_documento'] ?? null, 20),
                'vendedor_id' => $vendedorId,
                'vendedor_nome' => $vendedorNome,
                'subtotal' => $this->num($venda['subtotal'] ?? 0),
                'desconto' => $this->num($venda['desconto'] ?? 0),
                'acrescimo' => $this->num($venda['acrescimo'] ?? 0),
                'total' => $this->num($venda['total'] ?? 0),
                'forma_pagamento' => $this->resolverFormaPagamento($pagamentos),
                'fiscal' => $nfce !== null,
                'situacao' => 'F',
                'aberto_em' => $abertoEm,
                'fechado_em' => $fechadoEm,
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

                if ($productId !== null && ! Product::query()->whereKey($productId)->exists()) {
                    $productId = null;
                }

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
                            null,
                            $empresa,
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

            $forcaOrderId = isset($venda['forca_order_id']) ? (int) $venda['forca_order_id'] : 0;
            if ($forcaOrderId > 0) {
                $this->concluirForcaViaPdv($forcaOrderId, $pdvVenda->fresh());
            }

            return [
                'uuid' => $uuid,
                'status' => 'importado',
                'pdv_venda_id' => (int) $pdvVenda->id,
                'numero' => (int) $pdvVenda->numero,
            ];
        });

        if ($this->isEstornoPayload($venda)) {
            $pdv = PdvVenda::query()->find($resultado['pdv_venda_id'] ?? 0);

            if ($pdv !== null) {
                return $this->aplicarEstornoOffline($pdv, $venda, $empresa, $uuid);
            }
        }

        return $resultado;
    }

    /**
     * @param  array<string,mixed>  $venda
     */
    private function isEstornoPayload(array $venda): bool
    {
        $situacao = strtoupper(trim((string) ($venda['situacao'] ?? '')));
        $status = strtolower(trim((string) ($venda['status'] ?? '')));

        return $situacao === 'C' || $status === 'cancelada';
    }

    /**
     * @param  array<string,mixed>  $venda
     * @return array<string,mixed>
     */
    private function aplicarEstornoOffline(PdvVenda $pdvVenda, array $venda, ?Empresa $empresa, string $uuid): array
    {
        $motivo = trim((string) ($venda['motivo_estorno'] ?? ''));

        if ($motivo === '') {
            $motivo = PdvEstornoMotivo::MOTIVO_AUTOMATICO;
        }

        $pdvVenda->loadMissing(['itens', 'pagamentos', 'nfce', 'venda']);

        $result = (new EstornarVendaService())->fromPdvVenda(
            $pdvVenda,
            $motivo,
            EstornarVendaService::ORIGEM_PDV,
            $empresa,
            $pdvVenda->pdv_caixa_sessao_id ? (int) $pdvVenda->pdv_caixa_sessao_id : null,
        );

        return [
            'uuid' => $uuid,
            'status' => $result->alreadyCancelled ? 'duplicado' : 'importado',
            'pdv_venda_id' => (int) $pdvVenda->id,
            'numero' => (int) $pdvVenda->numero,
        ];
    }

    /**
     * @param  array<string,mixed>  $nfce
     */
    private function importarNfce(PdvVenda $pdvVenda, ?Empresa $empresa, array $nfce): void
    {
        $attrs = [
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
            'motivo_rejeicao' => $this->str($nfce['motivo_rejeicao'] ?? null, 255),
            'autorizada_em' => $this->data($nfce['autorizada_em'] ?? null),
        ];

        $existente = PdvVendaNfce::query()->where('pdv_venda_id', $pdvVenda->id)->first();

        if ($existente !== null) {
            $existente->fill($attrs)->save();

            return;
        }

        PdvVendaNfce::query()->create($attrs + [
            'pdv_venda_id' => $pdvVenda->id,
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

    private function concluirForcaViaPdv(int $forcaOrderId, ?PdvVenda $pdvVenda): void
    {
        if ($pdvVenda === null || $forcaOrderId < 1) {
            return;
        }

        $pdvVenda->loadMissing('venda');
        $venda = $pdvVenda->venda;

        if (! $venda instanceof Venda) {
            $vendaId = (int) ($pdvVenda->venda_id ?? 0);
            $venda = $vendaId > 0 ? Venda::query()->find($vendaId) : null;
        }

        if (! $venda instanceof Venda) {
            Log::warning('PDV retorno: forca_order_id sem venda espelhada.', [
                'forca_order_id' => $forcaOrderId,
                'pdv_venda_id' => $pdvVenda->id,
            ]);

            return;
        }

        $order = ForcaVendasOrder::query()->find($forcaOrderId);

        if ($order === null) {
            Log::warning('PDV retorno: forca_order_id não encontrado.', [
                'forca_order_id' => $forcaOrderId,
                'pdv_venda_id' => $pdvVenda->id,
            ]);

            return;
        }

        try {
            (new ForcaVendasFaturamentoService())->concluirViaPdv($order, $venda);
        } catch (Throwable $e) {
            Log::error('PDV retorno: falha ao concluir Força via PDV.', [
                'forca_order_id' => $forcaOrderId,
                'venda_id' => $venda->id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Vendas já importadas sem título (retorno antigo) recebem CR no reenvio,
     * sem relançar caixa nem espelho.
     *
     * @param  array<string,mixed>  $venda
     */
    private function completarContasReceberSeFaltar(PdvVenda $pdvVenda, array $venda): void
    {
        if (! config('pdv_carga.retorno_gerar_financeiro', true)) {
            return;
        }

        if ($this->jaTemContasReceber($pdvVenda)) {
            return;
        }

        $pagServico = $this->pagamentosParaServicos((array) ($venda['pagamentos'] ?? []));

        if ($pagServico === []) {
            return;
        }

        $crediarioDias = $this->parseCrediarioDias($venda['crediario_dias'] ?? null);

        (new PdvVendaFinanceiroService())->gerarContasReceber(
            $pdvVenda,
            $pdvVenda->person_id ? (int) $pdvVenda->person_id : null,
            $pagServico,
            $crediarioDias,
        );
    }

    private function jaTemContasReceber(PdvVenda $venda): bool
    {
        $documento = 'PDV-'.str_pad((string) $venda->numero, 6, '0', STR_PAD_LEFT);

        return ContaReceber::query()
            ->where(function ($q) use ($documento): void {
                $q->where('documento', $documento)
                    ->orWhere('documento', 'like', $documento.'/%');
            })
            ->exists();
    }

    private function semAcento(string $value): string
    {
        $upper = mb_strtoupper(trim($value), 'UTF-8');
        $map = [
            'Á' => 'A', 'À' => 'A', 'Ã' => 'A', 'Â' => 'A',
            'É' => 'E', 'Ê' => 'E',
            'Í' => 'I',
            'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O',
            'Ú' => 'U',
            'Ç' => 'C',
        ];

        return strtr($upper, $map);
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
        $cadastro = FormaPagamento::query()->get(['descricao', 'tipo', 'tipo_movimento', 'aparece_contas_receber'])
            ->keyBy(fn ($f) => mb_strtoupper(trim((string) $f->descricao), 'UTF-8'));

        $result = [];

        foreach ($pagamentos as $p) {
            $forma = mb_strtoupper(trim((string) ($p['forma'] ?? '')), 'UTF-8');
            $valor = $this->num($p['valor'] ?? 0);

            if ($forma === '' || $valor <= 0) {
                continue;
            }

            $cad = $cadastro->get($forma)
                ?? $cadastro->first(fn ($f) => $this->semAcento((string) $f->descricao) === $this->semAcento($forma));

            // Prioriza o tipo enviado pelo PDV offline; cai no cadastro do ERP.
            $tipo = trim((string) ($p['tipo'] ?? '')) !== ''
                ? (string) $p['tipo']
                : (string) ($cad->tipo ?? '');

            $tipoMovimento = trim((string) ($p['tipo_movimento'] ?? '')) !== ''
                ? (string) $p['tipo_movimento']
                : (string) ($cad->tipo_movimento ?? '');

            $tipoLower = mb_strtolower($tipo, 'UTF-8');
            if ($tipoMovimento === '' && in_array($tipoLower, ['crediario', 'cheque', 'boleto'], true)) {
                $tipoMovimento = 'contas_receber';
            }
            if ($tipoMovimento === '' && PdvFinalizarPagamentosHelper::isFormaAPrazo($forma)) {
                $tipoMovimento = 'contas_receber';
            }

            $result[] = [
                'forma' => $forma,
                'valor' => $valor,
                'tipo' => $tipo,
                'tipo_movimento' => $tipoMovimento,
                'aparece_contas_receber' => (bool) ($cad->aparece_contas_receber ?? false)
                    || $tipoMovimento === 'contas_receber',
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
        $model = PdvOfflineTerminalLookup::find($empresaId, (string) $terminal, false);

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

    private function resolverUsuario(?int $empresaId, mixed $userCentralId = null): int
    {
        $centralId = is_numeric($userCentralId) ? (int) $userCentralId : 0;
        if ($centralId > 0) {
            $byCentral = User::query()->whereKey($centralId)->first();
            if ($byCentral !== null) {
                return (int) $byCentral->id;
            }
        }

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
