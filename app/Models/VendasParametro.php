<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            ],
        );
    }

    public function peekNumero(): int
    {
        return (int) ($this->numero ?? 1);
    }

    public function consumeNumero(): int
    {
        $numero = $this->peekNumero();
        $this->update(['numero' => $numero + 1]);

        return $numero;
    }

    public function hasStoredSenhaCertificado(): bool
    {
        return filled($this->getRawOriginal('senha_certificado'));
    }

    public function safeSenhaCertificado(): ?string
    {
        return $this->safeEncrypted('senha_certificado');
    }

    public function safeEncrypted(string $attribute): ?string
    {
        if (! filled($this->getRawOriginal($attribute))) {
            return null;
        }

        try {
            $value = $this->{$attribute};

            return filled($value) ? (string) $value : null;
        } catch (DecryptException) {
            return null;
        }
    }

    protected function casts(): array
    {
        return [
            'senha_certificado' => 'encrypted',
            'proxy_senha' => 'encrypted',
            'email_senha' => 'encrypted',
        ];
    }
}
