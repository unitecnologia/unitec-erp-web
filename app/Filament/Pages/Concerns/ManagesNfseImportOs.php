<?php

namespace App\Filament\Pages\Concerns;

use App\Models\OrdemServico;
use App\Models\Person;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;

trait ManagesNfseImportOs
{
    public bool $nfseImportOsOpen = false;

    public string $nfseImportOsNumero = '';

    public string $nfseImportOsCliente = '';

    public string $nfseImportOsDocumento = '';

    public string $nfseImportOsSituacao = OrdemServico::SITUACAO_FINALIZADA;

    public string $nfseImportOsDataDe = '';

    public string $nfseImportOsDataAte = '';

    /** @var list<array<string, mixed>> */
    public array $nfseImportOsResults = [];

    public ?int $nfseImportOsSelectedIndex = null;

    public ?int $nfseImportOsMarkedId = null;

    public ?int $nfseOsOrigemId = null;

    public ?int $nfseImportOsPendenteId = null;

    public bool $nfseImportOsConfirmOpen = false;

    public string $nfseOsLaudoPreservado = '';

    public function openNfseImportOs(): void
    {
        if (! $this->nfseModalOpen || $this->nfseSomenteLeitura()) {
            return;
        }

        if ($this->nfseImportOsOpen || $this->nfseEspelhoModalOpen || $this->nfseDanfseModalOpen) {
            return;
        }

        $hoje = ErpTimezone::today();

        $this->nfseImportOsNumero = '';
        $this->nfseImportOsCliente = '';
        $this->nfseImportOsDocumento = '';
        $this->nfseImportOsSituacao = OrdemServico::SITUACAO_FINALIZADA;
        $this->nfseImportOsDataDe = $hoje;
        $this->nfseImportOsDataAte = $hoje;
        $this->nfseImportOsSelectedIndex = null;
        $this->nfseImportOsMarkedId = null;
        $this->nfseImportOsPendenteId = null;
        $this->nfseImportOsConfirmOpen = false;
        $this->nfseImportOsOpen = true;
        $this->buscarNfseImportOs();
    }

    public function closeNfseImportOs(): void
    {
        $this->nfseImportOsOpen = false;
        $this->nfseImportOsResults = [];
        $this->nfseImportOsSelectedIndex = null;
        $this->nfseImportOsMarkedId = null;
        $this->nfseImportOsPendenteId = null;
        $this->nfseImportOsConfirmOpen = false;
    }

    public function updatedNfseImportOsNumero(): void
    {
        $this->buscarNfseImportOs();
    }

    public function updatedNfseImportOsCliente(): void
    {
        $this->buscarNfseImportOs();
    }

    public function updatedNfseImportOsDocumento(): void
    {
        $this->buscarNfseImportOs();
    }

    public function updatedNfseImportOsSituacao(): void
    {
        $this->buscarNfseImportOs();
    }

    public function updatedNfseImportOsDataDe(): void
    {
        $this->buscarNfseImportOs();
    }

    public function updatedNfseImportOsDataAte(): void
    {
        $this->buscarNfseImportOs();
    }

    public function selectNfseImportOsRow(int $index): void
    {
        if (! isset($this->nfseImportOsResults[$index])) {
            return;
        }

        $this->nfseImportOsSelectedIndex = $index;

        if (empty($this->nfseImportOsResults[$index]['importavel'])) {
            return;
        }

        $osId = (int) ($this->nfseImportOsResults[$index]['id'] ?? 0);

        if ($osId > 0) {
            $this->nfseImportOsMarkedId = $osId;
        }
    }

    public function toggleNfseImportOsMarkAt(int $index): void
    {
        if (! isset($this->nfseImportOsResults[$index])) {
            return;
        }

        $this->nfseImportOsSelectedIndex = $index;

        if (empty($this->nfseImportOsResults[$index]['importavel'])) {
            Notification::make()
                ->title((string) ($this->nfseImportOsResults[$index]['bloqueio'] ?? 'OS não pode ser importada.'))
                ->warning()
                ->send();

            return;
        }

        $osId = (int) ($this->nfseImportOsResults[$index]['id'] ?? 0);

        if ($osId < 1) {
            return;
        }

        $this->nfseImportOsMarkedId = $this->nfseImportOsMarkedId === $osId ? null : $osId;
    }

