<?php

namespace App\Support\Fiscal;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Compatibilidade CRT × Imposto Padrão / item (sem alterar cadastros).
 * Avisos na troca de regime; bloqueio só na emissão fiscalmente inválida.
 */
final class ImpostoPadraoCompatibilidade
{
    /**
     * @param  array<string, mixed>  $params  param_imp_* ou atributos da empresa
     * @return array{ok: bool, erros: list<string>, avisos: list<string>}
     */
    public static function analisarParametrosEmpresa(array $params, ?string $regime): array
    {
        $crt = RegimeCrtMapper::toCrt($regime, false);
        $erros = [];
        $avisos = [];

        $cst2 = self::cstIcms2($params['param_imp_icms_cst'] ?? null);
        $csosn = self::digits((string) ($params['param_imp_csosn'] ?? ''), 3);
        $cstPis = PisCofinsCst::normalizar((string) ($params['param_imp_cofins_cst'] ?? $params['param_imp_pis_cst'] ?? ''));
        $cstCof = PisCofinsCst::normalizar((string) ($params['param_imp_cst_cofins'] ?? ''));

        // Imposto Padrão: só orientação. Bloqueio real fica na emissão (produto/item).
        // CSOSN residual junto ao CST NÃO é erro — CRT 2/3 emite pelo CST.
        if (RegimeCrtMapper::usaIcmsCsosn($crt)) {
            if ($csosn === '') {
                $avisos[] = 'CRT '.$crt.' (Simples/MEI): CSOSN vazio no Imposto Padrão — a emissão pode usar fallback 102 no item; revise produtos.';
            }
        } else {
            if ($cst2 === '') {
                $avisos[] = 'CRT '.$crt.': CST ICMS vazio no Imposto Padrão. A emissão exige CST no produto/item (CSOSN residual não substitui).';
            }
            if ($csosn !== '' && in_array($csosn, ['102', '103', '300', '400', '500'], true)) {
                $avisos[] = 'CSOSN '.$csosn.' permanece gravado, mas CRT '.$crt.' emite ICMS com CST — revise o Imposto Padrão.';
            }
        }

        if ($cstPis === '' || $cstCof === '') {
            $avisos[] = 'CST de PIS/COFINS vazio no Imposto Padrão — a emissão valida no produto/item.';
        } elseif (! in_array($cstPis, PisCofinsCst::permitidosEmissao(), true)
            || ! in_array($cstCof, PisCofinsCst::permitidosEmissao(), true)) {
            $erros[] = 'CST PIS/COFINS do Imposto Padrão não é suportado na emissão (evite CST 03).';
        }

        if ($crt === 3 && in_array($cstPis, ['99', '49'], true) && in_array($cstCof, ['99', '49'], true)) {
            $avisos[] = 'CRT 3 com PIS/COFINS '.$cstPis.'/'.$cstCof.': confirme com o contador as alíquotas da operação (sem 0,65%/3% automático).';
        }

        if ($crt === 2) {
            $avisos[] = 'CRT 2 (excesso de sublimite): ICMS com CST; PIS/COFINS no tratamento do Simples. DIFAL será avaliado na emissão.';
        }

        return [
            'ok' => $erros === [],
            'erros' => $erros,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  array{ok: bool, erros: list<string>, avisos: list<string>}  $analise
     */
    public static function resumirParaAviso(array $analise): string
    {
        $partes = [];
        if ($analise['erros'] !== []) {
            $partes[] = 'Incompatibilidades: '.implode(' ', $analise['erros']);
        }
        if ($analise['avisos'] !== []) {
            $partes[] = implode(' ', $analise['avisos']);
        }
        if ($partes === []) {
            return 'Parâmetros aparentam compatíveis com o novo regime. Produtos existentes não foram alterados.';
        }

        $fecho = $analise['erros'] !== []
            ? ' A emissão será bloqueada enquanto a operação estiver inválida. Cadastros/produtos não foram alterados.'
            : ' A emissão valida CST/CSOSN e PIS/COFINS no produto/item. Cadastros/produtos não foram alterados.';

        return implode(' ', $partes).$fecho;
    }

    public static function assertItemParaEmissao(
        int $crt,
        ?string $cstIcms,
        ?string $csosn,
        ?string $cstPis,
        ?string $cstCofins,
        string $prefixo = '',
    ): void {
        $cst2 = self::cstIcms2($cstIcms);

        // CRT 1/4: CSOSN vazio ainda usa fallback 102 no payload (preserva Simples/MEI).
        // CRT 2/3: exige CST ICMS informado — não inventamos CST a partir de CSOSN.
        if (! RegimeCrtMapper::usaIcmsCsosn($crt) && $cst2 === '') {
            throw new FiscalEngineException(
                $prefixo.'CRT '.$crt.' exige CST ICMS no item/produto (não inventamos CST). '
                .'Cadastros não foram alterados automaticamente.',
            );
        }

        PisCofinsCst::assertInformados($cstPis, $cstCofins, $prefixo);
    }

    public static function cstIcms2(mixed $raw): string
    {
        $d = preg_replace('/\D/', '', (string) ($raw ?? '')) ?? '';
        if ($d === '') {
            return '';
        }

        return str_pad(substr($d, -2), 2, '0', STR_PAD_LEFT);
    }

    private static function digits(string $value, int $len): string
    {
        $d = preg_replace('/\D/', '', $value) ?? '';
        if ($d === '') {
            return '';
        }

        return str_pad(substr($d, 0, $len), $len, '0', STR_PAD_LEFT);
    }
}
