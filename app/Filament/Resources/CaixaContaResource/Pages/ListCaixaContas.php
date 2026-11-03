<?php

namespace App\Filament\Resources\CaixaContaResource\Pages;

use App\Filament\Concerns\InteractsWithErpListPage;
use App\Filament\Concerns\InteractsWithErpSimpleListPage;
use App\Filament\Concerns\NormalizesErpUppercaseFormData;
use App\Filament\Resources\CaixaContaResource;
use App\Models\CaixaConta;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Financeiro\CaixaContaExclusaoGuard;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;

class ListCaixaContas extends ListRecords
{
    use InteractsWithErpListPage;
    use InteractsWithErpSimpleListPage;
    use NormalizesErpUppercaseFormData;

    protected static string $resource = CaixaContaResource::class;

    protected static ?string $title = '';

    #[Url(as: 'q')]
    public string $localSearch = '';

    #[Url(as: 'campo')]
    public string $searchColumn = 'codigo';

    public bool $showForm = false;

    public ?int $formId = null;

    public bool $deleteConfirmOpen = false;

    public ?int $deleteConfirmRecordId = null;

    public string $deleteConfirmLabel = '';

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(): void
    {
        parent::mount();

        CaixaConta::ensureCaixaGeral();
        ErpScreen::set('Contas');
    }

    protected static function erpListPageClass(): string
    {
        return 'erp-contas-caixa-page';
    }

    protected function erpListEntityName(): string
    {
        return 'uma conta caixa';
    }

    protected function erpSimpleListSearchInput(): string
    {
        return '.erp-contas-caixa__input';
    }

    protected function erpSimpleListCreateMethod(): string
    {
        return 'createContaCaixa';
    }

    protected function erpSimpleListEditMethod(): string
    {
        return 'editContaCaixa';
    }

    protected function erpSimpleListDeleteMethod(): string
    {
        return 'deleteContaCaixa';
    }

    protected function customErpListKeyboardConfig(): array
    {
        return [
            ...$this->buildSimpleListKeyboardConfig(),
            'refresh' => null,
            'blockingSelector' => '.erp-contas-caixa-modal, .erp-plano-conta-confirm',
            'extraKeys' => [
                'F4' => ['method' => 'deleteContaCaixa'],
            ],
        ];
    }

