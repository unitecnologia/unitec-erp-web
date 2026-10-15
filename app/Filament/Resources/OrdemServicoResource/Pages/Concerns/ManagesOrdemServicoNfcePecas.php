<?php

namespace App\Filament\Resources\OrdemServicoResource\Pages\Concerns;

use App\Models\OrdemServico;
use App\Models\PdvVendaNfce;
use App\Models\Person;
use App\Support\Erp\DocumentoBrasileiroValidator;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\Nfce\NfceOrdemServicoService;
use App\Support\Erp\Nfce\NfceRegularizacaoService;
use App\Support\Erp\Pdv\PdvConfig;
use App\Support\Erp\Pdv\PdvNfceCupomPrinter;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

trait ManagesOrdemServicoNfcePecas
{
    public bool $osNfceEmitirModalOpen = false;

    public string $osNfceEmitirCpf = '';

    public string $osNfceEmitirNome = '';

    public string $osNfceEmitirObservacoes = '';

    #[\Livewire\Attributes\Locked]
    public ?int $osNfceEmitirOrdemId = null;

    public ?string $osNfceFiscalSucessoDetalhe = null;

    public ?int $osNfceFiscalSucessoId = null;

    public ?int $osNfceFiscalSucessoPdvVendaId = null;

    public ?string $osNfceFiscalErroTitulo = null;

    public ?string $osNfceFiscalErroCodigo = null;

    public ?string $osNfceFiscalErroMensagem = null;

    /**
     * F11: abre o modal de consumidor/observações antes de transmitir.
     */
    public function emitirNfcePecasDaOs(): void
    {
        if (! $this->highlightedRecordId) {
            Notification::make()->title('Selecione uma ordem de serviço.')->warning()->send();

            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfce.access')) {
            return;
        }

        $ordem = $this->ordemSelecionadaParaFiscal();

        if ($ordem === null) {
            return;
        }

        $service = app(NfceOrdemServicoService::class);
        $motivo = $service->motivoBloqueioEmissao($ordem);

        if ($motivo !== null && $this->nfceOsRetransmissivel($ordem) === null) {
            $this->mostrarOsNfceFiscalErro('NÃO FOI POSSÍVEL EMITIR A NFC-E', $motivo);

            return;
        }

        $this->abrirOsNfceEmitirModal($ordem);
    }

    protected function abrirOsNfceEmitirModal(OrdemServico $ordem): void
    {
        $prefill = $this->preencherConsumidorModalDaOs($ordem);

        $this->osNfceEmitirOrdemId = (int) $ordem->id;
        $this->osNfceEmitirCpf = $prefill['cpf'];
        $this->osNfceEmitirNome = $prefill['nome'];
        $this->osNfceEmitirObservacoes = '';
        $this->osNfceEmitirModalOpen = true;
    }

    /**
     * Prefill só quando o cliente da OS é PF com CPF válido.
     * Não preenche nem substitui cliente PJ; consumidor final fica em branco (não identificado).
     *
     * @return array{cpf: string, nome: string}
     */
    protected function preencherConsumidorModalDaOs(OrdemServico $ordem): array
    {
        $cliente = $ordem->cliente;

        if (! $cliente instanceof Person) {
            return ['cpf' => '', 'nome' => ''];
        }

        if (Person::isCodigoConsumidorFinal($cliente->codigo !== null ? (string) $cliente->codigo : null)) {
            return ['cpf' => '', 'nome' => ''];
        }

        $doc = DocumentoBrasileiroValidator::digits((string) $cliente->cpf_cnpj);

        if (strlen($doc) === 14 || ! DocumentoBrasileiroValidator::isValidCpf($doc)) {
            return ['cpf' => '', 'nome' => ''];
        }

        $nome = trim((string) ($cliente->nome_razao ?: $cliente->apelido_fantasia ?: $ordem->nome ?: ''));

        return [
            'cpf' => preg_replace('/^(\d{3})(\d{3})(\d{3})(\d{2})$/', '$1.$2.$3-$4', $doc) ?: $doc,
            'nome' => mb_strtoupper($nome, 'UTF-8'),
        ];
    }

