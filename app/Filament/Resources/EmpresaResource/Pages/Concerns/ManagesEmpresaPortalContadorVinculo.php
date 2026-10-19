<?php

namespace App\Filament\Resources\EmpresaResource\Pages\Concerns;

use App\Models\Empresa;
use App\Support\ContadorCloud\ContadorCloudAutoVinculoService;
use App\Support\ContadorCloud\ContadorCloudClient;
use App\Support\ContadorCloud\ContadorCloudConfig;
use App\Support\Erp\EmpresaParametros;
use Filament\Notifications\Notification;

trait ManagesEmpresaPortalContadorVinculo
{
    /** Contador selecionado antes do último save (idempotência / troca). */
    public ?int $portalContadorIdAntesDoSave = null;

    public string $portalContadorLastMessage = '';

    public string $portalContadorLastCode = '';

    /**
     * Dispara vínculo automático após salvar a empresa (somente quando necessário).
     */
    protected function syncPortalContadorAposSalvar(Empresa $empresa, bool $force = false): void
    {
        $antes = $this->portalContadorIdAntesDoSave;
        $this->portalContadorIdAntesDoSave = null;

        $resultado = app(ContadorCloudAutoVinculoService::class)
            ->syncAfterSave($empresa, $antes, $force);

        $empresa->refresh();
        $this->reloadPortalContadorFormData($empresa);

        $this->portalContadorLastMessage = $resultado['message'];
        $this->portalContadorLastCode = (string) ($resultado['code'] ?? '');

        if (! $resultado['attempted'] && ($resultado['code'] ?? '') === 'idempotente') {
            return;
        }

        if (! $resultado['attempted'] && ($resultado['code'] ?? '') === 'disabled') {
            return;
        }

        $notification = Notification::make()
            ->title('Portal do Contador')
            ->body($resultado['message']);

        if ($resultado['ok']) {
            $notification->success()->send();

            return;
        }

        $notification->warning()->send();
    }

    /**
     * Snapshot do contador antes de gravar o formulário.
     */
    protected function capturePortalContadorIdAntesDoSave(): void
    {
        if (! property_exists($this, 'record') || ! $this->record instanceof Empresa) {
            $this->portalContadorIdAntesDoSave = null;

            return;
        }

        $id = (int) ($this->record->param_portal_contador_contador_id ?? 0);
        $this->portalContadorIdAntesDoSave = $id > 0 ? $id : null;
    }

    public function tentarVincularPortalContadorNovamente(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            Notification::make()
                ->title('Portal do Contador')
                ->body('Salve a empresa antes de vincular ao portal.')
                ->warning()
                ->send();

            return;
        }

        $this->persistPortalContadorCamposVisiveis($empresa);
        $empresa->refresh();

        $this->portalContadorIdAntesDoSave = (int) ($empresa->param_portal_contador_contador_id ?? 0) ?: null;
        $this->syncPortalContadorAposSalvar($empresa, force: true);
    }

    public function desvincularPortalContador(): void
    {
        $empresa = $this->resolveEmpresaRecordForPortalContador();

        if (! $empresa) {
            return;
        }

        app(ContadorCloudAutoVinculoService::class)->limparCredenciaisEnvio(
            $empresa,
            preservarHabilitar: false,
            preservarContadorId: true,
        );

        $empresa->refresh();
        $this->reloadPortalContadorFormData($empresa);
        $this->portalContadorLastMessage = '';
        $this->portalContadorLastCode = '';

        Notification::make()
            ->title('Portal do Contador')
            ->body('Credenciais removidas neste ERP. O envio ficou desabilitado. Habilite e salve para vincular de novo.')
            ->success()
            ->send();
    }

    /**
     * Status real: só “conectado” com habilitar + token + empresaId.
     *
     * @return array{state: string, label: string, resumo: string}
     */
    public function portalContadorStatusView(): array
    {
        $config = ContadorCloudConfig::fromFormData($this->data ?? []);

        if ($config->isActive()) {
            $nome = trim((string) ($this->data['param_portal_contador_contador_nome_portal'] ?? ''));
            $vinculadoEm = $this->record?->param_portal_contador_vinculado_em ?? null;
            $resumo = 'Conectado e envio habilitado.';

            if ($nome !== '' && $vinculadoEm) {
                $resumo = 'Conectado com '.$nome.' em '.$vinculadoEm->format('d/m/Y H:i').'. Envio ativo.';
            } elseif ($nome !== '') {
                $resumo = 'Conectado com '.$nome.'. Envio ativo.';
            }

            return ['state' => 'connected', 'label' => 'Conectado', 'resumo' => $resumo];
        }

        if (! $config->habilitar) {
            $resumo = $config->isConfigured()
                ? 'Portal desabilitado — credenciais preservadas; nenhum documento será enviado.'
                : 'Portal desabilitado. Habilite, selecione o contador e salve para vincular automaticamente.';

            return ['state' => 'disabled', 'label' => 'Desabilitado', 'resumo' => $resumo];
        }

        if (blank($this->data['param_portal_contador_contador_id'] ?? null)) {
            return [
                'state' => 'pending',
                'label' => 'Pendente',
                'resumo' => 'Selecione o contador e salve a empresa para vincular ao portal.',
            ];
        }

        $msg = trim($this->portalContadorLastMessage);

        return [
            'state' => 'error',
            'label' => 'Não conectado',
            'resumo' => $msg !== ''
                ? $msg
                : 'Habilitado, mas sem credenciais completas. Salve a empresa ou use “Tentar vincular novamente”.',
        ];
    }

    /**
     * Compat: resumo textual usado em testes/legado.
     */
    public function portalContadorVinculoResumo(): string
    {
        return $this->portalContadorStatusView()['resumo'];
    }

    protected function persistPortalContadorCamposVisiveis(Empresa $empresa): void
    {
        $fields = [
            'param_portal_contador_habilitar' => (bool) ($this->data['param_portal_contador_habilitar'] ?? false),
            'param_portal_contador_contador_id' => filled($this->data['param_portal_contador_contador_id'] ?? null)
                ? (int) $this->data['param_portal_contador_contador_id']
                : null,
            'param_portal_contador_enviar_compras' => (bool) ($this->data['param_portal_contador_enviar_compras'] ?? true),
            'param_portal_contador_enviar_vendas' => (bool) ($this->data['param_portal_contador_enviar_vendas'] ?? false),
            'param_portal_contador_enviar_nfse' => (bool) ($this->data['param_portal_contador_enviar_nfse'] ?? true),
            'param_portal_contador_enviar_xml' => (bool) ($this->data['param_portal_contador_enviar_xml'] ?? true),
            'param_portal_contador_enviar_canceladas' => (bool) ($this->data['param_portal_contador_enviar_canceladas'] ?? true),
            'param_portal_contador_url' => EmpresaParametros::defaultPortalContadorUrl(),
            'param_portal_contador_ambiente' => 'producao',
            'param_portal_contador_timeout' => (int) config('contador-cloud.default_timeout', 30),
        ];

        if (array_key_exists('param_portal_contador_sistema_contabil', $this->data ?? [])) {
            $options = EmpresaParametros::portalContadorSistemaContabilOptions();
            $sistema = mb_strtolower(trim((string) $this->data['param_portal_contador_sistema_contabil']), 'UTF-8');
            $fields['param_portal_contador_sistema_contabil'] = array_key_exists($sistema, $options)
                ? $sistema
                : EmpresaParametros::portalContadorSistemaContabilDefault();
        }

        Empresa::query()->whereKey($empresa->getKey())->update($fields);
        $this->record?->refresh();
    }
}
