<?php

namespace App\Filament\Pages\Concerns;

use App\Models\PdvCaixaMovimento;
use App\Models\PdvCaixaSessao;
use App\Support\Erp\ErpMoney;
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
            ];
        }

        $movimentos = $sessao->movimentos()
            ->latest('id')
            ->get()
            ->map(fn (PdvCaixaMovimento $movimento): array => [
                'historico' => $movimento->historico,
                'entrada' => ErpMoney::formatBr($movimento->entrada),
                'saida' => ErpMoney::formatBr($movimento->saida),
            ])
            ->all();

        return [
            'total_entrada' => ErpMoney::formatBr($sessao->saldoEntradas()),
            'total_saida' => ErpMoney::formatBr($sessao->saldoSaidas()),
            'saldo_total' => ErpMoney::formatBr($sessao->saldoTotal()),
            'saldo_dinheiro' => ErpMoney::formatBr($sessao->saldoDinheiro()),
            'movimentos' => $movimentos,
        ];
    }

    public function confirmAbrirCaixa(): void
    {
        if ($this->caixaAberto) {
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
            ->latest('id')
            ->first();

        if ($sessaoAberta) {
            $this->caixaSessaoId = $sessaoAberta->id;
            $this->caixaAberto = true;
            session(['erp.pdv.caixa_sessao_id' => $sessaoAberta->id]);
            $this->closePdvModal();

            Notification::make()
                ->title('Caixa já está aberto neste terminal.')
                ->warning()
                ->send();

            return;
        }

        $sessao = DB::transaction(function () use ($valorAbertura, $terminal): PdvCaixaSessao {
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

            return $sessao;
        });

        $this->caixaSessaoId = $sessao->id;
        $this->caixaAberto = true;
        session(['erp.pdv.caixa_sessao_id' => $sessao->id]);
        $this->aberturaForm['valor'] = ErpMoney::formatBr($valorAbertura);
        $this->closePdvModal();

        Notification::make()
            ->title('Caixa aberto.')
            ->body('Valor inicial: R$ ' . ErpMoney::formatBr($valorAbertura))
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

        if ($this->cupomTemItens()) {
            Notification::make()
                ->title('Existe venda em andamento.')
                ->body('Finalize ou cancele o cupom antes de fechar o caixa.')
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

        DB::transaction(function () use ($sessao): void {
            $sessao->update([
                'valor_fechamento' => $sessao->saldoTotal(),
                'fechado_em' => now(),
            ]);
        });

        $this->persistCaixaState(false);
        $this->limparCupom();
        $this->closePdvModal();

        Notification::make()
            ->title('Caixa fechado.')
            ->success()
            ->send();
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

        $historico = filled($this->sangriaForm['historico'] ?? null)
            ? mb_strtoupper(trim($this->sangriaForm['historico']), 'UTF-8')
            : 'SANGRIA';

        DB::transaction(function () use ($valor, $historico): void {
            PdvCaixaMovimento::query()->create(
                $this->pdvMovimentoPayload('sangria', [
                    'pdv_caixa_sessao_id' => $this->caixaSessaoId,
                    'tipo' => 'sangria',
                    'historico' => $historico,
                    'forma_pagamento' => $this->sangriaForm['tipo_conta'] ?: 'DINHEIRO',
                    'sangria_destino' => $this->sangriaForm['destino'] ?? null,
                    'entrada' => 0,
                    'saida' => $valor,
                ]),
            );
        });

        $this->sangriaForm = [
            'historico' => '',
            'valor' => '0,00',
            'tipo_conta' => '',
            'destino' => 'SANGRIA P/ CAIXA GERAL',
        ];

        $this->closePdvModal();

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

        DB::transaction(function () use ($valor, $historico): void {
            PdvCaixaMovimento::query()->create(
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

        Notification::make()
            ->title('Suprimento registrado.')
            ->success()
            ->send();
    }
}
