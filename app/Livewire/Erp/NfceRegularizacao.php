<?php

namespace App\Livewire\Erp;

use App\Models\Empresa;
use App\Models\Venda;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpTimezone;
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

    /** @var array<string, string> */
    public array $aplicados = [];

    public int $perPage = 50;

    public bool $houveEmissao = false;

    public function mount(): void
    {
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
        $this->aplicarFiltros();
    }

    public function fechar(): void
    {
        $this->dispatch('erp-nfce-regularizacao-fechar', houveEmissao: $this->houveEmissao);
    }

    /**
     * Valida as vendas selecionadas antes de qualquer transmissão.
     *
     * @param  list<int|string>  $ids
     * @return array{bloqueio: string|null, aptas: list<array{id: int, numero: string}>, erros: list<array{id: int, numero: string, mensagem: string}>}
     */
    #[Renderless]
    public function validarSelecionadas(array $ids): array
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
            $motivo = $service->validar($id, (int) $empresa->id);

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
     * @return array{id: int, status: string, mensagem: string, nfce_numero: int|null}
     */
    #[Renderless]
    public function emitirVenda(int $id): array
    {
        if (! ErpAccess::currentCan('nfce.access')) {
            return ['id' => $id, 'status' => NfceRegularizacaoService::STATUS_REJEITADA, 'mensagem' => 'Sem permissão.', 'nfce_numero' => null];
        }

        $empresa = $this->empresa();

        if ($empresa === null) {
            return ['id' => $id, 'status' => NfceRegularizacaoService::STATUS_REJEITADA, 'mensagem' => 'Empresa não configurada.', 'nfce_numero' => null];
        }

        @set_time_limit(180);

        $resultado = (new NfceRegularizacaoService())->emitir($id, $empresa);

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
        ))->build()->paginate($this->perPage, pageName: 'regPage');

        return view('livewire.erp.nfce-regularizacao', [
            'paginator' => $paginator,
            'rows' => NfceRegularizacaoQuery::mapRows($paginator->getCollection()),
            'origens' => NfceRegularizacaoQuery::origemLabels(),
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
