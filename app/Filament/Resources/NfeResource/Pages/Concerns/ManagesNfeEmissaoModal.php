<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Cfop;
use App\Models\DevolucaoCompra;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\OperacaoFiscal;
use App\Models\NfeReferencia;
use App\Models\Person;
use App\Models\Product;
use App\Models\Transportadora;
use App\Models\VendasParametro;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\CepLookupService;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Nfe\NfeCalculoService;
use App\Support\Erp\Nfe\NfeDanfeReportService;
use App\Support\Erp\Nfe\NfeEspelhoReportService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use App\Support\Erp\Pdv\PdvNfceFiscalMensagens;
use App\Support\Erp\WhatsApp\WhatsAppMessageHelper;
use App\Support\Erp\WhatsApp\WhatsAppPhone;
use App\Support\Erp\WhatsApp\WhatsAppSender;
use App\Support\Fiscal\NfeEmissionService;
use Filament\Notifications\Notification;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;

trait ManagesNfeEmissaoModal
{
    use ManagesNfeItemGrid;

    public bool $nfeModalOpen = false;

    public ?int $nfeModalRecordId = null;

    public string $nfeModalStatus = 'ABERTA';

    public bool $nfeModalHomologacao = false;

    public string $nfeModalMainTab = 'itens';

    public string $nfeModalDetailTab = 'totais';

    /** @var array<string, mixed> */
    public array $nfeForm = [];

    /** @var array<int, array<string, mixed>> */
    public array $nfeModalRows = [];

    /** @var array<string, string> */
    public array $nfeModalTotais = [];

    public bool $nfeSkipTotaisBlur = false;

    /** @var array<int, array<string, string>> */
    public array $nfeModalFaturas = [];

    /** @var array<int, array<string, string>> */
    public array $nfeModalReferencias = [];

    public string $nfeReferenciaInput = '';

    public int $nfeSelectedRowIndex = 0;

    public ?string $nfeFiscalOverlayTitulo = null;

    public ?string $nfeFiscalOverlayMensagem = null;

    public ?string $nfeFiscalOverlayCodigo = null;

    public ?string $nfeFiscalSucessoDetalhe = null;

    public ?int $nfeFiscalSucessoNfeId = null;

    public ?string $nfeFiscalInfoTitulo = null;

    public ?string $nfeFiscalInfoMensagem = null;

    public bool $nfeWhatsAppModalOpen = false;

    public ?int $nfeWhatsAppNfeId = null;

    public string $nfeWhatsAppTo = '';

    public string $nfeWhatsAppMessage = '';

    public ?string $nfeWhatsAppPdfPath = null;

    public string $nfeWhatsAppPdfName = '';

    public string $nfeWhatsAppPdfDisplay = '';

    public ?string $nfeWhatsAppXmlPath = null;

    public string $nfeWhatsAppXmlName = '';

    public string $nfeWhatsAppXmlDisplay = '';

    public string $nfeWhatsAppDocumento = 'danfe';

    public string $nfeWhatsAppDestinatario = 'cliente';

    public string $nfeClienteCodigo = '';

    public string $nfeClienteNome = '';

    public string $nfeClienteBusca = '';

    /** @var list<array{id: int, codigo: string, nome: string, cpf_cnpj: string}> */
    public array $nfeClienteSugestoes = [];

    public bool $nfeClienteSugestoesOpen = false;

    public int $nfeSelectedClienteSugestaoIndex = 0;

    public string $nfeClienteFone = '';

    public string $nfeClienteWhatsapp = '';

    public string $nfeClienteEndereco = '';

    public string $nfeClienteNumeroEnd = '';

    public string $nfeClienteBairro = '';

    public string $nfeClienteCep = '';

    public string $nfeClienteCidade = '';

    public string $nfeTransportadoraBusca = '';

    /** @var list<array{id: int, codigo: string, nome: string, cpf_cnpj: string, doc_tipo: string}> */
    public array $nfeTransportadoraSugestoes = [];

    public bool $nfeTransportadoraSugestoesOpen = false;

    public int $nfeSelectedTransportadoraSugestaoIndex = 0;

    /** @var list<array{codigo: string, label: string}> */
    public array $nfeNaturezaSugestoes = [];

    public bool $nfeNaturezaSugestoesOpen = false;

    public int $nfeSelectedNaturezaIndex = 0;

    public function createNfe(): void
    {
        if ($this->nfeModalOpen) {
            return;
        }

        $empresaId = $this->resolveEmpresaId();
        $params = $empresaId ? VendasParametro::forEmpresa($empresaId) : null;

        $this->nfeModalRecordId = null;
        $this->nfeModalStatus = 'ABERTA';
        $this->nfeModalVendaId = null;
        $this->nfeModalPdvVendaId = null;
        $this->nfeModalDevolucaoCompraId = null;
        $this->syncNfeModalAmbiente($params);
        $this->nfeModalMainTab = 'itens';
        $this->nfeModalDetailTab = 'totais';
        $this->nfeSelectedRowIndex = 0;
        $this->nfeReferenciaInput = '';
        $this->clearNfeItemEntryRow();
        $this->clearNfeClienteDisplay();
        $this->clearNfeTransportadoraDisplay();
        $this->nfeForm = $this->defaultNfeFormData($params);
        $this->nfeModalRows = [];
        $this->nfeModalFaturas = [];
        $this->nfeModalReferencias = [];
        $this->nfeModalTotais = $this->defaultNfeModalTotais();
        $this->nfeModalOpen = true;
        $this->dispatch('erp-nfe-focus-cliente');
    }

    public function editNfe(): void
    {
        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $nfe = Nfe::query()
            ->with(['cliente', 'transportadora', 'itens.product', 'faturas', 'referencias', 'empresa', 'venda'])
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
        $this->closeNfeFiscalOverlay();
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();
        $this->closeNfeWhatsAppModal();
        $this->closeNfeCancelModal();
        $this->closeNfeCceModal();
        $this->closeNfeCceSucessoOverlay();
        $this->closeNfeCceDispatchModals();
        $this->closeNfeImportModals();
        $this->nfeItemDeleteConfirmIndex = null;
        $this->fecharNfeModalDescontoItem();
        $this->fecharNfeInfoAdicionaisModal();
        $this->nfeModalOpen = false;
        $this->nfeModalRecordId = null;
        $this->nfeModalStatus = 'ABERTA';
        $this->nfeModalVendaId = null;
        $this->nfeModalPdvVendaId = null;
        $this->nfeModalDevolucaoCompraId = null;
        $this->nfeModalHomologacao = false;
        $this->nfeModalMainTab = 'itens';
        $this->nfeModalDetailTab = 'totais';
        $this->nfeSelectedRowIndex = 0;
        $this->nfeReferenciaInput = '';
        $this->clearNfeItemEntryRow();
        $this->clearNfeClienteDisplay();
        $this->fecharNfeSugestoesNatureza();
        $this->nfeForm = [];
        $this->nfeModalRows = [];
        $this->nfeModalFaturas = [];
        $this->nfeModalReferencias = [];
        $this->nfeModalTotais = [];
        $this->clearNfeSelectedRowFoto();
    }

    public function setNfeModalMainTab(string $tab): void
    {
        $allowed = ['itens', 'impostos', 'pagamento'];

        $this->nfeModalMainTab = in_array($tab, $allowed, true) ? $tab : 'itens';
    }

