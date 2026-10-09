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

    public const MODO_ATUALIZACAO_NORMAL = 'normal';

    public const MODO_ATUALIZACAO_HOTFIX = 'hotfix';

    public const MODO_ATUALIZACAO_BLOQUEADO = 'bloqueado';

    /**
     * @param  ?string  $modoAtualizacao  null = portal sem o campo (vale só bloquear_atualizacao)
     */
    public function __construct(
        public readonly string $status,
        public readonly ?string $validoAte = null,
        public readonly ?string $nome = null,
        public readonly ?string $mensagem = null,
        public readonly bool $bloquearAtualizacao = false,
        public readonly ?int $quantidadeComputadores = null,
        public readonly ?int $quantidadeTelefones = null,
        public readonly bool $fromCache = false,
        public readonly ?string $modoAtualizacao = null,
    ) {}

    public static function normalizeModoAtualizacao(mixed $value): ?string
    {
        $modo = strtolower(trim((string) ($value ?? '')));

        return in_array($modo, [
            self::MODO_ATUALIZACAO_NORMAL,
            self::MODO_ATUALIZACAO_HOTFIX,
            self::MODO_ATUALIZACAO_BLOQUEADO,
        ], true) ? $modo : null;
    }

    public function permiteAtualizacaoOficial(): bool
    {
        if ($this->modoAtualizacao !== null) {
            return $this->modoAtualizacao === self::MODO_ATUALIZACAO_NORMAL;
        }

        return ! $this->bloquearAtualizacao;
    }

    public function permiteHotfix(): bool
    {
        return $this->modoAtualizacao === self::MODO_ATUALIZACAO_HOTFIX;
    }

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
     * @return array{status: string, valido_ate: ?string, nome: ?string, mensagem: ?string, bloquear_atualizacao: bool, quantidade_computadores: ?int, quantidade_telefones: ?int, from_cache: bool, modo_atualizacao: ?string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'valido_ate' => $this->validoAte,
            'nome' => $this->nome,
            'mensagem' => $this->mensagem,
            'bloquear_atualizacao' => $this->bloquearAtualizacao,
            'quantidade_computadores' => $this->quantidadeComputadores,
            'quantidade_telefones' => $this->quantidadeTelefones,
            'from_cache' => $this->fromCache,
            'modo_atualizacao' => $this->modoAtualizacao,
        ];
    }

    /**
     * @param  array{status?: string, valido_ate?: ?string, nome?: ?string, mensagem?: ?string, bloquear_atualizacao?: bool, quantidade_computadores?: ?int, quantidade_telefones?: ?int, modo_atualizacao?: ?string}  $data
     */
    public static function fromArray(array $data, bool $fromCache = false): self
    {
        return new self(
            status: (string) ($data['status'] ?? self::STATUS_INDISPONIVEL),
            validoAte: isset($data['valido_ate']) ? (string) $data['valido_ate'] : null,
            nome: isset($data['nome']) ? (string) $data['nome'] : null,
            mensagem: isset($data['mensagem']) ? (string) $data['mensagem'] : null,
            bloquearAtualizacao: (bool) ($data['bloquear_atualizacao'] ?? false),
            quantidadeComputadores: self::normalizeQuota($data['quantidade_computadores'] ?? null),
            quantidadeTelefones: self::normalizeQuota($data['quantidade_telefones'] ?? null),
            fromCache: $fromCache,
            modoAtualizacao: self::normalizeModoAtualizacao($data['modo_atualizacao'] ?? null),
        );
    }

    private static function normalizeQuota(mixed $value): ?int
    {
        if ($value === null || $value === '' || strtolower((string) $value) === 'null') {
            return null;
        }

        return max(0, (int) $value);
    }
}
