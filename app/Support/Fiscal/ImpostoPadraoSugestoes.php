<?php

namespace App\Support\Fiscal;

/**
 * Sugestões editáveis de Imposto Padrão por regime.
 * Nunca grava sozinha — só preenche formulário quando o usuário cria empresa
 * ou confirma revisão ao trocar regime.
 *
 * CRT 3 (Presumido/Real/Normal): sem CST, PIS/COFINS 0,65%/3%, FCP ou MVA universais.
 */
final class ImpostoPadraoSugestoes
{
    /**
     * Campos param_imp_* que a sugestão pode propor (espelho do Imposto Padrão).
     *
     * @return list<string>
     */
    public static function campos(): array
    {
        return [
            'param_imp_cfop_venda',
            'param_imp_origem',
            'param_imp_icms_cst',
            'param_imp_csosn',
            'param_imp_icms_aliquota',
            'param_imp_cfop_externo',
            'param_imp_icms_cst_externo',
            'param_imp_csosn_externo',
            'param_imp_icms_aliquota_externo',
            'param_imp_pis_cst',
            'param_imp_cofins_cst',
            'param_imp_cst_cofins',
            'param_imp_pis_aliquota',
            'param_imp_cofins_aliquota',
            'param_imp_ipi_cst',
            'param_imp_ipi_aliquota',
            'param_imp_fcp_pct',
            'param_imp_mva_pct',
            'param_imp_mva_normal',
            'param_imp_reducao_base_pct',
        ];
    }

    public static function rotulo(?string $regime): string
    {
        return match (RegimeCrtMapper::normalize($regime)) {
            'simples' => 'Simples Nacional (CRT 1)',
            'mei' => 'MEI / SIMEI (CRT 4)',
            'excesso_sublimite' => 'Simples — excesso de sublimite (CRT 2)',
            'presumido' => 'Lucro Presumido (CRT 3)',
            'real' => 'Lucro Real (CRT 3)',
            'normal' => 'Regime Normal (CRT 3)',
            default => 'Regime tributário',
        };
    }

    /**
     * Texto de orientação exibido na revisão / Imposto Padrão.
     */
    public static function orientacao(?string $regime): string
    {
        $crt = RegimeCrtMapper::toCrt($regime, false);

        if ($crt === 3) {
            return 'CRT 3: preencha CST ICMS, CST e alíquotas de PIS/COFINS conforme o contador. '
                .'Não há padrão universal (0,65%/3%, FCP ou MVA). '
                .'A emissão exige tributação válida no produto/item.';
        }

        if ($crt === 2) {
            return 'CRT 2: ICMS com CST; PIS/COFINS no tratamento do Simples (sem alíquotas de Presumido). '
                .'Revise CST e CSOSN conforme a operação.';
        }

        if ($crt === 4) {
            return 'MEI (CRT 4): sugestão inicial com CSOSN 102 e PIS/COFINS 99. '
                .'Na emissão, CFOP/CSOSN podem ser normalizados às regras do MEI.';
        }

        return 'Simples Nacional: sugestão inicial com CSOSN 102 e PIS/COFINS CST 99 (alíquotas 0). '
            .'Ajuste apenas o que for diferente na operação.';
    }

    /**
     * @return array<string, string>
     */
    public static function paraRegime(?string $regime): array
    {
        $slug = RegimeCrtMapper::normalize($regime);
        $base = [
            'param_imp_cfop_venda' => '5102',
            'param_imp_cfop_externo' => '6102',
            'param_imp_origem' => '0',
            'param_imp_icms_aliquota' => '0.00',
            'param_imp_icms_aliquota_externo' => '0.00',
            'param_imp_pis_aliquota' => '0.00',
            'param_imp_cofins_aliquota' => '0.00',
            'param_imp_ipi_cst' => '99',
            'param_imp_ipi_aliquota' => '0.00',
            'param_imp_fcp_pct' => '0.00',
            'param_imp_mva_pct' => '0.00',
            'param_imp_mva_normal' => '0.00',
            'param_imp_reducao_base_pct' => '0.00',
        ];

        return match ($slug) {
            'mei' => array_merge($base, [
                'param_imp_icms_cst' => '000',
                'param_imp_icms_cst_externo' => '000',
                'param_imp_csosn' => '102',
                'param_imp_csosn_externo' => '102',
                'param_imp_pis_cst' => '99',
                'param_imp_cofins_cst' => '99',
                'param_imp_cst_cofins' => '99',
            ]),
            'excesso_sublimite' => array_merge($base, [
                // CRT 2: CST no ICMS; PIS/COFINS estilo Simples (sem 0,65/3).
                'param_imp_icms_cst' => '00',
                'param_imp_icms_cst_externo' => '00',
                'param_imp_csosn' => '',
                'param_imp_csosn_externo' => '',
                'param_imp_pis_cst' => '99',
                'param_imp_cofins_cst' => '99',
                'param_imp_cst_cofins' => '99',
            ]),
            'presumido', 'real', 'normal' => array_merge($base, [
                // CRT 3: estrutura sem presumir CST nem alíquotas PIS/COFINS.
                'param_imp_icms_cst' => '',
                'param_imp_icms_cst_externo' => '',
                'param_imp_csosn' => '',
                'param_imp_csosn_externo' => '',
                'param_imp_pis_cst' => '',
                'param_imp_cofins_cst' => '',
                'param_imp_cst_cofins' => '',
            ]),
            default => array_merge($base, [
                // Simples Nacional — mesmos defaults históricos do Imposto Padrão.
                'param_imp_icms_cst' => '000',
                'param_imp_icms_cst_externo' => '000',
                'param_imp_csosn' => '102',
                'param_imp_csosn_externo' => '102',
                'param_imp_pis_cst' => '99',
                'param_imp_cofins_cst' => '99',
                'param_imp_cst_cofins' => '99',
            ]),
        };
    }

    /**
     * Diff texto para o modal de revisão (campo → atual → sugerido).
     *
     * @param  array<string, mixed>  $atuais
     * @return list<array{campo: string, atual: string, sugerido: string}>
     */
    public static function diffComAtuais(array $atuais, ?string $regime): array
    {
        $sugestao = self::paraRegime($regime);
        $diff = [];

        foreach (self::campos() as $campo) {
            $atual = self::formatValor($atuais[$campo] ?? '');
            $novo = self::formatValor($sugestao[$campo] ?? '');
            if ($atual === $novo) {
                continue;
            }
            $diff[] = [
                'campo' => $campo,
                'atual' => $atual === '' ? '(vazio)' : $atual,
                'sugerido' => $novo === '' ? '(vazio)' : $novo,
            ];
        }

        return $diff;
    }

    private static function formatValor(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
