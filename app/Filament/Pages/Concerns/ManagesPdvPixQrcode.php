<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PixCobranca;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvFinalizarPagamentosHelper;
use App\Support\Pix\PixCobrancaService;
use Illuminate\Support\Str;
use Throwable;

/**
 * PIX com "Gerar QR Code na tela do PDV": exibe QR na finalização e
 * só conclui a venda após confirmação do pagamento (polling).
 *
 * Abre o modal na 1ª resposta Livewire e só então gera o QR (2ª request),
 * para o overlay fiscal da NFC-e não cobrir / travar a tela.
 */
trait ManagesPdvPixQrcode
{
    /** Tempo máximo do QR na tela do PDV (Ailos cobra até 24h; no caixa 5 min basta). */
    public const PDV_PIX_TELA_SEGUNDOS = 300;

    public bool $finalizarPixQrAberta = false;

    public bool $finalizarPixQrConfirmado = false;

    public bool $finalizarPixQrGerando = false;

    public ?int $finalizarPixCobrancaId = null;

    public string $finalizarPixQrImagem = '';

    public string $finalizarPixCopiaCola = '';

    public string $finalizarPixValorLabel = '';

    public string $finalizarPixStatus = '';

    public string $finalizarPixErro = '';

    public ?string $finalizarPixPendingOperacao = null;

    /** Unix timestamp em que o QR da tela expira. */
    public int $finalizarPixExpiraEmTs = 0;

    /** Duração total do timer (para a barra). */
    public int $finalizarPixDuracaoSegundos = self::PDV_PIX_TELA_SEGUNDOS;

    protected function resetFinalizarPixQrcode(): void
    {
        $this->cancelarCobrancaPixPdvSePendente();

        $this->finalizarPixQrAberta = false;
        $this->finalizarPixQrConfirmado = false;
        $this->finalizarPixQrGerando = false;
        $this->finalizarPixCobrancaId = null;
        $this->finalizarPixQrImagem = '';
        $this->finalizarPixCopiaCola = '';
        $this->finalizarPixValorLabel = '';
        $this->finalizarPixStatus = '';
        $this->finalizarPixErro = '';
        $this->finalizarPixPendingOperacao = null;
        $this->finalizarPixExpiraEmTs = 0;
        $this->finalizarPixDuracaoSegundos = self::PDV_PIX_TELA_SEGUNDOS;
    }

    protected function finalizarPixQrTotalValor(): float
    {
        $total = 0.0;

        foreach ($this->finalizarPagamentos as $pagamento) {
            if (! PdvFinalizarPagamentosHelper::isFormaPixGerarQrcodePdv($pagamento)) {
                continue;
            }

            $valor = ErpMoney::parseBr($pagamento['valor'] ?? '0');

            if ($valor > 0) {
                $total += $valor;
            }
        }

        return round($total, 2);
    }

    protected function finalizarTemPixGerarQrcodeComValor(): bool
    {
        return $this->finalizarPixQrTotalValor() > 0;
    }

    /**
     * Abre o QR se necessário. Retorna false enquanto aguarda pagamento.
     */
    protected function ensurePixQrcodePdv(): bool
    {
        if (! $this->finalizarTemPixGerarQrcodeComValor()) {
            $this->finalizarPixQrConfirmado = false;
            $this->finalizarPixQrAberta = false;

            return true;
        }

        if ($this->finalizarPixQrConfirmado) {
            return true;
        }

        return $this->abrirPixQrcodePdv();
    }

    /**
     * Só abre o modal (rápido). A emissão Ailos roda em gerarPixQrcodePdvAgora().
     */
    protected function abrirPixQrcodePdv(): bool
    {
        $valor = $this->finalizarPixQrTotalValor();

        if ($valor <= 0) {
            return true;
        }

        $this->finalizarPixQrAberta = true;
        $this->finalizarPixQrConfirmado = false;
        $this->finalizarPixErro = '';
        $this->finalizarPixStatus = 'Gerando QR Code…';
        $this->finalizarPixValorLabel = ErpMoney::formatBr($valor);

        // Reaproveita cobrança pendente do mesmo valor.
        if ($this->finalizarPixCobrancaId) {
            $existente = PixCobranca::query()->find($this->finalizarPixCobrancaId);

            if (
                $existente
                && $existente->isPendente()
                && abs((float) $existente->valor - $valor) < 0.009
                && filled($existente->qr_copia_cola)
            ) {
                $this->preencherPixQrDaCobranca($existente, iniciarTimer: true);
                $this->dispatchPixQrUiEvents();

                return false;
            }

            $this->cancelarCobrancaPixPdvSePendente();
            $this->finalizarPixCobrancaId = null;
        }

        $this->finalizarPixQrImagem = '';
        $this->finalizarPixCopiaCola = '';
        $this->finalizarPixQrGerando = true;
        $this->finalizarPixExpiraEmTs = 0;
        $this->dispatchPixQrUiEvents();

        // 2ª request: modal já está na tela; fiscal overlay não cobre.
        $this->js('queueMicrotask(() => { try { $wire.gerarPixQrcodePdvAgora(); } catch (e) {} })');

        return false;
    }

