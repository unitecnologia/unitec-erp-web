<?php

namespace App\Support\Pdv;

use App\Models\CaixaConta;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Models\EstoqueReserva;
use App\Models\FiscalIbptItem;
use App\Models\FormaPagamento;
use App\Models\Person;
use App\Models\Product;
use App\Models\TabelaPrazo;
use App\Models\Terminal;
use App\Models\User;
use App\Models\VendasParametro;
use App\Support\Erp\EstoqueReservaService;
use App\Support\Erp\Nfe\NfeFiscalConfig;
use App\Support\Erp\Pdv\PdvProductSearchRanking;
use App\Support\Fiscal\NfceTerminalSequencia;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Monta o pacote de carga (pull) do ERP central para o mini-PDV offline.
 *
 * Espelha o padrão do ForcaVendasSyncService, porém com o payload exato que o
 * PDV local precisa para operar e (na Fase 2) emitir NFC-e. Suporta deltas
 * incrementais via `?since=` em cada tabela que tenha `updated_at`.
 */
class PdvCargaService
{
    public const PRODUCTS_PAGE_DEFAULT = 2000;

    public const PRODUCTS_PAGE_MAX = 5000;

    /**
     * @param  array<string, Carbon|null>|null  $sinceByEntity
     * @return array<string, mixed>
     */
    public function buildPull(
        ?Carbon $since,
        int $empresaId,
        ?string $terminal = null,
        int $productsAfterId = 0,
        int $productsLimit = self::PRODUCTS_PAGE_DEFAULT,
        ?array $sinceByEntity = null,
    ): array {
        $productsAfterId = max(0, $productsAfterId);
        $productsLimit = max(1, min(self::PRODUCTS_PAGE_MAX, $productsLimit));

        $sinceProducts = $sinceByEntity['products'] ?? $since;
        $sinceCustomers = $sinceByEntity['customers'] ?? $since;
        $sinceFormas = $sinceByEntity['formas_pagamento'] ?? $since;
        $sinceUsers = $sinceByEntity['users'] ?? $since;

        $productsPage = $this->products($sinceProducts, $empresaId, $productsAfterId, $productsLimit);

        $meta = [
            'server_time' => now()->toIso8601String(),
            'since' => $since?->toIso8601String(),
            'products' => $productsPage['items'],
            'products_has_more' => $productsPage['has_more'],
            'products_next_after_id' => $productsPage['next_after_id'],
        ];

        // Páginas seguintes: só produtos (PDV já aplicou empresa/clientes/users).
        if ($productsAfterId > 0) {
            return $meta;
        }

        return [
            ...$meta,
            'empresa' => $this->empresa($empresaId, $terminal),
            'formas_pagamento' => $this->formasPagamento($sinceFormas),
            'customers' => $this->customers($sinceCustomers),
            'users' => $this->users($empresaId, $sinceUsers),
            'ibpt' => $this->ibptParaProdutosAtivos(),
        ];
    }

