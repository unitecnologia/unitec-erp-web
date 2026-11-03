<?php

namespace App\Support\Fiscal;

use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * CST de PIS/COFINS suportados na emissão online (NF-e 55 / NFC-e 65).
 * Não inventa CST; CST 03 (quantidade) não é suportado nesta etapa.
 *
 * Grupo XML PISNT/COFINSNT (leiaute): CST 04 (monofásico), 05 (ST da contribuição),
 * 06–09. Isso é distinto do grupo opcional PISST/COFINSST (retenção), ainda não emitido.
 */
final class PisCofinsCst
{
    /** @var list<string> */
    public const ALIQ = ['01', '02'];

    /** @var list<string> */
    public const QTDE = ['03'];

    /**
     * CST do grupo PISNT/COFINSNT (sem BC/alíquota/valor no XML).
     * Inclui 04 (monofásico) e 05 (substituição tributária da contribuição).
     *
     * @var list<string>
     */
    public const NT = ['04', '05', '06', '07', '08', '09'];

    /** @var list<string> */
    public const OUTR = ['49', '99'];

    public static function normalizar(?string $raw): string
    {
        $digits = preg_replace('/\D/', '', (string) $raw) ?? '';
        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, 0, 2), 2, '0', STR_PAD_LEFT);
    }

    public static function isAliq(string $cst): bool
    {
        return in_array($cst, self::ALIQ, true);
    }

    public static function isNt(string $cst): bool
    {
        return in_array($cst, self::NT, true);
    }

    /** CST 04 — monofásico (grupo PISNT/COFINSNT). */
    public static function isMonofasico(string $cst): bool
    {
        return self::normalizar($cst) === '04';
    }

    /**
     * CST 05 — ST da contribuição no grupo PISNT/COFINSNT.
     * Não implica emitir o grupo separado PISST/COFINSST.
     */
    public static function isStContribuicaoNt(string $cst): bool
    {
        return self::normalizar($cst) === '05';
    }

    public static function isOutr(string $cst): bool
    {
        return in_array($cst, self::OUTR, true);
    }

    public static function isQtde(string $cst): bool
    {
        return in_array($cst, self::QTDE, true);
    }

    /**
     * @return list<string>
     */
    public static function permitidosEmissao(): array
    {
        return [...self::ALIQ, ...self::NT, ...self::OUTR];
    }

    /**
     * Exige CST informado e suportado (bloqueia vazio, 03 e códigos fora da lista).
     *
     * @return array{0: string, 1: string} [cstPis, cstCofins]
     */
    public static function assertInformados(
        ?string $cstPisRaw,
        ?string $cstCofinsRaw,
        string $prefixo = '',
    ): array {
        $cstPis = self::normalizar($cstPisRaw);
        $cstCofins = self::normalizar($cstCofinsRaw);

        if ($cstPis === '' || $cstCofins === '') {
            throw new FiscalEngineException(
                $prefixo.'informe CST de PIS e CST de COFINS no produto/operação. '
                .'Não é permitido inventar CST 01 nem CST 07 automaticamente.',
            );
        }

        self::assertSuportado($cstPis, 'PIS', $prefixo);
        self::assertSuportado($cstCofins, 'COFINS', $prefixo);

        return [$cstPis, $cstCofins];
    }

    public static function assertSuportado(string $cst, string $imposto, string $prefixo = ''): void
    {
        if (self::isQtde($cst)) {
            throw new FiscalEngineException(
                $prefixo.'CST '.$imposto.' 03 (tributação por quantidade / '.$imposto.'Qtde) '
                .'ainda não é suportado na emissão online. Informe outro CST ou aguarde implementação.',
            );
        }

        if (! in_array($cst, self::permitidosEmissao(), true)) {
            throw new FiscalEngineException(
                $prefixo.'CST '.$imposto.' "'.$cst.'" não suportado. '
                .'Use 01/02, 04–09 ou 49/99 conforme a operação.',
            );
        }
    }
}
