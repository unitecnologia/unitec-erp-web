<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\Orcamento;
use App\Models\Person;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Os\OrcamentoOsImportacao;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;

trait ManagesOrdemServicoImportarOrcamento
{
    public bool $osImportOrcamentoOpen = false;

    public string $osImportOrcNumero = '';

    public string $osImportOrcCliente = '';

    public string $osImportOrcDataDe = '';

    public string $osImportOrcDataAte = '';

    /** @var list<array{orcamento_id: int, numero: string, data: string, cliente: string, total: string}> */
    public array $osImportOrcamentoResults = [];

    public ?int $osImportOrcamentoSelectedIndex = null;

    public ?int $orcamentoOrigemId = null;

    public function abrirImportarOrcamentoOs(): void
    {
        if ($this->osReadOnly() || $this->osImportOrcamentoOpen) {
            return;
        }

        if ($this->osFaturamentoOpen || $this->printModalOpen || $this->descontoModalOpen || $this->servicoPrestadoModalOpen) {
            return;
        }

        $this->osImportOrcNumero = '';
        $this->osImportOrcCliente = '';
        $this->osImportOrcDataDe = '';
        $this->osImportOrcDataAte = '';
        $this->refreshOsImportOrcamentoResults();
        $this->osImportOrcamentoOpen = true;
    }

    public function fecharImportarOrcamentoOs(): void
    {
        $this->osImportOrcamentoOpen = false;
        $this->osImportOrcNumero = '';
        $this->osImportOrcCliente = '';
        $this->osImportOrcDataDe = '';
        $this->osImportOrcDataAte = '';
        $this->osImportOrcamentoResults = [];
        $this->osImportOrcamentoSelectedIndex = null;
    }

    public function updatedOsImportOrcNumero(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->osImportOrcNumero !== $upper) {
            $this->osImportOrcNumero = $upper;

            return;
        }

