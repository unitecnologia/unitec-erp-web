<?php

namespace Unitec\PdvUi\Concerns;

/**
 * Defaults do "contrato" da tela compartilhada do PDV (pdvui::partials.main e o
 * wrapper). Um componente Livewire que consuma a tela do pacote pode usar este
 * trait para já ter TODOS os membros que a view referencia, sobrescrevendo
 * apenas os que de fato suporta (ex.: o PDV offline). O PDV do ERP NÃO usa este
 * trait — ele implementa tudo nas suas próprias concerns.
 *
 * Assim, quando a tela do pacote ganhar um campo novo, basta adicionar o
 * default aqui e os dois lados continuam funcionando.
 */
trait ProvidesPdvScreenDefaults
{
    // --- Flags do wrapper (data-*) -----------------------------------------
    public bool $pdvExibirF3Vendedor = false;

    public bool $pdvPermitirDescontoItem = false;

    public bool $pdvSomAtivo = false;

    public bool $pdvExibeMesas = false;

    public bool $pdvCaixaRapido = false;

    public bool $pdvLerPesoBalanca = false;

    public bool $pdvBuscaBalancaBarras = false;

    public bool $pdvUsaTef = false;

    // --- Estado da grade / consulta ----------------------------------------
    public bool $pdvEmConsulta = false;

    /** @var array<int,array<string,mixed>> */
    public array $pdvSearchResults = [];

    public ?int $selectedSearchIndex = null;

    public bool $pdvMostrarDetalheItem = false;

    public ?int $selectedCupomIndex = null;

    // --- Preview do produto -------------------------------------------------
    public string $pdvPreviewProductName = '';

    public ?string $pdvPreviewFotoUrl = null;

    // --- Fluxo de lançamento (Qtde/Preço) ----------------------------------
    public bool $pdvShowLaunchFields = false;

    public string $pdvLaunchStep = '';

    public string $pdvLaunchQtd = '';

    public string $pdvLaunchPreco = '';

    public string $pdvLaunchItemTotal = '0,00';

    // --- Detalhe do item selecionado ---------------------------------------
    public string $cupomItemQtd = '0';

    public string $cupomItemPreco = '0,00';

    public string $cupomItemTotal = '0,00';

    // --- Flash de último item ----------------------------------------------
    public string $pdvFlashQtd = '';

    public string $pdvFlashPreco = '';

    public string $pdvFlashTotal = '';

    // --- Cabeçalho ----------------------------------------------------------
    /** Texto do letreiro (marquee) no topo; vazio = mostra o nome da empresa. */
    public string $pdvMarqueeTexto = '';

    // --- Toolbar / rodapé ---------------------------------------------------
    public bool $pdvExibirResumoCaixa = false;

    public bool $pdvHabilitarTabelaPreco = false;

    /**
     * Formata a quantidade do cupom (remove zeros à direita). Igual ao ERP.
     */
    public function formatCupomQuantidade(float $quantidade): string
    {
        $formatado = rtrim(rtrim(number_format($quantidade, 3, ',', ''), '0'), ',');

        return $formatado === '' ? '0' : $formatado;
    }

    // --- Métodos referenciados pela tela (no-op por padrão) ----------------
    public function selectSearchResult(int $index): void {}

    public function addSearchResultToCupom(int $index): void {}

    public function selectCupomItem(int $index): void {}

    public function handlePdvLaunchQtdEnter(): void {}

    public function handlePdvLaunchPrecoEnter(mixed $value = null): void {}

    public function openImportarModal(): void {}

    public function openPdvModal(string $modal): void {}

    public function openPersonOverlay(): void {}

    public function openProductOverlay(): void {}

    public function handlePdvEscape(): void {}
}