    public function setNfeModalDetailTab(string $tab): void
    {
        if ($tab === 'volumes') {
            $tab = 'transportadora';
        }

        $allowed = ['totais', 'fisco', 'contribuinte', 'transportadora', 'referencia', 'contingencia'];

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
        $baseDate = $this->nfeForm['data_emissao'] ?? ErpTimezone::today();
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

    public function saveNfe(bool $quiet = false): bool
    {
        $this->ensureNfeDevolucaoVinculos();

        $clienteId = (int) ($this->nfeForm['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            Notification::make()->title('Informe o Cliente!')->warning()->send();
            $this->dispatch('erp-nfe-focus-cliente');

            return false;
        }

        $cliente = Person::query()->find($clienteId);

        if (! $cliente || ! CepLookupService::isValidIbgeCode($cliente->cidade_codigo)) {
            Notification::make()
                ->title('Código IBGE da cidade do cliente inválido')
                ->body('Abra o cadastro do cliente, informe o CEP e clique em Pesquisar CEP antes de emitir a NF-e.')
                ->danger()
                ->send();
            $this->dispatch('erp-nfe-focus-cliente');

            return false;
        }

        if ($this->nfeModalRows === []) {
            Notification::make()->title('Informe os Itens da NF-e!')->warning()->send();
            $this->dispatch('erp-nfe-focus-item-produto');

            return false;
        }

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

        $empresaId = $this->resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;
        try {
            $this->validarDestinatarioProprioParaPerda($empresa, $clienteId);
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return false;
        }
        $this->syncNfeModalTotaisExtrasToItens();
        $this->recalculateNfeTotais();

        $calculated = app(NfeCalculoService::class)->calcular(
            $this->nfeModalRows,
            $empresaId ? Empresa::query()->find($empresaId) : null,
            $this->nfeForm['uf'] ?? null,
        );

        $totais = $calculated['totais'];
        $this->syncNfeModalFaturasAVista();
        $isEditing = (bool) $this->nfeModalRecordId;
        $savedId = null;

        DB::transaction(function () use ($empresaId, $totais, $calculated, $isEditing, &$savedId): void {
            $params = $empresaId ? VendasParametro::forEmpresa($empresaId) : null;
            $numero = $isEditing
                ? $this->nfeForm['numero']
                : (string) ($params?->peekNumeroNfe() ?? Nfe::nextNumero($empresaId));

            $serie = $this->nfeForm['serie'] ?: (string) ($params?->serie_nfe ?? 1);

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
                'venda_id' => $this->nfeModalVendaId ?: null,
                'pdv_venda_id' => $this->nfeModalPdvVendaId ?: null,
                'devolucao_compra_id' => $this->nfeModalDevolucaoCompraId ?: null,
                'cfop' => $calculated['cfop'],
                'finalidade' => $this->mapFinalidade($this->nfeForm['finalidade'] ?? 'normal'),
                'movimento' => ($this->nfeForm['movimento'] ?? 'saida') === 'entrada' ? '0' : '1',
                'consumidor_final' => ! empty($this->nfeForm['consumidor_final']) ? '1' : '0',
                'forma_pgto' => $this->nfeForm['forma_pgto'] ?? null,
                'meio_pgto' => $this->nfeForm['meio_pgto'] ?? null,
                'obs_fisco' => $this->nfeForm['obs_fisco'] ?? null,
                'obs_contribuinte' => $this->mergeObsContribuinteWithIbpt(
                    (string) ($this->nfeForm['obs_contribuinte'] ?? ''),
                    (string) ($totais['ibpt_texto'] ?? ''),
                ),
                'tipo_frete' => (string) ($this->nfeForm['tipo_frete'] ?? '9'),
                'transportadora_id' => ((int) ($this->nfeForm['transportadora_id'] ?? 0)) ?: null,
                'placa' => filled($this->nfeForm['placa'] ?? null)
                    ? mb_strtoupper(trim((string) $this->nfeForm['placa']), 'UTF-8')
                    : null,
                'uf_placa' => filled($this->nfeForm['uf_placa'] ?? null)
                    ? mb_strtoupper(trim((string) $this->nfeForm['uf_placa']), 'UTF-8')
                    : null,
                'especie' => filled($this->nfeForm['especie'] ?? null)
                    ? mb_strtoupper(trim((string) $this->nfeForm['especie']), 'UTF-8')
                    : null,
                'marca' => filled($this->nfeForm['marca'] ?? null)
                    ? mb_strtoupper(trim((string) $this->nfeForm['marca']), 'UTF-8')
                    : null,
                'nvol' => filled($this->nfeForm['nvol'] ?? null)
                    ? trim((string) $this->nfeForm['nvol'])
                    : null,
                'qvol' => max(0, (int) preg_replace('/\D/', '', (string) ($this->nfeForm['qvol'] ?? '0'))),
                'peso_l' => ErpMoney::parseBr((string) ($this->nfeForm['peso_l'] ?? '0'), 3),
                'peso_b' => ErpMoney::parseBr((string) ($this->nfeForm['peso_b'] ?? '0'), 3),
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
                'base_icms_st' => $totais['base_st'],
                'valor_icms_st' => $totais['valor_st'],
                'frete' => $totais['frete'],
                'seguro' => $totais['seguro'],
                'outros' => $totais['outras'],
                'total_desoneracao' => $totais['desoneracao'],
                'trib_fed' => $totais['trib_fed'] ?? 0,
                'trib_est' => $totais['trib_est'] ?? 0,
                'trib_mun' => $totais['trib_mun'] ?? 0,
                'trib_imp' => $totais['trib_imp'] ?? 0,
                'situacao' => Nfe::SITUACAO_ABERTA,
                'status' => Nfe::STATUS_ABERTA,
                'chave_nfe_referenciada' => $this->primeiraChaveReferenciaModal(),
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
                $params?->consumeNumeroNfe();
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
                    'trib_fed' => $row['trib_fed'] ?? 0,
                    'trib_est' => $row['trib_est'] ?? 0,
                    'trib_mun' => $row['trib_mun'] ?? 0,
                    'trib_imp' => $row['trib_imp'] ?? 0,
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
            return false;
        }

        // Garante F3 Transmitir liberado após F2 (nova ou edição).
        $this->nfeModalRecordId = (int) $savedId;
        $this->nfeModalStatus = 'ABERTA';

        if (! $quiet) {
            Notification::make()
                ->title($isEditing ? 'NF-e gravada.' : 'NF-e incluída.')
                ->success()
                ->send();
        }

        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($savedId);
        $this->dispatch('erp-nfe-sync-transmit-btn');

        NfeEventoLogger::registrar(
            nfeId: $savedId,
            tipo: $isEditing ? NfeEvento::TIPO_EDITADA : NfeEvento::TIPO_CRIADA,
            titulo: $isEditing ? 'NF-e alterada' : 'NF-e criada',
            descricao: $isEditing
                ? 'Dados da nota fiscal foram atualizados no sistema.'
                : 'NF-e incluída no sistema em situação aberta.',
        );

        return true;
    }

    /**
     * Grava rascunho após mount (ex.: ?devolucao_compra_id=) e recarrega o modal do banco,
     * para não perder referência/itens no próximo save/transmit.
     */
    public function saveNfeDraftFromMount(): bool
    {
        if (! $this->saveNfe(quiet: true)) {
            return false;
        }

        if (! $this->nfeModalRecordId) {
            return false;
        }

        $nfe = Nfe::query()
            ->with(['cliente', 'transportadora', 'itens.product', 'faturas', 'referencias', 'empresa', 'venda', 'devolucaoCompra.compra'])
            ->find($this->nfeModalRecordId);

        if ($nfe) {
            $this->loadNfeIntoModal($nfe);
        }

        return true;
    }

    /**
     * Evita apagar chave referenciada / vínculo da devolução quando o estado do modal
     * chegou incompleto (ex.: regravação no Transmitir após abrir via query string).
     */
    protected function ensureNfeDevolucaoVinculos(): void
    {
        if (($this->nfeForm['finalidade'] ?? '') !== 'devolucao') {
            return;
        }

        if (! $this->nfeModalDevolucaoCompraId && $this->nfeModalRecordId) {
            $existingDevId = (int) (Nfe::query()->whereKey($this->nfeModalRecordId)->value('devolucao_compra_id') ?? 0);

            if ($existingDevId > 0) {
                $this->nfeModalDevolucaoCompraId = $existingDevId;
            }
        }

        if ($this->nfeModalReferencias !== []) {
            return;
        }

        if ($this->nfeModalRecordId) {
            $refs = NfeReferencia::query()
                ->where('nfe_id', $this->nfeModalRecordId)
                ->pluck('referencia')
                ->all();

            foreach ($refs as $ref) {
                $digits = preg_replace('/\D/', '', (string) $ref) ?? '';

                if (strlen($digits) === 44) {
                    $this->nfeModalReferencias[] = ['referencia' => $digits];
                }
            }

            if ($this->nfeModalReferencias !== []) {
                return;
            }

            $chaveColuna = preg_replace(
                '/\D/',
                '',
                (string) (Nfe::query()->whereKey($this->nfeModalRecordId)->value('chave_nfe_referenciada') ?? ''),
            ) ?? '';

            if (strlen($chaveColuna) === 44) {
                $this->nfeModalReferencias[] = ['referencia' => $chaveColuna];

                return;
            }
        }

        $devolucaoId = (int) ($this->nfeModalDevolucaoCompraId ?? 0);

        if ($devolucaoId <= 0) {
            return;
        }

        $chaveCompra = preg_replace(
            '/\D/',
            '',
            (string) (DevolucaoCompra::query()->with('compra')->find($devolucaoId)?->compra?->chave_nfe ?? ''),
        ) ?? '';

        if (strlen($chaveCompra) === 44) {
            $this->nfeModalReferencias[] = ['referencia' => $chaveCompra];
        }
    }

    public function transmitNfe(): void
    {
        if (! $this->nfeModalRecordId) {
            Notification::make()
                ->title('Grave a NF-e antes de transmitir.')
                ->warning()
                ->send();

            return;
        }

        try {
            $savedOk = $this->saveNfe(quiet: true);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->dispatch('erp-nfe-hide-fiscal-progress');

            throw $e;
        }

        if (! $savedOk || ! $this->nfeModalRecordId) {
            $this->dispatch('erp-nfe-hide-fiscal-progress');

            return;
        }

        $nfe = Nfe::query()->with(['itens.product', 'faturas', 'cliente', 'referencias', 'devolucaoCompra.compra'])->find($this->nfeModalRecordId);
        $empresaId = $this->resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        if (! $nfe || ! $empresa) {
            Notification::make()
                ->title('Não foi possível localizar os dados para transmissão.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-hide-fiscal-progress');

            return;
        }

        if ($nfe->status !== Nfe::STATUS_ABERTA) {
            Notification::make()
                ->title('Somente NF-e aberta pode ser transmitida.')
                ->warning()
                ->send();
            $this->dispatch('erp-nfe-hide-fiscal-progress');

            return;
        }

        try {
            $nfe = (new NfeEmissionService())->transmitir($nfe, $empresa);
            $this->loadNfeIntoModal($nfe);
            $this->resetTable();
            $this->showNfeFiscalOverlaySucesso($nfe);
        } catch (FiscalEngineException $exception) {
            $this->registrarFalhaTransmissaoNfe((int) $nfe->id, $exception->getMessage(), $exception->sefazCodigo);
            $this->showNfeFiscalOverlayErro($exception);
        } catch (\Throwable $exception) {
            $this->registrarFalhaTransmissaoNfe((int) $nfe->id, $exception->getMessage());
            $this->showNfeFiscalOverlayErroGenerico($exception->getMessage());
        } finally {
            $this->dispatch('erp-nfe-hide-fiscal-progress');
        }
    }

    protected function registrarFalhaTransmissaoNfe(int $nfeId, string $mensagem, ?string $codigo = null): void
    {
        if ($nfeId <= 0) {
            return;
        }

        $descricao = trim($mensagem);
        if (filled($codigo)) {
            $descricao = trim("cStat {$codigo}. {$descricao}");
        }

        try {
            NfeEventoLogger::registrar(
                nfeId: $nfeId,
                tipo: NfeEvento::TIPO_ERRO_TRANSMISSAO,
                titulo: 'Falha na transmissão',
                descricao: $descricao !== '' ? $descricao : 'Erro desconhecido na transmissão.',
                metadata: filled($codigo) ? ['cStat' => $codigo] : null,
            );
        } catch (\Throwable) {
            // Não bloquear overlay de erro se o log falhar.
        }

        \Illuminate\Support\Facades\Log::warning('NF-e transmissão falhou', [
            'nfe_id' => $nfeId,
            'cStat' => $codigo,
            'message' => $mensagem,
        ]);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function nfeFretePorContaOptions(): array
    {
        return [
            '9' => '9 - SEM FRETE',
            '0' => '0 - EMITENTE',
            '1' => '1 - DESTINATÁRIO',
            '2' => '2 - TERCEIROS',
            '3' => '3 - PRÓPRIO REMETENTE',
            '4' => '4 - PRÓPRIO DESTINATÁRIO',
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function nfeUfPlacaOptions(): array
    {
        return Person::ufs();
    }

    public function imprimirNfeEtiquetaVolume(): void
    {
        if (! $this->nfeModalRecordId) {
            Notification::make()
                ->title('Grave a NF-e (F2) antes de imprimir a etiqueta.')
                ->warning()
                ->send();

            return;
        }

        $this->saveNfe();

        if (! $this->nfeModalRecordId) {
            return;
        }

        $url = route('erp.reports.nfe-etiqueta-volume', [
            'nfe' => $this->nfeModalRecordId,
        ]);

        $this->js('window.ErpNfePrint?.openDanfe(' . Js::from($url) . ')');
    }

    public function updatedNfeFormTransportadoraCodigo(): void
    {
        $codigo = trim((string) ($this->nfeForm['transportadora_codigo'] ?? ''));

        if ($codigo === '' || $codigo === '0') {
            $this->clearNfeTransportadoraDisplay();

            return;
        }

        $this->resolverNfeTransportadoraPorCodigo();
    }

    public function updatedNfeTransportadoraBusca(string $value): void
    {
        $term = trim($value);

        if ($term === '') {
            $this->fecharNfeSugestoesTransportadora();

            return;
        }

        if ($this->nfeTransportadoraJaSelecionadaCorresponde($term)) {
            $this->fecharNfeSugestoesTransportadora();

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';
        $termUpper = mb_strtoupper($term, 'UTF-8');
        $likeContains = '%'.$termUpper.'%';
        $likeStarts = $termUpper.'%';
        $codigoExato = ltrim($term, '0') ?: $term;

        $rows = Transportadora::query()
            ->where('ativo', true)
            ->where(function ($q) use ($likeStarts, $likeContains, $digits): void {
                $q->where('codigo', 'like', $likeStarts)
                    ->orWhereRaw('UPPER(proprietario) LIKE ?', [$likeContains])
                    ->orWhereRaw("UPPER(COALESCE(apelido, '')) LIKE ?", [$likeContains]);

                if ($digits !== '') {
                    $q->orWhere('cnpj_cpf', 'like', '%'.$digits.'%');
                }
            })
            ->orderByRaw(
                "CASE
                    WHEN codigo = ? OR codigo = ? THEN 0
                    WHEN codigo LIKE ? THEN 1
                    WHEN UPPER(proprietario) LIKE ? THEN 2
                    WHEN UPPER(COALESCE(apelido, '')) LIKE ? THEN 3
                    WHEN cnpj_cpf LIKE ? THEN 4
                    ELSE 5
                END",
                [
                    $term,
                    $codigoExato,
                    $likeStarts,
                    $likeStarts,
                    $likeStarts,
                    $digits !== '' ? $digits.'%' : '__never__',
                ]
            )
            ->orderBy('proprietario')
            ->limit(12)
            ->get(['id', 'codigo', 'proprietario', 'apelido', 'cnpj_cpf']);

        $this->nfeTransportadoraSugestoes = $rows
            ->map(function (Transportadora $t): array {
                $doc = (string) ($t->cnpj_cpf ?? '');
                $digitsOnly = preg_replace('/\D/', '', $doc) ?: '';

                return [
                    'id' => (int) $t->id,
                    'codigo' => (string) ($t->codigo ?? ''),
                    'nome' => mb_strtoupper(trim((string) ($t->proprietario ?: $t->apelido ?: '—')), 'UTF-8'),
                    'cpf_cnpj' => $this->formatNfeCpfCnpj($doc),
                    'doc_tipo' => strlen($digitsOnly) > 11 ? 'cnpj' : (strlen($digitsOnly) >= 11 ? 'cpf' : 'outro'),
                ];
            })
            ->values()
            ->all();
        $this->nfeTransportadoraSugestoesOpen = $this->nfeTransportadoraSugestoes !== [];
        $this->nfeSelectedTransportadoraSugestaoIndex = 0;
    }

    public function confirmarNfeTransportadoraBusca(): void
    {
        $term = trim($this->nfeTransportadoraBusca);

        if ($term === '') {
            $this->fecharNfeSugestoesTransportadora();

            return;
        }

        if ($this->nfeTransportadoraJaSelecionadaCorresponde($term)) {
            $this->fecharNfeSugestoesTransportadora();

            return;
        }

        if (preg_match('/^\d+$/', $term)) {
            $this->nfeForm['transportadora_codigo'] = $term;
            $this->resolverNfeTransportadoraPorCodigo();

            if ((int) ($this->nfeForm['transportadora_id'] ?? 0) > 0) {
                return;
            }
        }

        if (preg_match('/^(\d+)\s*[—\-]\s*(.*)$/u', $term, $m)) {
            $this->nfeForm['transportadora_codigo'] = $m[1];
            $this->resolverNfeTransportadoraPorCodigo();

            return;
        }

        if ($this->nfeTransportadoraSugestoesOpen && $this->nfeTransportadoraSugestoes !== []) {
            $index = $this->nfeSelectedTransportadoraSugestaoIndex;
            if (! isset($this->nfeTransportadoraSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarNfeTransportadora((int) $this->nfeTransportadoraSugestoes[$index]['id']);

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';
        $row = Transportadora::query()
            ->where('ativo', true)
            ->where(function ($q) use ($term, $digits): void {
                $q->where('codigo', $term)
                    ->orWhere('codigo', ltrim($term, '0'))
                    ->orWhere('proprietario', $term);

                if ($digits !== '' && strlen($digits) >= 11) {
                    $q->orWhere('cnpj_cpf', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('proprietario')
            ->first();

        if (! $row) {
            Notification::make()->title('Transportador não encontrado.')->warning()->send();

            return;
        }

        $this->selecionarNfeTransportadora((int) $row->id);
    }

    public function resolverNfeTransportadoraPorCodigo(): void
    {
        $codigo = trim((string) ($this->nfeForm['transportadora_codigo'] ?? ''));

        if ($codigo === '' || $codigo === '0') {
            $this->clearNfeTransportadoraDisplay();

            return;
        }

        $row = Transportadora::query()
            ->where('ativo', true)
            ->where(function ($q) use ($codigo): void {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', ltrim($codigo, '0'));
            })
            ->first();

        if (! $row) {
            Notification::make()->title('Transportador não encontrado.')->warning()->send();
            $this->nfeForm['transportadora_id'] = '';
            $this->nfeTransportadoraBusca = '';
            $this->fecharNfeSugestoesTransportadora();

            return;
        }

        $this->aplicarNfeTransportadora($row, syncBusca: true);
    }

    public function selecionarNfeTransportadora(int $id): void
    {
        $row = Transportadora::query()
            ->where('ativo', true)
            ->find($id);

        if (! $row) {
            Notification::make()->title('Transportador não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarNfeTransportadora($row, syncBusca: true);
        $this->fecharNfeSugestoesTransportadora();
    }

    public function moverNfeSugestaoTransportadora(int $delta): void
    {
        if (! $this->nfeTransportadoraSugestoesOpen || $this->nfeTransportadoraSugestoes === []) {
            return;
        }

        $count = count($this->nfeTransportadoraSugestoes);
        $index = $this->nfeSelectedTransportadoraSugestaoIndex + $delta;
        $this->nfeSelectedTransportadoraSugestaoIndex = max(0, min($count - 1, $index));
    }

    public function fecharNfeSugestoesTransportadora(): void
    {
        $this->nfeTransportadoraSugestoes = [];
        $this->nfeTransportadoraSugestoesOpen = false;
        $this->nfeSelectedTransportadoraSugestaoIndex = 0;
    }

    protected function clearNfeTransportadoraDisplay(): void
    {
        $this->nfeForm['transportadora_id'] = '';
        $this->nfeForm['transportadora_codigo'] = '0';
        $this->nfeTransportadoraBusca = '';
        $this->fecharNfeSugestoesTransportadora();
    }

    protected function aplicarNfeTransportadora(Transportadora $transportadora, bool $syncBusca = true): void
    {
        $this->nfeForm['transportadora_id'] = (string) $transportadora->id;
        $this->nfeForm['transportadora_codigo'] = (string) ($transportadora->codigo ?: '0');

        if ($syncBusca) {
            $this->nfeTransportadoraBusca = $this->formatarNfeTransportadoraBusca($transportadora);
        }
    }

    protected function formatarNfeTransportadoraBusca(Transportadora $transportadora): string
    {
        $codigo = trim((string) ($transportadora->codigo ?? ''));
        $nome = mb_strtoupper(trim((string) ($transportadora->proprietario ?: $transportadora->apelido ?: '')), 'UTF-8');

        if ($codigo !== '' && $nome !== '') {
            return $codigo.' — '.$nome;
        }

        return $nome !== '' ? $nome : $codigo;
    }

    protected function nfeTransportadoraJaSelecionadaCorresponde(string $term): bool
    {
        $id = (int) ($this->nfeForm['transportadora_id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        $termNorm = mb_strtoupper(trim($term), 'UTF-8');
        $buscaNorm = mb_strtoupper(trim($this->nfeTransportadoraBusca), 'UTF-8');

        if ($termNorm !== '' && $termNorm === $buscaNorm) {
            return true;
        }

        $codigo = trim((string) ($this->nfeForm['transportadora_codigo'] ?? ''));

        return $codigo !== '' && $codigo !== '0' && (
            $termNorm === mb_strtoupper($codigo, 'UTF-8')
            || $termNorm === mb_strtoupper(ltrim($codigo, '0'), 'UTF-8')
        );
    }

    public function updatedNfeFormClienteId(): void
    {
        $clienteId = (int) ($this->nfeForm['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            $this->clearNfeClienteDisplay(keepBusca: true);
            $this->nfeForm['uf'] = '';
            $this->nfeForm['cnpj'] = '';

            return;
        }

        $cliente = Person::query()->find($clienteId);

        if (! $cliente) {
            $this->clearNfeClienteDisplay(keepBusca: true);

            return;
        }

        $this->aplicarNfeCliente($cliente, syncBusca: $this->nfeClienteBusca === '');
        $this->recalculateNfeTotais();
    }

    public function updatedNfeClienteBusca(string $value): void
    {
        $term = trim($value);

        if ($term === '') {
            $this->fecharNfeSugestoesCliente();

            return;
        }

        if ($this->nfeClienteJaSelecionadoCorresponde($term)) {
            $this->fecharNfeSugestoesCliente();

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';
        $termUpper = mb_strtoupper($term, 'UTF-8');
        $likeContains = '%'.$termUpper.'%';
        $likeStarts = $termUpper.'%';
        $codigoExato = ltrim($term, '0') ?: $term;

        $people = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where(function ($q) use ($likeStarts, $likeContains, $digits): void {
                $q->where('codigo', 'like', $likeStarts)
                    ->orWhereRaw('UPPER(nome_razao) LIKE ?', [$likeContains])
                    ->orWhereRaw('UPPER(apelido_fantasia) LIKE ?', [$likeContains]);

                if ($digits !== '') {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderByRaw(
                'CASE
                    WHEN codigo = ? OR codigo = ? THEN 0
                    WHEN codigo LIKE ? THEN 1
                    WHEN UPPER(nome_razao) LIKE ? THEN 2
                    WHEN UPPER(apelido_fantasia) LIKE ? THEN 3
                    WHEN cpf_cnpj LIKE ? THEN 4
                    ELSE 5
                END',
                [
                    $term,
                    $codigoExato,
                    $likeStarts,
                    $likeStarts,
                    $likeStarts,
                    $digits !== '' ? $digits.'%' : '__never__',
                ]
            )
            ->orderBy('nome_razao')
            ->limit(12)
            ->get(['id', 'codigo', 'nome_razao', 'cpf_cnpj']);

        $this->nfeClienteSugestoes = $people
            ->map(function (Person $p): array {
                $doc = (string) ($p->cpf_cnpj ?? '');
                $digitsOnly = preg_replace('/\D/', '', $doc) ?: '';

                return [
                    'id' => (int) $p->id,
                    'codigo' => (string) ($p->codigo ?? ''),
                    'nome' => (string) ($p->nome_razao ?? ''),
                    'cpf_cnpj' => $this->formatNfeCpfCnpj($doc),
                    'doc_tipo' => strlen($digitsOnly) > 11 ? 'cnpj' : (strlen($digitsOnly) >= 11 ? 'cpf' : 'outro'),
                ];
            })
            ->values()
            ->all();
        $this->nfeClienteSugestoesOpen = $this->nfeClienteSugestoes !== [];
        $this->nfeSelectedClienteSugestaoIndex = 0;
    }

    public function confirmarNfeClienteBusca(): void
    {
        $term = trim($this->nfeClienteBusca);

        if ($term === '') {
            $this->fecharNfeSugestoesCliente();

            return;
        }

        if ($this->nfeClienteJaSelecionadoCorresponde($term)) {
            $this->fecharNfeSugestoesCliente();
            $this->nfeModalMainTab = 'itens';
            $this->dispatch('erp-nfe-focus-item-produto');

            return;
        }

        // Código puro (ex.: 15 + Enter) — prioriza match exato de código
        if (preg_match('/^\d+$/', $term)) {
            $this->nfeClienteCodigo = $term;
            $this->buscarNfeClientePorCodigo();

            if ((int) ($this->nfeForm['cliente_id'] ?? 0) > 0) {
                return;
            }
        }

        if (preg_match('/^(\d+)\s*[—\-]\s*(.*)$/u', $term, $m)) {
            $this->nfeClienteCodigo = $m[1];
            $this->buscarNfeClientePorCodigo();

            return;
        }

        if ($this->nfeClienteSugestoesOpen && $this->nfeClienteSugestoes !== []) {
            $index = $this->nfeSelectedClienteSugestaoIndex;
            if (! isset($this->nfeClienteSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarNfeCliente((int) $this->nfeClienteSugestoes[$index]['id']);

            return;
        }

        $digits = preg_replace('/\D/', '', $term) ?: '';
        $person = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where(function ($q) use ($term, $digits): void {
                $q->where('codigo', $term)
                    ->orWhere('codigo', ltrim($term, '0'))
                    ->orWhere('nome_razao', $term);

                if ($digits !== '' && strlen($digits) >= 11) {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderBy('nome_razao')
            ->first();

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->selecionarNfeCliente((int) $person->id);
    }

    public function buscarNfeClientePorCodigo(): void
    {
        $term = trim($this->nfeClienteCodigo);

        if ($term === '') {
            return;
        }

        $person = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where(function ($q) use ($term): void {
                $q->where('codigo', $term)
                    ->orWhere('codigo', ltrim($term, '0'));
            })
            ->first();

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->selecionarNfeCliente((int) $person->id);
    }

    public function selecionarNfeCliente(int $id): void
    {
        $person = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->find($id);

        if (! $person) {
            Notification::make()->title('Cliente não encontrado.')->warning()->send();

            return;
        }

        $this->aplicarNfeCliente($person, syncBusca: true);
        $this->fecharNfeSugestoesCliente();
        $this->recalculateNfeTotais();
        $this->nfeModalMainTab = 'itens';
        $this->dispatch('erp-nfe-focus-item-produto');
    }

    public function moverNfeSugestaoCliente(int $delta): void
    {
        if (! $this->nfeClienteSugestoesOpen || $this->nfeClienteSugestoes === []) {
            return;
        }

        $count = count($this->nfeClienteSugestoes);
        $index = $this->nfeSelectedClienteSugestaoIndex + $delta;
        $this->nfeSelectedClienteSugestaoIndex = max(0, min($count - 1, $index));
    }

    public function fecharNfeSugestoesCliente(): void
    {
        $this->nfeClienteSugestoes = [];
        $this->nfeClienteSugestoesOpen = false;
        $this->nfeSelectedClienteSugestaoIndex = 0;
    }

    public function updatedNfeFormNaturezaOperacao(string $value): void
    {
        $term = trim($value);

        if ($term === '') {
            $this->fecharNfeSugestoesNatureza();

            return;
        }

        // Já selecionado no formato completo → não reabre lista
        if (preg_match('/^\d{3,4}\s*-\s+.+/u', $term) && mb_strlen($term) > 14) {
            $this->fecharNfeSugestoesNatureza();

            return;
        }

        $this->refreshNfeNaturezaSugestoes($term);
    }

    public function abrirNfeSugestoesNatureza(): void
    {
        $term = trim((string) ($this->nfeForm['natureza_operacao'] ?? ''));

        if ($term === '') {
            $this->refreshNfeNaturezaSugestoes('');

            return;
        }

        if (preg_match('/^\d{3,4}\s*-\s+.+/u', $term) && mb_strlen($term) > 14) {
            return;
        }

        $this->refreshNfeNaturezaSugestoes($term);
    }

    public function confirmarNfeNaturezaBusca(?string $termFromInput = null): void
    {
        if ($termFromInput !== null) {
            $this->nfeForm['natureza_operacao'] = trim($termFromInput);
        }

        $term = trim((string) ($this->nfeForm['natureza_operacao'] ?? ''));

        if ($term === '') {
            $this->fecharNfeSugestoesNatureza();

            return;
        }

        if (preg_match('/^\d{3,4}$/', $term)) {
            if ($this->aplicarNfeNaturezaPorCodigo((int) $term)) {
                return;
            }

            Notification::make()->title('CFOP não encontrado.')->warning()->send();

            return;
        }

        if (preg_match('/^(\d{3,4})\s*[-—]/s*/u', $term, $m)) {
            if ($this->aplicarNfeNaturezaPorCodigo((int) $m[1])) {
                return;
            }
        }

        if ($this->nfeNaturezaSugestoesOpen && $this->nfeNaturezaSugestoes !== []) {
            $index = $this->nfeSelectedNaturezaIndex;
            if (! isset($this->nfeNaturezaSugestoes[$index])) {
                $index = 0;
            }
            $this->selecionarNfeNatureza($this->nfeNaturezaSugestoes[$index]['label']);

            return;
        }

        $this->refreshNfeNaturezaSugestoes($term);

        if (count($this->nfeNaturezaSugestoes) === 1) {
            $this->selecionarNfeNatureza($this->nfeNaturezaSugestoes[0]['label']);

            return;
        }

        if ($this->nfeNaturezaSugestoes === []) {
            Notification::make()->title('CFOP não encontrado.')->warning()->send();
        }
    }

    public function selecionarNfeNatureza(string $label): void
    {
        $this->nfeForm['natureza_operacao'] = $label;
        $this->fecharNfeSugestoesNatureza();
    }

    public function moverNfeSugestaoNatureza(int $delta): void
    {
        if (! $this->nfeNaturezaSugestoesOpen || $this->nfeNaturezaSugestoes === []) {
            return;
        }

        $count = count($this->nfeNaturezaSugestoes);
        $index = $this->nfeSelectedNaturezaIndex + $delta;
        $this->nfeSelectedNaturezaIndex = max(0, min($count - 1, $index));
    }

    public function fecharNfeSugestoesNatureza(): void
    {
        $this->nfeNaturezaSugestoes = [];
        $this->nfeNaturezaSugestoesOpen = false;
        $this->nfeSelectedNaturezaIndex = 0;
    }

    protected function refreshNfeNaturezaSugestoes(string $term): void
    {
        $tipoPreferido = ($this->nfeForm['movimento'] ?? 'saida') === 'entrada'
            ? Cfop::TIPO_ENTRADA
            : Cfop::TIPO_SAIDA;

        $term = trim($term);
        $termUpper = mb_strtoupper($term, 'UTF-8');
        $digits = preg_replace('/\D/', '', $term) ?: '';

        $query = Cfop::query()->ativos();

        if ($term !== '') {
            $like = '%'.$termUpper.'%';

            $query->where(function ($q) use ($like, $digits): void {
                $q->whereRaw('UPPER(descricao) LIKE ?', [$like]);

                if ($digits !== '') {
                    $q->orWhere('codigo', (int) $digits)
                        ->orWhereRaw('CAST(codigo AS CHAR) LIKE ?', [$digits.'%'])
                        ->orWhereRaw('CAST(codigo AS CHAR) LIKE ?', ['%'.$digits.'%']);
                } else {
                    $q->orWhereRaw('CAST(codigo AS CHAR) LIKE ?', [$like]);
                }
            });
        }

        $cfops = (clone $query)
            ->orderByRaw('CASE WHEN tipo = ? THEN 0 ELSE 1 END', [$tipoPreferido])
            ->orderByRaw(
                'CASE WHEN codigo = ? THEN 0 WHEN CAST(codigo AS CHAR) LIKE ? THEN 1 ELSE 2 END',
                [(int) ($digits ?: 0), $digits !== '' ? $digits.'%' : '']
            )
            ->orderBy('codigo')
            ->limit(20)
            ->get(['codigo', 'descricao', 'tipo']);

        if ($cfops->isEmpty() && $digits !== '') {
            $cfops = Cfop::query()
                ->ativos()
                ->where(function ($q) use ($digits): void {
                    $q->where('codigo', (int) $digits)
                        ->orWhereRaw('CAST(codigo AS CHAR) LIKE ?', [$digits.'%']);
                })
                ->orderBy('codigo')
                ->limit(20)
                ->get(['codigo', 'descricao', 'tipo']);
        }

        if ($cfops->isEmpty() && $digits !== '') {
            $cfops = Cfop::query()
                ->where(function ($q) use ($digits): void {
                    $q->where('codigo', (int) $digits)
                        ->orWhereRaw('CAST(codigo AS CHAR) LIKE ?', [$digits.'%']);
                })
                ->orderBy('codigo')
                ->limit(20)
                ->get(['codigo', 'descricao', 'tipo']);
        }

        if ($cfops->isEmpty()) {
            $fallback = Cfop::query()
                ->orderByRaw('CASE WHEN tipo = ? THEN 0 ELSE 1 END', [$tipoPreferido])
                ->orderBy('codigo')
                ->limit(20);

            if ($term !== '') {
                $like = '%'.mb_strtoupper($term, 'UTF-8').'%';
                $fallback->where(function ($q) use ($like, $digits): void {
                    $q->whereRaw('UPPER(descricao) LIKE ?', [$like]);
                    if ($digits !== '') {
                        $q->orWhere('codigo', (int) $digits)
                            ->orWhereRaw('CAST(codigo AS CHAR) LIKE ?', [$digits.'%']);
                    }
                });
            }

            $cfops = $fallback->get(['codigo', 'descricao', 'tipo']);
        }

        $this->nfeNaturezaSugestoes = $cfops
            ->map(function (Cfop $cfop): array {
                $descricao = mb_strtoupper((string) $cfop->descricao, 'UTF-8');
                $label = trim($cfop->codigo.' - '.$descricao);

                return [
                    'codigo' => (string) $cfop->codigo,
                    'descricao' => $descricao,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();

        $this->nfeNaturezaSugestoesOpen = $this->nfeNaturezaSugestoes !== [];
        $this->nfeSelectedNaturezaIndex = 0;
    }

    protected function aplicarNfeNaturezaPorCodigo(int $codigo): bool
    {
        if ($codigo <= 0) {
            return false;
        }

        $tipo = ($this->nfeForm['movimento'] ?? 'saida') === 'entrada'
            ? Cfop::TIPO_ENTRADA
            : Cfop::TIPO_SAIDA;

        $cfop = Cfop::query()
            ->ativos()
            ->where('codigo', $codigo)
            ->where('tipo', $tipo)
            ->first(['codigo', 'descricao']);

        if (! $cfop) {
            $cfop = Cfop::query()->ativos()->where('codigo', $codigo)->first(['codigo', 'descricao']);
        }

        if (! $cfop) {
            $cfop = Cfop::query()->where('codigo', $codigo)->first(['codigo', 'descricao']);
        }

        if (! $cfop) {
            return false;
        }

        $this->selecionarNfeNatureza(
            trim($cfop->codigo.' - '.mb_strtoupper((string) $cfop->descricao, 'UTF-8'))
        );

        return true;
    }
    #[Computed]
    public function naturezaOperacaoOptions(): array
    {
        $tipo = ($this->nfeForm['movimento'] ?? 'saida') === 'entrada'
            ? Cfop::TIPO_ENTRADA
            : Cfop::TIPO_SAIDA;

        $options = Cfop::query()
            ->where('tipo', $tipo)
            ->orderBy('codigo')
            ->get(['codigo', 'descricao'])
            ->mapWithKeys(function (Cfop $cfop): array {
                $label = trim($cfop->codigo.' - '.mb_strtoupper((string) $cfop->descricao, 'UTF-8'));

                return [$label => $label];
            })
            ->all();

        $atual = trim((string) ($this->nfeForm['natureza_operacao'] ?? ''));

        if ($atual !== '' && ! array_key_exists($atual, $options)) {
            $options = [$atual => $atual] + $options;
        }

        return $options;
    }

    public function updatedNfeFormMovimento(): void
    {
        unset($this->naturezaOperacaoOptions);

        $options = $this->naturezaOperacaoOptions;
        $atual = trim((string) ($this->nfeForm['natureza_operacao'] ?? ''));

        if ($atual === '' || ! array_key_exists($atual, $options)) {
            $this->nfeForm['natureza_operacao'] = array_key_first($options) ?? '';
        }
    }

    public function updatedNfeFormFormaPgto(): void
    {
        if (($this->nfeForm['forma_pgto'] ?? '') === 'a_vista') {
            $this->syncNfeModalFaturasAVista();
        }
    }

    public function updatedNfeFormDataEmissao(): void
    {
        $this->syncNfeModalFaturasAVista();
    }

    /**
     * Pagamento à vista: sem parcelas (SEFAZ cStat 853 — grupo cobr/fat/dup proibido).
     */
    protected function syncNfeModalFaturasAVista(): void
    {
        if (($this->nfeForm['forma_pgto'] ?? '') !== 'a_vista') {
            return;
        }

        $this->nfeModalFaturas = [];
    }

    protected function loadNfeIntoModal(Nfe $nfe): void
    {
        $this->nfeModalRecordId = $nfe->id;
        $this->nfeModalStatus = mb_strtoupper(Nfe::statusLabels()[$nfe->status] ?? $nfe->status, 'UTF-8');
        $this->nfeModalVendaId = $nfe->venda_id ? (int) $nfe->venda_id : null;
        $this->nfeModalPdvVendaId = $nfe->pdv_venda_id ? (int) $nfe->pdv_venda_id : null;
        $this->nfeModalDevolucaoCompraId = $nfe->devolucao_compra_id ? (int) $nfe->devolucao_compra_id : null;
        $this->syncNfeModalAmbiente(
            VendasParametro::forEmpresa((int) ($nfe->empresa_id ?: $this->resolveEmpresaId())),
        );
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
            'data_emissao' => $nfe->data_emissao?->format('Y-m-d') ?? ErpTimezone::today(),
            'data_saida' => $nfe->data_saida?->format('Y-m-d') ?? ErpTimezone::today(),
            'consumidor_final' => $nfe->cliente?->isConsumidorFinalPadrao()
                || $nfe->consumidor_final === '1'
                || $nfe->consumidor_final === true,
            'finalidade' => $this->unmapFinalidade((string) $nfe->finalidade),
            'movimento' => ($nfe->movimento ?? '1') === '0' ? 'entrada' : 'saida',
            'forma_pgto' => $nfe->forma_pgto ?? 'a_vista',
            'meio_pgto' => $nfe->meio_pgto ?? 'dinheiro',
            'obs_fisco' => $nfe->obs_fisco ?? '',
            'obs_contribuinte' => $nfe->obs_contribuinte ?? '',
            'tipo_frete' => (string) ($nfe->tipo_frete ?: '9'),
            'transportadora_id' => (string) ($nfe->transportadora_id ?? ''),
            'transportadora_codigo' => (string) ($nfe->transportadora?->codigo ?: '0'),
            'placa' => (string) ($nfe->placa ?? ''),
            'uf_placa' => (string) ($nfe->uf_placa ?? ''),
            'especie' => filled($nfe->especie) ? (string) $nfe->especie : 'CAIXA',
            'marca' => (string) ($nfe->marca ?? ''),
            'nvol' => (string) ($nfe->nvol ?? ''),
            'qvol' => (string) ($nfe->qvol ?? 1),
            'peso_l' => ErpMoney::formatBr((float) ($nfe->peso_l ?? 0), 3),
            'peso_b' => ErpMoney::formatBr((float) ($nfe->peso_b ?? 0), 3),
        ];

        if ($nfe->cliente) {
            $this->aplicarNfeCliente($nfe->cliente, syncBusca: true);
        } else {
            $this->clearNfeClienteDisplay();
        }

        if ($nfe->transportadora) {
            $this->aplicarNfeTransportadora($nfe->transportadora, syncBusca: true);
        } else {
            $this->clearNfeTransportadoraDisplay();
        }

        $this->nfeModalRows = $nfe->itens->map(fn (NfeItem $item): array => [
            'key' => 'item-' . $item->id,
            'product_id' => $item->product_id,
            'codigo' => $item->product?->codigo ?? '',
            'cod_barra' => (string) ($item->cod_barra ?: $item->product?->codigo_barras ?: ''),
            'referencia' => trim((string) ($item->product?->referencia ?? '')),
            'descricao' => $item->descricao,
            'info_adicionais' => $item->info_adicionais ?? '',
            'cfop' => $item->cfop,
            'cst' => $item->cst,
            'csosn' => $item->csosn,
            'pedido' => (string) ($nfe->npedido ?? ''),
            'quantidade' => ErpMoney::formatBr((float) $item->quantidade, 3),
            'valor_unitario' => ErpMoney::formatBr((float) $item->valor_unitario, 3),
            'unidade' => $item->unidade ?? 'UN',
            'desconto' => ErpMoney::formatBr((float) $item->desconto, 2),
            'frete' => ErpMoney::formatBr((float) $item->frete, 2),
            'seguro' => ErpMoney::formatBr((float) $item->seguro, 2),
            'outros' => ErpMoney::formatBr((float) $item->outros, 2),
            'base_icms' => ErpMoney::formatBr((float) $item->base_icms, 2),
            'aliq_icms' => ErpMoney::formatBr((float) $item->aliq_icms, 2),
            'valor_icms' => ErpMoney::formatBr((float) $item->valor_icms, 2),
            'motivo_desoneracao' => $item->motivo_desoneracao ?? '',
            'base_desoneracao' => ErpMoney::formatBr((float) ($item->base_desoneracao ?? 0), 2),
            'desc_desoneracao' => ErpMoney::formatBr((float) ($item->desc_desoneracao ?? 0), 2),
            'valor_desoneracao' => ErpMoney::formatBr((float) ($item->valor_desoneracao ?? 0), 2),
            'aliq_ipi' => ErpMoney::formatBr((float) $item->aliq_ipi, 2),
            'valor_ipi' => ErpMoney::formatBr((float) $item->valor_ipi, 2),
            'aliq_pis_icms' => ErpMoney::formatBr((float) $item->aliq_pis_icms, 2),
            'valor_pis_icms' => ErpMoney::formatBr((float) $item->valor_pis_icms, 2),
            'aliq_cofins_icms' => ErpMoney::formatBr((float) $item->aliq_cofins_icms, 2),
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
        $this->syncNfeSelectedRowFotoFromGrid();
        $this->nfeModalOpen = true;
    }

    private function validarDestinatarioProprioParaPerda(?Empresa $empresa, int $clienteId): void
    {
        $cfopPerda = $empresa
            ? OperacaoFiscal::forEmpresa((int) $empresa->id)->cfopSaidaPerda(false)
            : null;

        if (! $cfopPerda) {
            return;
        }

        $usaCfopPerda = collect($this->nfeModalRows)
            ->contains(fn (array $row): bool => (int) preg_replace('/\D/', '', (string) ($row['cfop'] ?? '')) === $cfopPerda);

        if (! $usaCfopPerda) {
            return;
        }

        $cliente = Person::query()->find($clienteId);
        $cnpjEmpresa = preg_replace('/\D/', '', (string) ($empresa?->cnpj ?? '')) ?: '';
        $cnpjCliente = preg_replace('/\D/', '', (string) ($cliente?->cpf_cnpj ?? '')) ?: '';

        if ($cnpjEmpresa === '' || $cnpjCliente !== $cnpjEmpresa) {
            throw new \RuntimeException(
                'NF-e de saída por perda só pode ter a própria empresa como destinatária (CFOP '.$cfopPerda.').'
            );
        }
    }

    public function nfeTotaisEnter(string $key, string $value): void
    {
        if (! in_array($key, ['frete', 'seguro', 'outras', 'desconto'], true)) {
            return;
        }

        $this->nfeSkipTotaisBlur = true;
        $this->commitNfeTotaisExtra($key, $value);
    }

    public function nfeTotaisBlur(string $key, string $value): void
    {
        if (! in_array($key, ['frete', 'seguro', 'outras', 'desconto'], true)) {
            return;
        }

        if ($this->nfeSkipTotaisBlur) {
            $this->nfeSkipTotaisBlur = false;
            $this->skipRender();

            return;
        }

        $this->commitNfeTotaisExtra($key, $value);
    }

    protected function commitNfeTotaisExtra(string $key, mixed $value): void
    {
        if ($this->nfeModalStatus !== 'ABERTA') {
            return;
        }

        if ($this->nfeModalRows === []) {
            Notification::make()->title('Informe itens na NF-e antes de editar os totais.')->warning()->send();

            return;
        }

        $parsed = max(0.0, ErpMoney::parseBr((string) $value, 2));
        $this->nfeModalTotais[$key] = ErpMoney::formatBr($parsed);
        $this->aplicarRateioNfeTotaisExtra($key, $parsed);
        $this->recalculateNfeTotais();
    }

    protected function nfeTotaisRowField(string $key): string
    {
        return $key === 'outras' ? 'outros' : $key;
    }

    protected function syncNfeModalTotaisExtrasToItens(): void
    {
        if ($this->nfeModalRows === []) {
            return;
        }

        foreach (['frete', 'seguro', 'outras', 'desconto'] as $key) {
            $rowField = $this->nfeTotaisRowField($key);
            $modalValor = ErpMoney::parseBr($this->nfeModalTotais[$key] ?? '0', 2);
            $itensValor = round(
                collect($this->nfeModalRows)->sum(
                    static fn (array $row): float => ErpMoney::parseBr((string) ($row[$rowField] ?? '0'), 2),
                ),
                2,
            );

            if (abs($modalValor - $itensValor) < 0.009) {
                continue;
            }

            // Rodapé em 0 com valor nos itens: o desconto/frete veio da grade.
            // Não zerar o rateio (input do rodapé nem sempre morph no Livewire).
            if ($modalValor <= 0 && $itensValor > 0) {
                continue;
            }

            $this->aplicarRateioNfeTotaisExtra($key, $modalValor);
        }
    }

    protected function aplicarRateioNfeTotaisExtra(string $key, float $valorTotal): void
    {
        if ($this->nfeModalRows === []) {
            return;
        }

        $rowField = $this->nfeTotaisRowField($key);
        $pesos = [];
        $somaPesos = 0.0;

        foreach ($this->nfeModalRows as $index => $row) {
            $qtd = ErpMoney::parseBr((string) ($row['quantidade'] ?? '1'), 3);
            $preco = ErpMoney::parseBr((string) ($row['valor_unitario'] ?? '0'), 2);
            $peso = max(0.0, round($qtd * $preco, 2));
            $pesos[$index] = $peso;
            $somaPesos += $peso;
        }

        $indices = array_keys($this->nfeModalRows);
        $lastIndex = $indices === [] ? null : $indices[array_key_last($indices)];
        $restante = round($valorTotal, 2);

        if ($somaPesos <= 0) {
            foreach ($indices as $position => $index) {
                if ($index === $lastIndex) {
                    $this->nfeModalRows[$index][$rowField] = ErpMoney::formatBr($restante, 2);
                } else {
                    $parte = round($valorTotal / count($indices), 2);
                    $this->nfeModalRows[$index][$rowField] = ErpMoney::formatBr($parte, 2);
                    $restante = round($restante - $parte, 2);
                }
            }

            return;
        }

        foreach ($indices as $index) {
            if ($index === $lastIndex) {
                $parte = $restante;
            } else {
                $parte = round($valorTotal * ($pesos[$index] / $somaPesos), 2);
                $restante = round($restante - $parte, 2);
            }

            $this->nfeModalRows[$index][$rowField] = ErpMoney::formatBr(max(0.0, $parte), 2);
        }
    }

    protected function recalculateNfeTotais(): void
    {
        $empresaId = $this->resolveEmpresaId();
        $calculated = app(NfeCalculoService::class)->calcular(
            $this->nfeModalRows,
            $empresaId ? Empresa::query()->find($empresaId) : null,
            $this->nfeForm['uf'] ?? null,
            $this->nfeImpostoCalcHint,
        );

        $this->nfeModalRows = $this->formatNfeModalRowsForDisplay(
            $calculated['rows'],
            $empresaId ? Empresa::query()->find($empresaId) : null,
        );
        $this->nfeImpostoCalcHint = null;
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
            'trib_fed' => ErpMoney::formatBr((float) ($totais['trib_fed'] ?? 0)),
            'trib_est' => ErpMoney::formatBr((float) ($totais['trib_est'] ?? 0)),
            'trib_mun' => ErpMoney::formatBr((float) ($totais['trib_mun'] ?? 0)),
            'v_tot_trib' => ErpMoney::formatBr((float) ($totais['v_tot_trib'] ?? 0)),
            'ibpt_texto' => (string) ($totais['ibpt_texto'] ?? ''),
        ];

        $this->syncNfeObsContribuinteComIbpt(
            $this->formatNfeIbptLinhaComplementar($totais),
        );
        $this->syncNfePesosFromProdutos();
        $this->syncNfeModalFaturasAVista();
    }

    /**
     * Soma peso líquido/bruto a partir do cadastro do produto × quantidade dos itens.
     */
    protected function syncNfePesosFromProdutos(): void
    {
        if ($this->nfeModalRows === []) {
            $this->nfeForm['peso_l'] = '0,000';
            $this->nfeForm['peso_b'] = '0,000';

            return;
        }

        $productIds = collect($this->nfeModalRows)
            ->map(static fn (array $row): int => (int) ($row['product_id'] ?? 0))
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->whereIn('id', $productIds)
            ->get(['id', 'peso_liq', 'peso_kg'])
            ->keyBy('id');

        $pesoL = 0.0;
        $pesoB = 0.0;

        foreach ($this->nfeModalRows as $row) {
            $product = $products->get((int) ($row['product_id'] ?? 0));

            if (! $product instanceof Product) {
                continue;
            }

            $qtd = max(0.0, ErpMoney::parseBr($row['quantidade'] ?? '0', 3));
            $liqUnit = (float) ($product->peso_liq ?? 0);
            $bruUnit = (float) ($product->peso_kg ?? 0);

            // Se só um dos pesos estiver cadastrado, usa o mesmo nos dois.
            if ($liqUnit <= 0 && $bruUnit > 0) {
                $liqUnit = $bruUnit;
            }

            if ($bruUnit <= 0 && $liqUnit > 0) {
                $bruUnit = $liqUnit;
            }

            $pesoL += $qtd * $liqUnit;
            $pesoB += $qtd * $bruUnit;
        }

        $this->nfeForm['peso_l'] = ErpMoney::formatBr(round($pesoL, 3), 3);
        $this->nfeForm['peso_b'] = ErpMoney::formatBr(round($pesoB, 3), 3);
    }

    /**
     * Linha única do IBPT para o campo informações complementares.
     *
     * @param  array<string, mixed>  $totais
     */
    protected function formatNfeIbptLinhaComplementar(array $totais): string
    {
        $fed = (float) ($totais['trib_fed'] ?? 0);
        $est = (float) ($totais['trib_est'] ?? 0);
        $mun = (float) ($totais['trib_mun'] ?? 0);
        $tot = (float) ($totais['v_tot_trib'] ?? ($fed + $est + $mun));

        if ($tot <= 0) {
            return '';
        }

        $oficial = trim((string) ($totais['ibpt_texto'] ?? ''));

        if ($oficial !== '') {
            return $oficial;
        }

        return sprintf(
            'Trib. aprox. Federal: R$ %s | Estadual: R$ %s | Municipal: R$ %s | Total: R$ %s. Fonte: IBPT. Lei 12.741/2012.',
            number_format($fed, 2, ',', '.'),
            number_format($est, 2, ',', '.'),
            number_format($mun, 2, ',', '.'),
            number_format($tot, 2, ',', '.'),
        );
    }

    protected function syncNfeObsContribuinteComIbpt(string $ibptTexto): void
    {
        $obs = trim((string) ($this->nfeForm['obs_contribuinte'] ?? ''));

        // Remove linha IBPT anterior (formatos antigos e atuais).
        $obs = trim((string) preg_replace(
            '/\s*(?:Trib\. aprox\.|Federal:\s*R\$).*?(?:Lei 12\.741\/2012\.?|$)/uis',
            '',
            $obs,
        ));
        $obs = trim((string) preg_replace('/\s{2,}/u', ' ', $obs));

        $ibptTexto = trim(preg_replace('/\s+/u', ' ', $ibptTexto) ?? $ibptTexto);

        if ($ibptTexto === '') {
            $this->nfeForm['obs_contribuinte'] = $obs;

            return;
        }

        $this->nfeForm['obs_contribuinte'] = $obs === ''
            ? $ibptTexto
            : rtrim($obs, " .\n").' '.$ibptTexto;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultNfeFormData(?VendasParametro $params = null): array
    {
        $today = ErpTimezone::today();
        $empresaId = $this->resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        return [
            'numero' => Nfe::nextNumero($empresaId),
            'serie' => (string) ($params?->serie_nfe ?? 1),
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
            'obs_fisco' => trim((string) ($empresa?->obs_fisco ?? '')),
            'obs_contribuinte' => trim((string) ($empresa?->obs_contribuinte ?? '')),
            'tipo_frete' => '9',
            'transportadora_id' => '',
            'transportadora_codigo' => '0',
            'placa' => '',
            'uf_placa' => '',
            'especie' => 'CAIXA',
            'marca' => '',
            'nvol' => '',
            'qvol' => '1',
            'peso_l' => '0,000',
            'peso_b' => '0,000',
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
            'trib_fed' => '0,00',
            'trib_est' => '0,00',
            'trib_mun' => '0,00',
            'v_tot_trib' => '0,00',
            'ibpt_texto' => '',
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

    protected function primeiraChaveReferenciaModal(): ?string
    {
        foreach ($this->nfeModalReferencias as $referencia) {
            $digits = preg_replace('/\D/', '', (string) ($referencia['referencia'] ?? '')) ?? '';

            if (strlen($digits) === 44) {
                return $digits;
            }
        }

        return null;
    }

    protected function mergeObsContribuinteWithIbpt(string $obs, string $ibptTexto): ?string
    {
        $obs = trim($obs);
        $ibptTexto = trim($ibptTexto);

        if ($ibptTexto === '') {
            return $obs !== '' ? $obs : null;
        }

        if ($obs === '') {
            return $ibptTexto;
        }

        if (str_contains($obs, 'Lei 12.741') || str_contains($obs, 'Trib. aprox.')) {
            return $obs;
        }

        return rtrim($obs, " .\n").'. '.$ibptTexto;
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
        return \App\Support\Erp\ErpContext::currentEmpresaId();
    }

    protected function syncNfeModalAmbiente(?VendasParametro $params = null): void
    {
        if (! $params) {
            $empresaId = $this->resolveEmpresaId();
            $params = $empresaId ? VendasParametro::forEmpresa($empresaId) : null;
        }

        // Config Fiscais: 0 = Produção, 1 = Homologação → SEFAZ tpAmb 1/2.
        $this->nfeModalHomologacao = (int) ($params?->ambiente ?? VendasParametro::AMBIENTE_HOMOLOGACAO)
            !== VendasParametro::AMBIENTE_PRODUCAO;
    }

    protected function aplicarNfeCliente(Person $person, bool $syncBusca = true): void
    {
        $this->nfeForm['cliente_id'] = (string) $person->id;
        $this->nfeForm['uf'] = (string) ($person->uf ?? '');
        $this->nfeForm['cnpj'] = $this->formatNfeCpfCnpj($person->cpf_cnpj);
        $this->nfeForm['consumidor_final'] = $person->isConsumidorFinalPadrao();

        $this->nfeClienteCodigo = (string) ($person->codigo ?? '');
        $this->nfeClienteNome = trim((string) ($person->nome_razao ?? ''));
        $this->nfeClienteFone = (string) ($person->fone1 ?? $person->fone2 ?? '');
        $this->nfeClienteWhatsapp = (string) ($person->whatsapp ?? $person->celular1 ?? '');
        $this->nfeClienteEndereco = (string) ($person->endereco ?? '');
        $this->nfeClienteNumeroEnd = (string) ($person->numero ?? '');
        $this->nfeClienteBairro = (string) ($person->bairro ?? '');
        $this->nfeClienteCep = (string) ($person->cep ?? '');
        $this->nfeClienteCidade = (string) ($person->cidade_nome ?? '');

        if ($syncBusca) {
            $this->nfeClienteBusca = $this->formatarNfeClienteBusca($person);
        }
    }

    protected function clearNfeClienteDisplay(bool $keepBusca = false): void
    {
        $this->nfeClienteCodigo = '';
        $this->nfeClienteNome = '';
        if (! $keepBusca) {
            $this->nfeClienteBusca = '';
        }
        $this->nfeClienteFone = '';
        $this->nfeClienteWhatsapp = '';
        $this->nfeClienteEndereco = '';
        $this->nfeClienteNumeroEnd = '';
        $this->nfeClienteBairro = '';
        $this->nfeClienteCep = '';
        $this->nfeClienteCidade = '';
        $this->fecharNfeSugestoesCliente();
    }

    protected function formatarNfeClienteBusca(Person $person): string
    {
        $nome = trim((string) ($person->nome_razao ?? ''));

        return trim(($person->codigo ? $person->codigo.' — ' : '').$nome);
    }

    protected function nfeClienteJaSelecionadoCorresponde(string $term): bool
    {
        $clienteId = (int) ($this->nfeForm['cliente_id'] ?? 0);

        if ($clienteId <= 0) {
            return false;
        }

        $termNorm = mb_strtoupper(trim($term), 'UTF-8');
        if ($termNorm === '') {
            return false;
        }

        // Não comparar com nfeClienteBusca: o Livewire já atualiza esse campo
        // para o texto digitado, então a lista nunca reabria após gravar a nota.
        $formatadoNorm = mb_strtoupper(trim(
            ($this->nfeClienteCodigo !== '' ? $this->nfeClienteCodigo.' — ' : '').$this->nfeClienteNome
        ), 'UTF-8');

        return $formatadoNorm !== '' && $termNorm === $formatadoNorm;
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

    public function closeNfeFiscalOverlay(): void
    {
        $this->nfeFiscalOverlayTitulo = null;
        $this->nfeFiscalOverlayMensagem = null;
        $this->nfeFiscalOverlayCodigo = null;
    }

    public function closeNfeFiscalSucessoOverlay(): void
    {
        $this->closeNfeWhatsAppModal();
        $this->closeNfeDanfeEmailModal();
        $this->closeNfeCancelModal();
        $this->closeNfeCceModal();
        $this->closeNfeCceSucessoOverlay();
        $this->closeNfeCceDispatchModals();
        $this->nfeFiscalSucessoDetalhe = null;
        $this->nfeFiscalSucessoNfeId = null;
    }

    public function acknowledgeNfeFiscalSucessoOverlay(): void
    {
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeModal();
    }

    public function printNfeDanfe(): void
    {
        if (! $this->nfeFiscalSucessoNfeId) {
            $this->showNfeFiscalOverlayInfo('Imprimir DANFE', 'NF-e não encontrada para impressão.');

            return;
        }

        $this->openNfeDanfePrint((int) $this->nfeFiscalSucessoNfeId);
    }

    public function printNfeDanfeFromList(): void
    {
        $nfeId = $this->resolveNfePrintTargetId();

        if (! $nfeId) {
            return;
        }

        $this->openNfeDanfePrint($nfeId);
    }

    protected function resolveNfePrintTargetId(): ?int
    {
        if ($this->nfeFiscalSucessoNfeId) {
            return (int) $this->nfeFiscalSucessoNfeId;
        }

        if ($this->nfeModalOpen && $this->nfeModalRecordId) {
            return (int) $this->nfeModalRecordId;
        }

        return $this->highlightedRecordIdOrNotify('imprimir');
    }

    protected function openNfeDanfePrint(int $nfeId): void
    {
        $nfe = Nfe::query()->find($nfeId);

        if (! $nfe) {
            Notification::make()
                ->title('NF-e não encontrada para impressão.')
                ->warning()
                ->send();

            return;
        }

        if ($nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            Notification::make()
                ->title('Somente NF-e transmitida possui DANFE para impressão.')
                ->body('Selecione uma nota na aba Transmitida (ou use Espelho para notas abertas).')
                ->warning()
                ->send();

            return;
        }

        if (blank($nfe->xml) && blank($nfe->chave)) {
            Notification::make()
                ->title('XML/chave da NF-e não encontrados.')
                ->body('Não é possível gerar o DANFE sem o XML autorizado.')
                ->warning()
                ->send();

            return;
        }

        $url = route('erp.reports.nfe-danfe', [
            'nfe' => $nfeId,
        ]);

        $this->js('window.ErpNfePrint?.openDanfe(' . Js::from($url) . ')');
    }

    public function openNfeWhatsAppModal(): void
    {
        // DANFE do cliente: tela unificada Enviar nota (e-mail + WhatsApp).
        $this->openNfeDanfeEmailModal();
    }

    public function openNfeWhatsAppFromList(): void
    {
        // DANFE do cliente: tela unificada Enviar nota (e-mail + WhatsApp).
        $this->openNfeDanfeEmailFromList();
    }

    protected function resolveNfeWhatsAppTargetId(): ?int
    {
        if ($this->nfeFiscalSucessoNfeId) {
            return $this->nfeFiscalSucessoNfeId;
        }

        if ($this->nfeModalOpen && $this->nfeModalRecordId) {
            return $this->nfeModalRecordId;
        }

        return $this->highlightedRecordIdOrNotify('whatsapp');
    }

    protected function prepareNfeWhatsAppModal(
        int $nfeId,
        bool $useFiscalOverlayErrors = false,
        string $documento = 'danfe',
        string $destinatario = 'cliente',
    ): void {
        $nfe = Nfe::query()->with(['cliente', 'transportadora'])->find($nfeId);

        if (! $nfe) {
            $this->notifyNfeWhatsAppError('NF-e não encontrada para envio.', $useFiscalOverlayErrors);

            return;
        }

        if ($documento === 'danfe' && $nfe->status !== Nfe::STATUS_TRANSMITIDA) {
            $this->notifyNfeWhatsAppError('Somente NF-e transmitida pode ser enviada por WhatsApp.', $useFiscalOverlayErrors);

            return;
        }

        if ($documento === 'espelho' && $nfe->status !== Nfe::STATUS_ABERTA) {
            $this->notifyNfeWhatsAppError('Somente NF-e aberta possui espelho para envio.', $useFiscalOverlayErrors);

            return;
        }

        $this->cleanupNfeWhatsAppPdf();

        $xml = null;

        try {
            if ($documento === 'espelho') {
                $report = app(NfeEspelhoReportService::class);
                $pdf = $report->storePdfAttachment($nfe);
                $message = $report->defaultWhatsAppMessage($nfe, $destinatario);
            } else {
                $report = app(NfeDanfeReportService::class);
                $pdf = $report->storePdfAttachment($nfe);
                $message = $report->defaultWhatsAppMessage($nfe);
                $xml = $report->storeXmlAttachment($nfe);
            }
        } catch (\Throwable $exception) {
            report($exception);

            $this->notifyNfeWhatsAppError(
                $documento === 'espelho'
                    ? 'Não foi possível gerar o PDF do espelho para envio.'
                    : 'Não foi possível gerar o PDF da DANFE para envio.',
                $useFiscalOverlayErrors,
            );

            return;
        }

        $party = match ($destinatario) {
            'fornecedor' => $nfe->transportadora,
            default => $nfe->cliente,
        };

        $phoneRaw = $party instanceof Transportadora
            ? (string) ($party->whatsapp ?? '')
            : (string) ($party?->celular1 ?: ($party?->whatsapp ?: ($party?->fone1 ?: '')));
        $phoneDigits = WhatsAppPhone::digitsOnly($phoneRaw);

        $this->nfeWhatsAppNfeId = $nfe->id;
        $this->nfeWhatsAppDocumento = $documento;
        $this->nfeWhatsAppDestinatario = $destinatario;
        $this->nfeWhatsAppTo = strlen($phoneDigits) === 11
            ? WhatsAppPhone::formatDisplay($phoneDigits)
            : ($phoneDigits !== '' ? WhatsAppPhone::formatDisplay('55' . $phoneDigits) : '');
        $this->nfeWhatsAppMessage = $message;
        $this->nfeWhatsAppPdfPath = $pdf['path'];
        $this->nfeWhatsAppPdfName = $pdf['name'];
        $this->nfeWhatsAppPdfDisplay = $pdf['display'];
        $this->nfeWhatsAppXmlPath = is_array($xml) ? ($xml['path'] ?? null) : null;
        $this->nfeWhatsAppXmlName = is_array($xml) ? (string) ($xml['name'] ?? '') : '';
        $this->nfeWhatsAppXmlDisplay = is_array($xml) ? (string) ($xml['display'] ?? '') : '';
        $this->nfeWhatsAppModalOpen = true;

        $this->dispatch('erp-nfe-focus-whatsapp-modal');
    }

    protected function notifyNfeWhatsAppError(string $message, bool $useFiscalOverlayErrors): void
    {
        if ($useFiscalOverlayErrors) {
            $this->showNfeFiscalOverlayInfo('WhatsApp', $message);

            return;
        }

        Notification::make()
            ->title('WhatsApp')
            ->body($message)
            ->warning()
            ->send();
    }

    public function closeNfeWhatsAppModal(): void
    {
        $this->nfeWhatsAppModalOpen = false;
        $this->nfeWhatsAppNfeId = null;
        $this->nfeWhatsAppTo = '';
        $this->nfeWhatsAppMessage = '';
        $this->nfeWhatsAppPdfName = '';
        $this->nfeWhatsAppPdfDisplay = '';
        $this->nfeWhatsAppXmlName = '';
        $this->nfeWhatsAppXmlDisplay = '';
        $this->nfeWhatsAppDocumento = 'danfe';
        $this->nfeWhatsAppDestinatario = 'cliente';
        $this->cleanupNfeWhatsAppPdf();
    }

    public function updatedNfeWhatsAppMessage(string $value): void
    {
        $clean = WhatsAppMessageHelper::stripSystemFooter($value);

        if ($clean !== $value) {
            $this->nfeWhatsAppMessage = $clean;
        }
    }

    public function sendNfeWhatsApp(): void
    {
        $this->nfeWhatsAppMessage = WhatsAppMessageHelper::stripSystemFooter($this->nfeWhatsAppMessage);

        $maxLength = WhatsAppMessageHelper::maxUserMessageLength();

        $this->validate([
            'nfeWhatsAppTo' => ['required', 'string', 'max:30', new CelularBrasileiroValido()],
            'nfeWhatsAppMessage' => ['required', 'string', 'max:' . $maxLength],
        ], [
            'nfeWhatsAppTo.required' => 'Informe o WhatsApp do destinatário.',
            'nfeWhatsAppMessage.required' => 'Informe a mensagem.',
        ]);

        if (! is_string($this->nfeWhatsAppPdfPath) || ! is_file($this->nfeWhatsAppPdfPath)) {
            Notification::make()
                ->title('Anexos da NF-e não encontrados.')
                ->body('Feche e abra novamente o envio por WhatsApp.')
                ->warning()
                ->send();

            return;
        }

        $documents = [[
            'path' => $this->nfeWhatsAppPdfPath,
            'name' => $this->nfeWhatsAppPdfName !== '' ? $this->nfeWhatsAppPdfName : 'DANFE-NFE.PDF',
            'mimetype' => 'application/pdf',
        ]];

        if (
            $this->nfeWhatsAppDocumento === 'danfe'
            && is_string($this->nfeWhatsAppXmlPath)
            && is_file($this->nfeWhatsAppXmlPath)
        ) {
            $documents[] = [
                'path' => $this->nfeWhatsAppXmlPath,
                'name' => $this->nfeWhatsAppXmlName !== '' ? $this->nfeWhatsAppXmlName : 'NFE.xml',
                'mimetype' => 'application/xml',
                'caption' => 'XML da NF-e',
            ];
        }

        $nfe = Nfe::query()->find($this->nfeWhatsAppNfeId);

        if (! $nfe) {
            Notification::make()
                ->title('NF-e não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $empresa = Empresa::query()->find($nfe->empresa_id);

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada.')
                ->warning()
                ->send();

            return;
        }

        $sender = app(WhatsAppSender::class);

        try {
            $result = $sender->sendDocumentMessages(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFE,
                number: $this->nfeWhatsAppTo,
                text: $this->nfeWhatsAppMessage,
                documents: $documents,
            );
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível enviar o WhatsApp.')
                ->body('Verifique a conexão em Empresa → Parâmetros → WhatsApp.')
                ->danger()
                ->send();

            return;
        }

        if (! $result['ok']) {
            Notification::make()
                ->title('Não foi possível enviar o WhatsApp.')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('WhatsApp enviado.')
            ->success()
            ->send();

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_WHATSAPP,
            titulo: $this->nfeWhatsAppDocumento === 'espelho'
                ? 'Espelho enviado por WhatsApp'
                : 'NF-e enviada por WhatsApp',
            descricao: $this->nfeWhatsAppDocumento === 'espelho'
                ? 'Espelho da NF-e em aberto enviado com anexo em PDF.'
                : 'DANFE e XML enviados ao destinatário.',
            destinatario: WhatsAppPhone::formatDisplay($this->nfeWhatsAppTo) ?? $this->nfeWhatsAppTo,
            metadata: [
                'contexto' => $this->nfeWhatsAppDocumento,
                'destinatario_tipo' => $this->nfeWhatsAppDestinatario,
                'anexos' => collect($documents)->pluck('name')->filter()->values()->all(),
            ],
        );

        $this->closeNfeWhatsAppModal();
    }

    protected function cleanupNfeWhatsAppPdf(): void
    {
        if (is_string($this->nfeWhatsAppPdfPath) && is_file($this->nfeWhatsAppPdfPath)) {
            @unlink($this->nfeWhatsAppPdfPath);
        }

        if (is_string($this->nfeWhatsAppXmlPath) && is_file($this->nfeWhatsAppXmlPath)) {
            @unlink($this->nfeWhatsAppXmlPath);
        }

        $this->nfeWhatsAppPdfPath = null;
        $this->nfeWhatsAppXmlPath = null;
    }

    public function closeNfeFiscalInfoOverlay(): void
    {
        $this->nfeFiscalInfoTitulo = null;
        $this->nfeFiscalInfoMensagem = null;
    }

    public function showNfeFiscalOverlayInfo(string $titulo, string $mensagem = 'Em implementação.'): void
    {
        $this->closeNfeFiscalOverlay();
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $this->nfeFiscalInfoTitulo = trim($titulo);
        $this->nfeFiscalInfoMensagem = trim($mensagem) !== '' ? trim($mensagem) : null;

        $this->dispatch('erp-nfe-focus-fiscal-info');
    }

    protected function showNfeFiscalOverlaySucesso(Nfe $nfe): void
    {
        $this->closeNfeFiscalOverlay();
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $numero = ltrim((string) $nfe->numero, '0') ?: (string) $nfe->numero;
        $protocolo = trim((string) ($nfe->protocolo ?? ''));

        $this->nfeFiscalSucessoDetalhe = $protocolo !== ''
            ? "Nota {$numero} — Protocolo: {$protocolo}"
            : "Nota {$numero} autorizada pela SEFAZ.";
        $this->nfeFiscalSucessoNfeId = $nfe->id;

        $this->dispatch('erp-nfe-focus-fiscal-sucesso');
        $this->js(
            'window.__erpNfeShowFiscalSucessoOverlay && window.__erpNfeShowFiscalSucessoOverlay('
            .Js::from(['detalhe' => $this->nfeFiscalSucessoDetalhe])
            .')'
        );
    }

    protected function showNfeFiscalOverlayErro(FiscalEngineException $exception): void
    {
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $resolvido = PdvNfceFiscalMensagens::resolver($exception);
        $mensagemExcecao = trim($exception->getMessage());

        // Pré-SEFAZ (ex.: NCM): título padrão + texto no corpo — overlay vermelho do meio da tela.
        // Com cStat/corpo amigável: mantém título/código SEFAZ.
        if (filled($exception->sefazCodigo) || filled($resolvido['corpo'] ?? null)) {
            $mensagem = trim((string) ($resolvido['corpo'] ?? $mensagemExcecao));
            $this->nfeFiscalOverlayTitulo = mb_strtoupper((string) $resolvido['titulo'], 'UTF-8');
            $this->nfeFiscalOverlayMensagem = $mensagem !== '' ? $mensagem : null;
            $this->nfeFiscalOverlayCodigo = $exception->sefazCodigo;
        } else {
            $this->nfeFiscalOverlayTitulo = 'NÃO FOI POSSÍVEL TRANSMITIR A NF-E';
            $this->nfeFiscalOverlayMensagem = $mensagemExcecao !== ''
                ? $mensagemExcecao
                : 'Tente novamente em instantes.';
            $this->nfeFiscalOverlayCodigo = null;
        }

        $this->dispatch('erp-nfe-focus-fiscal-overlay');
        $this->js(
            'window.__erpNfeShowFiscalErroOverlay && window.__erpNfeShowFiscalErroOverlay('
            .Js::from([
                'titulo' => $this->nfeFiscalOverlayTitulo,
                'mensagem' => $this->nfeFiscalOverlayMensagem,
                'codigo' => $this->nfeFiscalOverlayCodigo,
            ])
            .')'
        );
    }

    protected function showNfeFiscalOverlayErroGenerico(string $mensagem): void
    {
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $mensagem = trim($mensagem);

        $this->nfeFiscalOverlayTitulo = 'NÃO FOI POSSÍVEL TRANSMITIR A NF-E';
        $this->nfeFiscalOverlayMensagem = $mensagem !== '' ? $mensagem : 'Tente novamente em instantes.';
        $this->nfeFiscalOverlayCodigo = null;
        $this->dispatch('erp-nfe-focus-fiscal-overlay');
        $this->js(
            'window.__erpNfeShowFiscalErroOverlay && window.__erpNfeShowFiscalErroOverlay('
            .Js::from([
                'titulo' => $this->nfeFiscalOverlayTitulo,
                'mensagem' => $this->nfeFiscalOverlayMensagem,
                'codigo' => null,
            ])
            .')'
        );
    }
}
