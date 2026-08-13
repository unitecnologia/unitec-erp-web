<?php

namespace App\Support\Erp\Import;

use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\PdvCaixaSessao;
use App\Models\PdvVenda;
use App\Models\PdvVendaItem;
use App\Models\PdvVendaPagamento;
use App\Models\Person;
use App\Models\Product;
use App\Models\Terminal;
use App\Models\User;
use App\Models\Venda;
use App\Models\Vendedor;
use App\Support\Erp\Pdv\PdvVendaRetaguardaMirrorService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FirebirdPdvVendaImportService
{
    public const BATCH = 100;

    /**
     * @param  array<string, int>  $sessaoIdByLote
     * @param  array<string, int>  $userIdByFbCodigo
     * @param  array<string, int>  $personIdByCodigo
     * @param  array<string, int>  $vendedorIdByCodigo
     * @param  array<string, array{id: int, descricao: string, unidade: string}>  $productByCodigo
     * @param  array<string, string>  $formaByCodigo
     * @return array<string, mixed>|null
     */
    public function mapMasterRow(
        array $row,
        array $sessaoIdByLote,
        array $userIdByFbCodigo,
        array $personIdByCodigo,
        array $vendedorIdByCodigo,
        array $formaByCodigo,
        int $fallbackUserId,
    ): ?array {
        $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);

        if ($codigo < 1) {
            return null;
        }

        $lote = trim((string) ($row['LOTE'] ?? $row['lote'] ?? ''));
        $sessaoId = $lote !== ''
            ? ($sessaoIdByLote[$lote] ?? $sessaoIdByLote[(string) (int) $lote] ?? null)
            : null;

        if (! $sessaoId) {
            return null;
        }

        $fkUser = trim((string) ($row['FK_USUARIO'] ?? $row['fk_usuario'] ?? ''));
        $userId = $fkUser !== ''
            ? ($userIdByFbCodigo[$fkUser] ?? $userIdByFbCodigo[(string) (int) $fkUser] ?? $fallbackUserId)
            : $fallbackUserId;

        $fkCliente = trim((string) ($row['ID_CLIENTE'] ?? $row['id_cliente'] ?? ''));
        $personId = $fkCliente !== ''
            ? ($personIdByCodigo[$fkCliente] ?? $personIdByCodigo[(string) (int) $fkCliente] ?? null)
            : null;

        $fkVendedor = trim((string) ($row['FK_VENDEDOR'] ?? $row['fk_vendedor'] ?? ''));
        $vendedorId = $fkVendedor !== ''
            ? ($vendedorIdByCodigo[$fkVendedor] ?? $vendedorIdByCodigo[(string) (int) $fkVendedor] ?? null)
            : null;

        $vendedorNome = null;
        if ($vendedorId) {
            $vendedorNome = Vendedor::query()->whereKey($vendedorId)->value('nome');
        }

        $emissao = $this->mapDateTime(
            $row['DATA_EMISSAO'] ?? $row['data_emissao'] ?? null,
            $row['HORA'] ?? $row['hora'] ?? null,
        );

        $situacao = Str::upper(trim((string) ($row['SITUACAO'] ?? $row['situacao'] ?? 'F')));
        if ($situacao === '') {
            $situacao = 'F';
        }

        $flagNfce = Str::upper(trim((string) ($row['FLAG_NFCE'] ?? $row['flag_nfce'] ?? '')));

        $formaMaster = trim((string) ($row['FORMA_PAGAMENTO'] ?? $row['forma_pagamento'] ?? ''));
        $forma = $formaMaster !== ''
            ? Str::upper($formaMaster)
            : 'DINHEIRO';

        return [
            'numero' => $codigo,
            'pdv_caixa_sessao_id' => (int) $sessaoId,
            'user_id' => (int) $userId,
            'person_id' => $personId,
            'cpf_nota' => trim((string) ($row['CPF_NOTA'] ?? $row['cpf_nota'] ?? '')) ?: null,
            'vendedor_id' => $vendedorId,
            'vendedor_nome' => $vendedorNome,
            'subtotal' => BrDecimalImport::parse($row['SUBTOTAL'] ?? 0),
            'desconto' => BrDecimalImport::parse($row['DESCONTO'] ?? 0),
            'acrescimo' => BrDecimalImport::parse($row['ACRESCIMO'] ?? 0),
            'total' => BrDecimalImport::parse($row['TOTAL'] ?? 0),
            'troco' => BrDecimalImport::parse($row['TROCO'] ?? 0),
            'dinheiro' => BrDecimalImport::parse($row['DINHEIRO'] ?? 0),
            'forma_pagamento' => $forma,
            'fiscal' => $flagNfce === 'S',
            'observacoes' => trim((string) ($row['OBSERVACOES'] ?? $row['observacoes'] ?? '')) ?: null,
            'situacao' => $situacao,
            'fechado_em' => $emissao,
            'origem' => 'firebird',
            '_lote' => $lote,
            '_forma_fallback' => $forma,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $masterRows
     * @param  array<int, array<string, mixed>>  $itemRows
     * @param  array<int, array<string, mixed>>  $pagamentoRows
     * @param  array<string, int>  $sessaoIdByLote
     * @param  array<string, int>  $userIdByFbCodigo
     * @param  array<string, int>  $personIdByCodigo
     * @param  array<string, int>  $vendedorIdByCodigo
     * @param  array<string, array{id: int, descricao: string, unidade: string}>  $productByCodigo
     * @param  array<string, string>  $formaByCodigo
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importRows(
        array $masterRows,
        array $itemRows,
        array $pagamentoRows,
        array $sessaoIdByLote,
        array $userIdByFbCodigo,
        array $personIdByCodigo,
        array $vendedorIdByCodigo,
        array $productByCodigo,
        array $formaByCodigo,
        int $fallbackUserId,
        bool $updateExisting = true,
        bool $dryRun = false,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $itensByVenda = [];
        foreach ($itemRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fk = (int) ($row['FKVENDA'] ?? $row['fkvenda'] ?? 0);
            if ($fk < 1) {
                continue;
            }
            $itensByVenda[$fk][] = $row;
        }

        $pagByVenda = [];
        foreach ($pagamentoRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $fk = (int) ($row['VENDAS_MASTER'] ?? $row['vendas_master'] ?? 0);
            if ($fk < 1) {
                continue;
            }
            $pagByVenda[$fk][] = $row;
        }

        DB::transaction(function () use (
            $masterRows,
            $itensByVenda,
            $pagByVenda,
            $sessaoIdByLote,
            $userIdByFbCodigo,
            $personIdByCodigo,
            $vendedorIdByCodigo,
            $productByCodigo,
            $formaByCodigo,
            $fallbackUserId,
            $updateExisting,
            $dryRun,
            &$stats,
        ): void {
            foreach ($masterRows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $payload = $this->mapMasterRow(
                    $row,
                    $sessaoIdByLote,
                    $userIdByFbCodigo,
                    $personIdByCodigo,
                    $vendedorIdByCodigo,
                    $formaByCodigo,
                    $fallbackUserId,
                );

                if ($payload === null) {
                    $stats['skipped']++;

                    continue;
                }

                $numero = (int) $payload['numero'];
                $pagamentos = $pagByVenda[$numero] ?? [];
                $primeiraForma = $this->resolvePrimeiraForma($pagamentos, $formaByCodigo);
                if ($primeiraForma !== null) {
                    $payload['forma_pagamento'] = $primeiraForma;
                }
                unset($payload['_lote'], $payload['_forma_fallback']);

                $existing = PdvVenda::query()->where('numero', $numero)->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $venda = $existing;
                    $venda->itens()->delete();
                    $venda->pagamentos()->delete();
                    $stats['updated']++;
                } else {
                    $venda = PdvVenda::query()->create($payload);
                    $stats['created']++;
                }

                foreach ($itensByVenda[$numero] ?? [] as $itemRow) {
                    $itemPayload = $this->mapItemRow($itemRow, (int) $venda->id, $productByCodigo);
                    if ($itemPayload === null) {
                        continue;
                    }
                    PdvVendaItem::query()->create($itemPayload);
                }

                foreach ($pagamentos as $pagRow) {
                    $pagPayload = $this->mapPagamentoRow($pagRow, (int) $venda->id, $formaByCodigo);
                    if ($pagPayload === null) {
                        continue;
                    }
                    PdvVendaPagamento::query()->create($pagPayload);
                }

                $this->syncRetaguardaEspelho($venda);
            }
        });

        return $stats;
    }

    /**
     * Garante espelho em `vendas` (tela Vendas → Cupom/Todos).
     */
    public function syncRetaguardaEspelho(PdvVenda $pdvVenda): Venda
    {
        $pdvVenda->loadMissing(['itens', 'pagamentos']);

        $retaguarda = (new PdvVendaRetaguardaMirrorService())->espelhar($pdvVenda);

        // Sem NFC-e (fiscal=false) = Pedido; com NFC-e = Cupom.
        $updates = [
            'tipo' => $pdvVenda->fiscal ? Venda::TIPO_CUPOM : Venda::TIPO_PEDIDO,
            'plataforma' => Venda::PLATAFORMA_PDV,
        ];

        $situacao = Str::upper(trim((string) ($pdvVenda->situacao ?? '')));
        if (in_array($situacao, ['C', 'X'], true)) {
            $updates['status'] = Venda::STATUS_CANCELADO;
        }

        $retaguarda->update($updates);

        return $retaguarda->fresh() ?? $retaguarda;
    }

    /**
     * @param  array<string, array{id: int, descricao: string, unidade: string}>  $productByCodigo
     * @return array<string, mixed>|null
     */
    public function mapItemRow(array $row, int $pdvVendaId, array $productByCodigo): ?array
    {
        $fkProduto = trim((string) ($row['ID_PRODUTO'] ?? $row['id_produto'] ?? ''));
        $product = $fkProduto !== ''
            ? ($productByCodigo[$fkProduto] ?? $productByCodigo[(string) (int) $fkProduto] ?? null)
            : null;

        $codigoBarra = trim((string) ($row['COD_BARRA'] ?? $row['cod_barra'] ?? ''));
        $unidade = Str::upper(trim((string) ($row['UNIDADE'] ?? $row['unidade'] ?? '')));
        if ($unidade === '') {
            $unidade = $product['unidade'] ?? 'UN';
        }

        $descricao = $product['descricao'] ?? '';
        if ($descricao === '') {
            $descricao = $codigoBarra !== '' ? $codigoBarra : ('PRODUTO '.($fkProduto !== '' ? $fkProduto : '?'));
        }

        return [
            'pdv_venda_id' => $pdvVendaId,
            'product_id' => $product['id'] ?? null,
            'codigo' => $codigoBarra !== '' ? $codigoBarra : ($fkProduto !== '' ? $fkProduto : null),
            'descricao' => Str::upper($descricao),
            'unidade' => $unidade,
            'observacao' => trim((string) ($row['OBSERVACAO'] ?? $row['observacao'] ?? '')) ?: null,
            'quantidade' => BrDecimalImport::parse($row['QTD'] ?? 0, 3),
            'preco_unitario' => BrDecimalImport::parse($row['PRECO'] ?? 0),
            'desconto' => BrDecimalImport::parse($row['VDESCONTO'] ?? 0),
            'acrescimo' => BrDecimalImport::parse($row['ACRESCIMO'] ?? 0),
            'total' => BrDecimalImport::parse($row['TOTAL'] ?? 0),
        ];
    }

    /**
     * @param  array<string, string>  $formaByCodigo
     * @return array<string, mixed>|null
     */
    public function mapPagamentoRow(array $row, int $pdvVendaId, array $formaByCodigo): ?array
    {
        $fkForma = trim((string) ($row['ID_FORMA'] ?? $row['id_forma'] ?? ''));
        $forma = $fkForma !== ''
            ? ($formaByCodigo[$fkForma] ?? $formaByCodigo[(string) (int) $fkForma] ?? null)
            : null;

        if ($forma === null || $forma === '') {
            $forma = 'DINHEIRO';
        }

        $parcelas = filled($row['PARCELAS'] ?? null) ? (int) $row['PARCELAS'] : null;

        return [
            'pdv_venda_id' => $pdvVendaId,
            'forma' => Str::upper($forma),
            'valor' => BrDecimalImport::parse($row['VALOR'] ?? $row['TOTAL'] ?? 0),
            'cartao_nsu' => trim((string) ($row['NSU'] ?? '')) ?: null,
            'cartao_autorizacao' => trim((string) ($row['CODIGOAUTORIZACAO'] ?? '')) ?: null,
            'cartao_bandeira' => trim((string) ($row['BANDEIRA'] ?? '')) ?: null,
            'cartao_parcela' => $parcelas,
        ];
    }

    /**
     * Cria sessões PDV a partir dos lotes do Firebird.
     *
     * @param  array<int, array<string, mixed>>  $loteRows  LOTE, DE, ATE, USU
     * @param  array<string, int>  $userIdByFbCodigo
     * @return array<string, int> lote => sessao_id
     */
    public function ensureSessoes(
        array $loteRows,
        array $userIdByFbCodigo,
        int $fallbackUserId,
        bool $dryRun = false,
    ): array {
        $empresaId = Empresa::query()->orderBy('id')->value('id');
        $terminalId = Terminal::query()->orderBy('id')->value('id');
        $maxLote = 0;
        foreach ($loteRows as $row) {
            $maxLote = max($maxLote, (int) ($row['LOTE'] ?? 0));
        }

        $map = [];

        foreach ($loteRows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lote = trim((string) ($row['LOTE'] ?? ''));
            if ($lote === '') {
                continue;
            }

            $fkUser = trim((string) ($row['USU'] ?? $row['usu'] ?? ''));
            $userId = $fkUser !== ''
                ? ($userIdByFbCodigo[$fkUser] ?? $userIdByFbCodigo[(string) (int) $fkUser] ?? $fallbackUserId)
                : $fallbackUserId;

            $abertoEm = $this->mapDateTime($row['DE'] ?? null, null) ?? now()->toDateTimeString();
            $ate = $this->mapDateTime($row['ATE'] ?? null, '23:59:59');
            $fechadoEm = ((int) $lote < $maxLote) ? $ate : null;

            if ($dryRun) {
                $map[$lote] = -1 * (int) $lote;
                $map[(string) (int) $lote] = $map[$lote];

                continue;
            }

            $sessao = PdvCaixaSessao::query()->create([
                'user_id' => $userId,
                'empresa_id' => $empresaId,
                'terminal_id' => $terminalId,
                'valor_abertura' => 0,
                'valor_fechamento' => null,
                'aberto_em' => $abertoEm,
                'fechado_em' => $fechadoEm,
            ]);

            $map[$lote] = (int) $sessao->id;
            $map[(string) (int) $lote] = (int) $sessao->id;
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    public function buildUserIdByFbCodigo(array $usuarioRows): array
    {
        $map = [];
        $usersByName = User::query()
            ->get(['id', 'name'])
            ->mapWithKeys(fn (User $u) => [Str::upper((string) $u->name) => (int) $u->id])
            ->all();

        foreach ($usuarioRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $codigo = trim((string) ($row['CODIGO'] ?? $row['codigo'] ?? ''));
            $login = Str::upper(trim((string) ($row['LOGIN'] ?? $row['login'] ?? '')));
            if ($codigo === '' || $login === '') {
                continue;
            }
            $id = $usersByName[$login] ?? null;
            if ($id) {
                $map[$codigo] = $id;
                $map[(string) (int) $codigo] = $id;
            }
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    public function buildPersonIdByCodigo(): array
    {
        return Person::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    public function buildVendedorIdByCodigo(): array
    {
        return Vendedor::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->mapWithKeys(fn ($id, $codigo) => [(string) $codigo => (int) $id])
            ->all();
    }

    /**
     * @return array<string, array{id: int, descricao: string, unidade: string}>
     */
    public function buildProductByCodigo(): array
    {
        $map = [];
        foreach (Product::query()->get(['id', 'codigo', 'descricao', 'unidade']) as $p) {
            $codigo = (string) $p->codigo;
            $entry = [
                'id' => (int) $p->id,
                'descricao' => (string) $p->descricao,
                'unidade' => (string) ($p->unidade ?: 'UN'),
            ];
            $map[$codigo] = $entry;
            $map[(string) (int) $codigo] = $entry;
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    public function buildFormaByCodigo(): array
    {
        $map = [];
        foreach (FormaPagamento::query()->get(['codigo', 'descricao']) as $f) {
            $codigo = (string) $f->codigo;
            $nome = Str::upper(trim((string) $f->descricao));
            if ($codigo === '' || $nome === '') {
                continue;
            }
            $map[$codigo] = $nome;
            $map[(string) (int) $codigo] = $nome;
        }

        return $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pagamentos
     * @param  array<string, string>  $formaByCodigo
     */
    protected function resolvePrimeiraForma(array $pagamentos, array $formaByCodigo): ?string
    {
        foreach ($pagamentos as $row) {
            $fk = trim((string) ($row['ID_FORMA'] ?? $row['id_forma'] ?? ''));
            if ($fk === '') {
                continue;
            }
            $nome = $formaByCodigo[$fk] ?? $formaByCodigo[(string) (int) $fk] ?? null;
            if ($nome) {
                return Str::upper($nome);
            }
        }

        return null;
    }

    protected function mapDateTime(mixed $date, mixed $time): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $d = Carbon::parse($date)->format('Y-m-d');
            $t = '00:00:00';
            if ($time !== null && $time !== '') {
                $raw = trim((string) $time);
                if (preg_match('/^(\d{1,2}:\d{2}:\d{2})/', $raw, $m)) {
                    $t = $m[1];
                } else {
                    try {
                        $t = Carbon::parse($time)->format('H:i:s');
                    } catch (\Throwable) {
                        $t = '00:00:00';
                    }
                }
            }

            return $d.' '.$t;
        } catch (\Throwable) {
            return null;
        }
    }
}
