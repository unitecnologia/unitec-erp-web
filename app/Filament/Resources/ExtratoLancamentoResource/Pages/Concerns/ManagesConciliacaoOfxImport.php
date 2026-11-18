<?php

namespace App\Filament\Resources\ExtratoLancamentoResource\Pages\Concerns;

use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Financeiro\Conciliacao\OfxImportResult;
use App\Support\Erp\Financeiro\Conciliacao\OfxImportService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesConciliacaoOfxImport
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $ofxUpload = null;

    /** @var array<string, mixed>|null */
    public ?array $ofxImportSummary = null;

    public function updatedOfxUpload(): void
    {
        if ($this->ofxUpload instanceof TemporaryUploadedFile) {
            $this->importarOfx();
        }
    }

    public function limparResumoOfx(): void
    {
        $this->ofxImportSummary = null;
    }

    public function consultarPeriodoImportado(): void
    {
        $de = is_array($this->ofxImportSummary) ? ($this->ofxImportSummary['periodo_de'] ?? null) : null;
        $ate = is_array($this->ofxImportSummary) ? ($this->ofxImportSummary['periodo_ate'] ?? null) : null;

        if ((! is_string($de) || $de === '') || (! is_string($ate) || $ate === '')) {
            $this->ajustarFiltroParaTodoExtratoDaConta();

            return;
        }

        $this->aplicarPeriodoDoExtratoImportado($de, $ate);
        $this->clearListSelection();
        $this->resetTable();
    }

    /**
     * Ajusta datas para MIN/MAX do extrato da conta selecionada (1 query, só no clique).
     */
    public function ajustarFiltroParaTodoExtratoDaConta(): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $contaId = (int) $this->caixaContaId;

        if ($empresaId <= 0 || $contaId <= 0) {
            Notification::make()
                ->title('Selecione a conta bancária')
                ->warning()
                ->send();

            return;
        }

        $bounds = \App\Models\ExtratoLancamento::query()
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->selectRaw('MIN(data_movimento) as dmin, MAX(data_movimento) as dmax, COUNT(*) as total')
            ->first();

        $total = (int) ($bounds->total ?? 0);
        if ($total <= 0) {
            Notification::make()
                ->title('Nenhum lançamento de extrato nesta conta.')
                ->warning()
                ->send();

            return;
        }

        $this->aplicarPeriodoDoExtratoImportado(
            isset($bounds->dmin) ? (string) $bounds->dmin : null,
            isset($bounds->dmax) ? (string) $bounds->dmax : null,
        );

        Notification::make()
            ->title('Filtro ajustado ao período do extrato')
            ->body(sprintf(
                '%d lançamento(s) de %s a %s.',
                $total,
                \Carbon\Carbon::parse((string) $bounds->dmin)->format('d/m/Y'),
                \Carbon\Carbon::parse((string) $bounds->dmax)->format('d/m/Y'),
            ))
            ->success()
            ->send();

        $this->clearListSelection();
        $this->resetTable();
    }

    private function aplicarPeriodoDoExtratoImportado(?string $de, ?string $ate): void
    {
        $de = is_string($de) ? trim($de) : '';
        $ate = is_string($ate) ? trim($ate) : '';

        if ($de !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $de)) {
            $this->periodoDe = $de;
        }
        if ($ate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ate)) {
            $this->periodoAte = $ate;
        }
    }

    public function importarOfx(): void
    {
        if (! ErpAccess::currentCan('conciliacao_bancaria.import')) {
            Notification::make()->title('Sem permissão para importar extrato.')->warning()->send();
            $this->ofxUpload = null;

            return;
        }

        if (! $this->ofxUpload instanceof TemporaryUploadedFile) {
            throw ValidationException::withMessages([
                'ofxUpload' => 'Selecione um arquivo OFX.',
            ]);
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $contaId = (int) $this->caixaContaId;

        if ($this->contaBancariaOptions() === []) {
            Notification::make()
                ->title('Nenhuma conta BANCO ativa')
                ->body('Cadastre ou ative a conta no fluxo de Contas (Caixa) antes de importar.')
                ->warning()
                ->send();
            $this->ofxUpload = null;

            return;
        }

        if ($contaId <= 0) {
            Notification::make()
                ->title('Selecione a conta bancária')
                ->body('Escolha a conta BANCO no filtro antes de importar o OFX.')
                ->warning()
                ->send();
            $this->ofxUpload = null;

            return;
        }

        try {
            $path = $this->ofxUpload->getRealPath();
            $conteudo = is_string($path) && is_file($path)
                ? (string) file_get_contents($path)
                : (string) $this->ofxUpload->get();

            /** @var OfxImportResult $result */
            $result = app(OfxImportService::class)->import([
                'empresa_id' => $empresaId,
                'caixa_conta_id' => $contaId,
                'arquivo_nome' => (string) $this->ofxUpload->getClientOriginalName(),
                'conteudo' => $conteudo,
            ]);

            $this->ofxImportSummary = [
                'lidos' => $result->lidos,
                'importados' => $result->importados,
                'ja_existentes' => $result->jaExistentes,
                'erros' => $result->erros,
                'ambiguos' => $result->ambiguos,
                'total_entradas' => $result->totalEntradas,
                'total_saidas' => $result->totalSaidas,
                'alerta_conta' => $result->alertaContaIncompativel,
                'reimportacao' => $result->reimportacaoIdempotente,
                'status' => $result->status,
                'avisos' => array_values(array_slice($result->warnings, 0, 5)),
                'periodo_de' => $result->periodoDe,
                'periodo_ate' => $result->periodoAte,
            ];

            // Alinha o filtro ao período do extrato (causa típica de grade vazia pós-importação).
            $this->aplicarPeriodoDoExtratoImportado($result->periodoDe, $result->periodoAte);

            $notification = Notification::make()
                ->title($result->tituloNotificacao())
                ->body($result->corpoNotificacao());

            if ($result->status === 'erro' || $result->alertaContaIncompativel) {
                $notification->warning();
            } else {
                $notification->success();
            }
            $notification->send();

            $this->clearListSelection();
            $this->resetTable();
        } catch (InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Falha ao importar OFX.')->body($e->getMessage())->danger()->send();
        } finally {
            $this->ofxUpload = null;
        }
    }
}