    /**
     * Assinatura barata para ETag (contagens + maior updated_at por tabela).
     */
    public function pullSignature(int $empresaId, ?string $terminal = null): string
    {
        $parts = ['empresa:'.$empresaId, 'terminal:'.(string) $terminal];

        $terminaisMax = (string) Terminal::query()->where('empresa_id', $empresaId)->max('updated_at');
        $parts[] = "terminais:{$terminaisMax}";
        $parts[] = 'cert:'.(string) $this->certificadoFingerprint($empresaId);

        foreach ([
            'products' => Product::query()->where('ativo', true),
            'people' => Person::query()->where('is_cliente', true),
            'formas_pagamento' => FormaPagamento::query(),
            'empresas' => Empresa::query()->whereKey($empresaId),
            'vendas_parametros' => VendasParametro::query()->whereKey($empresaId),
            'users' => User::query()->where(function (Builder $q) use ($empresaId): void {
                $q->where('is_admin', true)
                    ->orWhere('empresa_id', $empresaId)
                    ->orWhereHas('empresas', fn (Builder $e) => $e->whereKey($empresaId));
            }),
        ] as $label => $query) {
            $table = $query->getModel()->getTable();
            $count = (clone $query)->count();
            $max = Schema::hasColumn($table, 'updated_at')
                ? (string) (clone $query)->max('updated_at')
                : '';
            $parts[] = "{$label}:{$count}:{$max}";
        }

        $reservaMax = (string) EstoqueReserva::query()->max('updated_at');
        $parts[] = "estoque_reservas:{$reservaMax}";

        if (Schema::hasTable('fiscal_ibpt_itens')) {
            $ibptCount = (int) FiscalIbptItem::query()->count();
            $ibptMax = Schema::hasColumn('fiscal_ibpt_itens', 'updated_at')
                ? (string) FiscalIbptItem::query()->max('updated_at')
                : '';
            $parts[] = "ibpt:{$ibptCount}:{$ibptMax}";
        }

        return sha1(implode('|', $parts));
    }

    private function applySince(Builder $query, ?Carbon $since, string $table): Builder
    {
        if ($since !== null && Schema::hasColumn($table, 'updated_at')) {
            $query->where('updated_at', '>', $since);
        }

        return $query;
    }

