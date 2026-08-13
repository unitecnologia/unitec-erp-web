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

    public string $removerItensSearch = '';

    /** @var array<int, array<string, mixed>> */
    public array $vendedorResults = [];

    public ?int $selectedVendedorIndex = null;

    public ?int $vendedorId = null;

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

    public string $consultaVendaSearch = '';

    /** @var list<array<string, mixed>> */
    public array $consultaVendaResults = [];

    public string $receberValor = '0,00';

    public string $descontoItemTipo = 'desconto';

    public string $descontoItemModo = 'percentual';

    public string $descontoItemValor = '0,00';

    /** @var array<string, mixed>|null */
    public ?array $cupomItemSelecionado = null;

    public string $pdvAuthPassword = '';

    public string $pdvFiscalOverlayTitulo = '';

    public string $pdvFiscalOverlayDetalhe = '';

    public string $vendaEsperaSearch = '';

    /** @var list<array<string, mixed>> */
    public array $vendaEsperaResults = [];

    public ?int $selectedVendaEsperaIndex = null;

    public ?int $vendaEsperaExcluirId = null;

    /** @var array{desconto?: string, acrescimo?: string} */
    public array $descontoItemForm = ['desconto' => '0,00', 'acrescimo' => '0,00'];

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