    public function gerarPixQrcodePdvAgora(): void
    {
        if (! $this->finalizarPixQrAberta || $this->finalizarPixQrConfirmado) {
            return;
        }

        if ($this->finalizarPixCobrancaId && filled($this->finalizarPixCopiaCola)) {
            $this->finalizarPixQrGerando = false;

            return;
        }

        $valor = $this->finalizarPixQrTotalValor();

        if ($valor <= 0) {
            $this->finalizarPixQrGerando = false;
            $this->finalizarPixErro = 'Informe o valor do Pix.';
            $this->finalizarPixStatus = 'Erro';

            return;
        }

        $this->finalizarPixQrGerando = true;
        $this->finalizarPixErro = '';
        $this->finalizarPixStatus = 'Gerando QR Code…';
        $this->dispatch('erp-pdv-hide-fiscal-progress');

        try {
            $orderUuid = 'pdv-'.($this->caixaSessaoId ?: '0').'-'.Str::lower((string) Str::ulid());
            $empresaId = ErpContext::currentEmpresaId();

            $cobranca = app(PixCobrancaService::class)->criarParaPedido(
                orderUuid: $orderUuid,
                valor: $valor,
                payerEmail: null,
                empresaId: $empresaId,
                descricao: 'PDV '.$orderUuid,
            );

            $this->finalizarPixCobrancaId = (int) $cobranca->id;
            $this->preencherPixQrDaCobranca($cobranca, iniciarTimer: true);
            $this->finalizarPixQrGerando = false;
            $this->dispatchPixQrUiEvents();
        } catch (Throwable $e) {
            $this->finalizarPixQrGerando = false;
            $this->finalizarPixErro = trim($e->getMessage()) !== ''
                ? $e->getMessage()
                : 'Não foi possível gerar o QR Code Pix.';
            $this->finalizarPixStatus = 'Erro';
            $this->finalizarPixExpiraEmTs = 0;
            $this->dispatch('erp-pdv-hide-fiscal-progress');
            // Mantém o modal aberto com o erro (não só o alerta).
            $this->notifyPdvError('Pix QR Code', $this->finalizarPixErro);
        }
    }

    protected function dispatchPixQrUiEvents(): void
    {
        $this->dispatch('erp-pdv-hide-fiscal-progress');
        $this->dispatch('erp-pdv-pix-qr-opened');
    }

    protected function preencherPixQrDaCobranca(PixCobranca $cobranca, bool $iniciarTimer = false): void
    {
        $base64 = trim((string) ($cobranca->qr_imagem_base64 ?? ''));

        if ($base64 !== '' && ! str_starts_with($base64, 'data:')) {
            $base64 = 'data:image/png;base64,'.$base64;
        }

        $this->finalizarPixQrImagem = $base64;
        $this->finalizarPixCopiaCola = (string) ($cobranca->qr_copia_cola ?? '');
        $this->finalizarPixValorLabel = ErpMoney::formatBr((float) $cobranca->valor);
        $this->finalizarPixStatus = $cobranca->isPago()
            ? 'Pago'
            : 'Aguardando pagamento…';
        $this->finalizarPixErro = '';

        if ($iniciarTimer || $this->finalizarPixExpiraEmTs <= 0) {
            $this->finalizarPixDuracaoSegundos = self::PDV_PIX_TELA_SEGUNDOS;
            $this->finalizarPixExpiraEmTs = time() + self::PDV_PIX_TELA_SEGUNDOS;
        }
    }

