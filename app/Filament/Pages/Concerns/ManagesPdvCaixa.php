<?php

namespace App\Filament\Pages\Concerns;

use App\Models\CaixaConta;
use App\Models\Empresa;
use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Printing\Documents\PdvCaixaResumoCupomPrintDocument;
use App\Support\Erp\Printing\Documents\PdvMovimentoCaixaCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Carbon\CarbonInterface;
use App\Support\Erp\Pdv\PdvCaixaFechamentoService;
use App\Support\Erp\Pdv\TerminalResolver;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

trait ManagesPdvCaixa
{
    public ?int $caixaSessaoId = null;

    /** @var array<string, string> */
    public array $aberturaForm = [
        'valor' => '0,00',
    ];

    /** @var array{dinheiro_informado: string} */
    public array $fechamentoForm = [
        'dinheiro_informado' => '0,00',
    ];

    public bool $fechamentoMoedasModalOpen = false;

    public bool $pdvConfirmImprimirMovimentoCaixa = false;

    public ?int $pdvImprimirMovimentoCaixaId = null;

    public ?string $pdvImprimirMovimentoCaixaTipo = null;

    public bool $pdvConfirmImprimirResumoCaixa = false;

    public ?int $pdvImprimirResumoCaixaSessaoId = null;

    public float $pdvImprimirResumoCaixaDinheiro = 0.0;

    /**
     * Quantidades por face (chave = centavos: 100, 50, 25, 10, 5).
     * Sem ponto no key — Livewire interpreta "." como path aninhado.
     *
     * @var array<string, string>
     */
    public array $fechamentoMoedasQtd = [
        '100' => '',
        '50' => '',
        '25' => '',
        '10' => '',
        '5' => '',
    ];

    /**
     * Faces de moeda BRL usadas no contador.
     *
     * @return list<float>
     */
    public function fechamentoMoedasFaces(): array
    {
        return [1.0, 0.5, 0.25, 0.1, 0.05];
    }

    /**
     * Chave Livewire estável (centavos) para a face.
     */
    public function fechamentoMoedasKey(float $face): string
    {
        return (string) (int) round($face * 100);
    }
    /**
     * Contexto exibido no modal Abrir/Fechar Caixa.
     *
     * @return array{usuario: string, operador: string, empresa: string, terminal: string}
     */
    public function getCaixaModalContextoProperty(): array
    {
        $user = Auth::user();
        $empresaId = $this->resolveEmpresaId();
        $empresaNome = '';

        if ($empresaId) {
            $empresaNome = (string) (Empresa::query()->whereKey($empresaId)->value('nome') ?? '');
        }

        $terminal = TerminalResolver::make()->resolveOrCreateDefault($empresaId);
        $operador = trim((string) ($this->vendedor ?? ''));

        if ($operador === '' && $user) {
            $vendedor = $user->relationLoaded('vendedor')
                ? $user->vendedor
                : $user->vendedor()->first();
            $operador = trim((string) ($vendedor?->nome ?? ''));
        }

        return [
            'usuario' => mb_strtoupper(trim((string) ($user?->name ?? '—')), 'UTF-8'),
            'operador' => $operador !== ''
                ? mb_strtoupper($operador, 'UTF-8')
                : '—',
            'empresa' => $empresaNome !== ''
                ? mb_strtoupper($empresaNome, 'UTF-8')
                : '—',
            'terminal' => mb_strtoupper(trim((string) ($terminal?->nome ?? 'PDV')), 'UTF-8'),
        ];
    }

    protected function resolveEmpresaId(): ?int
    {
        $empresaId = session('erp_empresa_id', Auth::user()?->empresa_id);

        return filled($empresaId) ? (int) $empresaId : null;
    }

    protected function loadCaixaFromDatabase(): void
    {
        $terminal = TerminalResolver::make()->resolveOrCreateDefault($this->resolveEmpresaId());

        $sessao = PdvCaixaSessao::query()
            ->where('user_id', Auth::id())
            ->whereNull('fechado_em')
            ->when(
                $this->resolveEmpresaId(),
                fn ($query) => $query->where('empresa_id', $this->resolveEmpresaId()),
            )
            ->when(
                $terminal?->id,
                fn ($query) => $query->where('terminal_id', $terminal->id),
            )
            ->latest('id')
            ->first();

        if (! $sessao) {
            $this->caixaSessaoId = null;
            $this->caixaAberto = false;
            session()->forget('erp.pdv.caixa_sessao_id');

            return;
        }

        $this->caixaSessaoId = $sessao->id;
        $this->caixaAberto = true;
        session(['erp.pdv.caixa_sessao_id' => $sessao->id]);
    }

