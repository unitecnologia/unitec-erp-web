<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'empresa_id',
    'nome',
    'ip',
    'numero_loja',
    'empresa_ativa',
    'numero_logico_terminal',
    'eh_caixa',
    'pdv',
    'ativo',
    'restaurante',
    'delivery',
    'logado',
    'usa_tef',
    'usa_pos',
    'exibe_f3',
    'exibe_f4',
    'exibe_f5',
    'exibe_f6',
    'pesquisa_rapida',
    'ler_peso',
    'busca_balanca_barras',
    'mensagem_pdv',
    'mostrar_mensagem_pdv',
    'mostrar_tela_caixa_livre',
    'time_tela_caixa_livre',
    'imprime',
    'usa_gaveta',
    'usar_device_service',
    'fab_impressora',
    'modelo',
    'porta',
    'velocidade',
    'nvias',
    'serie',
    'numeracao_inicial',
    'usar_numero_inicial',
    'tipo_impressora',
    'tipo_fechamento',
    'impressora_nome',
    'pagina_codigo',
    'margem_superior',
    'margem_inferior',
    'margem_esquerda',
    'margem_direita',
    'largura_bobina',
    'tamanho_fonte',
    'balanca_porta',
    'balanca_velocidade',
    'balanca_marca',
    'balanca_paridade',
    'balanca_databits',
    'balanca_stopbits',
    'balanca_handshaking',
    'qtd_tentativa_conect_bal',
    'modelo_tef',
    'tef_gerenciador',
    'ip_servidor_tef',
    'porta_pin_pad',
    'mensagem_pin_pad',
    'tef_max_cartoes',
    'tef_troco_maximo',
    'tef_via_reduzida',
    'tef_multiplos_cartoes',
    'caminho_cozinha',
    'caminho_bar',
    'impressora_extra',
    'tef_extra',
    'categoria_licenca',
    'origens_dispositivo',
    'device_uuid',
    'device_name',
    'device_platform',
    'device_registered_at',
    'device_last_seen_at',
])]
class Terminal extends Model
{
    protected $table = 'terminais';

    protected function casts(): array
    {
        return [
            'eh_caixa' => 'boolean',
            'pdv' => 'boolean',
            'ativo' => 'boolean',
            'restaurante' => 'boolean',
            'delivery' => 'boolean',
            'logado' => 'boolean',
            'usa_tef' => 'boolean',
            'usa_pos' => 'boolean',
            'exibe_f3' => 'boolean',
            'exibe_f4' => 'boolean',
            'exibe_f5' => 'boolean',
            'exibe_f6' => 'boolean',
            'pesquisa_rapida' => 'boolean',
            'ler_peso' => 'boolean',
            'busca_balanca_barras' => 'boolean',
            'mostrar_mensagem_pdv' => 'boolean',
            'mostrar_tela_caixa_livre' => 'boolean',
            'imprime' => 'boolean',
            'usa_gaveta' => 'boolean',
            'usar_device_service' => 'boolean',
            'usar_numero_inicial' => 'boolean',
            'tef_via_reduzida' => 'boolean',
            'tef_multiplos_cartoes' => 'boolean',
            'margem_superior' => 'decimal:2',
            'margem_inferior' => 'decimal:2',
            'margem_esquerda' => 'decimal:2',
            'margem_direita' => 'decimal:2',
            'tef_troco_maximo' => 'decimal:2',
            'impressora_extra' => 'array',
            'tef_extra' => 'array',
            'origens_dispositivo' => 'array',
            'device_registered_at' => 'datetime',
            'device_last_seen_at' => 'datetime',
        ];
    }

    /**
     * Celular/tablet de app (Força de Vendas, Vendas Internas, Gestor…): ocupa vaga de telefone e
     * nunca emite NFC-e, mesmo que alguém marque pdv/eh_caixa por engano.
     */
    public function ehAparelhoMovel(): bool
    {
        if (strtolower(trim((string) ($this->categoria_licenca ?? ''))) === 'telefone') {
            return true;
        }

        $origens = $this->origensNormalizadas();

        return $origens !== []
            && array_diff($origens, ['erp_web', 'gestor_web', 'pdv_offline']) === $origens
            && ! $this->ehPdvOffline();
    }

    /** PDV offline (instalado no PC do caixa, sincroniza por carga/retorno). */
    public function ehPdvOffline(): bool
    {
        if (in_array('pdv_offline', $this->origensNormalizadas(), true)) {
            return true;
        }

        return preg_match('/^PDV\s*\d+$/i', trim((string) ($this->nome ?? ''))) === 1;
    }

    /** Caixa que emite NFC-e (PDV web do ERP ou PDV offline). */
    public function emiteNfce(): bool
    {
        if ($this->ehAparelhoMovel()) {
            return false;
        }

        return (bool) ($this->pdv ?? false) || (bool) ($this->eh_caixa ?? false) || $this->ehPdvOffline();
    }

    /**
     * @return list<string>
     */
    public function origensNormalizadas(): array
    {
        $origens = $this->origens_dispositivo;

        if (is_string($origens)) {
            $origens = json_decode($origens, true);
        }

        return collect(is_array($origens) ? $origens : [])
            ->map(static fn (mixed $v): string => strtolower(trim((string) $v)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function caixaSessoes(): HasMany
    {
        return $this->hasMany(PdvCaixaSessao::class);
    }

    public function vendedores(): BelongsToMany
    {
        return $this->belongsToMany(Vendedor::class, 'terminal_vendedor')->withTimestamps();
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultAttributes(?int $empresaId = null): array
    {
        return [
            'empresa_id' => $empresaId,
            'ip' => request()->ip(),
            'tipo_impressora' => '0',
            'nvias' => 1,
            'eh_caixa' => true,
            'pdv' => true,
            'ativo' => true,
            'imprime' => true,
            'usar_device_service' => true,
            'busca_balanca_barras' => true,
            // Defaults de balança serial (PDV); leitura no PDV fica desligada até o cliente habilitar.
            'balanca_marca' => 'balToledo',
            'balanca_porta' => 'COM3',
            'balanca_velocidade' => '9600',
            'balanca_databits' => '8',
            'balanca_paridade' => 'None',
            'balanca_stopbits' => '1',
            'balanca_handshaking' => 'None',
            'ler_peso' => false,
        ];
    }
}