    public function expirarPixQrcodePdv(): void
    {
        if (! $this->finalizarPixQrAberta || $this->finalizarPixQrConfirmado) {
            return;
        }

        if ($this->finalizarPixExpiraEmTs > 0 && time() < $this->finalizarPixExpiraEmTs) {
            return;
        }

        $this->cancelarCobrancaPixPdvSePendente();
        $this->finalizarPixCobrancaId = null;
        $this->finalizarPixQrImagem = '';
        $this->finalizarPixCopiaCola = '';
        $this->finalizarPixQrGerando = false;
        $this->finalizarPixExpiraEmTs = 0;
        $this->finalizarPixStatus = 'Expirado';
        $this->finalizarPixErro = 'Tempo esgotado (5 minutos). Gere um novo QR Code.';
    }

    public function pollFinalizarPixQrcode(): void
    {
        if (! $this->finalizarPixQrAberta || ! $this->finalizarPixCobrancaId || $this->finalizarPixQrGerando) {
            return;
        }

        if ($this->finalizarPixExpiraEmTs > 0 && time() >= $this->finalizarPixExpiraEmTs) {
            $this->expirarPixQrcodePdv();

            return;
        }

        $cobranca = PixCobranca::query()->find($this->finalizarPixCobrancaId);

        if (! $cobranca) {
            $this->finalizarPixErro = 'Cobrança Pix não encontrada.';
            $this->finalizarPixStatus = 'Erro';

            return;
        }

        try {
            $cobranca = app(PixCobrancaService::class)->atualizarStatus($cobranca);
        } catch (Throwable) {
            return;
        }

        $this->preencherPixQrDaCobranca($cobranca);

        if ($cobranca->isPago()) {
            $this->concluirPixQrcodePdv();
        } elseif ($cobranca->status === PixCobranca::STATUS_EXPIRADO) {
            $this->finalizarPixStatus = 'Expirado';
            $this->finalizarPixErro = 'QR Code expirado. Feche e tente novamente.';
        } elseif ($cobranca->status === PixCobranca::STATUS_CANCELADO) {
            $this->finalizarPixStatus = 'Cancelado';
        }
    }

    public function cancelFinalizarPixQrcode(): void
    {
        if (! $this->finalizarPixQrAberta) {
            return;
        }

        $this->cancelarCobrancaPixPdvSePendente();
        $this->finalizarPixQrAberta = false;
        $this->finalizarPixQrConfirmado = false;
        $this->finalizarPixQrGerando = false;
        $this->finalizarPixCobrancaId = null;
        $this->finalizarPixQrImagem = '';
        $this->finalizarPixCopiaCola = '';
        $this->finalizarPixStatus = '';
        $this->finalizarPixErro = '';
        $this->finalizarPixPendingOperacao = null;
        $this->finalizarPixExpiraEmTs = 0;
        $this->dispatch('erp-pdv-focus-finalizar-pagamento', index: $this->selectedPagamentoIndex ?? 0);
    }

    public function concluirPixQrcodePdv(): void
    {
        $this->finalizarPixQrConfirmado = true;
        $this->finalizarPixQrAberta = false;
        $this->finalizarPixQrGerando = false;
        $this->finalizarPixStatus = 'Pago';

        $operacao = $this->finalizarPixPendingOperacao;
        $this->finalizarPixPendingOperacao = null;

        if (filled($operacao)) {
            $this->confirmFinalizarComOperacao((string) $operacao);

            return;
        }

        $this->dispatch('erp-pdv-focus-finalizar-ok');
    }

    protected function validaPixQrcodeFinalizar(): ?string
    {
        if (! $this->finalizarTemPixGerarQrcodeComValor()) {
            return null;
        }

        if (! $this->finalizarPixQrConfirmado) {
            return 'Aguarde a confirmação do pagamento Pix (QR Code).';
        }

        return null;
    }

    protected function cancelarCobrancaPixPdvSePendente(): void
    {
        if (! $this->finalizarPixCobrancaId) {
            return;
        }

        $cobranca = PixCobranca::query()->find($this->finalizarPixCobrancaId);

        if ($cobranca && $cobranca->isPendente()) {
            try {
                app(PixCobrancaService::class)->cancelar($cobranca);
            } catch (Throwable) {
                // Ignora falha de cancelamento remoto.
            }
        }
    }
}