    protected function caixaSessaoAtual(): ?PdvCaixaSessao
    {
        if (! $this->caixaSessaoId) {
            return null;
        }

        return PdvCaixaSessao::query()->find($this->caixaSessaoId);
    }

    protected function persistCaixaState(bool $aberto): void
    {
        $this->caixaAberto = $aberto;

        if (! $aberto) {
            $this->caixaSessaoId = null;
            session()->forget('erp.pdv.caixa_sessao_id');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getResumoCaixaProperty(): array
    {
        $sessao = $this->caixaSessaoAtual();

        if (! $sessao || ! $this->caixaAberto) {
            return [
                'total_entrada' => '0,00',
                'total_saida' => '0,00',
                'saldo_total' => '0,00',
                'saldo_dinheiro' => '0,00',
                'movimentos' => [],
                'vendas_canceladas' => [],
                'produtos_cancelados' => [],
                'total_vendas_canceladas' => '0,00',
                'total_produtos_cancelados' => '0,00',
            ];
        }

        return $this->montarResumoCaixaSessao($sessao);
    }

    /**
     * @return array<string, mixed>
     */
    protected function montarResumoCaixaSessao(PdvCaixaSessao $sessao): array
    {
        $linhasResumo = \App\Support\Erp\Pdv\PdvCaixaResumoMovimentos::fromSessao($sessao);
        $entradaResumo = 0.0;
        $saidaResumo = 0.0;

        foreach ($linhasResumo as $linha) {
            $entradaResumo = round($entradaResumo + (float) ($linha['entrada'] ?? 0), 2);
            $saidaResumo = round($saidaResumo + (float) ($linha['saida'] ?? 0), 2);
        }

        $movimentos = collect($linhasResumo)
            ->map(fn (array $linha): array => [
                'historico' => $linha['historico'],
                'entrada' => ErpMoney::formatBr($linha['entrada']),
                'saida' => ErpMoney::formatBr($linha['saida']),
            ])
            ->all();

        $vendasCanceladas = \App\Models\PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $sessao->id)
            ->where('situacao', 'C')
            ->orderBy('id')
            ->get(['id', 'numero', 'total', 'motivo_estorno', 'fechado_em', 'updated_at'])
            ->map(fn ($v): array => [
                'numero' => (string) $v->numero,
                'total' => ErpMoney::formatBr($v->total),
                'motivo' => (string) ($v->motivo_estorno ?: '—'),
                'em' => $this->formatCancelamentoEm($v->updated_at ?? $v->fechado_em),
            ])
            ->all();

        $produtosCancelados = [];
        $totalProdutos = 0.0;

        foreach (is_array($sessao->itens_cancelados) ? $sessao->itens_cancelados : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $total = (float) ($row['total'] ?? 0);
            $totalProdutos += $total;
            $produtosCancelados[] = [
                'codigo' => (string) ($row['codigo'] ?? ''),
                'descricao' => (string) ($row['descricao'] ?? ''),
                'qtd' => ErpMoney::formatBr((float) ($row['qtd'] ?? 0), 3),
                'total' => ErpMoney::formatBr($total),
                'em' => $this->formatCancelamentoEm($row['em'] ?? null),
            ];
        }

        $totalVendas = (float) \App\Models\PdvVenda::query()
            ->where('pdv_caixa_sessao_id', $sessao->id)
            ->where('situacao', 'C')
            ->sum('total');

        return [
            'total_entrada' => ErpMoney::formatBr($entradaResumo),
            'total_saida' => ErpMoney::formatBr($saidaResumo),
            'saldo_total' => ErpMoney::formatBr(round($entradaResumo - $saidaResumo, 2)),
            'saldo_dinheiro' => ErpMoney::formatBr($sessao->saldoDinheiro()),
            'movimentos' => $movimentos,
            'vendas_canceladas' => $vendasCanceladas,
            'produtos_cancelados' => $produtosCancelados,
            'total_vendas_canceladas' => ErpMoney::formatBr($totalVendas),
            'total_produtos_cancelados' => ErpMoney::formatBr($totalProdutos),
        ];
    }

    protected function formatCancelamentoEm(CarbonInterface|string|null $moment): string
    {
        if ($moment === null || $moment === '') {
            return '—';
        }

        try {
            return ErpTimezone::toLocal($moment)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return '—';
        }
    }

    public function getFechamentoMoedasTotalProperty(): float
    {
        $total = 0.0;

        foreach ($this->fechamentoMoedasFaces() as $face) {
            $key = $this->fechamentoMoedasKey($face);
            $raw = preg_replace('/\D+/', '', (string) ($this->fechamentoMoedasQtd[$key] ?? '')) ?? '';
            $qtd = $raw === '' ? 0 : max(0, (int) $raw);
            $total += $face * $qtd;
        }

        return round($total, 2);
    }

    public function abrirContarMoedas(): void
    {
        if ($this->activeModal !== 'fechar_caixa') {
            return;
        }

        foreach ($this->fechamentoMoedasFaces() as $face) {
            $key = $this->fechamentoMoedasKey($face);
            $this->fechamentoMoedasQtd[$key] = (string) ($this->fechamentoMoedasQtd[$key] ?? '');
        }

        $this->fechamentoMoedasModalOpen = true;
        $this->dispatch('erp-pdv-contar-moedas-opened');
    }

    public function fecharContarMoedas(): void
    {
        $this->fechamentoMoedasModalOpen = false;
    }

    public function confirmarContarMoedas(): void
    {
        $total = $this->fechamentoMoedasTotal;
        $this->fechamentoForm['dinheiro_informado'] = ErpMoney::formatBr($total);
        $this->fechamentoMoedasModalOpen = false;

        Notification::make()
            ->title('Moedas lançadas')
            ->body('Dinheiro contado: R$ '.ErpMoney::formatBr($total))
            ->success()
            ->send();
    }

    public function imprimirResumoCaixaFechamento(): void
    {
        $sessao = $this->caixaSessaoAtual();

        if (! $sessao) {
            Notification::make()
                ->title('Nenhuma sessão de caixa aberta.')
                ->warning()
                ->send();

            return;
        }

        $dinheiro = ErpMoney::parseBr($this->fechamentoForm['dinheiro_informado'] ?? '0');
        $this->imprimirResumoCaixaEscPos($sessao, $dinheiro);
    }

    protected function resetFechamentoForm(): void
    {
        $this->fechamentoForm = ['dinheiro_informado' => '0,00'];
        $this->fechamentoMoedasModalOpen = false;
        $this->fechamentoMoedasQtd = [
            '100' => '',
            '50' => '',
            '25' => '',
            '10' => '',
            '5' => '',
        ];
    }
    public function confirmAbrirCaixa(): void
    {
        if ($this->caixaAberto) {
            $this->closePdvModal();

            return;
        }

        if (! $this->garantirOperadorDoUsuarioLogado()) {
            $this->closePdvModal();

            return;
        }

        if (! $this->aplicarVendedorDoUsuarioLogado() || ! $this->vendedorId) {
            $this->notificarOperadorObrigatorio();
            $this->closePdvModal();

            return;
        }

        $valorAbertura = ErpMoney::parseBr($this->aberturaForm['valor'] ?? '0');

        if ($valorAbertura < 0) {
            Notification::make()
                ->title('Informe um valor de abertura válido.')
                ->warning()
                ->send();

            return;
        }

        $terminal = TerminalResolver::make()->resolveOrCreateDefault($this->resolveEmpresaId());

        $user = Auth::user();
        if ($user && ! $user->podeOperarPdvNoTerminal($terminal)) {
            $nome = trim((string) ($terminal?->nome ?? '')) ?: 'este terminal';

            Notification::make()
                ->title('PDV não liberado para este usuário.')
                ->body('Você não tem permissão para operar no PDV "'.$nome.'".')
                ->danger()
                ->send();

            return;
        }

        if ($user && ! $user->podeOperarComCaixaPdv($this->resolveEmpresaId())) {
            Notification::make()
                ->title('Sem caixa PDV liberado.')
                ->body('O PDV só opera com conta tipo PDV. Libere um caixa PDV nas permissões do usuário.')
                ->danger()
                ->send();

            return;
        }

        $criouAgora = false;

        $sessao = DB::transaction(function () use ($valorAbertura, $terminal, &$criouAgora): PdvCaixaSessao {
            $sessaoAberta = PdvCaixaSessao::query()
                ->where('user_id', Auth::id())
                ->whereNull('fechado_em')
                ->when(
                    $this->resolveEmpresaId(),
                    fn ($query) => $query->where('empresa_id', $this->resolveEmpresaId()),
                )
                ->when(
                    $terminal?->id,
                    fn ($query) => $query->where('terminal_id', $terminal->id),
                )
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($sessaoAberta) {
                return $sessaoAberta;
            }

            $sessao = PdvCaixaSessao::query()->create([
                'user_id' => Auth::id(),
                'empresa_id' => $this->resolveEmpresaId(),
                'terminal_id' => $terminal?->id,
                'valor_abertura' => $valorAbertura,
                'aberto_em' => now(),
            ]);

            PdvCaixaMovimento::query()->create(
                $this->pdvMovimentoPayload('abertura', [
                    'pdv_caixa_sessao_id' => $sessao->id,
                    'tipo' => 'abertura',
                    'historico' => 'ABERTURA DE CAIXA',
                    'entrada' => $valorAbertura,
                    'saida' => 0,
                ]),
            );

            $criouAgora = true;

            return $sessao;
        });

        $this->caixaSessaoId = $sessao->id;
        $this->caixaAberto = true;
        session(['erp.pdv.caixa_sessao_id' => $sessao->id]);
        $this->aberturaForm['valor'] = ErpMoney::formatBr((float) $sessao->valor_abertura);
        $this->aplicarVendedorDoUsuarioLogado();
        $this->closePdvModal();

        if (! $criouAgora) {
            Notification::make()
                ->title('Caixa já está aberto neste terminal.')
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Caixa aberto.')
            ->body(
                'Valor inicial: R$ '.ErpMoney::formatBr($valorAbertura)
                .' · Operador: '.$this->vendedor
            )
            ->success()
            ->send();

        $this->dispatch('erp-pdv-caixa-opened');
    }

    public function confirmFecharCaixa(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->closePdvModal();

            return;
        }

        $dinheiroInformado = ErpMoney::parseBr($this->fechamentoForm['dinheiro_informado'] ?? '0');

        if ($dinheiroInformado <= 0) {
            Notification::make()
                ->title('Informe o dinheiro contado.')
                ->body('O valor em Dinheiro contado deve ser maior que R$ 0,00 para finalizar o caixa.')
                ->warning()
                ->send();

            return;
        }

        if ($this->cupomTemItens()) {
            Notification::make()
                ->title('Existe venda em andamento.')
                ->body('Finalize ou cancele o cupom antes de fechar o caixa.')
                ->warning()
                ->send();

            return;
        }

        $esperas = $this->vendasEmEsperaPendentesCount();

        if ($esperas > 0) {
            Notification::make()
                ->title('Existem vendas em espera.')
                ->body("Recupere ou descarte as {$esperas} venda(s) em espera antes de fechar o caixa.")
                ->warning()
                ->send();

            return;
        }

        $sessao = $this->caixaSessaoAtual();

        if (! $sessao) {
            $this->persistCaixaState(false);
            $this->closePdvModal();

            return;
        }

        $lancamentos = [];
        $fechouAgora = false;

        DB::transaction(function () use ($sessao, &$lancamentos, &$fechouAgora): void {
            $locked = PdvCaixaSessao::query()->whereKey($sessao->id)->lockForUpdate()->first();

            if ($locked === null || $locked->fechado_em !== null) {
                return;
            }

            $saldoFechamento = $locked->saldoTotal();

            $locked->update([
                'valor_fechamento' => $saldoFechamento,
                'fechado_em' => now(),
            ]);

            $lancamentos = app(PdvCaixaFechamentoService::class)->lancarNoLivroCaixa($locked->fresh());
            $fechouAgora = true;
        });

        if (! $fechouAgora) {
            $this->persistCaixaState(false);
            $this->closePdvModal();

            Notification::make()
                ->title('Caixa já estava fechado.')
                ->warning()
                ->send();

            return;
        }

        $sessaoId = (int) $sessao->id;

        $this->persistCaixaState(false);
        $this->limparCupom();
        $this->resetFechamentoForm();
        $this->closePdvModal();

        $totalLivro = round(array_sum(array_map(
            fn ($l): float => (float) $l->entrada,
            $lancamentos,
        )), 2);

        $body = $totalLivro > 0
            ? 'Dinheiro lançado no Livro Caixa: R$ '.ErpMoney::formatBr($totalLivro)
            : 'Sem dinheiro em espécie. Cartões ficam no Contas a Receber até cair na conta.';

        Notification::make()
            ->title('Caixa fechado.')
            ->body($body)
            ->success()
            ->send();

        $this->pdvImprimirResumoCaixaSessaoId = $sessaoId;
        $this->pdvImprimirResumoCaixaDinheiro = $dinheiroInformado;
        $this->pdvConfirmImprimirResumoCaixa = true;
        $this->dispatch('erp-pdv-imprimir-resumo-caixa-opened');
    }

    public function confirmImprimirResumoCaixa(bool $imprimir): void
    {
        $sessaoId = $this->pdvImprimirResumoCaixaSessaoId;
        $dinheiro = $this->pdvImprimirResumoCaixaDinheiro;

        $this->pdvConfirmImprimirResumoCaixa = false;
        $this->pdvImprimirResumoCaixaSessaoId = null;
        $this->pdvImprimirResumoCaixaDinheiro = 0.0;

        if ($imprimir && $sessaoId) {
            $sessao = PdvCaixaSessao::query()
                ->with(['user', 'terminal', 'movimentos', 'empresa'])
                ->find($sessaoId);

            if ($sessao) {
                $this->imprimirResumoCaixaEscPos($sessao, $dinheiro);
            }
        }

        $this->dispatch('erp-pdv-focus-search');
    }

    protected function imprimirResumoCaixaEscPos(PdvCaixaSessao $sessao, float $dinheiroInformado): void
    {
        $user = Auth::user();
        $empresaId = session('erp_empresa_id', $user?->empresa_id);
        $empresa = $sessao->empresa_id
            ? Empresa::query()->find($sessao->empresa_id)
            : ($empresaId ? Empresa::query()->find($empresaId) : $user?->empresa);

        $this->js(PrintFacade::livewireOpenJs(
            new PdvCaixaResumoCupomPrintDocument(
                sessao: $sessao,
                empresa: $empresa,
                dinheiroInformado: $dinheiroInformado,
                usuarioFallback: $user?->name,
            ),
            1,
        ));
    }

    public function gravarSangria(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            Notification::make()
                ->title('Caixa fechado.')
                ->warning()
                ->send();

            return;
        }

        $valor = ErpMoney::parseBr($this->sangriaForm['valor'] ?? '0');

        if ($valor <= 0) {
            Notification::make()
                ->title('Informe o valor da sangria.')
                ->warning()
                ->send();

            return;
        }

        $forma = mb_strtoupper(trim((string) ($this->sangriaForm['tipo_conta'] ?? '')), 'UTF-8');

        if (! in_array($forma, ['DINHEIRO', 'CHEQUE'], true)) {
            Notification::make()
                ->title('Selecione DINHEIRO ou CHEQUE.')
                ->warning()
                ->send();

            return;
        }

        $sessao = $this->caixaSessaoAtual();

        if (! $sessao) {
            Notification::make()
                ->title('Sessão de caixa não encontrada.')
                ->warning()
                ->send();

            return;
        }

        $saldo = $sessao->saldoPorForma($forma);

        if ($valor > round($saldo + 0.001, 2)) {
            Notification::make()
                ->title('Valor acima do saldo.')
                ->body($forma.' disponível: R$ '.ErpMoney::formatBr($saldo))
                ->warning()
                ->send();

            return;
        }

        $destinoId = (int) ($this->sangriaForm['destino'] ?? 0);
        $contaDestino = CaixaConta::query()
            ->whereKey($destinoId)
            ->where('ativo', true)
            ->where('tipo', CaixaConta::TIPO_SUBCAIXA)
            ->first();

        if (! $contaDestino) {
            Notification::make()
                ->title('Selecione uma subcaixa de destino.')
                ->warning()
                ->send();

            return;
        }

        $destinoLabel = trim((string) $contaDestino->codigo).' — '
            .mb_strtoupper((string) $contaDestino->nome, 'UTF-8');

        $historico = filled($this->sangriaForm['historico'] ?? null)
            ? mb_strtoupper(trim($this->sangriaForm['historico']), 'UTF-8')
            : 'SANGRIA';

        $movimento = DB::transaction(function () use ($valor, $historico, $forma, $destinoLabel) {
            return PdvCaixaMovimento::query()->create(
                $this->pdvMovimentoPayload('sangria', [
                    'pdv_caixa_sessao_id' => $this->caixaSessaoId,
                    'tipo' => 'sangria',
                    'historico' => $historico,
                    'forma_pagamento' => $forma,
                    'sangria_destino' => $destinoLabel,
                    'entrada' => 0,
                    'saida' => $valor,
                ]),
            );
        });

        $this->sangriaForm = [
            'historico' => '',
            'valor' => '0,00',
            'tipo_conta' => '',
            'destino' => $this->defaultSangriaDestinoId($this->sangriaDestinoOptions),
        ];

        $this->closePdvModal();
        $this->abrirConfirmImprimirMovimentoCaixa((int) $movimento->id, 'sangria');

        Notification::make()
            ->title('Sangria registrada.')
            ->success()
            ->send();
    }
    public function gravarSuprimento(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            Notification::make()
                ->title('Caixa fechado.')
                ->warning()
                ->send();

            return;
        }

