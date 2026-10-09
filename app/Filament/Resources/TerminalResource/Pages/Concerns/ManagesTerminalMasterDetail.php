<?php

namespace App\Filament\Resources\TerminalResource\Pages\Concerns;

use App\Models\PdvCaixaSessao;
use App\Models\Terminal;
use App\Support\Erp\ErpUppercase;
use App\Support\Erp\License\DeviceLicenseLimitExceeded;
use App\Support\Erp\License\DeviceLicenseService;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\Erp\Terminais\TerminalFormOptions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesTerminalMasterDetail
{
    /** @var array<string, mixed> */
    public array $data = [];

    public string $activeTerminalTab = 'configuracoes';

    public bool $isNewTerminal = false;

    public ?int $editingTerminalId = null;

    public ?int $terminalInfoId = null;

    /** PDV offline: nome (PDVn) e nº lógico só leitura — a carga localiza o caixa por eles. */
    public bool $terminalIdentidadeFixa = false;

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
        return ['configuracoes', 'balanca', 'aparelhos'];
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
        $terminal = TerminalResolver::make()->current();

        if (! $terminal) {
            Notification::make()
                ->title('Este computador ainda não tem terminal.')
                ->body('Não havia vaga de computador na licença quando este computador acessou. Selecione um terminal na lista e use "Usar este terminal".')
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

            if (array_key_exists('porta', $payload)) {
                $payload['impressora_nome'] = TerminalFormOptions::windowsPrinterFromPorta($payload['porta'] ?? null);
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

            // PDV offline é localizado na carga por PDVn / nº lógico: renomear criaria outro terminal.
            if ($terminal->ehPdvOffline()) {
                unset($payload['nome'], $payload['numero_logico_terminal']);
            }

            if (array_key_exists('porta', $payload)) {
                $payload['impressora_nome'] = $this->impressoraNomeParaGravar($payload['porta'] ?? null, $terminal);
            }

            $terminal->fill($payload);
            $terminal->save();
        }

        // Não altera o terminal da sessão: editar outro caixa não pode trocar série NFC-e,
        // impressora nem caixa aberto deste navegador.
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

        $resolver = TerminalResolver::make();
        $empresaId = $resolver->resolveEmpresaId();

        $terminal = Terminal::query()
            ->where('empresa_id', $empresaId)
            ->find($this->editingTerminalId ?? $this->highlightedRecordId);

        if (! $terminal) {
            $this->highlightedRecordIdOrNotify('use');

            return;
        }

        $bloqueio = match (true) {
            ! (bool) ($terminal->ativo ?? true) => 'Terminal inativo. Ative-o antes de usar neste computador.',
            $terminal->ehPdvOffline() => 'Caixa de PDV offline é exclusivo do PDV instalado. Escolha um terminal do ERP.',
            ! $resolver->usavelNoNavegador($terminal) => 'Terminal de aparelho móvel não pode ser usado no navegador.',
            default => null,
        };

        $caixaAberto = $bloqueio === null
            ? PdvCaixaSessao::query()
                ->where('user_id', Auth::id())
                ->where('empresa_id', $empresaId)
                ->whereNull('fechado_em')
                ->whereNotNull('terminal_id')
                ->where('terminal_id', '!=', $terminal->id)
                ->first()
            : null;

        if ($caixaAberto !== null) {
            $nomeCaixa = Terminal::query()->whereKey($caixaAberto->terminal_id)->value('nome') ?: 'outro terminal';
            $bloqueio = 'Você tem caixa aberto em '.$nomeCaixa.'. Feche o caixa antes de trocar de terminal.';
        }

        $uuid = $resolver->deviceUuid();
        $dono = trim((string) ($terminal->device_uuid ?? ''));
        $jaEDeste = $uuid !== null && $dono !== '' && strcasecmp($dono, $uuid) === 0;

        // Caixa aberto por outro operador neste terminal: está em uso em outro computador.
        if ($bloqueio === null && ! $jaEDeste && PdvCaixaSessao::query()
            ->where('terminal_id', $terminal->id)
            ->whereNull('fechado_em')
            ->where('user_id', '!=', Auth::id())
            ->exists()) {
            $bloqueio = 'Há caixa aberto por outro operador em '.$terminal->nome.'. Feche esse caixa antes de reassociar o terminal.';
        }

        if ($bloqueio !== null) {
            Notification::make()
                ->title('Não foi possível usar este terminal.')
                ->body($bloqueio)
                ->warning()
                ->send();

            return;
        }

        if ($uuid !== null && ! $jaEDeste) {
            try {
                $terminal = app(DeviceLicenseService::class)->reassociarNavegador($terminal, $uuid);
            } catch (DeviceLicenseLimitExceeded $e) {
                Notification::make()
                    ->title('Não foi possível usar este terminal.')
                    ->body($e->getMessage())
                    ->warning()
                    ->send();

                return;
            }
        }

        $resolver->remember($terminal);

        Notification::make()
            ->title('Terminal ativo')
            ->body($terminal->nome.' passa a ser o terminal deste computador (série NFC-e, caixa e impressão).'
                .($dono !== '' && ! $jaEDeste ? ' O outro computador vinculado a ele foi desassociado.' : ''))
            ->success()
            ->send();
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
        // Caminho sem RAW (COM/USB/LPT) não tem impressora Windows: descarta o nome anterior.
        $this->data['impressora_nome'] = $fromRaw;
        $this->data['usar_device_service'] = true;
    }

    /**
     * RAW:Nome define a impressora. Caminho trocado para COM/USB/LPT descarta o nome antigo;
     * caminho inalterado preserva impressora_nome legado.
     */
    protected function impressoraNomeParaGravar(?string $porta, Terminal $terminal): ?string
    {
        $fromRaw = TerminalFormOptions::windowsPrinterFromPorta($porta);
        if ($fromRaw !== null) {
            return $fromRaw;
        }

        $portaMudou = strcasecmp(trim((string) $porta), trim((string) ($terminal->porta ?? ''))) !== 0;
        if ($portaMudou) {
            return null;
        }

        $atual = trim((string) ($terminal->impressora_nome ?? ''));

        return $atual !== '' ? $atual : null;
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
        $resolver = TerminalResolver::make();
        $terminal = $resolver->current()
            ?? Terminal::query()
                ->where('empresa_id', $resolver->resolveEmpresaId())
                ->where('nome', '!=', '')
                ->orderBy('id')
                ->first();

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
            'tipo_impressora' => TerminalFormOptions::normalizeTipoImpressora($terminal->tipo_impressora ?? '0'),
            // IP do PDV offline vem da carga; não preencher com o IP deste navegador.
            'ip' => $terminal->ip ?: ($terminal->ehPdvOffline() ? null : TerminalResolver::make()->resolveClientIp()),
        ];
        $this->data = TerminalFormOptions::canonicalizeBalanca($this->data);
        $this->terminalIdentidadeFixa = $terminal->ehPdvOffline();

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
        $this->terminalIdentidadeFixa = false;
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

        $merged = TerminalFormOptions::canonicalizeBalanca($merged);

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
            'serie' => null,
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
