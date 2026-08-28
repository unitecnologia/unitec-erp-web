<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Models\ContaReceber;
use App\Models\Person;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaReceberCadastroService;
use App\Support\Erp\Financeiro\ContaReceberExclusaoService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaReceberFormModal
{
    public bool $contaFormModalOpen = false;

    public ?int $contaFormRecordId = null;

    public string $contaFormNumero = '';

    public string $contaFormEmissao = '';

    public string $contaFormForma = ContaReceber::FORMA_CARTEIRA;

    public string $contaFormDocumento = '';

    public string $contaFormEmpresa = '';

    public string $contaFormClienteId = '';

    public string $contaFormClienteBusca = '';

    public bool $contaFormClienteLookupOpen = false;

    /** @var array<int, array{id: int, codigo: string, nome: string, cpf_cnpj: string}> */
    public array $contaFormClienteResults = [];

    public ?int $contaFormClienteIndex = null;

    public string $contaFormVencimento = '';

    public string $contaFormHistorico = '';

    public string $contaFormValor = '0,00';

    public string $contaFormParcelas = '1';

    public function createConta(): void
    {
        if ($this->contaFormModalOpen) {
            return;
        }

        $this->fillContaFormForCreate();
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
        $this->dispatch('erp-masks-refresh');
    }

    public function editConta(): void
    {
        if ($this->contaFormModalOpen) {
            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $conta = ContaReceber::query()
            ->with('cliente')
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta) {
            Notification::make()
                ->title('Conta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if ((float) $conta->valor_recebido > 0) {
            Notification::make()
                ->title('Conta já possui baixa')
                ->body('Estorne o recebimento antes de alterar o título.')
                ->warning()
                ->send();

            return;
        }

        $exclusao = app(ContaReceberExclusaoService::class);

        if (! $exclusao->podeExcluir($conta)) {
            Notification::make()
                ->title('Não é possível alterar')
                ->body($exclusao->motivoBloqueio($conta) ?? 'Esta conta não é um lançamento avulso.')
                ->warning()
                ->send();

            return;
        }

        $this->fillContaFormFromRecord($conta);
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
        $this->dispatch('erp-masks-refresh');
    }

    public function closeContaFormModal(): void
    {
        $this->contaFormModalOpen = false;
        $this->contaFormRecordId = null;
        $this->closeContaFormClienteLookup();
        $this->resetErrorBag();
    }

    public function handleContaFormEscape(): void
    {
        if ($this->contaFormClienteLookupOpen) {
            $this->closeContaFormClienteLookup();

            return;
        }

        $this->closeContaFormModal();
    }

    public function updatedContaFormClienteBusca(): void
    {
        $upper = mb_strtoupper(trim($this->contaFormClienteBusca), 'UTF-8');
        $this->contaFormClienteBusca = $upper;
        $this->contaFormClienteId = '';
        $this->contaFormClienteLookupOpen = true;
        $this->refreshContaFormClienteResults();
    }

    public function openContaFormClienteLookup(): void
    {
        $this->contaFormClienteLookupOpen = true;

        if (filled(trim($this->contaFormClienteBusca))) {
            $this->refreshContaFormClienteResults();
        }
    }

    public function refreshContaFormClienteResults(): void
    {
        $term = trim($this->contaFormClienteBusca);

        if ($term === '') {
            $this->contaFormClienteResults = [];
            $this->contaFormClienteIndex = null;

            return;
        }

        $this->contaFormClienteResults = $this->searchContaFormClientes($term);
        $this->contaFormClienteIndex = $this->contaFormClienteResults === [] ? null : 0;
    }

    /**
     * @return array<int, array{id: int, codigo: string, nome: string, cpf_cnpj: string}>
     */
    protected function searchContaFormClientes(string $term): array
    {
        $like = '%'.$term.'%';
        $digits = preg_replace('/\D/', '', $term) ?? '';

        $query = Person::query()
            ->where('ativo', true)
            ->where('is_cliente', true)
            ->where(function ($sub) use ($like, $digits): void {
                $sub->where('nome_razao', 'like', $like)
                    ->orWhere('apelido_fantasia', 'like', $like)
                    ->orWhere('codigo', 'like', $like)
                    ->orWhere('cpf_cnpj', 'like', $like);

                if (strlen($digits) >= 2) {
                    $sub->orWhereRaw(
                        "replace(replace(replace(replace(cpf_cnpj, '.', ''), '-', ''), '/', ''), ' ', '') like ?",
                        ['%'.$digits.'%']
                    );
                }
            });

        return $query
            ->orderBy('nome_razao')
            ->limit(50)
            ->get()
            ->map(fn (Person $person): array => [
                'id' => (int) $person->id,
                'codigo' => (string) ($person->codigo ?? ''),
                'nome' => mb_strtoupper((string) $person->nome_razao, 'UTF-8'),
                'cpf_cnpj' => (string) ($person->cpf_cnpj ?? ''),
            ])
            ->all();
    }

    public function moveContaFormClienteSelection(int $delta): void
    {
        if ($this->contaFormClienteResults === []) {
            return;
        }

        $index = ($this->contaFormClienteIndex ?? 0) + $delta;
        $count = count($this->contaFormClienteResults);
        $this->contaFormClienteIndex = max(0, min($count - 1, $index));
    }

    public function highlightContaFormClienteResult(int $index): void
    {
        if (! isset($this->contaFormClienteResults[$index])) {
            return;
        }

        $this->contaFormClienteIndex = $index;
    }

    public function selectContaFormClienteResult(int $index): void
    {
        if (! isset($this->contaFormClienteResults[$index])) {
            return;
        }

        $this->contaFormClienteIndex = $index;
        $this->confirmContaFormClienteSelection();
    }

    public function confirmContaFormClienteSelection(): void
    {
        $index = $this->contaFormClienteIndex;

        if ($index === null || ! isset($this->contaFormClienteResults[$index])) {
            $this->contaFormClienteLookupOpen = false;

            return;
        }

        $row = $this->contaFormClienteResults[$index];
        $this->contaFormClienteId = (string) $row['id'];
        $this->contaFormClienteBusca = $row['nome'];
        $this->resetErrorBag('contaFormClienteId');
        $this->closeContaFormClienteLookup();
    }

    public function handleContaFormClienteEnter(): void
    {
        if (! $this->contaFormClienteLookupOpen) {
            return;
        }

        if ($this->contaFormClienteResults === []) {
            $this->contaFormClienteLookupOpen = false;

            return;
        }

        $this->confirmContaFormClienteSelection();
    }

    public function closeContaFormClienteLookup(): void
    {
        $this->contaFormClienteLookupOpen = false;
        $this->contaFormClienteResults = [];
        $this->contaFormClienteIndex = null;
    }

    public function salvarContaForm(): void
    {
        $tipos = implode(',', array_keys(ContaReceberCadastroService::tiposAvulso()));

        $rules = [
            'contaFormEmissao' => ['required', 'date'],
            'contaFormForma' => ['required', 'in:'.$tipos],
            'contaFormDocumento' => ['nullable', 'string', 'max:40'],
            'contaFormClienteId' => ['required', 'integer', 'exists:people,id'],
            'contaFormVencimento' => ['required', 'date'],
            'contaFormHistorico' => ['nullable', 'string', 'max:500'],
            'contaFormValor' => ['required', 'string'],
        ];

        if ($this->contaFormRecordId === null) {
            $rules['contaFormParcelas'] = ['required', 'integer', 'min:1', 'max:120'];
        }

        $this->validate(
            $rules,
            [
                'contaFormClienteId.required' => 'Selecione o cliente.',
                'contaFormEmissao.required' => 'Informe a emissão.',
                'contaFormVencimento.required' => 'Informe o vencimento.',
                'contaFormForma.in' => 'Tipo inválido.',
            ],
            [
                'contaFormEmissao' => 'emissão',
                'contaFormForma' => 'tipo',
                'contaFormDocumento' => 'documento',
                'contaFormClienteId' => 'cliente',
                'contaFormVencimento' => 'vencimento',
                'contaFormHistorico' => 'histórico',
                'contaFormValor' => 'valor',
                'contaFormParcelas' => 'repetir por',
            ],
        );

        $valor = ErpMoney::parseBr($this->contaFormValor);

        if ($valor <= 0) {
            Notification::make()
                ->title('Informe um valor maior que zero.')
                ->warning()
                ->send();

            return;
        }

        try {
            if ($this->contaFormRecordId !== null) {
                app(ContaReceberCadastroService::class)->atualizar((int) $this->contaFormRecordId, [
                    'emissao' => $this->contaFormEmissao,
                    'documento' => $this->contaFormDocumento,
                    'cliente_id' => (int) $this->contaFormClienteId,
                    'vencimento' => $this->contaFormVencimento,
                    'historico' => $this->contaFormHistorico,
                    'valor' => $valor,
                    'forma' => $this->contaFormForma,
                ]);
                $mensagem = 'Conta alterada.';
            } else {
                $criadas = app(ContaReceberCadastroService::class)->criar([
                    'emissao' => $this->contaFormEmissao,
                    'documento' => $this->contaFormDocumento,
                    'cliente_id' => (int) $this->contaFormClienteId,
                    'vencimento' => $this->contaFormVencimento,
                    'historico' => $this->contaFormHistorico,
                    'valor' => $valor,
                    'forma' => $this->contaFormForma,
                    'parcelas' => (int) $this->contaFormParcelas,
                ]);
                $qtd = count($criadas);
                $mensagem = $qtd === 1 ? 'Conta cadastrada.' : "{$qtd} parcelas cadastradas.";
            }
        } catch (InvalidArgumentException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->warning()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);
            Notification::make()
                ->title('Não foi possível salvar a conta.')
                ->danger()
                ->send();

            return;
        }

        $this->closeContaFormModal();
        $this->situacaoFilter = 'a_receber';
        $this->clearListSelection();
        $this->resetTable();

        Notification::make()
            ->title($mensagem)
            ->success()
            ->send();
    }

    protected function fillContaFormForCreate(): void
    {
        $hoje = ErpTimezone::toLocal()->toDateString();

        $this->contaFormRecordId = null;
        $this->contaFormNumero = ContaReceber::nextNumero();
        $this->contaFormEmissao = $hoje;
        $this->contaFormForma = ContaReceber::FORMA_CARTEIRA;
        $this->contaFormDocumento = '';
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormClienteId = '';
        $this->contaFormClienteBusca = '';
        $this->closeContaFormClienteLookup();
        $this->contaFormVencimento = $hoje;
        $this->contaFormHistorico = '';
        $this->contaFormValor = '0,00';
        $this->contaFormParcelas = '1';
    }

    protected function fillContaFormFromRecord(ContaReceber $conta): void
    {
        $this->contaFormRecordId = (int) $conta->id;
        $this->contaFormNumero = (string) ($conta->numero ?? '');
        $this->contaFormEmissao = optional($conta->emissao)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $forma = mb_strtolower(trim((string) ($conta->forma ?? '')), 'UTF-8');
        $this->contaFormForma = array_key_exists($forma, ContaReceberCadastroService::tiposAvulso())
            ? $forma
            : ContaReceber::FORMA_CARTEIRA;
        $this->contaFormDocumento = (string) ($conta->documento ?? '');
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormClienteId = (string) ($conta->cliente_id ?? '');
        $this->contaFormClienteBusca = mb_strtoupper(trim((string) ($conta->cliente?->nome_razao ?? '')), 'UTF-8');
        $this->closeContaFormClienteLookup();
        $this->contaFormVencimento = optional($conta->vencimento)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $this->contaFormHistorico = (string) ($conta->historico ?? '');
        $this->contaFormValor = ErpMoney::formatBr((float) $conta->valor);
        $this->contaFormParcelas = '1';
    }

    protected function contaFormEmpresaAtual(): string
    {
        $empresa = ErpContext::currentEmpresa();
        $empresaNome = trim((string) (
            $empresa?->fantasia
            ?: $empresa?->nome
            ?: $empresa?->razao_social
            ?: ''
        ));

        return $empresaNome !== '' ? mb_strtoupper($empresaNome, 'UTF-8') : '—';
    }
}
