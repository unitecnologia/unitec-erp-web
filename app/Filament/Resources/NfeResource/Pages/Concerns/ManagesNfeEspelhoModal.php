<?php

namespace App\Filament\Resources\NfeResource\Pages\Concerns;

use App\Models\Empresa;
use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\Person;
use App\Support\Erp\Mail\FiscalMailService;
use App\Support\Erp\Nfe\NfeEspelhoReportService;
use App\Support\Erp\Nfe\NfeEventoLogger;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;
use Livewire\Attributes\Computed;

trait ManagesNfeEspelhoModal
{
    public bool $nfeEspelhoModalOpen = false;

    public ?int $nfeEspelhoNfeId = null;

    public bool $nfeEspelhoEmailModalOpen = false;

    public string $nfeEspelhoEmailDestinatario = 'cliente';

    public string $nfeEspelhoEmailTo = '';

    public string $nfeEspelhoEmailSubject = '';

    public string $nfeEspelhoEmailMessage = '';

    /** @var list<array{id: string, name: string, path: string, display: string}> */
    public array $nfeEspelhoEmailAttachments = [];

    public function handleNfeF7FromList(): void
    {
        if ($this->statusFilter === Nfe::STATUS_ABERTA) {
            $this->openNfeEspelhoFromList();

            return;
        }

        $this->printNfeDanfeFromList();
    }

    public function openNfeEspelhoFromList(): void
    {
        $nfeId = $this->resolveNfeEspelhoTargetId();

        if (! $nfeId) {
            return;
        }

        $nfe = Nfe::query()->find($nfeId);

        if (! $nfe || $nfe->status !== Nfe::STATUS_ABERTA) {
            Notification::make()
                ->title('Somente NF-e aberta possui espelho para visualização.')
                ->warning()
                ->send();

            return;
        }

        $this->nfeEspelhoNfeId = $nfe->id;
        $this->nfeEspelhoModalOpen = true;
        $this->dispatch('erp-nfe-focus-espelho-modal');
    }

    public function closeNfeEspelho(): void
    {
        $this->nfeEspelhoModalOpen = false;
        $this->nfeEspelhoNfeId = null;
        $this->closeNfeEspelhoEmailModal();
    }

    public function printNfeEspelhoDocument(): void
    {
        if (! $this->nfeEspelhoNfeId) {
            return;
        }

        $url = route('erp.reports.nfe-espelho', [
            'nfe' => $this->nfeEspelhoNfeId,
        ]);

        $this->js('window.ErpNfePrint?.openDanfe(' . Js::from($url) . ')');
    }

    public function openNfeEspelhoWhatsAppCliente(): void
    {
        $this->prepareNfeEspelhoWhatsApp('cliente');
    }

    public function openNfeEspelhoWhatsAppFornecedor(): void
    {
        $this->prepareNfeEspelhoWhatsApp('fornecedor');
    }

    public function openNfeEspelhoEmailModal(): void
    {
        if (! $this->nfeEspelhoNfeId) {
            return;
        }

        $context = $this->resolveNfeEspelhoContext();

        if ($context === null) {
            return;
        }

        ['nfe' => $nfe, 'empresa' => $empresa] = $context;
        $report = app(NfeEspelhoReportService::class);

        $this->cleanupNfeEspelhoEmailAttachments();

        try {
            $pdf = $report->storePdfAttachment($nfe, $empresa);
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível gerar o PDF do espelho.')
                ->warning()
                ->send();

            return;
        }

        $this->nfeEspelhoEmailDestinatario = 'cliente';
        $this->applyNfeEspelhoEmailDestinatarioContato($nfe);
        $this->nfeEspelhoEmailSubject = $report->defaultEmailSubject($nfe);
        $this->nfeEspelhoEmailAttachments = [[
            'id' => 'espelho',
            'name' => $pdf['name'],
            'path' => $pdf['path'],
            'display' => $pdf['display'],
        ]];
        $this->nfeEspelhoEmailModalOpen = true;
        $this->dispatch('erp-nfe-focus-espelho-email-modal');
    }

