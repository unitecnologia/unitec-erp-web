<?php

namespace App\Filament\Concerns;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\ProductResource\Pages\Concerns\ErpProductFormPage;
use App\Models\Cfop;
use App\Models\Compra;
use App\Models\Empresa;
use App\Models\Grupo;
use App\Models\NotaFornecedor;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\Compra\GerarCompraFromNotaService;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpScreen;
use App\Support\Erp\Fiscal\CfopEntradaResolver;
use App\Support\Erp\NotaFornecedor\NotaFornecedorDanfeReportService;
use App\Support\Erp\NotaFornecedor\NotaFornecedorFornecedorCadastro;
use App\Support\Erp\NotaFornecedor\NotaFornecedorProductPrefill;
use App\Support\Erp\NotaFornecedor\NotaFornecedorXmlProdutoMatcher;
use App\Support\Fiscal\NotaFornecedorXmlDownloadService;
use DomainException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

trait ManagesImportarXmlModal
{
    use WithFileUploads;

    public bool $importarXmlModalOpen = false;

    public ?int $importarXmlNotaId = null;

    /** @var array<string, mixed> */
    public array $importarXmlHeader = [];

    /** @var list<array<string, mixed>> */
    public array $importarXmlItens = [];

    /** @var array<string, mixed> */
    public array $importarXmlTotais = [];

    public ?int $importarXmlItemIndex = null;

    public string $importarXmlTab = 'detalhes';

    public bool $overlayProductOpen = false;

    public $importarXmlUpload = null;

    public bool $importarXmlPesquisarOpen = false;

    public string $importarXmlPesquisarTermo = '';

    /** @var list<array<string, mixed>> */
    public array $importarXmlPesquisarResultados = [];

    public ?int $importarXmlPesquisarIndex = null;

    public bool $importarXmlCadastroProgressOpen = false;

    public int $importarXmlCadastroProgressCurrent = 0;

    public int $importarXmlCadastroProgressTotal = 0;

    public int $importarXmlCadastroProgressPercent = 0;

    public string $importarXmlCadastroProgressLabel = '';

    public string $importarXmlCadastroProgressDetail = '';

    /** @var list<int> */
    public array $importarXmlCadastroProgressFila = [];

    public int $importarXmlCadastroProgressCadastrados = 0;

    public int $importarXmlCadastroProgressJaExistentes = 0;

    /** @var list<string> */
    public array $importarXmlCadastroProgressAvisos = [];

    public bool $importarXmlAvisoOpen = false;

    public string $importarXmlAvisoTitulo = '';

    public string $importarXmlAvisoMensagem = '';

    public string $importarXmlAvisoTone = 'info';

    public bool $importarXmlImportProgressOpen = false;

    public int $importarXmlImportProgressPercent = 0;

    public int $importarXmlImportProgressStep = 0;

    public string $importarXmlImportProgressLabel = '';

    public string $importarXmlImportProgressDetail = '';

    public bool $importarXmlFecharConfirmOpen = false;

