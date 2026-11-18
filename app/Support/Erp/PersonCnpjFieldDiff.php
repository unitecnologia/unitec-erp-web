<?php

namespace App\Support\Erp;

use App\Models\Person;
use Illuminate\Support\Str;

/**
 * Compara o cadastro atual com o retorno da consulta de CNPJ.
 * Não propõe limpar campos quando a API omite valores.
 */
final class PersonCnpjFieldDiff
{
    /** @var array<string, string> */
    public const LABELS = [
        'nome_razao' => 'Razão Social',
        'apelido_fantasia' => 'Nome Fantasia',
        'rg_ie' => 'RG/IE',
        'tipo_contribuinte' => 'Tipo de Contribuinte',
        'cep' => 'CEP',
        'endereco' => 'Endereço',
        'numero' => 'Número',
        'complemento' => 'Complemento',
        'bairro' => 'Bairro',
        'cidade_codigo' => 'Cód. Cidade',
        'cidade_nome' => 'Cidade',
        'uf' => 'UF',
        'email' => 'Email',
        'fone1' => 'Telefone 1',
        'fone2' => 'Telefone 2',
        'regime_tributario' => 'Regime tributário',
    ];

    /**
     * Campos que não indicam “cadastro já preenchido” (defaults do create).
     *
     * @var list<string>
     */
    private const IGNORE_FOR_PREFILLED = [
        'tipo_contribuinte',
        'regime_tributario',
    ];

    /**
     * Enriquece a consulta com tipo_contribuinte quando houver IE suficiente.
     * Não presume NÃO CONTRIBUINTE só porque a API omitiu a IE.
     * Não altera regime tributário.
     *
     * @param  array<string, string|null>  $consulta
     * @param  array<string, mixed>  $current
     * @return array<string, string|null>
     */
    public static function enrichConsultaWithTipoContribuinte(array $consulta, array $current = []): array
    {
        if (filled($consulta['rg_ie'] ?? null)) {
            $tipo = self::tipoFromIe((string) $consulta['rg_ie']);

            if ($tipo !== null) {
                $consulta['tipo_contribuinte'] = $tipo;
            }

            return $consulta;
        }

        // API sem IE: não apaga/presume classificação.
        // Se o cadastro já tem IE válida e está incoerente como NÃO CONTRIBUINTE,
        // inclui a correção na comparação (informação disponível no formulário).
        $tipoEsperado = self::tipoFromIe((string) ($current['rg_ie'] ?? ''));
        $tipoAtual = self::normalize('tipo_contribuinte', $current['tipo_contribuinte'] ?? null);

        if (
            $tipoEsperado !== null
            && $tipoEsperado !== 'nao_contribuinte'
            && $tipoAtual === 'nao_contribuinte'
        ) {
            $consulta['tipo_contribuinte'] = $tipoEsperado;
        }

        return $consulta;
    }

    /**
     * Deriva tipo a partir da IE. Null = informação insuficiente (não alterar).
     */
    public static function tipoFromIe(?string $ie): ?string
    {
        $ie = mb_strtoupper(trim((string) $ie), 'UTF-8');
        $ie = preg_replace('/\s+/', ' ', $ie) ?? $ie;

        if ($ie === '' || $ie === '-') {
            return null;
        }

        if (in_array($ie, ['ISENTO', 'ISENTA'], true)) {
            return 'isento';
        }

        if (in_array($ie, [
            'NAO CONTRIBUINTE',
            'NÃO CONTRIBUINTE',
            'NAO_CONTRIBUINTE',
            'NÃO_CONTRIBUINTE',
            'NAO CONTRIB',
            'NÃO CONTRIB',
        ], true)) {
            return 'nao_contribuinte';
        }

        return 'contribuinte';
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, string|null>  $consulta
     * @return list<array{campo: string, label: string, atual: string, novo: string, selected: bool}>
     */
    public static function differences(array $current, array $consulta): array
    {
        $rows = [];

        foreach (self::LABELS as $campo => $label) {
            $novo = $consulta[$campo] ?? null;

            if (! filled($novo)) {
                continue;
            }

            $atual = $current[$campo] ?? null;
            $atualNorm = self::normalize($campo, $atual);
            $novoNorm = self::normalize($campo, $novo);

            if ($atualNorm === $novoNorm) {
                continue;
            }

            $rows[] = [
                'campo' => $campo,
                'label' => $label,
                'atual' => filled($atual) ? trim((string) $atual) : '—',
                'novo' => trim((string) $novo),
                'selected' => true,
            ];
        }

        return $rows;
    }

    public static function normalize(string $campo, mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '' || $raw === '—') {
            return '';
        }

        return match ($campo) {
            'cep', 'cidade_codigo' => preg_replace('/\D/', '', $raw) ?? '',
            'fone1', 'fone2' => preg_replace('/\D/', '', $raw) ?? '',
            'email' => Str::lower($raw),
            'uf', 'regime_tributario', 'tipo_contribuinte' => Str::lower($raw),
            default => Str::upper($raw),
        };
    }

    public static function formatDisplay(string $campo, mixed $value): string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '' || $raw === '—') {
            return '—';
        }

        if ($campo === 'tipo_contribuinte') {
            $key = Str::lower($raw);

            return Person::tiposContribuinte()[$key] ?? Str::upper($raw);
        }

        if ($campo === 'regime_tributario') {
            $key = Str::lower($raw);

            return Person::regimesTributarios()[$key] ?? Str::upper($raw);
        }

        return $raw;
    }

    /**
     * Cadastro novo (só CNPJ) vs. já preenchido / existente.
     *
     * @param  array<string, mixed>  $current
     */
    public static function hasComparableData(array $current): bool
    {
        foreach (array_keys(self::LABELS) as $campo) {
            if (in_array($campo, self::IGNORE_FOR_PREFILLED, true)) {
                continue;
            }

            if (filled($current[$campo] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
