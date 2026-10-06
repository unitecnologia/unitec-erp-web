<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\OsVeiculo;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Os\ConsultaPlacaOsService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * Campos, consulta de placa e cadastro de veículo/equipamento usados pela OS.
 * O orçamento reutiliza o mesmo fluxo e a mesma tabela os_veiculos.
 */
trait ManagesEquipamentoVeiculo
{
    public string $numeroSerie = '';

    public string $descricao = '';

    public bool $equipamentoLookupOpen = false;

    /** @var array<int, array{id: int, placa: string, descricao: string, modelo: string}> */
    public array $equipamentoResults = [];

    public ?int $selectedEquipamentoIndex = null;

    public bool $ignorarBuscaEquipamento = false;

    public string $descricao2 = '';

    public string $modelo = '';

    public string $ano = '';

    public string $placa = '';

    public string $placaLocalResolvida = '';

    public string $km = '';

    public string $corVeiculo = '';

    public string $chassiVeiculo = '';

    public string $veiculoVersao = '';

    public string $veiculoCombustivel = '';

    public string $veiculoTipoEspecie = '';

    public string $veiculoCarroceria = '';

    public string $veiculoCidadeUf = '';

    public string $veiculoPlacaAlternativa = '';

    public string $veiculoRenavam = '';

    protected function equipamentoSomenteLeitura(): bool
    {
        if (method_exists($this, 'osReadOnly')) {
            return $this->osReadOnly();
        }

        if (method_exists($this, 'orcamentoReadOnly')) {
            return $this->orcamentoReadOnly();
        }

        return false;
    }

