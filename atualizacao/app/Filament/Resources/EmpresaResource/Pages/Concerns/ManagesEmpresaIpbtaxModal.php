<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Support\Erp\Fiscal\IpbtaxImportService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ManagesEmpresaIpbtaxModal
{
    public $ipbtaxUpload = null;

    public bool $ipbtaxModalOpen = false;

    public string $ipbtaxCaminhoArquivo = '';

    public string $ipbtaxActiveTab = 'dados';

    public string $ipbtaxStatus = '';

    public int $ipbtaxProgress = 0;

    public bool $ipbtaxBusy = false;

    /** @var array{versao: string, quantidade: int, vigencia: string, chave: string, fonte: string} */
    public array $ipbtaxMeta = [
        'versao' => '',
        'quantidade' => 0,
        'vigencia' => '',
        'chave' => '',
        'fonte' => '',
    ];

    /** @var list<array<string, mixed>> */
    public array $ipbtaxPreviewRows = [];

    /** @var list<array{linha: int, mensagem: string}> */
    public array $ipbtaxErrors = [];

    public ?string $ipbtaxStoredPath = null;

    public bool $ipbtaxReadyToConfirm = false;

    public function openIpbtaxModal(): void
    {
        $this->ipbtaxModalOpen = true;
        $this->ipbtaxActiveTab = 'dados';
        $this->ipbtaxStatus = 'Pronto';
        $this->ipbtaxProgress = 0;
        $this->ipbtaxBusy = false;
        $this->ipbtaxReadyToConfirm = false;

        if (blank($this->ipbtaxCaminhoArquivo) && filled($this->data['param_imp_ipbtax_arquivo_nome'] ?? null)) {
            $this->ipbtaxCaminhoArquivo = (string) $this->data['param_imp_ipbtax_arquivo_nome'];
        }
    }

    public function closeIpbtaxModal(): void
    {
        $this->ipbtaxModalOpen = false;
        $this->ipbtaxStatus = '';
        $this->ipbtaxProgress = 0;
        $this->ipbtaxBusy = false;
    }

    public function setIpbtaxActiveTab(string $tab): void
    {
        $this->ipbtaxActiveTab = in_array($tab, ['dados', 'erros'], true) ? $tab : 'dados';
    }

    public function updatedIpbtaxUpload(): void
    {
        $this->purgeInvalidFilamentMountedActions();
        $this->beginIpbtaxProgress('Recebendo arquivo…', 8);

        try {
            /** @var TemporaryUploadedFile|null $upload */
            $upload = $this->ipbtaxUpload;

            if (! $upload instanceof TemporaryUploadedFile) {
                throw new \RuntimeException('Selecione um arquivo .csv ou .txt.');
            }

            $extension = strtolower((string) $upload->getClientOriginalExtension());

            if (! in_array($extension, ['csv', 'txt', 'tsv'], true)) {
                throw new \RuntimeException('Use arquivo .csv ou .txt da TabelaIBPTax.');
            }

            if ($upload->getSize() > 50 * 1024 * 1024) {
                throw new \RuntimeException('Arquivo muito grande (máx. 50 MB).');
            }

            $this->setIpbtaxProgress('Salvando arquivo no servidor…', 25);

            $previous = $this->data['param_imp_ipbtax_arquivo'] ?? $this->ipbtaxStoredPath;

            if (filled($previous)) {
                Storage::disk('local')->delete($previous);
            }

            $storedPath = $upload->store('fiscal/ipbtax', 'local');

            $this->ipbtaxStoredPath = $storedPath;
            $this->ipbtaxCaminhoArquivo = $upload->getClientOriginalName();
            $this->data['param_imp_ipbtax_arquivo'] = $storedPath;
            $this->data['param_imp_ipbtax_arquivo_nome'] = $upload->getClientOriginalName();

            $this->ipbtaxPreviewRows = [];
            $this->ipbtaxErrors = [];
            $this->ipbtaxReadyToConfirm = false;
            $this->ipbtaxMeta = [
                'versao' => '',
                'quantidade' => 0,
                'vigencia' => '',
                'chave' => '',
                'fonte' => '',
            ];

            $this->finishIpbtaxProgress('Arquivo selecionado. Clique em Atualizar.', 30);

            Notification::make()
                ->title('Arquivo IPBTAX selecionado.')
                ->body('Clique em Atualizar para carregar a prévia.')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            $this->failIpbtaxProgress('Falha ao selecionar arquivo.');

            Notification::make()
                ->title('Falha ao selecionar arquivo IPBTAX.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->ipbtaxUpload = null;
        }
    }

    public function atualizarIpbtaxArquivo(): void
    {
        $path = $this->resolveIpbtaxAbsolutePath();

        if ($path === null) {
            Notification::make()->title('Selecione o arquivo IPBTAX.')->warning()->send();

            return;
        }

        $this->beginIpbtaxProgress('Lendo arquivo IPBTAX…', 20);

        try {
            $this->setIpbtaxProgress('Analisando linhas e alíquotas…', 55);
            $parsed = (new IpbtaxImportService)->parseFromPath($path);

            $this->setIpbtaxProgress('Montando prévia na tela…', 85);
            $this->ipbtaxPreviewRows = $parsed['preview'];
            $this->ipbtaxErrors = $parsed['errors'];
            $this->ipbtaxMeta = $parsed['meta'];
            $this->ipbtaxReadyToConfirm = ($parsed['meta']['quantidade'] ?? 0) > 0;
            $this->ipbtaxActiveTab = $this->ipbtaxErrors !== [] && $this->ipbtaxPreviewRows === [] ? 'erros' : 'dados';

            $this->finishIpbtaxProgress(
                $this->ipbtaxReadyToConfirm
                    ? 'Prévia carregada. Clique em Confirma para gravar.'
                    : 'Nenhum item válido encontrado.',
                $this->ipbtaxReadyToConfirm ? 70 : 40,
            );

            Notification::make()
                ->title('Arquivo IPBTAX atualizado.')
                ->body(sprintf(
                    '%d item(ns)%s.',
                    $parsed['meta']['quantidade'],
                    ($parsed['skipped'] ?? 0) > 0 ? sprintf(', %d linha(s) com erro', $parsed['skipped']) : '',
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            $this->failIpbtaxProgress('Falha ao ler o arquivo.');
            $this->ipbtaxReadyToConfirm = false;

            Notification::make()
                ->title('Falha ao atualizar IPBTAX.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmarIpbtaxImportacao(): void
    {
        if (! $this->ipbtaxReadyToConfirm) {
            Notification::make()->title('Atualize o arquivo antes de confirmar.')->warning()->send();

            return;
        }

        $path = $this->resolveIpbtaxAbsolutePath();

        if ($path === null) {
            Notification::make()->title('Arquivo IPBTAX não encontrado.')->danger()->send();

            return;
        }

        $this->beginIpbtaxProgress('Gravando IPBTAX na base…', 75);

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(300);
            }

            $this->setIpbtaxProgress('Importando registros…', 90);
            $result = (new IpbtaxImportService)->importFromPath($path);

            $this->data['param_imp_ipbtax_importado_em'] = now()->format('d/m/Y H:i');
            $this->ipbtaxMeta = $result['meta'];
            $this->ipbtaxReadyToConfirm = false;

            $this->finishIpbtaxProgress(sprintf(
                'Importação concluída — %d registro(s).',
                $result['imported'],
            ));

            Notification::make()
                ->title('IPBTAX confirmada.')
                ->body(sprintf(
                    '%d registro(s) gravado(s). Salve a empresa (F5) para gravar o vínculo do arquivo.',
                    $result['imported'],
                ))
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            $this->failIpbtaxProgress('Falha ao confirmar.');

            Notification::make()
                ->title('Falha ao confirmar IPBTAX.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function beginIpbtaxProgress(string $status, int $progress = 10): void
    {
        $this->ipbtaxBusy = true;
        $this->ipbtaxStatus = $status;
        $this->ipbtaxProgress = max(0, min(100, $progress));
    }

    protected function setIpbtaxProgress(string $status, int $progress): void
    {
        $this->ipbtaxBusy = true;
        $this->ipbtaxStatus = $status;
        $this->ipbtaxProgress = max(0, min(100, $progress));
    }

    protected function finishIpbtaxProgress(string $status, int $progress = 100): void
    {
        $this->ipbtaxBusy = false;
        $this->ipbtaxStatus = $status;
        $this->ipbtaxProgress = max(0, min(100, $progress));
    }

    protected function failIpbtaxProgress(string $status): void
    {
        $this->ipbtaxBusy = false;
        $this->ipbtaxStatus = $status;
        $this->ipbtaxProgress = 0;
    }

    protected function resolveIpbtaxAbsolutePath(): ?string
    {
        $stored = $this->ipbtaxStoredPath
            ?: ($this->data['param_imp_ipbtax_arquivo'] ?? null);

        if (! filled($stored) || ! Storage::disk('local')->exists($stored)) {
            return null;
        }

        return Storage::disk('local')->path($stored);
    }
}
