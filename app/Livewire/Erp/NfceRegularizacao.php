<?php

namespace App\Livewire\Erp;

use App\Models\Empresa;
use App\Models\Person;
use App\Models\Venda;
use App\Support\Erp\DocumentoBrasileiroValidator;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfce\NfceConsumidorIdentificado;
use App\Support\Erp\Nfce\NfceRegularizacaoQuery;
use App\Support\Erp\Nfce\NfceRegularizacaoService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Regularização Fiscal — Vendas sem NFC-e/NF-e.
 * Seleção e soma ficam no navegador (Alpine); o servidor só é chamado para filtrar/paginar e emitir.
 */
class NfceRegularizacao extends Component
{
    use WithPagination;

    private const MAX_SELECAO = 500;

    public string $dataDe = '';

    public string $dataAte = '';

    public string $numero = '';

    public string $cliente = '';

    public string $origem = '';

    public string $vendedor = '';

    public string $pagamento = '';

    /** @var array<string, string> */
    public array $aplicados = [];

    public int $perPage = 50;

    public bool $houveEmissao = false;

    public function mount(): void
    {
        abort_unless(ErpAccess::currentCan('nfce.access'), 403);

        $hoje = ErpTimezone::nowLocal();
        $this->dataDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->dataAte = $hoje->toDateString();
        $this->guardarFiltros();
    }

    public function aplicarFiltros(): void
    {
        $this->guardarFiltros();
        $this->resetPage('regPage');
    }

    public function limparFiltros(): void
    {
        $hoje = ErpTimezone::nowLocal();
        $this->dataDe = $hoje->copy()->startOfMonth()->toDateString();
        $this->dataAte = $hoje->toDateString();
        $this->numero = '';
        $this->cliente = '';
        $this->origem = '';
        $this->vendedor = '';
        $this->pagamento = '';
        $this->aplicarFiltros();
    }

    public function fechar(): void
    {
        $this->dispatch('erp-nfce-regularizacao-fechar', houveEmissao: $this->houveEmissao);
    }

    /**
     * Clientes da base para a coluna Cliente (só CPF ou sem documento; CNPJ não vai em NFC-e).
     *
     * @return list<array{id: int, nome: string, cpf: string}>
     */
    #[Renderless]
    public function buscarClientes(string $termo): array
    {
        if (! ErpAccess::currentCan('nfce.access')) {
            return [];
        }

        $termo = mb_strtoupper(trim(mb_substr($termo, 0, 60, 'UTF-8')), 'UTF-8');
        $digits = DocumentoBrasileiroValidator::digits($termo);

        if (mb_strlen($termo, 'UTF-8') < 2) {
            return [];
        }

        $query = Person::query()
            ->select(['id', 'codigo', 'nome_razao', 'apelido_fantasia', 'cpf_cnpj'])
            ->where('is_cliente', true)
            ->where('ativo', true);

        if ($digits !== '' && strlen($digits) >= 3 && $digits === preg_replace('/[\s.\-\/]/', '', $termo)) {
            $col = $query->getQuery()->getGrammar()->wrap('cpf_cnpj');
            $query->whereRaw("REPLACE(REPLACE(REPLACE(COALESCE({$col}, ''), '.', ''), '-', ''), '/', '') LIKE ?", [$digits.'%']);
        } else {
            $query->where(function ($q) use ($termo): void {
                $q->where('nome_razao', 'like', $termo.'%')
                    ->orWhere('nome_razao', 'like', '% '.$termo.'%')
                    ->orWhere('apelido_fantasia', 'like', $termo.'%');
            });
        }

        return $query->orderBy('nome_razao')->limit(30)->get()
            ->filter(function (Person $person): bool {
                $documento = DocumentoBrasileiroValidator::digits((string) $person->cpf_cnpj);

                return strlen($documento) !== 14
                    && ! Person::isCodigoConsumidorFinal($person->codigo !== null ? (string) $person->codigo : null);
            })
            ->take(8)
            ->map(fn (Person $person): array => [
                'id' => (int) $person->id,
                'nome' => mb_strtoupper(trim((string) ($person->nome_razao ?: $person->apelido_fantasia)), 'UTF-8'),
                'cpf' => DocumentoBrasileiroValidator::isValidCpf((string) $person->cpf_cnpj)
                    ? NfceRegularizacaoQuery::formatarCpf((string) $person->cpf_cnpj)
                    : '',
            ])
            ->values()
            ->all();
    }

