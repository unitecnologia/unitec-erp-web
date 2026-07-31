<?php

namespace App\Support\Erp\Import;

/**
 * Orquestra migração Firebird → ERP web.
 */
final class FirebirdMigraService
{
    public const PRODUTO_BATCH = 100;

    public const PDV_NFCE_BATCH = 40;

    public const NFE_BATCH = 25;

    public function __construct(
        private readonly FirebirdIsqlClient $client,
        private readonly FirebirdEmpresaImportService $empresas,
        private readonly FirebirdProductImportService $produtos,
        private readonly FirebirdPersonImportService $pessoas,
        private readonly FirebirdCadastrosImportService $cadastros,
        private readonly FirebirdContaPagarImportService $contasPagar,
        private readonly FirebirdContaReceberImportService $contasReceber,
        private readonly FirebirdCaixaLancamentoImportService $caixaLancamentos,
        private readonly FirebirdPdvVendaImportService $pdvVendas,
        private readonly FirebirdPdvNfceImportService $pdvNfce,
        private readonly FirebirdNfeImportService $nfes,
        private readonly FirebirdPdvCaixaMovimentoImportService $pdvCaixaMovimentos,
        private readonly FirebirdPlanoContaImportService $planosContas,
        private readonly FirebirdContaPagarPagamentoImportService $contaPagarPagamentos,
        private readonly FirebirdProdUltimosPrecosImportService $prodUltimosPrecos,
        private readonly FirebirdVendasParametroImportService $vendasParametros,
        private readonly FirebirdCompraImportService $compras,
        private readonly FirebirdNotaFornecedorImportService $notasFornecedor,
    ) {
    }

    public function client(): FirebirdIsqlClient
    {
        return $this->client;
    }

    /**
     * @param  list<string>  $only
     * @return array<string, array<string, int|null>>
     */
    public function migrate(array $only = [], bool $updateExisting = true, bool $dryRun = false): array
    {
        $only = $this->normalizeOnly($only);
        $result = [];

        foreach ($this->expandDomains($only) as $domain) {
            if ($domain === 'produtos') {
                $result['produtos'] = $this->importProdutosEmLotes($updateExisting, $dryRun);

                continue;
            }

            $part = $this->migrateDomain($domain, $only, $updateExisting, $dryRun);
            foreach ($part as $key => $stats) {
                $result[$key] = $stats;
            }
        }

        return $result;
    }