    public function updatedNfeEspelhoEmailDestinatario(string $value): void
    {
        if (! in_array($value, ['cliente', 'fornecedor'], true)) {
            $this->nfeEspelhoEmailDestinatario = 'cliente';

            return;
        }

        $nfe = $this->nfeEspelhoNfeId
            ? Nfe::query()->with(['cliente', 'transportadora'])->find($this->nfeEspelhoNfeId)
            : null;

        if ($nfe) {
            $this->applyNfeEspelhoEmailDestinatarioContato($nfe);
        }
    }

    public function sendNfeEspelhoEmail(): void
    {
        $this->validate([
            'nfeEspelhoEmailDestinatario' => ['required', 'in:cliente,fornecedor'],
            'nfeEspelhoEmailTo' => ['required', 'email'],
            'nfeEspelhoEmailSubject' => ['required', 'string', 'max:255'],
            'nfeEspelhoEmailMessage' => ['required', 'string', 'max:5000'],
        ], [
            'nfeEspelhoEmailTo.required' => 'Informe o e-mail do destinatário.',
            'nfeEspelhoEmailTo.email' => 'Informe um e-mail válido.',
        ]);

        if ($this->nfeEspelhoEmailAttachments === []) {
            Notification::make()
                ->title('Anexo do espelho não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $context = $this->resolveNfeEspelhoContext();

        if ($context === null) {
            return;
        }

        ['nfe' => $nfe, 'empresa' => $empresa] = $context;

        try {
            FiscalMailService::sendForEmpresa(
                empresaId: (int) $empresa->id,
                to: $this->nfeEspelhoEmailTo,
                messageBody: $this->nfeEspelhoEmailMessage,
                subjectLine: $this->nfeEspelhoEmailSubject,
                fileAttachments: collect($this->nfeEspelhoEmailAttachments)
                    ->map(fn (array $attachment): array => [
                        'path' => $attachment['path'],
                        'name' => $attachment['name'],
                    ])
                    ->all(),
                fromAddress: $empresa->email ?: null,
                fromName: $empresa->nome ?: null,
            );
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Não foi possível enviar o e-mail.')
                ->body('Verifique a configuração de e-mail em Configurações Fiscais.')
                ->danger()
                ->send();

            return;
        }

        NfeEventoLogger::registrar(
            nfeId: (int) $nfe->id,
            tipo: NfeEvento::TIPO_EMAIL,
            titulo: 'Espelho enviado por e-mail',
            descricao: $this->nfeEspelhoEmailSubject . '. ' . $this->nfeEspelhoDispatchDestinatarioLabel(),
            destinatario: $this->nfeEspelhoEmailTo,
            metadata: [
                'contexto' => 'espelho',
                'destinatario_tipo' => $this->nfeEspelhoEmailDestinatario,
            ],
        );

        Notification::make()
            ->title('E-mail enviado.')
            ->body($this->nfeEspelhoDispatchDestinatarioLabel())
            ->success()
            ->send();

        $this->closeNfeEspelhoEmailModal();
    }

    public function closeNfeEspelhoEmailModal(): void
    {
        $this->nfeEspelhoEmailModalOpen = false;
        $this->nfeEspelhoEmailTo = '';
        $this->nfeEspelhoEmailSubject = '';
        $this->nfeEspelhoEmailMessage = '';
        $this->nfeEspelhoEmailDestinatario = 'cliente';
        $this->cleanupNfeEspelhoEmailAttachments();
    }

    #[Computed]
    public function nfeEspelhoDispatchDestinatarioNome(): string
    {
        $nfe = $this->nfeEspelhoNfeId
            ? Nfe::query()->with(['cliente', 'transportadora'])->find($this->nfeEspelhoNfeId)
            : null;

        if (! $nfe) {
            return '';
        }

        $person = $this->nfeEspelhoEmailModalOpen
            ? $this->resolveNfeEspelhoDestinatarioPerson($nfe, $this->nfeEspelhoEmailDestinatario)
            : $nfe->cliente;

        return mb_strtoupper($person?->nome_razao ?? '', 'UTF-8');
    }

    #[Computed]
    public function nfeEspelhoDispatchDestinatarioAviso(): string
    {
        if (! $this->nfeEspelhoEmailModalOpen) {
            return '';
        }

        $nfe = $this->nfeEspelhoNfeId
            ? Nfe::query()->with(['cliente', 'transportadora'])->find($this->nfeEspelhoNfeId)
            : null;

        if (! $nfe) {
            return '';
        }

        if ($this->nfeEspelhoEmailDestinatario === 'fornecedor' && ! $nfe->transportadora) {
            return 'NF-e sem fornecedor/transportadora vinculado. Informe o contato manualmente.';
        }

        return '';
    }

    protected function resolveNfeEspelhoTargetId(): ?int
    {
        if ($this->nfeEspelhoNfeId) {
            return $this->nfeEspelhoNfeId;
        }

        if ($this->nfeModalOpen && $this->nfeModalRecordId) {
            return (int) $this->nfeModalRecordId;
        }

        return $this->highlightedRecordIdOrNotify('espelho');
    }

    protected function prepareNfeEspelhoWhatsApp(string $destinatario): void
    {
        if (! $this->nfeEspelhoNfeId) {
            return;
        }

        $this->prepareNfeWhatsAppModal(
            nfeId: $this->nfeEspelhoNfeId,
            useFiscalOverlayErrors: false,
            documento: 'espelho',
            destinatario: $destinatario,
        );
    }

    /**
     * @return array{nfe: Nfe, empresa: Empresa}|null
     */
    protected function resolveNfeEspelhoContext(): ?array
    {
        if (! $this->nfeEspelhoNfeId) {
            return null;
        }

        $nfe = Nfe::query()->with(['cliente', 'transportadora'])->find($this->nfeEspelhoNfeId);

        if (! $nfe || $nfe->status !== Nfe::STATUS_ABERTA) {
            Notification::make()
                ->title('NF-e aberta não encontrada para o espelho.')
                ->warning()
                ->send();

            return null;
        }

        $empresa = Empresa::query()->find($nfe->empresa_id);

        if (! $empresa) {
            Notification::make()
                ->title('Empresa não identificada.')
                ->warning()
                ->send();

            return null;
        }

        return [
            'nfe' => $nfe,
            'empresa' => $empresa,
        ];
    }

    protected function applyNfeEspelhoEmailDestinatarioContato(Nfe $nfe): void
    {
        $person = $this->resolveNfeEspelhoDestinatarioPerson($nfe, $this->nfeEspelhoEmailDestinatario);
        $report = app(NfeEspelhoReportService::class);

        $this->nfeEspelhoEmailTo = trim((string) ($person?->email ?? ''));
        $this->nfeEspelhoEmailMessage = $report->defaultEmailMessage(
            $nfe,
            mb_strtoupper($person?->nome_razao ?? '', 'UTF-8'),
        );
    }

    protected function resolveNfeEspelhoDestinatarioPerson(Nfe $nfe, string $destinatario): ?Person
    {
        return $destinatario === 'fornecedor'
            ? $nfe->transportadora
            : $nfe->cliente;
    }

    protected function nfeEspelhoDispatchDestinatarioLabel(): string
    {
        $nome = $this->nfeEspelhoDispatchDestinatarioNome;

        return match ($this->nfeEspelhoEmailDestinatario) {
            'fornecedor' => filled($nome) ? "Enviado para fornecedor: {$nome}" : 'Enviado para fornecedor.',
            default => filled($nome) ? "Enviado para cliente: {$nome}" : 'Enviado para cliente.',
        };
    }

    protected function cleanupNfeEspelhoEmailAttachments(): void
    {
        foreach ($this->nfeEspelhoEmailAttachments as $attachment) {
            if (isset($attachment['path']) && is_file($attachment['path'])) {
                @unlink($attachment['path']);
            }
        }

        $this->nfeEspelhoEmailAttachments = [];
    }
}