    /**
     * Página de produtos (cursor por id) para catálogos grandes (~50k).
     *
     * @return array{items: list<array<string, mixed>>, has_more: bool, next_after_id: ?int}
     */
    private function products(?Carbon $since, int $empresaId, int $afterId, int $limit): array
    {
        $reservados = (new EstoqueReservaService())->totaisReservadosAtivos();

        // Completa: só ativos. Incremental: ativos + inativos recentes (para o PDV marcar ativo=false).
        $query = Product::query()->where(function (Builder $q) use ($since): void {
            $q->where('ativo', true);

            if ($since !== null) {
                $q->orWhere(function (Builder $q2) use ($since): void {
                    $q2->where('ativo', false)->where('updated_at', '>', $since);
                });
            }
        });

        if ($since !== null && Schema::hasColumn('products', 'updated_at')) {
            $idsReservaAlterada = EstoqueReserva::query()
                ->where('updated_at', '>', $since)
                ->distinct()
                ->pluck('product_id')
                ->all();

            $idsExtras = array_values(array_unique(array_merge($idsReservaAlterada, array_keys($reservados))));

            $query->where(function (Builder $q) use ($since, $idsExtras): void {
                $q->where('updated_at', '>', $since);

                if ($idsExtras !== []) {
                    $q->orWhereIn('id', $idsExtras);
                }
            });
        }

        if ($afterId > 0) {
            $query->where('id', '>', $afterId);
        }

        $columns = [
            'id', 'codigo', 'referencia', 'codigo_barras', 'codigo_barras_caixa', 'descricao',
            'unidade', 'marca', 'grupo', 'localizacao', 'foto_path',
            'preco_venda', 'preco_atacado', 'qtd_atacado', 'preco_especial',
            'promo_preco_venda', 'promo_data_inicio', 'promo_data_fim',
            'estoque', 'prefixo_balanca', 'produto_pesado', 'preco_variavel', 'is_servico',
            'ncm', 'cest', 'cfop_interno', 'origem', 'cst_icms', 'csosn',
            'aliq_icms', 'aliq_pis', 'aliq_cofins', 'cclass_trib', 'ativo', 'updated_at',
        ];

        $rows = $query
            ->orderBy('id')
            ->limit($limit + 1)
            ->get($columns);

        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $saidas = PdvProductSearchRanking::qtdSaidaByProductIds(
            $rows->pluck('id')->all(),
            $empresaId > 0 ? $empresaId : null,
        );

        $items = [];
        foreach ($rows as $p) {
            /** @var Product $p */
            $fisico = (float) $p->estoque;
            $reservado = (float) ($reservados[$p->id] ?? 0);

            $items[] = [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'referencia' => $p->referencia,
                'codigo_barras' => $p->codigo_barras,
                'codigo_barras_caixa' => $p->codigo_barras_caixa,
                'descricao' => $p->descricao,
                'unidade' => $p->unidade ?: 'UN',
                'marca' => $p->marca,
                'grupo' => $p->grupo,
                'localizacao' => $p->localizacao,
                'foto_path' => $p->foto_path,
                'preco_venda' => (float) $p->preco_venda,
                'preco_atacado' => (float) $p->preco_atacado,
                'qtd_atacado' => (float) $p->qtd_atacado,
                'preco_especial' => (float) ($p->preco_especial ?? 0),
                'promo_preco_venda' => (float) $p->promo_preco_venda,
                'promo_data_inicio' => optional($p->promo_data_inicio)->toDateString(),
                'promo_data_fim' => optional($p->promo_data_fim)->toDateString(),
                'estoque' => $fisico,
                'estoque_reservado' => $reservado,
                'estoque_disponivel' => $fisico - $reservado,
                'qtd_saida' => (float) ($saidas[(int) $p->id] ?? 0),
                'prefixo_balanca' => $p->prefixo_balanca,
                'produto_pesado' => (bool) $p->produto_pesado,
                'preco_variavel' => (bool) $p->preco_variavel,
                'is_servico' => (bool) $p->is_servico,
                'ncm' => $p->ncm,
                'cest' => $p->cest,
                'cfop_interno' => $p->cfop_interno,
                'origem' => $p->origem,
                'cst_icms' => $p->cst_icms,
                'csosn' => $p->csosn,
                'aliq_icms' => (float) $p->aliq_icms,
                'aliq_pis' => (float) $p->aliq_pis,
                'aliq_cofins' => (float) $p->aliq_cofins,
                'cclass_trib' => $p->cclass_trib,
                'ativo' => (bool) $p->ativo,
                'updated_at' => optional($p->updated_at)->toIso8601String(),
            ];
        }

        $lastId = $items !== [] ? (int) $items[array_key_last($items)]['id'] : null;

        return [
            'items' => $items,
            'has_more' => $hasMore,
            'next_after_id' => $hasMore ? $lastId : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function customers(?Carbon $since): array
    {
        $query = Person::query()->where('is_cliente', true);

        $clientes = $this->applySince($query, $since, 'people')
            ->orderBy('id')
            ->get();

        if ($clientes->isEmpty()) {
            return [];
        }

        // Saldo em aberto (crédito comprometido) por cliente — igual ao usado pelo
        // PdvClienteLimiteService — enviado como snapshot para o PDV checar limite offline.
        $saldos = ContaReceber::query()
            ->whereIn('cliente_id', $clientes->pluck('id')->all())
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo) as saldo')
            ->pluck('saldo', 'cliente_id');

        // Dias da tabela de prazo padrão de cada cliente (crediário sem base no PDV).
        $tabelaIds = $clientes->pluck('tabela_prazo_id')->filter()->unique()->all();
        $tabelas = $tabelaIds === []
            ? collect()
            : TabelaPrazo::query()->whereIn('id', $tabelaIds)->pluck('dias', 'id');

        return $clientes
            ->map(fn (Person $c): array => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'pessoa_tipo' => $c->pessoa_tipo,
                'nome_razao' => $c->nome_razao,
                'apelido_fantasia' => $c->apelido_fantasia,
                'cpf_cnpj' => $c->cpf_cnpj,
                'rg_ie' => $c->rg_ie,
                'cep' => $c->cep,
                'endereco' => $c->endereco,
                'numero' => $c->numero,
                'complemento' => $c->complemento,
                'bairro' => $c->bairro,
                'cidade_codigo' => $c->cidade_codigo,
                'cidade_nome' => $c->cidade_nome,
                'uf' => $c->uf,
                'email' => $c->email,
                'fone1' => $c->fone1,
                'celular1' => $c->celular1,
                'whatsapp' => $c->whatsapp,
                'tipo_contribuinte' => $c->tipo_contribuinte,
                'limite_credito' => round((float) ($c->limite_credito ?? 0), 2),
                'saldo_em_aberto' => round((float) ($saldos[$c->id] ?? 0), 2),
                'tabela_prazo_dias' => $c->tabela_prazo_id
                    ? (string) ($tabelas[$c->tabela_prazo_id] ?? '')
                    : '',
                'ativo' => (bool) $c->ativo,
                'updated_at' => optional($c->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Usuários que podem operar o PDV desta empresa (admin, vínculo direto ou
     * pela pivot empresa_user). Vai o hash da senha para o PDV autenticar
     * offline (endpoint da carga é protegido por token).
     *
     * @return array<int, array<string, mixed>>
     */
    private function users(int $empresaId, ?Carbon $since): array
    {
        $query = User::query()
            ->with('vendedor')
            ->where('ativo', true)
            ->where(function (Builder $q) use ($empresaId): void {
                $q->where('is_admin', true)
                    ->orWhere('empresa_id', $empresaId)
                    ->orWhereHas('empresas', fn (Builder $e) => $e->whereKey($empresaId));
            });

        $users = $this->applySince($query, $since, 'users')
            ->orderBy('name')
            ->get();

        $caixaPorUser = $this->caixaPadraoPorUsuarios($users, $empresaId);

        return $users
            ->map(function (User $u) use ($caixaPorUser): array {
                $caixa = $caixaPorUser[(int) $u->id] ?? null;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'password' => $u->password,
                    'is_admin' => (bool) $u->is_admin,
                    'ativo' => (bool) $u->ativo,
                    'empresa_id' => $u->empresa_id ? (int) $u->empresa_id : null,
                    'vendedor_id' => $u->vendedor_id ? (int) $u->vendedor_id : null,
                    'vendedor_nome' => $u->vendedor?->nome,
                    'caixa_conta_id' => $caixa['id'] ?? null,
                    'caixa_conta_nome' => $caixa['nome'] ?? null,
                    'updated_at' => optional($u->updated_at)->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * Caixa padrão de cada usuário (pivot caixa_conta_user), mesma regra de
     * User::defaultCaixaContaId: is_padrao, senão o primeiro liberado; sem
     * vínculo, o primeiro caixa operacional da empresa.
     *
     * @param  Collection<int, User>  $users
     * @return array<int, array{id: int, nome: string}>
     */
    private function caixaPadraoPorUsuarios(Collection $users, int $empresaId): array
    {
        if ($users->isEmpty() || $empresaId <= 0) {
            return [];
        }

        $rows = DB::table('caixa_conta_user')
            ->whereIn('user_id', $users->modelKeys())
            ->where('empresa_id', $empresaId)
            ->orderByDesc('is_padrao')
            ->orderBy('caixa_conta_id')
            ->get(['user_id', 'caixa_conta_id']);

        $caixaIdPorUser = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            if (! isset($caixaIdPorUser[$uid])) {
                $caixaIdPorUser[$uid] = (int) $row->caixa_conta_id;
            }
        }

        $fallbackId = (int) (CaixaConta::query()->assignable()->orderBy('codigo')->value('id') ?? 0);

        foreach ($users as $user) {
            $uid = (int) $user->id;
            if (! isset($caixaIdPorUser[$uid]) && $fallbackId > 0) {
                $caixaIdPorUser[$uid] = $fallbackId;
            }
        }

        if ($caixaIdPorUser === []) {
            return [];
        }

        $nomes = CaixaConta::query()
            ->whereIn('id', array_values(array_unique($caixaIdPorUser)))
            ->pluck('nome', 'id');

        $out = [];
        foreach ($caixaIdPorUser as $uid => $caixaId) {
            $nome = trim((string) ($nomes[$caixaId] ?? ''));
            if ($nome === '') {
                continue;
            }
            $out[$uid] = ['id' => $caixaId, 'nome' => $nome];
        }

        return $out;
    }

    /**
     * Linhas IBPT dos NCMs usados pelos produtos ativos (Lei 12.741 no PDV offline).
     *
     * @return list<array<string, mixed>>
     */
    private function ibptParaProdutosAtivos(): array
    {
        if (! Schema::hasTable('fiscal_ibpt_itens')) {
            return [];
        }

        $ncms = Product::query()
            ->where('ativo', true)
            ->whereNotNull('ncm')
            ->where('ncm', '!=', '')
            ->pluck('ncm')
            ->map(function (mixed $ncm): string {
                $digits = preg_replace('/\D/', '', (string) $ncm) ?? '';

                return $digits === '' ? '' : substr(str_pad($digits, 8, '0', STR_PAD_LEFT), 0, 8);
            })
            ->filter(fn (string $ncm): bool => strlen($ncm) >= 4)
            ->unique()
            ->values()
            ->all();

        if ($ncms === []) {
            return [];
        }

        $variants = [];
        foreach ($ncms as $ncm) {
            $variants[] = $ncm;
            $variants[] = ltrim($ncm, '0') ?: '0';
        }
        $variants = array_values(array_unique($variants));

        return FiscalIbptItem::query()
            ->where(function (Builder $q) use ($ncms, $variants): void {
                $q->whereIn('ncm', $variants);
                foreach ($ncms as $ncm) {
                    $q->orWhereRaw("LPAD(REPLACE(ncm, ' ', ''), 8, '0') = ?", [$ncm]);
                }
            })
            ->orderBy('ncm')
            ->orderByDesc('id')
            ->get()
            ->map(fn (FiscalIbptItem $item): array => [
                'id' => $item->id,
                'ncm' => (string) $item->ncm,
                'ex_tipi' => $item->ex_tipi,
                'tipo' => $item->tipo,
                'descricao' => $item->descricao,
                'aliq_nacional' => (float) $item->aliq_nacional,
                'aliq_importado' => (float) $item->aliq_importado,
                'aliq_estadual' => (float) $item->aliq_estadual,
                'aliq_municipal' => (float) $item->aliq_municipal,
                'vigencia_inicio' => optional($item->vigencia_inicio)->toDateString(),
                'vigencia_fim' => optional($item->vigencia_fim)->toDateString(),
                'chave' => $item->chave,
                'versao' => $item->versao,
                'fonte' => $item->fonte,
                'updated_at' => optional($item->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function formasPagamento(?Carbon $since): array
    {
        return $this->applySince(FormaPagamento::query(), $since, 'formas_pagamento')
            ->orderBy('codigo')
            ->get()
            ->map(fn (FormaPagamento $f): array => [
                'id' => $f->id,
                'codigo' => $f->codigo,
                'descricao' => $f->descricao,
                'tipo' => $f->tipo,
                'atalho' => $f->atalho,
                'max_parcelas' => (int) $f->max_parcelas,
                'prazo_cartao' => (int) $f->prazo_cartao,
                'intervalo_parcelas' => (int) $f->intervalo_parcelas,
                'aparece_venda' => (bool) $f->aparece_venda,
                'aparece_contas_receber' => (bool) $f->aparece_contas_receber,
                'nfce' => (bool) $f->nfce,
                'ativo' => (bool) $f->ativo,
                'updated_at' => optional($f->updated_at)->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Identidade + parâmetros fiscais/PDV da empresa. Não inclui certificado
     * nem senhas — a distribuição do certificado é tratada à parte (Fase 2).
     */
    private function empresa(int $empresaId, ?string $terminal = null): ?array
    {
        $empresa = Empresa::query()->find($empresaId);

        if ($empresa === null) {
            return null;
        }

        $params = collect($empresa->getAttributes())
            ->filter(fn ($v, $k): bool => str_starts_with((string) $k, 'param_'))
            ->reject(fn ($v, $k): bool => Str::contains((string) $k, ['senha', 'password', 'secret', 'smtp', 'email', 'key'], true))
            ->map(fn ($v) => $v)
            ->all();

        $vp = VendasParametro::query()->find($empresaId);
        $caixa = $this->resolveTerminal($empresaId, $terminal);

        return [
            'id' => $empresa->id,
            'codigo' => $empresa->codigo,
            'nome' => $empresa->nome,
            'fantasia' => $empresa->fantasia,
            'razao_social' => $empresa->razao_social,
            'cnpj' => $empresa->cnpj,
            'ie' => $empresa->ie,
            'im' => $empresa->im,
            'regime_tributario' => $empresa->regime_tributario,
            'cep' => $empresa->cep,
            'endereco' => $empresa->endereco,
            'numero' => $empresa->numero,
            'complemento' => $empresa->complemento,
            'bairro' => $empresa->bairro,
            'cidade_codigo' => $empresa->cidade_codigo,
            'cidade' => $empresa->cidade,
            'uf' => $empresa->uf,
            'telefone' => $empresa->telefone,
            'obs_nfce' => $empresa->obs_nfce,
            // Config de transmissão NFC-e (sem certificado/senha).
            'nfce_ambiente' => $vp?->ambiente,
            'nfce_serie' => $vp?->serie,
            'nfce_uf' => $vp?->uf,
            'nfce_versao_qrcode' => $vp?->versao_qrcode,
            'nfce_csc_id' => $vp?->id_token,
            'nfce_csc_token' => $vp?->token,
            // Série NFC-e exclusiva do caixa (offline). Cai para a série da
            // empresa quando o terminal não tem série própria configurada.
            'pdv_terminal' => $caixa,
            'pdv_terminal_serie' => $caixa['serie'] ?? $vp?->serie,
            'resp_tecnico' => $this->respTecnicoPayload($vp),
            // Impressão digital do certificado A1: o PDV usa para saber quando
            // precisa (re)baixar o .pfx via /carga/certificado.
            'certificado_fingerprint' => $this->certificadoFingerprint($empresaId),
            'params' => $params,
            'updated_at' => optional($empresa->updated_at)->toIso8601String(),
        ];
    }

    /**
     * SHA-1 do certificado A1 atual (ou null se não houver). Barato e estável.
     */
    public function certificadoFingerprint(int $empresaId): ?string
    {
        $params = VendasParametro::query()->find($empresaId);

        if ($params === null) {
            return null;
        }

        $path = NfeFiscalConfig::certificadoAbsolutePath($params);

        if ($path === null || ! is_file($path)) {
            return null;
        }

        $hash = sha1_file($path);

        return $hash !== false ? $hash : null;
    }

    /**
     * Blob criptografado (AES-256-GCM) com o certificado A1 (.pfx) + senha,
     * para o PDV assinar NFC-e offline. A chave deriva de empresa + terminal.
     *
     * @return array{algo: string, iv: string, tag: string, data: string, fingerprint: string}|null
     */
    public function certificado(int $empresaId, int $terminalId): ?array
    {
        $params = VendasParametro::query()->find($empresaId);

        if ($params === null) {
            return null;
        }

        $path = NfeFiscalConfig::certificadoAbsolutePath($params);
        $senha = $params->safeSenhaCertificado();

        if ($path === null || ! is_file($path) || $senha === null) {
            return null;
        }

        $content = file_get_contents($path);

        if ($content === false) {
            return null;
        }

        if ($terminalId < 1) {
            return null;
        }

        $fingerprint = sha1($content);

        $plaintext = json_encode([
            'pfx' => base64_encode($content),
            'senha' => $senha,
            'numero_serie' => $params->numero_serie_certificado,
            'fingerprint' => $fingerprint,
        ], JSON_THROW_ON_ERROR);

        $blob = PdvCargaCrypto::encrypt($plaintext, $empresaId, $terminalId);
        $blob['fingerprint'] = $fingerprint;

        return $blob;
    }

    /**
     * Responsável técnico da aba Configurações fiscais (não vai em params: a
     * carga filtra chaves com "email").
     *
     * @return array{cnpj: string, contato: string, email: string, fone: string, id_csrt: ?string, csrt: ?string}
     */
    private function respTecnicoPayload(?VendasParametro $vp): array
    {
        $fixed = NfeFiscalConfig::defaultRespTecnico();
        $dto = $vp !== null ? NfeFiscalConfig::respTecnicoFromParametros($vp) : null;

        return [
            'cnpj' => $dto?->cnpj ?? $fixed['cnpj'],
            'contato' => $dto?->contato ?? $fixed['contato'],
            'email' => $dto?->email ?? $fixed['email'],
            'fone' => $dto?->fone ?? $fixed['fone'],
            'id_csrt' => $dto?->idCsrt,
            'csrt' => $dto?->csrtToken,
        ];
    }

    /**
     * Resolve o caixa que fez a carga (por nº lógico ou nome) e sua série NFC-e.
     *
     * @return array{id: int, nome: string, terminal: string, serie: string, numero_inicial: int, usar_numero_inicial: bool}|null
     */
    private function resolveTerminal(int $empresaId, ?string $terminal): ?array
    {
        $model = PdvOfflineTerminalLookup::find($empresaId, (string) $terminal, true);

        if ($model === null) {
            return null;
        }

        $params = VendasParametro::forEmpresa($empresaId);
        $serie = NfceTerminalSequencia::serieEfetiva($model, $params);

        return [
            'id' => (int) $model->id,
            'nome' => (string) ($model->nome ?: 'Caixa'),
            'terminal' => (string) ($model->numero_logico_terminal ?: $model->id),
            'serie' => $serie,
            'numero_inicial' => NfceTerminalSequencia::proximoPiso($model, $params),
            'usar_numero_inicial' => true,
            // Comportamento por caixa (espelha o que o PdvConfig do ERP lê do terminal).
            'config' => [
                'pesquisa_rapida' => (bool) $model->pesquisa_rapida,
                'restaurante' => (bool) $model->restaurante,
                'delivery' => (bool) $model->delivery,
                'ler_peso' => (bool) $model->ler_peso,
                'busca_balanca_barras' => (bool) $model->busca_balanca_barras,
                'usa_tef' => (bool) $model->usa_tef,
                'usa_pos' => (bool) $model->usa_pos,
                'usa_gaveta' => (bool) $model->usa_gaveta,
                'usar_device_service' => true,
                'imprime' => (bool) $model->imprime,
                'tipo_impressora' => (string) ($model->tipo_impressora ?? '0'),
                'impressora_nome' => $model->impressora_nome,
                'porta' => $model->porta,
                // Device Service sempre ativo no PDV offline (agente no PC do caixa).
                'balanca_marca' => (string) ($model->balanca_marca ?? ''),
                'balanca_porta' => (string) ($model->balanca_porta ?? ''),
                'balanca_velocidade' => (int) ($model->balanca_velocidade ?: 9600),
                'balanca_databits' => (int) ($model->balanca_databits ?: 8),
                'balanca_paridade' => (string) ($model->balanca_paridade ?: 'None'),
                'balanca_stopbits' => (string) ($model->balanca_stopbits ?: '1'),
                'balanca_handshaking' => (string) ($model->balanca_handshaking ?: 'None'),
                'exibe_f3' => (bool) $model->exibe_f3,
                'exibe_f4' => (bool) $model->exibe_f4,
                'exibe_f5' => (bool) $model->exibe_f5,
                'exibe_f6' => (bool) $model->exibe_f6,
            ],
        ];
    }
}
