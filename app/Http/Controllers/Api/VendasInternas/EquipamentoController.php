<?php

namespace App\Http\Controllers\Api\VendasInternas;

use App\Models\Empresa;
use App\Models\OsVeiculo;
use App\Models\User;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\Os\ConsultaPlacaOsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Cadastro/consulta de equipamentos (os_veiculos) para o App Vendas Internas.
 * Sem schema novo — mesma tabela usada por OS, Orçamento e NFS-e.
 */
class EquipamentoController
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $this->assertEquipamentoHabilitado($empresaId);
        $q = mb_strtoupper(trim((string) $request->query('q', '')), 'UTF-8');
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        $query = OsVeiculo::query()
            ->where('empresa_id', $empresaId)
            ->orderBy('descricao')
            ->orderBy('placa');

        if (mb_strlen($q) >= 2) {
            $like = '%'.$q.'%';
            $placa = OsVeiculo::normalizarPlaca($q);
            $query->where(function ($builder) use ($like, $placa): void {
                $builder->where('descricao', 'like', $like)
                    ->orWhere('marca', 'like', $like)
                    ->orWhere('modelo', 'like', $like)
                    ->orWhere('versao', 'like', $like);

                if ($placa !== '') {
                    $builder->orWhere('placa', 'like', '%'.$placa.'%')
                        ->orWhere('placa_alternativa', 'like', '%'.$placa.'%');
                }
            });
        }

        $items = $query->limit($limit)->get()->map(fn (OsVeiculo $v): array => $this->serialize($v))->all();

        return response()->json(['equipamentos' => $items]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $this->assertEquipamentoHabilitado($empresaId);
        $veiculo = OsVeiculo::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($id)
            ->first();

        if ($veiculo === null) {
            return response()->json(['message' => 'Equipamento não encontrado.'], 404);
        }

        return response()->json(['equipamento' => $this->serialize($veiculo)]);
    }

    public function store(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $this->assertEquipamentoHabilitado($empresaId);
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'placa' => ['required', 'string', 'max:15'],
            'descricao' => ['nullable', 'string', 'max:160'],
            'marca' => ['nullable', 'string', 'max:80'],
            'modelo' => ['nullable', 'string', 'max:80'],
            'versao' => ['nullable', 'string', 'max:80'],
            'ano_fabricacao' => ['nullable', 'string', 'max:4'],
            'ano_modelo' => ['nullable', 'string', 'max:4'],
            'cor' => ['nullable', 'string', 'max:40'],
            'chassi' => ['nullable', 'string', 'max:30'],
            'renavam' => ['nullable', 'string', 'max:20'],
            'combustivel' => ['nullable', 'string', 'max:40'],
            'cidade' => ['nullable', 'string', 'max:80'],
            'uf' => ['nullable', 'string', 'max:2'],
            'placa_alternativa' => ['nullable', 'string', 'max:10'],
            'tipo' => ['nullable', 'string', 'max:60'],
            'especie' => ['nullable', 'string', 'max:60'],
            'carroceria' => ['nullable', 'string', 'max:60'],
            // Snapshot do orçamento (não persistem em os_veiculos).
            'numero_serie' => ['nullable', 'string', 'max:60'],
            'descricao2' => ['nullable', 'string', 'max:150'],
            'km' => ['nullable'],
            'ano' => ['nullable', 'string', 'max:10'],
        ]);

        $placa = OsVeiculo::normalizarPlaca((string) $data['placa']);
        if (! OsVeiculo::placaValida($placa)) {
            throw ValidationException::withMessages([
                'placa' => 'Informe uma placa válida (ABC1234 ou ABC1D23).',
            ]);
        }

        $id = (int) ($data['id'] ?? 0);
        $veiculo = null;
        if ($id > 0) {
            $veiculo = OsVeiculo::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($id)
                ->first();
            if ($veiculo === null) {
                return response()->json(['message' => 'Equipamento não encontrado.'], 404);
            }
        } else {
            $veiculo = OsVeiculo::porEmpresaPlaca($empresaId, $placa);
        }

        $attrs = $this->attrsCadastro($data, $placa);
        if ($veiculo !== null) {
            $veiculo->fill($attrs);
            $veiculo->save();
        } else {
            $veiculo = OsVeiculo::query()->create(array_merge(
                ['empresa_id' => $empresaId, 'placa' => $placa],
                $attrs,
            ));
        }

        return response()->json([
            'equipamento' => $this->serialize($veiculo->fresh() ?? $veiculo, $data),
        ]);
    }

    /**
     * Consulta Verifica Online (ou cadastro local). Não grava automaticamente —
     * o app confirma via POST /equipamentos.
     */
    public function consultarPlaca(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $this->assertEquipamentoHabilitado($empresaId);
        $data = $request->validate([
            'placa' => ['required', 'string', 'max:15'],
            'forcar_externo' => ['nullable', 'boolean'],
        ]);

        $placa = OsVeiculo::normalizarPlaca((string) $data['placa']);
        if (! OsVeiculo::placaValida($placa)) {
            throw ValidationException::withMessages([
                'placa' => 'Informe uma placa válida (ABC1234 ou ABC1D23).',
            ]);
        }

        $forcar = (bool) ($data['forcar_externo'] ?? false);
        $local = OsVeiculo::porEmpresaPlaca($empresaId, $placa);
        if ($local !== null && ! $forcar) {
            return response()->json([
                'ok' => true,
                'fonte' => 'local',
                'message' => 'Dados do veículo preenchidos pelo cadastro.',
                'equipamento' => $this->serialize($local),
                'preview' => $local->camposOs(),
            ]);
        }

        /** @var User $user */
        $user = $request->user();
        $empresa = Empresa::query()->find($empresaId);
        if ($empresa === null) {
            return response()->json(['ok' => false, 'message' => 'Empresa inválida.'], 422);
        }

        $lockKey = 'vi-consulta-placa:'.$empresaId.':'.$placa;
        $espera = max(15, min(300, (int) ($empresa->param_consulta_placa_timeout ?? 10)) + 5);
        $lock = Cache::lock($lockKey, $espera);
        if (! $lock->get()) {
            return response()->json([
                'ok' => false,
                'message' => 'A consulta desta placa já está em andamento.',
            ], 429);
        }

        try {
            if ($forcar && Cache::has($lockKey.':cooldown')) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Aguarde alguns segundos antes de consultar novamente.',
                ], 429);
            }

            $resultado = app(ConsultaPlacaOsService::class)->consultar($empresa, $placa);
            if (! ($resultado['ok'] ?? false)) {
                return response()->json([
                    'ok' => false,
                    'message' => (string) ($resultado['message'] ?? 'Não foi possível consultar a placa.'),
                    'fields' => $resultado['fields'] ?? [],
                ], 422);
            }

            $vehicle = $resultado['vehicle'] ?? null;
            if (! is_array($vehicle) || ! OsVeiculo::vehicleIdentificavel($vehicle)) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Não foi possível obter os dados do veículo. Tente novamente.',
                ], 422);
            }

            Cache::put($lockKey.':cooldown', 1, now()->addSeconds(20));

            // Preview sem gravar — app confirma o salvamento.
            $fields = is_array($resultado['fields'] ?? null) ? $resultado['fields'] : [];
            $fields['placa'] = $placa;

            return response()->json([
                'ok' => true,
                'fonte' => 'api',
                'message' => (string) ($resultado['message'] ?? 'Dados obtidos. Confira antes de salvar.'),
                'preview' => $fields,
                'vehicle' => $vehicle,
                'equipamento_existente' => $local !== null ? $this->serialize($local) : null,
                'consultado_por' => $user->name,
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * Confirma gravação do retorno da API (após conferência no app).
     */
    public function confirmarConsulta(Request $request): JsonResponse
    {
        $empresaId = $this->empresaId($request);
        $this->assertEquipamentoHabilitado($empresaId);
        $data = $request->validate([
            'placa' => ['required', 'string', 'max:15'],
            'vehicle' => ['required', 'array'],
        ]);

        $placa = OsVeiculo::normalizarPlaca((string) $data['placa']);
        if (! OsVeiculo::placaValida($placa)) {
            throw ValidationException::withMessages([
                'placa' => 'Informe uma placa válida (ABC1234 ou ABC1D23).',
            ]);
        }

        $vehicle = $data['vehicle'];
        if (! OsVeiculo::vehicleIdentificavel($vehicle)) {
            return response()->json(['message' => 'Retorno de veículo incompleto.'], 422);
        }

        $salvo = OsVeiculo::salvarDaApi($empresaId, $placa, $vehicle);

        return response()->json([
            'ok' => true,
            'message' => 'Equipamento salvo no cadastro da empresa.',
            'equipamento' => $this->serialize($salvo),
        ]);
    }

    private function empresaId(Request $request): int
    {
        /** @var User|null $user */
        $user = $request->user();
        $empresaId = $user?->empresa_id ? (int) $user->empresa_id : 0;
        if ($empresaId <= 0) {
            throw ValidationException::withMessages([
                'empresa' => 'Empresa não vinculada à sessão.',
            ]);
        }

        return $empresaId;
    }

    private function assertEquipamentoHabilitado(int $empresaId): void
    {
        if (! EmpresaParametros::vendasInternasHabilitarEquipamento($empresaId)) {
            throw ValidationException::withMessages([
                'equipamento' => 'Equipamento desabilitado para esta empresa no App Vendas Internas.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    private function attrsCadastro(array $data, string $placa): array
    {
        $up = static function (?string $v, int $max): ?string {
            $v = mb_strtoupper(trim((string) ($v ?? '')), 'UTF-8');
            if ($v === '') {
                return null;
            }

            return mb_strlen($v, 'UTF-8') > $max ? mb_substr($v, 0, $max, 'UTF-8') : $v;
        };

        $descricao = $up($data['descricao'] ?? null, 160);
        $marca = $up($data['marca'] ?? null, 80);
        if ($marca === null && $descricao !== null) {
            $marca = mb_substr($descricao, 0, 80, 'UTF-8');
        }

        $alt = OsVeiculo::normalizarPlaca((string) ($data['placa_alternativa'] ?? ''));
        if ($alt === '' || $alt === $placa || ! OsVeiculo::placaValida($alt)) {
            $alt = '';
        }

        return [
            'placa' => $placa,
            'placa_alternativa' => $alt !== '' ? $alt : null,
            'descricao' => $descricao,
            'marca' => $marca,
            'modelo' => $up($data['modelo'] ?? null, 80),
            'versao' => $up($data['versao'] ?? null, 80),
            'ano_fabricacao' => $up($data['ano_fabricacao'] ?? null, 4),
            'ano_modelo' => $up($data['ano_modelo'] ?? null, 4),
            'cor' => $up($data['cor'] ?? null, 40),
            'chassi' => $up($data['chassi'] ?? null, 30),
            'renavam' => $up($data['renavam'] ?? null, 20),
            'combustivel' => $up($data['combustivel'] ?? null, 40),
            'cidade' => $up($data['cidade'] ?? null, 80),
            'uf' => $up($data['uf'] ?? null, 2),
            'tipo' => $up($data['tipo'] ?? null, 60),
            'especie' => $up($data['especie'] ?? null, 60),
            'carroceria' => $up($data['carroceria'] ?? null, 60),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $extrasOrcamento
     * @return array<string, mixed>
     */
    private function serialize(OsVeiculo $v, ?array $extrasOrcamento = null): array
    {
        $ano = $v->anoLista();
        $payload = [
            'id' => (int) $v->id,
            'placa' => (string) $v->placa,
            'placa_alternativa' => $v->placa_alternativa,
            'descricao' => $v->descricao,
            'marca' => $v->marca,
            'modelo' => $v->modelo,
            'versao' => $v->versao,
            'ano' => $ano !== '' ? $ano : null,
            'ano_fabricacao' => $v->ano_fabricacao,
            'ano_modelo' => $v->ano_modelo,
            'cor' => $v->cor,
            'chassi' => $v->chassi,
            'renavam' => $v->renavam,
            'combustivel' => $v->combustivel,
            'cidade' => $v->cidade,
            'uf' => $v->uf,
            'tipo' => $v->tipo,
            'especie' => $v->especie,
            'carroceria' => $v->carroceria,
            'consultado_em' => optional($v->consultado_em)?->toIso8601String(),
        ];

        if ($extrasOrcamento !== null) {
            foreach (['numero_serie', 'descricao2', 'km'] as $k) {
                if (array_key_exists($k, $extrasOrcamento) && $extrasOrcamento[$k] !== null && $extrasOrcamento[$k] !== '') {
                    $payload[$k] = $extrasOrcamento[$k];
                }
            }
            if (! empty($extrasOrcamento['ano'])) {
                $payload['ano'] = $extrasOrcamento['ano'];
            }
        }

        return $payload;
    }
}
