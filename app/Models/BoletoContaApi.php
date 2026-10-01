<?php

namespace App\Models;

use App\Support\Erp\EmpresaParametros;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id', 'nome', 'banco', 'ativo', 'padrao', 'ambiente',
    'convenio', 'carteira', 'agencia', 'agencia_dv', 'conta', 'conta_dv',
    'beneficiario_codigo', 'client_id', 'client_secret', 'dev_app_key', 'senha_api',
    'api_url', 'callback_url', 'especie_documento', 'instrucao1', 'instrucao2',
    'juros_pct', 'multa_pct', 'desconto_pct', 'protesto_dias', 'pos_vencimento', 'pix_hibrido',
])]
class BoletoContaApi extends Model
{
    protected $table = 'boleto_contas_api';

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function boletos(): HasMany
    {
        return $this->hasMany(Boleto::class, 'boleto_conta_api_id');
    }

    /**
     * Overlay da empresa com credenciais desta conta (não persiste).
     * Serviços Ailos/Sicredi continuam lendo param_boleto_*.
     */
    public function asEmpresaOverlay(Empresa $empresa): Empresa
    {
        $overlay = new Empresa;
        $overlay->forceFill($empresa->getAttributes());
        $overlay->id = $empresa->id;
        $overlay->exists = true;
        $overlay->syncOriginal();

        $overlay->forceFill([
            'param_boleto_banco' => $this->banco,
            'param_boleto_ambiente' => $this->ambiente ?: 'homologacao',
            'param_boleto_convenio' => $this->convenio,
            'param_boleto_carteira' => $this->carteira,
            'param_boleto_agencia' => $this->agencia,
            'param_boleto_agencia_dv' => $this->agencia_dv,
            'param_boleto_conta' => $this->conta,
            'param_boleto_conta_dv' => $this->conta_dv,
            'param_boleto_beneficiario_codigo' => $this->beneficiario_codigo,
            'param_boleto_client_id' => $this->client_id,
            'param_boleto_client_secret' => $this->client_secret,
            'param_boleto_dev_app_key' => $this->dev_app_key,
            'param_boleto_senha_api' => $this->senha_api,
            'param_boleto_api_url' => $this->api_url,
            'param_boleto_callback_url' => $this->callback_url
                ?: EmpresaParametros::BOLETO_AILOS_AUTH_CALLBACK_URL,
            'param_boleto_especie_documento' => $this->especie_documento ?: 'DM',
            'param_boleto_instrucao1' => $this->instrucao1,
            'param_boleto_instrucao2' => $this->instrucao2,
            'param_boleto_juros_pct' => $this->juros_pct,
            'param_boleto_multa_pct' => $this->multa_pct,
            'param_boleto_desconto_pct' => $this->desconto_pct,
            'param_boleto_protesto_dias' => $this->protesto_dias,
            'param_boleto_pos_vencimento' => $this->pos_vencimento ?: 'nenhuma',
            'param_boleto_pix_hibrido' => (bool) $this->pix_hibrido,
            'param_boleto_habilitar' => true,
        ]);

        return $overlay;
    }

    public function rotulo(): string
    {
        $banco = preg_replace('/\D/', '', (string) $this->banco) ?? '';
        $bancoNome = match ($banco) {
            EmpresaParametros::BOLETO_BANCO_AILOS => 'Ailos',
            EmpresaParametros::BOLETO_BANCO_SICREDI => 'Sicredi',
            default => $banco !== '' ? $banco : 'Banco',
        };

        $nome = trim((string) ($this->nome ?? ''));
        if ($nome !== '' && mb_strtolower($nome, 'UTF-8') !== mb_strtolower($bancoNome, 'UTF-8')) {
            return $bancoNome.' — '.$nome;
        }

        $extra = match ($banco) {
            EmpresaParametros::BOLETO_BANCO_AILOS => trim((string) ($this->convenio ?: $this->conta ?: '')),
            EmpresaParametros::BOLETO_BANCO_SICREDI => trim((string) ($this->beneficiario_codigo ?: '')),
            default => '',
        };

        return $extra !== '' ? $bancoNome.' ('.$extra.')' : $bancoNome;
    }

    public function bancoCompe(): string
    {
        return preg_replace('/\D/', '', (string) $this->banco) ?? '';
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
            'padrao' => 'boolean',
            'pix_hibrido' => 'boolean',
        ];
    }
}
