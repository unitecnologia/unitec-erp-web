<?php

namespace App\Support\Erp\Nfse;

/**
 * Grupo serv/obra da DPS v1.01 (TCInfoObra).
 * inscImobFisc é opcional. A identificação é exclusiva: cObra, cCIB ou end.
 */
class NfseObra
{
    /**
     * Subitens da rejeição SEFIN E0370, em 6 dígitos.
     *
     * @var list<string>
     */
    public const CODIGOS = [
        '070201',
        '070202',
        '070401',
        '070501',
        '070502',
        '070601',
        '070602',
        '070701',
        '070801',
        '071701',
        '071901',
        '141403',
        '141404',
    ];

    public static function exige(mixed $cTribNac): bool
    {
        $codigo = self::codigo($cTribNac);

        return $codigo !== null && in_array($codigo, self::CODIGOS, true);
    }

    /**
     * Impede gravar valor que não cabe na coluna. Identificação vazia pode ser gravada.
     *
     * @param  array<string, mixed>  $linha
     */
    public static function erroTamanho(mixed $cTribNac, array $linha): ?string
    {
        if (! self::exige($cTribNac)) {
            return null;
        }

        if (self::excede($linha['obra_insc_imob_fisc'] ?? null, 30)) {
            return 'A inscrição imobiliária fiscal da obra deve ter no máximo 30 caracteres.';
        }

        $tipo = self::tipo($linha['obra_tipo'] ?? null);

        if ($tipo === 'cObra' && self::excede($linha['obra_c_obra'] ?? null, 30)) {
            return 'O código da obra (CNO ou CEI) deve ter no máximo 30 caracteres.';
        }

        if ($tipo === 'cCIB' && self::excede($linha['obra_c_cib'] ?? null, 8)) {
            return 'O CIB deve ter exatamente 8 caracteres.';
        }

        if ($tipo === 'end') {
            if (self::cepExcede($linha['obra_cep'] ?? null)) {
                return 'O CEP da obra deve ter 8 dígitos.';
            }

            if (self::excede($linha['obra_logradouro'] ?? null, 255)) {
                return 'O logradouro da obra deve ter no máximo 255 caracteres.';
            }

            if (self::excede($linha['obra_numero'] ?? null, 60)) {
                return 'O número da obra deve ter no máximo 60 caracteres.';
            }

            if (self::excede($linha['obra_complemento'] ?? null, 156)) {
                return 'O complemento da obra deve ter no máximo 156 caracteres.';
            }

            if (self::excede($linha['obra_bairro'] ?? null, 60)) {
                return 'O bairro da obra deve ter no máximo 60 caracteres.';
            }
        }

        return null;
    }

    /**
     * Mensagem para bloquear a transmissão. Fora da lista, não bloqueia.
     *
     * @param  array<string, mixed>  $linha
     */
    public static function pendencia(mixed $cTribNac, array $linha, string $descricao = ''): ?string
    {
        if (! self::exige($cTribNac)) {
            return null;
        }

        $tamanho = self::erroTamanho($cTribNac, $linha);

        if ($tamanho !== null) {
            return self::comServico($tamanho, $descricao);
        }

        $tipo = self::tipo($linha['obra_tipo'] ?? null);
        $codigo = self::rotulo((string) self::codigo($cTribNac));

        if ($tipo === null) {
            return self::comServico(
                'O código de tributação nacional '.$codigo.' exige as informações da obra. Informe o código CNO/CEI, o CIB ou o endereço da obra.',
                $descricao,
            );
        }

        if ($tipo === 'cObra' && self::texto($linha['obra_c_obra'] ?? null) === null) {
            return self::comServico('Informe o código da obra (CNO ou CEI).', $descricao);
        }

        if ($tipo === 'cCIB' && self::cib($linha['obra_c_cib'] ?? null) === null) {
            return self::comServico('Informe o CIB com 8 caracteres.', $descricao);
        }

        if ($tipo === 'end' && self::endereco($linha) === null) {
            return self::comServico('Informe o endereço da obra: CEP com 8 dígitos, logradouro, número e bairro.', $descricao);
        }

        return null;
    }

