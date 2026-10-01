<?php

namespace App\Support\Erp\Financeiro;

use App\Models\Boleto;
use App\Models\BoletoContaApi;
use App\Models\ContaReceber;
use App\Models\Empresa;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use Carbon\Carbon;

/**
 * Juros de atraso para Contas a Receber tipo Carteira.
 * Boleto usa juros próprios da conta API — não passa por aqui.
 */
final class ContaReceberJurosCarteira
{
    /**
     * @return array{juros_diario_pct: float, carencia_juros_dias: int}
     */
    public static function defaultsDaEmpresa(?int $empresaId = null): array
    {
        $id = $empresaId ?: ErpContext::currentEmpresaId();
        $empresa = $id ? Empresa::query()->find($id) : null;

        return [
            'juros_diario_pct' => round((float) ($empresa?->param_juros_diario_pct ?? 0), 4),
            'carencia_juros_dias' => max(0, (int) round((float) ($empresa?->param_carencia_juros ?? 0))),
            'multa_pct' => round((float) ($empresa?->param_multa_atraso_pct ?? 0), 4),
        ];
    }

    /**
     * Atributos para create de ContaReceber (só Carteira herda padrão da Empresa).
     *
     * @return array{juros_diario_pct: float, carencia_juros_dias: int}
     */
    public static function atributosParaCreate(?string $forma, ?int $empresaId = null): array
    {
        $key = mb_strtolower(trim((string) $forma), 'UTF-8');

        if ($key !== ContaReceber::FORMA_CARTEIRA) {
            return [
                'juros_diario_pct' => 0.0,
                'carencia_juros_dias' => 0,
                'multa_pct' => 0.0,
            ];
        }

        return self::defaultsDaEmpresa($empresaId);
    }

    /**
     * Percentuais usados na baixa.
     * Boleto já emitido no banco usa a conta desse boleto (juros ao mês).
     * Boleto ainda não emitido, e as outras formas, usam o parâmetro da empresa.
     *
     * @return array{juros_diario_pct: float, juros_mensal_pct: float, carencia_juros_dias: int, multa_pct: float}
     */
    public static function percentuaisEfetivos(ContaReceber $conta): array
    {
        if ($conta->isFormaBoleto()) {
            $doBanco = self::percentuaisBoletoEmitido($conta);
            if ($doBanco !== null) {
                return $doBanco;
            }
        }

        $pct = round((float) ($conta->juros_diario_pct ?? 0), 4);
        $multa = round((float) ($conta->multa_pct ?? 0), 4);
        $carencia = max(0, (int) ($conta->carencia_juros_dias ?? 0));

        if ($pct > 0 || $multa > 0) {
            return [
                'juros_diario_pct' => $pct,
                'juros_mensal_pct' => 0.0,
                'carencia_juros_dias' => $carencia,
                'multa_pct' => $multa,
            ];
        }

        $defaults = self::defaultsDaEmpresa($conta->empresa_id ? (int) $conta->empresa_id : null);

        return [
            'juros_diario_pct' => $defaults['juros_diario_pct'],
            'juros_mensal_pct' => 0.0,
            'carencia_juros_dias' => $defaults['carencia_juros_dias'],
            'multa_pct' => $defaults['multa_pct'],
        ];
    }

