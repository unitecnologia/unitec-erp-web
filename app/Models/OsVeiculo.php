<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;

class OsVeiculo extends Model
{
    protected $table = 'os_veiculos';

    protected $fillable = [
        'empresa_id',
        'placa',
        'placa_alternativa',
        'descricao',
        'marca',
        'modelo',
        'submodelo',
        'versao',
        'ano_fabricacao',
        'ano_modelo',
        'cidade',
        'uf',
        'renavam',
        'chassi',
        'cor',
        'combustivel',
        'tipo',
        'especie',
        'carroceria',
        'origem',
        'nacionalidade',
        'segmento',
        'subsegmento',
        'consultado_em',
    ];

    protected function casts(): array
    {
        return [
            'consultado_em' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public static function normalizarPlaca(string $placa): string
    {
        return mb_strtoupper(str_replace([' ', '-'], '', trim($placa)), 'UTF-8');
    }

    public static function placaValida(string $placa): bool
    {
        return (bool) preg_match('/^[A-Z]{3}[0-9]{4}$/', $placa)
            || (bool) preg_match('/^[A-Z]{3}[0-9][A-Z][0-9]{2}$/', $placa);
    }

    /**
     * Placa informada + equivalente clássica/Mercosul (quando conversível).
     *
     * @return list<string>
     */
    public static function variantesPlaca(string $placa): array
    {
        $placa = self::normalizarPlaca($placa);
        if (! self::placaValida($placa)) {
            return [];
        }

        $variantes = [$placa];
        $mapa = 'ABCDEFGHIJ';

        if (preg_match('/^([A-Z]{3})([0-9])([0-9])([0-9]{2})$/', $placa, $m) === 1) {
            $variantes[] = $m[1].$m[2].$mapa[(int) $m[3]].$m[4];
        } elseif (preg_match('/^([A-Z]{3})([0-9])([A-J])([0-9]{2})$/', $placa, $m) === 1) {
            $digito = strpos($mapa, $m[3]);
            if ($digito !== false) {
                $variantes[] = $m[1].$m[2].$digito.$m[4];
            }
        }

        return array_values(array_unique($variantes));
    }

    /**
     * @param  array<string, mixed>  $vehicle
     */
    public static function vehicleIdentificavel(array $vehicle): bool
    {
        foreach (['brand', 'model', 'chassis'] as $chave) {
            if (trim((string) ($vehicle[$chave] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Busca por placa exata, alternativa ou equivalente Mercosul na mesma empresa.
     * Se houver mais de um cadastro conflitante sem match exato, não escolhe (evita fusão).
     */
    public static function porEmpresaPlaca(int $empresaId, string $placa): ?self
    {
        $placa = self::normalizarPlaca($placa);
        $variantes = self::variantesPlaca($placa);
        if ($empresaId <= 0 || $variantes === []) {
            return null;
        }

        $colunas = [
            'id',
            'placa',
            'descricao',
            'marca',
            'modelo',
            'versao',
            'ano_fabricacao',
            'ano_modelo',
            'cor',
            'chassi',
            'placa_alternativa',
            'combustivel',
            'tipo',
            'especie',
            'carroceria',
            'cidade',
            'uf',
            'renavam',
        ];

        $candidatos = static::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($query) use ($variantes): void {
                $query->whereIn('placa', $variantes)
                    ->orWhereIn('placa_alternativa', $variantes);
            })
            ->get($colunas);

        if ($candidatos->isEmpty()) {
            return null;
        }

        $exato = $candidatos->first(static fn (self $item): bool => self::normalizarPlaca((string) $item->placa) === $placa);
        if ($exato !== null) {
            return $exato;
        }

        $porAlternativa = $candidatos->first(
            static fn (self $item): bool => self::normalizarPlaca((string) ($item->placa_alternativa ?? '')) === $placa,
        );
        if ($porAlternativa !== null) {
            return $porAlternativa;
        }

        // Um único candidato via equivalência Mercosul: reutiliza sem fundir registros.
        return $candidatos->count() === 1 ? $candidatos->first() : null;
    }

    /**
     * Grava o retorno de data.vehicle como snapshot dos campos da consulta.
     * Atualiza o cadastro já existente (incl. equivalente Mercosul) sem criar duplicata.
     * Não persiste fipe_candidates. Não altera OS/orçamentos já gravados.
     *
     * @param  array<string, mixed>  $vehicle
     */
    public static function salvarDaApi(int $empresaId, string $placaConsultada, array $vehicle): self
    {
        $placa = self::placaValida($placaConsultada)
            ? self::normalizarPlaca($placaConsultada)
            : self::normalizarPlaca((string) ($vehicle['plate'] ?? ''));

        if ($empresaId <= 0 || ! self::placaValida($placa)) {
            throw new \InvalidArgumentException('Placa inválida.');
        }

        if (! self::vehicleIdentificavel($vehicle)) {
            throw new \InvalidArgumentException('Retorno de veículo incompleto.');
        }

        $existente = self::porEmpresaPlaca($empresaId, $placa);
        $placaGravacao = $existente !== null
            ? self::normalizarPlaca((string) $existente->placa)
            : $placa;

        $attrs = self::atributosDaApi($vehicle, $placaGravacao);
        $attrs['consultado_em'] = now();

        // Mantém a placa principal do cadastro; a forma digitada vira alternativa se diferente.
        if ($existente !== null && $placaGravacao !== $placa) {
            $altAtual = self::normalizarPlaca((string) ($attrs['placa_alternativa'] ?? ''));
            if ($altAtual === '' || $altAtual === $placaGravacao) {
                $attrs['placa_alternativa'] = $placa;
            }
        }

        $chave = [
            'empresa_id' => $empresaId,
            'placa' => $placaGravacao,
        ];

        try {
            if ($existente !== null) {
                $existente->fill($attrs);
                $existente->save();

                return $existente->fresh() ?? $existente;
            }

            return static::query()->updateOrCreate($chave, $attrs);
        } catch (UniqueConstraintViolationException $exception) {
            $conflito = static::query()->where($chave)->first();
            if ($conflito === null) {
                throw $exception;
            }

            $conflito->fill($attrs);
            $conflito->save();

            return $conflito;
        }
    }

    /**
     * Campos já existentes na OS. Vazio não entra, para não apagar o que está na tela.
     *
     * @return array<string, string>
     */
    public function camposOs(): array
    {
        $fields = [];

        $placa = self::normalizarPlaca((string) $this->placa);
        if (self::placaValida($placa)) {
            $fields['placa'] = $placa;
        }

        $descricao = trim((string) ($this->descricao ?? ''));
        if ($descricao === '') {
            $descricao = trim(implode(' ', array_filter([
                trim((string) ($this->marca ?? '')),
                trim((string) ($this->modelo ?? '')),
                trim((string) ($this->versao ?? '')),
            ], static fn (string $parte): bool => $parte !== '')));
        }
        if ($descricao !== '') {
            $fields['descricao'] = $descricao;
        }

        $modelo = trim((string) ($this->modelo ?? ''));
        if ($modelo !== '') {
            $fields['modelo'] = $modelo;
        }

        $ano = self::anoExibicao((string) ($this->ano_fabricacao ?? ''), (string) ($this->ano_modelo ?? ''));
        if ($ano !== '') {
            $fields['ano'] = $ano;
        }

        $cor = trim((string) ($this->cor ?? ''));
        if ($cor !== '') {
            $fields['cor'] = $cor;
        }

        $chassi = trim((string) ($this->chassi ?? ''));
        if ($chassi !== '') {
            $fields['chassi'] = $chassi;
        }

        return $fields;
    }

    /**
     * Dados extras da aba Equipamento. Não inclui segmento, origem nem nacionalidade.
     *
     * @return array<string, string>
     */
    public function extrasEquipamento(): array
    {
        $extras = [];
        $guardar = static function (array &$destino, string $chave, string $valor): void {
            $valor = trim($valor);
            if ($valor !== '') {
                $destino[$chave] = $valor;
            }
        };

        $guardar($extras, 'versao', (string) ($this->versao ?? ''));
        $guardar($extras, 'combustivel', (string) ($this->combustivel ?? ''));
        $guardar($extras, 'tipo_especie', self::juntarPartes((string) ($this->tipo ?? ''), (string) ($this->especie ?? '')));
        $guardar($extras, 'carroceria', (string) ($this->carroceria ?? ''));
        $guardar($extras, 'cidade_uf', self::juntarLocal((string) ($this->cidade ?? ''), (string) ($this->uf ?? '')));
        $guardar($extras, 'placa_alternativa', (string) ($this->placa_alternativa ?? ''));
        $guardar($extras, 'renavam', (string) ($this->renavam ?? ''));

        return $extras;
    }

    public function anoLista(): string
    {
        return self::anoExibicao((string) ($this->ano_fabricacao ?? ''), (string) ($this->ano_modelo ?? ''));
    }

    public static function anoExibicao(string $fabricacao, string $modelo): string
    {
        $fabricacao = trim($fabricacao);
        $modelo = trim($modelo);

        if ($fabricacao !== '' && $modelo !== '') {
            return $fabricacao.'/'.$modelo;
        }

        return $fabricacao !== '' ? $fabricacao : $modelo;
    }

    private static function juntarPartes(string $esquerda, string $direita): string
    {
        $esquerda = trim($esquerda);
        $direita = trim($direita);

        if ($esquerda !== '' && $direita !== '') {
            return $esquerda.' / '.$direita;
        }

        return $esquerda !== '' ? $esquerda : $direita;
    }

    private static function juntarLocal(string $cidade, string $uf): string
    {
        $cidade = trim($cidade);
        $uf = trim($uf);

        if ($cidade !== '' && $uf !== '') {
            return $cidade.'/'.$uf;
        }

        return $cidade !== '' ? $cidade : $uf;
    }

    /**
     * @param  array<string, mixed>  $vehicle
     * @return array<string, string|null>
     */
    private static function atributosDaApi(array $vehicle, string $placaConsultada): array
    {
        $texto = static function (mixed $valor): string {
            return trim((string) ($valor ?? ''));
        };
        $ano = static function (mixed $valor) use ($texto): string {
            $bruto = $texto($valor);
            if ($bruto === '' || $bruto === '0') {
                return '';
            }
            if (preg_match('/^(\d{4})/', $bruto, $match) === 1) {
                return $match[1];
            }

            return $bruto;
        };
        $up = static function (string $valor): string {
            return $valor === '' ? '' : mb_strtoupper($valor, 'UTF-8');
        };

        $marca = $up($texto($vehicle['brand'] ?? null));
        $modelo = $up($texto($vehicle['model'] ?? null));
        $versao = $up($texto($vehicle['version'] ?? null));
        $descricao = trim(implode(' ', array_filter(
            [$marca, $modelo, $versao],
            static fn (string $parte): bool => $parte !== '',
        )));

        $alternativa = self::normalizarPlaca($texto($vehicle['alternative_plate'] ?? null));
        $placaApi = self::normalizarPlaca($texto($vehicle['plate'] ?? null));
        if ($alternativa === '' && $placaApi !== '' && $placaApi !== $placaConsultada && self::placaValida($placaApi)) {
            $alternativa = $placaApi;
        }
        if ($alternativa === $placaConsultada || ! self::placaValida($alternativa)) {
            $alternativa = '';
        }

        $uf = $up($texto($vehicle['state'] ?? null));
        if (strlen($uf) > 2) {
            $uf = substr($uf, 0, 2);
        }

        $corte = static function (string $valor, int $max): string {
            return mb_strlen($valor, 'UTF-8') > $max ? mb_substr($valor, 0, $max, 'UTF-8') : $valor;
        };

        $nullable = static function (string $valor, int $max) use ($corte): ?string {
            $valor = trim($valor);
            if ($valor === '') {
                return null;
            }

            return $corte($valor, $max);
        };

        // Snapshot completo dos campos da API: vazio limpa valor antigo no mesmo cadastro da placa.
        return [
            'placa_alternativa' => $nullable($alternativa, 10),
            'descricao' => $nullable($descricao, 160),
            'marca' => $nullable($marca, 80),
            'modelo' => $nullable($modelo, 80),
            'submodelo' => $nullable($up($texto($vehicle['submodel'] ?? null)), 80),
            'versao' => $nullable($versao, 80),
            'ano_fabricacao' => $nullable($ano($vehicle['manufacture_year'] ?? null), 4),
            'ano_modelo' => $nullable($ano($vehicle['model_year'] ?? null), 4),
            'cidade' => $nullable($up($texto($vehicle['city'] ?? null)), 80),
            'uf' => $nullable($uf, 2),
            'renavam' => $nullable($up($texto($vehicle['renavam'] ?? null)), 20),
            'chassi' => $nullable($up($texto($vehicle['chassis'] ?? null)), 30),
            'cor' => $nullable($up($texto($vehicle['color'] ?? null)), 40),
            'combustivel' => $nullable($up($texto($vehicle['fuel'] ?? null)), 40),
            'tipo' => $nullable($up($texto($vehicle['type'] ?? null)), 60),
            'especie' => $nullable($up($texto($vehicle['kind'] ?? null)), 60),
            'carroceria' => $nullable($up($texto($vehicle['body_type'] ?? null)), 60),
            'origem' => $nullable($up($texto($vehicle['origin'] ?? null)), 60),
            'nacionalidade' => $nullable($up($texto($vehicle['nationality'] ?? null)), 60),
            'segmento' => $nullable($up($texto($vehicle['segment'] ?? null)), 60),
            'subsegmento' => $nullable($up($texto($vehicle['subsegment'] ?? null)), 60),
        ];
    }
}
