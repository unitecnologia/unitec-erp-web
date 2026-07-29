<?php

namespace App\Filament\Concerns;

use App\Models\FiscalClassificacaoTributaria;
use App\Support\Erp\Fiscal\ClassificacaoTributariaIvaImportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ManagesCclassTribLookup
{
    public bool $cclassTribModalOpen = false;

    public string $cclassTribFiltroCodigo = '';

    public string $cclassTribFiltroCst = '';

    public string $cclassTribFiltroIndicador = 'todos';

    /** @var list<array{codigo: string, cst: string, cst_descricao: string, descricao: string}> */
    public array $cclassTribRows = [];

    public ?string $cclassTribSelectedCodigo = null;

    public bool $cclassTribImporting = false;

    public int $cclassTribProgress = 0;

    public int $cclassTribImportStep = 0;

    public string $cclassTribStatus = '';

    public $cclassTribUpload = null;

    /** @var list<string> */
    private const CCLASS_TRIB_IMPORT_STEPS = [
        'Recebendo arquivo',
        'Validando layout do CSV',
        'Importando classificações',
        'Atualizando tabela na tela',
        'Finalizando importação',
    ];

    public function openCclassTribModal(): void
    {
        $this->cclassTribModalOpen = true;
        $this->cclassTribSelectedCodigo = null;
        $this->resetCclassTribImportProgress();
        $this->filtrarCclassTrib();

        if ($this->cclassTribRows === [] && FiscalClassificacaoTributaria::query()->count() === 0) {
            $data = is_array($this->data ?? null) ? $this->data : [];
            $canImport = array_key_exists('param_imp_cclass_trib', $data);

            Notification::make()
                ->title('Tabela CClassTrib vazia.')
                ->body($canImport
                    ? 'Atualize a tabela com o CSV oficial (botão Atualizar tabela).'
                    : 'A tabela padrão do sistema não foi carregada. Rode o seed fiscal ou atualize em Empresa → Imposto Padrão.')
                ->warning()
                ->send();
        }
    }

    public function updatedCclassTribFiltroIndicador(): void
    {
        if ($this->cclassTribModalOpen) {
            $this->filtrarCclassTrib();
        }
    }

    public function closeCclassTribModal(): void
    {
        $this->cclassTribModalOpen = false;
        $this->cclassTribSelectedCodigo = null;
        $this->cclassTribImporting = false;
    }

    public function updatedCclassTribUpload(): void
    {
        if (method_exists($this, 'purgeInvalidFilamentMountedActions')) {
            $this->purgeInvalidFilamentMountedActions();
        }

        $this->beginCclassTribImportProgress(0, 8);

        try {
            /** @var TemporaryUploadedFile|null $upload */
            $upload = $this->cclassTribUpload;

            if (! $upload instanceof TemporaryUploadedFile) {
                throw ValidationException::withMessages([
                    'cclassTribUpload' => 'Selecione um arquivo .csv ou .txt.',
                ]);
            }

            $extension = strtolower((string) $upload->getClientOriginalExtension());

            if (! in_array($extension, ['csv', 'txt', 'tsv'], true)) {
                throw ValidationException::withMessages([
                    'cclassTribUpload' => 'Use arquivo .csv ou .txt (no Excel: Arquivo → Salvar como → CSV).',
                ]);
            }

            if ($upload->getSize() > 50 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'cclassTribUpload' => 'Arquivo muito grande (máx. 50 MB).',
                ]);
            }

            $this->setCclassTribImportProgress(1, 22);

            $data = is_array($this->data ?? null) ? $this->data : [];

            if (array_key_exists('param_imp_cclass_trib_arquivo', $data) && filled($data['param_imp_cclass_trib_arquivo'] ?? null)) {
                Storage::disk('local')->delete($data['param_imp_cclass_trib_arquivo']);
            }

            $storedPath = $upload->store('fiscal/cclass-trib', 'local');
            $absolutePath = Storage::disk('local')->path($storedPath);

            $this->assertCclassTribTextTabela($absolutePath);

            $this->setCclassTribImportProgress(2, 48);

            $result = (new ClassificacaoTributariaIvaImportService)->importFromPath($absolutePath);

            if (array_key_exists('param_imp_cclass_trib_arquivo', $data)) {
                $this->data['param_imp_cclass_trib_arquivo'] = $storedPath;
                $this->data['param_imp_cclass_trib_arquivo_nome'] = $upload->getClientOriginalName();
                $this->data['param_imp_cclass_trib_importado_em'] = now()->format('d/m/Y H:i');
            }

            $this->setCclassTribImportProgress(3, 78);
            $this->filtrarCclassTrib();

            $imported = (int) ($result['imported'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);

            if ($imported <= 0) {
                $this->failCclassTribImportProgress('Nenhum registro válido encontrado no arquivo.');

                Notification::make()
                    ->title('Nenhum registro importado.')
                    ->body('Confira se o arquivo está no layout CST;Descrição CST;cClassTrib;Nome (CSV).')
                    ->warning()
                    ->send();

                return;
            }

            $this->finishCclassTribImportProgress(sprintf('%d registro(s) importado(s).', $imported));

            $body = sprintf(
                '%d registro(s) na tela%s.',
                $imported,
                $skipped > 0 ? sprintf(', %d linha(s) ignorada(s)', $skipped) : '',
            );

            if (array_key_exists('param_imp_cclass_trib_arquivo', $data)) {
                $body .= ' Salve a empresa (F5) para gravar o vínculo do arquivo.';
            }

            Notification::make()
                ->title('Classificação Tributária IVA importada.')
                ->body($body)
                ->success()
                ->send();
        } catch (ValidationException $e) {
            $this->failCclassTribImportProgress('Falha na validação do arquivo.');

            Notification::make()
                ->title('Arquivo inválido.')
                ->body(collect($e->errors())->flatten()->first() ?: 'Selecione um CSV válido.')
                ->warning()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            $this->failCclassTribImportProgress('Falha ao importar.');

            Notification::make()
                ->title('Falha ao importar Classificação Tributária IVA.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->cclassTribUpload = null;
        }
    }

    protected function assertCclassTribTextTabela(string $absolutePath): void
    {
        $handle = fopen($absolutePath, 'rb');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o arquivo selecionado.');
        }

        try {
            $sample = fread($handle, 16) ?: '';
        } finally {
            fclose($handle);
        }

        if (str_starts_with($sample, 'PK') || str_starts_with($sample, "\xD0\xCF\x11\xE0")) {
            throw new \RuntimeException(
                'Arquivo Excel (.xlsx/.xls) não é suportado. Abra no Excel e salve como CSV (separado por ponto e vírgula ou vírgula).'
            );
        }
    }

    protected function beginCclassTribImportProgress(int $step = 0, int $progress = 10): void
    {
        $this->cclassTribImporting = true;
        $this->cclassTribImportStep = max(0, min(count(self::CCLASS_TRIB_IMPORT_STEPS) - 1, $step));
        $this->cclassTribProgress = max(0, min(100, $progress));
        $this->cclassTribStatus = $this->cclassTribStepLabel($this->cclassTribImportStep).'…';
    }

    protected function setCclassTribImportProgress(int $step, int $progress): void
    {
        $this->cclassTribImporting = true;
        $this->cclassTribImportStep = max(0, min(count(self::CCLASS_TRIB_IMPORT_STEPS) - 1, $step));
        $this->cclassTribProgress = max(0, min(100, $progress));
        $this->cclassTribStatus = $this->cclassTribStepLabel($this->cclassTribImportStep).'…';
    }

    protected function finishCclassTribImportProgress(string $message, int $progress = 100): void
    {
        $this->cclassTribImporting = false;
        $this->cclassTribImportStep = count(self::CCLASS_TRIB_IMPORT_STEPS) - 1;
        $this->cclassTribProgress = max(0, min(100, $progress));
        $this->cclassTribStatus = $message;
    }

    protected function failCclassTribImportProgress(string $message): void
    {
        $this->cclassTribImporting = false;
        $this->cclassTribProgress = 0;
        $this->cclassTribStatus = $message;
    }

    protected function resetCclassTribImportProgress(): void
    {
        $this->cclassTribImporting = false;
        $this->cclassTribImportStep = 0;
        $this->cclassTribProgress = 0;
        $this->cclassTribStatus = '';
    }

    protected function cclassTribStepLabel(int $step): string
    {
        return self::CCLASS_TRIB_IMPORT_STEPS[$step] ?? self::CCLASS_TRIB_IMPORT_STEPS[0];
    }

    public function filtrarCclassTrib(): void
    {
        $codigo = trim($this->cclassTribFiltroCodigo);
        $cst = trim($this->cclassTribFiltroCst);
        $indicador = $this->cclassTribFiltroIndicador ?: 'todos';

        $query = FiscalClassificacaoTributaria::query()
            ->orderBy('cst_ibs_cbs')
            ->orderBy('codigo');

        if ($codigo !== '') {
            $query->where(function ($inner) use ($codigo): void {
                $inner->where('codigo', 'like', '%'.$codigo.'%')
                    ->orWhere('descricao', 'like', '%'.$codigo.'%')
                    ->orWhere('cst_descricao', 'like', '%'.$codigo.'%');
            });
        }

        if ($cst !== '') {
            $query->where('cst_ibs_cbs', 'like', '%'.$cst.'%');
        }

        if ($indicador !== 'todos') {
            $column = match ($indicador) {
                'nfe' => 'ind_nfe',
                'nfce' => 'ind_nfce',
                'nfse' => 'ind_nfse',
                'cte' => 'ind_cte',
                default => null,
            };

            if ($column !== null) {
                $query->where(function ($inner) use ($column): void {
                    $inner->where($column, true)->orWhereNull($column);
                });
            }
        }

        $this->cclassTribRows = $query
            ->limit(800)
            ->get(['codigo', 'cst_ibs_cbs', 'cst_descricao', 'descricao'])
            ->map(static fn (FiscalClassificacaoTributaria $row): array => [
                'codigo' => (string) $row->codigo,
                'cst' => (string) ($row->cst_ibs_cbs ?? ''),
                'cst_descricao' => (string) ($row->cst_descricao ?? ''),
                'descricao' => (string) ($row->descricao ?? ''),
            ])
            ->all();
    }

    public function selectCclassTribRow(string $codigo): void
    {
        $this->cclassTribSelectedCodigo = $codigo;
    }

    public function applyCclassTribRow(string $codigo): void
    {
        $row = FiscalClassificacaoTributaria::query()
            ->where('codigo', $codigo)
            ->first();

        if (! $row) {
            Notification::make()
                ->title('Classificação não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $data = $this->data ?? [];

        if (array_key_exists('cclass_trib', $data)) {
            $this->data['cclass_trib'] = (string) $row->codigo;
            $this->data['cclass_trib_descricao'] = (string) ($row->descricao ?? '');

            if (filled($row->cst_ibs_cbs)) {
                $this->data['iva_cst'] = (string) $row->cst_ibs_cbs;
            }
        }

        if (array_key_exists('param_imp_cclass_trib', $data)) {
            $this->data['param_imp_cclass_trib'] = (string) $row->codigo;

            if (filled($row->cst_ibs_cbs)) {
                $this->data['param_imp_iva_cst'] = (string) $row->cst_ibs_cbs;
            }
        }

        if (isset($this->form) && method_exists($this->form, 'fill')) {
            $this->form->fill($this->data);
        }

        $this->closeCclassTribModal();

        Notification::make()
            ->title('Classificação Tributária aplicada.')
            ->body('Código '.$row->codigo.(filled($row->cst_ibs_cbs) ? ' · CST '.$row->cst_ibs_cbs : ''))
            ->success()
            ->send();
    }
}