    public function cancelarOsNfceEmitirModal(): void
    {
        $this->osNfceEmitirModalOpen = false;
        $this->osNfceEmitirOrdemId = null;
        $this->osNfceEmitirCpf = '';
        $this->osNfceEmitirNome = '';
        $this->osNfceEmitirObservacoes = '';
    }

    public function confirmarOsNfceEmitirModal(): void
    {
        if (! $this->osNfceEmitirModalOpen || ! $this->osNfceEmitirOrdemId) {
            return;
        }

        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'nfce.access')) {
            return;
        }

        $digits = DocumentoBrasileiroValidator::digits($this->osNfceEmitirCpf);

        if (strlen($digits) === 14) {
            $this->dispatch(
                'erp-sefaz-resultado',
                ok: false,
                etapa: 'validacao',
                mensagem: 'NFC-e não aceita CNPJ. Para pessoa jurídica emita NF-e Peças (F10).',
            );
            Notification::make()
                ->title('NFC-e não aceita CNPJ.')
                ->body('Para pessoa jurídica emita NF-e Peças (F10).')
                ->warning()
                ->send();

            return;
        }

        try {
            $consumidor = NfceRegularizacaoService::normalizarConsumidor([
                'person_id' => null,
                'nome' => $this->osNfceEmitirNome,
                'cpf' => $this->osNfceEmitirCpf,
            ]);
        } catch (DomainException $exception) {
            $this->dispatch(
                'erp-sefaz-resultado',
                ok: false,
                etapa: 'validacao',
                mensagem: mb_substr($exception->getMessage(), 0, 600, 'UTF-8'),
            );
            Notification::make()
                ->title($exception->getMessage())
                ->warning()
                ->send();

            return;
        }

        $ordemId = (int) $this->osNfceEmitirOrdemId;
        $observacoes = $this->osNfceEmitirObservacoes;
        $this->cancelarOsNfceEmitirModal();

        $ordem = OrdemServico::query()
            ->with(['cliente', 'itens.product', 'empresa', 'nfcePecasEmitida', 'nfePecasEmitida'])
            ->find($ordemId);

        if ($ordem === null) {
            Notification::make()->title('Ordem de serviço não encontrada.')->warning()->send();

            return;
        }

        $resultado = app(NfceOrdemServicoService::class)->emitir($ordem, $consumidor, $observacoes);

        if ($resultado['status'] === NfceOrdemServicoService::STATUS_AUTORIZADA) {
            $this->dispatch('erp-sefaz-resultado', ok: true);
            $this->mostrarOsNfceFiscalSucesso($resultado);
            $this->resetTable();

            return;
        }

        $this->dispatch(
            'erp-sefaz-resultado',
            ok: false,
            etapa: 'sefaz',
            mensagem: mb_substr(trim((string) $resultado['mensagem']), 0, 600, 'UTF-8'),
        );
        $this->mostrarOsNfceFiscalErro(
            'NFC-E NÃO AUTORIZADA',
            (string) $resultado['mensagem'],
            $resultado['codigo'] !== null ? (string) $resultado['codigo'] : null,
        );
        $this->resetTable();
    }

    public function osSelecionadaPodeEmitirNfcePecas(): bool
    {
        $ordem = $this->osSelecionadaNaGrade();

        if ($ordem === null || ! $this->osFaturada($ordem)) {
            return false;
        }

        if ($ordem->possuiNfePecasEmitida() || $ordem->possuiNfceAutorizada()) {
            return false;
        }

        if (! $this->osListaIndicaPecas($ordem)) {
            return false;
        }

        if ($this->osClienteTemCnpj($ordem->cliente)) {
            return false;
        }

        $nfce = $ordem->nfcePecasEmitida;

        if ($nfce instanceof PdvVendaNfce && (string) $nfce->status === PdvVendaNfce::STATUS_DENEGADA) {
            return false;
        }

        return true;
    }

    /**
     * Cliente PJ (CNPJ): NFC-e indisponível — usar F10. Não substitui por consumidor.
     */
    private function osClienteTemCnpj(?Person $cliente): bool
    {
        if ($cliente === null) {
            return false;
        }

        if (Person::isCodigoConsumidorFinal($cliente->codigo !== null ? (string) $cliente->codigo : null)) {
            return false;
        }

        return strlen(DocumentoBrasileiroValidator::digits((string) $cliente->cpf_cnpj)) === 14;
    }

    private function nfceOsRetransmissivel(OrdemServico $ordem): ?PdvVendaNfce
    {
        $nfce = $ordem->relationLoaded('nfcePecasEmitida')
            ? $ordem->nfcePecasEmitida
            : $ordem->nfcePecasEmitida()->first();

        if (! $nfce instanceof PdvVendaNfce) {
            return null;
        }

        return in_array((string) $nfce->status, NfceOrdemServicoService::STATUS_RETRANSMITIVEIS, true)
            ? $nfce
            : null;
    }

    /**
     * @param  array{mensagem: string, nfce_id: int|null, nfce_numero: int|null, pdv_venda_id: int|null}  $resultado
     */
    protected function mostrarOsNfceFiscalSucesso(array $resultado): void
    {
        $numero = $resultado['nfce_numero'] !== null ? (string) $resultado['nfce_numero'] : '—';
        $this->osNfceFiscalSucessoId = $resultado['nfce_id'];
        $this->osNfceFiscalSucessoPdvVendaId = $resultado['pdv_venda_id'];
        $this->osNfceFiscalSucessoDetalhe = 'NFC-e nº '.$numero.' autorizada com sucesso.';
        $this->osNfceFiscalErroTitulo = null;
        $this->osNfceFiscalErroCodigo = null;
        $this->osNfceFiscalErroMensagem = null;
        $this->js("document.getElementById('erp-os-nfce-fiscal-sucesso')?.style.setProperty('display','grid')");
    }

    protected function mostrarOsNfceFiscalErro(string $titulo, string $mensagem, ?string $codigo = null): void
    {
        $this->osNfceFiscalErroTitulo = $titulo;
        $this->osNfceFiscalErroCodigo = $codigo;
        $this->osNfceFiscalErroMensagem = $mensagem;
        $this->js("document.getElementById('erp-os-nfce-fiscal-erro')?.style.setProperty('display','grid')");
    }

    public function closeOsNfceFiscalSucesso(): void
    {
        $this->osNfceFiscalSucessoDetalhe = null;
        $this->osNfceFiscalSucessoId = null;
        $this->osNfceFiscalSucessoPdvVendaId = null;
        $this->js("document.getElementById('erp-os-nfce-fiscal-sucesso')?.style.setProperty('display','none')");
    }

    public function closeOsNfceFiscalErro(): void
    {
        $this->osNfceFiscalErroTitulo = null;
        $this->osNfceFiscalErroCodigo = null;
        $this->osNfceFiscalErroMensagem = null;
        $this->js("document.getElementById('erp-os-nfce-fiscal-erro')?.style.setProperty('display','none')");
    }

    public function imprimirOsNfceAutorizada(): void
    {
        $pdvVendaId = (int) ($this->osNfceFiscalSucessoPdvVendaId ?? 0);

        if ($pdvVendaId <= 0 && $this->osNfceFiscalSucessoId) {
            $pdvVendaId = (int) (PdvVendaNfce::query()->whereKey($this->osNfceFiscalSucessoId)->value('pdv_venda_id') ?? 0);
        }

        if ($pdvVendaId <= 0) {
            Notification::make()->title('NFC-e não encontrada para impressão.')->warning()->send();

            return;
        }

        // Mesmos parâmetros do terminal (A4/ESC-POS, impressora, vias) usados no PDV.
        $copias = PdvConfig::make()->viasImpressao();
        $js = PdvNfceCupomPrinter::livewireOpenJs($pdvVendaId, $copias);

        if ($js !== '') {
            $this->js($js);
        }
    }

    public function abrirEnvioOsNfceAutorizada(): void
    {
        $nfceId = (int) ($this->osNfceFiscalSucessoId ?? 0);
        $this->closeOsNfceFiscalSucesso();

        if ($nfceId <= 0) {
            return;
        }

        // Mesmo modal F8 da tela NFC-e (e-mail + WhatsApp + DANFE/XML).
        $this->openNfceEnviarModalPorId($nfceId);
    }
}
