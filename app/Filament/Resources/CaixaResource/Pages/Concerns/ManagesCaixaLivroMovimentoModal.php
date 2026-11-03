<?php

namespace App\Filament\Resources\CaixaResource\Pages\Concerns;

use App\Models\CaixaConta;
use App\Models\CaixaLivroMovimento;
use App\Models\CaixaLivroMovimentoEvento;
use App\Models\User;
use App\Support\Erp\BrDecimal;
use App\Support\Erp\ErpContext;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Financeiro\CaixaLivroFechamentoConferencia;
use App\Support\Erp\Financeiro\CaixaLivroFechamentoReportData;
use App\Support\Erp\Financeiro\CaixaLivroMovimentoService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

trait ManagesCaixaLivroMovimentoModal
{
    public bool $caixaAbrirOpen = false;

    public bool $caixaFecharOpen = false;

    /** @var array<string, mixed> */
    public array $caixaAbrirForm = [];

    /**
     * Campos de sistema do Fechar Caixa (somente leitura).
     * #[Locked] impede o frontend de sobrescrever saldos/totais.
     *
     * @var array<string, mixed>
     */
    #[Locked]
    public array $caixaFecharLocked = [];

    /**
     * Únicos campos editáveis do Fechar: formas + observação.
     *
     * @var array{formas?: array<string, string>, observacao?: string}
     */
    public array $caixaFecharForm = [];

    /** Resumo calculado no servidor a partir da conferência. */
    #[Locked]
    public array $caixaFecharResumo = [];

    /** true após confirmar fechamento — libera F6 Imprimir. */
    public bool $caixaFecharConcluido = false;

    public bool $caixaFecharPrintOpen = false;

    /** @var array<string, bool> */
    public array $caixaFecharPrintFlags = [];

    public string $caixaFecharPrintModo = 'resumido';

    public bool $caixaFecharHistOpen = false;

    /** @var list<array<string, mixed>> */
    public array $caixaFecharHistLista = [];

    /** Overlay iframe do relatório A4 (mesma tela, sem nova guia). */
    public bool $caixaFecharPreviewOpen = false;

    public ?string $caixaFecharPreviewUrl = null;

    public string $caixaMovimentoAlert = '';

    #[Computed]
    public function caixaMovimentoAberto(): ?CaixaLivroMovimento
    {
        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($contaId <= 0 || $empresaId <= 0) {
            return null;
        }

        return app(CaixaLivroMovimentoService::class)->movimentoAberto($empresaId, $contaId);
    }

    #[Computed]
    public function caixaF8PodeUsar(): bool
    {
        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        if ($contaId <= 0) {
            return false;
        }

        $conta = CaixaConta::query()->visiveisNaEmpresa()->whereKey($contaId)->first();
        if (! $conta || ! $conta->ativo) {
            return false;
        }

        return in_array(
            (string) $conta->tipo,
            app(CaixaLivroMovimentoService::class)->tiposElegiveis(),
            true
        );
    }

    #[Computed]
    public function caixaF8Label(): string
    {
        return $this->caixaMovimentoAberto ? 'Fechar Caixa' : 'Abrir Caixa';
    }

    public function openCaixaF8(): void
    {
        if ($this->caixaMovimentoAberto) {
            $this->openCaixaFechamento();

            return;
        }

        $this->openCaixaAbertura();
    }

    public function openCaixaAbertura(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.abrir')) {
            return;
        }

        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;

        try {
            $ctx = app(CaixaLivroMovimentoService::class)->contexto($contaId);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Abertura indisponível')->body($e->getMessage())->warning()->send();

            return;
        }

        if ($ctx['aberto']) {
            Notification::make()
                ->title('Caixa já aberto')
                ->body('Use F8 — Fechar Caixa para encerrar o movimento atual.')
                ->warning()
                ->send();

            return;
        }

        $empresa = ErpContext::currentEmpresa();
        $user = auth()->user();

