<?php

namespace App\Filament\Resources\TerminalResource\Pages\Concerns;

use App\Models\Terminal;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Pdv\TerminalResolver;
use App\Support\Erp\Tef\Scope\ScopeTefDiagnostico;
use App\Support\Erp\Tef\Scope\ScopeTefPdvGate;
use App\Support\Erp\Tef\Scope\ScopeTefTerminalConfig;
use App\Support\Erp\Tef\SuperTefApiClient;
use App\Support\Erp\Tef\SuperTefSofthouseToken;
use App\Support\Erp\Tef\SuperTefTerminalConfig;
use App\Support\Erp\Tef\TefProviderId;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesTerminalSuperTef
{
    public bool $tefAtivo = false;

    public string $tefProvedor = SuperTefTerminalConfig::PROVIDER_SUPERTEF;

    public string $tefPosId = '';

    /** Máximo de parcelas SuperTEF Crédito neste terminal (1–18). */
    public string $tefCreditoMaxParcelas = '12';

    /** Digitar só para substituir; nunca devolve o valor gravado. */
    public string $tefClienteChaveInput = '';

    public bool $tefClienteChaveConfigured = false;

    public string $tefSofthouseTokenInput = '';

    public bool $tefSofthouseTokenConfigured = false;

    public string $tefTestStatus = '';

    public string $tefTestMessage = '';

    /** Scope TEF (Conexão Tech) — preparação; cobranças bloqueadas sem SDK. */
    public string $scopeAmbiente = ScopeTefTerminalConfig::AMBIENTE_HOMOLOGACAO;

    public string $scopeEmpresaCodigo = '';

    public string $scopeLojaCodigo = '';

    public string $scopeTerminalCodigo = '';

    public string $scopeServicoHost = '127.0.0.1';

    public string $scopeServicoPorta = '';

    public string $scopeCredencialInput = '';

    public bool $scopeCredencialConfigured = false;

    public function canManageTerminalTef(): bool
    {
        return ErpAccess::can(Auth::user(), 'terminais.tef');
    }

    public function clearTefClienteChave(): void
    {
        if (! $this->canManageTerminalTef()) {
            return;
        }

        $this->tefClienteChaveInput = '';
        $this->tefClienteChaveConfigured = false;
    }

    public function clearTefSofthouseToken(): void
    {
        if (! $this->canManageTerminalTef()) {
            return;
        }

        $this->tefSofthouseTokenInput = '';
        $this->tefSofthouseTokenConfigured = false;
    }

    public function clearScopeCredencial(): void
    {
        if (! $this->canManageTerminalTef()) {
            return;
        }

        $this->scopeCredencialInput = '';
        $this->scopeCredencialConfigured = false;
    }

    public function isTefProvedorScope(): bool
    {
        return TefProviderId::normalize($this->tefProvedor) === TefProviderId::SCOPE;
    }

    public function saveTerminalTefConfig(): void
    {
        if (! $this->assertCanManageTef()) {
            return;
        }

        try {
            $this->assertScopeActivationAllowed();
            $this->persistSofthouseTokenFromForm();
            $terminal = $this->persistTerminalTefFromForm();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Não foi possível salvar o TEF.')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        if ($terminal !== null) {
            $this->loadTerminalTefIntoForm($terminal);
        } else {
            $this->hydrateSofthouseTokenFlags();
            $this->tefClienteChaveInput = '';
            $this->tefSofthouseTokenInput = '';
            $this->scopeCredencialInput = '';
        }

        $this->tefTestStatus = '';
        $this->tefTestMessage = '';

        $scope = $this->isTefProvedorScope();
        Notification::make()
            ->title($scope ? 'Configuração Scope TEF salva.' : 'Configuração SuperTEF salva.')
            ->body($scope
                ? 'Cadastro preparado. Cobranças Scope permanecem bloqueadas até o SDK oficial.'
                : 'Débito e crédito SuperTEF usam esta configuração do terminal.')
            ->success()
            ->send();
    }

    public function testTerminalTefConnection(): void
    {
        if (! $this->assertCanManageTef()) {
            return;
        }

        if ($this->isTefProvedorScope()) {
            $this->testTerminalScopeDiagnostico();

            return;
        }

        $this->tefTestStatus = 'busy';
        $this->tefTestMessage = 'Consultando API…';

        $token = trim($this->tefSofthouseTokenInput);
        if ($token === '') {
            $token = SuperTefSofthouseToken::get();
        }

        $chave = trim($this->tefClienteChaveInput);
        if ($chave === '') {
            $terminal = $this->findTerminalForTef($this->editingTerminalId);
            if ($terminal) {
                $chave = (string) (SuperTefTerminalConfig::read($terminal, withSecret: true)['cliente_chave'] ?? '');
            }
        }

        $posId = (int) trim($this->tefPosId);
        $client = new SuperTefApiClient($token);
        $list = $client->listPos($chave);

        if (! $list['ok']) {
            $this->tefTestStatus = 'error';
            $this->tefTestMessage = $list['message'];
            Notification::make()
                ->title('Teste SuperTEF falhou.')
                ->body($list['message'])
                ->danger()
                ->send();

            return;
        }

        $check = $client->validatePos($list['items'], $posId);

        if (! $check['ok']) {
            $this->tefTestStatus = 'error';
            $this->tefTestMessage = $check['message'];
            Notification::make()
                ->title('POS não validada.')
                ->body($check['message'])
                ->warning()
                ->send();

            return;
        }

        $pos = $check['pos'] ?? [];
        $detalhe = trim(implode(' · ', array_filter([
            'POS '.$posId,
            (string) ($pos['nome'] ?? ''),
            (string) ($pos['banco'] ?? ''),
            (string) ($pos['marca'] ?? ''),
            (string) ($pos['modelo'] ?? ''),
        ])));

        $this->tefTestStatus = 'ok';
        $this->tefTestMessage = $detalhe !== '' ? $detalhe : $check['message'];

        Notification::make()
            ->title('SuperTEF conectado.')
            ->body($this->tefTestMessage.' Sem cobrança.')
            ->success()
            ->send();
    }

    protected function resetTerminalTefForm(): void
    {
        $this->tefAtivo = false;
        $this->tefProvedor = SuperTefTerminalConfig::PROVIDER_SUPERTEF;
        $this->tefPosId = '';
        $this->tefCreditoMaxParcelas = '12';
        $this->tefClienteChaveInput = '';
        $this->tefClienteChaveConfigured = false;
        $this->tefSofthouseTokenInput = '';
        $this->hydrateSofthouseTokenFlags();
        $this->resetScopeTefFormFields();
        $this->tefTestStatus = '';
        $this->tefTestMessage = '';
    }

    protected function resetScopeTefFormFields(): void
    {
        $this->scopeAmbiente = ScopeTefTerminalConfig::AMBIENTE_HOMOLOGACAO;
        $this->scopeEmpresaCodigo = '';
        $this->scopeLojaCodigo = '';
        $this->scopeTerminalCodigo = '';
        $this->scopeServicoHost = '127.0.0.1';
        $this->scopeServicoPorta = '';
        $this->scopeCredencialInput = '';
        $this->scopeCredencialConfigured = false;
    }

    protected function loadTerminalTefIntoForm(Terminal $terminal): void
    {
        $cfg = SuperTefTerminalConfig::read($terminal, withSecret: false);
        $scope = ScopeTefTerminalConfig::read($terminal, withSecret: false);

        $this->tefProvedor = (string) $cfg['provedor'];
        $this->tefPosId = $cfg['pos_id'] !== null ? (string) $cfg['pos_id'] : '';
        $this->tefCreditoMaxParcelas = (string) (int) ($cfg['credito_max_parcelas'] ?? 12);
        $this->tefClienteChaveInput = '';
        $this->tefClienteChaveConfigured = (bool) $cfg['cliente_chave_configured'];
        $this->tefSofthouseTokenInput = '';
        $this->hydrateSofthouseTokenFlags();

        $this->scopeAmbiente = (string) $scope['ambiente'];
        $this->scopeEmpresaCodigo = (string) $scope['empresa_codigo'];
        $this->scopeLojaCodigo = (string) $scope['loja_codigo'];
        $this->scopeTerminalCodigo = (string) $scope['terminal_codigo'];
        $this->scopeServicoHost = (string) $scope['servico_host'];
        $this->scopeServicoPorta = $scope['servico_porta'] !== null ? (string) $scope['servico_porta'] : '';
        $this->scopeCredencialInput = '';
        $this->scopeCredencialConfigured = (bool) $scope['credencial_configured'];

        $this->tefAtivo = $this->isTefProvedorScope()
            ? (bool) $scope['ativo']
            : (bool) $cfg['ativo'];

        $this->tefTestStatus = '';
        $this->tefTestMessage = '';
    }

    protected function hydrateSofthouseTokenFlags(): void
    {
        $this->tefSofthouseTokenConfigured = SuperTefSofthouseToken::isConfigured();
    }

    protected function persistSofthouseTokenFromForm(): void
    {
        $input = trim($this->tefSofthouseTokenInput);

        if ($input !== '') {
            SuperTefSofthouseToken::put($input, keepExisting: false);

            return;
        }

        if (! $this->tefSofthouseTokenConfigured) {
            SuperTefSofthouseToken::put(null, keepExisting: false);
        }
    }

    protected function persistTerminalTefFromForm(): ?Terminal
    {
        if ($this->isNewTerminal || ! $this->editingTerminalId) {
            throw new \RuntimeException('Grave o terminal (F10) antes de salvar o SuperTEF deste caixa.');
        }

        $terminal = $this->findTerminalForTef($this->editingTerminalId);

        if (! $terminal) {
            throw new \RuntimeException('Terminal não encontrado nesta empresa.');
        }

            $this->writeTefExtraToTerminal($terminal, registrarLog: true);

        return $terminal->fresh();
    }

    protected function applyTerminalTefOnSave(Terminal $terminal): void
    {
        if (! $this->canManageTerminalTef()) {
            return;
        }

        if (! $this->terminalPertenceEmpresaAtual($terminal)) {
            Notification::make()
                ->title('Terminal de outra empresa.')
                ->body('A configuração TEF não foi alterada.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->assertScopeActivationAllowed();
            $this->persistSofthouseTokenFromForm();
            // Persistência junto ao save do terminal — sem log TEF duplicado.
            $this->writeTefExtraToTerminal($terminal, registrarLog: false);
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Configuração TEF não aplicada.')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function writeTefExtraToTerminal(Terminal $terminal, bool $registrarLog = false): void
    {
        $inputChave = trim($this->tefClienteChaveInput);
        $keepChave = $this->tefClienteChaveConfigured && $inputChave === '';
        $provedor = TefProviderId::normalize($this->tefProvedor);
        $ativoSuper = $provedor === TefProviderId::SUPERTEF && $this->tefAtivo;
        $ativoScope = $provedor === TefProviderId::SCOPE && $this->tefAtivo;

        $extra = SuperTefTerminalConfig::writePayload([
            'ativo' => $ativoSuper,
            'provedor' => $provedor,
            'pos_id' => $this->tefPosId,
            'credito_max_parcelas' => $this->tefCreditoMaxParcelas,
            'credito_installment_type' => SuperTefTerminalConfig::INSTALLMENT_TYPE_LOJA,
            'cliente_chave' => $inputChave,
            'keep_cliente_chave' => $keepChave,
        ], $keepChave ? $terminal : null);

        $inputCred = trim($this->scopeCredencialInput);
        $keepCred = $this->scopeCredencialConfigured && $inputCred === '';

        $extra = ScopeTefTerminalConfig::mergeIntoExtra($extra, [
            'ativo' => $ativoScope,
            'ambiente' => $this->scopeAmbiente,
            'empresa_codigo' => $this->scopeEmpresaCodigo,
            'loja_codigo' => $this->scopeLojaCodigo,
            'terminal_codigo' => $this->scopeTerminalCodigo,
            'servico_host' => $this->scopeServicoHost,
            'servico_porta' => $this->scopeServicoPorta !== '' ? $this->scopeServicoPorta : null,
            'credencial' => $inputCred,
            'keep_credencial' => $keepCred,
        ], $keepCred ? $terminal : null);

        $terminal->tef_extra = $extra;

        // SuperTEF/Scope no PDV usam tef_extra (não a coluna usa_tef / Ctrl+T legado).
        // Mantém usa_tef=false: a carga offline exporta essa flag e o menu Ctrl+T depende dela no cliente.
        $terminal->usa_tef = false;
        $terminal->save();

        if (! $registrarLog) {
            return;
        }

        app(ErpOperacaoLogService::class)->registrar(
            operacao: 'ALTERAR_TERMINAL_TEF',
            resumo: 'TEF do terminal #'.$terminal->id.' atualizado ('.$provedor.').',
            origem: 'terminais_tef',
            documentoTipo: 'terminal',
            documentoId: (int) $terminal->id,
            documentoNumero: (string) ($terminal->nome ?: $terminal->id),
            detalhes: [
                'provedor' => $provedor,
                'ativo_supertef' => $ativoSuper,
                'ativo_scope' => $ativoScope,
                'pos_id' => $this->tefPosId !== '' ? $this->tefPosId : null,
                'credito_max_parcelas' => $this->tefCreditoMaxParcelas,
                'cliente_chave' => $inputChave !== '' || $keepChave ? '(configurado)' : '(vazio)',
                'softhouse_token' => SuperTefSofthouseToken::isConfigured() ? '(configurado)' : '(vazio)',
                'scope_credencial' => $inputCred !== '' || $keepCred ? '(configurado)' : '(vazio)',
                'scope_ambiente' => $this->scopeAmbiente,
            ],
            empresaId: $terminal->empresa_id ? (int) $terminal->empresa_id : ErpContext::currentEmpresaId(),
        );
    }

    protected function assertScopeActivationAllowed(): void
    {
        if (! $this->isTefProvedorScope()) {
            return;
        }

        if (! $this->tefAtivo) {
            return;
        }

        if (ScopeTefPdvGate::sdkAvailable() && ScopeTefPdvGate::featureEnabled()) {
            return;
        }

        throw new \RuntimeException(
            ScopeTefPdvGate::activationBlockedReason()
            .' Salve com "Configuração ativa" desmarcada para apenas preparar o cadastro.'
        );
    }

    protected function testTerminalScopeDiagnostico(): void
    {
        $terminal = $this->findTerminalForTef($this->editingTerminalId);
        if (! $terminal) {
            $this->tefTestStatus = 'error';
            $this->tefTestMessage = 'Grave o terminal antes do diagnóstico Scope.';
            Notification::make()
                ->title('Diagnóstico Scope indisponível.')
                ->body($this->tefTestMessage)
                ->warning()
                ->send();

            return;
        }

        // Aplica formulário em memória (sem gravar) para o diagnóstico refletir a tela.
        $draft = clone $terminal;
        $inputChave = trim($this->tefClienteChaveInput);
        $keepChave = $this->tefClienteChaveConfigured && $inputChave === '';
        $extra = SuperTefTerminalConfig::writePayload([
            'ativo' => false,
            'provedor' => TefProviderId::SCOPE,
            'pos_id' => $this->tefPosId,
            'credito_max_parcelas' => $this->tefCreditoMaxParcelas,
            'cliente_chave' => $inputChave,
            'keep_cliente_chave' => $keepChave,
        ], $keepChave ? $terminal : null);
        $inputCred = trim($this->scopeCredencialInput);
        $keepCred = $this->scopeCredencialConfigured && $inputCred === '';
        $draft->tef_extra = ScopeTefTerminalConfig::mergeIntoExtra($extra, [
            'ativo' => $this->tefAtivo,
            'ambiente' => $this->scopeAmbiente,
            'empresa_codigo' => $this->scopeEmpresaCodigo,
            'loja_codigo' => $this->scopeLojaCodigo,
            'terminal_codigo' => $this->scopeTerminalCodigo,
            'servico_host' => $this->scopeServicoHost,
            'servico_porta' => $this->scopeServicoPorta !== '' ? $this->scopeServicoPorta : null,
            'credencial' => $inputCred,
            'keep_credencial' => $keepCred,
        ], $keepCred ? $terminal : null);

        $result = ScopeTefDiagnostico::run($draft);
        $this->tefTestStatus = $result->ok ? 'ok' : 'error';
        $this->tefTestMessage = $result->message;

        Notification::make()
            ->title($result->ok ? 'Diagnóstico Scope' : 'Scope ainda não operacional')
            ->body($result->message)
            ->{$result->ok ? 'success' : 'warning'}()
            ->send();
    }

    protected function findTerminalForTef(?int $terminalId): ?Terminal
    {
        $terminalId = (int) ($terminalId ?? 0);

        if ($terminalId <= 0) {
            return null;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId()
            ?: TerminalResolver::make()->resolveEmpresaId());

        $query = Terminal::query()->whereKey($terminalId);

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->first();
    }

    protected function terminalPertenceEmpresaAtual(Terminal $terminal): bool
    {
        $empresaId = (int) (ErpContext::currentEmpresaId()
            ?: TerminalResolver::make()->resolveEmpresaId());

        if ($empresaId <= 0) {
            return true;
        }

        return (int) $terminal->empresa_id === $empresaId;
    }

    protected function assertCanManageTef(): bool
    {
        if ($this->canManageTerminalTef()) {
            return true;
        }

        Notification::make()
            ->title('Sem permissão para configurar TEF.')
            ->warning()
            ->send();

        return false;
    }
}
