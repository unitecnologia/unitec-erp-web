<?php

namespace Unitec\PdvUi\Concerns;

/**
 * Defaults de propriedades dos modais do pdvui::screen.
 * Garante que o blade compartilhado renderiza no ERP e no offline sem
 * "Property not found". Cada app sobrescreve o que realmente opera.
 */
trait ProvidesPdvErpUiDefaults
{
    public string $pdvFiscalOverlayTipo = '';

    public string $pdvFiscalOverlayMensagem = '';

    public bool $pdvBloqueado = false;

    public bool $pdvConfirmImprimirPosVenda = false;

    public bool $removerItensConfirmando = false;

    /** @var array<string, mixed>|null */
    public ?array $removerItensItem = null;

    /** @var array<int, array<string, mixed>> */
    public array $vendedorResults = [];

    public ?int $selectedVendedorIndex = null;

    public string $vendedorSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $tabelaPrecoResults = [];

    public ?int $selectedTabelaPrecoIndex = null;

    public string $tabelaPrecoSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $pdvSerialResults = [];

    public ?int $selectedPdvSerialIndex = null;

    /** @var array<int, array<string, mixed>> */
    public array $pdvGradeRows = [];

    public ?int $selectedPdvGradeIndex = null;

    /** @var array<int, array<string, mixed>> */
    public array $reimprimirResults = [];

    public ?int $selectedReimprimirIndex = null;

    public string $reimprimirSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $reimprimirNfceResults = [];

    public ?int $selectedReimprimirNfceIndex = null;

    public string $reimprimirNfceSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $receberResults = [];

    public ?int $selectedReceberIndex = null;

    public string $receberSearch = '';

    public string $importarTitulo = 'Importar';

    public string $importarTipo = '';

    /** @var array<int, array<string, mixed>> */
    public array $importarResults = [];

    public ?int $selectedImportarIndex = null;

    /** @var array<int, array<string, mixed>> */
    public array $importarPedidoResults = [];

    public ?int $selectedImportarPedidoIndex = null;

    /** @var array<int, array{label: string, key: string}> */
    public array $importarMenuOptions = [];

    public ?int $selectedImportarMenuIndex = null;

    public string $buscaAvancadaColumn = 'descricao';

    public string $buscaAvancadaSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $buscaAvancadaResults = [];

    public ?int $selectedBuscaAvancadaIndex = null;

    public string $buscaPrecoSearch = '';

    /** @var array<string, mixed>|null */
    public ?array $buscaPrecoResult = null;

    public string $buscaPrecoPrecoVendaFormatado = '0,00';

    public ?string $buscaPrecoPrecoTabelaFormatado = null;

    public string $consultaVendaMotivoEstorno = '';

    public ?int $consultaVendaEstornoId = null;

    public ?string $consultaVendaEstornoNumero = null;

    /** @var array<string, mixed>|null */
    public ?array $consultaVendaDetalhe = null;

    /** @var array{desconto?: string, acrescimo?: string} */
    public array $descontoItemForm = ['desconto' => '0,00', 'acrescimo' => '0,00'];

    /** SuperTEF (ERP): stubs para o blade compartilhado no offline. */
    public bool $finalizarSuperTefAberta = false;

    public bool $finalizarSuperTefConfirmado = false;

    public bool $finalizarSuperTefEnviando = false;

    public bool $finalizarSuperTefAguardandoConclusao = false;

    public ?int $finalizarSuperTefIntencaoId = null;

    public string $finalizarSuperTefValorLabel = '';

    public string $finalizarSuperTefStatus = '';

    public string $finalizarSuperTefErro = '';

    public string $finalizarSuperTefOrderId = '';

    public string $finalizarSuperTefPosLabel = '';

    public bool $finalizarSuperTefEnvioLock = false;

    public bool $finalizarSuperTefCancelando = false;

    public bool $finalizarSuperTefConfirmNovaCobranca = false;

    public ?int $finalizarSuperTefConfirmOcupanteId = null;

    public bool $finalizarSuperTefNovaCobrancaAutorizada = false;

    public bool $finalizarSuperTefResolverAberta = false;

    public ?int $finalizarSuperTefResolverIntencaoId = null;

    public bool $finalizarSuperTefResolverVerificado = false;

    public bool $finalizarSuperTefResolverConfirmCancelar = false;

    public bool $finalizarSuperTefResolverBusy = false;

    public string $finalizarSuperTefResolverStatusLabel = '';

    public string $finalizarSuperTefResolverDetalhe = '';

    public function finalizarTemSuperTefComValor(): bool
    {
        return false;
    }

    public function finalizarSuperTefDevePollar(): bool
    {
        return false;
    }

    public function finalizarSuperTefPodeCancelarPagamento(): bool
    {
        return false;
    }

    public function enviarSuperTefPdvAgora(): void
    {
        //
    }

    public function concluirVendaAposSuperTefPago(): void
    {
        //
    }

    public function pollFinalizarSuperTef(): void
    {
        //
    }

    public function cancelFinalizarSuperTef(): void
    {
        //
    }

    public function cancelarPagamentoSuperTef(): void
    {
        //
    }

    public function voltarConfirmacaoNovaCobrancaSuperTef(): void
    {
        //
    }

    public function confirmarNovaCobrancaSuperTef(): void
    {
        //
    }

    public function voltarResolverPagamentoAnteriorSuperTef(): void
    {
        //
    }

    public function verificarPagamentoAnteriorSuperTef(): void
    {
        //
    }

    public function solicitarCancelamentoPagamentoAnteriorSuperTef(): void
    {
        //
    }

    public function confirmarCancelamentoPagamentoAnteriorSuperTef(): void
    {
        //
    }

    public function tratarPagamentoAnteriorAprovadoSuperTef(): void
    {
        //
    }

    public function finalizarSuperTefResolverPodeCancelar(): bool
    {
        return false;
    }

    public function finalizarSuperTefResolverPodeTratarPago(): bool
    {
        return false;
    }

    public function finalizarSuperTefResolverPosLiberada(): bool
    {
        return false;
    }

    /**
     * Stub: offline não tem PdvConfig Filament; evita erro em blades que chamam.
     */
    public function pdvConfig(): object
    {
        return new class
        {
            public function motivoEstornoAutomatico(): bool
            {
                return false;
            }
        };
    }
}