        $valor = ErpMoney::parseBr($this->suprimentoForm['valor'] ?? '0');

        if ($valor <= 0) {
            Notification::make()
                ->title('Informe o valor do suprimento.')
                ->warning()
                ->send();

            return;
        }

        $historico = filled($this->suprimentoForm['historico'] ?? null)
            ? mb_strtoupper(trim($this->suprimentoForm['historico']), 'UTF-8')
            : 'SUPRIMENTO DE CAIXA';

        $movimento = DB::transaction(function () use ($valor, $historico) {
            return PdvCaixaMovimento::query()->create(
                $this->pdvMovimentoPayload('suprimento', [
                    'pdv_caixa_sessao_id' => $this->caixaSessaoId,
                    'tipo' => 'suprimento',
                    'historico' => $historico,
                    'forma_pagamento' => $this->suprimentoForm['tipo_conta'] ?: 'DINHEIRO',
                    'entrada' => $valor,
                    'saida' => 0,
                ]),
            );
        });

        $this->suprimentoForm = [
            'historico' => 'SUPRIMENTO DE CAIXA',
            'valor' => '0,00',
            'tipo_conta' => '',
        ];

        $this->closePdvModal();
        $this->abrirConfirmImprimirMovimentoCaixa((int) $movimento->id, 'suprimento');