    public function toggleNfseImportOsMarkFocused(): void
    {
        if ($this->nfseImportOsSelectedIndex === null) {
            return;
        }

        $this->toggleNfseImportOsMarkAt((int) $this->nfseImportOsSelectedIndex);
    }

    public function isNfseImportOsRowMarked(int $index): bool
    {
        if ($this->nfseImportOsMarkedId === null || ! isset($this->nfseImportOsResults[$index])) {
            return false;
        }

        return (int) ($this->nfseImportOsResults[$index]['id'] ?? 0) === (int) $this->nfseImportOsMarkedId;
    }

    public function moveNfseImportOsSelection(int $delta): void
    {
        if ($this->nfseImportOsResults === []) {
            return;
        }

        $count = count($this->nfseImportOsResults);
        $atual = $this->nfseImportOsSelectedIndex ?? 0;
        $index = $atual + $delta;

        if ($index < 0) {
            $index = $count - 1;
        } elseif ($index >= $count) {
            $index = 0;
        }

        $this->nfseImportOsSelectedIndex = $index;
    }

    public function confirmarNfseImportOs(): void
    {
        $osId = (int) ($this->nfseImportOsMarkedId ?? 0);

        if ($osId < 1) {
            Notification::make()
                ->title('Marque uma OS na lista para importar.')
                ->warning()
                ->send();

            return;
        }

        $row = collect($this->nfseImportOsResults)->first(
            fn (array $item): bool => (int) ($item['id'] ?? 0) === $osId
        );

        if (! is_array($row)) {
            Notification::make()
                ->title('OS selecionada não está na lista.')
                ->warning()
                ->send();

            return;
        }

        if (empty($row['importavel'])) {
            Notification::make()
                ->title((string) ($row['bloqueio'] ?? 'OS não pode ser importada.'))
                ->warning()
                ->send();

            return;
        }

        if ($this->nfseOsJaImportada($osId) || $this->nfseTemServicosDaOs($osId)) {
            $this->nfseImportOsPendenteId = $osId;
            $this->nfseImportOsConfirmOpen = true;

            return;
        }

        $this->aplicarImportacaoOsNaNfse($osId, substituir: false);
    }

    public function confirmarNfseImportOsDuplicada(): void
    {
        $osId = (int) ($this->nfseImportOsPendenteId ?? 0);
        $this->nfseImportOsConfirmOpen = false;
        $this->nfseImportOsPendenteId = null;

        if ($osId < 1) {
            return;
        }

        $this->aplicarImportacaoOsNaNfse($osId, substituir: true);
    }

