<?php

namespace App\Support\Erp\Ccg;

final class CcgConsGtinResult
{
    /**
     * @param  list<string>  $cests
     */
    public function __construct(
        public readonly string $cStat,
        public readonly string $xMotivo,
        public readonly ?string $gtin = null,
        public readonly ?string $tpGtin = null,
        public readonly ?string $xProd = null,
        public readonly ?string $ncm = null,
        public readonly array $cests = [],
    ) {}

    public function sucesso(): bool
    {
        return $this->cStat === '9490';
    }

    public function mensagem(): string
    {
        $motivo = $this->xMotivo !== ''
            ? $this->xMotivo
            : (self::motivosOficiais()[$this->cStat] ?? 'Retorno não reconhecido da SVRS.');

        $mensagem = $this->cStat . ' – ' . $motivo;

        if ($this->cStat === '656') {
            $mensagem .= ' Aguarde antes de consultar este GTIN novamente.';
        }

        return $mensagem;
    }

    /**
     * @return array<string, string>
     */
    public static function motivosOficiais(): array
    {
        return [
            '9490' => 'Consulta realizada com sucesso',
            '9491' => 'Rejeição: GTIN com dígito verificador inválido',
            '9492' => 'Rejeição: GTIN não possui prefixo 789 ou 790 (Brasil)',
            '9493' => 'Rejeição: CNPJ/CPF do Certificado de Transmissão não é emitente de NF-e ou NFC-e',
            '9494' => 'Rejeição: GTIN inexistente no Cadastro Centralizado de GTIN (CCG)',
            '9495' => 'Rejeição: GTIN existe no CCG com situação inválida. Solicitar ao dono da marca que entre em contato com a GS1',
            '9496' => 'Rejeição: GTIN existe no CCG, mas o dono da marca não autorizou a publicação das informações. Entrar em contato com o dono da marca',
            '9497' => 'Rejeição: GTIN existe no CCG com NCM não informado',
            '9498' => 'Rejeição: GTIN existe no CCG com NCM inválido',
            '656' => 'Rejeição: Consumo Indevido',
        ];
    }
}