    public function table(Table $table): Table
    {
        return $this->applyErpListSelection(CaixaContaResource::table($table));
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery()
            ->visiveisNaEmpresa()
            ->with('ultimoUsuario');

        if (filled($this->localSearch)) {
            $this->applySimpleLocalSearch($query, $this->localSearch, ['codigo', 'nome'], 'codigo');
        }

        return $query;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.contas-caixa.titlebar'),
                View::make('filament.components.erp.contas-caixa.screen'),
                EmbeddedTable::make()->columnSpanFull(),
                View::make('filament.components.erp.contas-caixa.action-bar'),
                View::make('filament.components.erp.contas-caixa.modal'),
                View::make('filament.components.erp.contas-caixa.confirm-delete-modal'),
            ]);
    }

    public function handleContasCaixaEscape(): void
    {
        if ($this->deleteConfirmOpen) {
            $this->cancelDeleteContaCaixa();

            return;
        }

        if ($this->showForm) {
            $this->closeForm();

            return;
        }

        $this->closeScreen();
    }

    public function createContaCaixa(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        $this->resetForm();
        $this->form['codigo'] = (int) (CaixaConta::max('codigo') ?? 0) + 1;
        $this->showForm = true;
    }

    public function editContaCaixa(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $record = CaixaConta::query()->visiveisNaEmpresa()->find($recordId);

        if (! $record) {
            return;
        }

        if ($record->isSistema()) {
            Notification::make()
                ->title('Conta do sistema.')
                ->body('O CAIXA GERAL é protegido (fechamento do PDV) e não pode ser alterado.')
                ->warning()
                ->send();

            return;
        }

        $ehBanco = (string) $record->tipo === CaixaConta::TIPO_BANCO;

        $this->formId = $record->id;
        $this->form = [
            'codigo' => $record->codigo,
            'nome' => $record->nome,
            'eh_bancaria' => $ehBanco,
            'tipo' => match ((string) $record->tipo) {
                'CAIXA', 'X' => CaixaConta::TIPO_PDV,
                CaixaConta::TIPO_BANCO => CaixaConta::TIPO_BANCO,
                default => $record->tipo,
            },
            'ativo' => $record->ativo,
            'banco_codigo' => (string) ($record->banco_codigo ?? ''),
            'agencia' => (string) ($record->agencia ?? ''),
            'agencia_dv' => (string) ($record->agencia_dv ?? ''),
            'conta_numero' => (string) ($record->conta_numero ?? ''),
            'conta_dv' => (string) ($record->conta_dv ?? ''),
            'conta_tipo_bancaria' => (string) ($record->conta_tipo_bancaria ?? CaixaConta::CONTA_CORRENTE),
            'titular_nome' => (string) ($record->titular_nome ?? ''),
            'titular_documento' => (string) ($record->titular_documento ?? ''),
        ];
        $this->showForm = true;
    }

    public function updatedFormEhBancaria(mixed $value): void
    {
        $marcado = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($marcado) {
            $this->form['tipo'] = CaixaConta::TIPO_BANCO;
            if (($this->form['conta_tipo_bancaria'] ?? '') === '') {
                $this->form['conta_tipo_bancaria'] = CaixaConta::CONTA_CORRENTE;
            }

            return;
        }

        if (($this->form['tipo'] ?? '') === CaixaConta::TIPO_BANCO) {
            $this->form['tipo'] = CaixaConta::TIPO_SUBCAIXA;
        }
    }

    public function updatedFormTipo(mixed $value): void
    {
        $tipo = (string) $value;
        $this->form['eh_bancaria'] = $tipo === CaixaConta::TIPO_BANCO;
    }

    public function updatedFormBancoCodigo(mixed $value): void
    {
        $codigo = preg_replace('/\D/', '', (string) $value) ?? '';
        $this->form['banco_codigo'] = $codigo;
    }

    public function saveContaCaixa(): void
    {
        if ($this->deleteConfirmOpen) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $existing = null;

        if ($this->formId) {
            $existing = CaixaConta::query()
                ->visiveisNaEmpresa($empresaId > 0 ? $empresaId : null)
                ->find($this->formId);

            if (! $existing) {
                Notification::make()
                    ->title('Conta não encontrada nesta empresa.')
                    ->warning()
                    ->send();
                $this->closeForm();

                return;
            }

            if ($existing->isSistema()) {
                Notification::make()
                    ->title('Conta do sistema não pode ser alterada.')
                    ->warning()
                    ->send();

                $this->closeForm();

                return;
            }
        }

        $ehBancaria = filter_var($this->form['eh_bancaria'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($ehBancaria) {
            $this->form['tipo'] = CaixaConta::TIPO_BANCO;
        }

        $regras = [
            'form.codigo' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('caixa_contas', 'codigo')->ignore($this->formId),
            ],
            'form.nome' => ['required', 'string', 'max:120'],
            'form.tipo' => ['required', Rule::in(CaixaConta::tiposValidos())],
        ];

        $atributos = [
            'form.codigo' => 'código',
            'form.nome' => 'descrição',
            'form.tipo' => 'tipo',
        ];

        if ($ehBancaria) {
            $regras['form.banco_codigo'] = ['required', 'string', 'max:10'];
            $regras['form.agencia'] = ['required', 'string', 'max:20'];
            $regras['form.conta_numero'] = ['required', 'string', 'max:40'];
            $regras['form.conta_tipo_bancaria'] = ['required', Rule::in(array_keys(CaixaConta::contaTipoBancariaLabels()))];
            $regras['form.titular_nome'] = ['required', 'string', 'max:120'];
            $regras['form.titular_documento'] = ['required', 'string', 'max:20'];
            $regras['form.agencia_dv'] = ['nullable', 'string', 'max:4'];
            $regras['form.conta_dv'] = ['nullable', 'string', 'max:4'];

            $atributos['form.banco_codigo'] = 'banco';
            $atributos['form.agencia'] = 'agência';
            $atributos['form.conta_numero'] = 'número da conta';
            $atributos['form.conta_tipo_bancaria'] = 'tipo de conta';
            $atributos['form.titular_nome'] = 'titular';
            $atributos['form.titular_documento'] = 'CPF/CNPJ do titular';
        }

        $data = $this->validate($regras, [], $atributos)['form'];

        if ($ehBancaria && $empresaId <= 0) {
            Notification::make()
                ->title('Empresa logada obrigatória.')
                ->body('Contas bancárias só podem ser cadastradas com uma empresa ativa.')
                ->warning()
                ->send();

            return;
        }

        $nome = mb_strtoupper(trim($data['nome']), 'UTF-8');

        if ($nome === CaixaConta::NOME_CAIXA_GERAL) {
            Notification::make()
                ->title('Nome reservado.')
                ->body('CAIXA GERAL é uma conta do sistema e não pode ser recriada.')
                ->warning()
                ->send();

            return;
        }

        $tipo = (string) $data['tipo'];
        if ($ehBancaria) {
            $tipo = CaixaConta::TIPO_BANCO;
        } elseif ($tipo === CaixaConta::TIPO_BANCO) {
            $tipo = CaixaConta::TIPO_SUBCAIXA;
        }

        if ($existing
            && ((string) $existing->tipo === CaixaConta::TIPO_BANCO || $existing->temDadosBancarios())
            && $tipo !== CaixaConta::TIPO_BANCO
        ) {
            Notification::make()
                ->title('Tipo não pode ser alterado.')
                ->body('Esta conta possui dados bancários. Mantenha como conta bancária para não perder as informações.')
                ->warning()
                ->send();

            return;
        }

        if ($existing && (string) $existing->tipo === CaixaConta::TIPO_PDV && $tipo === CaixaConta::TIPO_BANCO) {
            Notification::make()
                ->title('Conta PDV protegida.')
                ->body('Não é permitido converter uma conta PDV em conta bancária.')
                ->warning()
                ->send();

            return;
        }

        $bancoCodigo = '';
        $bancoNome = null;
        $agencia = null;
        $agenciaDv = null;
        $contaNumero = null;
        $contaDv = null;
        $contaTipo = null;
        $titularNome = null;
        $titularDocumento = null;

        if ($tipo === CaixaConta::TIPO_BANCO) {
            $bancoCodigo = str_pad(preg_replace('/\D/', '', (string) ($data['banco_codigo'] ?? '')) ?? '', 3, '0', STR_PAD_LEFT);
            $bancos = EmpresaParametros::boletoBancoOptions();
            $bancoNome = $bancos[$bancoCodigo] ?? null;
            if ($bancoNome === null || $bancoCodigo === '' || $bancoCodigo === '000') {
                Notification::make()
                    ->title('Banco inválido.')
                    ->body('Selecione um banco da lista COMPE.')
                    ->warning()
                    ->send();

                return;
            }

            $agencia = mb_substr(trim((string) ($data['agencia'] ?? '')), 0, 20);
            $agenciaDv = mb_substr(trim((string) ($data['agencia_dv'] ?? '')), 0, 4) ?: null;
            $contaNumero = mb_substr(trim((string) ($data['conta_numero'] ?? '')), 0, 40);
            $contaDv = mb_substr(trim((string) ($data['conta_dv'] ?? '')), 0, 4) ?: null;
            $contaTipo = (string) ($data['conta_tipo_bancaria'] ?? CaixaConta::CONTA_CORRENTE);
            $titularNome = mb_substr(mb_strtoupper(trim((string) ($data['titular_nome'] ?? '')), 'UTF-8'), 0, 120);
            $titularDocumento = mb_substr(preg_replace('/\D/', '', (string) ($data['titular_documento'] ?? '')) ?? '', 0, 20);
        }

        $payload = [
            'codigo' => (int) $data['codigo'],
            'nome' => $nome,
            'tipo' => $tipo,
            'situacao' => CaixaConta::SITUACAO_ABERTO,
            'ativo' => (bool) ($this->form['ativo'] ?? true),
            'sistema' => false,
            'ultimo_usuario_id' => Auth::id(),
        ];

        if (SchemaFacade::hasColumn('caixa_contas', 'empresa_id')) {
            if ($existing) {
                // empresa_id de conta existente nunca muda pela UI.
                $payload['empresa_id'] = $existing->empresa_id;
            } else {
                $payload['empresa_id'] = $tipo === CaixaConta::TIPO_BANCO ? $empresaId : null;
            }
        }

        if (SchemaFacade::hasColumn('caixa_contas', 'banco_codigo')) {
            if ($tipo === CaixaConta::TIPO_BANCO) {
                $payload['banco_codigo'] = $bancoCodigo;
                $payload['banco_nome'] = mb_substr((string) $bancoNome, 0, 80);
                $payload['agencia'] = $agencia;
                $payload['agencia_dv'] = $agenciaDv;
                $payload['conta_numero'] = $contaNumero;
                $payload['conta_dv'] = $contaDv;
                $payload['conta_tipo_bancaria'] = $contaTipo;
                $payload['titular_nome'] = $titularNome;
                $payload['titular_documento'] = $titularDocumento !== '' ? $titularDocumento : null;
            } elseif (! $existing?->temDadosBancarios()) {
                $payload['banco_codigo'] = null;
                $payload['banco_nome'] = null;
                $payload['agencia'] = null;
                $payload['agencia_dv'] = null;
                $payload['conta_numero'] = null;
                $payload['conta_dv'] = null;
                $payload['conta_tipo_bancaria'] = null;
                $payload['titular_nome'] = null;
                $payload['titular_documento'] = null;
            }
        }

        if ($this->formId) {
            CaixaConta::whereKey($this->formId)->update($payload);
        } else {
            CaixaConta::create($payload);
        }

        $this->closeForm();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Conta caixa gravada.')
            ->success()
            ->send();
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function deleteContaCaixa(): void
    {
        if ($this->showForm || $this->deleteConfirmOpen) {
            return;
        }

        if (! erp_can('contas_caixa.delete')) {
            Notification::make()
                ->title('Sem permissão para excluir contas caixa.')
                ->warning()
                ->send();

            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('delete');

        if (! $recordId) {
            return;
        }

        $record = CaixaConta::query()->visiveisNaEmpresa()->find($recordId);

        if (! $record) {
            Notification::make()
                ->title('Conta caixa não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $motivo = CaixaContaExclusaoGuard::motivoBloqueio($record);

        if ($motivo !== null) {
            Notification::make()
                ->title('Exclusão não permitida.')
                ->body($motivo)
                ->warning()
                ->send();

            return;
        }

        $this->deleteConfirmRecordId = (int) $record->id;
        $this->deleteConfirmLabel = trim(
            (string) $record->codigo.' — '.mb_strtoupper((string) $record->nome, 'UTF-8')
        );
        $this->deleteConfirmOpen = true;
    }

    public function confirmDeleteContaCaixa(): void
    {
        if (! $this->deleteConfirmRecordId) {
            $this->cancelDeleteContaCaixa();

            return;
        }

        if (! erp_can('contas_caixa.delete')) {
            $this->cancelDeleteContaCaixa();
            Notification::make()
                ->title('Sem permissão para excluir contas caixa.')
                ->warning()
                ->send();

            return;
        }

        try {
            DB::transaction(function (): void {
                $conta = CaixaConta::query()
                    ->visiveisNaEmpresa()
                    ->whereKey($this->deleteConfirmRecordId)
                    ->lockForUpdate()
                    ->first();

                if (! $conta) {
                    throw new \RuntimeException('Conta caixa não encontrada.');
                }

                $motivo = CaixaContaExclusaoGuard::motivoBloqueio($conta);

                if ($motivo !== null) {
                    throw new \DomainException($motivo);
                }

                $conta->delete();
            });
        } catch (\DomainException $e) {
            $this->cancelDeleteContaCaixa();
            Notification::make()
                ->title('Exclusão não permitida.')
                ->body($e->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            $this->cancelDeleteContaCaixa();
            Notification::make()
                ->title('Não foi possível excluir a conta caixa.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->cancelDeleteContaCaixa();
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title('Conta caixa excluída.')
            ->success()
            ->send();
    }

    public function cancelDeleteContaCaixa(): void
    {
        $this->deleteConfirmOpen = false;
        $this->deleteConfirmRecordId = null;
        $this->deleteConfirmLabel = '';
    }

    protected function resetForm(): void
    {
        $this->formId = null;
        $this->resetErrorBag();
        $this->form = [
            'codigo' => null,
            'nome' => '',
            'eh_bancaria' => false,
            'tipo' => CaixaConta::TIPO_PDV,
            'ativo' => true,
            'banco_codigo' => '',
            'agencia' => '',
            'agencia_dv' => '',
            'conta_numero' => '',
            'conta_dv' => '',
            'conta_tipo_bancaria' => CaixaConta::CONTA_CORRENTE,
            'titular_nome' => '',
            'titular_documento' => '',
        ];
    }
}
