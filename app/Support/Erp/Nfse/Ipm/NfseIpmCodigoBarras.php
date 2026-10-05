<?php

namespace App\Support\Erp\Nfse\Ipm;

use Throwable;

/**
 * Code 128 do identificador da NFS-e, como no DANFSe da IPM.
 */
final class NfseIpmCodigoBarras
{
    private const PADROES = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    private const INICIO_B = 104;

    private const INICIO_C = 105;

    private const FIM = 106;

    public static function pngDataUri(string $conteudo, int $modulo = 2, int $altura = 44): ?string
    {
        $valores = self::valores(preg_replace('/\s+/', '', $conteudo) ?? '');

        if ($valores === null || ! function_exists('imagecreatetruecolor')) {
            return null;
        }

        $larguras = '';
        foreach ($valores as $valor) {
            $larguras .= self::PADROES[$valor];
        }

        $margem = 10 * $modulo;
        $total = array_sum(array_map('intval', str_split($larguras))) * $modulo + 2 * $margem;

        try {
            $imagem = imagecreatetruecolor($total, $altura);
            $branco = imagecolorallocate($imagem, 255, 255, 255);
            $preto = imagecolorallocate($imagem, 0, 0, 0);
            imagefilledrectangle($imagem, 0, 0, $total - 1, $altura - 1, $branco);

            $x = $margem;
            foreach (str_split($larguras) as $i => $largura) {
                $pixels = (int) $largura * $modulo;
                if ($i % 2 === 0) {
                    imagefilledrectangle($imagem, $x, 0, $x + $pixels - 1, $altura - 1, $preto);
                }
                $x += $pixels;
            }

            ob_start();
            imagepng($imagem);
            $png = (string) ob_get_clean();
            imagedestroy($imagem);
        } catch (Throwable) {
            return null;
        }

        return $png !== '' ? 'data:image/png;base64,'.base64_encode($png) : null;
    }

    /**
     * @return list<int>|null
     */
    private static function valores(string $texto): ?array
    {
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^\d+$/', $texto) === 1 && strlen($texto) % 2 === 0) {
            $valores = [self::INICIO_C];
            foreach (str_split($texto, 2) as $par) {
                $valores[] = (int) $par;
            }
        } else {
            $valores = [self::INICIO_B];
            foreach (str_split($texto) as $caractere) {
                $codigo = ord($caractere);
                if ($codigo < 32 || $codigo > 126) {
                    return null;
                }
                $valores[] = $codigo - 32;
            }
        }

        $soma = $valores[0];
        foreach (array_slice($valores, 1) as $posicao => $valor) {
            $soma += $valor * ($posicao + 1);
        }

        $valores[] = $soma % 103;
        $valores[] = self::FIM;

        return $valores;
    }
}
