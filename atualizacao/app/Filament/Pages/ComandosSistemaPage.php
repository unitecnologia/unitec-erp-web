<?php

namespace App\Filament\Pages;

use App\Support\Erp\Import\ProdutosPlanilhaImportService;
use App\Support\Erp\ComandosSistemaService;
use App\Support\Erp\ErpAccess;
use App\Support\Erp\ErpScreen;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Throwable;

class ComandosSistemaPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?string $title = '';

    protected static ?string $slug = 'comandos-sistema';

    protected static bool $shouldRegisterNavigation = false;

    /** @var list<array{label: string, value: string}> */
    public array $info = [];

    public string $feedbackMsg = '';

    public string $feedbackTipo = 'ok';

    public bool $busy = false;

    public string $foco = 'info';

    public string $importArquivo = '';

    /** @var array<string, mixed>|null */
    public ?array $importResumo = null;

    public bool $importConfirmacaoAberta = false;

    /** @var list<string> */
    public array $importCamposAtualizar = [];

    public bool $importZerarTabela = false;

    /**
     * @return list<string>
     */
    public array $importArquivosDisponiveis = [];

    public function mount(): void
    {
        ErpScreen::set('Comandos do Sistema');

        $foco = (string) request()->query('foco', 'info');
        $this->foco = in_array($foco, ['cache', 'aquecer', 'importar', 'info'], true) ? $foco : 'info';
        $this->refreshInfo();
        $this->refreshImportArquivos();
    }

    public static function canAccess(): bool
    {
        return ErpAccess::currentCan('comandos.access');
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'erp-comandos-sistema-page',
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->gap(false)
            ->components([
                View::make('filament.components.erp.comandos.screen'),
            ]);
    }

    public function limparCache(ComandosSistemaService $service): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'comandos.clear_cache')) {
            return;
        }

        if ($this->busy) {
            return;
        }

        $this->busy = true;
        $this->clearFeedback();

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }

            $result = $service->limparCache();
            $this->refreshInfo();
            $this->setFeedback($result['ok'] ? 'ok' : 'erro', $result['message']);

            $notification = Notification::make()
                ->title($result['ok'] ? 'Cache limpo' : 'Falha ao limpar cache')
                ->body($result['message']);

            if ($result['ok']) {
                $notification->success();
            } else {
                $notification->danger();
            }

            $notification->send();
        } catch (Throwable $e) {
            report($e);
            $this->setFeedback('erro', $e->getMessage());
            Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
        } finally {
            $this->busy = false;
        }
    }

    public function aquecerSistema(ComandosSistemaService $service): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'comandos.warm')) {
            return;
        }

        if ($this->busy) {
            return;
        }

        $this->busy = true;
        $this->clearFeedback();

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(120);
            }

            $result = $service->aquecerSistema();
            $this->refreshInfo();
            $this->setFeedback($result['ok'] ? 'ok' : 'erro', $result['message']);

            $notification = Notification::make()
                ->title($result['ok'] ? 'Sistema aquecido' : 'Aquecimento incompleto')
                ->body($result['message']);

            if ($result['ok']) {
                $notification->success();
            } else {
                $notification->warning();
            }

            $notification->send();
        } catch (Throwable $e) {
            report($e);
            $this->setFeedback('erro', $e->getMessage());
            Notification::make()->title('Erro')->body($e->getMessage())->danger()->send();
        } finally {
            $this->busy = false;
        }
    }

    public function analisarImportacao(ProdutosPlanilhaImportService $service): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'comandos.import_data')) {
            return;
        }

        $this->clearFeedback();
        $arquivo = trim($this->importArquivo);

        if ($arquivo === '') {
            $this->setFeedback('erro', 'Selecione uma planilha da pasta importar.');

            return;
        }

        try {
            $this->importResumo = $service->analisar($this->importPath($arquivo), $this->importCamposAtualizar, $this->importZerarTabela);
            $this->importConfirmacaoAberta = false;
            $this->setFeedback('info', 'Planilha analisada. Revise a prévia e confirme a criação ou atualização dos produtos.');
        } catch (Throwable $e) {
            report($e);
            $this->importResumo = null;
            $this->setFeedback('erro', $e->getMessage());
        }
    }

    public function confirmarImportacao(ProdutosPlanilhaImportService $service): void
    {
        if (! ErpAccess::authorizeOrNotify(Auth::user(), 'comandos.import_data')) {
            return;
        }

        if ($this->importResumo === null || (($this->importResumo['novos'] ?? 0) <= 0 && ($this->importResumo['atualizaveis'] ?? 0) <= 0)) {
            $this->setFeedback('erro', 'Analise uma planilha com produtos novos ou selecione campos para atualizar existentes.');

            return;
        }

        $this->busy = true;
        $this->clearFeedback();

        try {
            if (function_exists('set_time_limit')) {
                @set_time_limit(600);
            }

            $result = $service->importar($this->importPath($this->importArquivo), $this->importCamposAtualizar, $this->importZerarTabela);
            $this->importResumo = null;
            $this->importConfirmacaoAberta = false;
            $this->refreshInfo();

            $message = sprintf(
                '%d produtos criados. %d produtos atualizados. %d existentes sem alteração; %d estoques negativos foram zerados.',
                $result['importados'],
                $result['atualizados'],
                $result['existentes'],
                $result['estoque_negativo_zerado'],
            );

            $this->setFeedback('ok', $message);
            Notification::make()->title('Importação concluída')->body($message)->success()->send();
        } catch (Throwable $e) {
            report($e);
            $this->setFeedback('erro', $e->getMessage());
            Notification::make()->title('Falha na importação')->body($e->getMessage())->danger()->send();
        } finally {
            $this->busy = false;
        }
    }

    public function toggleConfirmacaoImportacao(): void
    {
        $this->importConfirmacaoAberta = ! $this->importConfirmacaoAberta;
    }

    public function marcarTodosCamposImportacao(): void
    {
        $this->importCamposAtualizar = ProdutosPlanilhaImportService::camposAtualizaveis();
        $this->importResumo = null;
        $this->importConfirmacaoAberta = false;
    }

    public function desmarcarTodosCamposImportacao(): void
    {
        $this->importCamposAtualizar = [];
        $this->importResumo = null;
        $this->importConfirmacaoAberta = false;
    }

    public function updatedImportCamposAtualizar(): void
    {
        $this->importResumo = null;
        $this->importConfirmacaoAberta = false;
    }

    public function updatedImportZerarTabela(): void
    {
        $this->importResumo = null;
        $this->importConfirmacaoAberta = false;
    }

    public function refreshInfo(): void
    {
        $this->info = app(ComandosSistemaService::class)->infoSistema();
    }

    public function dismissFeedback(): void
    {
        $this->clearFeedback();
    }

    public function closeScreen(): void
    {
        ErpScreen::set('Principal');
        $this->redirect(filament()->getUrl());
    }

    protected function setFeedback(string $tipo, string $message): void
    {
        $this->feedbackTipo = in_array($tipo, ['ok', 'erro', 'info'], true) ? $tipo : 'info';
        $this->feedbackMsg = $message;
    }

    protected function clearFeedback(): void
    {
        $this->feedbackMsg = '';
        $this->feedbackTipo = 'ok';
    }

    public function refreshImportArquivos(): void
    {
        $files = glob(base_path('importar/*.{xlsx,XLSX}'), GLOB_BRACE) ?: [];

        $this->importArquivosDisponiveis = array_values(array_map(
            static fn (string $path): string => basename($path),
            $files,
        ));

        if ($this->importArquivo !== '' && ! in_array($this->importArquivo, $this->importArquivosDisponiveis, true)) {
            $this->importArquivo = '';
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function importGruposCampos(): array
    {
        return collect(ProdutosPlanilhaImportService::gruposCamposAtualizaveis())
            ->map(static fn (array $campos): array => collect($campos)
                ->mapWithKeys(static fn (string $campo): array => [$campo => self::labelCampoImportacao($campo)])
                ->all())
            ->all();
    }

    private static function labelCampoImportacao(string $campo): string
    {
        return match ($campo) {
            'descricao' => 'Descrição',
            'referencia' => 'Referência',
            'codigo_barras' => 'Código de barras',
            'codigo_barras_caixa' => 'Código de barras da caixa',
            'tipo_produto' => 'Tipo de produto',
            'preco_compra' => 'Preço de compra',
            'ult_compra' => 'Última compra',
            'ult_compra_anterior' => 'Última compra anterior',
            'pct_custos' => '% de custos',
            'preco_custo' => 'Preço de custo',
            'preco_custo_anterior' => 'Preço de custo anterior',
            'e_medio' => 'Estoque médio',
            'pct_lucro' => '% de lucro',
            'preco_venda' => 'Preço de venda',
            'preco_venda_anterior' => 'Preço de venda anterior',
            'qtd_atacado' => 'Quantidade no atacado',
            'preco_atacado' => 'Preço no atacado',
            'preco_especial' => 'Preço especial',
            'estoque_minimo' => 'Estoque mínimo',
            'peso_kg' => 'Peso (kg)',
            'info_adicionais' => 'Informações adicionais',
            'ncm_descricao' => 'Descrição do NCM',
            'cfop_interno' => 'CFOP interno',
            'cst_icms' => 'CST ICMS',
            'aliq_icms' => 'Alíquota de ICMS',
            'cfop_externo' => 'CFOP externo',
            'cst_externo' => 'CST externo',
            'csosn_externo' => 'CSOSN externo',
            'aliq_icms_externo' => 'Alíquota de ICMS externo',
            'cst_entrada' => 'CST de entrada',
            'cst_saida' => 'CST de saída',
            'cst_cofins' => 'CST COFINS',
            'aliq_pis' => 'Alíquota de PIS',
            'aliq_cofins' => 'Alíquota de COFINS',
            'cst_ipi' => 'CST IPI',
            'cod_enq_ipi' => 'Código de enquadramento IPI',
            'aliq_ipi' => 'Alíquota de IPI',
            'fcp_pct' => '% FCP',
            'mva_pct' => '% MVA',
            'mva_normal' => 'MVA normal',
            'reducao_base_pct' => '% redução da base',
            'icms_diferido' => 'ICMS diferido',
            'aliq_deson' => 'Alíquota desoneração',
            'motivo_desoneracao' => 'Motivo da desoneração',
            'tipo_tributacao' => 'Tipo de tributação',
            'cod_beneficio' => 'Código de benefício fiscal',
            'iva_cst' => 'IVA CST',
            'cclass_trib' => 'Classificação tributária',
            'cclass_trib_descricao' => 'Descrição da classificação tributária',
            'aliq_ibs_uf' => 'Alíquota IBS UF',
            'aliq_cbs' => 'Alíquota CBS',
            'aliq_ibs_mun' => 'Alíquota IBS município',
            'aliq_adrem_ibs' => 'Alíquota ad rem IBS',
            'aliq_adrem_cbs' => 'Alíquota ad rem CBS',
            'reducao_cbs' => '% redução CBS',
            'reducao_ibs' => '% redução IBS',
            'tributacao_monofasica' => 'Tributação monofásica',
            'glp_pct' => '% GLP',
            'gnn_pct' => '% GNN',
            'gni_pct' => '% GNI',
            'peso_liq' => 'Peso líquido',
            'anp_code' => 'Código ANP',
            'issqn' => 'ISSQN',
            'prefixo_balanca' => 'Prefixo da balança',
            'produto_pesado' => 'Produto pesado',
            'tem_info_nutricional' => 'Possui informação nutricional',
            'nutri_porcao_qtd' => 'Nutrição: quantidade por porção',
            'nutri_porcao_unidade' => 'Nutrição: unidade da porção',
            'nutri_medida_inteiro' => 'Nutrição: medida inteira',
            'nutri_medida_fracao' => 'Nutrição: medida fracionada',
            'nutri_medida_tipo' => 'Nutrição: tipo de medida',
            'nutri_valor_energetico' => 'Nutrição: valor energético',
            'nutri_carboidratos' => 'Nutrição: carboidratos',
            'nutri_proteinas' => 'Nutrição: proteínas',
            'nutri_gorduras_totais' => 'Nutrição: gorduras totais',
            'nutri_gorduras_saturadas' => 'Nutrição: gorduras saturadas',
            'nutri_gorduras_trans' => 'Nutrição: gorduras trans',
            'nutri_fibra' => 'Nutrição: fibra alimentar',
            'nutri_sodio' => 'Nutrição: sódio',
            'is_restaurante' => 'Produto de restaurante',
            'tipo_restaurante' => 'Tipo de restaurante',
            'menu_id' => 'Cardápio',
            'tipo_alimento' => 'Tipo de alimento',
            'qtd_sabores' => 'Quantidade de sabores',
            'valor_pequena' => 'Valor tamanho pequeno',
            'valor_media' => 'Valor tamanho médio',
            'valor_grande' => 'Valor tamanho grande',
            'complemento' => 'Permite complemento',
            'tempo_espera' => 'Tempo de espera',
            'is_remedio' => 'Produto é remédio',
            'principio_ativo_id' => 'Princípio ativo',
            'aplicacao' => 'Aplicação',
            'is_composicao' => 'É composição',
            'is_servico' => 'É serviço',
            'is_grade' => 'Usa grade',
            'usa_tab_preco' => 'Usa tabela de preço',
            'is_combustivel' => 'É combustível',
            'usa_imei' => 'Usa IMEI',
            'contr_est_grade' => 'Controla estoque por grade',
            'mostrar_no_app' => 'Mostrar no aplicativo',
            'promo_data_inicio' => 'Promoção: data inicial',
            'promo_data_fim' => 'Promoção: data final',
            'promo_preco_venda' => 'Promoção: preço de venda',
            'promo_preco_atacado' => 'Promoção: preço de atacado',
            default => ucfirst(str_replace('_', ' ', $campo)),
        };
    }

    private function importPath(string $arquivo): string
    {
        $arquivo = basename(trim($arquivo));

        if (! in_array($arquivo, $this->importArquivosDisponiveis, true)) {
            throw new \RuntimeException('Arquivo não encontrado na pasta importar.');
        }

        return base_path('importar'.DIRECTORY_SEPARATOR.$arquivo);
    }
}
