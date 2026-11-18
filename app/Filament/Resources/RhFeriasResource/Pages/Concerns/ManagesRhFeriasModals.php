<?php

namespace App\Filament\Resources\RhFeriasResource\Pages\Concerns;

use App\Filament\Resources\RhFeriasResource;
use App\Models\RhAnexo;
use App\Models\RhFeriasGozo;
use App\Models\RhFeriasPeriodo;
use App\Models\RhFuncionario;
use App\Support\Erp\ErpAccess;
use App\Support\Rh\RhFeriasService;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesRhFeriasModals
{
    use WithFileUploads;

    public bool $feriasGozoModalOpen = false;

    public ?int $feriasGozoModalRecordId = null;

    /** @var array<string, mixed> */
    public array $feriasGozoForm = [];

    public ?TemporaryUploadedFile $feriasAvisoUpload = null;

    public string $feriasAvisoArquivoAtual = '';

    public bool $feriasPeriodoModalOpen = false;

    /** @var array<string, mixed> */
    public array $feriasPeriodoForm = [];

    public bool $feriasCancelModalOpen = false;

    public string $feriasCancelMotivo = '';

    public bool $feriasSaldoModalOpen = false;

    /** @var array<string, mixed> */
    public array $feriasSaldoForm = [];

    public function createFeriasGozo(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.create')) {
            Notification::make()->title('Sem permissão para incluir.')->warning()->send();

            return;
        }

        $this->feriasGozoModalRecordId = null;
        $this->feriasGozoForm = $this->defaultFeriasGozoForm();
        $this->feriasAvisoUpload = null;
        $this->feriasAvisoArquivoAtual = '';
        $this->feriasGozoModalOpen = true;
    }

    public function editFeriasGozo(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.update')) {
            Notification::make()->title('Sem permissão para alterar.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $record = RhFeriasGozo::query()->with(['periodo', 'anexos' => fn ($q) => $q->where('categoria', RhAnexo::CAT_FERIAS)->limit(1)])
            ->find($this->highlightedRecordId);
        if (! $record || $record->situacao === RhFeriasGozo::SIT_CANCELADA) {
            Notification::make()->title('Gozo não encontrado ou cancelado.')->warning()->send();

            return;
        }

        $this->feriasGozoModalRecordId = (int) $record->getKey();
        $this->feriasGozoForm = $this->feriasGozoFormFromRecord($record);
        $this->feriasAvisoUpload = null;
        $anexo = $record->anexos->first();
        $this->feriasAvisoArquivoAtual = $anexo && filled($anexo->caminho) ? basename((string) $anexo->caminho) : '';
        $this->feriasGozoModalOpen = true;
    }

    public function closeFeriasGozoModal(): void
    {
        $this->feriasGozoModalOpen = false;
        $this->feriasGozoModalRecordId = null;
        $this->feriasGozoForm = [];
        $this->feriasAvisoUpload = null;
        $this->feriasAvisoArquivoAtual = '';
        $this->resetValidation();
    }

    public function openFeriasPeriodoModal(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.create')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->feriasPeriodoForm = [
            'funcionario_id' => '',
            'dias_direito' => 30,
            'observacao' => '',
        ];
        $this->feriasPeriodoModalOpen = true;
    }

    public function closeFeriasPeriodoModal(): void
    {
        $this->feriasPeriodoModalOpen = false;
        $this->feriasPeriodoForm = [];
        $this->resetValidation();
    }

    public function saveFeriasPeriodo(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.create')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->validate([
            'feriasPeriodoForm.funcionario_id' => ['required', 'integer', Rule::exists('rh_funcionarios', 'id')],
            'feriasPeriodoForm.dias_direito' => ['required', 'integer', 'min:1', 'max:365'],
            'feriasPeriodoForm.observacao' => ['nullable', 'string', 'max:500'],
        ], [], [
            'feriasPeriodoForm.funcionario_id' => 'funcionário',
            'feriasPeriodoForm.dias_direito' => 'dias de direito',
        ]);

        $func = RhFuncionario::query()->find((int) $this->feriasPeriodoForm['funcionario_id']);
        if (! $func) {
            Notification::make()->title('Funcionário inválido.')->danger()->send();

            return;
        }

        try {
            $periodo = app(RhFeriasService::class)->gerarPeriodoAquisitivo(
                $func,
                (int) $this->feriasPeriodoForm['dias_direito'],
                $this->feriasPeriodoForm['observacao'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeFeriasPeriodoModal();
        $this->feriasGozoForm['funcionario_id'] = (int) $func->getKey();
        $this->feriasGozoForm['periodo_id'] = (int) $periodo->getKey();
        Notification::make()->title('Período aquisitivo '.$periodo->codigo.' gerado.')->success()->send();
    }

    public function saveFeriasGozo(): void
    {
        $isEdit = $this->feriasGozoModalRecordId !== null;

        if (! ErpAccess::currentCan($isEdit ? 'rh.ferias.update' : 'rh.ferias.create')) {
            Notification::make()->title('Sem permissão para gravar.')->warning()->send();

            return;
        }

        $rules = [
            'feriasGozoForm.periodo_id' => ['required', 'integer', Rule::exists('rh_ferias_periodos', 'id')],
            'feriasGozoForm.gozo_inicio' => ['required', 'date'],
            'feriasGozoForm.gozo_fim' => ['required', 'date', 'after_or_equal:feriasGozoForm.gozo_inicio'],
            'feriasGozoForm.aviso_em' => ['nullable', 'date'],
            'feriasGozoForm.observacao' => ['nullable', 'string', 'max:500'],
            'feriasGozoForm.ajuste_admin' => ['boolean'],
            'feriasGozoForm.motivo_ajuste' => ['nullable', 'string', 'max:255'],
            'feriasAvisoUpload' => ['nullable', 'file', 'max:5120'],
        ];

        $this->validate($rules, [], [
            'feriasGozoForm.periodo_id' => 'período',
            'feriasGozoForm.gozo_inicio' => 'início',
            'feriasGozoForm.gozo_fim' => 'fim',
            'feriasAvisoUpload' => 'aviso',
        ]);

        $payload = [
            'gozo_inicio' => $this->feriasGozoForm['gozo_inicio'],
            'gozo_fim' => $this->feriasGozoForm['gozo_fim'],
            'aviso_em' => $this->feriasGozoForm['aviso_em'] ?? null,
            'observacao' => $this->feriasGozoForm['observacao'] ?? null,
            'ajuste_admin' => (bool) ($this->feriasGozoForm['ajuste_admin'] ?? false),
            'motivo_ajuste' => $this->feriasGozoForm['motivo_ajuste'] ?? null,
        ];

        $upload = $this->feriasAvisoUpload instanceof TemporaryUploadedFile ? $this->feriasAvisoUpload : null;
        $service = app(RhFeriasService::class);

        try {
            if ($isEdit) {
                $record = RhFeriasGozo::query()->find($this->feriasGozoModalRecordId);
                if (! $record) {
                    Notification::make()->title('Registro não encontrado.')->warning()->send();

                    return;
                }
                $service->alterarGozo($record, $payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Gozo alterado.';
            } else {
                $periodo = RhFeriasPeriodo::query()->find((int) $this->feriasGozoForm['periodo_id']);
                if (! $periodo) {
                    Notification::make()->title('Período inválido.')->danger()->send();

                    return;
                }
                $record = $service->programarGozo($periodo, $payload, $upload);
                $id = (int) $record->getKey();
                $title = 'Gozo programado.';

                if ($record->alertaAvisoPrazo()) {
                    Notification::make()->title('Atenção: aviso de férias ausente ou com menos de 30 dias de antecedência.')
                        ->warning()
                        ->send();
                }
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeFeriasGozoModal();
        $this->clearListSelection();
        $this->resetTable();
        $this->highlightRecord($id);
        Notification::make()->title($title)->success()->send();
    }

    public function cancelarFeriasGozo(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('cancel')) {
            return;
        }

        $this->feriasCancelMotivo = '';
        $this->feriasCancelModalOpen = true;
    }

    public function closeFeriasCancelModal(): void
    {
        $this->feriasCancelModalOpen = false;
        $this->feriasCancelMotivo = '';
    }

    public function confirmCancelarFeriasGozo(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.delete')) {
            Notification::make()->title('Sem permissão.')->warning()->send();
            $this->closeFeriasCancelModal();

            return;
        }

        $this->validate([
            'feriasCancelMotivo' => ['required', 'string', 'min:3', 'max:255'],
        ], [], ['feriasCancelMotivo' => 'motivo']);

        $record = RhFeriasGozo::query()->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Gozo não encontrado.')->warning()->send();
            $this->closeFeriasCancelModal();

            return;
        }

        try {
            app(RhFeriasService::class)->cancelar($record, $this->feriasCancelMotivo);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeFeriasCancelModal();
        $this->clearListSelection();
        $this->resetTable();
        Notification::make()->title('Gozo cancelado e saldo restituído.')->success()->send();
    }

    public function openAjusteSaldoModal(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $gozo = RhFeriasGozo::query()->with('periodo')->find($this->highlightedRecordId);
        if (! $gozo?->periodo) {
            Notification::make()->title('Período não encontrado.')->warning()->send();

            return;
        }

        $this->feriasSaldoForm = [
            'periodo_id' => (int) $gozo->periodo_id,
            'novo_saldo' => (int) $gozo->periodo->dias_saldo,
            'motivo' => '',
        ];
        $this->feriasSaldoModalOpen = true;
    }

    public function closeFeriasSaldoModal(): void
    {
        $this->feriasSaldoModalOpen = false;
        $this->feriasSaldoForm = [];
    }

    public function saveAjusteSaldoFerias(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $this->validate([
            'feriasSaldoForm.periodo_id' => ['required', 'integer'],
            'feriasSaldoForm.novo_saldo' => ['required', 'integer', 'min:0', 'max:365'],
            'feriasSaldoForm.motivo' => ['required', 'string', 'min:3', 'max:255'],
        ], [], [
            'feriasSaldoForm.novo_saldo' => 'novo saldo',
            'feriasSaldoForm.motivo' => 'motivo',
        ]);

        $periodo = RhFeriasPeriodo::query()->find((int) $this->feriasSaldoForm['periodo_id']);
        if (! $periodo) {
            Notification::make()->title('Período não encontrado.')->danger()->send();

            return;
        }

        try {
            app(RhFeriasService::class)->ajustarSaldoAdmin(
                $periodo,
                (int) $this->feriasSaldoForm['novo_saldo'],
                $this->feriasSaldoForm['motivo'],
            );
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->closeFeriasSaldoModal();
        $this->resetTable();
        Notification::make()->title('Saldo ajustado.')->success()->send();
    }

    public function marcarFeriasEmAndamento(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $record = RhFeriasGozo::query()->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Selecione um gozo.')->warning()->send();

            return;
        }

        try {
            app(RhFeriasService::class)->marcarEmAndamento($record);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->resetTable();
        Notification::make()->title('Gozo em andamento.')->success()->send();
    }

    public function concluirFeriasGozo(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.update')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        $record = RhFeriasGozo::query()->find($this->highlightedRecordId);
        if (! $record) {
            Notification::make()->title('Selecione um gozo.')->warning()->send();

            return;
        }

        try {
            app(RhFeriasService::class)->concluir($record);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->resetTable();
        Notification::make()->title('Gozo concluído.')->success()->send();
    }

    public function abrirAvisoFeriasSelecionado(): void
    {
        if (! ErpAccess::currentCan('rh.ferias.access')) {
            Notification::make()->title('Sem permissão.')->warning()->send();

            return;
        }

        if (! $this->highlightedRecordIdOrNotify('open')) {
            return;
        }

        $gozo = RhFeriasGozo::query()->find($this->highlightedRecordId);
        if (! $gozo) {
            return;
        }

        $anexo = $gozo->anexos()
            ->where('categoria', RhAnexo::CAT_FERIAS)
            ->orderByDesc('id')
            ->first();

        if (! $anexo?->downloadUrl()) {
            Notification::make()->title('Aviso não anexado.')->warning()->send();

            return;
        }

        $this->dispatch('erp-open-url', url: $anexo->downloadUrl());
    }

  /**
     * @return list<array{id:int,label:string}>
     */
    public function feriasFuncionarioOptions(): array
    {
        return RhFeriasResource::funcionariosQuery()->get(['id', 'codigo', 'nome'])->map(
            fn (RhFuncionario $f): array => [
                'id' => (int) $f->id,
                'label' => trim($f->codigo.' — '.$f->nome),
            ],
        )->all();
    }

    /**
     * @return list<array{id:int,label:string,saldo:int}>
     */
    public function feriasPeriodoOptions(): array
    {
        $funcId = filled($this->feriasGozoForm['funcionario_id'] ?? null)
            ? (int) $this->feriasGozoForm['funcionario_id']
            : null;

        $query = RhFeriasPeriodo::query()
            ->where('ativo', true)
            ->where('dias_saldo', '>', 0)
            ->whereNotIn('situacao', [RhFeriasPeriodo::SIT_CANCELADO, RhFeriasPeriodo::SIT_GOZADO])
            ->orderByDesc('aquisitivo_inicio');

        if ($funcId) {
            $query->where('funcionario_id', $funcId);
        }

        return $query->limit(100)->get()->map(fn (RhFeriasPeriodo $p): array => [
            'id' => (int) $p->id,
            'label' => $p->codigo.' | '.$p->aquisitivoResumo().' (saldo '.$p->dias_saldo.')',
            'saldo' => (int) $p->dias_saldo,
        ])->all();
    }

    public function updatedFeriasGozoFormFuncionarioId(): void
    {
        $this->feriasGozoForm['periodo_id'] = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultFeriasGozoForm(): array
    {
        return [
            'funcionario_id' => '',
            'periodo_id' => '',
            'gozo_inicio' => '',
            'gozo_fim' => '',
            'aviso_em' => '',
            'observacao' => '',
            'ajuste_admin' => false,
            'motivo_ajuste' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function feriasGozoFormFromRecord(RhFeriasGozo $record): array
    {
        return [
            'funcionario_id' => (int) $record->funcionario_id,
            'periodo_id' => (int) $record->periodo_id,
            'gozo_inicio' => $record->gozo_inicio?->toDateString() ?? '',
            'gozo_fim' => $record->gozo_fim?->toDateString() ?? '',
            'aviso_em' => $record->aviso_em?->toDateString() ?? '',
            'observacao' => $record->observacao ?? '',
            'ajuste_admin' => (bool) $record->ajuste_admin,
            'motivo_ajuste' => '',
        ];
    }
}