        $this->caixaAbrirForm = [
            'empresa' => mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->nome_fantasia ?: 'EMPRESA'), 'UTF-8'),
            'conta' => mb_strtoupper((string) $ctx['conta']->nome, 'UTF-8'),
            'conta_id' => (int) $ctx['conta']->id,
            'data_hora' => ErpTimezone::toLocal()->format('d/m/Y H:i'),
            'usuario' => mb_strtoupper((string) ($user?->name ?? ''), 'UTF-8'),
            'saldo' => number_format((float) $ctx['saldo'], 2, ',', '.'),
            'exige_troco' => (bool) $ctx['exige_troco'],
            'troco' => '0,00',
            'observacao' => '',
        ];
        $this->caixaMovimentoAlert = '';
        $this->caixaAbrirOpen = true;
    }

    public function closeCaixaAbertura(): void
    {
        $this->caixaAbrirOpen = false;
        $this->caixaAbrirForm = [];
        $this->caixaMovimentoAlert = '';
    }

    public function confirmarCaixaAbertura(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.abrir')) {
            return;
        }

        $contaId = (int) ($this->caixaAbrirForm['conta_id'] ?? 0);
        $troco = BrDecimal::parse($this->caixaAbrirForm['troco'] ?? 0, 2);
        $obs = trim((string) ($this->caixaAbrirForm['observacao'] ?? ''));

        try {
            app(CaixaLivroMovimentoService::class)->abrir($contaId, $troco, $obs);
        } catch (InvalidArgumentException $e) {
            $this->caixaMovimentoAlert = $e->getMessage();

            return;
        }

        $this->closeCaixaAbertura();
        unset($this->caixaMovimentoAberto, $this->caixaF8Label, $this->caixaRodape);
        $this->clearListSelection();
        $this->pushCaixaListRefresh(renderPage: true);

        Notification::make()
            ->title('Caixa aberto.')
            ->body('Troco inicial é só conferência. Dinheiro novo na conta: use F7 Transferir ou lançamento adequado.')
            ->success()
            ->send();
    }

    public function openCaixaFechamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.fechar')) {
            return;
        }

        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;

        try {
            $ctx = app(CaixaLivroMovimentoService::class)->contexto($contaId);
        } catch (InvalidArgumentException $e) {
            Notification::make()->title('Fechamento indisponível')->body($e->getMessage())->warning()->send();

            return;
        }

        $aberto = $ctx['aberto'];
        if (! $aberto) {
            Notification::make()
                ->title('Nenhum movimento aberto')
                ->body('Use F8 — Abrir Caixa para iniciar um movimento.')
                ->warning()
                ->send();

            return;
        }

        $totais = app(CaixaLivroMovimentoService::class)->totaisDoMovimento($aberto);

        $user = auth()->user();
        $empresa = ErpContext::currentEmpresa();
        $exigeTroco = (bool) $ctx['exige_troco'];

        $modalidades = CaixaLivroFechamentoConferencia::modalidades(
            $ctx['conta'],
            $totais['saldo_movimento'],
            $exigeTroco,
        );

        // Snapshot somente leitura (#[Locked]). Confirmação ignora estes valores e recalcula no service.
        $this->caixaFecharLocked = [
            'movimento_id' => (int) $aberto->id,
            'empresa_id' => (int) $ctx['empresa_id'],
            'empresa' => mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->nome_fantasia ?: 'EMPRESA'), 'UTF-8'),
            'conta_id' => $contaId,
            'conta' => mb_strtoupper((string) $ctx['conta']->nome, 'UTF-8'),
            'aberto_em' => optional($aberto->aberto_em)?->format('d/m/Y H:i') ?? '—',
            'aberto_por' => mb_strtoupper((string) ($aberto->abertoPor?->name ?? '—'), 'UTF-8'),
            'troco_inicial' => number_format((float) $aberto->troco_inicial, 2, ',', '.'),
            'saldo_abertura_fin' => number_format((float) $aberto->saldo_abertura, 2, ',', '.'),
            'entradas' => number_format($totais['entradas'], 2, ',', '.'),
            'saidas' => number_format($totais['saidas'], 2, ',', '.'),
            'trf_entrada' => number_format($totais['transferencias_entrada'], 2, ',', '.'),
            'trf_saida' => number_format($totais['transferencias_saida'], 2, ',', '.'),
            'saldo_financeiro' => number_format($totais['saldo_financeiro'], 2, ',', '.'),
            'saldo_movimento' => number_format($totais['saldo_movimento'], 2, ',', '.'),
            'saldo_calculado' => number_format($totais['saldo_movimento'], 2, ',', '.'),
            'usuario' => mb_strtoupper((string) ($user?->name ?? ''), 'UTF-8'),
            'exige_troco' => $exigeTroco,
            'modalidades' => $modalidades,
        ];

        $this->caixaFecharForm = [
            'formas' => CaixaLivroFechamentoConferencia::prefillInformados($modalidades),
            'observacao' => '',
        ];
        $this->caixaFecharConcluido = false;
        $this->caixaFecharPrintOpen = false;
        $this->caixaFecharPrintFlags = CaixaLivroFechamentoReportData::flagsResumido();
        $this->caixaFecharPrintModo = 'resumido';
        $this->atualizarResumoConferenciaFechamento();
        $this->caixaMovimentoAlert = '';
        $this->caixaFecharOpen = true;
    }

    public function closeCaixaFechamento(): void
    {
        $this->caixaFecharOpen = false;
        $this->caixaFecharLocked = [];
        $this->caixaFecharForm = [];
        $this->caixaFecharResumo = [];
        $this->caixaFecharConcluido = false;
        $this->caixaFecharPrintOpen = false;
        $this->caixaFecharPrintFlags = [];
        $this->caixaFecharPrintModo = 'resumido';
        $this->caixaFecharHistOpen = false;
        $this->caixaFecharHistLista = [];
        $this->caixaFecharPreviewOpen = false;
        $this->caixaFecharPreviewUrl = null;
        $this->caixaMovimentoAlert = '';
    }

    public function updatedCaixaFecharForm($value, string $key): void
    {
        // Só conferência e observação são editáveis.
        if ($key === 'observacao') {
            $obs = mb_substr(trim((string) ($this->caixaFecharForm['observacao'] ?? '')), 0, 500);
            $this->caixaFecharForm['observacao'] = $obs;

            return;
        }

        if (str_starts_with($key, 'formas.')) {
            $this->sanitizarFormasInformadasFechamento();
            $this->atualizarResumoConferenciaFechamento();

            return;
        }

        // Qualquer outra chave injetada no form editável é descartada.
        $formas = is_array($this->caixaFecharForm['formas'] ?? null)
            ? $this->caixaFecharForm['formas']
            : [];
        $obs = (string) ($this->caixaFecharForm['observacao'] ?? '');
        $this->caixaFecharForm = [
            'formas' => $formas,
            'observacao' => $obs,
        ];
    }

    private function sanitizarFormasInformadasFechamento(): void
    {
        $permitidas = [];
        foreach ($this->caixaFecharLocked['modalidades'] ?? [] as $mod) {
            $chave = (string) ($mod['chave'] ?? '');
            if ($chave !== '') {
                $permitidas[$chave] = true;
            }
        }

        $formas = is_array($this->caixaFecharForm['formas'] ?? null)
            ? $this->caixaFecharForm['formas']
            : [];
        $limpo = [];
        foreach ($formas as $chave => $valor) {
            $chave = (string) $chave;
            if (! isset($permitidas[$chave])) {
                continue;
            }
            $limpo[$chave] = is_scalar($valor) ? (string) $valor : '0,00';
        }
        $this->caixaFecharForm['formas'] = $limpo;
    }

    public function atualizarResumoConferenciaFechamento(): void
    {
        $modalidades = $this->caixaFecharLocked['modalidades'] ?? [];
        if (! is_array($modalidades) || $modalidades === []) {
            $this->caixaFecharResumo = [];

            return;
        }

        $exigeTroco = (bool) ($this->caixaFecharLocked['exige_troco'] ?? false);
        $formas = is_array($this->caixaFecharForm['formas'] ?? null)
            ? $this->caixaFecharForm['formas']
            : [];

        $payload = CaixaLivroFechamentoConferencia::montarPayload($modalidades, $formas, $exigeTroco);
        $dif = $payload['diferenca_disponivel'] ? (float) $payload['diferenca'] : null;

        $this->caixaFecharResumo = [
            'total_informado' => number_format($payload['total_informado'], 2, ',', '.'),
            'total_fisico' => number_format($payload['total_informado_fisico'], 2, ',', '.'),
            'total_eletronico' => number_format($payload['total_informado_eletronico'], 2, ',', '.'),
            'valor_contado' => number_format($payload['valor_contado'], 2, ',', '.'),
            'diferenca' => $dif !== null ? number_format($dif, 2, ',', '.') : null,
            'diferenca_disponivel' => $payload['diferenca_disponivel'],
            'diferenca_ok' => $dif !== null && abs($dif) < 0.005,
            'linhas' => $payload['linhas'],
        ];
    }

    public function confirmarCaixaFechamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.fechar')) {
            return;
        }

        // Proteção: snapshot incompleto / manipulado → não fecha.
        $movimentoId = (int) ($this->caixaFecharLocked['movimento_id'] ?? 0);
        $contaIdLocked = (int) ($this->caixaFecharLocked['conta_id'] ?? 0);
        $contaIdFiltro = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        $empresaIdCtx = (int) (ErpContext::currentEmpresaId() ?? 0);
        $empresaIdLocked = (int) ($this->caixaFecharLocked['empresa_id'] ?? 0);

        if (
            $movimentoId <= 0
            || $contaIdLocked <= 0
            || $empresaIdCtx <= 0
            || ($empresaIdLocked > 0 && $empresaIdLocked !== $empresaIdCtx)
            || ($contaIdFiltro > 0 && $contaIdFiltro !== $contaIdLocked)
            || blank($this->caixaFecharLocked['conta'] ?? null)
            || blank($this->caixaFecharLocked['empresa'] ?? null)
        ) {
            $this->caixaMovimentoAlert = 'Dados do fechamento inconsistentes ou incompletos. Feche a janela e abra novamente com F8.';

            return;
        }

        // Só o que o operador pode informar. Saldos/totais são recalculados no service.
        $obs = trim((string) ($this->caixaFecharForm['observacao'] ?? ''));
        $this->sanitizarFormasInformadasFechamento();
        $formas = is_array($this->caixaFecharForm['formas'] ?? null)
            ? $this->caixaFecharForm['formas']
            : [];

        try {
            $mov = app(CaixaLivroMovimentoService::class)->fechar(
                $contaIdLocked,
                $obs,
                $empresaIdCtx,
                null,
                $formas,
                $movimentoId,
            );
        } catch (InvalidArgumentException $e) {
            $this->caixaMovimentoAlert = $e->getMessage();

            return;
        }

        // Mantém a janela aberta para impressão (F6) do fechamento concluído.
        $this->carregarSnapshotFechamentoPersistido((int) $mov->id);
        $this->caixaFecharConcluido = true;
        $this->caixaFecharForm = [
            'formas' => [],
            'observacao' => (string) ($mov->observacao_fechamento ?? ''),
        ];
        $this->caixaFecharPrintFlags = CaixaLivroFechamentoReportData::flagsResumido();
        $this->caixaFecharPrintModo = 'resumido';
        $this->caixaMovimentoAlert = '';

        unset($this->caixaMovimentoAberto, $this->caixaF8Label, $this->caixaRodape);
        $this->clearListSelection();
        $this->pushCaixaListRefresh(renderPage: true);

        $body = 'Total informado: R$ '.number_format((float) ($mov->total_informado ?? 0), 2, ',', '.');
        if ($mov->diferenca !== null) {
            $dif = (float) $mov->diferenca;
            $body .= ' · Diferença (físico): R$ '.number_format($dif, 2, ',', '.');
            if (abs($dif) >= 0.01) {
                $body .= ' (sem ajuste automático de saldo).';
            }
        }
        $body .= ' Use F6 para imprimir.';

        Notification::make()
            ->title('Caixa fechado.')
            ->body($body)
            ->success()
            ->send();
    }

    public function openCaixaFechamentoPrint(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.print')) {
            return;
        }

        if (! $this->caixaFecharConcluido) {
            $this->caixaMovimentoAlert = 'Impressão disponível somente após confirmar o fechamento.';

            return;
        }

        $movimentoId = (int) ($this->caixaFecharLocked['movimento_id'] ?? 0);
        if ($movimentoId <= 0) {
            $this->caixaMovimentoAlert = 'Movimento de fechamento não identificado para impressão.';

            return;
        }

        $mov = CaixaLivroMovimento::query()->whereKey($movimentoId)->first();
        if (! $mov || ! $mov->isFechado()) {
            $this->caixaMovimentoAlert = 'Só é possível imprimir movimento já fechado.';

            return;
        }

        if ($this->caixaFecharPrintFlags === []) {
            $this->caixaFecharPrintFlags = CaixaLivroFechamentoReportData::flagsResumido();
            $this->caixaFecharPrintModo = 'resumido';
        }

        $this->caixaFecharPrintOpen = true;
    }

    public function closeCaixaFechamentoPrint(): void
    {
        $this->caixaFecharPrintOpen = false;
    }

    public function aplicarModoImpressaoFechamento(string $modo): void
    {
        $modo = mb_strtolower(trim($modo), 'UTF-8');
        if ($modo === 'completo') {
            $this->caixaFecharPrintModo = 'completo';
            $this->caixaFecharPrintFlags = CaixaLivroFechamentoReportData::flagsCompleto();

            return;
        }

        $this->caixaFecharPrintModo = 'resumido';
        $this->caixaFecharPrintFlags = CaixaLivroFechamentoReportData::flagsResumido();
    }

    public function confirmarImpressaoFechamento(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.print')) {
            return;
        }

        $movimentoId = (int) ($this->caixaFecharLocked['movimento_id'] ?? 0);
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        $mov = CaixaLivroMovimento::query()
            ->whereKey($movimentoId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (! $mov || ! $mov->isFechado()) {
            $this->caixaMovimentoAlert = 'Impressão bloqueada: movimento não está fechado nesta empresa.';
            $this->caixaFecharPrintOpen = false;

            return;
        }

        $flags = app(CaixaLivroFechamentoReportData::class)->normalizeFlags($this->caixaFecharPrintFlags);
        $this->caixaFecharPrintFlags = $flags;

        $query = array_merge(
            [
                'auto' => 1,
                'embed' => 1,
            ],
            array_map(static fn (bool $v): int => $v ? 1 : 0, $flags),
        );

        $url = route('erp.reports.caixa-livro-fechamento', ['movimento' => $mov->id] + $query);

        $this->registrarEventoImpressaoFechamento($mov, $flags);
        $this->caixaFecharPrintOpen = false;
        $this->caixaFecharPreviewUrl = $url;
        $this->caixaFecharPreviewOpen = true;
    }

    public function closeCaixaFechamentoPreview(): void
    {
        $this->caixaFecharPreviewOpen = false;
        $this->caixaFecharPreviewUrl = null;
    }

    public function openCaixaFechamentoHistorico(): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.print')) {
            return;
        }

        $contaId = is_numeric($this->contaFilter) ? (int) $this->contaFilter : 0;
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($contaId <= 0 || $empresaId <= 0) {
            Notification::make()->title('Selecione a conta')->warning()->send();

            return;
        }

        $rows = CaixaLivroMovimento::query()
            ->with(['abertoPor:id,name', 'fechadoPor:id,name'])
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where('status', CaixaLivroMovimento::STATUS_FECHADO)
            ->orderByDesc('fechado_em')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $this->caixaFecharHistLista = $rows->map(static function (CaixaLivroMovimento $m): array {
            return [
                'id' => (int) $m->id,
                'aberto_em' => optional($m->aberto_em)?->format('d/m/Y H:i') ?? '—',
                'fechado_em' => optional($m->fechado_em)?->format('d/m/Y H:i') ?? '—',
                'aberto_por' => mb_strtoupper((string) ($m->abertoPor?->name ?? '—'), 'UTF-8'),
                'fechado_por' => mb_strtoupper((string) ($m->fechadoPor?->name ?? '—'), 'UTF-8'),
                'total_informado' => number_format((float) ($m->total_informado ?? 0), 2, ',', '.'),
                'diferenca' => $m->diferenca !== null
                    ? number_format((float) $m->diferenca, 2, ',', '.')
                    : '—',
            ];
        })->all();

        $this->caixaFecharHistOpen = true;
    }

    public function closeCaixaFechamentoHistorico(): void
    {
        $this->caixaFecharHistOpen = false;
        $this->caixaFecharHistLista = [];
    }

    public function selecionarHistoricoFechamento(int $movimentoId): void
    {
        if (! $this->erpAuthorizeOrNotify('caixa.print')) {
            return;
        }

        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $mov = CaixaLivroMovimento::query()
            ->whereKey($movimentoId)
            ->where('empresa_id', $empresaId)
            ->where('status', CaixaLivroMovimento::STATUS_FECHADO)
            ->first();

        if (! $mov) {
            Notification::make()->title('Fechamento não encontrado')->warning()->send();

            return;
        }

        $this->carregarSnapshotFechamentoPersistido((int) $mov->id);
        $this->caixaFecharForm = [
            'formas' => [],
            'observacao' => (string) ($mov->observacao_fechamento ?? ''),
        ];
        $this->caixaFecharConcluido = true;
        $this->caixaFecharPrintFlags = CaixaLivroFechamentoReportData::flagsResumido();
        $this->caixaFecharPrintModo = 'resumido';
        $this->caixaFecharHistOpen = false;
        $this->caixaFecharOpen = true;
        $this->caixaMovimentoAlert = '';
        $this->caixaFecharPrintOpen = true;
    }

    private function carregarSnapshotFechamentoPersistido(int $movimentoId): void
    {
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);
        $mov = CaixaLivroMovimento::query()
            ->with(['empresa', 'conta', 'abertoPor:id,name', 'fechadoPor:id,name'])
            ->whereKey($movimentoId)
            ->where('empresa_id', $empresaId)
            ->where('status', CaixaLivroMovimento::STATUS_FECHADO)
            ->first();

        if (! $mov) {
            throw new InvalidArgumentException('Fechamento persistido não encontrado.');
        }

        $empresa = $mov->empresa;
        $conferencia = is_array($mov->conferencia_formas) ? $mov->conferencia_formas : [];
        $linhas = is_array($conferencia['linhas'] ?? null) ? $conferencia['linhas'] : [];
        $totais = is_array($conferencia['totais'] ?? null) ? $conferencia['totais'] : [];
        $saldoFinAtual = round(
            (float) $mov->saldo_abertura
            + (float) ($mov->total_entradas ?? 0)
            - (float) ($mov->total_saidas ?? 0),
            2
        );

        $this->caixaFecharLocked = [
            'movimento_id' => (int) $mov->id,
            'empresa_id' => (int) $mov->empresa_id,
            'empresa' => mb_strtoupper((string) ($empresa?->razao_social ?: $empresa?->nome_fantasia ?: 'EMPRESA'), 'UTF-8'),
            'conta_id' => (int) $mov->caixa_conta_id,
            'conta' => mb_strtoupper((string) ($mov->conta?->nome ?? '—'), 'UTF-8'),
            'aberto_em' => optional($mov->aberto_em)?->format('d/m/Y H:i') ?? '—',
            'aberto_por' => mb_strtoupper((string) ($mov->abertoPor?->name ?? '—'), 'UTF-8'),
            'troco_inicial' => number_format((float) $mov->troco_inicial, 2, ',', '.'),
            'saldo_abertura_fin' => number_format((float) $mov->saldo_abertura, 2, ',', '.'),
            'entradas' => number_format((float) ($mov->total_entradas ?? 0), 2, ',', '.'),
            'saidas' => number_format((float) ($mov->total_saidas ?? 0), 2, ',', '.'),
            'trf_entrada' => number_format((float) ($mov->total_transferencias_entrada ?? 0), 2, ',', '.'),
            'trf_saida' => number_format((float) ($mov->total_transferencias_saida ?? 0), 2, ',', '.'),
            'saldo_financeiro' => number_format($saldoFinAtual, 2, ',', '.'),
            'saldo_movimento' => number_format((float) ($mov->saldo_calculado ?? 0), 2, ',', '.'),
            'saldo_calculado' => number_format((float) ($mov->saldo_calculado ?? 0), 2, ',', '.'),
            'usuario' => mb_strtoupper((string) ($mov->fechadoPor?->name ?? auth()->user()?->name ?? ''), 'UTF-8'),
            'exige_troco' => (float) $mov->troco_inicial > 0,
            'modalidades' => $linhas,
            'fechado' => true,
        ];

        $dif = $mov->diferenca !== null ? (float) $mov->diferenca : null;
        $this->caixaFecharResumo = [
            'total_informado' => number_format((float) ($mov->total_informado ?? $totais['informado'] ?? 0), 2, ',', '.'),
            'total_fisico' => number_format((float) ($totais['fisico'] ?? 0), 2, ',', '.'),
            'total_eletronico' => number_format((float) ($totais['eletronico'] ?? 0), 2, ',', '.'),
            'valor_contado' => number_format((float) ($mov->valor_contado ?? 0), 2, ',', '.'),
            'diferenca' => $dif !== null ? number_format($dif, 2, ',', '.') : null,
            'diferenca_disponivel' => $dif !== null,
            'diferenca_ok' => $dif !== null && abs($dif) < 0.005,
            'linhas' => $linhas,
        ];
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function registrarEventoImpressaoFechamento(CaixaLivroMovimento $mov, array $flags): void
    {
        if (! Schema::hasTable('caixa_livro_movimento_eventos')) {
            return;
        }

        $usuario = auth()->user();
        if (! $usuario instanceof User) {
            return;
        }

        CaixaLivroMovimentoEvento::query()->create([
            'caixa_livro_movimento_id' => $mov->id,
            'tipo' => CaixaLivroMovimentoEvento::TIPO_IMPRIMIR,
            'user_id' => $usuario->getKey(),
            'payload' => [
                'flags' => $flags,
                'modo' => $this->caixaFecharPrintModo,
            ],
            'created_at' => now(),
        ]);
    }
}
