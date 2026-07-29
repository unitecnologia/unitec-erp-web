<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Cfop;
use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\NfeReferencia;
use App\Models\Person;
use App\Models\VendasParametro;
use App\Rules\CelularBrasileiroValido;
use App\Support\Erp\ErpMoney;
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

    public string $nfeWhatsAppDocumento = 'danfe';

    public string $nfeWhatsAppDestinatario = 'cliente';

    public string $nfeClienteCodigo = '';

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
        $this->nfeModalMainTab = 'itens';
        $this->nfeModalDetailTab = 'totais';
        $this->nfeSelectedRowIndex = 0;
        $this->nfeReferenciaInput = '';
        $this->clearNfeItemEntryRow();
        $this->clearNfeClienteDisplay();
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
        $this->closeNfeFiscalOverlay();
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();
        $this->closeNfeWhatsAppModal();
        $this->closeNfeCancelModal();
        $this->closeNfeCceModal();
        $this->closeNfeCceSucessoOverlay();
        $this->closeNfeCceDispatchModals();
        $this->closeNfeImportModals();
        $this->nfeModalOpen = false;
        $this->nfeModalRecordId = null;
        $this->nfeModalStatus = 'ABERTA';
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
                'trib_fed' => $totais['trib_fed'] ?? 0,
                'trib_est' => $totais['trib_est'] ?? 0,
                'trib_mun' => $totais['trib_mun'] ?? 0,
                'trib_imp' => $totais['trib_imp'] ?? 0,
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
            return;
        }

        Notification::make()
            ->title($isEditing ? 'NF-e gravada.' : 'NF-e incluída.')
            ->success()
            ->send();

        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($savedId);

        NfeEventoLogger::registrar(
            nfeId: $savedId,
            tipo: $isEditing ? NfeEvento::TIPO_EDITADA : NfeEvento::TIPO_CRIADA,
            titulo: $isEditing ? 'NF-e alterada' : 'NF-e criada',
            descricao: $isEditing
                ? 'Dados da nota fiscal foram atualizados no sistema.'
                : 'NF-e incluída no sistema em situação aberta.',
        );
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

        $nfe = Nfe::query()->with(['itens', 'faturas', 'cliente'])->find($this->nfeModalRecordId);
        $empresaId = $this->resolveEmpresaId();
        $empresa = $empresaId ? Empresa::query()->find($empresaId) : null;

        if (! $nfe || ! $empresa) {
            Notification::make()
                ->title('Não foi possível localizar os dados para transmissão.')
                ->warning()
                ->send();

            return;
        }

        if ($nfe->status !== Nfe::STATUS_ABERTA) {
            Notification::make()
                ->title('Somente NF-e aberta pode ser transmitida.')
                ->warning()
                ->send();

            return;
        }

        try {
            $nfe = (new NfeEmissionService())->transmitir($nfe, $empresa);
        } catch (FiscalEngineException $exception) {
            $this->showNfeFiscalOverlayErro($exception);

            return;
        } catch (\Throwable $exception) {
            $this->showNfeFiscalOverlayErroGenerico($exception->getMessage());

            return;
        }

        $this->loadNfeIntoModal($nfe);
        $this->resetTable();
        $this->showNfeFiscalOverlaySucesso($nfe);
    }

    public function openNfeProdutos(): void
    {
        $this->openNfeProdutoLookup();
    }

    public function openNfePessoas(): void
    {
        // Lookup dedicado em breve — cliente no Destinatário.
    }

    public function openNfeTransportadora(): void
    {
        // Lookup dedicado em breve.
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

        $people = Person::query()
            ->where('is_cliente', true)
            ->where('ativo', true)
            ->where(function ($q) use ($term, $digits): void {
                $q->where('codigo', 'like', $term.'%')
                    ->orWhere('nome_razao', 'like', '%'.$term.'%')
                    ->orWhere('apelido_fantasia', 'like', '%'.$term.'%');

                if ($digits !== '') {
                    $q->orWhere('cpf_cnpj', 'like', '%'.$digits.'%');
                }
            })
            ->orderByRaw('CASE WHEN codigo = ? OR codigo = ? THEN 0 WHEN codigo LIKE ? THEN 1 ELSE 2 END', [
                $term,
                ltrim($term, '0') ?: $term,
                $term.'%',
            ])
            ->orderBy('nome_razao')
            ->limit(12)
            ->get(['id', 'codigo', 'nome_razao', 'cpf_cnpj']);

        $this->nfeClienteSugestoes = $people
            ->map(fn (Person $p): array => [
                'id' => (int) $p->id,
                'codigo' => (string) ($p->codigo ?? ''),
                'nome' => (string) ($p->nome_razao ?? ''),
                'cpf_cnpj' => (string) ($p->cpf_cnpj ?? ''),
            ])
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
            'consumidor_final' => $nfe->cliente?->isConsumidorFinalPadrao()
                || $nfe->consumidor_final === '1'
                || $nfe->consumidor_final === true,
            'finalidade' => $this->unmapFinalidade((string) $nfe->finalidade),
            'movimento' => ($nfe->movimento ?? '1') === '0' ? 'entrada' : 'saida',
            'forma_pgto' => $nfe->forma_pgto ?? 'a_vista',
            'meio_pgto' => $nfe->meio_pgto ?? 'dinheiro',
            'obs_fisco' => $nfe->obs_fisco ?? '',
            'obs_contribuinte' => $nfe->obs_contribuinte ?? '',
        ];

        if ($nfe->cliente) {
            $this->aplicarNfeCliente($nfe->cliente, syncBusca: true);
        } else {
            $this->clearNfeClienteDisplay();
        }

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

    protected function aplicarNfeCliente(Person $person, bool $syncBusca = true): void
    {
        $this->nfeForm['cliente_id'] = (string) $person->id;
        $this->nfeForm['uf'] = (string) ($person->uf ?? '');
        $this->nfeForm['cnpj'] = $this->formatNfeCpfCnpj($person->cpf_cnpj);
        $this->nfeForm['consumidor_final'] = $person->isConsumidorFinalPadrao();

        $this->nfeClienteCodigo = (string) ($person->codigo ?? '');
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
        $buscaNorm = mb_strtoupper(trim($this->nfeClienteBusca), 'UTF-8');
        $codigoNorm = mb_strtoupper(trim($this->nfeClienteCodigo), 'UTF-8');

        return $termNorm !== ''
            && ($termNorm === $buscaNorm || $termNorm === $codigoNorm);
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
        if (! Nfe::query()->whereKey($nfeId)->exists()) {
            Notification::make()
                ->title('NF-e não encontrada para impressão.')
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
        if (! $this->nfeFiscalSucessoNfeId) {
            $this->showNfeFiscalOverlayInfo('WhatsApp', 'NF-e não encontrada para envio.');

            return;
        }

        $this->prepareNfeWhatsAppModal($this->nfeFiscalSucessoNfeId, true);
    }

    public function openNfeWhatsAppFromList(): void
    {
        $nfeId = $this->resolveNfeWhatsAppTargetId();

        if (! $nfeId) {
            return;
        }

        $this->prepareNfeWhatsAppModal(
            $nfeId,
            $this->nfeModalOpen && filled($this->nfeFiscalSucessoDetalhe),
        );
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

        try {
            if ($documento === 'espelho') {
                $report = app(NfeEspelhoReportService::class);
                $pdf = $report->storePdfAttachment($nfe);
                $message = $report->defaultWhatsAppMessage($nfe, $destinatario);
            } else {
                $report = app(NfeDanfeReportService::class);
                $pdf = $report->storePdfAttachment($nfe);
                $message = $report->defaultWhatsAppMessage($nfe);
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

        $phoneDigits = WhatsAppPhone::digitsOnly($party?->celular1 ?: ($party?->whatsapp ?: ($party?->fone1 ?: '')));

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
                ->title('PDF da NF-e não encontrado.')
                ->body('Feche e abra novamente o envio por WhatsApp.')
                ->warning()
                ->send();

            return;
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
            $result = $sender->sendDocumentMessage(
                empresa: $empresa,
                tipo: WhatsAppSender::TIPO_NFE,
                number: $this->nfeWhatsAppTo,
                text: $this->nfeWhatsAppMessage,
                documentPath: $this->nfeWhatsAppPdfPath,
                documentName: $this->nfeWhatsAppPdfName !== '' ? $this->nfeWhatsAppPdfName : 'DANFE-NFE.PDF',
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
                : 'DANFE enviada por WhatsApp',
            descricao: $this->nfeWhatsAppDocumento === 'espelho'
                ? 'Espelho da NF-e em aberto enviado com anexo em PDF.'
                : 'Documento auxiliar da NF-e enviado com anexo em PDF.',
            destinatario: WhatsAppPhone::formatDisplay($this->nfeWhatsAppTo) ?? $this->nfeWhatsAppTo,
            metadata: [
                'contexto' => $this->nfeWhatsAppDocumento,
                'destinatario_tipo' => $this->nfeWhatsAppDestinatario,
                'arquivo' => $this->nfeWhatsAppPdfName !== '' ? $this->nfeWhatsAppPdfName : 'DANFE-NFE.PDF',
            ],
        );

        $this->closeNfeWhatsAppModal();
    }

    protected function cleanupNfeWhatsAppPdf(): void
    {
        if (is_string($this->nfeWhatsAppPdfPath) && is_file($this->nfeWhatsAppPdfPath)) {
            @unlink($this->nfeWhatsAppPdfPath);
        }

        $this->nfeWhatsAppPdfPath = null;
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
    }

    protected function showNfeFiscalOverlayErro(FiscalEngineException $exception): void
    {
        $this->closeNfeFiscalSucessoOverlay();
        $this->closeNfeFiscalInfoOverlay();

        $resolvido = PdvNfceFiscalMensagens::resolver($exception);
        $mensagem = $resolvido['corpo'] ?? trim($exception->getMessage());

        $this->nfeFiscalOverlayTitulo = mb_strtoupper($resolvido['titulo'], 'UTF-8');
        $this->nfeFiscalOverlayMensagem = $mensagem !== '' ? $mensagem : null;
        $this->nfeFiscalOverlayCodigo = $exception->sefazCodigo;
        $this->dispatch('erp-nfe-focus-fiscal-overlay');
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
    }
}
