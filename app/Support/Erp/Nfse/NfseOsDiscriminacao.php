<?php

namespace App\Support\Erp\Nfse;

use App\Models\OrdemServico;

/**
 * Bloco da OS (número, equipamento, problema, laudo, observações) para a discriminação da NFS-e.
 * O serviço prestado de cada serviço vai na própria linha da nota (nfse_itens.servico_prestado).
 * Uma linha por informação; campos vazios ou repetidos ficam de fora.
 */
final class NfseOsDiscriminacao
{
    public const LIMITE_TEXTO_LIVRE = 400;

    /** Mesmo limite do campo Discriminação da NFS-e. */
    public const LIMITE = 1500;

    public static function texto(OrdemServico $ordem): string
    {
        $linhas = ['OS nº '.self::numero($ordem)];

        $equipamento = self::equipamento($ordem);

        if ($equipamento !== '') {
            $linhas[] = $equipamento;
        }

        $usados = [];

        foreach ([
            'Problema' => $ordem->problema,
            'Laudo' => $ordem->laudo,
            'Obs.' => $ordem->observacoes,
        ] as $rotulo => $valor) {
            $texto = self::limpar($valor);
            $chave = self::chave($texto);

            if ($chave === '' || self::repetido($chave, $usados)) {
                continue;
            }

            $usados[] = $chave;
            $linhas[] = $rotulo.': '.self::cortar($texto, self::LIMITE_TEXTO_LIVRE);
        }

        return self::cortar(implode("\n", $linhas), self::LIMITE);
    }

    /**
     * Junta o bloco à discriminação atual sem repetir uma OS que já está lá.
     */
    public static function anexar(string $atual, OrdemServico $ordem): string
    {
        $atual = trim($atual);
        $cabecalho = 'OS nº '.self::numero($ordem);

        if (preg_match('/^'.preg_quote($cabecalho, '/').'$/mu', $atual) === 1) {
            return $atual;
        }

        $bloco = self::texto($ordem);

        return self::cortar($atual === '' ? $bloco : $atual."\n\n".$bloco, self::LIMITE);
    }

    private static function numero(OrdemServico $ordem): string
    {
        return trim((string) ($ordem->numero ?: $ordem->id));
    }

    private static function equipamento(OrdemServico $ordem): string
    {
        $descricao = self::limpar($ordem->descricao);
        $modelo = self::limpar($ordem->modelo);
        $placa = mb_strtoupper(self::limpar($ordem->placa), 'UTF-8');
        $serie = self::limpar($ordem->numero_serie);
        $chaveDescricao = self::chave($descricao);
        $partes = [];

        if ($descricao !== '') {
            $partes[] = 'Equipamento/Veículo: '.$descricao;
        }

        if ($modelo !== '' && ! str_contains($chaveDescricao, self::chave($modelo))) {
            $partes[] = 'Modelo: '.$modelo;
        }

        if ($placa !== '' && ! str_contains($chaveDescricao, self::chave($placa))) {
            $partes[] = 'Placa: '.$placa;
        }

        if ($serie !== '' && self::chave($serie) !== self::chave($placa) && ! str_contains($chaveDescricao, self::chave($serie))) {
            $partes[] = 'Nº série/IMEI: '.$serie;
        }

        return implode(' | ', $partes);
    }

    /**
     * @param  list<string>  $usados
     */
    private static function repetido(string $chave, array $usados): bool
    {
        foreach ($usados as $usado) {
            if (str_contains($usado, $chave)) {
                return true;
            }
        }

        return false;
    }

    private static function limpar(mixed $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $valor) ?? '');
    }

    private static function chave(string $texto): string
    {
        return preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtoupper($texto, 'UTF-8')) ?? '';
    }

    private static function cortar(string $texto, int $limite): string
    {
        return mb_strlen($texto) > $limite ? rtrim(mb_substr($texto, 0, $limite - 3)).'...' : $texto;
    }
}
