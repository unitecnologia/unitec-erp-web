<?php

namespace App\Support\Erp\License;

use Carbon\Carbon;
use Throwable;

final class LicencaSnapshot
{
    public const STATUS_ATIVO = 'ativo';

    public const STATUS_BLOQUEADO = 'bloqueado';

    public const STATUS_NAO_ENCONTRADO = 'nao_encontrado';

    public const STATUS_SEM_CNPJ = 'sem_cnpj';

    public const STATUS_INDISPONIVEL = 'indisponivel';

    public const STATUS_DESABILITADO = 'desabilitado';

    public function __construct(
        public readonly string $status,
        public readonly ?string $validoAte = null,
        public readonly ?string $nome = null,
        public readonly ?string $mensagem = null,
        public readonly bool $fromCache = false,
    ) {}

    public function isAllowed(): bool
    {
        return in_array($this->status, [
            self::STATUS_ATIVO,
            self::STATUS_DESABILITADO,
            self::STATUS_INDISPONIVEL,
        ], true);
    }

    public function isBlocked(): bool
    {
        return ! $this->isAllowed();
    }

    public function expiresAt(): ?Carbon
    {
        $raw = trim((string) $this->validoAte);

        if ($raw === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{status: string, valido_ate: ?string, nome: ?string, mensagem: ?string, from_cache: bool}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'valido_ate' => $this->validoAte,
            'nome' => $this->nome,
            'mensagem' => $this->mensagem,
            'from_cache' => $this->fromCache,
        ];
    }

    /**
     * @param  array{status?: string, valido_ate?: ?string, nome?: ?string, mensagem?: ?string}  $data
     */
    public static function fromArray(array $data, bool $fromCache = false): self
    {
        return new self(
            status: (string) ($data['status'] ?? self::STATUS_INDISPONIVEL),
            validoAte: isset($data['valido_ate']) ? (string) $data['valido_ate'] : null,
            nome: isset($data['nome']) ? (string) $data['nome'] : null,
            mensagem: isset($data['mensagem']) ? (string) $data['mensagem'] : null,
            fromCache: $fromCache,
        );
    }
}
