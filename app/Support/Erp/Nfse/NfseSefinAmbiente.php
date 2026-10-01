<?php

namespace App\Support\Erp\Nfse;

enum NfseSefinAmbiente: string
{
    case Producao = 'producao';
    case ProducaoRestrita = 'producao_restrita';

    /**
     * @return array<string, string>
     */
    public static function opcoes(): array
    {
        return [
            self::Producao->value => 'Produção',
            self::ProducaoRestrita->value => 'Produção Restrita',
        ];
    }

    public static function daEmpresa(mixed $valor): self
    {
        $ambiente = self::tryFrom(strtolower(trim((string) $valor)));

        if (! $ambiente instanceof self) {
            throw new NfseSefinNaoEnviada('Ambiente da NFS-e não configurado.');
        }

        return $ambiente;
    }

    public static function para(string $ambiente): self
    {
        return match (strtolower(trim($ambiente))) {
            'producao', 'production' => self::Producao,
            default => self::ProducaoRestrita,
        };
    }

    public function tpAmb(): string
    {
        return $this->destino()['tp_amb'];
    }

    public function host(): string
    {
        return $this->destino()['host'];
    }

    public function url(): string
    {
        return $this->destino()['url'];
    }

    public function eTeste(): bool
    {
        return $this !== self::Producao;
    }

    /**
     * @return array{tp_amb: string, host: string, url: string}
     */
    private function destino(): array
    {
        return match ($this) {
            self::Producao => [
                'tp_amb' => '1',
                'host' => NfseSefinEndpoints::HOST_PRODUCAO,
                'url' => NfseSefinEndpoints::NFSE_PRODUCAO,
            ],
            self::ProducaoRestrita => [
                'tp_amb' => '2',
                'host' => NfseSefinEndpoints::HOST_PRODUCAO_RESTRITA,
                'url' => NfseSefinEndpoints::NFSE_PRODUCAO_RESTRITA,
            ],
        };
    }
}
