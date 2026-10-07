<?php

namespace App\Support\Erp\Nfce;

use App\Models\Nfe;
use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Models\Venda;
use App\Support\Erp\ErpSchema;
use App\Support\Erp\ErpTimezone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Vendas finalizadas (todas as origens convergem em `vendas`) sem NFC-e/NF-e autorizada.
 * Listagem leve: só colunas da grade; itens/XML ficam para o momento da emissão.
 */
final class NfceRegularizacaoQuery
{
    public const ORIGEM_PDV = 'pdv';

    public const ORIGEM_TELA_VENDA = 'tela_venda';

    public const ORIGEM_FORCA_VENDAS = 'forca_vendas';

    public const ORIGEM_VENDAS_INTERNAS = 'vendas_internas';

    public const ORIGEM_ERP = 'erp';

    public const LABEL_REQUER_NFE = 'Requer NF-e';

    private const TELA_VENDA_DEVICE = 'monitor-web';

    public function __construct(
        public ?int $empresaId = null,
        public ?string $dataDe = null,
        public ?string $dataAte = null,
        public string $numero = '',
        public string $cliente = '',
        public string $origem = '',
        public string $vendedor = '',
    ) {}

    /**
     * @return array<string, string>
     */
    public static function origemLabels(): array
    {
        return [
            self::ORIGEM_PDV => 'PDV',
            self::ORIGEM_TELA_VENDA => 'Tela de Venda',
            self::ORIGEM_FORCA_VENDAS => 'Força de Vendas',
            self::ORIGEM_VENDAS_INTERNAS => 'Vendas Internas',
            self::ORIGEM_ERP => 'Pedido / ERP',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statusVendaRegularizaveis(): array
    {
        return [Venda::STATUS_FECHADO, Venda::STATUS_GRAVADO];
    }

    /**
     * Regularização só no mês corrente (fuso local do ERP), independente dos filtros enviados.
     *
     * @return array{0: string, 1: string} [Y-m-d início, Y-m-d fim]
     */
    public static function periodoPermitido(): array
    {
        $hoje = ErpTimezone::nowLocal();

        return [$hoje->copy()->startOfMonth()->toDateString(), $hoje->copy()->endOfMonth()->toDateString()];
    }

    /**
     * Sem documento que impeça nova emissão: NF-e transmitida/contingência ou NFC-e real
     * autorizada/contingência. Cupom simulado (sem SEFAZ) não conta.
     */
    public static function semDocumentoValido(Builder $query): Builder
    {
        return $query
            ->whereNotExists(function (QueryBuilder $sub): void {
                $sub->selectRaw('1')
                    ->from('nfes')
                    ->whereColumn('nfes.venda_id', 'vendas.id')
                    ->whereIn('nfes.status', [Nfe::STATUS_TRANSMITIDA, Nfe::STATUS_CONTINGENCIA]);
            })
            ->whereNotExists(function (QueryBuilder $sub): void {
                $sub->selectRaw('1')
                    ->from('pdv_venda_nfce as nfv')
                    ->join('pdv_vendas as pvv', 'pvv.id', '=', 'nfv.pdv_venda_id')
                    ->whereColumn('pvv.venda_id', 'vendas.id')
                    ->whereIn('nfv.status', [PdvVendaNfce::STATUS_AUTORIZADA, PdvVendaNfce::STATUS_CONTINGENCIA])
                    ->where(function (QueryBuilder $o): void {
                        $o->where('nfv.simulada', false)->orWhereNull('nfv.simulada');
                    });
            });
    }

    public static function documentoRequerNfe(?string $documento, ?string $codigoCliente): bool
    {
        if (Person::isCodigoConsumidorFinal($codigoCliente)) {
            return false;
        }

        return strlen(preg_replace('/\D/', '', (string) $documento) ?? '') === 14;
    }

    public function build(): Builder
    {
        [$inicioMes, $fimMes] = self::periodoPermitido();
        $dataDe = filled($this->dataDe) && $this->dataDe > $inicioMes ? (string) $this->dataDe : $inicioMes;
        $dataAte = filled($this->dataAte) && $this->dataAte < $fimMes ? (string) $this->dataAte : $fimMes;

        $query = Venda::query()
            ->leftJoin('people as rc', 'rc.id', '=', 'vendas.cliente_id')
            ->leftJoin('vendedores as rv', 'rv.id', '=', 'vendas.vendedor_id')
            ->select([
                'vendas.id',
                'vendas.numero',
                'vendas.data',
                'vendas.hora',
                'vendas.vendedor_nome',
                'vendas.forma_pagamento',
                'vendas.total',
                'vendas.plataforma',
                'rc.nome_razao as cliente_nome',
                'rc.cpf_cnpj as cliente_documento',
                'rc.codigo as cliente_codigo',
                'rv.nome as vendedor_cadastro',
            ])
            ->whereIn('vendas.status', self::statusVendaRegularizaveis())
            ->whereBetween('vendas.data', [$dataDe, $dataAte]);

        self::semDocumentoValido($query);

        if ($this->empresaId !== null && $this->empresaId > 0 && ErpSchema::hasColumn('vendas', 'empresa_id')) {
            $empresaId = $this->empresaId;
            $query->where(function (Builder $q) use ($empresaId): void {
                $q->where('vendas.empresa_id', $empresaId)->orWhereNull('vendas.empresa_id');
            });
        }

        $numero = trim($this->numero);
        if ($numero !== '') {
            $query->where(function (Builder $q) use ($numero): void {
                $q->where('vendas.numero', 'like', '%'.$numero.'%');

                if (ctype_digit($numero)) {
                    $q->orWhere('vendas.numero', ltrim($numero, '0') ?: '0');
                }
            });
        }

        $cliente = mb_strtoupper(trim($this->cliente), 'UTF-8');
        if ($cliente !== '') {
            $digits = preg_replace('/\D/', '', $cliente) ?? '';
            $query->where(function (Builder $q) use ($cliente, $digits): void {
                $q->where('rc.nome_razao', 'like', '%'.$cliente.'%');

                if (strlen($digits) >= 3) {
                    $col = $q->getQuery()->getGrammar()->wrap('rc.cpf_cnpj');
                    $q->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(COALESCE({$col}, ''), '.', ''), '-', ''), '/', '') LIKE ?",
                        ['%'.$digits.'%'],
                    );
                }
            });
        }