    public function cancelarNfseImportOsDuplicada(): void
    {
        $this->nfseImportOsConfirmOpen = false;
        $this->nfseImportOsPendenteId = null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function nfseImportOsSituacaoOpcoes(): array
    {
        return [
            ['value' => OrdemServico::SITUACAO_FINALIZADA, 'label' => 'Finalizada'],
            ['value' => OrdemServico::SITUACAO_ENTREGUE, 'label' => 'Entregue'],
            ['value' => '', 'label' => 'Finalizada + Entregue'],
        ];
    }

    protected function buscarNfseImportOs(): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $numero = trim($this->nfseImportOsNumero);
        $cliente = trim($this->nfseImportOsCliente);
        $documento = preg_replace('/\D/', '', $this->nfseImportOsDocumento) ?? '';
        $situacao = trim($this->nfseImportOsSituacao);
        $dataDe = trim($this->nfseImportOsDataDe);
        $dataAte = trim($this->nfseImportOsDataAte);

        if ($dataDe === '') {
            $dataDe = ErpTimezone::today();
            $this->nfseImportOsDataDe = $dataDe;
        }

        if ($dataAte === '') {
            $dataAte = $dataDe;
            $this->nfseImportOsDataAte = $dataAte;
        }

        if ($dataDe > $dataAte) {
            [$dataDe, $dataAte] = [$dataAte, $dataDe];
            $this->nfseImportOsDataDe = $dataDe;
            $this->nfseImportOsDataAte = $dataAte;
        }

        $situacoesPermitidas = [
            OrdemServico::SITUACAO_FINALIZADA,
            OrdemServico::SITUACAO_ENTREGUE,
        ];

        $query = OrdemServico::query()
            ->with(['cliente:id,nome_razao,cpf_cnpj', 'itens.product'])
            ->when($empresaId !== null, fn ($q) => $q->where(function ($q) use ($empresaId): void {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            }))
            ->when(
                $situacao !== '' && in_array($situacao, $situacoesPermitidas, true),
                fn ($q) => $q->where('situacao', $situacao),
                fn ($q) => $q->whereIn('situacao', $situacoesPermitidas),
            )
            ->when($numero !== '', function ($q) use ($numero): void {
                $q->where(function ($q) use ($numero): void {
                    $q->where('numero', 'like', '%'.$numero.'%')
                        ->orWhere('id', $numero);
                });
            })
            ->when($cliente !== '', function ($q) use ($cliente): void {
                $q->whereHas('cliente', function ($q) use ($cliente): void {
                    $q->where('nome_razao', 'like', '%'.$cliente.'%');
                });
            })
            ->when($documento !== '', function ($q) use ($documento): void {
                $q->whereHas('cliente', function ($q) use ($documento): void {
                    $q->where('cpf_cnpj', 'like', '%'.$documento.'%');
                });
            })
            ->where(function ($q) use ($dataDe, $dataAte): void {
                // Preferência: término; senão entrega; senão emissão.
                $q->whereBetween('data_termino', [$dataDe, $dataAte])
                    ->orWhere(function ($q) use ($dataDe, $dataAte): void {
                        $q->whereNull('data_termino')
                            ->whereBetween('data_entrega', [$dataDe, $dataAte]);
                    })
                    ->orWhere(function ($q) use ($dataDe, $dataAte): void {
                        $q->whereNull('data_termino')
                            ->whereNull('data_entrega')
                            ->whereBetween('data_emissao', [$dataDe, $dataAte]);
                    });
            })
            ->orderByDesc('data_termino')
            ->orderByDesc('id')
            ->limit(80);

        /** @var Collection<int, OrdemServico> $ordens */
        $ordens = $query->get();

        $this->nfseImportOsResults = $ordens
            ->map(function (OrdemServico $ordem): ?array {
                $motivo = NfseFromOrdemServico::motivoBloqueio($ordem);
                $servicos = NfseFromOrdemServico::servicos($ordem);
                $total = '0.00';

                foreach ($servicos as $item) {
                    $qtd = NfseFromOrdemServico::quantidade($item) ?? '0.000';
                    $valor = NfseFromOrdemServico::valor($item) ?? '0.00';
                    $total = bcadd($total, bcmul($qtd, $valor, 8), 2);
                }

                /** @var Person|null $cliente */
                $cliente = $ordem->cliente;
                $dataRef = $ordem->data_termino ?? $ordem->data_entrega ?? $ordem->data_emissao;

                return [
                    'id' => (int) $ordem->id,
                    'numero' => (string) ($ordem->numero ?: $ordem->id),
                    'data' => $dataRef ? ErpTimezone::toLocal($dataRef)->format('d/m/Y') : '—',
                    'cliente' => (string) ($cliente?->nome_razao ?? '—'),
                    'documento' => (string) ($cliente?->cpf_cnpj ?? ''),
                    'situacao' => $ordem->situacaoLabel(),
                    'situacao_codigo' => (string) $ordem->situacao,
                    'total' => $this->nfseFormatarDecimal($total, 2),
                    'servicos_qtd' => count($servicos),
                    'bloqueio' => $motivo,
                    'importavel' => $motivo === null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($this->nfseImportOsMarkedId !== null) {
            $marcadoVisivel = collect($this->nfseImportOsResults)->contains(
                fn (array $row): bool => (int) ($row['id'] ?? 0) === (int) $this->nfseImportOsMarkedId
            );

            if (! $marcadoVisivel) {
                $this->nfseImportOsMarkedId = null;
            }
        }

        $this->nfseImportOsSelectedIndex = $this->nfseImportOsResults === [] ? null : 0;
    }

    protected function aplicarImportacaoOsNaNfse(int $osId, bool $substituir): void
    {
        $empresaId = ErpContext::currentEmpresaId();
        $ordem = OrdemServico::query()
            ->with(['cliente', 'itens.product'])
            ->when($empresaId !== null, fn ($q) => $q->where(function ($q) use ($empresaId): void {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            }))
            ->find($osId);

        if ($ordem === null) {
            Notification::make()->title('OS não encontrada.')->warning()->send();

            return;
        }

        $motivo = NfseFromOrdemServico::motivoBloqueio($ordem);

        if ($motivo !== null) {
            Notification::make()->title($motivo)->warning()->send();

            return;
        }

        if ($substituir) {
            $prefix = 'nfse-os-'.$osId.'-';
            $this->nfseServicos = array_values(array_filter(
                $this->nfseServicos,
                function (array $linha) use ($osId, $prefix): bool {
                    $key = (string) ($linha['key'] ?? '');

                    if (str_starts_with($key, $prefix)) {
                        return false;
                    }

                    return (int) ($linha['os_id'] ?? 0) !== $osId;
                },
            ));
        }

        $linhas = $this->montarLinhasServicoDaOs($ordem);

        if ($linhas === []) {
            Notification::make()->title('A OS não tem serviço para a NFS-e.')->warning()->send();

            return;
        }

        // Último serviço da OS no topo (mesmo padrão da inclusão).
        foreach (array_reverse($linhas) as $linha) {
            array_unshift($this->nfseServicos, $linha);
        }
        $this->nfseServicos = array_values($this->nfseServicos);
        $this->nfseServicoLinhaIndex = 0;

        if ($ordem->cliente !== null && $this->nfseTomadorId === null) {
            $this->aplicarNfseTomadorSugestao($this->mapearNfseTomador($ordem->cliente));
        }

        $laudo = trim((string) ($ordem->laudo ?? ''));
        if ($laudo !== '') {
            $this->nfseOsLaudoPreservado = $laudo;
        }

        $this->nfseOsOrigemId = $osId;
        $this->closeNfseImportOs();

        Notification::make()
            ->title('Serviços da OS importados.')
            ->body('Somente itens de serviço foram incluídos.')
            ->success()
            ->send();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function montarLinhasServicoDaOs(OrdemServico $ordem): array
    {
        $linhas = [];

        foreach (NfseFromOrdemServico::servicos($ordem) as $item) {
            $produto = $item->product;

            if ($produto === null || ! $produto->is_servico) {
                continue;
            }

            $quantidade = NfseFromOrdemServico::quantidade($item) ?? '0.000';
            $valor = NfseFromOrdemServico::valor($item) ?? '0.00';
            $total = $this->nfseMultiplicarDecimal($quantidade, $valor);
            $this->nfseServicoSeq++;

            $linhas[] = [
                'key' => 'nfse-os-'.$ordem->id.'-'.$item->id,
                'rev' => 0,
                'product_id' => (int) $produto->id,
                'codigo' => (string) ($produto->codigo ?? ''),
                'descricao' => NfseFromOrdemServico::descricao($item),
                'unidade' => (string) ($produto->unidade ?? ''),
                'quantidade' => $this->nfseFormatarDecimal($quantidade, 3),
                'valor' => $this->nfseFormatarDecimal($valor, 2),
                'desconto' => '0,00',
                'acrescimo' => '0,00',
                'total' => $this->nfseFormatarDecimal($total, 2),
                'total_decimal' => $total,
                'c_trib_nac' => $produto->c_trib_nac,
                'c_nbs' => $produto->c_nbs,
                'c_trib_mun' => $produto->c_trib_mun,
                'c_ind_op' => $produto->c_ind_op,
                'os_id' => (int) $ordem->id,
            ];
        }

        return $linhas;
    }

    protected function nfseOsJaImportada(int $osId): bool
    {
        return $this->nfseOsOrigemId !== null && (int) $this->nfseOsOrigemId === $osId;
    }

    protected function nfseTemServicosDaOs(int $osId): bool
    {
        $prefix = 'nfse-os-'.$osId.'-';

        foreach ($this->nfseServicos as $linha) {
            $key = (string) ($linha['key'] ?? '');

            if (str_starts_with($key, $prefix)) {
                return true;
            }

            if ((int) ($linha['os_id'] ?? 0) === $osId) {
                return true;
            }
        }

        return false;
    }
}
