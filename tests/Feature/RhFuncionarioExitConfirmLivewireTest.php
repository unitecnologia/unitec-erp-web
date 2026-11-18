<?php

namespace Tests\Feature;

use App\Filament\Resources\RhFuncionarioResource\Pages\Concerns\ManagesRhFuncionarioFormModal;
use App\Support\Erp\Rh\RhFuncionarioFormSnapshot;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Exercita requestClose / confirm / dismiss sem montar a ListRecords completa.
 */
class RhFuncionarioExitConfirmLivewireTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('empresas');
        Schema::create('empresas', function (Blueprint $table): void {
            $table->id();
            $table->string('nome')->nullable();
            $table->timestamps();
        });
        DB::table('empresas')->insert(['nome' => 'EMP TESTE', 'created_at' => now(), 'updated_at' => now()]);
        Cache::forget('erp.empresa.exists');
    }

    public function test_sem_alteracao_sair_fecha_direto(): void
    {
        $form = $this->formBase();
        $harness = $this->novoHarness($form, RhFuncionarioFormSnapshot::hash($form));

        $harness->call('requestCloseRhFuncionarioModal')
            ->assertSet('rhFuncionarioModalOpen', false)
            ->assertSet('rhFuncionarioExitConfirmOpen', false);
    }

    public function test_com_alteracao_sair_abre_confirmacao_e_preserva_dados(): void
    {
        $form = $this->formBase();
        $hash = RhFuncionarioFormSnapshot::hash($form);
        $form['nome'] = 'MARIA SILVA';

        $harness = $this->novoHarness($form, $hash);
        $harness->call('requestCloseRhFuncionarioModal')
            ->assertSet('rhFuncionarioModalOpen', true)
            ->assertSet('rhFuncionarioExitConfirmOpen', true)
            ->assertSet('rhFuncionarioForm.nome', 'MARIA SILVA');

        $harness->call('dismissRhFuncionarioExitConfirm')
            ->assertSet('rhFuncionarioModalOpen', true)
            ->assertSet('rhFuncionarioExitConfirmOpen', false)
            ->assertSet('rhFuncionarioForm.nome', 'MARIA SILVA')
            ->assertDispatched('erp-rh-func-focus-form');
    }

    public function test_sair_sem_salvar_fecha_e_descarta(): void
    {
        $form = $this->formBase();
        $hash = RhFuncionarioFormSnapshot::hash($form);
        $form['nome'] = 'DESCARTAR';

        $harness = $this->novoHarness($form, $hash);
        $harness->call('requestCloseRhFuncionarioModal')
            ->call('confirmRhFuncionarioExitWithoutSaving')
            ->assertSet('rhFuncionarioModalOpen', false)
            ->assertSet('rhFuncionarioExitConfirmOpen', false)
            ->assertSet('rhFuncionarioForm.nome', '');
    }

    public function test_esc_com_confirmacao_aberta_continua_editando(): void
    {
        $form = $this->formBase();
        $hash = RhFuncionarioFormSnapshot::hash($form);
        $form['cpf'] = '123';

        $harness = $this->novoHarness($form, $hash);
        $harness->set('rhFuncionarioExitConfirmOpen', true)
            ->call('requestCloseRhFuncionarioModal')
            ->assertSet('rhFuncionarioExitConfirmOpen', false)
            ->assertSet('rhFuncionarioModalOpen', true)
            ->assertSet('rhFuncionarioForm.cpf', '123');
    }

    /**
     * @return array<string, mixed>
     */
    private function formBase(): array
    {
        return [
            'codigo' => '10',
            'nome' => '',
            'cpf' => '',
            'rg' => '',
            'pis_pasep' => '',
            'data_nascimento' => '',
            'telefone' => '',
            'whatsapp' => '',
            'email' => '',
            'cep' => '',
            'logradouro' => '',
            'endereco' => '',
            'numero' => '',
            'bairro' => '',
            'complemento' => '',
            'cidade_codigo' => '',
            'cidade_nome' => '',
            'uf' => '',
            'cargo_id' => '',
            'departamento_id' => '',
            'ctps' => '',
            'inss' => '',
            'tipo_salario' => '',
            'salario' => '',
            'data_admissao' => '',
            'data_demissao' => '',
            'ativo' => true,
            'eh_operador' => false,
            'usuario_id' => '',
            'terminais' => [],
            'estoque_id' => '',
            'entregador' => true,
            'setor_vendas' => true,
            'tabela_venda_id' => '',
            'comissao_av' => '0,00',
            'comissao_ap' => '0,00',
            'mobile_meta_venda' => '0,00',
            'ganha_comissao_todas_vendas' => false,
            'setor_servicos' => false,
            'comissao_servico' => '0,00',
            'ganha_comissao_todos_servicos' => false,
            'observacoes_operador' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $form
     */
    private function novoHarness(array $form, string $baselineHash)
    {
        return Livewire::test(RhFuncionarioExitHarness::class, [
            'rhFuncionarioForm' => $form,
            'rhFuncionarioFormBaselineHash' => $baselineHash,
            'rhFuncionarioModalOpen' => true,
        ]);
    }
}

class RhFuncionarioExitHarness extends Component
{
    use ManagesRhFuncionarioFormModal;

    public function mount(
        array $rhFuncionarioForm,
        string $rhFuncionarioFormBaselineHash,
        bool $rhFuncionarioModalOpen = true,
    ): void {
        $this->rhFuncionarioForm = $rhFuncionarioForm;
        $this->rhFuncionarioFormBaselineHash = $rhFuncionarioFormBaselineHash;
        $this->rhFuncionarioModalOpen = $rhFuncionarioModalOpen;
        $this->rhCargoOptions = [];
        $this->rhDepartamentoOptions = [];
    }

    public function render(): string
    {
        return '<div></div>';
    }

    protected function highlightedRecordIdOrNotify(string $action): ?int
    {
        return null;
    }

    public function resetTable(): void
    {
        //
    }

    /** Evita consultas de estoque/tabela no fechamento do harness. */
    protected function blankRhFuncionarioForm(): array
    {
        return [
            'codigo' => '',
            'nome' => '',
            'cpf' => '',
            'rg' => '',
            'pis_pasep' => '',
            'data_nascimento' => '',
            'telefone' => '',
            'whatsapp' => '',
            'email' => '',
            'cep' => '',
            'logradouro' => '',
            'endereco' => '',
            'numero' => '',
            'bairro' => '',
            'complemento' => '',
            'cidade_codigo' => '',
            'cidade_nome' => '',
            'uf' => '',
            'cargo_id' => '',
            'departamento_id' => '',
            'ctps' => '',
            'inss' => '',
            'tipo_salario' => '',
            'salario' => '',
            'data_admissao' => '',
            'data_demissao' => '',
            'ativo' => true,
            'eh_operador' => false,
            'usuario_id' => '',
            'terminais' => [],
            'estoque_id' => '',
            'entregador' => true,
            'setor_vendas' => true,
            'tabela_venda_id' => '',
            'comissao_av' => '0,00',
            'comissao_ap' => '0,00',
            'mobile_meta_venda' => '0,00',
            'ganha_comissao_todas_vendas' => false,
            'setor_servicos' => false,
            'comissao_servico' => '0,00',
            'ganha_comissao_todos_servicos' => false,
            'observacoes_operador' => '',
        ];
    }
}
