<?php

namespace App\Filament\Resources\ContaPagarResource\Pages\Concerns;

use App\Models\ContaPagar;
use App\Models\ContaPagarPagamento;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ContaPagarEstornoService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaPagarDesdobramentos
{
    public ?int $desdobramentoContaId = null;

    /** @var list<int> */
    public array $desdobramentoSelectedIds = [];

    /** @var list<array<string, mixed>> */
    public array $desdobramentoRows = [];

    /** @var array<string, string> */
    public array $desdobramentoTitulo = [
        'numero' => '',
        'emissao' => '',
        'documento' => '',
        'historico' => '',
        'fornecedor' => '',
        'vencimento' => '',
        'valor' => '0,00',
        'desconto' => '0,00',
        'juros' => '0,00',
        'valor_pago' => '0,00',
        'saldo' => '0,00',
    ];

    public bool $estornoConfirmOpen = false;

    public function podeVerAbaDesdobramentos(): bool
    {
        if ($this->viewTab === 'desdobramentos') {
            return true;
        }

        if (! $this->highlightedRecordId) {
            return false;
        }

        $conta = ContaPagar::query()
            ->whereKey((int) $this->highlightedRecordId)
            ->first(['id', 'valor_pago']);

        if (! $conta) {
            return false;
        }

        if ((float) $conta->valor_pago > 0) {
            return true;
        }

        return ContaPagarPagamento::query()
            ->where('conta_pagar_id', (int) $conta->id)
            ->exists();
    }

    public function abrirDesdobramentos(): void
    {
        if (! $this->highlightedRecordIdOrNotify('desdobramentos')) {
            return;
        }

        $conta = ContaPagar::query()
            ->with(['fornecedor:id,nome_razao,apelido_fantasia', 'pagamentos'])
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta) {
            return;
        }

        if ((float) $conta->valor_pago <= 0 && $conta->pagamentos->isEmpty()) {
            Notification::make()
                ->title('Título sem baixa')
                ->body('Desdobramentos só está disponível para títulos já pagos.')
                ->warning()
                ->send();

            return;
        }

        $this->viewTab = 'desdobramentos';
        $this->desdobramentoContaId = (int) $conta->id;
        $this->carregarDesdobramentos($conta);
    }

    public function voltarParaTitulos(): void
    {
        $this->viewTab = 'titulos';
        $this->desdobramentoContaId = null;
        $this->desdobramentoSelectedIds = [];
        $this->desdobramentoRows = [];
        $this->estornoConfirmOpen = false;
        $this->desdobramentoTitulo = [
            'numero' => '',
            'emissao' => '',
            'documento' => '',
            'historico' => '',
            'fornecedor' => '',
            'vencimento' => '',
            'valor' => '0,00',
            'desconto' => '0,00',
            'juros' => '0,00',
            'valor_pago' => '0,00',
            'saldo' => '0,00',
        ];
        $this->clearListSelection();
        $this->resetTable();
    }

    public function toggleDesdobramentoFlag(int $pagamentoId): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->desdobramentoSelectedIds)));

        if (in_array($pagamentoId, $ids, true)) {
            $ids = array_values(array_filter(
                $ids,
                fn (int $id): bool => $id !== $pagamentoId,
            ));
        } else {
            $ids[] = $pagamentoId;
        }

        // wire:model de checkbox usa string no value do input
        $this->desdobramentoSelectedIds = array_map(static fn (int $id): string => (string) $id, $ids);
    }

    public function pedirEstornoDesdobramento(): void
    {
        if ($this->viewTab !== 'desdobramentos') {
            return;
        }

        if ($this->desdobramentoSelectedIds === []) {
            Notification::make()
                ->title('Marque a(s) parcela(s) a estornar.')
                ->body('Use a flag na grade. Pode marcar uma ou várias.')
                ->warning()
                ->send();

            return;
        }

        $this->estornoConfirmOpen = true;
    }

    public function cancelarEstornoDesdobramento(): void
    {
        $this->estornoConfirmOpen = false;
    }

    public function confirmarEstornoDesdobramento(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->desdobramentoSelectedIds)));

        if ($ids === []) {
            $this->estornoConfirmOpen = false;

            return;
        }

        $service = app(ContaPagarEstornoService::class);
        $ok = 0;
        $total = 0.0;
        $erro = null;

        foreach ($ids as $pagamentoId) {
            try {
                $resultado = $service->estornarPagamento($pagamentoId);
                $ok++;
                $total += (float) $resultado['valor'];
            } catch (InvalidArgumentException $e) {
                $erro = $e->getMessage();
                break;
            } catch (\Throwable $e) {
                report($e);
                $erro = 'Não foi possível estornar a parcela.';
                break;
            }
        }

        $this->estornoConfirmOpen = false;
        $this->desdobramentoSelectedIds = [];

        $conta = ContaPagar::query()
            ->with(['fornecedor:id,nome_razao,apelido_fantasia', 'pagamentos'])
            ->whereKey((int) $this->desdobramentoContaId)
            ->first();

        if ($ok > 0) {
            Notification::make()
                ->title($ok === 1 ? 'Parcela estornada.' : "{$ok} parcelas estornadas.")
                ->body('Total estornado: R$ '.ErpMoney::formatBr($total))
                ->success()
                ->send();
        }

        if ($erro) {
            Notification::make()
                ->title($erro)
                ->danger()
                ->send();
        }

        if (! $conta || $conta->pagamentos->isEmpty()) {
            $this->voltarParaTitulos();
            $this->situacaoFilter = 'a_pagar';
            $this->clearListSelection();

            return;
        }

        $this->carregarDesdobramentos($conta);
    }

    protected function carregarDesdobramentos(ContaPagar $conta): void
    {
        $fornecedor = $conta->fornecedor;
        $fornecedorNome = trim((string) (
            $fornecedor?->apelido_fantasia
            ?: $fornecedor?->nome_razao
            ?: ''
        ));

        $this->desdobramentoTitulo = [
            'numero' => (string) ($conta->numero ?? '—'),
            'emissao' => optional($conta->emissao)->format('d/m/Y') ?: '—',
            'documento' => mb_strtoupper(trim((string) ($conta->documento ?: '—')), 'UTF-8'),
            'historico' => mb_strtoupper(trim((string) ($conta->produto ?: '—')), 'UTF-8'),
            'fornecedor' => $fornecedorNome !== '' ? mb_strtoupper($fornecedorNome, 'UTF-8') : '—',
            'vencimento' => optional($conta->vencimento)->format('d/m/Y') ?: '—',
            'valor' => ErpMoney::formatBr((float) $conta->valor),
            'desconto' => ErpMoney::formatBr((float) $conta->desconto),
            'juros' => ErpMoney::formatBr((float) $conta->juros),
            'valor_pago' => ErpMoney::formatBr((float) $conta->valor_pago),
            'saldo' => ErpMoney::formatBr((float) $conta->saldo),
        ];

        $this->desdobramentoRows = $conta->pagamentos
            ->sortByDesc('data')
            ->sortByDesc('id')
            ->values()
            ->map(fn (ContaPagarPagamento $pagamento): array => [
                'id' => (int) $pagamento->id,
                'data' => optional($pagamento->data)?->format('d/m/Y') ?? '—',
                'valor_parcela' => ErpMoney::formatBr((float) $pagamento->valor_parcela),
                'juros' => ErpMoney::formatBr((float) $pagamento->juros),
                'desconto' => ErpMoney::formatBr((float) $pagamento->desconto),
                'valor_pago' => ErpMoney::formatBr((float) $pagamento->valor_pago),
                'cheque' => trim((string) ($pagamento->numero_cheque ?? '')) ?: '—',
            ])
            ->all();

        $this->desdobramentoSelectedIds = [];
    }
}
