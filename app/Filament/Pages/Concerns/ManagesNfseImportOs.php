<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Nfse;
use App\Models\NfseItem;
use App\Models\OrdemServico;
use App\Models\Person;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfse\NfseFromOrdemServico;
use App\Support\Erp\Nfse\NfseOsDiscriminacao;
use App\Support\Erp\Nfse\NfsePagamentosOs;
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
        $comNfse = $this->nfseOsIdsComNotaValida($ordens->pluck('id')->map(fn ($id): int => (int) $id)->all());

        $this->nfseImportOsResults = $ordens
            ->map(function (OrdemServico $ordem) use ($comNfse): ?array {
                $motivo = $this->motivoBloqueioImportacaoOs($ordem, $comNfse);
                $servicos = NfseFromOrdemServico::servicos($ordem);
                $total = NfseFromOrdemServico::totalLiquido($ordem);

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

        $motivo = $this->motivoBloqueioImportacaoOs($ordem);

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

        $this->nfseDiscriminacao = NfseOsDiscriminacao::anexar($this->nfseDiscriminacao, $ordem);

        $origem = (int) ($this->nfseOsOrigemId ?? 0);

        if ($origem < 1 || ! in_array($origem, $this->nfseOsIdsDasLinhas(), true)) {
            $this->nfseOsOrigemId = $osId;
        }

        $this->aplicarEquipamentoDaOsNfse($ordem);
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

        foreach (NfseFromOrdemServico::linhas($ordem) as $linha) {
            $item = $linha['item'];
            $produto = $item->product;

            if ($produto === null || ! $produto->is_servico) {
                continue;
            }

            $this->nfseServicoSeq++;

            $linhas[] = [
                'key' => 'nfse-os-'.$ordem->id.'-'.$item->id,
                'rev' => 0,
                'product_id' => (int) $produto->id,
                'codigo' => (string) ($produto->codigo ?? ''),
                'descricao' => NfseFromOrdemServico::descricao($item),
                'servico_prestado' => trim((string) ($item->servico_prestado ?? '')),
                'unidade' => (string) ($produto->unidade ?? ''),
                'quantidade' => $this->nfseFormatarDecimal($linha['quantidade'], 3),
                'valor' => $this->nfseFormatarDecimal($linha['valor'], 2),
                'desconto' => $this->nfseFormatarDecimal($linha['desconto'], 2),
                'acrescimo' => $this->nfseFormatarDecimal($linha['acrescimo'], 2),
                'total' => $this->nfseFormatarDecimal($linha['total'], 2),
                'total_decimal' => $linha['total'],
                'c_trib_nac' => $produto->c_trib_nac,
                'c_nbs' => $produto->c_nbs,
                'c_trib_mun' => $produto->c_trib_mun,
                'c_ind_op' => $produto->c_ind_op,
                'os_id' => (int) $ordem->id,
            ];
            $this->sugerirAliquotaIssDoProduto((int) $produto->id);
        }

        return $linhas;
    }

    /**
     * @param  array<int, Nfse>|null  $notasPorOs  já consultadas em lote (busca); null consulta só esta OS
     */
    protected function motivoBloqueioImportacaoOs(OrdemServico $ordem, ?array $notasPorOs = null): ?string
    {
        $motivo = NfseFromOrdemServico::motivoBloqueio($ordem);

        if ($motivo !== null) {
            return $motivo;
        }

        $numeroOs = (string) ($ordem->numero ?: $ordem->id);
        $notaValida = $notasPorOs !== null
            ? ($notasPorOs[(int) $ordem->id] ?? null)
            : ($this->nfseOsIdsComNotaValida([(int) $ordem->id])[(int) $ordem->id] ?? null);

        if ($notaValida !== null) {
            return 'A OS nº '.$numeroOs.' já tem NFS-e '.$this->nfseDescricaoNotaOs($notaValida).'.';
        }

        if ($this->nfseTomadorId !== null && (int) $this->nfseTomadorId !== (int) $ordem->cliente_id) {
            return 'A OS nº '.$numeroOs.' é de outro cliente. Só é possível importar OS do tomador desta NFS-e.';
        }

        return null;
    }

    /**
     * Revalida no F2 as OS da nota: mesmo cliente do tomador e sem outra NFS-e válida.
     */
    protected function motivoBloqueioOsDaNfse(int $tomadorId): ?string
    {
        $osIds = $this->nfseOsIdsDasLinhas();

        if ($osIds === []) {
            return null;
        }

        $ordens = OrdemServico::query()->whereIn('id', $osIds)->get(['id', 'numero', 'cliente_id']);

        foreach ($ordens as $ordem) {
            if ((int) $ordem->cliente_id !== $tomadorId) {
                return 'A OS nº '.($ordem->numero ?: $ordem->id).' é de outro cliente. Os serviços de OS precisam ser do tomador da NFS-e.';
            }
        }

        $outras = $this->nfseOsIdsComNotaValida($osIds);

        foreach ($ordens as $ordem) {
            $nota = $outras[(int) $ordem->id] ?? null;

            if ($nota !== null) {
                return 'A OS nº '.($ordem->numero ?: $ordem->id).' já tem NFS-e '.$this->nfseDescricaoNotaOs($nota).'.';
            }
        }

        return null;
    }

    /**
     * Pagamento registrado no faturamento das OS desta nota (só leitura).
     *
     * @return array{linhas: list<array{os: string, forma: string, parcela: string, vencimento: string, valor: string}>, avisos: list<string>}
     */
    public function getNfsePagamentosOsProperty(): array
    {
        return NfsePagamentosOs::porOsIds($this->nfseOsIdsDasLinhas());
    }

    /**
     * Número das OS importadas na nota, para o título do modal.
     *
     * @return list<string>
     */
    public function nfseOsNumerosImportadas(): array
    {
        $osIds = $this->nfseOsIdsDasLinhas();

        if ($osIds === []) {
            return [];
        }

        return OrdemServico::query()
            ->whereIn('id', $osIds)
            ->orderBy('numero')
            ->get(['id', 'numero'])
            ->map(fn (OrdemServico $ordem): string => (string) ($ordem->numero ?: $ordem->id))
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function nfseOsIdsDasLinhas(): array
    {
        $ids = [];

        foreach ($this->nfseServicos as $linha) {
            $osId = (int) ($linha['os_id'] ?? 0);

            if ($osId < 1 && preg_match('/^nfse-os-(\d+)-/', (string) ($linha['key'] ?? ''), $match) === 1) {
                $osId = (int) $match[1];
            }

            if ($osId > 0 && ! in_array($osId, $ids, true)) {
                $ids[] = $osId;
            }
        }

        return $ids;
    }

    /**
     * NFS-e válida de cada OS (fora a nota aberta na tela), em duas consultas.
     *
     * @param  list<int>  $osIds
     * @return array<int, Nfse>
     */
    protected function nfseOsIdsComNotaValida(array $osIds): array
    {
        if ($osIds === []) {
            return [];
        }

        $empresaId = ErpContext::currentEmpresaId();
        $ignorar = $this->nfseIdAtualParaOs();
        $base = fn () => Nfse::query()
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId))
            ->when($ignorar !== null, fn ($query) => $query->whereKeyNot($ignorar))
            ->whereNotIn('status', NfseFromOrdemServico::NFSE_SEM_VALIDADE);

        $notas = [];

        foreach ($base()->whereIn('ordem_servico_id', $osIds)->orderBy('id')->get() as $nota) {
            $notas[(int) $nota->ordem_servico_id] = $nota;
        }

        $porItem = NfseItem::query()
            ->whereIn('ordem_servico_id', $osIds)
            ->whereIn('nfse_id', $base()->select('id'))
            ->orderBy('nfse_id')
            ->get(['nfse_id', 'ordem_servico_id']);

        if ($porItem->isNotEmpty()) {
            $porId = $base()->whereKey($porItem->pluck('nfse_id')->unique()->all())->get()->keyBy('id');

            foreach ($porItem as $item) {
                $nota = $porId->get((int) $item->nfse_id);

                if ($nota !== null) {
                    $notas[(int) $item->ordem_servico_id] ??= $nota;
                }
            }
        }

        return $notas;
    }

    protected function nfseIdAtualParaOs(): ?int
    {
        return $this->nfseId !== null && (int) $this->nfseId > 0 ? (int) $this->nfseId : null;
    }

    protected function nfseDescricaoNotaOs(Nfse $nota): string
    {
        $numero = trim((string) ($nota->numero_nfse ?? ''));

        if ($numero !== '') {
            return 'nº '.$numero;
        }

        return '(DPS nº '.$nota->numero_dps.', '.mb_strtolower($nota->statusLabel(), 'UTF-8').')';
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
