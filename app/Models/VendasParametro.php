<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

#[Fillable([
    'empresa_id',
    'uf',
    'ambiente',
    'versao_nfe',
    'forma_emissao',
    'tipo_emissao',
    'caminho_certificado',
    'senha_certificado',
    'numero_serie_certificado',
    'crypt_lib',
    'http_lib',
    'xml_sign',
    'ssl_tipo',
    'aguardar',
    'tentativas',
    'intervalo',
    'ajustar_auto',
    'proxy_host',
    'proxy_porta',
    'proxy_usuario',
    'proxy_senha',
    'path_salvar_nfe',
    'path_schemas_nfe',
    'path_enviada_nfe',
    'path_can_nfe',
    'path_inuti_nfe',
    'path_evento_nfe',
    'path_pdf_nfe',
    'logomarca',
    'numero',
    'serie',
    'serie_nfe',
    'numero_nfe',
    'dfe_ultimo_nsu',
    'dfe_bloqueado_ate',
    'id_token',
    'token',
    'versao_qrcode',
    'email_host',
    'email_porta',
    'email_user',
    'email_senha',
    'email_assunto',
    'email_ssl',
    'email_tls',
    'email_modo',
    'email_api_provedor',
    'email_api_key',
    'resp_tecnico_cnpj',
    'resp_tecnico_contato',
    'resp_tecnico_email',
    'resp_tecnico_fone',
    'resp_tecnico_id_csrt',
    'resp_tecnico_csrt',
])]
class VendasParametro extends Model
{
    protected $table = 'vendas_parametros';

    protected $primaryKey = 'empresa_id';

    public $incrementing = false;

    public const AMBIENTE_PRODUCAO = 0;

    public const AMBIENTE_HOMOLOGACAO = 1;

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function forEmpresa(int $empresaId): self
    {
        return static::query()->firstOrCreate(
            ['empresa_id' => $empresaId],
            [
                'ambiente' => self::AMBIENTE_HOMOLOGACAO,
                'numero' => 1,
                'serie' => '1',
                'serie_nfe' => 1,
                'numero_nfe' => 1,
            ],
        );
    }

    public function peekNumero(): int
    {
        return (int) ($this->numero ?? 1);
    }

    public function consumeNumero(): int
    {
        return $this->consumeSequencia('numero');
    }

    public function peekNumeroNfe(): int
    {
        return (int) ($this->numero_nfe ?? 1);
    }

    public function consumeNumeroNfe(): int
    {
        return $this->consumeSequencia('numero_nfe');
    }

    /**
     * Garante que o próximo número NFC-e seja pelo menos $minimo (ex.: após rejeição 539).
     */
    public function ensureNumeroPeloMenos(int $minimo): void
    {
        $this->ensureSequenciaPeloMenos('numero', $minimo);
    }

    /**
     * Consome o sequencial em conexão separada para o incremento sobreviver ao rollback
     * da venda quando a SEFAZ rejeita (ex.: duplicidade 539).
     */
    private function consumeSequencia(string $column): int
    {
        $connection = $this->sequenciaConnectionName();

        $numero = \Illuminate\Support\Facades\DB::connection($connection)->transaction(function () use ($connection, $column): int {
            $row = static::on($connection)
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new \RuntimeException('Parâmetros fiscais não encontrados para sequencial.');
            }

            $atual = max(1, (int) ($row->{$column} ?? 1));
            $row->newQuery()
                ->whereKey($row->getKey())
                ->update([$column => $atual + 1]);

            return $atual;
        });

        $this->setAttribute($column, $numero + 1);

        return $numero;
    }

    private function ensureSequenciaPeloMenos(string $column, int $minimo): void
    {
        $minimo = max(1, $minimo);
        $connection = $this->sequenciaConnectionName();

        \Illuminate\Support\Facades\DB::connection($connection)->transaction(function () use ($connection, $column, $minimo): void {
            $row = static::on($connection)
                ->whereKey($this->getKey())
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return;
            }

            $atual = max(1, (int) ($row->{$column} ?? 1));
            if ($atual >= $minimo) {
                return;
            }

            $row->newQuery()
                ->whereKey($row->getKey())
                ->update([$column => $minimo]);
        });

        if ((int) ($this->{$column} ?? 1) < $minimo) {
            $this->setAttribute($column, $minimo);
        }
    }

    private function sequenciaConnectionName(): string
    {
        $default = (string) config('database.default');
        $driver = (string) config("database.connections.{$default}.driver");

        // SQLite :memory: não compartilha estado entre conexões — usa a mesma.
        if ($driver === 'sqlite') {
            $database = (string) config("database.connections.{$default}.database");
            if ($database === ':memory:' || str_contains($database, 'mode=memory')) {
                return $default;
            }
        }

        $seq = $default.'_fiscal_seq';

        if (! config()->has("database.connections.{$seq}")) {
            config(["database.connections.{$seq}" => config("database.connections.{$default}")]);
        }

        return $seq;
    }

    public function hasStoredSenhaCertificado(): bool
    {
        return filled($this->getRawOriginal('senha_certificado'));
    }

    public function safeSenhaCertificado(): ?string
    {
        return $this->safeEncrypted('senha_certificado');
    }

    /**
     * Lê senha em texto puro. Valores legados criptografados pelo Laravel (eyJ…)
     * são descriptografados uma vez e regravados em claro; MAC inválido → null.
     */
    public function safeEncrypted(string $attribute): ?string
    {
        $raw = $this->getRawOriginal($attribute);

        if (! filled($raw)) {
            return null;
        }

        $raw = (string) $raw;

        if ($this->looksLikeLaravelEncryptedPayload($raw)) {
            try {
                $plain = Crypt::decryptString($raw);
            } catch (DecryptException) {
                return null;
            }

            if (! filled($plain)) {
                return null;
            }

            $plain = (string) $plain;

            if ($this->exists && $this->getKey() !== null) {
                $this->newQueryWithoutScopes()
                    ->whereKey($this->getKey())
                    ->update([$attribute => $plain]);

                $this->attributes[$attribute] = $plain;
                $this->syncOriginalAttribute($attribute);
            }

            return $plain;
        }

        return $raw;
    }

    private function looksLikeLaravelEncryptedPayload(string $value): bool
    {
        if (! str_starts_with($value, 'eyJ')) {
            return false;
        }

        $decoded = base64_decode($value, true);

        if ($decoded === false || $decoded === '') {
            return false;
        }

        $json = json_decode($decoded, true);

        return is_array($json)
            && isset($json['iv'], $json['value'], $json['mac']);
    }

    protected function casts(): array
    {
        return [
            'dfe_bloqueado_ate' => 'datetime',
        ];
    }
}
