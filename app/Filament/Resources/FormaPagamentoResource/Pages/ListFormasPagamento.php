<?php

namespace App\Filament\Resources\FormaPagamentoResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Resources\FormaPagamentoResource;
use App\Filament\Resources\FormaPagamentoResource\Pages\Concerns\ManagesFormaPagamentoBandeiras;
use App\Filament\Resources\FormaPagamentoResource\Pages\Concerns\ManagesFormaPagamentoMaquininhas;
use App\Models\CaixaConta;
use App\Models\FormaPagamento;
use App\Models\TabelaPrazo;
use App\Support\Erp\ErpScreen;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListFormasPagamento extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use ManagesFormaPagamentoBandeiras;
    use ManagesFormaPagamentoMaquininhas;

    protected static string $resource = FormaPagamentoResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public bool $showForm = false;

    public ?int $formId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public string $prazosRapidos = '';

    public function mount(): void
    {
        parent::mount();

        ErpScreen::set('Forma de Pagamento');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-formas-pgto-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma forma de pagamento';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-unidades__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createFormaPagamento';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editFormaPagamento';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteFormaPagamento';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return $this->buildSimpleListKeyboardConfig();
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(FormaPagamentoResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'descricao'], 'codigo');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.formas-pagamento.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.formas-pagamento.action-bar'),
                View::make('filament.components.erp.formas-pagamento.modal'),
                View::make('filament.components.erp.formas-pagamento.bandeiras-modal'),
            ]);
    }

    /**
     * @return array<int, string>
     */
    public function contaDestinoOptions(): array
    {
        return CaixaConta::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->all();
    }

    public function createFormaPagamento(): void
    {
        if ($this->showForm) {
            return;
        }

        $this->resetForm();
        $this->form['codigo'] = (int) (FormaPagamento::max('codigo') ?? 0) + 1;
        $this->showForm = true;
    }

    public function editFormaPagamento(): void
    {
        if ($this->showForm) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $record = FormaPagamento::find($recordId);

        if (! $record) {
            return;
        }

        $this->formId = $record->id;
        $this->form = [
            'codigo' => $record->codigo,
            'descricao' => $record->descricao,
            'conta_destino_id' => $record->conta_destino_id,
            'tipo' => $record->tipo,
            'taxa_cartao' => (float) $record->taxa_cartao,
            'prazo_cartao' => $record->prazo_cartao,
            'max_parcelas' => $record->max_parcelas,
            'intervalo_parcelas' => $record->intervalo_parcelas,
            'atalho' => $record->atalho,
            'tipo_movimento' => $record->tipo_movimento ?: 'nenhum',
            'ativo' => $record->ativo,
            'usa_tef' => $record->usa_tef,
            'usa_super_tef' => $record->usa_super_tef,
            'aparece_venda' => $record->aparece_venda,
            'aparece_contas_receber' => $record->aparece_contas_receber,
            'nfce' => $record->nfce,
            'disponivel_mobile' => $record->disponivel_mobile,
            'parcelas' => $this->normalizeParcelas($record->parcelas ?? []),
        ];
        $this->showForm = true;
    }

    public function saveFormaPagamento(): void
    {
        $data = $this->validate([
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique(FormaPagamento::class, 'codigo')->ignore($this->formId),
            ],
            'form.descricao' => ['required', 'string', 'max:120'],
            'form.conta_destino_id' => [
                'nullable',
                'integer',
                Rule::exists(CaixaConta::class, 'id'),
            ],
            'form.tipo' => ['nullable', Rule::in(array_keys(FormaPagamento::tipoLabels()))],
            'form.taxa_cartao' => ['nullable', 'numeric', 'min:0'],
            'form.prazo_cartao' => ['nullable', 'integer', 'min:0'],
            'form.max_parcelas' => ['nullable', 'integer', 'min:0'],
            'form.intervalo_parcelas' => ['nullable', 'integer', 'min:0'],
            'form.atalho' => ['nullable', 'string', 'max:5'],
            'form.tipo_movimento' => ['required', Rule::in(array_keys(FormaPagamento::tipoMovimentoLabels()))],
        ], [
            'form.max_parcelas.min' => 'O nº máximo de parcelas deve ser zero ou maior.',
        ], [
            'form.codigo' => 'código',
            'form.descricao' => 'nome',
            'form.tipo_movimento' => 'tipo de movimento',
            'form.max_parcelas' => 'nº máximo de parcelas',
            'form.conta_destino_id' => 'conta de destino',
        ])['form'];

        $payload = [
            'codigo' => (int) $data['codigo'],
            'descricao' => mb_strtoupper(trim($data['descricao']), 'UTF-8'),
            'conta_destino_id' => $data['conta_destino_id'] ?: null,
            'tipo' => $data['tipo'] ?: null,
            'taxa_cartao' => (float) ($data['taxa_cartao'] ?? 0),
            'prazo_cartao' => (int) ($data['prazo_cartao'] ?? 0),
            'max_parcelas' => (int) ($data['max_parcelas'] ?? 0),
            'intervalo_parcelas' => (int) ($data['intervalo_parcelas'] ?? 0),
            'atalho' => $data['atalho'] ? mb_strtoupper(trim($data['atalho']), 'UTF-8') : null,
            'tipo_movimento' => $data['tipo_movimento'],
            'ativo' => (bool) ($this->form['ativo'] ?? false),
            'usa_tef' => (bool) ($this->form['usa_tef'] ?? false),
            'usa_super_tef' => (bool) ($this->form['usa_super_tef'] ?? false),
            'aparece_venda' => (bool) ($this->form['aparece_venda'] ?? false),
            'aparece_contas_receber' => (bool) ($this->form['aparece_contas_receber'] ?? false),
            'nfce' => (bool) ($this->form['nfce'] ?? false),
            'disponivel_mobile' => (bool) ($this->form['disponivel_mobile'] ?? false),
            'parcelas' => $this->normalizeParcelas($this->form['parcelas'] ?? []),
        ];

        if ($this->formId) {
            FormaPagamento::whereKey($this->formId)->update($payload);
            $formaId = $this->formId;
        } else {
            $formaId = FormaPagamento::create($payload)->id;
        }

        $this->syncTabelasPrazo($formaId, $payload['parcelas']);

        $this->closeForm();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Forma de pagamento gravada.')
            ->success()
            ->send();
    }

    public function closeForm(): void
    {
        $this->closeBandeiraForm();
        $this->closeMaquininhaForm();
        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteFormaPagamento(): void
    {
        if ($this->showForm) {
            return;
        }

        $this->deleteSimpleRecord(FormaPagamento::class, 'Forma de pagamento excluída.');
    }

    protected function resetForm(): void
    {
        $this->formId = null;
        $this->prazosRapidos = '';
        $this->resetErrorBag();
        $this->form = [
            'codigo' => null,
            'descricao' => '',
            'conta_destino_id' => null,
            'tipo' => null,
            'taxa_cartao' => 0,
            'prazo_cartao' => 0,
            'max_parcelas' => 1,
            'intervalo_parcelas' => 30,
            'atalho' => null,
            'tipo_movimento' => 'nenhum',
            'ativo' => true,
            'usa_tef' => false,
            'usa_super_tef' => false,
            'aparece_venda' => true,
            'aparece_contas_receber' => false,
            'nfce' => false,
            'disponivel_mobile' => false,
            'parcelas' => [],
        ];
    }

    public function gerarParcelasFixas(): void
    {
        $qtd = max(1, (int) ($this->form['max_parcelas'] ?? 1));
        $intervalo = max(0, (int) ($this->form['intervalo_parcelas'] ?? 0));

        $dias = [];

        for ($i = 1; $i <= $qtd; $i++) {
            $dias[] = $intervalo * $i;
        }

        $this->adicionarTabelaPrazo(implode(',', $dias));
    }

    public function gerarPrazosRapidos(): void
    {
        $tabela = $this->normalizeTabelaString($this->prazosRapidos);

        if ($tabela === '') {
            Notification::make()
                ->title('Informe os prazos em dias (ex.: 30,60,90).')
                ->warning()
                ->send();

            return;
        }

        $this->adicionarTabelaPrazo($tabela);
        $this->prazosRapidos = '';
    }

    public function adicionarParcelaVariavel(): void
    {
        $this->adicionarTabelaPrazo('');
    }

    public function removerParcela(int $index): void
    {
        $tabelas = array_values($this->form['parcelas'] ?? []);

        unset($tabelas[$index]);

        $this->form['parcelas'] = array_values($tabelas);
    }

    /**
     * @param  array<int, string>  $tabelas
     */
    protected function syncTabelasPrazo(int $formaId, array $tabelas): void
    {
        TabelaPrazo::where('forma_pagamento_id', $formaId)->delete();

        $ordem = 1;

        foreach ($tabelas as $dias) {
            $dias = trim((string) $dias);

            if ($dias === '') {
                continue;
            }

            TabelaPrazo::create([
                'forma_pagamento_id' => $formaId,
                'dias' => $dias,
                'ordem' => $ordem++,
            ]);
        }
    }

    protected function adicionarTabelaPrazo(string $tabela): void
    {
        $tabelas = array_values($this->form['parcelas'] ?? []);
        $tabelas[] = $tabela;

        $this->form['parcelas'] = $tabelas;
    }

    /**
     * Cada item de "parcelas" é uma tabela de prazo (string de dias separados por vírgula).
     *
     * @param  array<int, mixed>  $parcelas
     * @return array<int, string>
     */
    protected function normalizeParcelas(array $parcelas): array
    {
        // Formato antigo (lista de {numero, dias}) → vira uma única tabela.
        if (isset($parcelas[0]) && is_array($parcelas[0]) && array_key_exists('dias', $parcelas[0])) {
            $dias = [];

            foreach ($parcelas as $parcela) {
                $dias[] = (int) ($parcela['dias'] ?? 0);
            }

            return [implode(',', $dias)];
        }

        $normalized = [];

        foreach (array_values($parcelas) as $item) {
            $tabela = $this->normalizeTabelaString(is_string($item) ? $item : '');

            if ($tabela !== '') {
                $normalized[] = $tabela;
            }
        }

        return $normalized;
    }

    protected function normalizeTabelaString(string $value): string
    {
        $tokens = preg_split('/[\s,;.]+/', trim($value)) ?: [];

        $dias = [];

        foreach ($tokens as $token) {
            if ($token === '' || ! is_numeric($token)) {
                continue;
            }

            $dias[] = (int) $token;
        }

        return implode(',', $dias);
    }
}