    public function aplicarVeiculoLocalDaPlaca(?string $placaInformada = null): void
    {
        if ($this->equipamentoSomenteLeitura()) {
            return;
        }

        if ($placaInformada !== null) {
            $this->placa = $placaInformada;
        }

        $placa = OsVeiculo::normalizarPlaca($this->placa);
        if ($placa === $this->placaLocalResolvida) {
            return;
        }

        if (! OsVeiculo::placaValida($placa)) {
            $this->placaLocalResolvida = '';
            $this->lembrarOsVeiculo(null);
            $this->aplicarExtrasVeiculo([]);

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $this->placaLocalResolvida = $placa;
        $local = OsVeiculo::porEmpresaPlaca($empresaId, $placa);
        $this->lembrarOsVeiculo($local);
        if ($local === null) {
            $this->aplicarExtrasVeiculo([]);

            return;
        }

        $this->preencherCamposVeiculoOs($local->camposOs());
        $this->aplicarExtrasVeiculo($local->extrasEquipamento());
    }

    public function consultarPlaca(): void
    {
        if ($this->equipamentoSomenteLeitura()) {
            return;
        }

        $empresa = ErpContext::currentEmpresa();
        if ($empresa === null) {
            Notification::make()->title('Selecione a empresa para consultar a placa.')->warning()->send();

            return;
        }

        $placa = OsVeiculo::normalizarPlaca($this->placa);
        if (! OsVeiculo::placaValida($placa)) {
            $this->lembrarOsVeiculo(null);
            $this->aplicarExtrasVeiculo([]);
            Notification::make()->title('Informe uma placa válida (ABC1234 ou ABC1D23).')->warning()->send();

            return;
        }

        $local = OsVeiculo::porEmpresaPlaca((int) $empresa->id, $placa);
        if ($local !== null) {
            $this->placaLocalResolvida = $placa;
            $this->lembrarOsVeiculo($local);
            $this->preencherCamposVeiculoOs($local->camposOs());
            $this->aplicarExtrasVeiculo($local->extrasEquipamento());
            Notification::make()->title('Dados do veículo preenchidos pelo cadastro.')->success()->send();

            return;
        }

        $this->aplicarExtrasVeiculo([]);

        $espera = max(15, min(300, (int) config('unitec.consulta_placa.timeout', 10)) + 5);
        $lock = Cache::lock('os-consulta-placa:'.$empresa->id.':'.$placa, $espera);
        if (! $lock->get()) {
            Notification::make()->title('A consulta desta placa já está em andamento.')->warning()->send();

            return;
        }

        try {
            $resultado = app(ConsultaPlacaOsService::class)->consultar($empresa, $placa);

            if (! ($resultado['ok'] ?? false)) {
                Notification::make()->title((string) ($resultado['message'] ?? 'Não foi possível consultar a placa.'))->warning()->send();

                return;
            }

            $this->placaLocalResolvida = $placa;
            $this->preencherCamposVeiculoOs($resultado['fields'] ?? []);
            $this->aplicarExtrasVeiculo($resultado['extras'] ?? []);
            $this->lembrarOsVeiculo(OsVeiculo::porEmpresaPlaca((int) $empresa->id, $placa));

            Notification::make()->title((string) $resultado['message'])->success()->send();
        } catch (\Throwable) {
            Notification::make()->title('Não foi possível consultar a placa. Tente novamente.')->warning()->send();
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, string>  $fields
     */
    protected function preencherCamposVeiculoOs(array $fields): void
    {
        foreach ($fields as $campo => $valor) {
            $valor = trim((string) $valor);
            if ($valor === '') {
                continue;
            }

            match ($campo) {
                'placa' => $this->placa = $valor,
                'descricao' => $this->descricao = $valor,
                'modelo' => $this->modelo = $valor,
                'ano' => $this->ano = $valor,
                'cor' => $this->corVeiculo = $valor,
                'chassi' => $this->chassiVeiculo = $valor,
                default => null,
            };
        }
    }

    /**
     * @param  array<string, string>  $extras
     */
    protected function aplicarExtrasVeiculo(array $extras): void
    {
        $this->veiculoVersao = trim((string) ($extras['versao'] ?? ''));
        $this->veiculoCombustivel = trim((string) ($extras['combustivel'] ?? ''));
        $this->veiculoTipoEspecie = trim((string) ($extras['tipo_especie'] ?? ''));
        $this->veiculoCarroceria = trim((string) ($extras['carroceria'] ?? ''));
        $this->veiculoCidadeUf = trim((string) ($extras['cidade_uf'] ?? ''));
        $this->veiculoPlacaAlternativa = trim((string) ($extras['placa_alternativa'] ?? ''));
        $this->veiculoRenavam = trim((string) ($extras['renavam'] ?? ''));
    }

    protected function carregarExtrasVeiculoLocal(): void
    {
        if (property_exists($this, 'osVeiculoId') && $this->osVeiculoId) {
            $vinculado = OsVeiculo::query()->find($this->osVeiculoId);
            if ($vinculado !== null) {
                $this->aplicarExtrasVeiculo($vinculado->extrasEquipamento());

                return;
            }
        }

        $placa = OsVeiculo::normalizarPlaca($this->placa);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0 || ! OsVeiculo::placaValida($placa)) {
            $this->aplicarExtrasVeiculo([]);

            return;
        }

        $local = OsVeiculo::porEmpresaPlaca($empresaId, $placa);
        $this->lembrarOsVeiculo($local);
        $this->aplicarExtrasVeiculo($local?->extrasEquipamento() ?? []);
    }

    public function updatedDescricao(string $value): void
    {
        if ($this->equipamentoSomenteLeitura() || $this->ignorarBuscaEquipamento) {
            $this->ignorarBuscaEquipamento = false;

            return;
        }

        $upper = mb_strtoupper($value, 'UTF-8');
        if ($this->descricao !== $upper) {
            $this->descricao = $upper;
        }

        $this->buscarEquipamentoCadastrado();
    }

    public function openEquipamentoLookup(): void
    {
        if ($this->equipamentoSomenteLeitura()) {
            return;
        }

        $this->buscarEquipamentoCadastrado();
    }

    public function closeEquipamentoLookup(): void
    {
        $this->equipamentoLookupOpen = false;
        $this->equipamentoResults = [];
        $this->selectedEquipamentoIndex = null;
    }

    public function moveEquipamentoSelection(int $delta): void
    {
        if ($this->equipamentoResults === []) {
            return;
        }

        $index = ($this->selectedEquipamentoIndex ?? 0) + $delta;
        $this->selectedEquipamentoIndex = max(0, min(count($this->equipamentoResults) - 1, $index));
    }

    public function selectEquipamentoResult(int $index): void
    {
        if (! isset($this->equipamentoResults[$index])) {
            return;
        }

        $this->selectedEquipamentoIndex = $index;
        $this->confirmEquipamentoSelection();
    }

    public function confirmEquipamentoSelection(): void
    {
        $index = $this->selectedEquipamentoIndex;
        if ($index === null || ! isset($this->equipamentoResults[$index])) {
            $this->closeEquipamentoLookup();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $veiculo = OsVeiculo::query()
            ->where('empresa_id', $empresaId)
            ->whereKey($this->equipamentoResults[$index]['id'])
            ->first();

        if ($veiculo === null) {
            $this->closeEquipamentoLookup();

            return;
        }

        $this->ignorarBuscaEquipamento = true;
        $this->lembrarOsVeiculo($veiculo);
        $this->preencherCamposVeiculoOs($veiculo->camposOs());
        $this->aplicarExtrasVeiculo($veiculo->extrasEquipamento());
        $descricao = trim((string) ($veiculo->descricao ?? ''));
        if ($descricao === '') {
            $descricao = trim((string) ($veiculo->marca ?? ''));
        }
        $modelo = trim((string) ($veiculo->modelo ?? ''));
        if ($descricao !== '') {
            $this->descricao = mb_strtoupper($descricao, 'UTF-8');
        }
        if ($modelo !== '') {
            $this->modelo = mb_strtoupper($modelo, 'UTF-8');
        }
        $placaLocal = OsVeiculo::normalizarPlaca($this->placa);
        $this->placaLocalResolvida = OsVeiculo::placaValida($placaLocal) ? $placaLocal : '';
        $this->closeEquipamentoLookup();
    }

    protected function salvarEquipamentoDaOs(int $empresaId): ?OsVeiculo
    {
        if ($empresaId <= 0) {
            $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        }

        $placa = OsVeiculo::normalizarPlaca($this->placa);
        if ($empresaId <= 0 || ! OsVeiculo::placaValida($placa)) {
            return null;
        }

        $descricao = mb_substr(mb_strtoupper(trim($this->descricao), 'UTF-8'), 0, 160, 'UTF-8');
        $modelo = mb_substr(mb_strtoupper(trim($this->modelo), 'UTF-8'), 0, 80, 'UTF-8');
        if ($descricao === '' && $modelo === '') {
            return OsVeiculo::porEmpresaPlaca($empresaId, $placa);
        }

        $attrs = array_filter([
            'descricao' => $descricao,
            'marca' => mb_substr($descricao, 0, 80, 'UTF-8'),
            'modelo' => $modelo,
        ], static fn (string $valor): bool => $valor !== '');

        return OsVeiculo::query()->updateOrCreate(
            [
                'empresa_id' => $empresaId,
                'placa' => $placa,
            ],
            $attrs,
        );
    }

    public function handleEquipamentoEnter(): void
    {
        if ($this->equipamentoSomenteLeitura() || ! $this->equipamentoLookupOpen || $this->equipamentoResults === []) {
            return;
        }

        if ($this->selectedEquipamentoIndex === null) {
            $this->selectedEquipamentoIndex = 0;
        }

        $this->confirmEquipamentoSelection();
    }

    /**
     * Copia o snapshot do orçamento para os campos da OS sem apagar o que o orçamento deixou em branco.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function aplicarEquipamentoImportado(?int $osVeiculoId, array $snapshot): void
    {
        $temSnapshot = $osVeiculoId !== null && $osVeiculoId > 0;
        foreach (['numero_serie', 'descricao', 'descricao2', 'modelo', 'ano', 'placa', 'km', 'cor', 'chassi'] as $chave) {
            if (trim((string) ($snapshot[$chave] ?? '')) !== '') {
                $temSnapshot = true;
                break;
            }
        }

        if (! $temSnapshot) {
            return;
        }

        if ($osVeiculoId !== null && $osVeiculoId > 0) {
            $veiculo = OsVeiculo::query()->find($osVeiculoId);
            if ($veiculo !== null) {
                $this->preencherCamposVeiculoOs($veiculo->camposOs());
                $this->aplicarExtrasVeiculo($veiculo->extrasEquipamento());
            }
        }

        $this->preencherCamposVeiculoOs([
            'placa' => (string) ($snapshot['placa'] ?? ''),
            'descricao' => (string) ($snapshot['descricao'] ?? ''),
            'modelo' => (string) ($snapshot['modelo'] ?? ''),
            'ano' => (string) ($snapshot['ano'] ?? ''),
            'cor' => (string) ($snapshot['cor'] ?? ''),
            'chassi' => (string) ($snapshot['chassi'] ?? ''),
        ]);

        $numeroSerie = trim((string) ($snapshot['numero_serie'] ?? ''));
        if ($numeroSerie !== '') {
            $this->numeroSerie = $numeroSerie;
        }

        $descricao2 = trim((string) ($snapshot['descricao2'] ?? ''));
        if ($descricao2 !== '') {
            $this->descricao2 = mb_strtoupper($descricao2, 'UTF-8');
        }

        $km = trim((string) ($snapshot['km'] ?? ''));
        if ($km !== '') {
            $this->km = $km;
        }

        $placaLocal = OsVeiculo::normalizarPlaca($this->placa);
        $this->placaLocalResolvida = OsVeiculo::placaValida($placaLocal) ? $placaLocal : '';

        if ($osVeiculoId === null || $osVeiculoId <= 0) {
            $this->carregarExtrasVeiculoLocal();
        }
    }

    private function buscarEquipamentoCadastrado(): void
    {
        $term = mb_strtoupper(trim($this->descricao), 'UTF-8');
        if (mb_strlen($term) < 2) {
            $this->closeEquipamentoLookup();

            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            $this->closeEquipamentoLookup();

            return;
        }

        $like = '%'.$term.'%';
        $placa = OsVeiculo::normalizarPlaca($term);

        $this->equipamentoResults = OsVeiculo::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($query) use ($like, $placa): void {
                $query->where('descricao', 'like', $like)
                    ->orWhere('marca', 'like', $like)
                    ->orWhere('modelo', 'like', $like)
                    ->orWhere('versao', 'like', $like);

                if ($placa !== '') {
                    $query->orWhere('placa', 'like', '%'.$placa.'%');
                }
            })
            ->orderBy('descricao')
            ->orderBy('placa')
            ->limit(15)
            ->get(['id', 'placa', 'descricao', 'marca', 'modelo'])
            ->map(static function (OsVeiculo $veiculo): array {
                $descricao = trim((string) ($veiculo->descricao ?? ''));
                if ($descricao === '') {
                    $descricao = trim((string) ($veiculo->marca ?? ''));
                }

                return [
                    'id' => (int) $veiculo->id,
                    'placa' => (string) $veiculo->placa,
                    'descricao' => $descricao,
                    'modelo' => trim((string) ($veiculo->modelo ?? '')),
                ];
            })
            ->all();

        $this->equipamentoLookupOpen = true;
        $this->selectedEquipamentoIndex = $this->equipamentoResults === [] ? null : 0;
    }

    private function lembrarOsVeiculo(?OsVeiculo $veiculo): void
    {
        if (! property_exists($this, 'osVeiculoId')) {
            return;
        }

        $this->osVeiculoId = $veiculo?->getKey() !== null ? (int) $veiculo->getKey() : null;
    }
}
