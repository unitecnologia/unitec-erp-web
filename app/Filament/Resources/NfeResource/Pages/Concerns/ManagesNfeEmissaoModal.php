<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\NfeReferencia;
use App\Models\Person;
use App\Models\VendasParametro;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Nfe\NfeCalculoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait ManagesNfeEmissaoModal
{
    use ManagesNfeItemGrid;

    public bool $nfeModalOpen = false;

    public ?int $nfeModalRecordId = null;

    public string $nfeModalStatus = 'ABERTA';

    public string $nfeModalMainTab = 'itens';

    public string $nfeModalDetailTab = 'totais';

    /** @var array<string, mixed> */
    public array $nfeForm = [];

    /** @var array<int, array<string, mixed>> */
    public array $nfeModalRows = [];

    /** @var array<string, string> */
    public array $nfeModalTotais = [];

    /** @var array<int, array<string, string>> */
    public array $nfeModalFaturas = [];

    /** @var array<int, array<string, string>> */
    public array $nfeModalReferencias = [];

    public string $nfeReferenciaInput = '';

    public int $nfeSelectedRowIndex = 0;

    public function createNfe(): void
    {
        if ($this->nfeModalOpen) {
            return;
        }

        $empresaId = $this->resolveEmpresaId();
        $params = $empresaId ? VendasParametro::forEmpresa($empresaId) : null;

        $this->nfeModalRecordId = null;
        $this->nfeModalStatus = 'ABERTA';
        $this->nfeModalMainTab = 'itens';
        $this->nfeModalDetailTab = 'totais';
        $this->nfeSelectedRowIndex = 0;
        $this->nfeReferenciaInput = '';
        $this->clearNfeItemEntryRow();
        $this->nfeForm = $this->defaultNfeFormData($params);
        $this->nfeModalRows = [];
        $this->nfeModalFaturas = [];
        $this->nfeModalReferencias = [];
        $this->nfeModalTotais = $this->defaultNfeModalTotais();
        $this->nfeModalOpen = true;
    }

    public function editNfe(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $nfe = Nfe::query()
            ->with(['cliente', 'itens.product', 'faturas', 'referencias', 'empresa', 'venda'])
            ->find($this->highlightedRecordId);

        if (! $nfe) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $this->loadNfeIntoModal($nfe);
    }

    public function closeNfeModal(): void
    {
        $this->nfeModalOpen = false;
        $this->nfeModalRecordId = null;
        $this->nfeModalStatus = 'ABERTA';
        $this->nfeModalMainTab = 'itens';
        $this->nfeModalDetailTab = 'totais';
        $this->nfeSelectedRowIndex = 0;
        $this->nfeReferenciaInput = '';
        $this->clearNfeItemEntryRow();
        $this->nfeForm = [];
        $this->nfeModalRows = [];
        $this->nfeModalFaturas = [];
        $this->nfeModalReferencias = [];
        $this->nfeModalTotais = [];
    }

    public function setNfeModalMainTab(string $tab): void
    {
        $allowed = ['itens', 'impostos', 'pagamento'];

        $this->nfeModalMainTab = in_array($tab, $allowed, true) ? $tab : 'itens';
    }

    public function setNfeModalDetailTab(string $tab): void
    {
        $allowed = ['totais', 'volumes', 'fisco', 'contribuinte', 'transportadora', 'referencia', 'contingencia'];

        $this->nfeModalDetailTab = in_array($tab, $allowed, true) ? $tab : 'totais';
    }

    public function addNfeReferencia(): void
    {
        $chave = preg_replace('/\D/', '', $this->nfeReferenciaInput) ?? '';

        if (strlen($chave) !== 44) {
            Notification::make()->title('Chave de referência deve ter 44 dígitos.')->warning()->send();

            return;
        }

        foreach ($this->nfeModalReferencias as $row) {
            if (($row['referencia'] ?? '') === $chave) {
                Notification::make()->title('Chave já informada.')->warning()->send();

                return;
            }
        }

        $this->nfeModalReferencias[] = ['referencia' => $chave];
        $this->nfeReferenciaInput = '';
    }

    public function removeNfeReferencia(int $index): void
    {
        if (! isset($this->nfeModalReferencias[$index])) {
            return;
        }

        array_splice($this->nfeModalReferencias, $index, 1);
    }

    public function gerarNfeParcelas(int $parcelas = 1): void
    {
        $parcelas = max(1, min(120, $parcelas));
        $total = ErpMoney::parseBr($this->nfeModalTotais['total'] ?? '0');

        if ($total <= 0) {
            Notification::make()->title('Informe itens antes de gerar parcelas.')->warning()->send();

            return;
        }

        $valorParcela = round($total / $parcelas, 2);
        $baseDate = $this->nfeForm['data_emissao'] ?? now()->format('Y-m-d');
        $this->nfeModalFaturas = [];

        for ($i = 1; $i <= $parcelas; $i++) {
            $vencimento = date('Y-m-d', strtotime($baseDate . ' +' . $i . ' month'));
            $valor = $i === $parcelas
                ? round($total - ($valorParcela * ($parcelas - 1)), 2)
                : $valorParcela;

            $this->nfeModalFaturas[] = [
                'numero' => str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'data_vencimento' => $vencimento,
                'valor' => ErpMoney::formatBr($valor),
            ];
        }
    }

    public function saveNfe(): void
    {
        $this->validate(
            [
                'nfeForm.cliente_id' => ['required', 'integer', 'exists:people,id'],
                'nfeForm.data_emissao' => ['required', 'date'],
                'nfeForm.data_saida' => ['nullable', 'date'],
            ],
            [],
            [
                'nfeForm.cliente_id' => 'cliente',
                'nfeForm.data_emissao' => 'data de emissão',
                'nfeForm.data_saida' => 'data de saída',
            ],
        );

        if ($this->nfeModalRows === []) {
            Notification::make()->title('Informe ao menos um item.')->warning()->send();

            return;
        }

        $empresaId = $this->resolveEmpresaId();
        $this->recalculateNfeTotais();

        $calculated = app(NfeCalculoService::class)->calcular(
            $this->nfeModalRows,
            $empresaId ? Empresa::query()->find($empresaId) : null,
            $this->nfeForm['uf'] ?? null,
        );

        $totais = $calculated['totais'];
        $isEditing = (bool) $this->nfeModalRecordId;
        $savedId = null;

        DB::transaction(function () use ($empresaId, $totais, $calculated, $isEditing, &$savedId): void {
            $params = $empresaId ? VendasParametro::forEmpresa($empresaId) : null;
            $numero = $isEditing
                ? $this->nfeForm['numero']
                : (string) ($params?->peekNumero() ?? Nfe::nextNumero($empresaId));

            $serie = $this->nfeForm['serie'] ?: (string) ($params?->serie ?? '1');

            if (! $isEditing && $empresaId) {
                $duplicada = Nfe::query()
                    ->where('empresa_id', $empresaId)
                    ->where('numero', $numero)
                    ->where('serie', $serie)
                    ->exists();

                if ($duplicada) {
                    Notification::make()->title('Número de NF-e já utilizado nesta série.')->danger()->send();

                    return;
                }
            }

            $payload = [
                'empresa_id' => $empresaId,
                'numero' => $numero,
                'serie' => $serie,
                'modelo' => '55',
                'data_emissao' => $this->nfeForm['data_emissao'],
                'data_saida' => $this->nfeForm['data_saida'] ?: $this->nfeForm['data_emissao'],
                'cliente_id' => (int) $this->nfeForm['cliente_id'],
                'npedido' => $this->nfeForm['numero_pedido'] ?: null,
                'cfop' => $calculated['cfop'],
                'finalidade' => $this->mapFinalidade($this->nfeForm['finalidade'] ?? 'normal'),
                'movimento' => ($this->nfeForm['movimento'] ?? 'saida') === 'entrada' ? '0' : '1',
                'consumidor_final' => ! empty($this->nfeForm['consumidor_final']) ? '1' : '0',
                'forma_pgto' => $this->nfeForm['forma_pgto'] ?? null,
                'meio_pgto' => $this->nfeForm['meio_pgto'] ?? null,
                'obs_fisco' => $this->nfeForm['obs_fisco'] ?? null,
                'obs_contribuinte' => $this->nfeForm['obs_contribuinte'] ?? null,
                'subtotal' => $totais['subtotal'],
                'desconto' => $totais['desconto'],
                'total' => $totais['total'],
                'total_itens' => count($calculated['rows']),
                'base_icms' => $totais['base_icms'],
                'total_icms' => $totais['valor_icms'],
                'base_ipi' => $totais['base_ipi'],
                'total_ipi' => $totais['valor_ipi'],
                'base_icms_pis' => $totais['base_pis'],
                'total_icms_pis' => $totais['valor_pis'],
                'base_icms_cofins' => $totais['base_cofins'],
                'total_icms_cofins' => $totais['valor_cofins'],
                'situacao' => Nfe::SITUACAO_ABERTA,
                'status' => Nfe::STATUS_ABERTA,
            ];

            if ($isEditing) {
                $nfe = Nfe::query()->find($this->nfeModalRecordId);

                if (! $nfe) {
                    Notification::make()->title('NF-e não encontrada.')->warning()->send();

                    return;
                }

                if ($nfe->situacao !== Nfe::SITUACAO_ABERTA) {
                    Notification::make()->title('Somente NF-e aberta pode ser alterada.')->warning()->send();

                    return;
                }

                $nfe->update($payload);
            } else {
                $nfe = Nfe::query()->create($payload);
                $params?->consumeNumero();
                $this->nfeModalRecordId = $nfe->id;
                $this->nfeForm['numero'] = $numero;
            }

            $nfe->itens()->delete();
            foreach ($calculated['rows'] as $row) {
                NfeItem::query()->create([
                    'nfe_id' => $nfe->id,
                    'item' => $row['item'],
                    'product_id' => $row['product_id'] ?? null,
                    'cod_barra' => $row['cod_barra'] ?? null,
                    'ncm' => $row['ncm'] ?? null,
                    'cfop' => $row['cfop'] ?? null,
                    'cst' => $row['cst'] ?? null,
                    'csosn' => $row['csosn'] ?? null,
                    'cest' => $row['cest'] ?? null,
                    'unidade' => $row['unidade'] ?? 'UN',
                    'descricao' => $row['descricao'] ?? '',
                    'info_adicionais' => $row['info_adicionais'] ?? null,
                    'quantidade' => $row['quantidade'],
                    'valor_unitario' => $row['valor_unitario'],
                    'desconto' => $row['desconto'] ?? 0,
                    'frete' => $row['frete'] ?? 0,
                    'seguro' => $row['seguro'] ?? 0,
                    'outros' => $row['outros'] ?? 0,
                    'total' => $row['total'],
                    'situacao' => Nfe::SITUACAO_ABERTA,
                    'base_icms' => $row['base_icms'] ?? 0,
                    'aliq_icms' => $row['aliq_icms'] ?? 0,
                    'valor_icms' => $row['valor_icms'] ?? 0,
                    'motivo_desoneracao' => filled($row['motivo_desoneracao'] ?? null) ? $row['motivo_desoneracao'] : null,
                    'base_desoneracao' => $row['base_desoneracao'] ?? 0,
                    'desc_desoneracao' => $row['desc_desoneracao'] ?? 0,
                    'valor_desoneracao' => $row['valor_desoneracao'] ?? 0,
                    'base_ipi' => $row['base_ipi'] ?? 0,
                    'aliq_ipi' => $row['aliq_ipi'] ?? 0,
                    'valor_ipi' => $row['valor_ipi'] ?? 0,
                    'cst_ipi' => $row['cst_ipi'] ?? null,
                    'cst_pis' => $row['cst_pis'] ?? null,
                    'base_pis_icms' => $row['base_pis_icms'] ?? 0,
                    'aliq_pis_icms' => $row['aliq_pis_icms'] ?? 0,
                    'valor_pis_icms' => $row['valor_pis_icms'] ?? 0,
                    'cst_cofins' => $row['cst_cofins'] ?? null,
                    'base_cofins_icms' => $row['base_cofins_icms'] ?? 0,
                    'aliq_cofins_icms' => $row['aliq_cofins_icms'] ?? 0,
                    'valor_cofins_icms' => $row['valor_cofins_icms'] ?? 0,
                    'class_trib' => filled($row['class_trib'] ?? null) ? $row['class_trib'] : null,
                    'cst_ibs_cbs' => filled($row['cst_ibs_cbs'] ?? null) ? $row['cst_ibs_cbs'] : null,
                    'v_ibs_mun' => $row['v_ibs_mun'] ?? 0,
                    'v_ibs_uf' => $row['v_ibs_uf'] ?? 0,
                    'v_cbs' => $row['v_cbs'] ?? 0,
                    'bc_ibs' => $row['bc_ibs'] ?? 0,
                    'alq_cbs' => $row['alq_cbs'] ?? 0,
                    'alq_ibs_mun' => $row['alq_ibs_mun'] ?? 0,
                    'alq_ibs_uf' => $row['alq_ibs_uf'] ?? 0,
                ]);
            }

            $nfe->faturas()->delete();
            foreach ($this->nfeModalFaturas as $fatura) {
                NfeFatura::query()->create([
                    'nfe_id' => $nfe->id,
                    'empresa_id' => $empresaId,
                    'numero' => $fatura['numero'],
                    'data_vencimento' => $fatura['data_vencimento'],
                    'valor' => ErpMoney::parseBr($fatura['valor'] ?? '0'),
                ]);
            }

            $nfe->referencias()->delete();
            foreach ($this->nfeModalReferencias as $referencia) {
                NfeReferencia::query()->create([
                    'nfe_id' => $nfe->id,
                    'referencia' => $referencia['referencia'],
                ]);
            }

            $savedId = $nfe->id;
            $this->nfeModalStatus = 'ABERTA';
        });

        if (! $savedId) {
            return;
        }

        Notification::make()
            ->title($isEditing ? 'NF-e gravada.' : 'NF-e incluída.')
            ->success()
            ->send();

        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($savedId);
    }

    public function transmitNfe(): void
    {
        $this->modulePending('Transmissão de NF-e');
    }

    public function importNfeModal(): void
    {
        $this->modulePending('Importação de NF-e');
    }

    public function openNfeProdutos(): void
    {
        $this->modulePending('Consulta de produtos');
    }

    public function openNfePessoas(): void
    {
        $this->modulePending('Consulta de pessoas');
    }

    public function openNfeTransportadora(): void
    {
        $this->modulePending('Transportadora');
    }

    public function updatedNfeFormClienteId(): void
    {
        $clienteId = (int) ($this->nfeForm['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            $this->nfeForm['uf'] = '';
            $this->nfeForm['cnpj'] = '';

            return;
        }

        $cliente = Person::query()->find($clienteId);

        $this->nfeForm['uf'] = $cliente?->uf ?? '';
        $this->nfeForm['cnpj'] = $this->formatNfeCpfCnpj($cliente?->cpf_cnpj);
        $this->recalculateNfeTotais();
    }

    public function updatedNfeFormFormaPgto(): void
    {
        if (($this->nfeForm['forma_pgto'] ?? '') === 'a_vista') {
            $total = $this->nfeModalTotais['total'] ?? '0,00';
            $this->nfeModalFaturas = [[
                'numero' => '001',
                'data_vencimento' => $this->nfeForm['data_emissao'] ?? now()->format('Y-m-d'),
                'valor' => $total,
            ]];
        }
    }

    protected function loadNfeIntoModal(Nfe $nfe): void
    {
        $this->nfeModalRecordId = $nfe->id;
        $this->nfeModalStatus = mb_strtoupper(Nfe::statusLabels()[$nfe->status] ?? $nfe->status, 'UTF-8');
        $this->nfeModalMainTab = 'itens';
        $this->nfeModalDetailTab = 'totais';
        $this->nfeSelectedRowIndex = 0;
        $this->nfeReferenciaInput = '';
        $this->clearNfeItemEntryRow();

        $this->nfeForm = [
            'numero' => $nfe->numero,
            'serie' => $nfe->serie,
            'empresa' => $nfe->empresa?->fantasia ?: ($nfe->empresa?->nome ?: $nfe->empresa?->razao_social ?: $this->empresaNome),
            'cliente_id' => (string) ($nfe->cliente_id ?? ''),
            'uf' => $nfe->cliente?->uf ?? '',
            'cnpj' => $this->formatNfeCpfCnpj($nfe->cliente?->cpf_cnpj),
            'natureza_operacao' => ($nfe->cfop ? $nfe->cfop . ' - ' : '') . 'VENDA DE MERCADORIA',
            'numero_pedido' => $nfe->npedido ?? ($nfe->venda?->numero ?? ''),
            'data_emissao' => $nfe->data_emissao?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'data_saida' => $nfe->data_saida?->format('Y-m-d') ?? now()->format('Y-m-d'),
            'consumidor_final' => $nfe->consumidor_final === '1' || $nfe->consumidor_final === true,
            'finalidade' => $this->unmapFinalidade((string) $nfe->finalidade),
            'movimento' => ($nfe->movimento ?? '1') === '0' ? 'entrada' : 'saida',
            'forma_pgto' => $nfe->forma_pgto ?? 'a_vista',
            'meio_pgto' => $nfe->meio_pgto ?? 'dinheiro',
            'obs_fisco' => $nfe->obs_fisco ?? '',
            'obs_contribuinte' => $nfe->obs_contribuinte ?? '',
        ];

        $this->nfeModalRows = $nfe->itens->map(fn (NfeItem $item): array => [
            'key' => 'item-' . $item->id,
            'product_id' => $item->product_id,
            'codigo' => $item->product?->codigo ?? '',
            'descricao' => $item->descricao,
            'info_adicionais' => $item->info_adicionais ?? '',
            'cfop' => $item->cfop,
            'cst' => $item->cst ?: $item->csosn,
            'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 4),
            'valor_unitario' => ErpMoney::formatBr((float) $item->valor_unitario, 4),
            'unidade' => $item->unidade ?? 'UN',
            'desconto' => ErpMoney::formatBr((float) $item->desconto, 2),
            'frete' => ErpMoney::formatBr((float) $item->frete, 2),
            'seguro' => ErpMoney::formatBr((float) $item->seguro, 2),
            'outros' => ErpMoney::formatBr((float) $item->outros, 2),
            'base_icms' => ErpMoney::formatBr((float) $item->base_icms, 2),
            'aliq_icms' => ErpMoney::formatBr((float) $item->aliq_icms, 4),
            'valor_icms' => ErpMoney::formatBr((float) $item->valor_icms, 2),
            'motivo_desoneracao' => $item->motivo_desoneracao ?? '',
            'base_desoneracao' => ErpMoney::formatBr((float) ($item->base_desoneracao ?? 0), 2),
            'desc_desoneracao' => ErpMoney::formatBr((float) ($item->desc_desoneracao ?? 0), 2),
            'valor_desoneracao' => ErpMoney::formatBr((float) ($item->valor_desoneracao ?? 0), 2),
            'aliq_ipi' => ErpMoney::formatBr((float) $item->aliq_ipi, 4),
            'valor_ipi' => ErpMoney::formatBr((float) $item->valor_ipi, 2),
            'aliq_pis_icms' => ErpMoney::formatBr((float) $item->aliq_pis_icms, 4),
            'valor_pis_icms' => ErpMoney::formatBr((float) $item->valor_pis_icms, 2),
            'aliq_cofins_icms' => ErpMoney::formatBr((float) $item->aliq_cofins_icms, 4),
            'valor_cofins_icms' => ErpMoney::formatBr((float) $item->valor_cofins_icms, 2),
            'class_trib' => $item->class_trib ?? '',
            'cst_ibs_cbs' => $item->cst_ibs_cbs ?? '',
            'v_ibs_mun' => ErpMoney::formatBr((float) ($item->v_ibs_mun ?? 0), 2),
            'v_ibs_uf' => ErpMoney::formatBr((float) ($item->v_ibs_uf ?? 0), 2),
            'v_cbs' => ErpMoney::formatBr((float) ($item->v_cbs ?? 0), 2),
            'bc_ibs' => ErpMoney::formatBr((float) ($item->bc_ibs ?? 0), 2),
            'alq_cbs' => ErpMoney::formatBr((float) ($item->alq_cbs ?? 0), 4),
            'alq_ibs_mun' => ErpMoney::formatBr((float) ($item->alq_ibs_mun ?? 0), 4),
            'alq_ibs_uf' => ErpMoney::formatBr((float) ($item->alq_ibs_uf ?? 0), 4),
        ])->all();

        $this->nfeModalFaturas = $nfe->faturas->map(fn (NfeFatura $fatura): array => [
            'numero' => $fatura->numero,
            'data_vencimento' => $fatura->data_vencimento?->format('Y-m-d') ?? '',
            'valor' => ErpMoney::formatBr($fatura->valor),
        ])->all();

        $this->nfeModalReferencias = $nfe->referencias->map(fn (NfeReferencia $ref): array => [
            'referencia' => $ref->referencia,
        ])->all();

        $this->recalculateNfeTotais();
        $this->nfeModalOpen = true;
    }

    protected function recalculateNfeTotais(): void
    {
        $empresaId = $this->resolveEmpresaId();
        $calculated = app(NfeCalculoService::class)->calcular(
            $this->nfeModalRows,
            $empresaId ? Empresa::query()->find($empresaId) : null,
            $this->nfeForm['uf'] ?? null,
        );

        $this->nfeModalRows = $this->formatNfeModalRowsForDisplay($calculated['rows']);
        $totais = $calculated['totais'];

        $this->nfeModalTotais = [
            'subtotal' => ErpMoney::formatBr($totais['subtotal']),
            'base_cofins' => ErpMoney::formatBr($totais['base_cofins']),
            'valor_cofins' => ErpMoney::formatBr($totais['valor_cofins']),
            'base_pis' => ErpMoney::formatBr($totais['base_pis']),
            'valor_pis' => ErpMoney::formatBr($totais['valor_pis']),
            'base_ipi' => ErpMoney::formatBr($totais['base_ipi']),
            'valor_ipi' => ErpMoney::formatBr($totais['valor_ipi']),
            'frete' => ErpMoney::formatBr($totais['frete']),
            'seguro' => ErpMoney::formatBr($totais['seguro']),
            'outras' => ErpMoney::formatBr($totais['outras']),
            'desconto' => ErpMoney::formatBr($totais['desconto']),
            'desoneracao' => ErpMoney::formatBr($totais['desoneracao']),
            'base_icms' => ErpMoney::formatBr($totais['base_icms']),
            'valor_icms' => ErpMoney::formatBr($totais['valor_icms']),
            'base_st' => ErpMoney::formatBr($totais['base_st']),
            'valor_st' => ErpMoney::formatBr($totais['valor_st']),
            'total' => ErpMoney::formatBr($totais['total']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultNfeFormData(?VendasParametro $params = null): array
    {
        $today = now()->format('Y-m-d');
        $empresaId = $this->resolveEmpresaId();

        return [
            'numero' => Nfe::nextNumero($empresaId),
            'serie' => (string) ($params?->serie ?? '1'),
            'empresa' => $this->empresaNome,
            'cliente_id' => '',
            'uf' => '',
            'cnpj' => '',
            'natureza_operacao' => '5102 - VENDA DE MERCADORIA ADQUIRIDA OU RECEBIDA DE TERCEIROS',
            'numero_pedido' => '',
            'data_emissao' => $today,
            'data_saida' => $today,
            'consumidor_final' => false,
            'finalidade' => 'normal',
            'movimento' => 'saida',
            'forma_pgto' => 'a_vista',
            'meio_pgto' => 'dinheiro',
            'obs_fisco' => '',
            'obs_contribuinte' => '',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function defaultNfeModalTotais(): array
    {
        return [
            'subtotal' => '0,00',
            'base_cofins' => '0,00',
            'valor_cofins' => '0,00',
            'base_pis' => '0,00',
            'valor_pis' => '0,00',
            'base_ipi' => '0,00',
            'valor_ipi' => '0,00',
            'frete' => '0,00',
            'seguro' => '0,00',
            'outras' => '0,00',
            'desconto' => '0,00',
            'desoneracao' => '0,00',
            'base_icms' => '0,00',
            'valor_icms' => '0,00',
            'base_st' => '0,00',
            'valor_st' => '0,00',
            'total' => '0,00',
        ];
    }

    protected function mapFinalidade(string $value): string
    {
        return match ($value) {
            'complementar' => '2',
            'ajuste' => '3',
            'devolucao' => '4',
            default => '1',
        };
    }

    protected function unmapFinalidade(string $value): string
    {
        return match ($value) {
            '2' => 'complementar',
            '3' => 'ajuste',
            '4' => 'devolucao',
            default => 'normal',
        };
    }

    protected function resolveEmpresaId(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        if ($empresaId) {
            return (int) $empresaId;
        }

        return Empresa::query()->where('ativo', true)->orderBy('id')->value('id');
    }

    protected function formatNfeCpfCnpj(?string $value): string
    {
        if (! filled($value)) {
            return '';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (strlen($digits) === 14) {
            return preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/', '$1.$2.$3/$4-$5', $digits) ?: $value;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $digits) ?: $value;
        }

        return $value;
    }

    protected function parseMoney(string $value): float
    {
        return ErpMoney::parseBr($value);
    }
}