    /**
     * Migra um domínio (exceto produtos em lote — use migrateProdutosLote).
     *
     * @param  list<string>  $only
     * @return array<string, array{created: int, updated: int, skipped: int, empresa_id?: int|null}>
     */
    public function migrateDomain(string $domain, array $only = [], bool $updateExisting = true, bool $dryRun = false): array
    {
        $domain = strtolower(trim($domain));
        $only = $this->normalizeOnly($only);

        return match ($domain) {
            'contas', 'caixa_contas' => [
                'contas' => $this->cadastros->importCaixaContas(
                    $this->client->query('SELECT * FROM CONTAS ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'empresa' => [
                'empresa' => $this->empresas->importRows(
                    $this->client->query('SELECT * FROM EMPRESA ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'grupos' => [
                'grupos' => $this->cadastros->importGrupos(
                    $this->client->query('SELECT * FROM GRUPO ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'marcas' => [
                'marcas' => $this->cadastros->importMarcas(
                    $this->client->query('SELECT * FROM MARCA ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'unidades' => [
                'unidades' => $this->cadastros->importUnidades(
                    $this->client->query('SELECT * FROM UNIDADE ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'auxiliares' => array_merge(
                $this->migrateDomain('grupos', $only, $updateExisting, $dryRun),
                $this->migrateDomain('marcas', $only, $updateExisting, $dryRun),
                $this->migrateDomain('unidades', $only, $updateExisting, $dryRun),
            ),
            'pessoas', 'clientes' => [
                'pessoas' => $this->pessoas->importRows(
                    $this->client->query(
                        $domain === 'clientes' || (in_array('clientes', $only, true) && ! in_array('pessoas', $only, true))
                            ? "SELECT * FROM PESSOA WHERE CLI = 'S' ORDER BY CODIGO"
                            : 'SELECT * FROM PESSOA ORDER BY CODIGO'
                    ),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'formas', 'formas_pagamento' => [
                'formas' => $this->cadastros->importFormasPagamento(
                    $this->client->query('SELECT * FROM FORMA_PAGAMENTO ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'vendedores' => [
                'vendedores' => $this->cadastros->importVendedores(
                    $this->client->query('SELECT * FROM VENDEDORES ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'usuarios', 'users' => [
                'usuarios' => $this->cadastros->importUsuarios(
                    $this->client->query('SELECT * FROM USUARIOS ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'contador', 'contadores' => [
                'contador' => $this->cadastros->importContadores(
                    $this->client->query('SELECT * FROM CONTADOR ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'terminais' => [
                'terminais' => $this->cadastros->importTerminais(
                    $this->client->query('SELECT * FROM VENDAS_TERMINAIS ORDER BY NOME'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'contas_pagar', 'cpagar' => [
                'contas_pagar' => $this->contasPagar->importRows(
                    $this->client->query('SELECT * FROM CPAGAR ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'contas_receber', 'creceber' => [
                'contas_receber' => $this->contasReceber->importRows(
                    $this->client->query('SELECT * FROM CRECEBER ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'caixa', 'caixa_lancamentos', 'lancamentos_caixa' => [
                'caixa' => $this->importCaixaLancamentos($updateExisting, $dryRun),
            ],
            'planos_contas', 'planos', 'plano' => [
                'planos_contas' => $this->planosContas->importRows(
                    $this->client->query('SELECT * FROM PLANO ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'conta_pagar_pagamentos', 'cppagamento', 'baixas_pagar' => [
                'conta_pagar_pagamentos' => $this->contaPagarPagamentos->importRows(
                    $this->client->query('SELECT * FROM CPPAGAMENTO ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'ultimos_precos', 'prod_ultimos_precos' => [
                'ultimos_precos' => $this->prodUltimosPrecos->importRows(
                    $this->client->query('SELECT * FROM PROD_ULTIMOS_PRECOS ORDER BY CODIGO'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'vendas_parametros', 'parametros_fiscais' => [
                'vendas_parametros' => $this->vendasParametros->importRows(
                    $this->client->query('SELECT * FROM VENDAS_PARAMETROS'),
                    $updateExisting,
                    $dryRun,
                ),
            ],
            'pdv_vendas', 'vendas_pdv', 'vendas' => [
                'pdv_vendas' => $this->importPdvVendas($updateExisting, $dryRun),
            ],
            'pdv_nfce', 'nfce' => [
                'pdv_nfce' => $this->importPdvNfce($updateExisting, $dryRun),
            ],
            'nfes', 'nfe' => [
                'nfes' => $this->importNfes($updateExisting, $dryRun),
            ],
            'compras', 'compra' => [
                'compras' => $this->importCompras($updateExisting, $dryRun),
            ],
            'notas_fornecedor', 'notas_fornecedores', 'xml_master', 'xml_entrada', 'nfe_manifesto', 'manifesto' => [
                'notas_fornecedor' => $this->importNotasFornecedor($updateExisting, $dryRun),
            ],
            'pdv_caixa_movimentos', 'contas_movimento', 'movimentos_pdv' => [
                'pdv_caixa_movimentos' => $this->importPdvCaixaMovimentos($updateExisting, $dryRun),
            ],
            'produtos', 'estoque' => [
                'produtos' => $this->importProdutosEmLotes($updateExisting, $dryRun),
            ],
            default => throw new \InvalidArgumentException("Domínio de migração desconhecido: {$domain}"),
        };
    }

    /**
     * Um lote de produtos (para barra de progresso na UI).
     *
     * @return array{created: int, updated: int, skipped: int, done: bool, next_skip: int, fetched: int}
     */
    public function migrateProdutosLote(int $skip = 0, bool $updateExisting = true, bool $dryRun = false, int $batch = self::PRODUTO_BATCH): array
    {
        $skip = max(0, $skip);
        $batch = max(1, $batch);

        $sql = "SELECT FIRST {$batch} SKIP {$skip} P.*, G.DESCRICAO AS GRUPO_NOME "
            .'FROM PRODUTO P LEFT JOIN GRUPO G ON G.CODIGO = P.GRUPO '
            .'ORDER BY P.CODIGO';

        $rows = $this->client->query($sql);
        $fetched = count($rows);

        if ($fetched === 0) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'done' => true,
                'next_skip' => $skip,
                'fetched' => 0,
            ];
        }

        $part = $this->produtos->importRows($rows, $updateExisting, $dryRun);

        return [
            'created' => (int) ($part['created'] ?? 0),
            'updated' => (int) ($part['updated'] ?? 0),
            'skipped' => (int) ($part['skipped'] ?? 0),
            'done' => $fetched < $batch,
            'next_skip' => $skip + $batch,
            'fetched' => $fetched,
        ];
    }

    /**
     * @param  list<string>  $only
     * @return list<string>
     */
    public function expandDomains(array $only): array
    {
        $only = $this->normalizeOnly($only);

        if ($only === []) {
            return [
                'contas', 'empresa', 'auxiliares', 'produtos', 'pessoas',
                'formas', 'vendedores', 'usuarios', 'contador', 'terminais',
                'planos_contas', 'contas_pagar', 'conta_pagar_pagamentos', 'contas_receber',
                'caixa', 'ultimos_precos', 'vendas_parametros',
                'pdv_vendas', 'pdv_nfce', 'nfes', 'compras', 'notas_fornecedor', 'pdv_caixa_movimentos',
            ];
        }

        // Contas a pagar/receber, NF-e e compras precisam de pessoas (clientes/fornecedores).
        $precisaPessoas = array_intersect($only, [
            'contas_pagar',
            'conta_pagar_pagamentos',
            'contas_receber',
            'nfes',
            'nfe',
            'compras',
            'compra',
            'notas_fornecedor',
            'notas_fornecedores',
            'xml_master',
            'xml_entrada',
            'nfe_manifesto',
            'manifesto',
        ]) !== [];

        if ($precisaPessoas) {
            if (in_array('clientes', $only, true)) {
                $only = array_values(array_diff($only, ['clientes']));
            }
            if (! in_array('pessoas', $only, true)) {
                array_unshift($only, 'pessoas');
            }
        }

        if (array_intersect($only, ['nfes', 'nfe', 'compras', 'compra']) !== [] && ! in_array('produtos', $only, true)) {
            // Itens da NF-e / compra referenciam produtos pelo código legado.
            array_unshift($only, 'produtos');
        }

        $order = [
            'contas', 'empresa', 'auxiliares', 'produtos', 'clientes', 'pessoas',
            'formas', 'vendedores', 'usuarios', 'contador', 'terminais',
            'planos_contas', 'contas_pagar', 'conta_pagar_pagamentos', 'contas_receber',
            'caixa', 'ultimos_precos', 'vendas_parametros',
            'pdv_vendas', 'pdv_nfce', 'nfes', 'compras', 'notas_fornecedor', 'pdv_caixa_movimentos',
        ];

        $expanded = [];
        foreach ($order as $key) {
            if ($key === 'auxiliares' && (in_array('auxiliares', $only, true)
                || in_array('grupos', $only, true)
                || in_array('marcas', $only, true)
                || in_array('unidades', $only, true))) {
                $expanded[] = 'auxiliares';

                continue;
            }

            if ($key === 'produtos' && (in_array('produtos', $only, true) || in_array('estoque', $only, true))) {
                $expanded[] = 'produtos';

                continue;
            }

            if ($key === 'clientes' && in_array('clientes', $only, true) && ! in_array('pessoas', $only, true)) {
                $expanded[] = 'clientes';

                continue;
            }

            if ($key === 'pessoas' && in_array('pessoas', $only, true)) {
                $expanded[] = 'pessoas';

                continue;
            }

            if (in_array($key, $only, true)) {
                $expanded[] = $key;
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importCaixaLancamentos(bool $updateExisting, bool $dryRun): array
    {
        $planoNomeByCodigo = [];
        foreach ($this->client->query('SELECT CODIGO, DESCRICAO FROM PLANO ORDER BY CODIGO') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $codigo = trim((string) ($row['CODIGO'] ?? $row['codigo'] ?? ''));
            $nome = trim((string) ($row['DESCRICAO'] ?? $row['descricao'] ?? ''));

            if ($codigo === '' || $nome === '') {
                continue;
            }

            $planoNomeByCodigo[$codigo] = $nome;
            $planoNomeByCodigo[(string) (int) $codigo] = $nome;
        }

        $rows = $this->client->query(
            'SELECT C.*, P.DESCRICAO AS PLANO_NOME '
            .'FROM CAIXA C LEFT JOIN PLANO P ON P.CODIGO = C.FKPLANO '
            .'ORDER BY C.CODIGO'
        );

        return $this->caixaLancamentos->importRows($rows, $updateExisting, $dryRun, $planoNomeByCodigo);
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importPdvVendas(bool $updateExisting, bool $dryRun): array
    {
        $svc = $this->pdvVendas;

        $usuarioRows = $this->client->query('SELECT CODIGO, LOGIN FROM USUARIOS ORDER BY CODIGO');
        $userIdByFbCodigo = $svc->buildUserIdByFbCodigo($usuarioRows);
        $fallbackUserId = (int) (\App\Models\User::query()->orderBy('id')->value('id') ?? 0);

        if ($fallbackUserId < 1) {
            throw new \RuntimeException('Nenhum usuário no web para vincular vendas PDV. Migre usuários primeiro.');
        }

        $loteRows = $this->client->query(
            'SELECT LOTE, MIN(DATA_EMISSAO) AS DE, MAX(DATA_EMISSAO) AS ATE, MIN(FK_USUARIO) AS USU '
            .'FROM VENDAS_MASTER WHERE LOTE IS NOT NULL '
            .'GROUP BY LOTE ORDER BY LOTE'
        );

        $sessaoIdByLote = $svc->ensureSessoes($loteRows, $userIdByFbCodigo, $fallbackUserId, $dryRun);

        $personIdByCodigo = $svc->buildPersonIdByCodigo();
        $vendedorIdByCodigo = $svc->buildVendedorIdByCodigo();
        $productByCodigo = $svc->buildProductByCodigo();
        $formaByCodigo = $svc->buildFormaByCodigo();

        $masters = $this->client->query('SELECT * FROM VENDAS_MASTER ORDER BY CODIGO');
        $itens = $this->client->query('SELECT * FROM VENDAS_DETALHE ORDER BY FKVENDA, ITEM');
        $pagamentos = $this->client->query('SELECT * FROM VENDAS_FPG ORDER BY VENDAS_MASTER, CODIGO');

        // Processa em fatias para não segurar uma única transaction gigante.
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $chunks = array_chunk($masters, FirebirdPdvVendaImportService::BATCH);

        foreach ($chunks as $chunk) {
            $codigos = [];
            foreach ($chunk as $row) {
                if (is_array($row)) {
                    $codigos[] = (int) ($row['CODIGO'] ?? 0);
                }
            }
            $codigos = array_values(array_filter($codigos, fn (int $c): bool => $c > 0));
            $codigoSet = array_fill_keys($codigos, true);

            $itensChunk = array_values(array_filter(
                $itens,
                fn ($r): bool => is_array($r) && isset($codigoSet[(int) ($r['FKVENDA'] ?? 0)]),
            ));
            $pagChunk = array_values(array_filter(
                $pagamentos,
                fn ($r): bool => is_array($r) && isset($codigoSet[(int) ($r['VENDAS_MASTER'] ?? 0)]),
            ));

            $part = $svc->importRows(
                $chunk,
                $itensChunk,
                $pagChunk,
                $sessaoIdByLote,
                $userIdByFbCodigo,
                $personIdByCodigo,
                $vendedorIdByCodigo,
                $productByCodigo,
                $formaByCodigo,
                $fallbackUserId,
                $updateExisting,
                $dryRun,
            );

            $stats['created'] += $part['created'];
            $stats['updated'] += $part['updated'];
            $stats['skipped'] += $part['skipped'];
        }

        return $stats;
    }

    /**
     * NFC-e com XML via CAST (BLOB → VARCHAR) — isql LIST não devolve BLOB cru.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importPdvNfce(bool $updateExisting, bool $dryRun): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $skip = 0;

        while (true) {
            $part = $this->migratePdvNfceLote($skip, $updateExisting, $dryRun);
            $stats['created'] += $part['created'];
            $stats['updated'] += $part['updated'];
            $stats['skipped'] += $part['skipped'];
            $skip = $part['next_skip'];

            if ($part['done']) {
                break;
            }
        }

        return $stats;
    }

    /**
     * Um lote de NFC-e (barra de progresso na UI).
     *
     * @return array{created: int, updated: int, skipped: int, done: bool, next_skip: int, fetched: int, processed: int, total: int|null}
     */
    public function migratePdvNfceLote(int $skip = 0, bool $updateExisting = true, bool $dryRun = false, int $batch = self::PDV_NFCE_BATCH): array
    {
        $skip = max(0, $skip);
        $batch = max(1, $batch);

        $total = null;
        if ($skip === 0) {
            $countRows = $this->client->query(
                'SELECT COUNT(*) AS QTD FROM NFCE_MASTER WHERE FK_VENDA IS NOT NULL'
            );
            $total = (int) ($countRows[0]['QTD'] ?? $countRows[0]['qtd'] ?? 0);
        }

        $rows = $this->client->query(
            "SELECT FIRST {$batch} SKIP {$skip} "
            .'CODIGO, NUMERO, CHAVE, MODELO, SERIE, DATA_EMISSAO, HORA_EMISSAO, '
            .'FK_VENDA, SITUACAO, PROTOCOLO, MOTIVOCANCELAMENTO, FKEMPRESA, CNF, ABERTO, FLAG, '
            .'CAST(XML AS VARCHAR(32000)) AS XML_TXT, '
            .'CAST(XML_CANCELAMENTO AS VARCHAR(32000)) AS XML_CANC_TXT '
            .'FROM NFCE_MASTER WHERE FK_VENDA IS NOT NULL '
            .'ORDER BY CODIGO'
        );

        $fetched = count($rows);

        if ($fetched === 0) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'done' => true,
                'next_skip' => $skip,
                'fetched' => 0,
                'processed' => $skip,
                'total' => $total,
            ];
        }

        $part = $this->pdvNfce->importRows($rows, $updateExisting, $dryRun);
        $nextSkip = $skip + $batch;
        $processed = $skip + $fetched;

        return [
            'created' => (int) ($part['created'] ?? 0),
            'updated' => (int) ($part['updated'] ?? 0),
            'skipped' => (int) ($part['skipped'] ?? 0),
            'done' => $fetched < $batch,
            'next_skip' => $nextSkip,
            'fetched' => $fetched,
            'processed' => $processed,
            'total' => $total,
        ];
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importNfes(bool $updateExisting, bool $dryRun): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $skip = 0;

        while (true) {
            $part = $this->migrateNfeLote($skip, $updateExisting, $dryRun);
            $stats['created'] += $part['created'];
            $stats['updated'] += $part['updated'];
            $stats['skipped'] += $part['skipped'];
            $skip = $part['next_skip'];

            if ($part['done']) {
                break;
            }
        }

        return $stats;
    }

    /**
     * Um lote de NF-e (modelo 55) — master + itens + XML.
     *
     * @return array{created: int, updated: int, skipped: int, done: bool, next_skip: int, fetched: int, processed: int, total: int|null}
     */
    public function migrateNfeLote(int $skip = 0, bool $updateExisting = true, bool $dryRun = false, int $batch = self::NFE_BATCH): array
    {
        $skip = max(0, $skip);
        $batch = max(1, $batch);

        $total = null;
        if ($skip === 0) {
            $countRows = $this->client->query('SELECT COUNT(*) AS QTD FROM NFE_MASTER');
            $total = (int) ($countRows[0]['QTD'] ?? $countRows[0]['qtd'] ?? 0);
        }

        $masters = $this->client->query(
            "SELECT FIRST {$batch} SKIP {$skip} "
            .'CODIGO, NUMERO, CHAVE, MODELO, SERIE, SITUACAO, '
            .'DATA_EMISSAO, DATA_SAIDA, HORA_EMISSAO, HORA_SAIDA, '
            .'ID_EMITENTE, ID_CLIENTE, ID_TRANSPORTADOR, FKEMPRESA, '
            .'TIPO_FRETE, SUBTOTAL, DESPESAS, SEGURO, FRETE, DESCONTO, TROCO, TOTAL, '
            .'BASE_ST, TOTAL_ST, BASE_IPI, TOTAL_IPI, BASEICMS, TOTALICMS, '
            .'BASEICMSPIS, TOTALICMSPIS, BASEICMSCOF, TOTALICMSCOFINS, '
            .'FINALIDADE, MOVIMENTO, CONSUMIDOR_FINAL, TIPO_EMISSAO, CFOP, NPEDIDO, '
            .'PROTOCOLO, CNF, '
            .'QVOL, ESPECIE, MARCA, NVOL, PLACA, RNTC '
            .'FROM NFE_MASTER ORDER BY CODIGO'
        );

        $masters = $this->coalesceIsqlRows($masters);
        $fetched = count($masters);
        if ($fetched === 0) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'done' => true,
                'next_skip' => $skip,
                'fetched' => 0,
                'processed' => $skip,
                'total' => $total,
            ];
        }

        $codigos = [];
        foreach ($masters as $row) {
            $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
            if ($codigo > 0) {
                $codigos[] = $codigo;
            }
        }

        $items = [];
        if ($codigos !== []) {
            $inList = implode(',', $codigos);
            $items = $this->client->query(
                'SELECT * FROM NFE_DETALHE WHERE FKNFE IN ('.$inList.') ORDER BY FKNFE, ITEM'
            );
        }

        // XML em consulta separada (isql quebra linha quando BLOB vem junto do master).
        foreach ($masters as $i => $row) {
            $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
            if ($codigo < 1) {
                continue;
            }

            try {
                $xmlRows = $this->client->query(
                    'SELECT CAST(XML AS VARCHAR(32000)) AS XML_TXT, '
                    .'CAST(XML_CANCELAMENTO AS VARCHAR(32000)) AS XML_CANC_TXT '
                    .'FROM NFE_MASTER WHERE CODIGO = '.$codigo
                );
                $merged = [];
                foreach ($xmlRows as $part) {
                    if (is_array($part)) {
                        $merged = array_merge($merged, $part);
                    }
                }
                if (isset($merged['XML_TXT'])) {
                    $masters[$i]['XML_TXT'] = $merged['XML_TXT'];
                }
                if (isset($merged['XML_CANC_TXT'])) {
                    $masters[$i]['XML_CANC_TXT'] = $merged['XML_CANC_TXT'];
                }
            } catch (\Throwable) {
                // Mantém master sem XML.
            }
        }

        $part = $this->nfes->importRows($masters, $items, $updateExisting, $dryRun);
        $nextSkip = $skip + $batch;
        $processed = $skip + $fetched;

        return [
            'created' => (int) ($part['created'] ?? 0),
            'updated' => (int) ($part['updated'] ?? 0),
            'skipped' => (int) ($part['skipped'] ?? 0),
            'done' => $fetched < $batch,
            'next_skip' => $nextSkip,
            'fetched' => $fetched,
            'processed' => $processed,
            'total' => $total,
        ];
    }

    /**
     * isql às vezes devolve um registro “quebrado” em várias linhas (BLOB/campos longos).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function coalesceIsqlRows(array $rows): array
    {
        $out = [];
        $current = null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $hasCodigo = isset($row['CODIGO']) || isset($row['codigo']);
            if ($hasCodigo) {
                if ($current !== null) {
                    $out[] = $current;
                }
                $current = $row;

                continue;
            }

            if ($current !== null) {
                $current = array_merge($current, $row);
            }
        }

        if ($current !== null) {
            $out[] = $current;
        }

        return $out;
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importCompras(bool $updateExisting, bool $dryRun): array
    {
        $masters = $this->client->query(
            'SELECT ID, EMPRESA, DTENTRADA, DTEMISSAO, FORNECEDOR, MODELO, SERIE, CHAVE, '
            .'NR_NOTA, SUBTOTAL, FRETE, DESPESAS, SEGURO, DESCONTO, TOTAL, STATUS, NOME '
            .'FROM COMPRA ORDER BY ID'
        );
        $masters = $this->coalesceIsqlRowsByKey($masters, 'ID');

        $ids = [];
        foreach ($masters as $row) {
            $id = (int) ($row['ID'] ?? $row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $items = [];
        if ($ids !== []) {
            $inList = implode(',', $ids);
            $items = $this->client->query(
                'SELECT ID, FK_COMPRA, ITEM, FK_PRODUTO, QTD, VL_UNITARIO, VL_ITEM, TOTAL, DESCRICAO '
                .'FROM COMPRA_ITENS WHERE FK_COMPRA IN ('.$inList.') ORDER BY FK_COMPRA, ITEM'
            );
        }

        return $this->compras->importRows($masters, $items, $updateExisting, $dryRun);
    }

    /**
     * Notas de compra / DF-e: NFE_MANIFESTO (lista do legado).
     * XML via CAST; fallback XML_MASTER / COMPRA pela chave quando necessário.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importNotasFornecedor(bool $updateExisting, bool $dryRun): array
    {
        $masters = $this->client->query(
            'SELECT CODIGO, NUMERO, CHAVE, SERIE, NOME, CNPJ, NSU, VALOR, '
            .'DT_ENTRADA, DT_EMISSAO, SITUACAO, FK_EMPRESA, GEROU '
            .'FROM NFE_MANIFESTO ORDER BY CODIGO'
        );
        $masters = $this->coalesceIsqlRowsByKey($masters, 'CODIGO');

        foreach ($masters as $i => $row) {
            $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
            if ($codigo < 1) {
                continue;
            }

            $chave = preg_replace('/\D/', '', (string) ($row['CHAVE'] ?? $row['chave'] ?? '')) ?: '';
            $xmlTxt = $this->fetchXmlBlob(
                'SELECT CAST(XML AS VARCHAR(32000)) AS XML_TXT FROM NFE_MANIFESTO WHERE CODIGO = '.$codigo
            );

            // Preferência: manifesto → XML_MASTER → COMPRA (todas têm XML no legado).
            if ($xmlTxt === null && $chave !== '') {
                $chaveSql = str_replace("'", "''", $chave);
                $xmlTxt = $this->fetchXmlBlob(
                    "SELECT CAST(XML AS VARCHAR(32000)) AS XML_TXT FROM XML_MASTER WHERE REPLACE(CHAVE, ' ', '') = '{$chaveSql}'"
                );
            }
            if ($xmlTxt === null && $chave !== '') {
                $chaveSql = str_replace("'", "''", $chave);
                $xmlTxt = $this->fetchXmlBlob(
                    "SELECT CAST(XML AS VARCHAR(32000)) AS XML_TXT FROM COMPRA WHERE REPLACE(CHAVE, ' ', '') = '{$chaveSql}'"
                );
            }

            if ($xmlTxt !== null) {
                $masters[$i]['XML_TXT'] = $xmlTxt;
            }
        }

        return $this->notasFornecedor->importRows($masters, $updateExisting, $dryRun);
    }

    protected function fetchXmlBlob(string $sql): ?string
    {
        try {
            $xmlRows = $this->client->query($sql);
            $merged = [];
            foreach ($xmlRows as $part) {
                if (is_array($part)) {
                    $merged = array_merge($merged, $part);
                }
            }

            $raw = $merged['XML_TXT'] ?? $merged['xml_txt'] ?? null;
            if ($raw === null) {
                return null;
            }

            $txt = trim((string) $raw);

            return ($txt !== '' && strtoupper($txt) !== '<NULL>') ? $txt : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    protected function coalesceIsqlRowsByKey(array $rows, string $key): array
    {
        $keyUpper = strtoupper($key);
        $keyLower = strtolower($key);
        $out = [];
        $current = null;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $hasKey = isset($row[$keyUpper]) || isset($row[$keyLower]) || isset($row[$key]);
            if ($hasKey) {
                if ($current !== null) {
                    $out[] = $current;
                }
                $current = $row;

                continue;
            }

            if ($current !== null) {
                $current = array_merge($current, $row);
            }
        }

        if ($current !== null) {
            $out[] = $current;
        }

        return $out;
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importPdvCaixaMovimentos(bool $updateExisting, bool $dryRun): array
    {
        $svc = $this->pdvCaixaMovimentos;
        $vendaSvc = $this->pdvVendas;

        $usuarioRows = $this->client->query('SELECT CODIGO, LOGIN FROM USUARIOS ORDER BY CODIGO');
        $userIdByFbCodigo = $vendaSvc->buildUserIdByFbCodigo($usuarioRows);
        $fallbackUserId = (int) (\App\Models\User::query()->orderBy('id')->value('id') ?? 0);

        if ($fallbackUserId < 1) {
            throw new \RuntimeException('Nenhum usuário no web para vincular movimentos de caixa PDV.');
        }

        $loteRows = $this->client->query(
            'SELECT LOTE, MIN(DATA) AS DE, MAX(DATA) AS ATE, MIN(ID_USUARIO) AS USU '
            .'FROM CONTAS_MOVIMENTO WHERE LOTE IS NOT NULL '
            .'GROUP BY LOTE ORDER BY LOTE'
        );

        $vendaLotes = $this->client->query('SELECT LOTE, CODIGO FROM VENDAS_MASTER WHERE LOTE IS NOT NULL');
        $vendaNumerosByLote = [];
        foreach ($vendaLotes as $row) {
            if (! is_array($row)) {
                continue;
            }
            $lote = trim((string) ($row['LOTE'] ?? ''));
            $codigo = (int) ($row['CODIGO'] ?? 0);
            if ($lote === '' || $codigo < 1) {
                continue;
            }
            $vendaNumerosByLote[$lote][] = $codigo;
            $vendaNumerosByLote[(string) (int) $lote][] = $codigo;
        }

        $sessaoIdByLote = $svc->resolveSessaoIdByLote(
            $loteRows,
            $userIdByFbCodigo,
            $fallbackUserId,
            $dryRun,
            $vendaNumerosByLote,
        );

        $rows = $this->client->query('SELECT * FROM CONTAS_MOVIMENTO ORDER BY CODIGO');

        return $svc->importRows($rows, $sessaoIdByLote, $dryRun, replaceExisting: ! $dryRun);
    }

    /**
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function importProdutosEmLotes(bool $updateExisting, bool $dryRun): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $skip = 0;

        while (true) {
            $part = $this->migrateProdutosLote($skip, $updateExisting, $dryRun);
            $stats['created'] += $part['created'];
            $stats['updated'] += $part['updated'];
            $stats['skipped'] += $part['skipped'];
            $skip = $part['next_skip'];

            if ($part['done']) {
                break;
            }
        }

        return $stats;
    }

    /**
     * @param  list<string>  $only
     * @return list<string>
     */
    protected function normalizeOnly(array $only): array
    {
        $aliases = [
            'caixa_contas' => 'contas',
            'estoque' => 'produtos',
            'formas_pagamento' => 'formas',
            'users' => 'usuarios',
            'contadores' => 'contador',
            'cpagar' => 'contas_pagar',
            'creceber' => 'contas_receber',
            'caixa_lancamentos' => 'caixa',
            'lancamentos_caixa' => 'caixa',
            'planos' => 'planos_contas',
            'plano' => 'planos_contas',
            'cppagamento' => 'conta_pagar_pagamentos',
            'baixas_pagar' => 'conta_pagar_pagamentos',
            'prod_ultimos_precos' => 'ultimos_precos',
            'parametros_fiscais' => 'vendas_parametros',
            'vendas_pdv' => 'pdv_vendas',
            'vendas' => 'pdv_vendas',
            'nfce' => 'pdv_nfce',
            'nfe' => 'nfes',
            'compra' => 'compras',
            'notas_fornecedores' => 'notas_fornecedor',
            'xml_master' => 'notas_fornecedor',
            'xml_entrada' => 'notas_fornecedor',
            'nfe_manifesto' => 'notas_fornecedor',
            'manifesto' => 'notas_fornecedor',
            'contas_movimento' => 'pdv_caixa_movimentos',
            'movimentos_pdv' => 'pdv_caixa_movimentos',
        ];

        return array_values(array_unique(array_filter(array_map(
            function ($v) use ($aliases): string {
                $key = strtolower(trim((string) $v));

                return $aliases[$key] ?? $key;
            },
            $only,
        ))));
    }
}
