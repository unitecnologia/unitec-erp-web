<?php

namespace App\Filament\Resources\TerminalResource\Pages\Concerns;

use App\Models\Terminal;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\License\DeviceLicenseService;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\Erp\Terminais\TerminalFormOptions;
use Filament\Notifications\Notification;

trait ManagesTerminalMasterDetail
{
    /** @var array<string, mixed> */
    public array $data = [];

    public string $activeTerminalTab = 'configuracoes';

    public bool $isNewTerminal = false;

    public ?int $editingTerminalId = null;

    public ?int $terminalInfoId = null;

    /** @var list<string> */
    public array $portasImpressoraLista = [];

    public function selectTerminalTab(string $tab): void
    {
        if (in_array($tab, $this->terminalTabKeys(), true)) {
            $this->activeTerminalTab = $tab;
        }

        if ($tab === 'configuracoes') {
            // Só portas estáticas no swap de aba — Get-Printer no mount travava o sistema.
            $this->ensurePortasBasicas();
        }
    }

    public function toggleTerminalAtivo(int $terminalId): void
    {
        $terminal = Terminal::query()->find($terminalId);

        if (! $terminal) {
            Notification::make()
                ->title('Terminal não encontrado.')
                ->warning()
                ->send();

            return;
        }

        $novo = ! (bool) ($terminal->ativo ?? true);
        $terminal->forceFill(['ativo' => $novo])->saveQuietly();

        if ((int) ($this->editingTerminalId ?? 0) === $terminalId) {
            $this->data['ativo'] = $novo;
        }

        Notification::make()
            ->title($novo ? 'Terminal liberado.' : 'Terminal bloqueado.')
            ->body($terminal->nome)
            ->success()
            ->send();
    }

    public function toggleTerminalInfo(int $terminalId): void
    {
        $this->terminalInfoId = (int) ($this->terminalInfoId ?? 0) === $terminalId
            ? null
            : $terminalId;
    }

    public function closeTerminalInfo(): void
    {
        $this->terminalInfoId = null;
    }