    /**
     * CPF digitado na grade: valida e, se existir na base, devolve o cliente.
     *
     * @return array{erro: string|null, cliente: array{id: int, nome: string}|null}
     */
    #[Renderless]
    public function consultarCpf(string $cpf): array
    {
        if (! ErpAccess::currentCan('nfce.access')) {
            return ['erro' => 'Sem permissão para acessar NFC-e.', 'cliente' => null];
        }

        $digits = DocumentoBrasileiroValidator::digits($cpf);

        if (strlen($digits) === 14) {
            return ['erro' => 'NFC-e não aceita CNPJ. Para pessoa jurídica emita NF-e.', 'cliente' => null];
        }

        if ($erro = DocumentoBrasileiroValidator::mensagemCpf($digits)) {
            return ['erro' => $erro, 'cliente' => null];
        }

        $person = $digits !== '' ? NfceConsumidorIdentificado::findByCpf($digits) : null;

        return [
            'erro' => null,
            'cliente' => $person ? [
                'id' => (int) $person->id,
                'nome' => mb_strtoupper(trim((string) ($person->nome_razao ?: $person->apelido_fantasia)), 'UTF-8'),
            ] : null,
        ];
    }

    /**
     * Valida as vendas selecionadas antes de qualquer transmissão.
     *
     * @param  list<int|string>  $ids
     * @param  array<int|string, array<string, mixed>>  $consumidores  consumidor alterado na grade, por venda
     * @return array{bloqueio: string|null, aptas: list<array{id: int, numero: string}>, erros: list<array{id: int, numero: string, mensagem: string}>}
     */
    #[Renderless]
    public function validarSelecionadas(array $ids, array $consumidores = []): array
    {
        $retorno = ['bloqueio' => null, 'aptas' => [], 'erros' => []];

        if (! ErpAccess::currentCan('nfce.access')) {
            return ['bloqueio' => 'Sem permissão para emitir NFC-e.'] + $retorno;
        }

        $empresa = $this->empresa();

        if ($empresa === null) {
            return ['bloqueio' => 'Empresa não configurada para emissão fiscal.'] + $retorno;
        }

        $service = new NfceRegularizacaoService();
        $motivos = $service->motivosBloqueioConfiguracao($empresa);

        if ($motivos !== []) {
            return ['bloqueio' => 'NFC-e não configurada: '.implode(' ', $motivos)] + $retorno;
        }

        $ids = array_slice(array_values(array_unique(array_filter(
            array_map('intval', $ids),
            fn (int $id): bool => $id > 0,
        ))), 0, self::MAX_SELECAO);

        $numeros = Venda::query()->whereIn('id', $ids)->pluck('numero', 'id');

        foreach ($ids as $id) {
            $numero = (string) ($numeros[$id] ?? $id);
            $informado = is_array($consumidores[$id] ?? null);

            try {
                if ($informado) {
                    NfceRegularizacaoService::normalizarConsumidor($consumidores[$id]);
                }
                $motivo = $service->validar($id, (int) $empresa->id, $informado);
            } catch (\DomainException $exception) {
                $motivo = $exception->getMessage();
            }

            if ($motivo === null) {
                $retorno['aptas'][] = ['id' => $id, 'numero' => $numero];
            } else {
                $retorno['erros'][] = ['id' => $id, 'numero' => $numero, 'mensagem' => $motivo];
            }
        }

        return $retorno;
    }