        $this->refreshOsImportOrcamentoResults();
    }

    public function updatedOsImportOrcCliente(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');

        if ($this->osImportOrcCliente !== $upper) {
            $this->osImportOrcCliente = $upper;

            return;
        }

        $this->refreshOsImportOrcamentoResults();
    }

    public function updatedOsImportOrcDataDe(): void
    {
        $this->refreshOsImportOrcamentoResults();
    }

    public function updatedOsImportOrcDataAte(): void
    {
        $this->refreshOsImportOrcamentoResults();
    }

    public function refreshOsImportOrcamentoResults(): void
    {
        $numero = trim($this->osImportOrcNumero);
        $cliente = trim($this->osImportOrcCliente);
        $dataDe = $this->dataFiltroOsImport($this->osImportOrcDataDe);
        $dataAte = $this->dataFiltroOsImport($this->osImportOrcDataAte);

        $query = Orcamento::query()
            ->visivelNaListaOrcamentos()
            ->with(['cliente:id,nome_razao'])
            ->where('status', Orcamento::STATUS_FECHADO)
            ->whereHas('itens')
            ->orderByDesc('data')
            ->orderByDesc('id');

        if ($this->orcamentoOrigemId) {
            $query->where('id', '!=', $this->orcamentoOrigemId);
        }

        if ($numero !== '') {
            $query->where('numero', 'like', '%'.$numero.'%');
        }

        if ($cliente !== '') {
            $like = '%'.$cliente.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('cliente_nome', 'like', $like)
                    ->orWhereHas('cliente', function ($sub) use ($like): void {
                        $sub->where('nome_razao', 'like', $like)
                            ->orWhere('apelido_fantasia', 'like', $like);
                    });
            });
        }

        if ($dataDe !== null) {
            $query->whereDate('data', '>=', $dataDe);
        }

        if ($dataAte !== null) {
            $query->whereDate('data', '<=', $dataAte);
        }

        $this->osImportOrcamentoResults = $query
            ->limit(50)
            ->get()
            ->map(fn (Orcamento $orcamento): array => [
                'orcamento_id' => (int) $orcamento->id,
                'numero' => (string) $orcamento->numero,
                'data' => $orcamento->data?->format('d/m/Y') ?? '',
                'cliente' => mb_strtoupper($orcamento->clienteDisplayNome() !== ''
                    ? $orcamento->clienteDisplayNome()
                    : (string) ($orcamento->cliente?->nome_razao ?? '—'), 'UTF-8'),
                'total' => ErpMoney::formatBr($orcamento->total),
            ])
            ->values()
            ->all();

        $this->osImportOrcamentoSelectedIndex = $this->osImportOrcamentoResults === [] ? null : 0;
    }

    public function selectOsImportOrcamentoRow(int $index): void
    {
        if (isset($this->osImportOrcamentoResults[$index])) {
            $this->osImportOrcamentoSelectedIndex = $index;
        }
    }

    public function moveOsImportOrcamentoSelection(int $delta): void
    {
        if ($this->osImportOrcamentoResults === []) {
            return;
        }

        $count = count($this->osImportOrcamentoResults);
        $index = ($this->osImportOrcamentoSelectedIndex ?? 0) + $delta;
        $this->osImportOrcamentoSelectedIndex = max(0, min($count - 1, $index));
    }

    public function confirmarImportarOrcamentoOs(): void
    {
        if ($this->osReadOnly()) {
            return;
        }

        $index = $this->osImportOrcamentoSelectedIndex;

        if ($index === null || ! isset($this->osImportOrcamentoResults[$index])) {
            Notification::make()->title('Selecione um orçamento.')->warning()->send();

            return;
        }

        $orcamentoId = (int) $this->osImportOrcamentoResults[$index]['orcamento_id'];

        if ($this->orcamentoOrigemId !== null && $this->orcamentoOrigemId === $orcamentoId) {
            Notification::make()
                ->title('Este orçamento já foi importado nesta OS.')
                ->body('Os itens não foram duplicados.')
                ->warning()
                ->send();

            return;
        }

        $orcamento = Orcamento::query()
            ->visivelNaListaOrcamentos()
            ->with(['itens.product', 'cliente'])
            ->where('status', Orcamento::STATUS_FECHADO)
            ->find($orcamentoId);

        if (! $orcamento || $orcamento->itens->isEmpty()) {
            Notification::make()->title('Orçamento indisponível para importação.')->warning()->send();
            $this->refreshOsImportOrcamentoResults();

            return;
        }

        $tinhaItens = $this->itens !== [];
        $mapeado = app(OrcamentoOsImportacao::class)->mapear($orcamento);

        $this->aplicarClienteDoOrcamento($orcamento);
        $this->aplicarObservacoesDoOrcamento($orcamento);
        $this->aplicarEquipamentoDoOrcamento($orcamento);
        $this->aplicarItensDoOrcamento($mapeado);
        $this->ajustarDescontoOsParaTotalOrcamento((float) $orcamento->total);
        $this->orcamentoOrigemId = (int) $orcamento->id;
        $this->activeFormTab = 'dados';

        $this->fecharImportarOrcamentoOs();

        $notification = Notification::make()
            ->title('Orçamento nº '.$orcamento->numero.' importado.')
            ->body(count($mapeado['linhas']).' item(ns). O orçamento original não foi alterado.');

        if ($tinhaItens) {
            $notification->body(
                count($mapeado['linhas']).' item(ns). Os itens anteriores desta OS foram substituídos para não duplicar e manter o total do orçamento.'
            );
        }

        $notification->success()->send();
    }

    /**
     * @param  array{linhas: list<array<string, mixed>>, vl_desc_pecas: float, vl_desc_servicos: float}  $mapeado
     */
    protected function aplicarItensDoOrcamento(array $mapeado): void
    {
        $itens = [];

        foreach ($mapeado['linhas'] as $linha) {
            $itens[] = $this->recalcItemRowData([
                'id' => null,
                'key' => 'orc-'.Str::uuid()->toString(),
                'tipo' => $linha['tipo'] === 'S' ? 'S' : 'P',
                'product_id' => $linha['product_id'],
                'product_codigo' => $linha['product_codigo'],
                'discriminacao' => mb_strtoupper((string) $linha['discriminacao'], 'UTF-8'),
                'qtd' => ErpMoney::formatBr((float) $linha['qtd'], 3),
                'preco' => ErpMoney::formatBr((float) $linha['preco']),
                'acrescimo' => ErpMoney::formatBr((float) $linha['acrescimo']),
                'desconto' => ErpMoney::formatBr((float) $linha['desconto']),
                'total' => ErpMoney::formatBr((float) $linha['total']),
                'funcionario_id' => $this->atendenteId,
                'concluido_em' => '',
                'foto' => $linha['foto'] ?? null,
            ]);
        }

        $this->itens = $itens;
        $this->descPecasGlobal = ErpMoney::formatBr((float) $mapeado['vl_desc_pecas']);
        $this->descServicosGlobal = ErpMoney::formatBr((float) $mapeado['vl_desc_servicos']);
        $this->editingItemIndex = null;
        $this->clearItemEntryRow();
        $this->produtoLookupOpen = false;
        $this->produtoResults = [];

        $temServico = false;

        foreach ($itens as $row) {
            if (($row['tipo'] ?? 'P') === 'S') {
                $temServico = true;

                break;
            }
        }

        $this->activeItemTab = $temServico ? 'servicos' : 'pecas';
        $this->selectedItemIndex = null;

        foreach ($this->itens as $index => $row) {
            $tipoAba = $this->activeItemTab === 'servicos' ? 'S' : 'P';

            if (($row['tipo'] ?? 'P') === $tipoAba) {
                $this->selectedItemIndex = $index;

                break;
            }
        }

        $this->recalcTotais();
    }

    protected function ajustarDescontoOsParaTotalOrcamento(float $totalAlvo): void
    {
        $this->recalcTotais();
        $diff = round(ErpMoney::parseBr($this->totalGeral) - round($totalAlvo, 2), 2);

        if (abs($diff) < 0.01) {
            return;
        }

        if ($diff > 0) {
            $pecas = ErpMoney::parseBr($this->totalPecas);

            if ($pecas + 0.001 >= $diff) {
                $this->descPecasGlobal = ErpMoney::formatBr(ErpMoney::parseBr($this->descPecasGlobal) + $diff);
            } else {
                $this->descServicosGlobal = ErpMoney::formatBr(ErpMoney::parseBr($this->descServicosGlobal) + $diff);
            }
        } else {
            $reducao = abs($diff);
            $globalServicos = ErpMoney::parseBr($this->descServicosGlobal);

            if ($globalServicos + 0.001 >= $reducao) {
                $this->descServicosGlobal = ErpMoney::formatBr($globalServicos - $reducao);
            } else {
                $this->descPecasGlobal = ErpMoney::formatBr(max(0, ErpMoney::parseBr($this->descPecasGlobal) - $reducao));
            }
        }

        $this->recalcTotais();
    }

    protected function aplicarEquipamentoDoOrcamento(Orcamento $orcamento): void
    {
        $this->aplicarEquipamentoImportado(
            $orcamento->os_veiculo_id ? (int) $orcamento->os_veiculo_id : null,
            [
                'numero_serie' => (string) ($orcamento->numero_serie ?? ''),
                'descricao' => (string) ($orcamento->descricao ?? ''),
                'descricao2' => (string) ($orcamento->descricao2 ?? ''),
                'modelo' => (string) ($orcamento->modelo ?? ''),
                'ano' => (string) ($orcamento->ano ?? ''),
                'placa' => (string) ($orcamento->placa ?? ''),
                'km' => (string) ($orcamento->km ?? ''),
                'cor' => (string) ($orcamento->cor_veiculo ?? ''),
                'chassi' => (string) ($orcamento->chassi_veiculo ?? ''),
            ],
        );
    }

    protected function aplicarClienteDoOrcamento(Orcamento $orcamento): void
    {
        $atendente = $this->atendenteId;
        $person = $orcamento->cliente;
        $temSnapshot = filled($orcamento->cliente_nome)
            || filled($orcamento->cliente_cpf_cnpj)
            || filled($orcamento->cliente_endereco)
            || filled($orcamento->cliente_fone)
            || filled($orcamento->cliente_whatsapp);

        $this->clienteId = $orcamento->cliente_id ? (int) $orcamento->cliente_id : null;

        if ($temSnapshot) {
            $nome = trim((string) ($orcamento->cliente_nome ?: $person?->nome_razao ?: ''));
            $this->nome = mb_strtoupper($nome, 'UTF-8');
            $this->clienteSearch = $this->nome;
            $this->documento = $this->cortarOsImport((string) ($orcamento->cliente_cpf_cnpj ?: $person?->cpf_cnpj ?: ''), 20);
            $fone = trim((string) ($orcamento->cliente_fone ?: $orcamento->cliente_whatsapp ?: $person?->fone1 ?: ''));
            $this->fone1 = $this->cortarOsImport($fone, 20);
            $this->endereco = $this->cortarOsImport($this->enderecoDoOrcamento($orcamento, $person), 150);
            $this->bairro = $this->cortarOsImport(mb_strtoupper((string) ($orcamento->cliente_bairro ?: $person?->bairro ?: ''), 'UTF-8'), 80);
            $this->cidade = $this->cortarOsImport(mb_strtoupper((string) ($orcamento->cliente_cidade ?: $person?->cidade_nome ?: ''), 'UTF-8'), 80);
            $uf = mb_strtoupper(trim((string) ($orcamento->cliente_uf ?: $person?->uf ?: 'SC')), 'UTF-8');
            $this->uf = $uf !== '' ? substr($uf, 0, 2) : 'SC';
        } elseif ($person) {
            $this->applyClienteFields($person);
            $this->clienteSearch = $this->nome;
        }

        $this->atendenteId = $atendente;
        $this->clienteLookupOpen = false;
    }

    protected function aplicarObservacoesDoOrcamento(Orcamento $orcamento): void
    {
        $texto = trim((string) ($orcamento->observacoes ?? ''));

        if ($texto !== '') {
            $this->observacoes = $texto;
        }
    }

    protected function enderecoDoOrcamento(Orcamento $orcamento, ?Person $person): string
    {
        $logradouro = trim((string) ($orcamento->cliente_endereco ?: $person?->endereco ?: ''));
        $numero = trim((string) ($orcamento->cliente_numero ?: $person?->numero ?: ''));

        if ($logradouro !== '' && $numero !== '' && ! str_contains($logradouro, $numero)) {
            $logradouro .= ', '.$numero;
        }

        return mb_strtoupper($logradouro, 'UTF-8');
    }

    protected function dataFiltroOsImport(string $value): ?string
    {
        $value = trim($value);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return $value;
    }

    protected function cortarOsImport(string $value, int $limite): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return mb_substr($value, 0, $limite, 'UTF-8');
    }
}