    #[Computed]
    public function importarXmlGruposOptions(): array
    {
        return Grupo::query()
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'nome')
            ->all();
    }

    /**
     * CFOPs de entrada ativos (1/2/3) para o combo da tela de importação.
     *
     * @return list<string>
     */
    #[Computed]
    public function importarXmlCfopOptions(): array
    {
        return Cfop::query()
            ->ativos()
            ->where('tipo', Cfop::TIPO_ENTRADA)
            ->orderBy('codigo')
            ->pluck('codigo')
            ->map(static fn ($codigo): string => (string) $codigo)
            ->values()
            ->all();
    }

    public function openLerXmlSelecionada(): void
    {
        $id = $this->highlightedRecordIdOrNotify('ler o XML');

        if (! $id) {
            return;
        }

        $this->openLerXml((int) $id);
    }

    public function openLerXmlFromCompraSelecionada(): void
    {
        $recordId = method_exists($this, 'highlightedRecordIdOrNotify')
            ? $this->highlightedRecordIdOrNotify('ler o XML')
            : null;

        if (! $recordId) {
            return;
        }

        $compra = Compra::query()->find($recordId);

        if (! $compra) {
            Notification::make()->title('Compra não encontrada.')->warning()->send();

            return;
        }

        $nota = $this->resolveNotaFornecedorFromCompra($compra);

        if ($nota) {
            $this->openLerXml((int) $nota->id);

            return;
        }

        // Sem nota vinculada: abre modal vazio para buscar/importar o XML.
        $this->openImportarXmlModalVazio();
    }

    public function openImportarXmlModalVazio(): void
    {
        $this->importarXmlNotaId = null;
        $this->importarXmlHeader = [
            'chave' => '',
            'data_entrada' => now()->format('d/m/Y'),
            'data_emissao' => '—',
            'cfop' => '—',
            'fornecedor' => '—',
            'cnpj' => '—',
            'uf' => '—',
            'numero' => '—',
            'fornecedor_status' => '',
            'fornecedor_status_label' => '',
        ];
        $this->importarXmlItens = [];
        $this->importarXmlTotais = [];
        $this->importarXmlItemIndex = null;
        $this->importarXmlTab = 'detalhes';
        $this->importarXmlUpload = null;
        $this->importarXmlFecharConfirmOpen = false;
        $this->importarXmlModalOpen = true;
    }

    public function updatedImportarXmlUpload(): void
    {
        if (! $this->importarXmlUpload instanceof TemporaryUploadedFile) {
            return;
        }

        if ($this->importarXmlImportProgressOpen || $this->importarXmlCadastroProgressOpen) {
            $this->importarXmlUpload = null;

            return;
        }

        $extension = strtolower((string) $this->importarXmlUpload->getClientOriginalExtension());
        $mime = strtolower((string) ($this->importarXmlUpload->getMimeType() ?? ''));
        $mimeOk = $mime === ''
            || str_contains($mime, 'xml')
            || $mime === 'text/plain'
            || $mime === 'application/octet-stream';

        if ($extension !== 'xml' || ! $mimeOk) {
            $this->importarXmlUpload = null;
            $this->showImportarXmlAviso(
                'Somente arquivo XML',
                'Selecione um arquivo com extensão .xml.',
                'warning',
            );

            return;
        }

        try {
            $xml = $this->importarXmlUpload->get();

            if (! is_string($xml) || trim($xml) === '') {
                $this->importarXmlUpload = null;
                $this->showImportarXmlAviso(
                    'Arquivo XML inválido',
                    'Não foi possível ler o conteúdo do arquivo.',
                    'error',
                );

                return;
            }

            session([
                'erp_importar_xml_pending' => $xml,
                'erp_importar_xml_pending_name' => (string) $this->importarXmlUpload->getClientOriginalName(),
            ]);

            $this->importarXmlUpload = null;
            $this->iniciarProgressoImportXml();
        } catch (\Throwable $exception) {
            $this->importarXmlUpload = null;
            $this->showImportarXmlAviso(
                'Falha ao ler XML',
                $exception->getMessage(),
                'error',
            );
        }
    }

    protected function iniciarProgressoImportXml(): void
    {
        $nome = (string) session('erp_importar_xml_pending_name', 'arquivo.xml');

        $this->importarXmlImportProgressStep = 0;
        $this->importarXmlImportProgressPercent = 5;
        $this->importarXmlImportProgressLabel = 'Importando XML…';
        $this->importarXmlImportProgressDetail = $nome;
        $this->importarXmlImportProgressOpen = true;

        $this->js(<<<'JS'
            (async () => {
                const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
                try {
                    while (await $wire.processarProximoImportXml()) {
                        await wait(70);
                    }
                } catch (e) {
                    console.error(e);
                }
            })();
        JS);
    }

    public function processarProximoImportXml(): bool
    {
        if (! $this->importarXmlImportProgressOpen) {
            return false;
        }

        $xml = session('erp_importar_xml_pending');

        if (! is_string($xml) || trim($xml) === '') {
            $this->falharProgressoImportXml('Arquivo XML inválido', 'O conteúdo do XML não está mais disponível. Selecione o arquivo novamente.');

            return false;
        }

        try {
            return match ($this->importarXmlImportProgressStep) {
                0 => $this->stepImportXmlValidar($xml),
                1 => $this->stepImportXmlSalvarNota($xml),
                2 => $this->stepImportXmlPopularModal(),
                default => $this->concluirProgressoImportXml(),
            };
        } catch (\Throwable $exception) {
            $this->falharProgressoImportXml('Falha ao importar XML', $exception->getMessage());

            return false;
        }
    }

    protected function stepImportXmlValidar(string $xml): bool
    {
        $this->importarXmlImportProgressStep = 1;
        $this->importarXmlImportProgressPercent = 25;
        $this->importarXmlImportProgressLabel = 'Validando NF-e…';
        $this->importarXmlImportProgressDetail = 'Lendo estrutura e itens do XML';

        $parsed = (new NotaFornecedorDanfeReportService())->parseXml($xml);

        if ($parsed === null || ($parsed['itens'] ?? []) === []) {
            $this->falharProgressoImportXml(
                'XML sem itens para leitura',
                'O arquivo selecionado não contém uma NF-e válida com itens (procNFe).',
                'warning',
            );

            return false;
        }

        session(['erp_importar_xml_parsed_ok' => true]);

        return true;
    }

    protected function stepImportXmlSalvarNota(string $xml): bool
    {
        $this->importarXmlImportProgressStep = 2;
        $this->importarXmlImportProgressPercent = 55;
        $this->importarXmlImportProgressLabel = 'Importando XML para o sistema…';
        $this->importarXmlImportProgressDetail = 'Gravando nota e dados do fornecedor';

        $danfe = new NotaFornecedorDanfeReportService();
        $parsed = $danfe->parseXml($xml);

        if ($parsed === null || ($parsed['itens'] ?? []) === []) {
            $this->falharProgressoImportXml(
                'XML sem itens para leitura',
                'O arquivo selecionado não contém uma NF-e válida com itens (procNFe).',
                'warning',
            );

            return false;
        }

        $emitente = $parsed['emitente'] ?? [];
        $chave = preg_replace('/\D/', '', (string) ($parsed['chave'] ?? '')) ?? '';

        if ($chave === '' && preg_match('/Id=["\']NFe(\d{44})["\']/', $xml, $m) === 1) {
            $chave = $m[1];
        }

        if ($chave === '' && preg_match('/<chNFe>(\d{44})<\/chNFe>/', $xml, $m) === 1) {
            $chave = $m[1];
        }

        $empresa = $this->resolveEmpresaAtivaForImportarXml();
        $cnpj = preg_replace('/\D/', '', (string) ($emitente['cnpj'] ?? '')) ?? '';
        $numero = (string) ($parsed['numero'] ?? '');

        $nota = null;

        if (strlen($chave) === 44) {
            $nota = NotaFornecedor::query()
                ->when($empresa?->id, fn ($q) => $q->where('empresa_id', $empresa->id))
                ->where('chave', $chave)
                ->first();
        }

        $dataEmissao = $this->parseImportarXmlDate($parsed['data_emissao'] ?? null);
        $rawEmissao = is_string($parsed['data_emissao'] ?? null) ? trim((string) $parsed['data_emissao']) : '';

        if ($rawEmissao !== '' && $rawEmissao !== '—' && $dataEmissao === null) {
            $this->falharProgressoImportXml(
                'Data de emissão inválida no XML',
                'Não foi possível interpretar a data de emissão "'.$rawEmissao.'".',
                'warning',
            );

            return false;
        }

        $rawTotal = $parsed['totais']['total'] ?? $parsed['totais']['total_nota'] ?? null;
        $total = $this->parseImportarXmlMoney($rawTotal);

        if ($total === null) {
            $this->falharProgressoImportXml(
                'Total inválido no XML',
                'O valor total da NF-e não pôde ser interpretado.',
                'warning',
            );

            return false;
        }

        $payload = [
            'empresa_id' => $empresa?->id,
            'data_entrada' => now()->toDateString(),
            'data_emissao' => $dataEmissao,
            'numero' => $numero !== '' ? $numero : null,
            'chave' => strlen($chave) === 44 ? $chave : null,
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'nome' => (string) ($emitente['nome'] ?? ''),
            'total' => $total,
            'xml' => $xml,
            'status' => NotaFornecedor::STATUS_ACEITA,
        ];

        if ($nota) {
            // Não rebaixa nota que já gerou compra/estoque.
            if ($nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
                unset($payload['status']);
            }

            $nota->forceFill($payload)->save();
        } else {
            $nota = NotaFornecedor::query()->create($payload);
        }

        session(['erp_importar_xml_nota_id' => (int) $nota->id]);

        return true;
    }

    protected function stepImportXmlPopularModal(): bool
    {
        $this->importarXmlImportProgressStep = 3;
        $this->importarXmlImportProgressPercent = 85;
        $this->importarXmlImportProgressLabel = 'Carregando itens na grade…';
        $this->importarXmlImportProgressDetail = 'Vinculando produtos e montando a tela';

        $notaId = (int) session('erp_importar_xml_nota_id', 0);
        $nota = $notaId > 0 ? NotaFornecedor::query()->find($notaId) : null;

        if (! $nota) {
            $this->falharProgressoImportXml(
                'Falha ao importar XML',
                'A nota foi gravada, mas não foi possível montar a tela de importação.',
                'error',
            );

            return false;
        }

        $this->populateImportarXmlModal($nota);

        if ($this->importarXmlItens === [] || ! $this->importarXmlModalOpen) {
            $this->falharProgressoImportXml(
                'XML sem itens para leitura',
                'Não foi possível carregar os itens do XML na grade.',
                'warning',
            );

            return false;
        }

        return true;
    }

    protected function concluirProgressoImportXml(): bool
    {
        $this->importarXmlImportProgressPercent = 100;
        $this->importarXmlImportProgressLabel = 'Importação concluída';
        $this->importarXmlImportProgressDetail = 'XML carregado com sucesso';
        $this->limparSessaoImportXmlPendente();
        $this->importarXmlImportProgressOpen = false;
        $this->importarXmlImportProgressStep = 0;

        $qtd = count($this->importarXmlItens);
        $this->showImportarXmlAviso(
            'XML importado com sucesso',
            $qtd > 0
                ? "A nota foi importada para o sistema.\n{$qtd} item(ns) carregado(s) na grade."
                : 'A nota foi importada para o sistema.',
            'success',
        );

        return false;
    }

    protected function falharProgressoImportXml(string $titulo, string $mensagem, string $tone = 'error'): void
    {
        $this->limparSessaoImportXmlPendente();
        $this->importarXmlImportProgressOpen = false;
        $this->importarXmlImportProgressStep = 0;
        $this->importarXmlImportProgressPercent = 0;
        $this->showImportarXmlAviso($titulo, $mensagem, $tone);
    }

    protected function limparSessaoImportXmlPendente(): void
    {
        session()->forget([
            'erp_importar_xml_pending',
            'erp_importar_xml_pending_name',
            'erp_importar_xml_parsed_ok',
            'erp_importar_xml_nota_id',
        ]);
    }

    public function carregarXmlImportacao(string $xml): void
    {
        session([
            'erp_importar_xml_pending' => $xml,
            'erp_importar_xml_pending_name' => 'xml-importado.xml',
        ]);

        $this->iniciarProgressoImportXml();
    }

    public function openLerXml(int $notaId, bool $requireAceita = true): void
    {
        $nota = NotaFornecedor::query()->find($notaId);

        if (! $nota) {
            Notification::make()->title('Nota não encontrada.')->danger()->send();

            return;
        }

        if ($requireAceita && ! in_array($nota->status, [NotaFornecedor::STATUS_ACEITA, NotaFornecedor::STATUS_GEROU_COMPRAS], true)) {
            Notification::make()
                ->title('Ler XML disponível apenas para notas aceitas')
                ->body('Confirme a nota com F4 | Confirmar (Ciência da Operação) antes de ler o XML.')
                ->warning()
                ->send();

            return;
        }

        $empresa = $this->resolveEmpresaAtivaForImportarXml();

        if ($empresa) {
            try {
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }

                $nota = (new NotaFornecedorXmlDownloadService())->ensureProcNfe($nota, $empresa);
            } catch (FiscalEngineException $exception) {
                if ($exception->sefazCodigo === '596' || str_contains(mb_strtolower($exception->getMessage(), 'UTF-8'), 'prazo de 10 dias')) {
                    $this->notifyImportarXml(
                        'XML completo indisponível (prazo de 10 dias)',
                        $exception->getMessage(),
                        $exception->sefazCodigo,
                        'warning',
                    );
                } else {
                    $this->notifyImportarXml(
                        'Falha ao obter XML da NF-e',
                        $exception->getMessage(),
                        $exception->sefazCodigo,
                    );
                }

                return;
            } catch (\Throwable $exception) {
                $this->notifyImportarXml(
                    'Não foi possível baixar o XML completo',
                    $exception->getMessage(),
                );

                return;
            }
        }

        $this->populateImportarXmlModal($nota);
    }

    public function closeImportarXmlModal(): void
    {
        $this->importarXmlFecharConfirmOpen = false;
        $this->importarXmlModalOpen = false;
        $this->importarXmlNotaId = null;
        $this->importarXmlHeader = [];
        $this->importarXmlItens = [];
        $this->importarXmlTotais = [];
        $this->importarXmlItemIndex = null;
        $this->importarXmlTab = 'detalhes';
        $this->importarXmlUpload = null;
        $this->importarXmlCadastroProgressOpen = false;
        $this->importarXmlCadastroProgressFila = [];
        $this->importarXmlImportProgressOpen = false;
        $this->importarXmlImportProgressStep = 0;
        $this->limparSessaoImportXmlPendente();
        $this->closeImportarXmlAviso();
        $this->closePesquisarProdutoXml();
    }

    public function requestCloseImportarXmlModal(): void
    {
        if ($this->importarXmlImportProgressOpen || $this->importarXmlCadastroProgressOpen) {
            return;
        }

        if ($this->importarXmlFecharConfirmOpen) {
            $this->importarXmlFecharConfirmOpen = false;

            return;
        }

        if ($this->importarXmlPesquisarOpen) {
            $this->closePesquisarProdutoXml();

            return;
        }

        if ($this->importarXmlItens !== [] && $this->importarXmlNotaId) {
            $this->importarXmlFecharConfirmOpen = true;

            return;
        }

        $this->closeImportarXmlModal();
    }

    public function cancelCloseImportarXmlModal(): void
    {
        $this->importarXmlFecharConfirmOpen = false;
    }

    /**
     * Finaliza a leitura do XML: gera Compra + itens e lança entrada de estoque.
     * Só então a nota passa para "Gerou Compras".
     */
    public function finalizarImportarXml(): void
    {
        if (! $this->importarXmlNotaId) {
            Notification::make()
                ->title('Nenhuma nota carregada no modal.')
                ->warning()
                ->send();

            return;
        }

        $nota = NotaFornecedor::query()->find($this->importarXmlNotaId);

        if (! $nota) {
            Notification::make()->title('Nota não encontrada.')->danger()->send();
            $this->closeImportarXmlModal();

            return;
        }

        if ($nota->status === NotaFornecedor::STATUS_GEROU_COMPRAS) {
            $compraExistente = $nota->compra_id
                ? Compra::query()->find($nota->compra_id)
                : null;

            if ($compraExistente && $compraExistente->status !== Compra::STATUS_CANCELADA) {
                Notification::make()
                    ->title('Esta nota já gerou compra.')
                    ->body('Compra #'.$compraExistente->numero.' — estoque já foi lançado. Para refazer, cancele a compra primeiro.')
                    ->info()
                    ->send();

                $this->closeImportarXmlModal();

                return;
            }
        }

        if ($this->importarXmlItens === []) {
            Notification::make()
                ->title('Não há itens do XML para gerar a compra.')
                ->warning()
                ->send();

            return;
        }

        $cfopInvalido = $this->normalizarTodosCfopImportarXml();

        if ($cfopInvalido !== null) {
            Notification::make()
                ->title('CFOP de entrada inválido.')
                ->body($cfopInvalido)
                ->warning()
                ->send();

            return;
        }

        foreach (array_keys($this->importarXmlItens) as $index) {
            $this->recalcularLinhaItemXml((int) $index);
        }

        try {
            $compra = (new GerarCompraFromNotaService())->gerar($nota, $this->importarXmlItens);
        } catch (DomainException $exception) {
            Notification::make()
                ->title('Não foi possível gerar a compra.')
                ->body($exception->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $exception) {
            report($exception);

            Notification::make()
                ->title('Erro ao gerar a compra.')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->closeImportarXmlModal();

        Notification::make()
            ->title('Compra gerada com entrada de estoque.')
            ->body('Compra #'.$compra->numero.' — nota marcada como Gerou Compras.')
            ->success()
            ->send();

        if (method_exists($this, 'resetTable')) {
            $this->resetTable();
        }
    }

    public function selectImportarXmlItem(int $index): void
    {
        if ($index < 0 || $index >= count($this->importarXmlItens)) {
            return;
        }

        $this->importarXmlItemIndex = $index;
    }

    public function cadastrarProdutoXmlSelecionado(): void
    {
        if ($this->importarXmlItemIndex === null) {
            Notification::make()
                ->title('Selecione um item da grade')
                ->body('Clique no produto em vermelho antes de cadastrar.')
                ->warning()
                ->send();

            return;
        }

        $index = $this->importarXmlItemIndex;
        $item = $this->importarXmlItens[$index] ?? null;

        if (! is_array($item)) {
            Notification::make()->title('Item não encontrado.')->danger()->send();

            return;
        }

        if (($item['vinculado'] ?? false) === true) {
            Notification::make()
                ->title('Produto já vinculado')
                ->body('Este item já possui cadastro/vínculo. Use Desvincular se precisar alterar.')
                ->warning()
                ->send();

            return;
        }

        $cnpj = preg_replace('/\D/', '', (string) ($this->importarXmlHeader['cnpj'] ?? '')) ?? '';
        $matcher = new NotaFornecedorXmlProdutoMatcher();
        $existente = $matcher->findExistingForItem($item, $cnpj);

        if ($existente instanceof Product) {
            $fornecedor = $matcher->resolveFornecedorByCnpj($cnpj);

            if ($fornecedor) {
                $matcher->vincularProduto($existente, $fornecedor, (string) ($item['codigo'] ?? $existente->codigo));
            }

            $this->aplicarProdutoNoItemXml($index, $existente);

            Notification::make()
                ->title('Produto já cadastrado')
                ->body('O item foi vinculado ao produto '.$existente->codigo.' — '.$existente->descricao.'.')
                ->success()
                ->send();

            return;
        }

        $fornecedor = $matcher->resolveFornecedorByCnpj($cnpj);
        $preco = BrDecimal::parse((string) ($item['prc_unitario'] ?? '0'), 3);

        NotaFornecedorProductPrefill::store([
            'item_index' => $index,
            'cnpj' => $cnpj,
            'person_id' => $fornecedor?->id,
            'codigo_fornecedor' => (string) ($item['codigo'] ?? ''),
            'ean' => (string) ($item['ean'] ?? ''),
            'descricao' => (string) ($item['descricao'] ?? ''),
            'ncm' => (string) ($item['ncm'] ?? ''),
            'unidade' => (string) ($item['und'] ?? 'UN'),
            'preco' => $preco,
        ]);

        ErpScreen::set('Cadastro de Produtos');
        $this->overlayProductOpen = true;
    }

    public function cadastrarTodosProdutosXml(): void
    {
        if ($this->importarXmlCadastroProgressOpen) {
            return;
        }

        if ($this->importarXmlItens === []) {
            $this->showImportarXmlAviso(
                'Nenhum item para cadastrar',
                'Carregue um XML com itens antes de usar Cadastrar Todos.',
                'warning',
            );

            return;
        }

        $fila = [];

        foreach ($this->importarXmlItens as $index => $item) {
            if (is_array($item) && ($item['vinculado'] ?? false) !== true) {
                $fila[] = (int) $index;
            }
        }

        if ($fila === []) {
            $total = count($this->importarXmlItens);
            $vinculados = collect($this->importarXmlItens)
                ->filter(fn ($row) => is_array($row) && ($row['vinculado'] ?? false) === true)
                ->count();

            $this->showImportarXmlAviso(
                'Todos os itens já estão vinculados',
                $vinculados > 0
                    ? "{$vinculados} de {$total} item(ns) já possuem produto.\nNada novo será cadastrado."
                    : 'Não há itens pendentes de vínculo.',
                'info',
            );

            return;
        }

        $this->importarXmlCadastroProgressFila = $fila;
        $this->importarXmlCadastroProgressTotal = count($fila);
        $this->importarXmlCadastroProgressCurrent = 0;
        $this->importarXmlCadastroProgressPercent = 0;
        $this->importarXmlCadastroProgressCadastrados = 0;
        $this->importarXmlCadastroProgressJaExistentes = 0;
        $this->importarXmlCadastroProgressAvisos = [];
        $this->importarXmlCadastroProgressLabel = 'Preparando cadastro automático…';
        $this->importarXmlCadastroProgressDetail = '0 de '.$this->importarXmlCadastroProgressTotal.' item(ns)';
        $this->importarXmlCadastroProgressOpen = true;

        $this->js(<<<'JS'
            (async () => {
                const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
                try {
                    while (await $wire.processarProximoCadastroXml()) {
                        await wait(90);
                    }
                } catch (e) {
                    console.error(e);
                }
            })();
        JS);
    }

    public function processarProximoCadastroXml(): bool
    {
        if (! $this->importarXmlCadastroProgressOpen) {
            return false;
        }

        if ($this->importarXmlCadastroProgressFila === []) {
            $this->finalizarCadastroTodosProgresso();

            return false;
        }

        $index = (int) array_shift($this->importarXmlCadastroProgressFila);
        $this->importarXmlCadastroProgressCurrent++;
        $this->importarXmlItemIndex = $index;

        $item = $this->importarXmlItens[$index] ?? null;
        $descricaoItem = is_array($item)
            ? trim((string) ($item['descricao'] ?? $item['codigo'] ?? 'Item'))
            : 'Item';

        $this->importarXmlCadastroProgressLabel = 'Processando item '.$this->importarXmlCadastroProgressCurrent
            .' de '.$this->importarXmlCadastroProgressTotal.'…';
        $this->importarXmlCadastroProgressDetail = $descricaoItem;
        $this->importarXmlCadastroProgressPercent = (int) round(
            ($this->importarXmlCadastroProgressCurrent / max(1, $this->importarXmlCadastroProgressTotal)) * 100
        );

        if (! is_array($item) || ($item['vinculado'] ?? false) === true) {
            return $this->importarXmlCadastroProgressFila !== [];
        }

        $cnpj = preg_replace('/\D/', '', (string) ($this->importarXmlHeader['cnpj'] ?? '')) ?? '';
        $matcher = new NotaFornecedorXmlProdutoMatcher();
        $fornecedor = $matcher->resolveFornecedorByCnpj($cnpj);
        $empresa = $this->resolveEmpresaAtivaForImportarXml();

        try {
            $existente = $matcher->findExistingForItem($item, $cnpj);

            if ($existente instanceof Product) {
                if ($fornecedor) {
                    $matcher->vincularProduto($existente, $fornecedor, (string) ($item['codigo'] ?? $existente->codigo));
                }

                $this->aplicarProdutoNoItemXml($index, $existente);
                $this->importarXmlCadastroProgressJaExistentes++;
                $aviso = $existente->codigo.' — '.$existente->descricao;
                $this->importarXmlCadastroProgressAvisos[] = $aviso;
                $this->importarXmlCadastroProgressLabel = 'Produto já cadastrado — vinculado';
                $this->importarXmlCadastroProgressDetail = $aviso;
            } else {
                $produto = $this->criarProdutoAutomaticoDoItemXml($item, $fornecedor, $empresa);

                if ($fornecedor) {
                    $matcher->vincularProduto($produto, $fornecedor, (string) ($item['codigo'] ?? $produto->codigo));
                }

                $this->aplicarProdutoNoItemXml($index, $produto);
                $this->importarXmlCadastroProgressCadastrados++;
                $this->importarXmlCadastroProgressLabel = 'Produto cadastrado automaticamente';
                $this->importarXmlCadastroProgressDetail = $produto->codigo.' — '.$produto->descricao;
            }
        } catch (\Throwable $exception) {
            $this->importarXmlCadastroProgressLabel = 'Falha no item '.$this->importarXmlCadastroProgressCurrent;
            $this->importarXmlCadastroProgressDetail = $exception->getMessage();

            Notification::make()
                ->title('Falha ao cadastrar item '.($index + 1))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        if ($this->importarXmlCadastroProgressFila === []) {
            $this->finalizarCadastroTodosProgresso();

            return false;
        }

        return true;
    }

    protected function finalizarCadastroTodosProgresso(): void
    {
        $cadastrados = $this->importarXmlCadastroProgressCadastrados;
        $jaExistentes = $this->importarXmlCadastroProgressJaExistentes;
        $avisos = $this->importarXmlCadastroProgressAvisos;

        $this->importarXmlCadastroProgressPercent = 100;
        $this->importarXmlCadastroProgressLabel = 'Concluído';
        $this->importarXmlCadastroProgressDetail = "{$cadastrados} cadastrado(s), {$jaExistentes} já existia(m)";
        $this->importarXmlCadastroProgressOpen = false;
        $this->importarXmlCadastroProgressFila = [];

        $linhas = [];

        if ($cadastrados > 0) {
            $linhas[] = "{$cadastrados} produto(s) cadastrado(s) automaticamente.";
        }

        if ($jaExistentes > 0) {
            $linhas[] = "{$jaExistentes} produto(s) já existia(m) e foram vinculados:";
            foreach (array_slice($avisos, 0, 8) as $aviso) {
                $linhas[] = '• '.$aviso;
            }
            if (count($avisos) > 8) {
                $linhas[] = '…';
            }
        }

        if ($linhas === []) {
            $this->showImportarXmlAviso(
                'Nada a cadastrar',
                'Não há itens pendentes de vínculo.',
                'info',
            );

            return;
        }

        $tone = $jaExistentes > 0 && $cadastrados === 0 ? 'warning' : 'info';
        $titulo = $cadastrados > 0
            ? 'Cadastro automático concluído'
            : 'Produtos já cadastrados';

        $this->showImportarXmlAviso($titulo, implode("\n", $linhas), $tone);
    }

    public function showImportarXmlAviso(string $titulo, string $mensagem, string $tone = 'info'): void
    {
        $this->importarXmlAvisoTitulo = $titulo;
        $this->importarXmlAvisoMensagem = $mensagem;
        $this->importarXmlAvisoTone = in_array($tone, ['info', 'warning', 'success', 'error'], true) ? $tone : 'info';
        $this->importarXmlAvisoOpen = true;
    }

    public function closeImportarXmlAviso(): void
    {
        $this->importarXmlAvisoOpen = false;
        $this->importarXmlAvisoTitulo = '';
        $this->importarXmlAvisoMensagem = '';
        $this->importarXmlAvisoTone = 'info';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function criarProdutoAutomaticoDoItemXml(array $item, ?Person $fornecedor, ?Empresa $empresa): Product
    {
        $defaults = ErpProductFormPage::defaultProductFormData($empresa);
        $preco = BrDecimal::parse((string) ($item['prc_unitario'] ?? '0'), 3);
        $precoVenda = BrDecimal::parse((string) ($item['pr_venda'] ?? $item['prc_unitario'] ?? '0'), 3);
        $ean = preg_replace('/\D/', '', (string) ($item['ean'] ?? '')) ?? '';
        $ncm = preg_replace('/\D/', '', (string) ($item['ncm'] ?? '')) ?? '';
        $unidade = mb_strtoupper(trim((string) ($item['und'] ?? 'UN')), 'UTF-8');
        $descricao = mb_strtoupper(trim((string) ($item['descricao'] ?? '')), 'UTF-8');
        $referencia = trim((string) ($item['codigo'] ?? ''));
        $grupo = trim((string) ($item['grupo'] ?? ''));

        if ($unidade === '' || $unidade === 'UND') {
            $unidade = 'UN';
        }

        if ($descricao === '' || $descricao === '—') {
            $descricao = 'PRODUTO XML '.($referencia !== '' && $referencia !== '—' ? $referencia : Product::nextCodigo());
        }

        if ($grupo === '') {
            $grupo = (string) ($defaults['grupo'] ?? 'DIVERSOS');
        }

        $payload = array_merge($defaults, [
            'codigo' => Product::nextCodigo(),
            'descricao' => $descricao,
            'grupo' => $grupo,
            'unidade' => $unidade,
            'referencia' => $referencia !== '' && $referencia !== '—' ? $referencia : null,
            'codigo_barras' => strlen($ean) >= 8 ? $ean : null,
            'ncm' => strlen($ncm) >= 8 ? substr($ncm, 0, 8) : ($defaults['ncm'] ?? '00000000'),
            'preco_compra' => $preco,
            'preco_custo' => $preco,
            'ult_compra' => $preco,
            'preco_venda' => $precoVenda > 0 ? $precoVenda : $preco,
            'ult_fornecedor_id' => $fornecedor?->id,
            'ativo' => true,
        ]);

        return Product::query()->create($payload);
    }

    public function openPesquisarProdutoXml(): void
    {
        if ($this->importarXmlItemIndex === null) {
            Notification::make()
                ->title('Selecione um item da grade')
                ->body('Clique no item antes de pesquisar o produto.')
                ->warning()
                ->send();

            return;
        }

        $item = $this->importarXmlItens[$this->importarXmlItemIndex] ?? null;
        $termo = is_array($item)
            ? trim((string) ($item['descricao'] ?? $item['codigo'] ?? ''))
            : '';

        $this->importarXmlPesquisarOpen = true;
        $this->importarXmlPesquisarTermo = $termo;
        $this->importarXmlPesquisarIndex = null;
        $this->refreshPesquisarProdutoXml();
    }

    public function closePesquisarProdutoXml(): void
    {
        $this->importarXmlPesquisarOpen = false;
        $this->importarXmlPesquisarTermo = '';
        $this->importarXmlPesquisarResultados = [];
        $this->importarXmlPesquisarIndex = null;
    }

    public function updatedImportarXmlPesquisarTermo(): void
    {
        $this->refreshPesquisarProdutoXml();
    }

    public function refreshPesquisarProdutoXml(): void
    {
        $termo = trim($this->importarXmlPesquisarTermo);

        if (mb_strlen($termo) < 2) {
            $this->importarXmlPesquisarResultados = [];
            $this->importarXmlPesquisarIndex = null;

            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $termo).'%';

        $this->importarXmlPesquisarResultados = Product::query()
            ->where('ativo', true)
            ->where(function ($query) use ($like, $termo): void {
                $query->where('codigo', 'like', $like)
                    ->orWhere('descricao', 'like', $like)
                    ->orWhere('referencia', 'like', $like)
                    ->orWhere('codigo_barras', 'like', $like)
                    ->orWhere('codigo_barras_caixa', 'like', $like);

                if (ctype_digit(preg_replace('/\D/', '', $termo) ?? '')) {
                    $digits = preg_replace('/\D/', '', $termo) ?? '';
                    if (strlen($digits) >= 8) {
                        $query->orWhere('codigo_barras', $digits)
                            ->orWhere('codigo_barras_caixa', $digits);
                    }
                }
            })
            ->orderBy('descricao')
            ->limit(50)
            ->get(['id', 'codigo', 'descricao', 'referencia', 'preco_venda', 'estoque', 'grupo'])
            ->map(static fn (Product $p): array => [
                'id' => (int) $p->id,
                'codigo' => (string) $p->codigo,
                'descricao' => (string) $p->descricao,
                'referencia' => (string) ($p->referencia ?? ''),
                'preco_venda' => number_format((float) ($p->preco_venda ?? 0), 3, ',', '.'),
                'estoque' => (string) ($p->estoque ?? '0'),
                'grupo' => (string) ($p->grupo ?? ''),
            ])
            ->all();

        $this->importarXmlPesquisarIndex = $this->importarXmlPesquisarResultados !== [] ? 0 : null;
    }

    public function selectPesquisarProdutoXml(int $index): void
    {
        if ($index < 0 || $index >= count($this->importarXmlPesquisarResultados)) {
            return;
        }

        $this->importarXmlPesquisarIndex = $index;
    }

    public function confirmPesquisarProdutoXml(): void
    {
        $index = $this->importarXmlPesquisarIndex;
        $itemIndex = $this->importarXmlItemIndex;

        if ($index === null || $itemIndex === null) {
            Notification::make()->title('Selecione um produto na lista.')->warning()->send();

            return;
        }

        $row = $this->importarXmlPesquisarResultados[$index] ?? null;
        $product = isset($row['id']) ? Product::query()->find((int) $row['id']) : null;

        if (! $product) {
            Notification::make()->title('Produto não encontrado.')->danger()->send();

            return;
        }

        $item = $this->importarXmlItens[$itemIndex] ?? null;
        $cnpj = preg_replace('/\D/', '', (string) ($this->importarXmlHeader['cnpj'] ?? '')) ?? '';
        $matcher = new NotaFornecedorXmlProdutoMatcher();
        $fornecedor = $matcher->resolveFornecedorByCnpj($cnpj);

        if ($fornecedor && is_array($item)) {
            $matcher->vincularProduto($product, $fornecedor, (string) ($item['codigo'] ?? $product->codigo));
        }

        $this->aplicarProdutoNoItemXml($itemIndex, $product);
        $this->closePesquisarProdutoXml();

        Notification::make()
            ->title('Produto vinculado')
            ->body($product->codigo.' — '.$product->descricao)
            ->success()
            ->send();
    }

    public function desvincularProdutoXmlSelecionado(): void
    {
        if ($this->importarXmlItemIndex === null) {
            Notification::make()
                ->title('Selecione um item da grade')
                ->warning()
                ->send();

            return;
        }

        $this->limparVinculoItemXml($this->importarXmlItemIndex);

        Notification::make()
            ->title('Item desvinculado')
            ->success()
            ->send();
    }

    public function desvincularTodosProdutosXml(): void
    {
        $qtd = 0;

        foreach (array_keys($this->importarXmlItens) as $index) {
            if (($this->importarXmlItens[$index]['vinculado'] ?? false) === true) {
                $this->limparVinculoItemXml((int) $index);
                $qtd++;
            }
        }

        Notification::make()
            ->title($qtd > 0 ? "{$qtd} item(ns) desvinculado(s)" : 'Nenhum vínculo para remover')
            ->success()
            ->send();
    }

    public function aplicarGrupoXmlSelecionado(): void
    {
        if ($this->importarXmlItemIndex === null) {
            Notification::make()
                ->title('Selecione um item da grade')
                ->body('Escolha o item com o grupo desejado e clique em Grupo para aplicar a todos.')
                ->warning()
                ->send();

            return;
        }

        $grupo = trim((string) ($this->importarXmlItens[$this->importarXmlItemIndex]['grupo'] ?? ''));

        if ($grupo === '') {
            Notification::make()
                ->title('Informe o grupo no item selecionado')
                ->body('Selecione um grupo na coluna Grupo e depois clique em Grupo para replicar.')
                ->warning()
                ->send();

            return;
        }

        $itens = $this->importarXmlItens;

        foreach ($itens as $index => $item) {
            $itens[$index]['grupo'] = $grupo;
        }

        $this->importarXmlItens = $itens;

        Notification::make()
            ->title('Grupo aplicado a todos os itens')
            ->body($grupo)
            ->success()
            ->send();
    }

    protected function limparVinculoItemXml(int $index): void
    {
        $item = $this->importarXmlItens[$index] ?? null;

        if (! is_array($item)) {
            return;
        }

        $cnpj = preg_replace('/\D/', '', (string) ($this->importarXmlHeader['cnpj'] ?? '')) ?? '';
        $matcher = new NotaFornecedorXmlProdutoMatcher();
        $fornecedor = $matcher->resolveFornecedorByCnpj($cnpj);

        if ($fornecedor) {
            $matcher->desvincularProduto(
                $fornecedor,
                (string) ($item['codigo'] ?? ''),
                isset($item['product_id']) ? (int) $item['product_id'] : null,
            );
        }

        $itens = $this->importarXmlItens;
        $itens[$index]['vinculado'] = false;
        $itens[$index]['product_id'] = null;
        $itens[$index]['produto_codigo'] = null;
        $itens[$index]['produto_descricao'] = null;
        $this->importarXmlItens = $itens;
    }

    public function closeProductOverlay(): void
    {
        if (! $this->overlayProductOpen) {
            return;
        }

        $this->overlayProductOpen = false;
        NotaFornecedorProductPrefill::forget();
        ErpScreen::set($this->importarXmlScreenTitle());
    }

    public function applyOverlayProdutoXmlSaved(
        int $itemIndex,
        int $produtoId,
        string $produtoCodigo = '',
        string $produtoDescricao = '',
        string $produtoGrupo = '',
        string $produtoPrecoVenda = '',
    ): void {
        $this->overlayProductOpen = false;
        ErpScreen::set($this->importarXmlScreenTitle());

        if ($itemIndex < 0 || $itemIndex >= count($this->importarXmlItens) || $produtoId <= 0) {
            return;
        }

        $product = Product::query()->find($produtoId);

        if ($product) {
            $this->aplicarProdutoNoItemXml($itemIndex, $product);

            return;
        }

        $itens = $this->importarXmlItens;
        $itens[$itemIndex]['vinculado'] = true;
        $itens[$itemIndex]['product_id'] = $produtoId;
        $itens[$itemIndex]['produto_codigo'] = $produtoCodigo;
        $itens[$itemIndex]['produto_descricao'] = $produtoDescricao;
        $itens[$itemIndex]['grupo'] = $produtoGrupo;
        if ($produtoPrecoVenda !== '') {
            $itens[$itemIndex]['pr_venda'] = number_format(BrDecimal::parse($produtoPrecoVenda, 3), 3, ',', '.');
        }
        $this->importarXmlItens = $itens;
        $this->importarXmlItemIndex = $itemIndex;
    }

    public function setImportarXmlTab(string $tab): void
    {
        $this->importarXmlTab = in_array($tab, ['detalhes', 'totais'], true) ? $tab : 'detalhes';
    }

    public function normalizarCfopItemXml(int $index): void
    {
        if (! isset($this->importarXmlItens[$index])) {
            return;
        }

        $resolver = new CfopEntradaResolver();
        $fallback = trim((string) ($this->resolveEmpresaAtivaForImportarXml()?->param_imp_cfop_compra ?? '1102'));
        $entrada = $resolver->resolve((string) ($this->importarXmlItens[$index]['cfop'] ?? ''), $fallback);

        $this->importarXmlItens[$index]['cfop'] = $entrada;

        if ($index === 0 || ($this->importarXmlHeader['cfop'] ?? '') === '—') {
            $this->importarXmlHeader['cfop'] = $entrada !== '' ? $entrada : '—';
        }
    }

    public function selecionarCfopXml(string $codigo, ?int $itemIndex = null): void
    {
        $this->aplicarCfopCodigoXml($codigo, $itemIndex);
    }

    public function recalcularQtdTotalItemXml(int $index): void
    {
        $this->recalcularLinhaItemXml($index);
    }

    public function recalcularLinhaItemXml(int $index): void
    {
        if (! isset($this->importarXmlItens[$index])) {
            return;
        }

        $emb = BrDecimal::parse((string) ($this->importarXmlItens[$index]['qtd_emb'] ?? '0'), 3);
        $unid = BrDecimal::parse((string) ($this->importarXmlItens[$index]['qtd_unid'] ?? '0'), 3);
        $preco = BrDecimal::parse((string) ($this->importarXmlItens[$index]['prc_unitario'] ?? '0'), 4);

        if ($emb < 0) {
            $emb = 0.0;
        }

        if ($unid <= 0) {
            $unid = 1.0;
        }

        if ($preco < 0) {
            $preco = 0.0;
        }

        $totalQtd = round($emb * $unid, 3);
        $totalValor = round($emb * $preco, 2);

        $this->importarXmlItens[$index]['qtd_emb'] = number_format($emb, 3, ',', '.');
        $this->importarXmlItens[$index]['qtd_unid'] = number_format($unid, 3, ',', '.');
        $this->importarXmlItens[$index]['qtd_total'] = number_format($totalQtd, 3, ',', '.');
        $this->importarXmlItens[$index]['prc_unitario'] = number_format($preco, 3, ',', '.');
        $this->importarXmlItens[$index]['valor_total'] = number_format($totalValor, 2, ',', '.');
    }

    public function aplicarCfopXmlSelecionado(): void
    {
        if ($this->importarXmlItemIndex === null) {
            Notification::make()
                ->title('Selecione um item da grade')
                ->body('Escolha o item com o CFOP desejado e clique em Aplicar CFOP.')
                ->warning()
                ->send();

            return;
        }

        $cfop = trim((string) ($this->importarXmlItens[$this->importarXmlItemIndex]['cfop'] ?? ''));
        $resolver = new CfopEntradaResolver();
        $cfop = $resolver->resolve(
            $cfop,
            trim((string) ($this->resolveEmpresaAtivaForImportarXml()?->param_imp_cfop_compra ?? '1102')),
        );

        if (! $resolver->isEntrada($cfop)) {
            Notification::make()
                ->title('CFOP de entrada inválido no item selecionado')
                ->body('Informe um CFOP iniciado em 1, 2 ou 3 antes de aplicar a todos.')
                ->warning()
                ->send();

            return;
        }

        $this->aplicarCfopCodigoXml($cfop, null, aplicarTodos: true);

        Notification::make()
            ->title('CFOP aplicado a todos os itens')
            ->body($cfop)
            ->success()
            ->send();
    }

    public function aplicarCfopHeaderEmTodosXml(): void
    {
        $cfop = trim((string) ($this->importarXmlHeader['cfop'] ?? ''));
        $resolver = new CfopEntradaResolver();
        $cfop = $resolver->resolve(
            $cfop,
            trim((string) ($this->resolveEmpresaAtivaForImportarXml()?->param_imp_cfop_compra ?? '1102')),
        );

        if (! $resolver->isEntrada($cfop) || $this->importarXmlItens === []) {
            Notification::make()
                ->title('Informe um CFOP de entrada')
                ->body('Escolha um CFOP na lista e depois aplique a todos.')
                ->warning()
                ->send();

            return;
        }

        $this->aplicarCfopCodigoXml($cfop, null, aplicarTodos: true);

        Notification::make()
            ->title('CFOP aplicado a todos os itens')
            ->body($cfop)
            ->success()
            ->send();
    }

    protected function aplicarCfopCodigoXml(string $codigo, ?int $itemIndex = null, bool $aplicarTodos = false): void
    {
        $resolver = new CfopEntradaResolver();
        $fallback = trim((string) ($this->resolveEmpresaAtivaForImportarXml()?->param_imp_cfop_compra ?? '1102'));
        $codigo = $resolver->resolve($codigo, $fallback);

        if ($aplicarTodos) {
            $itens = $this->importarXmlItens;

            foreach ($itens as $i => $item) {
                $itens[$i]['cfop'] = $codigo;
            }

            $this->importarXmlItens = $itens;
            $this->importarXmlHeader['cfop'] = $codigo;

            return;
        }

        if ($itemIndex !== null && isset($this->importarXmlItens[$itemIndex])) {
            $this->importarXmlItens[$itemIndex]['cfop'] = $codigo;
            $this->importarXmlItemIndex = $itemIndex;

            // Cabeçalho só acompanha o 1º item (ou quando ainda vazio).
            if ($itemIndex === 0 || ($this->importarXmlHeader['cfop'] ?? '') === '—') {
                $this->importarXmlHeader['cfop'] = $codigo;
            }

            return;
        }

        $this->importarXmlHeader['cfop'] = $codigo;
    }

    /**
     * Normaliza CFOPs dos itens para entrada (1/2/3). Retorna mensagem de erro ou null.
     */
    protected function normalizarTodosCfopImportarXml(): ?string
    {
        $resolver = new CfopEntradaResolver();
        $fallback = trim((string) ($this->resolveEmpresaAtivaForImportarXml()?->param_imp_cfop_compra ?? '1102'));
        $itens = $this->importarXmlItens;

        foreach ($itens as $index => $item) {
            $entrada = $resolver->resolve((string) ($item['cfop'] ?? ''), $fallback);
            $itens[$index]['cfop'] = $entrada;

            if (! $resolver->isEntrada($entrada)) {
                $linha = $index + 1;
                $informado = trim((string) ($item['cfop'] ?? ''));

                return "Item {$linha}: informe um CFOP de entrada (iniciado em 1, 2 ou 3)"
                    .($informado !== '' ? " — informado: {$informado}." : '.');
            }
        }

        $this->importarXmlItens = $itens;

        if ($itens !== []) {
            $primeiro = (string) ($itens[0]['cfop'] ?? '');
            $this->importarXmlHeader['cfop'] = $primeiro !== '' ? $primeiro : '—';
        }

        return null;
    }

    #[Computed]
    public function productOverlayUrl(): string
    {
        return ProductResource::getUrl('create').'?nota_fornecedor=1';
    }

    protected function aplicarProdutoNoItemXml(int $index, Product $product): void
    {
        $itens = $this->importarXmlItens;
        $itens[$index]['vinculado'] = true;
        $itens[$index]['product_id'] = (int) $product->id;
        $itens[$index]['produto_codigo'] = (string) $product->codigo;
        $itens[$index]['produto_descricao'] = (string) $product->descricao;
        $itens[$index]['grupo'] = filled($product->grupo) ? (string) $product->grupo : '';
        $itens[$index]['pr_venda'] = number_format((float) ($product->preco_venda ?? 0), 3, ',', '.');
        $this->importarXmlItens = $itens;
        $this->importarXmlItemIndex = $index;
    }

    protected function populateImportarXmlModal(NotaFornecedor $nota): void
    {
        $nota->refresh();
        $xml = (new NotaFornecedorDanfeReportService())->resolveXml($nota);
        $parsed = filled($xml) ? (new NotaFornecedorDanfeReportService())->parseXml((string) $xml) : null;

        if ($parsed === null || ($parsed['itens'] ?? []) === []) {
            $this->notifyImportarXml(
                'XML sem itens para leitura',
                'O XML completo desta NF-e ainda não está disponível. '
                .'Confirme a nota (F4) dentro do prazo de 10 dias, solicite o procNFe ao fornecedor '
                .'ou use Buscar XML para carregar o arquivo.',
                null,
                'warning',
            );

            return;
        }

        $emitente = $parsed['emitente'] ?? [];
        $cfopResolver = new CfopEntradaResolver();
        $cfopFallback = trim((string) ($this->resolveEmpresaAtivaForImportarXml()?->param_imp_cfop_compra ?? '1102'));
        $cfop = $cfopResolver->resolve((string) (($parsed['itens'][0]['cfop'] ?? '') ?: ''), $cfopFallback);
        $cnpjFornecedor = (string) ($nota->cnpj ?: ($emitente['cnpj'] ?? ''));

        $cadastro = (new NotaFornecedorFornecedorCadastro())->ensure($emitente);

        $this->importarXmlNotaId = (int) $nota->id;
        $chaveNota = preg_replace('/\D/', '', (string) $nota->chave) ?? '';
        if (strlen($chaveNota) !== 44) {
            $chaveNota = preg_replace('/\D/', '', (string) ($parsed['chave'] ?? '')) ?? '';
        }

        $this->importarXmlHeader = [
            'chave' => $chaveNota,
            'data_entrada' => $nota->data_entrada?->format('d/m/Y') ?? ($parsed['data_entrada'] ?? '—'),
            'data_emissao' => $nota->data_emissao?->format('d/m/Y') ?? ($parsed['data_emissao'] ?? '—'),
            'cfop' => $cfop !== '' ? $cfop : '—',
            'fornecedor' => $nota->nome ?: ($emitente['nome'] ?? '—'),
            'cnpj' => $this->formatCnpjDisplayForImportarXml($cnpjFornecedor),
            'uf' => (string) ($emitente['uf'] ?? '—'),
            'numero' => (string) ($nota->numero ?: ($parsed['numero'] ?? '—')),
            'fornecedor_status' => $cadastro['status'],
            'fornecedor_status_label' => $cadastro['label'],
        ];

        $itensBase = array_values(array_map(static function (array $item) use ($cfopResolver, $cfopFallback): array {
            $preco = number_format(BrDecimal::parse((string) ($item['valor_unit'] ?? '0'), 3), 3, ',', '.');
            // qCom do XML = quantidade comercial da nota → Qtd. Emb.
            $qtdEmbNum = BrDecimal::parse((string) ($item['quant'] ?? '0'), 3);
            $precoNum = BrDecimal::parse((string) ($item['valor_unit'] ?? '0'), 4);
            $qtdEmb = number_format($qtdEmbNum, 3, ',', '.');
            $qtdUnid = '1,000';
            $qtdTotal = $qtdEmb;
            $cfopXml = (string) ($item['cfop'] ?? '');
            $valorTotal = number_format(round($qtdEmbNum * $precoNum, 2), 2, ',', '.');

            return [
                'codigo' => (string) ($item['codigo'] ?? '—'),
                'ean' => (string) ($item['ean'] ?? ''),
                'descricao' => (string) ($item['descricao'] ?? '—'),
                'grupo' => '',
                'qtd_emb' => $qtdEmb,
                'qtd_unid' => $qtdUnid,
                'qtd_total' => $qtdTotal,
                'und' => (string) ($item['un'] ?? 'UN'),
                'prc_unitario' => $preco,
                'pr_venda' => $preco,
                'cfop_xml' => $cfopXml,
                'cfop' => $cfopResolver->resolve($cfopXml, $cfopFallback),
                'ncm' => (string) ($item['ncm'] ?? ''),
                'cst' => (string) ($item['cst'] ?? ''),
                'valor_total' => $valorTotal,
                'vinculado' => false,
                'product_id' => null,
                'produto_codigo' => null,
                'produto_descricao' => null,
            ];
        }, $parsed['itens']));

        $this->importarXmlItens = (new NotaFornecedorXmlProdutoMatcher())->matchItens($itensBase, $cnpjFornecedor);
        $this->importarXmlTotais = $parsed['totais'] ?? [];
        $this->importarXmlItemIndex = null;
        $this->importarXmlTab = 'detalhes';
        $this->importarXmlFecharConfirmOpen = false;
        $this->importarXmlModalOpen = true;
    }

    protected function resolveNotaFornecedorFromCompra(Compra $compra): ?NotaFornecedor
    {
        $chave = preg_replace('/\D/', '', (string) $compra->chave_nfe) ?? '';

        $nota = NotaFornecedor::query()
            ->where('compra_id', $compra->id)
            ->first();

        if (! $nota && strlen($chave) === 44) {
            $nota = NotaFornecedor::query()
                ->when($compra->empresa_id, fn ($q) => $q->where('empresa_id', $compra->empresa_id))
                ->where('chave', $chave)
                ->first();
        }

        if ($nota) {
            if ((int) ($nota->compra_id ?? 0) !== (int) $compra->id) {
                $nota->forceFill(['compra_id' => $compra->id])->saveQuietly();
            }

            if ($nota->status === NotaFornecedor::STATUS_PENDENTE) {
                $nota->forceFill(['status' => NotaFornecedor::STATUS_ACEITA])->saveQuietly();
            }

            return $nota->fresh() ?? $nota;
        }

        if (strlen($chave) !== 44) {
            return null;
        }

        $empresa = $this->resolveEmpresaAtivaForImportarXml();
        $fornecedor = $compra->fornecedor;
        $cnpj = preg_replace('/\D/', '', (string) ($fornecedor?->cpf_cnpj ?? '')) ?? '';

        return NotaFornecedor::query()->create([
            'empresa_id' => $empresa?->id,
            'data_entrada' => $compra->data_entrada ?? $compra->data_emissao,
            'data_emissao' => $compra->data_emissao,
            'numero' => (string) ($compra->numero_nota ?: $compra->numero),
            'chave' => $chave,
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'nome' => $fornecedor?->nome_razao ?: $fornecedor?->apelido_fantasia,
            'total' => $compra->total,
            'status' => NotaFornecedor::STATUS_ACEITA,
            'compra_id' => $compra->id,
        ]);
    }

    protected function formatCnpjDisplayForImportarXml(string $cnpj): string
    {
        $digits = preg_replace('/\D/', '', $cnpj) ?? '';

        if (strlen($digits) !== 14) {
            return $cnpj !== '' ? $cnpj : '—';
        }

        return substr($digits, 0, 2).'.'
            .substr($digits, 2, 3).'.'
            .substr($digits, 5, 3).'/'
            .substr($digits, 8, 4).'-'
            .substr($digits, 12, 2);
    }

    protected function resolveEmpresaAtivaForImportarXml(): ?Empresa
    {
        if (method_exists($this, 'resolveEmpresaAtiva')) {
            /** @var Empresa|null $empresa */
            $empresa = $this->resolveEmpresaAtiva();

            return $empresa;
        }

        $empresaId = ErpContext::currentEmpresaId();

        return $empresaId
            ? Empresa::query()->whereKey($empresaId)->where('ativo', true)->first()
            : null;
    }

    protected function importarXmlScreenTitle(): string
    {
        return 'Notas de Fornecedores';
    }

    protected function notifyImportarXml(
        string $titulo,
        string $mensagem,
        ?string $codigo = null,
        string $tone = 'error',
    ): void {
        if (method_exists($this, 'showNfFornFiscalOverlay')) {
            $this->showNfFornFiscalOverlay(
                $titulo,
                $mensagem,
                $codigo,
                'Ler XML',
                $tone,
            );

            return;
        }

        $notification = Notification::make()
            ->title($titulo)
            ->body($mensagem);

        match ($tone) {
            'warning' => $notification->warning()->send(),
            'info' => $notification->info()->send(),
            default => $notification->danger()->send(),
        };
    }

    protected function parseImportarXmlDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '' || $value === '—') {
            return null;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($value), $m) === 1) {
            return $m[3].'-'.$m[2].'-'.$m[1];
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseImportarXmlMoney(mixed $value): ?float
    {
        if ($value === null || $value === '' || $value === '—') {
            return 0.0;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        return BrDecimal::tryParse((string) $value, 2);
    }
}