    /**
     * Colunas do item. Fora da lista, tudo nulo para o XML permanecer sem o grupo.
     *
     * @param  array<string, mixed>  $linha
     * @return array{
     *     obra_insc_imob_fisc: ?string,
     *     obra_tipo: ?string,
     *     obra_c_obra: ?string,
     *     obra_c_cib: ?string,
     *     obra_cep: ?string,
     *     obra_logradouro: ?string,
     *     obra_numero: ?string,
     *     obra_complemento: ?string,
     *     obra_bairro: ?string
     * }
     */
    public static function colunas(mixed $cTribNac, array $linha): array
    {
        $vazio = [
            'obra_insc_imob_fisc' => null,
            'obra_tipo' => null,
            'obra_c_obra' => null,
            'obra_c_cib' => null,
            'obra_cep' => null,
            'obra_logradouro' => null,
            'obra_numero' => null,
            'obra_complemento' => null,
            'obra_bairro' => null,
        ];

        if (! self::exige($cTribNac)) {
            return $vazio;
        }

        $tipo = self::tipo($linha['obra_tipo'] ?? null);
        $vazio['obra_insc_imob_fisc'] = self::texto($linha['obra_insc_imob_fisc'] ?? null, 30);
        $vazio['obra_tipo'] = $tipo;

        if ($tipo === 'cObra') {
            $vazio['obra_c_obra'] = self::texto($linha['obra_c_obra'] ?? null, 30);
        } elseif ($tipo === 'cCIB') {
            $vazio['obra_c_cib'] = self::texto($linha['obra_c_cib'] ?? null, 8);
        } elseif ($tipo === 'end') {
            $vazio['obra_cep'] = self::cep($linha['obra_cep'] ?? null);
            $vazio['obra_logradouro'] = self::texto($linha['obra_logradouro'] ?? null, 255);
            $vazio['obra_numero'] = self::texto($linha['obra_numero'] ?? null, 60);
            $vazio['obra_complemento'] = self::texto($linha['obra_complemento'] ?? null, 156);
            $vazio['obra_bairro'] = self::texto($linha['obra_bairro'] ?? null, 60);
        }

        return $vazio;
    }

    /**
     * Estrutura pronta para o XML. Só devolve quando a identificação fecha o XSD.
     *
     * @param  array<string, mixed>  $linha
     * @return array<string, mixed>|null
     */
    public static function paraXml(mixed $cTribNac, array $linha): ?array
    {
        if (self::pendencia($cTribNac, $linha) !== null) {
            return null;
        }

        $colunas = self::colunas($cTribNac, $linha);
        $tipo = $colunas['obra_tipo'];

        if ($tipo === null) {
            return null;
        }

        $grupo = [
            'inscImobFisc' => $colunas['obra_insc_imob_fisc'],
            'tipo' => $tipo,
        ];

        if ($tipo === 'cObra') {
            $grupo['cObra'] = $colunas['obra_c_obra'];
        } elseif ($tipo === 'cCIB') {
            $grupo['cCIB'] = $colunas['obra_c_cib'];
        } else {
            $grupo['end'] = self::endereco($linha);
        }

        return $grupo;
    }

    public static function codigo(mixed $cTribNac): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $cTribNac) ?? '';

        return strlen($digitos) === 6 ? $digitos : null;
    }

    private static function tipo(mixed $valor): ?string
    {
        $tipo = trim((string) $valor);

        return in_array($tipo, ['cObra', 'cCIB', 'end'], true) ? $tipo : null;
    }

    private static function texto(mixed $valor, ?int $max = null): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        if ($max !== null && mb_strlen($texto) > $max) {
            return null;
        }

        return $texto;
    }

    private static function excede(mixed $valor, int $max): bool
    {
        $texto = trim((string) $valor);

        return $texto !== '' && mb_strlen($texto) > $max;
    }

    private static function cep(mixed $valor): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        return strlen($digitos) === 8 ? $digitos : null;
    }

    private static function cepExcede(mixed $valor): bool
    {
        $digitos = preg_replace('/\D/', '', (string) $valor) ?? '';

        return strlen($digitos) > 8;
    }

    private static function cib(mixed $valor): ?string
    {
        $texto = self::texto($valor, 8);

        return $texto !== null && mb_strlen($texto) === 8 ? $texto : null;
    }

    /**
     * @param  array<string, mixed>  $linha
     * @return array{CEP: string, xLgr: string, nro: string, xCpl: ?string, xBairro: string}|null
     */
    private static function endereco(array $linha): ?array
    {
        $cep = self::cep($linha['obra_cep'] ?? null);
        $logradouro = self::texto($linha['obra_logradouro'] ?? null, 255);
        $numero = self::texto($linha['obra_numero'] ?? null, 60);
        $bairro = self::texto($linha['obra_bairro'] ?? null, 60);

        if ($cep === null || $logradouro === null || $numero === null || $bairro === null) {
            return null;
        }

        return [
            'CEP' => $cep,
            'xLgr' => $logradouro,
            'nro' => $numero,
            'xCpl' => self::texto($linha['obra_complemento'] ?? null, 156),
            'xBairro' => $bairro,
        ];
    }

    private static function rotulo(string $codigo): string
    {
        return substr($codigo, 0, 2).'.'.substr($codigo, 2, 2).'.'.substr($codigo, 4, 2);
    }

    private static function comServico(string $mensagem, string $descricao): string
    {
        $descricao = trim($descricao);

        if ($descricao === '') {
            return $mensagem;
        }

        return 'Serviço '.$descricao.': '.$mensagem;
    }
}