        Notification::make()
            ->title('Suprimento registrado.')
            ->success()
            ->send();
    }

    public function confirmImprimirMovimentoCaixa(bool $imprimir): void
    {
        $movimentoId = $this->pdvImprimirMovimentoCaixaId;

        $this->pdvConfirmImprimirMovimentoCaixa = false;
        $this->pdvImprimirMovimentoCaixaId = null;
        $this->pdvImprimirMovimentoCaixaTipo = null;

        if ($imprimir && $movimentoId) {
            $movimento = PdvCaixaMovimento::query()
                ->with(['sessao.user', 'sessao.terminal', 'sessao.empresa'])
                ->find($movimentoId);

            if ($movimento) {
                $user = Auth::user();
                $empresaId = session('erp_empresa_id', $user?->empresa_id);
                $sessao = $movimento->sessao;
                $empresa = $sessao?->empresa_id
                    ? Empresa::query()->find($sessao->empresa_id)
                    : ($empresaId ? Empresa::query()->find($empresaId) : $user?->empresa);

                $this->js(PrintFacade::livewireOpenJs(
                    new PdvMovimentoCaixaCupomPrintDocument(
                        movimento: $movimento,
                        empresa: $empresa,
                        usuarioFallback: $user?->name,
                    ),
                    1,
                ));
            }
        }

        $this->dispatch('erp-pdv-focus-search');
    }

    protected function abrirConfirmImprimirMovimentoCaixa(int $movimentoId, string $tipo): void
    {
        $this->pdvImprimirMovimentoCaixaId = $movimentoId;
        $this->pdvImprimirMovimentoCaixaTipo = $tipo;
        $this->pdvConfirmImprimirMovimentoCaixa = true;
        $this->dispatch('erp-pdv-imprimir-movimento-caixa-opened');
    }
}
