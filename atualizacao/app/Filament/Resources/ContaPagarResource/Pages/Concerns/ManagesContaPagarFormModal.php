<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\ContaPagar;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\ContaPagarCadastroService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaPagarFormModal
{
    public bool $contaFormModalOpen = false;

    public ?int $contaFormRecordId = null;

    public string $contaFormNumero = '';

    public string $contaFormEmissao = '';

    public string $contaFormDocumento = '';

    public string $contaFormEmpresa = '';

    public string $contaFormFornecedorId = '';

    public string $contaFormVencimento = '';

    public string $contaFormHistorico = '';

    public string $contaFormValor = '0,00';

    public string $contaFormParcelas = '1';

    public function createConta(): void
    {
        if ($this->viewTab === 'desdobramentos') {
            return;
        }

        if ($this->contaFormModalOpen) {
            return;
        }

        $this->fillContaFormForCreate();
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
    }

    public function editConta(): void
    {
        if ($this->viewTab === 'desdobramentos') {
            return;
        }

        if ($this->contaFormModalOpen) {
            return;
        }

        if (! $this->highlightedRecordIdOrNotify('edit')) {
            return;
        }

        $conta = ContaPagar::query()
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta) {
            Notification::make()
                ->title('Conta não encontrada.')
                ->warning()
                ->send();

            return;
        }

        if ((float) $conta->valor_pago > 0) {
            Notification::make()
                ->title('Conta já possui baixa')
                ->body('Estorne a parcela paga antes de alterar o título.')
                ->warning()
                ->send();

            return;
        }

        $this->fillContaFormFromRecord($conta);
        $this->resetErrorBag();
        $this->contaFormModalOpen = true;
    }

    public function closeContaFormModal(): void
    {
        $this->contaFormModalOpen = false;
        $this->contaFormRecordId = null;
        $this->resetErrorBag();
    }

    public function salvarContaForm(): void
    {
        $rules = [
            'contaFormEmissao' => ['required', 'date'],
            'contaFormDocumento' => ['nullable', 'string', 'max:40'],
            'contaFormFornecedorId' => ['required', 'integer', 'exists:people,id'],
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
                'contaFormFornecedorId.required' => 'Selecione o fornecedor.',
                'contaFormEmissao.required' => 'Informe a emissão.',
                'contaFormVencimento.required' => 'Informe o vencimento.',
            ],
            [
                'contaFormEmissao' => 'emissão',
                'contaFormDocumento' => 'documento',
                'contaFormFornecedorId' => 'fornecedor',
                'contaFormVencimento' => 'vencimento',
                'contaFormHistorico' => 'histórico',
                'contaFormValor' => 'valor',
                'contaFormParcelas' => 'nº parcelas',
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
                app(ContaPagarCadastroService::class)->atualizar((int) $this->contaFormRecordId, [
                    'emissao' => $this->contaFormEmissao,
                    'documento' => $this->contaFormDocumento,
                    'fornecedor_id' => (int) $this->contaFormFornecedorId,
                    'vencimento' => $this->contaFormVencimento,
                    'historico' => $this->contaFormHistorico,
                    'valor' => $valor,
                ]);
                $mensagem = 'Conta alterada.';
            } else {
                $criadas = app(ContaPagarCadastroService::class)->criar([
                    'emissao' => $this->contaFormEmissao,
                    'documento' => $this->contaFormDocumento,
                    'fornecedor_id' => (int) $this->contaFormFornecedorId,
                    'vencimento' => $this->contaFormVencimento,
                    'historico' => $this->contaFormHistorico,
                    'valor' => $valor,
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
        $this->situacaoFilter = 'a_pagar';
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
        $this->contaFormNumero = ContaPagar::nextNumero();
        $this->contaFormEmissao = $hoje;
        $this->contaFormDocumento = '';
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormFornecedorId = '';
        $this->contaFormVencimento = $hoje;
        $this->contaFormHistorico = '';
        $this->contaFormValor = '0,00';
        $this->contaFormParcelas = '1';
    }

    protected function fillContaFormFromRecord(ContaPagar $conta): void
    {
        $this->contaFormRecordId = (int) $conta->id;
        $this->contaFormNumero = (string) ($conta->numero ?? '');
        $this->contaFormEmissao = optional($conta->emissao)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $this->contaFormDocumento = (string) ($conta->documento ?? '');
        $this->contaFormEmpresa = $this->contaFormEmpresaAtual();
        $this->contaFormFornecedorId = (string) ($conta->fornecedor_id ?? '');
        $this->contaFormVencimento = optional($conta->vencimento)?->format('Y-m-d') ?? ErpTimezone::toLocal()->toDateString();
        $this->contaFormHistorico = (string) ($conta->produto ?? '');
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