    /**
     * Emite a NFC-e de uma venda (chamado em sequência pelo navegador: 1 venda = 1 NFC-e,
     * uma rejeição não interrompe as demais).
     *
     * @param  array<string, mixed>|null  $consumidor  null = mantém o cliente da venda
     * @return array{id: int, status: string, mensagem: string, nfce_numero: int|null}
     */
    #[Renderless]
    public function emitirVenda(int $id, ?array $consumidor = null): array
    {
        if (! ErpAccess::currentCan('nfce.access')) {
            return ['id' => $id, 'status' => NfceRegularizacaoService::STATUS_REJEITADA, 'mensagem' => 'Sem permissão.', 'nfce_numero' => null];
        }

        $empresa = $this->empresa();

        if ($empresa === null) {
            return ['id' => $id, 'status' => NfceRegularizacaoService::STATUS_REJEITADA, 'mensagem' => 'Empresa não configurada.', 'nfce_numero' => null];
        }

        try {
            $consumidor = $consumidor !== null ? NfceRegularizacaoService::normalizarConsumidor($consumidor) : null;
        } catch (\DomainException $exception) {
            return ['id' => $id, 'status' => NfceRegularizacaoService::STATUS_REJEITADA, 'mensagem' => $exception->getMessage(), 'nfce_numero' => null];
        }

        @set_time_limit(180);

        $resultado = (new NfceRegularizacaoService())->emitir($id, $empresa, $consumidor);

        if ($resultado['status'] === NfceRegularizacaoService::STATUS_AUTORIZADA) {
            $this->houveEmissao = true;
        }

        return ['id' => $id] + $resultado;
    }

    /** Recarrega a página atual após o lote (autorizadas saem da pendência). */
    public function concluirEmissao(): void
    {
        $this->houveEmissao = true;
    }

    public function render(): View
    {
        $paginator = (new NfceRegularizacaoQuery(
            empresaId: $this->empresa()?->id,
            dataDe: $this->aplicados['dataDe'] ?? null,
            dataAte: $this->aplicados['dataAte'] ?? null,
            numero: $this->aplicados['numero'] ?? '',
            cliente: $this->aplicados['cliente'] ?? '',
            origem: $this->aplicados['origem'] ?? '',
            vendedor: $this->aplicados['vendedor'] ?? '',
            pagamento: $this->aplicados['pagamento'] ?? '',
        ))->build()->paginate($this->perPage, pageName: 'regPage');

        return view('livewire.erp.nfce-regularizacao', [
            'paginator' => $paginator,
            'rows' => NfceRegularizacaoQuery::mapRows($paginator->getCollection()),
            'origens' => NfceRegularizacaoQuery::origemLabels(),
            'formasPagamento' => NfceRegularizacaoQuery::formasPagamentoOpcoes(),
            'periodo' => NfceRegularizacaoQuery::periodoPermitido(),
        ]);
    }

    private function guardarFiltros(): void
    {
        $this->aplicados = [
            'dataDe' => $this->normalizarData($this->dataDe),
            'dataAte' => $this->normalizarData($this->dataAte),
            'numero' => trim($this->numero),
            'cliente' => trim($this->cliente),
            'origem' => array_key_exists($this->origem, NfceRegularizacaoQuery::origemLabels()) ? $this->origem : '',
            'vendedor' => trim($this->vendedor),
            'pagamento' => mb_substr(trim($this->pagamento), 0, 60),
        ];
    }

    private function normalizarData(string $valor): string
    {
        $valor = trim($valor);

        if ($valor === '') {
            return '';
        }

        try {
            return Carbon::parse($valor)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }

    private function empresa(): ?Empresa
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return $empresaId
            ? Empresa::query()->whereKey((int) $empresaId)->where('ativo', true)->first()
            : null;
    }
}
