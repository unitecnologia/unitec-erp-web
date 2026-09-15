<?php

namespace App\Http\Controllers\Api\UnitecOs;

use App\Models\OrdemServico;
use App\Models\Person;
use App\Models\User;
use App\Support\UnitecOs\OrdemServicoApiPayload;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdemServicoController
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $this->scopedQuery($user);
        if ($query === null) {
            return response()->json(['data' => []]);
        }

        $itens = $query
            ->with(['atendente', 'cliente', 'itens'])
            ->orderByDesc('data_inicio')
            ->orderByDesc('id')
            ->get()
            ->map(static fn (OrdemServico $os): array => OrdemServicoApiPayload::from($os))
            ->values();

        return response()->json([
            'data' => $itens,
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $this->scopedQuery($user);
        if ($query === null) {
            return response()->json(['message' => 'OS não encontrada.'], 404);
        }

        $os = $query
            ->with(['atendente', 'cliente', 'itens'])
            ->whereKey($id)
            ->first();

        if (! $os instanceof OrdemServico) {
            return response()->json(['message' => 'OS não encontrada.'], 404);
        }

        return response()->json([
            'data' => OrdemServicoApiPayload::from($os),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $empresaId = (int) ($user->empresa_id ?? 0);
        $vendedorId = $user->vendedor_id ? (int) $user->vendedor_id : null;

        if ($empresaId <= 0) {
            throw ValidationException::withMessages([
                'empresa_id' => 'Usuário sem empresa. Não é possível criar OS.',
            ]);
        }

        if ($vendedorId === null) {
            throw ValidationException::withMessages([
                'tecnico' => 'Usuário sem técnico/vendedor vinculado.',
            ]);
        }

        if (trim((string) $request->input('app_local_uuid', '')) === '') {
            $request->merge(['app_local_uuid' => null]);
        }
        if (trim((string) $request->input('device_uuid', '')) === '') {
            $request->merge(['device_uuid' => null]);
        }

        $data = $request->validate([
            'app_local_uuid' => ['nullable', 'uuid'],
            'device_uuid' => ['nullable', 'string', 'max:120'],
            'cliente_id' => ['nullable', 'integer', 'exists:people,id'],
            'cliente' => ['required', 'string', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'string', 'max:120'],
            'cpf_cnpj' => ['nullable', 'string', 'max:20'],
            'nome_fantasia' => ['nullable', 'string', 'max:255'],
            'cep' => ['nullable', 'string', 'max:12'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:120'],
            'cidade' => ['nullable', 'string', 'max:120'],
            'uf' => ['nullable', 'string', 'max:2'],
            'equipamento' => ['nullable', 'string', 'max:255'],
            'problema' => ['nullable', 'string', 'max:5000'],
        ]);

        $nome = mb_strtoupper(trim((string) $data['cliente']), 'UTF-8');
        $fantasia = mb_strtoupper(trim((string) ($data['nome_fantasia'] ?? '')), 'UTF-8');
        $telefone = trim((string) ($data['telefone'] ?? ''));
        $email = mb_strtolower(trim((string) ($data['email'] ?? '')), 'UTF-8');
        $docDigits = preg_replace('/\D/', '', (string) ($data['cpf_cnpj'] ?? '')) ?? '';
        $cepDigits = preg_replace('/\D/', '', (string) ($data['cep'] ?? '')) ?? '';
        $endereco = mb_strtoupper(trim((string) ($data['endereco'] ?? '')), 'UTF-8');
        $numero = trim((string) ($data['numero'] ?? ''));
        $bairro = mb_strtoupper(trim((string) ($data['bairro'] ?? '')), 'UTF-8');
        $cidade = mb_strtoupper(trim((string) ($data['cidade'] ?? '')), 'UTF-8');
        $uf = mb_strtoupper(trim((string) ($data['uf'] ?? '')), 'UTF-8');
        $equipamento = mb_strtoupper(trim((string) ($data['equipamento'] ?? '')), 'UTF-8');
        $problema = trim((string) ($data['problema'] ?? ''));
        $cpfCnpj = $this->formatCpfCnpj($docDigits);
        $cep = $this->formatCep($cepDigits);

        if ($nome === '') {
            throw ValidationException::withMessages([
                'cliente' => 'Informe o cliente.',
            ]);
        }

        if ($docDigits !== '' && ! in_array(strlen($docDigits), [11, 14], true)) {
            throw ValidationException::withMessages([
                'cpf_cnpj' => 'Informe um CPF (11) ou CNPJ (14) válido.',
            ]);
        }

        $appLocalUuid = isset($data['app_local_uuid'])
            ? strtolower((string) $data['app_local_uuid'])
            : null;
        $deviceUuid = isset($data['device_uuid'])
            ? trim((string) $data['device_uuid'])
            : null;

        if ($appLocalUuid !== null) {
            $existente = $this->osDoApp($empresaId, $appLocalUuid);
            if ($existente !== null) {
                return $this->respostaOsCriada($existente, false);
            }
        }

        try {
            $os = $this->gravarOsDoApp(
                $data,
                $user,
                $empresaId,
                $vendedorId,
                $nome,
                $fantasia,
                $telefone,
                $email,
                $docDigits,
                $cpfCnpj,
                $cep,
                $endereco,
                $numero,
                $bairro,
                $cidade,
                $uf,
                $equipamento,
                $problema,
                $appLocalUuid,
                $deviceUuid,
            );
        } catch (UniqueConstraintViolationException $e) {
            if ($appLocalUuid === null) {
                throw $e;
            }

            $existente = $this->osDoApp($empresaId, $appLocalUuid);
            if ($existente === null) {
                throw $e;
            }

            return $this->respostaOsCriada($existente, false);
        }

        return $this->respostaOsCriada($os, (bool) $os->getAttribute('_cliente_criado'), 201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function gravarOsDoApp(
        array $data,
        User $user,
        int $empresaId,
        int $vendedorId,
        string $nome,
        string $fantasia,
        string $telefone,
        string $email,
        string $docDigits,
        string $cpfCnpj,
        string $cep,
        string $endereco,
        string $numero,
        string $bairro,
        string $cidade,
        string $uf,
        string $equipamento,
        string $problema,
        ?string $appLocalUuid,
        ?string $deviceUuid,
    ): OrdemServico {
        return DB::transaction(function () use (
            $data,
            $user,
            $empresaId,
            $vendedorId,
            $nome,
            $fantasia,
            $telefone,
            $email,
            $docDigits,
            $cpfCnpj,
            $cep,
            $endereco,
            $numero,
            $bairro,
            $cidade,
            $uf,
            $equipamento,
            $problema,
            $appLocalUuid,
            $deviceUuid,
        ): OrdemServico {
            $clienteId = ! empty($data['cliente_id']) ? (int) $data['cliente_id'] : null;
            $person = null;

            if ($clienteId) {
                $person = Person::query()
                    ->whereKey($clienteId)
                    ->where('ativo', true)
                    ->where('is_cliente', true)
                    ->first();
            }

            if (! $person instanceof Person && $docDigits !== '') {
                $person = Person::query()
                    ->where('ativo', true)
                    ->where('is_cliente', true)
                    ->whereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') = ?",
                        [$docDigits]
                    )
                    ->first();
            }

            if (! $person instanceof Person) {
                $person = Person::query()
                    ->where('ativo', true)
                    ->where('is_cliente', true)
                    ->whereRaw('UPPER(TRIM(nome_razao)) = ?', [$nome])
                    ->first();
            }

            $clienteCriado = false;
            $pessoaTipo = strlen($docDigits) === 14
                ? Person::PESSOA_JURIDICA
                : Person::PESSOA_FISICA;

            if (! $person instanceof Person) {
                $person = Person::query()->create([
                    'codigo' => Person::nextCodigo(),
                    'pessoa_tipo' => $pessoaTipo,
                    'nome_razao' => $nome,
                    'apelido_fantasia' => $fantasia !== '' ? $fantasia : null,
                    'cpf_cnpj' => $cpfCnpj !== '' ? $cpfCnpj : null,
                    'cep' => $cep !== '' ? $cep : null,
                    'endereco' => $endereco !== '' ? $endereco : null,
                    'numero' => $numero !== '' ? $numero : null,
                    'bairro' => $bairro !== '' ? $bairro : null,
                    'cidade_nome' => $cidade !== '' ? $cidade : null,
                    'uf' => $uf !== '' ? $uf : null,
                    'email' => $email !== '' ? $email : null,
                    'fone1' => $telefone !== '' ? $telefone : null,
                    'is_cliente' => true,
                    'ativo' => true,
                ]);
                $clienteCriado = true;
            } else {
                $updates = [];
                if ($fantasia !== '' && blank($person->apelido_fantasia)) {
                    $updates['apelido_fantasia'] = $fantasia;
                }
                if ($telefone !== '' && blank($person->fone1)) {
                    $updates['fone1'] = $telefone;
                }
                if ($email !== '' && blank($person->email)) {
                    $updates['email'] = $email;
                }
                if ($cpfCnpj !== '' && blank($person->cpf_cnpj)) {
                    $updates['cpf_cnpj'] = $cpfCnpj;
                    $updates['pessoa_tipo'] = $pessoaTipo;
                }
                if ($cep !== '' && blank($person->cep)) {
                    $updates['cep'] = $cep;
                }
                if ($endereco !== '' && blank($person->endereco)) {
                    $updates['endereco'] = $endereco;
                }
                if ($numero !== '' && blank($person->numero)) {
                    $updates['numero'] = $numero;
                }
                if ($bairro !== '' && blank($person->bairro)) {
                    $updates['bairro'] = $bairro;
                }
                if ($cidade !== '' && blank($person->cidade_nome)) {
                    $updates['cidade_nome'] = $cidade;
                }
                if ($uf !== '' && blank($person->uf)) {
                    $updates['uf'] = $uf;
                }
                if ($updates !== []) {
                    $person->forceFill($updates)->save();
                }
            }

            $agora = now();
            $logradouroOs = $endereco !== ''
                ? $endereco.($numero !== '' ? ', '.$numero : '')
                : (mb_strtoupper(trim((string) ($person->endereco ?? '')), 'UTF-8')
                    .($person->numero ? ', '.$person->numero : ''));
            $logradouroOs = trim($logradouroOs, ' ,');

            $os = OrdemServico::query()->create([
                'empresa_id' => $empresaId,
                'app_local_uuid' => $appLocalUuid,
                'device_uuid' => $deviceUuid,
                'numero' => OrdemServico::nextNumero(),
                'situacao' => OrdemServico::SITUACAO_ABERTA,
                'data_inicio' => $agora->toDateString(),
                // hora_inicio é gravada ao Iniciar atendimento (não na abertura).
                'hora_inicio' => null,
                'cliente_id' => $person->id,
                'atendente_id' => $vendedorId,
                'usuario_id' => $user->id,
                'nome' => $nome,
                'documento' => $cpfCnpj !== '' ? $cpfCnpj : ($person->cpf_cnpj ?: null),
                'fone1' => $telefone !== '' ? $telefone : ($person->fone1 ?: null),
                'endereco' => $logradouroOs !== '' ? $logradouroOs : null,
                'bairro' => $bairro !== '' ? $bairro : (mb_strtoupper((string) ($person->bairro ?? ''), 'UTF-8') ?: null),
                'cidade' => $cidade !== '' ? $cidade : (mb_strtoupper((string) ($person->cidade_nome ?? ''), 'UTF-8') ?: null),
                'uf' => $uf !== '' ? $uf : (mb_strtoupper((string) ($person->uf ?? ''), 'UTF-8') ?: null),
                'descricao' => $equipamento !== '' ? $equipamento : null,
                'problema' => $problema !== '' ? $problema : null,
            ]);

            $os->setAttribute('_cliente_criado', $clienteCriado);

            return $os;
        });
    }

    private function osDoApp(int $empresaId, string $appLocalUuid): ?OrdemServico
    {
        return OrdemServico::query()
            ->where('empresa_id', $empresaId)
            ->where('app_local_uuid', $appLocalUuid)
            ->first();
    }

    private function respostaOsCriada(OrdemServico $os, bool $clienteCriado, int $status = 200): JsonResponse
    {
        $os->load(['atendente', 'cliente', 'itens']);

        return response()->json([
            'data' => OrdemServicoApiPayload::from($os),
            'cliente_criado' => $clienteCriado,
            'message' => 'OS criada com sucesso.',
        ], $status);
    }

    private function formatCpfCnpj(string $digits): string
    {
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3).'.'.substr($digits, 3, 3).'.'.substr($digits, 6, 3).'-'.substr($digits, 9, 2);
        }

        if (strlen($digits) === 14) {
            return substr($digits, 0, 2).'.'.substr($digits, 2, 3).'.'.substr($digits, 5, 3).'/'.substr($digits, 8, 4).'-'.substr($digits, 12, 2);
        }

        return $digits;
    }

    private function formatCep(string $digits): string
    {
        if (strlen($digits) !== 8) {
            return $digits;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5, 3);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = $this->scopedQuery($user);
        if ($query === null) {
            return response()->json(['message' => 'OS não encontrada.'], 404);
        }

        /** @var OrdemServico|null $os */
        $os = $query->whereKey($id)->first();
        if (! $os instanceof OrdemServico) {
            return response()->json(['message' => 'OS não encontrada.'], 404);
        }

        $data = $request->validate([
            'status' => ['nullable', 'string', 'max:40'],
            'hora_inicio' => ['nullable', 'string', 'max:8'],
            'servico_realizado' => ['nullable', 'string', 'max:10000'],
            'observacoes' => ['nullable', 'string', 'max:10000'],
            'pecas' => ['nullable', 'array'],
            'pecas.*' => ['nullable'],
            'servicos' => ['nullable', 'array'],
            'servicos.*' => ['nullable'],
            'iniciar_atendimento' => ['nullable', 'boolean'],
            'finalizar' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($os, $data, $user): void {
            $agora = now();
            $empresaId = (int) ($user->empresa_id ?? 0);
            $vendedorId = $user->vendedor_id ? (int) $user->vendedor_id : null;

            if (! empty($data['iniciar_atendimento'])) {
                if ($empresaId <= 0) {
                    throw ValidationException::withMessages([
                        'empresa_id' => 'Usuário sem empresa. Não é possível iniciar atendimento.',
                    ]);
                }

                if ($vendedorId === null) {
                    throw ValidationException::withMessages([
                        'tecnico' => 'Usuário sem técnico/vendedor vinculado.',
                    ]);
                }

                if ($os->situacao === OrdemServico::SITUACAO_ABERTA) {
                    $os->situacao = OrdemServico::SITUACAO_ANDAMENTO;
                }

                // Se já iniciada, não cria novo horário.
                if (blank($os->hora_inicio)) {
                    $os->hora_inicio = $agora->format('H:i:s');
                }
                if (blank($os->data_inicio)) {
                    $os->data_inicio = $agora->toDateString();
                }

                $os->atendente_id = $vendedorId;
            }

            // Não sobrescreve horário de início já gravado.
            if (
                array_key_exists('hora_inicio', $data)
                && filled($data['hora_inicio'])
                && blank($os->hora_inicio)
            ) {
                $hora = trim((string) $data['hora_inicio']);
                $os->hora_inicio = strlen($hora) === 5 ? $hora.':00' : $hora;
            }

            if (array_key_exists('status', $data) && filled($data['status']) && empty($data['finalizar'])) {
                $situacao = OrdemServicoApiPayload::situacaoFromApp((string) $data['status']);
                if ($situacao !== null && ! self::osJaFaturada($os)) {
                    $os->situacao = $situacao;
                }
            }

            if (array_key_exists('servico_realizado', $data)) {
                $os->laudo = trim((string) ($data['servico_realizado'] ?? '')) ?: null;
            }

            if (array_key_exists('observacoes', $data)) {
                $os->observacoes = trim((string) ($data['observacoes'] ?? '')) ?: null;
            }

            $os->usuario_id = $user->id;
            $os->save();

            $vendedorId = $user->vendedor_id ? (int) $user->vendedor_id : null;

            if (array_key_exists('pecas', $data)) {
                $os->itens()->where('tipo', 'P')->delete();
                self::gravarItensCatalogo($os, $user, $vendedorId, (array) $data['pecas'], 'P', permitirPrecoManual: false);
            }

            if (array_key_exists('servicos', $data)) {
                $os->itens()->where('tipo', 'S')->delete();
                self::gravarItensCatalogo($os, $user, $vendedorId, (array) $data['servicos'], 'S', permitirPrecoManual: true);
            }

            if (array_key_exists('pecas', $data) || array_key_exists('servicos', $data)) {
                self::recalcularTotaisOs($os);
            }

            if (! empty($data['finalizar'])) {
                if ($empresaId <= 0) {
                    throw ValidationException::withMessages([
                        'empresa_id' => 'Usuário sem empresa. Não é possível finalizar a OS.',
                    ]);
                }

                if ($vendedorId === null) {
                    throw ValidationException::withMessages([
                        'tecnico' => 'Usuário sem técnico/vendedor vinculado.',
                    ]);
                }

                $os->refresh();

                if (self::osJaFaturada($os)) {
                    return;
                }

                $atendimentoIniciado = filled($os->hora_inicio)
                    || in_array($os->situacao, [
                        OrdemServico::SITUACAO_ANDAMENTO,
                        OrdemServico::SITUACAO_ABERTA,
                    ], true);

                if (! $atendimentoIniciado) {
                    throw ValidationException::withMessages([
                        'atendimento' => 'Inicie o atendimento antes de enviar a OS para faturamento.',
                    ]);
                }

                $qtdServicos = $os->itens()->where('tipo', 'S')->count();
                if ($qtdServicos < 1) {
                    throw ValidationException::withMessages([
                        'servicos' => 'Informe pelo menos um serviço realizado antes de enviar para faturamento.',
                    ]);
                }

                // No ERP fica aberta para o faturamento. O app lê data_termino como "em faturamento".
                $os->situacao = OrdemServico::SITUACAO_ABERTA;
                if (blank($os->data_termino)) {
                    $os->data_termino = $agora->toDateString();
                }
                if (blank($os->hora_termino)) {
                    $os->hora_termino = $agora->format('H:i:s');
                }
                $os->usuario_id = $user->id;
                $os->save();
            }
        });

        $os->refresh()->load(['atendente', 'cliente', 'itens.product']);

        return response()->json([
            'data' => OrdemServicoApiPayload::from($os),
            'message' => 'OS atualizada.',
        ]);
    }

    /**
     * @param  list<mixed>  $itens
     */
    private static function osJaFaturada(OrdemServico $os): bool
    {
        return in_array($os->situacao, [
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_ENTREGUE,
        ], true);
    }

    /**
     * @param  list<mixed>  $itens
     */
    private static function gravarItensCatalogo(
        OrdemServico $os,
        User $user,
        ?int $vendedorId,
        array $itens,
        string $tipo,
        bool $permitirPrecoManual,
    ): void {
        foreach ($itens as $item) {
            $productId = null;
            $nome = '';
            $preco = 0.0;
            $qtd = 1.0;
            $precoInformado = false;

            $desconto = 0.0;

            if (is_array($item)) {
                $productId = isset($item['produto_id'])
                    ? (int) $item['produto_id']
                    : (isset($item['id']) ? (int) $item['id'] : null);
                $nome = trim((string) ($item['descricao'] ?? $item['nome'] ?? ''));
                if (array_key_exists('preco', $item) && $item['preco'] !== null && $item['preco'] !== '') {
                    $preco = (float) $item['preco'];
                    $precoInformado = true;
                }
                if (isset($item['qtd']) && is_numeric($item['qtd'])) {
                    $qtd = max(0.001, (float) $item['qtd']);
                }
                if (isset($item['desconto']) && is_numeric($item['desconto'])) {
                    $desconto = max(0.0, (float) $item['desconto']);
                }
            } else {
                $nome = trim((string) $item);
            }

            if ($productId && $productId > 0) {
                $product = \App\Models\Product::query()->whereKey($productId)->first();
                if ($product) {
                    $nome = trim((string) ($product->descricao ?: $nome));
                    if (! $precoInformado || ! $permitirPrecoManual) {
                        $empresaId = (int) ($os->empresa_id ?? $user->empresa_id ?? 0);
                        $preco = app(\App\Support\Erp\ProductEmpresaPrecoService::class)
                            ->resolvePrecoVenda($product, $empresaId > 0 ? $empresaId : null);
                    }
                } else {
                    $productId = null;
                }
            }

            $nome = mb_strtoupper($nome, 'UTF-8');
            if ($nome === '') {
                continue;
            }

            $bruto = round($preco * $qtd, 2);
            $desconto = min($desconto, $bruto);
            $total = round(max(0.0, $bruto - $desconto), 2);

            $os->itens()->create([
                'empresa_id' => $os->empresa_id,
                'usuario_id' => $user->id,
                'funcionario_id' => $vendedorId,
                'product_id' => $productId,
                'tipo' => $tipo,
                'discriminacao' => $nome,
                'nome' => $nome,
                'qtd' => $qtd,
                'preco' => $preco,
                'desconto' => $desconto,
                'total' => $total,
            ]);
        }
    }

    private static function recalcularTotaisOs(OrdemServico $os): void
    {
        $os->load('itens');
        $pecas = $os->itens->where('tipo', 'P');
        $servicos = $os->itens->where('tipo', 'S');

        $subPecas = round((float) $pecas->sum(static fn ($i) => (float) $i->qtd * (float) $i->preco), 2);
        $subServicos = round((float) $servicos->sum(static fn ($i) => (float) $i->qtd * (float) $i->preco), 2);
        $descPecas = round((float) $pecas->sum('desconto'), 2);
        $descServicos = round((float) $servicos->sum('desconto'), 2);
        $totalPecas = round(max(0, $subPecas - $descPecas), 2);
        $totalServicos = round(max(0, $subServicos - $descServicos), 2);

        $os->forceFill([
            'subtotal' => round($subPecas + $subServicos, 2),
            'subtotal_pecas' => $subPecas,
            'subtotal_servicos' => $subServicos,
            'vl_desc_pecas' => $descPecas,
            'vl_desc_servicos' => $descServicos,
            'total_produtos' => $totalPecas,
            'total_servicos' => $totalServicos,
            'total_geral' => round($totalPecas + $totalServicos, 2),
        ])->save();
    }

    /**
     * Escopo obrigatório: mesma empresa da sessão + mesmo técnico/vendedor.
     * Sem empresa ou sem vendedor → nada acessível.
     */
    private function scopedQuery(User $user): ?Builder
    {
        $empresaId = (int) ($user->empresa_id ?? 0);
        $vendedorId = $user->vendedor_id ? (int) $user->vendedor_id : null;

        if ($empresaId <= 0 || $vendedorId === null) {
            return null;
        }

        return OrdemServico::query()
            ->where('empresa_id', $empresaId)
            ->where(function (Builder $q) use ($vendedorId): void {
                $q->where('atendente_id', $vendedorId)
                    ->orWhereHas('itens', function (Builder $itens) use ($vendedorId): void {
                        $itens->where('funcionario_id', $vendedorId);
                    });
            });
    }
}