    public function notifyBalancaTestResult(bool $ok, string $message, ?string $peso = null): void
    {
        $body = trim($message);

        if ($ok && filled($peso)) {
            $pesoFmt = number_format((float) $peso, 3, ',', '.');
            $body = trim("Peso lido: {$pesoFmt} kg".($body !== '' ? " — {$body}" : ''));
        }

        $notification = Notification::make()
            ->title($ok ? 'Balança comunicando.' : 'Teste da balança falhou.')
            ->body($body !== '' ? $body : ($ok ? 'Leitura concluída.' : 'Falha na comunicação.'))
            ->duration(8000);

        if ($ok) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    /**
     * @return list<string>
     */
    public function terminalTabKeys(): array
    {
        return ['configuracoes', 'balanca', 'tef', 'aparelhos'];
    }

    /**
     * Título do bloco de vínculo: ERP quando o caixa selecionado é o da retaguarda.
     */
    public function terminalConfigGrupoTitulo(): string
    {
        $origens = $this->terminalConfigOrigens();
        $isErp = in_array('erp_web', $origens, true) || in_array('gestor_web', $origens, true);
        $isPdv = in_array('pdv_offline', $origens, true);

        if ($isErp && $isPdv) {
            return 'ERP / PDV Offline';
        }

        if ($isErp) {
            return 'ERP';
        }

        if ($isPdv) {
            return 'PDV Offline';
        }

        $nome = mb_strtoupper(trim((string) ($this->data['nome'] ?? '')), 'UTF-8');
        if ($nome === 'ERP' || str_starts_with($nome, 'ERP')) {
            return 'ERP';
        }

        return 'PDV Offline';
    }

    public function terminalConfigEhPdvOffline(): bool
    {
        return str_contains($this->terminalConfigGrupoTitulo(), 'PDV');
    }

    /**
     * @return list<string>
     */
    protected function terminalConfigOrigens(): array
    {
        $origens = $this->data['origens_dispositivo'] ?? [];

        if (is_string($origens)) {
            $decoded = json_decode($origens, true);
            $origens = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($origens)) {
            return [];
        }

        return array_values(array_filter($origens, fn ($o): bool => is_string($o) && $o !== ''));
    }

    public function createTerminal(): void
    {
        $this->prepareNewTerminalForm();
        $this->clearListSelection();
        $this->activeTerminalTab = 'configuracoes';
    }

    public function reloadTerminal(): void
    {
        if ($this->isNewTerminal) {
            return;
        }

        $recordId = $this->highlightedRecordIdOrNotify('edit');

        if (! $recordId) {
            return;
        }

        $terminal = Terminal::query()->find($recordId);

        if ($terminal) {
            $this->loadTerminalIntoForm($terminal);

            Notification::make()
                ->title('Terminal recarregado.')
                ->success()
                ->send();
        }
    }

    public function selectTerminalAtual(): void
    {
        $terminal = TerminalResolver::make()->resolveOrCreateDefault();

        if (! $terminal) {
            Notification::make()
                ->title('Empresa não identificada.')
                ->warning()
                ->send();

            return;
        }

        $this->selectTerminalRecord($terminal->id);
    }

    public function saveTerminalForm(): void
    {
        if (blank($this->data['velocidade'] ?? null)) {
            $this->data['velocidade'] = 9600;
        }

        if (! $this->terminalConfigEhPdvOffline()) {
            if (blank($this->data['porta'] ?? null)) {
                $this->data['porta'] = 'COM2';
            }

            if (blank($this->data['modelo'] ?? null)) {
                $this->data['modelo'] = 'ELGIN';
            }
        }

        if (blank(trim((string) ($this->data['nome'] ?? '')))) {
            Notification::make()
                ->title('Informe o nome do terminal.')
                ->warning()
                ->send();

            return;
        }

        $resolver = TerminalResolver::make();

        // IP do PDV vem da carga offline (DHCP). Não sobrescrever com o IP
        // do navegador do ERP (muitas vezes 127.0.0.1).
        if ($this->isNewTerminal && blank($this->data['ip'] ?? null)) {
            $clientIp = $resolver->resolveClientIp();
            if ($clientIp !== null && ! str_starts_with($clientIp, '127.')) {
                $this->data['ip'] = $clientIp;
            }
        }

        $payload = $this->mergeTerminalFormData($this->data);

        if ($this->isNewTerminal) {
            $devices = app(DeviceLicenseService::class);

            try {
                if ($devices->isAvailable() && (bool) $payload['ativo']) {
                    $devices->assertCapacity(
                        (int) $payload['empresa_id'],
                        DeviceLicenseService::CATEGORY_COMPUTADOR,
                    );
                    $payload['categoria_licenca'] = DeviceLicenseService::CATEGORY_COMPUTADOR;
                }
            } catch (DeviceLicenseLimitExceeded $e) {
                Notification::make()
                    ->title('Limite de computadores atingido.')
                    ->body($e->getMessage())
                    ->danger()
                    ->send();

                return;
            }

            $terminal = Terminal::query()->create($payload);
            $this->isNewTerminal = false;
            $this->editingTerminalId = $terminal->id;
            $this->highlightedRecordId = $terminal->id;
        } else {
            $terminal = Terminal::query()->find($this->editingTerminalId ?? $this->highlightedRecordId);

            if (! $terminal) {
                Notification::make()
                    ->title('Terminal não encontrado.')
                    ->warning()
                    ->send();

                return;
            }

            $terminal->fill($payload);
            $terminal->save();
        }

        TerminalResolver::make()->remember($terminal);
        $this->loadTerminalIntoForm($terminal->fresh());

        Notification::make()
            ->title('Terminal gravado.')
            ->body('Reabra o PDV para aplicar as configurações deste terminal.')
            ->success()
            ->send();
    }

    public function useCurrentTerminal(): void
    {
        if ($this->isNewTerminal) {
            Notification::make()
                ->title('Grave o terminal antes de usá-lo no PDV.')
                ->warning()
                ->send();

            return;
        }

        $terminal = Terminal::query()->find($this->editingTerminalId ?? $this->highlightedRecordId);

        if (! $terminal) {
            $this->highlightedRecordIdOrNotify('use');

            return;
        }

        TerminalResolver::make()->remember($terminal);

        Notification::make()
            ->title('Terminal ativo')
            ->body($terminal->nome . ' será usado no PDV desta sessão.')
            ->success()
            ->send();
    }

    public function moduleStubTefTest(): void
    {
        Notification::make()
            ->title('Testar TEF')
            ->body('Integração TEF disponível no PDV desktop. Em implementação no web.')
            ->info()
            ->send();
    }
    public function moduleStubBrowseImpressora(): void
    {
        $this->refreshPortasComImpressorasWindows(true);
    }

    public function moduleStubListaImpressoras(): void
    {
        $this->refreshPortasComImpressorasWindows(true);
    }

    protected function refreshPortasComImpressorasWindows(bool $notify = false): void
    {
        // Enumeração sob demanda (botão), com timeout/cache — nunca no mount da tela.
        $this->portasImpressoraLista = TerminalFormOptions::portasComImpressorasWindows();
        $this->ensurePortaInLista();
        $this->syncImpressoraNomeFromPorta();

        if (! $notify) {
            return;
        }

        $rawCount = count(array_filter(
            $this->portasImpressoraLista,
            static fn (string $porta): bool => str_starts_with(strtoupper($porta), 'RAW:'),
        ));

        Notification::make()
            ->title('Impressoras do Windows')
            ->body($rawCount > 0
                ? $rawCount.' impressora(s) listada(s) como RAW:... no Caminho Padrao. Selecione e grave.'
                : 'Nenhuma impressora Windows encontrada neste PC (ou a consulta demorou demais). Confira em Configuracoes > Impressoras e tente de novo.')
            ->success()
            ->send();
    }

    public function updatedDataPorta(?string $value): void
    {
        $fromRaw = TerminalFormOptions::windowsPrinterFromPorta($this->data['porta'] ?? null);
        if ($fromRaw !== null) {
            $this->data['impressora_nome'] = $fromRaw;
            $this->data['usar_device_service'] = true;
        }
    }

    protected function syncImpressoraNomeFromPorta(): void
    {
        $fromRaw = TerminalFormOptions::windowsPrinterFromPorta($this->data['porta'] ?? null);
        if ($fromRaw !== null) {
            $this->data['impressora_nome'] = $fromRaw;
        }
    }

    protected function bootTerminalMasterDetail(): void
    {
        $terminal = TerminalResolver::make()->resolveOrCreateDefault();

        if ($terminal) {
            $this->selectTerminalRecord($terminal->id);

            return;
        }

        $this->prepareNewTerminalForm();
    }

    public function selectTerminalRecord(int $recordId): void
    {
        $this->highlightedRecordId = $recordId;
        $this->isNewTerminal = false;
        $this->terminalInfoId = null;

        $terminal = Terminal::query()->find($recordId);

        if ($terminal) {
            $this->loadTerminalIntoForm($terminal);
        }
    }

    protected function loadTerminalIntoForm(Terminal $terminal): void
    {
        $this->editingTerminalId = $terminal->id;
        $this->isNewTerminal = false;
        $extra = is_array($terminal->impressora_extra) ? $terminal->impressora_extra : [];

        $this->data = [
            ...$terminal->attributesToArray(),
            'ativo' => (bool) ($terminal->ativo ?? true),
            'usar_device_service' => true,
            'numero_logico_terminal' => $terminal->numero_logico_terminal,
            'tipo_operacao_padrao' => TerminalFormOptions::normalizeTipoOperacaoPadrao(
                (string) ($extra['tipo_operacao_padrao'] ?? 'pedido_nao_fiscal'),
            ),
            'preview_impressao' => (bool) ($extra['preview_impressao'] ?? false),
            'velocidade' => $terminal->velocidade ?: 9600,
            'nvias' => $terminal->nvias ?: 1,
            'modelo' => $terminal->modelo ?: 'ELGIN',
            'porta' => $terminal->porta ?: 'COM2',
            'tipo_impressora' => (string) ($terminal->tipo_impressora ?? '0'),
            'ip' => $terminal->ip ?: TerminalResolver::make()->resolveClientIp(),
        ];

        if ($this->terminalConfigEhPdvOffline()) {
            $this->data['porta'] = (string) ($terminal->porta ?? '');
            $this->data['modelo'] = (string) ($terminal->modelo ?: 'ELGIN');
            $this->data['nvias'] = (int) ($terminal->nvias ?: 1);
        }

        $this->ensurePortasBasicas();
    }

    protected function ensurePortasBasicas(): void
    {
        if ($this->portasImpressoraLista === []) {
            $this->portasImpressoraLista = TerminalFormOptions::portasImpressora();
        }

        $this->ensurePortaInLista();
        $this->syncImpressoraNomeFromPorta();
    }

    protected function ensurePortaInLista(): void
    {
        if ($this->portasImpressoraLista === []) {
            $this->portasImpressoraLista = TerminalFormOptions::portasImpressora();
        }

        $porta = trim((string) ($this->data['porta'] ?? ''));
        if ($porta !== '' && ! in_array($porta, $this->portasImpressoraLista, true)) {
            array_unshift($this->portasImpressoraLista, $porta);
        }
    }

    protected function prepareNewTerminalForm(): void
    {
        $this->editingTerminalId = null;
        $this->isNewTerminal = true;
        $this->data = static::defaultTerminalFormData();
        $this->ensurePortasBasicas();
    }

    protected function afterTerminalDeleted(): void
    {
        $next = Terminal::query()
            ->where('empresa_id', TerminalResolver::make()->resolveEmpresaId())
            ->where('nome', '!=', '')
            ->orderBy('id')
            ->first();

        if ($next) {
            $this->selectTerminalRecord($next->id);

            return;
        }

        $created = TerminalResolver::make()->resolveOrCreateDefault();

        if ($created) {
            $this->selectTerminalRecord($created->id);

            return;
        }

        $this->prepareNewTerminalForm();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mergeTerminalFormData(array $data): array
    {
        $merged = ErpUppercase::normalizeFormData($data);

        $merged['ativo'] = filter_var($merged['ativo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $merged['usar_device_service'] = true;
        $merged['imprime'] = filter_var($merged['imprime'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $merged['usa_gaveta'] = filter_var($merged['usa_gaveta'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $merged['eh_caixa'] = filter_var($merged['eh_caixa'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $merged['pdv'] = filter_var($merged['pdv'] ?? true, FILTER_VALIDATE_BOOLEAN);

        if (isset($merged['numero_logico_terminal']) && $merged['numero_logico_terminal'] === '') {
            $merged['numero_logico_terminal'] = null;
        }

        if (blank($merged['empresa_id'] ?? null)) {
            $merged['empresa_id'] = TerminalResolver::make()->resolveEmpresaId();
        }

        $merged['impressora_extra'] = [
            'tipo_operacao_padrao' => (string) ($merged['tipo_operacao_padrao'] ?? 'pedido_nao_fiscal'),
            'preview_impressao' => (bool) ($merged['preview_impressao'] ?? false),
        ];

        $fromRaw = TerminalFormOptions::windowsPrinterFromPorta($merged['porta'] ?? null);
        if ($fromRaw !== null) {
            $merged['impressora_nome'] = $fromRaw;
        }

        if ($this->terminalConfigEhPdvOffline()) {
            unset(
                $merged['tipo_impressora'],
                $merged['nvias'],
                $merged['modelo'],
                $merged['porta'],
                $merged['impressora_nome'],
            );
        }

        unset(
            $merged['id'],
            $merged['created_at'],
            $merged['updated_at'],
            $merged['tipo_operacao_padrao'],
            $merged['preview_impressao'],
            $merged['meia_folha'],
        );

        if (! $this->isNewTerminal) {
            unset(
                $merged['serie'],
                $merged['numeracao_inicial'],
                $merged['usar_numero_inicial'],
                $merged['device_uuid'],
                $merged['origens_dispositivo'],
                $merged['device_last_seen_at'],
                $merged['device_registered_at'],
                $merged['device_platform'],
                $merged['device_name'],
                $merged['categoria_licenca'],
            );
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function defaultTerminalFormData(): array
    {
        $resolver = TerminalResolver::make();
        $empresaId = $resolver->resolveEmpresaId();

        return [
            ...Terminal::defaultAttributes($empresaId),
            'empresa_id' => $empresaId,
            'nome' => $resolver->resolveMachineName(),
            'ip' => $resolver->resolveClientIp(),
            'velocidade' => 9600,
            'nvias' => 1,
            'serie' => '1',
            'numeracao_inicial' => 1,
            'tipo_impressora' => '0',
            'tipo_fechamento' => '0',
            'modelo' => 'ELGIN',
            'porta' => 'COM2',
            'tipo_operacao_padrao' => 'pedido_nao_fiscal',
            'preview_impressao' => true,
            'busca_balanca_barras' => true,
            'exibe_f3' => true,
            'exibe_f4' => true,
            'exibe_f5' => true,
            'exibe_f6' => true,
            'pdv' => true,
            'ativo' => true,
            'eh_caixa' => true,
            'imprime' => true,
            'usar_device_service' => true,
            'balanca_marca' => 'balToledo',
            'balanca_porta' => 'COM3',
            'balanca_velocidade' => '9600',
            'balanca_databits' => '8',
            'balanca_paridade' => 'None',
            'balanca_stopbits' => '1',
            'balanca_handshaking' => 'None',
            'ler_peso' => false,
        ];
    }

    public function getTerminalAtivoNomeProperty(): ?string
    {
        return TerminalResolver::make()->current()?->nome;
    }

    public function getTerminalFormTitleProperty(): string
    {
        if ($this->isNewTerminal) {
            return 'Novo terminal';
        }

        $nome = trim((string) ($this->data['nome'] ?? ''));

        return $nome !== '' ? $nome : 'Terminal';
    }
}