        $vendedor = mb_strtoupper(trim($this->vendedor), 'UTF-8');
        if ($vendedor !== '') {
            $query->where(function (Builder $q) use ($vendedor): void {
                $q->where('rv.nome', 'like', '%'.$vendedor.'%')
                    ->orWhere('vendas.vendedor_nome', 'like', '%'.$vendedor.'%');
            });
        }

        $this->applyOrigem($query);

        return $query->orderByDesc('vendas.data')->orderByDesc('vendas.id');
    }

    private function applyOrigem(Builder $query): void
    {
        $temFv = ErpSchema::hasTable('forca_vendas_orders');
        $temVi = ErpSchema::hasTable('vendas_internas_orders');

        $pdvReal = function (QueryBuilder $sub): void {
            $sub->selectRaw('1')
                ->from('pdv_vendas')
                ->whereColumn('pdv_vendas.venda_id', 'vendas.id')
                ->where(function (QueryBuilder $o): void {
                    $o->whereNull('pdv_vendas.origem')
                        ->orWhere('pdv_vendas.origem', '!=', PdvVenda::ORIGEM_REGULARIZACAO_FISCAL);
                });
        };
        $fvOrder = fn (?bool $telaVenda) => function (QueryBuilder $sub) use ($telaVenda): void {
            $sub->selectRaw('1')
                ->from('forca_vendas_orders')
                ->whereColumn('forca_vendas_orders.venda_id', 'vendas.id');

            if ($telaVenda === true) {
                $sub->where('forca_vendas_orders.device_uuid', self::TELA_VENDA_DEVICE);
            } elseif ($telaVenda === false) {
                $sub->where(function (QueryBuilder $o): void {
                    $o->whereNull('forca_vendas_orders.device_uuid')
                        ->orWhere('forca_vendas_orders.device_uuid', '!=', self::TELA_VENDA_DEVICE);
                });
            }
        };
        $viOrder = function (QueryBuilder $sub): void {
            $sub->selectRaw('1')
                ->from('vendas_internas_orders')
                ->whereColumn('vendas_internas_orders.venda_id', 'vendas.id');
        };

        switch ($this->origem) {
            case self::ORIGEM_PDV:
                $query->whereExists($pdvReal);
                break;
            case self::ORIGEM_VENDAS_INTERNAS:
                $temVi ? $query->whereExists($viOrder) : $query->whereRaw('1 = 0');
                break;
            case self::ORIGEM_TELA_VENDA:
                $temFv ? $query->whereExists($fvOrder(true))->whereNotExists($pdvReal) : $query->whereRaw('1 = 0');
                break;
            case self::ORIGEM_FORCA_VENDAS:
                $query->whereNotExists($pdvReal);
                if ($temVi) {
                    $query->whereNotExists($viOrder);
                }
                $query->where(function (Builder $q) use ($temFv, $fvOrder): void {
                    if ($temFv) {
                        $q->whereExists($fvOrder(false));
                    }
                    $q->orWhere('vendas.plataforma', Venda::PLATAFORMA_MOBILE);
                });
                if ($temFv) {
                    $query->whereNotExists($fvOrder(true));
                }
                break;
            case self::ORIGEM_ERP:
                $query->whereNotExists($pdvReal)
                    ->where(function (Builder $q): void {
                        $q->whereNull('vendas.plataforma')->orWhere('vendas.plataforma', '!=', Venda::PLATAFORMA_MOBILE);
                    });
                if ($temFv) {
                    $query->whereNotExists($fvOrder(null));
                }
                if ($temVi) {
                    $query->whereNotExists($viOrder);
                }
                break;
        }
    }

    /**
     * Complementa a página atual com origem/caixa/situação (4 consultas pequenas por página, sem N+1).
     *
     * @param  Collection<int, Venda>  $records
     * @return list<array<string, mixed>>
     */
    public static function mapRows(Collection $records): array
    {
        $ids = $records->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if ($ids === []) {
            return [];
        }

        $pdv = DB::table('pdv_vendas as pv')
            ->leftJoin('pdv_caixa_sessoes as s', 's.id', '=', 'pv.pdv_caixa_sessao_id')
            ->leftJoin('terminais as t', 't.id', '=', 's.terminal_id')
            ->leftJoin('pdv_venda_nfce as nf', 'nf.pdv_venda_id', '=', 'pv.id')
            ->whereIn('pv.venda_id', $ids)
            ->get([
                'pv.venda_id',
                'pv.origem',
                'pv.numero as pdv_numero',
                'pv.cpf_nota',
                'nf.simulada as nfce_simulada',
                't.nome as terminal',
                'nf.status as nfce_status',
                'nf.numero as nfce_numero',
                DB::raw('SUBSTRING('.DB::getQueryGrammar()->wrap('nf.motivo_rejeicao').', 1, 200) as nfce_motivo'),
            ])
            ->keyBy('venda_id');

        $fv = ErpSchema::hasTable('forca_vendas_orders')
            ? DB::table('forca_vendas_orders')
                ->whereIn('venda_id', $ids)
                ->get(['venda_id', 'device_uuid', 'meli_order_id'])
                ->keyBy('venda_id')
            : collect();

        $vi = ErpSchema::hasTable('vendas_internas_orders')
            ? DB::table('vendas_internas_orders')->whereIn('venda_id', $ids)->pluck('venda_id')->flip()
            : collect();

        $nfeAberta = DB::table('nfes')
            ->whereIn('venda_id', $ids)
            ->where('status', Nfe::STATUS_ABERTA)
            ->pluck('venda_id')
            ->flip();

        $labels = self::origemLabels();
        $hoje = Carbon::parse(ErpTimezone::today());
        $rows = [];

        foreach ($records as $record) {
            $id = (int) $record->id;
            $pdvRow = $pdv->get($id);
            $pdvReal = $pdvRow !== null && (string) ($pdvRow->origem ?? '') !== PdvVenda::ORIGEM_REGULARIZACAO_FISCAL;
            $fvRow = $fv->get($id);

            $origem = match (true) {
                $pdvReal => self::ORIGEM_PDV,
                $vi->has($id) => self::ORIGEM_VENDAS_INTERNAS,
                $fvRow !== null && (string) ($fvRow->device_uuid ?? '') === self::TELA_VENDA_DEVICE => self::ORIGEM_TELA_VENDA,
                $fvRow !== null, (string) $record->plataforma === Venda::PLATAFORMA_MOBILE => self::ORIGEM_FORCA_VENDAS,
                default => self::ORIGEM_ERP,
            };

            $origemLabel = $labels[$origem];
            if ($fvRow !== null && filled($fvRow->meli_order_id ?? null)) {
                $origemLabel = 'Mercado Livre';
            }

            $codigoCliente = isset($record->cliente_codigo) ? (string) $record->cliente_codigo : null;
            $consumidorFinal = Person::isCodigoConsumidorFinal($codigoCliente);
            $requerNfe = self::documentoRequerNfe($record->cliente_documento ?? null, $codigoCliente)
                || ($pdvReal && strlen(preg_replace('/\D/', '', (string) ($pdvRow->cpf_nota ?? '')) ?? '') === 14);

            [$situacao, $tone, $selecionavel] = self::situacao($pdvRow, $pdvReal, $nfeAberta->has($id), $requerNfe);

            $data = $record->data ? Carbon::parse($record->data) : null;

            $vendedor = trim((string) ($record->vendedor_cadastro ?? ''));
            if ($vendedor === '') {
                $vendedor = trim((string) ($record->vendedor_nome ?? ''));
            }

            $rows[] = [
                'id' => $id,
                'numero' => (string) $record->numero,
                'data' => $data?->format('d/m/Y') ?? '—',
                'origem' => $origem,
                'origem_label' => $origemLabel,
                'cliente' => $consumidorFinal || blank($record->cliente_nome)
                    ? 'CONSUMIDOR FINAL'
                    : mb_strtoupper((string) $record->cliente_nome, 'UTF-8'),
                'documento' => $consumidorFinal ? '—' : self::formatDocumento($record->cliente_documento ?? null),
                'caixa' => $pdvReal && filled($pdvRow->terminal ?? null) ? (string) $pdvRow->terminal : '—',
                'vendedor' => $vendedor !== '' ? mb_strtoupper($vendedor, 'UTF-8') : '—',
                'forma' => filled($record->forma_pagamento) ? (string) $record->forma_pagamento : '—',
                'total' => round((float) $record->total, 2),
                'situacao' => $situacao,
                'situacao_tone' => $tone,
                'selecionavel' => $selecionavel,
                'dias' => $data ? max(0, (int) $data->diffInDays($hoje, false)) : 0,
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: string, 2: bool} [rótulo, tom, selecionável]
     */
    private static function situacao(?object $pdvRow, bool $pdvReal, bool $nfeAberta, bool $requerNfe): array
    {
        if ($requerNfe) {
            return [self::LABEL_REQUER_NFE.' (cliente CNPJ)', 'danger', false];
        }

        if ($nfeAberta) {
            return ['NF-e em aberto vinculada', 'warning', false];
        }

        $status = (string) ($pdvRow->nfce_status ?? '');

        if ($status !== '' && ((bool) ($pdvRow->nfce_simulada ?? false) || $status === PdvVendaNfce::STATUS_SIMULADA)) {
            return ['Cupom simulado (sem SEFAZ)', 'pending', true];
        }

        return match ($status) {
            PdvVendaNfce::STATUS_REJEITADA => [
                'NFC-e rejeitada'.(filled($pdvRow->nfce_motivo ?? null) ? ': '.trim((string) $pdvRow->nfce_motivo) : ''),
                'danger',
                true,
            ],
            PdvVendaNfce::STATUS_PENDENTE => ['NFC-e gravada — transmitir em Gravados', 'warning', false],
            PdvVendaNfce::STATUS_CANCELADA => $pdvReal
                ? ['NFC-e do PDV cancelada — usar NF-e', 'gray', false]
                : ['NFC-e anterior cancelada', 'gray', true],
            default => ['Pendente de emissão', 'pending', true],
        };
    }

    private static function formatDocumento(?string $documento): string
    {
        $digits = preg_replace('/\D/', '', (string) $documento) ?? '';

        return match (strlen($digits)) {
            11 => substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2),
            14 => substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12, 2),
            default => $digits !== '' ? $digits : '—',
        };
    }
}