    /**
     * @return array{juros_diario_pct: float, juros_mensal_pct: float, carencia_juros_dias: int, multa_pct: float}|null
     */
    private static function percentuaisBoletoEmitido(ContaReceber $conta): ?array
    {
        $boleto = Boleto::query()
            ->where('conta_receber_id', $conta->id)
            ->where('status', '!=', Boleto::STATUS_CANCELADO)
            ->where(function ($query): void {
                $query->where(function ($query): void {
                    $query->whereNotNull('nosso_numero')->where('nosso_numero', '!=', '');
                })->orWhere(function ($query): void {
                    $query->whereNotNull('id_externo')->where('id_externo', '!=', '');
                })->orWhere(function ($query): void {
                    $query->whereNotNull('linha_digitavel')->where('linha_digitavel', '!=', '');
                });
            })
            ->latest('id')
            ->first();

        if (! $boleto instanceof Boleto) {
            return null;
        }

        $juros = 0.0;
        $multa = 0.0;

        if ((int) ($boleto->boleto_conta_api_id ?? 0) > 0) {
            $api = BoletoContaApi::query()->find($boleto->boleto_conta_api_id);
            if ($api instanceof BoletoContaApi) {
                $juros = self::percentual($api->juros_pct);
                $multa = self::percentual($api->multa_pct);
            }
        }

        if ($juros <= 0 && $multa <= 0) {
            $empresa = $conta->empresa_id ? Empresa::query()->find((int) $conta->empresa_id) : null;
            $juros = self::percentual($empresa?->param_boleto_juros_pct);
            $multa = self::percentual($empresa?->param_boleto_multa_pct);
        }

        return [
            'juros_diario_pct' => 0.0,
            'juros_mensal_pct' => $juros,
            'carencia_juros_dias' => 0,
            'multa_pct' => $multa,
        ];
    }

    private static function percentual(mixed $value): float
    {
        $raw = trim(str_replace('%', '', (string) ($value ?? '')));
        if ($raw === '') {
            return 0.0;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        return round(max(0, (float) $raw), 4);
    }

    /**
     * Valor de juros em R$ pelo atraso (após carência), sobre o valor do título.
     */
    public static function calcularValor(ContaReceber $conta, ?Carbon $naData = null): float
    {
        $params = self::percentuaisEfetivos($conta);
        $pct = $params['juros_diario_pct'];
        $mensal = $params['juros_mensal_pct'];
        if ($pct <= 0 && $mensal <= 0) {
            return 0.0;
        }

        $vencimento = $conta->vencimento
            ? Carbon::parse($conta->vencimento)->startOfDay()
            : null;

        if ($vencimento === null) {
            return 0.0;
        }

        $hoje = ($naData ?? ErpTimezone::toLocal())->copy()->startOfDay();
        if ($hoje->lte($vencimento)) {
            return 0.0;
        }

        $carencia = $params['carencia_juros_dias'];
        $diasAtraso = max(0, (int) $vencimento->diffInDays($hoje) - $carencia);

        if ($diasAtraso <= 0) {
            return 0.0;
        }

        $base = max(0.0, round((float) $conta->valor - (float) $conta->desconto, 2));

        if ($mensal > 0) {
            return round($base * ($mensal / 100) / 30 * $diasAtraso, 2);
        }

        return round($base * ($pct / 100) * $diasAtraso, 2);
    }

    /**
     * Multa única sobre o saldo em aberto. Só depois do vencimento e da carência.
     * Se o título já tem multa cobrada, não cobra de novo.
     */
    public static function calcularMulta(ContaReceber $conta, ?Carbon $naData = null): float
    {
        if (round((float) ($conta->multa ?? 0), 2) > 0.009) {
            return 0.0;
        }

        $pct = self::percentuaisEfetivos($conta)['multa_pct'];
        if ($pct <= 0) {
            return 0.0;
        }

        $vencimento = $conta->vencimento
            ? Carbon::parse($conta->vencimento)->startOfDay()
            : null;

        if ($vencimento === null) {
            return 0.0;
        }

        $hoje = ($naData ?? ErpTimezone::toLocal())->copy()->startOfDay();
        if ($hoje->lte($vencimento)) {
            return 0.0;
        }

        $carencia = self::percentuaisEfetivos($conta)['carencia_juros_dias'];
        $diasAtraso = max(0, (int) $vencimento->diffInDays($hoje) - $carencia);

        if ($diasAtraso <= 0) {
            return 0.0;
        }

        $base = max(0.0, round((float) $conta->saldo, 2));

        return round($base * ($pct / 100), 2);
    }

    /**
     * Atualiza o campo monetário `juros` da conta com o cálculo atual (carteira).
     */
    public static function aplicarNaConta(ContaReceber $conta, ?Carbon $naData = null): float
    {
        $valor = self::calcularValor($conta, $naData);
        $conta->juros = $valor;

        return $valor;
    }
}
