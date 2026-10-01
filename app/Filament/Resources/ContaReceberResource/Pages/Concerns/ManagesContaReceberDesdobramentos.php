<?php

namespace App\Filament\Resources\ContaReceberResource\Pages\Concerns;

use App\Models\ContaReceber;
use App\Models\ContaReceberPagamento;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Financeiro\ContaReceberEstornoService;
use Filament\Notifications\Notification;
use InvalidArgumentException;

trait ManagesContaReceberDesdobramentos
{
    public ?int $desdobramentoContaId = null;

    /** @var list<int|string> */
    public array $desdobramentoSelectedIds = [];

    /** @var list<array<string, mixed>> */
    public array $desdobramentoRows = [];

    /** @var array<string, string> */
    public array $desdobramentoTitulo = [
        'numero' => '',
        'emissao' => '',
        'documento' => '',
        'historico' => '',
        'cliente' => '',
        'vencimento' => '',
        'valor' => '0,00',
        'desconto' => '0,00',
        'juros' => '0,00',
        'valor_recebido' => '0,00',
        'saldo' => '0,00',
    ];

    public bool $estornoConfirmOpen = false;

    public function abrirDesdobramentos(): void
    {
        if (! $this->highlightedRecordIdOrNotify('desdobramentos')) {
            return;
        }

        $conta = ContaReceber::query()
            ->with(['cliente:id,nome_razao,apelido_fantasia', 'pagamentos.formaPagamento:id,descricao'])
            ->whereKey((int) $this->highlightedRecordId)
            ->first();

        if (! $conta) {
            return;
        }

        $this->viewTab = 'desdobramentos';
        $this->desdobramentoContaId = (int) $conta->id;
        $this->carregarDesdobramentos($conta);
    }

    public function voltarParaTitulos(): void
    {
        $this->viewTab = 'dados';
        $this->desdobramentoContaId = null;
        $this->desdobramentoSelectedIds = [];
        $this->desdobramentoRows = [];
        $this->estornoConfirmOpen = false;
        $this->desdobramentoTitulo = [
            'numero' => '',
            'emissao' => '',
            'documento' => '',
            'historico' => '',
            'cliente' => '',
            'vencimento' => '',
            'valor' => '0,00',
            'desconto' => '0,00',
            'juros' => '0,00',
            'valor_recebido' => '0,00',
            'saldo' => '0,00',
        ];
        $this->pushContaReceberListRefresh(skipPageRender: false);
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

        $this->desdobramentoSelectedIds = array_map(static fn (int $id): string => (string) $id, $ids);
    }

    public function pedirEstornoDesdobramento(): void
    {
        if ($this->viewTab !== 'desdobramentos') {
            Notification::make()
                ->title('Abra Desdobramentos e marque a baixa.')
                ->warning()
                ->send();

            return;
        }

        if ($this->desdobramentoSelectedIds === []) {
            Notification::make()
                ->title('Marque a baixa a estornar.')
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

        $service = app(ContaReceberEstornoService::class);
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
                $erro = 'Não foi possível estornar a baixa.';
                break;
            }
        }

        $this->estornoConfirmOpen = false;
        $this->desdobramentoSelectedIds = [];

        $conta = ContaReceber::query()
            ->with(['cliente:id,nome_razao,apelido_fantasia', 'pagamentos.formaPagamento:id,descricao'])
            ->whereKey((int) $this->desdobramentoContaId)
            ->first();

        if ($ok > 0) {
            Notification::make()
                ->title($ok === 1 ? 'Baixa estornada.' : "{$ok} baixas estornadas.")
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
            $this->viewTab = 'dados';
            $this->desdobramentoContaId = null;
            $this->desdobramentoRows = [];
            $this->pushContaReceberListRefresh(skipPageRender: false);

            return;
        }

        $this->carregarDesdobramentos($conta);
    }

    protected function carregarDesdobramentos(ContaReceber $conta): void
    {
        $cliente = $conta->cliente;
        $clienteNome = trim((string) (
            $cliente?->apelido_fantasia
            ?: $cliente?->nome_razao
            ?: ''
        ));
        $numero = ltrim((string) ($conta->numero ?? ''), '0');

        $this->desdobramentoTitulo = [
            'numero' => $numero !== '' ? $numero : '0',
            'emissao' => optional($conta->emissao)->format('d/m/Y') ?: '—',
            'documento' => mb_strtoupper(trim((string) ($conta->documento ?: '—')), 'UTF-8'),
            'historico' => mb_strtoupper(trim((string) ($conta->historico ?: '—')), 'UTF-8'),
            'cliente' => $clienteNome !== '' ? mb_strtoupper($clienteNome, 'UTF-8') : '—',
            'vencimento' => optional($conta->vencimento)->format('d/m/Y') ?: '—',
            'valor' => ErpMoney::formatBr((float) $conta->valor),
            'desconto' => ErpMoney::formatBr((float) $conta->desconto),
            'juros' => ErpMoney::formatBr((float) $conta->juros),
            'valor_recebido' => ErpMoney::formatBr((float) $conta->valor_recebido),
            'saldo' => ErpMoney::formatBr((float) $conta->saldo),
        ];

        $this->desdobramentoRows = $conta->pagamentos
            ->sortByDesc(fn (ContaReceberPagamento $pagamento) => $pagamento->data?->format('Y-m-d').'-'.$pagamento->id)
            ->values()
            ->map(fn (ContaReceberPagamento $pagamento): array => [
                'id' => (int) $pagamento->id,
                'data' => optional($pagamento->data)?->format('d/m/Y') ?? '—',
                'valor_parcela' => ErpMoney::formatBr((float) $pagamento->valor_parcela),
                'juros' => ErpMoney::formatBr((float) $pagamento->juros),
                'multa' => ErpMoney::formatBr((float) $pagamento->multa),
                'desconto' => ErpMoney::formatBr((float) $pagamento->desconto),
                'valor_recebido' => ErpMoney::formatBr((float) $pagamento->valor_recebido),
                'forma' => mb_strtoupper(trim((string) ($pagamento->formaPagamento?->descricao ?? '')), 'UTF-8') ?: '—',
                'cheque' => trim((string) ($pagamento->numero_cheque ?? '')) ?: '—',
            ])
            ->all();

        $this->desdobramentoSelectedIds = [];
    }
}
