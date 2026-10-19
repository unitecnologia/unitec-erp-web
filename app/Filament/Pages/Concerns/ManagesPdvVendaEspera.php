<?php

namespace App\Filament\Pages\Concerns;

use App\Livewire\Erp\PdvHotPath;
use App\Models\PdvVendaEspera;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Pdv\PdvCaixaEsperaDescarteLog;
use App\Support\Erp\Pdv\PdvImportReserva;
use App\Support\Erp\Pdv\PdvVendaEsperaService;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait ManagesPdvVendaEspera
{
    public string $vendaEsperaSearch = '';

    /** @var list<array<string, mixed>> */
    public array $vendaEsperaResults = [];

    public ?int $selectedVendaEsperaIndex = null;

    public string $vendaEsperaMotivoDescarte = '';

    /** Identificador informado no modal Suspender (JOÃO, BALCÃO 01…). */
    public string $vendaEsperaIdentificador = '';

    public function openSuspenderVendaModal(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        $this->loadCupomFromSession();

        if (! $this->cupomTemItens()) {
            Notification::make()
                ->title('Não há itens para suspender.')
                ->warning()
                ->send();

            return;
        }

        $this->vendaEsperaIdentificador = $this->resolverIdentificadorSuspenderPadrao();
        $this->openPdvModal('suspender_venda');
        $this->dispatch('erp-pdv-focus-suspender-venda');
    }

    public function updatedVendaEsperaIdentificador(string $value): void
    {
        $upper = mb_strtoupper(trim($value), 'UTF-8');
        if ($this->vendaEsperaIdentificador !== $upper) {
            $this->vendaEsperaIdentificador = $upper;
        }
    }

    public function cancelSuspenderVenda(): void
    {
        $this->vendaEsperaIdentificador = '';
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    public function confirmarSuspenderVenda(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        $this->loadCupomFromSession();

        if (! $this->cupomTemItens()) {
            Notification::make()
                ->title('Não há itens para suspender.')
                ->warning()
                ->send();
            $this->closePdvModal();

            return;
        }

        // Identificador opcional: vazio → lista mostra "SEM NOME"; ID/sequência identificam a espera.
        $identificador = mb_strtoupper(trim($this->vendaEsperaIdentificador), 'UTF-8');
        $identificadorSalvo = $identificador !== '' ? $identificador : null;

        $user = Auth::user();
        if (! $user) {
            $this->notifyPdvError('Usuário não identificado.');

            return;
        }

        $service = app(PdvVendaEsperaService::class);
        $contexto = [
            'import' => [
                'orcamento_id' => session('erp.pdv.orcamento_id'),
                'venda_id' => session('erp.pdv.venda_id'),
                'cliente_id' => session('erp.pdv.import_cliente_id'),
                'cliente_nome' => session('erp.pdv.import_cliente_nome'),
                'desconto_venda' => session('erp.pdv.import_desconto_venda'),
                'acrescimo_venda' => session('erp.pdv.import_acrescimo_venda'),
            ],
            'vendedor' => [
                'id' => $this->vendedorId,
                'nome' => $this->vendedor,
            ],
            'price_table_id' => session('erp.pdv.price_table_id'),
            'cupom_iniciado_em' => session('erp.pdv.cupom_iniciado_em'),
            'identificador' => $identificadorSalvo,
        ];

        $total = (float) $this->cupomTotalValor();
        $itens = array_values($this->cupomItens);
        $payload = [
            'cliente_nome' => $identificadorSalvo,
            'vendedor_id' => $this->vendedorId,
            'vendedor_nome' => $this->vendedor !== '' ? $this->vendedor : null,
            'qtd_itens' => count($itens),
            'total' => $total,
            'snapshot' => $service->encode($service->buildSnapshot($itens, $contexto)),
            'status' => PdvVendaEspera::STATUS_PENDENTE,
            'finalizada_em' => null,
            'pdv_venda_id' => null,
        ];

        $esperaAtiva = $this->encontrarEsperaAtivaParaAtualizar((int) $user->id);
        $atualizou = false;

        if ($esperaAtiva !== null) {
            $esperaAtiva->forceFill($payload)->save();
            $espera = $esperaAtiva;
            $atualizou = true;
        } else {
            $espera = PdvVendaEspera::query()->create([
                'pdv_caixa_sessao_id' => $this->caixaSessaoId,
                'user_id' => $user->id,
                'sequencia' => $service->nextSequencia($this->caixaSessaoId),
                ...$payload,
            ]);
        }

        // Limpa vínculo da espera ativa antes do limparCupom (evita devolver como em_uso).
        $this->forgetEsperaAtivaId();
        $this->vendaEsperaIdentificador = '';
        $this->limparCupom(liberarImportacao: false);
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');

        $rotulo = $identificadorSalvo ?? 'SEM NOME';
        Notification::make()
            ->title($atualizou ? 'Venda em espera atualizada.' : 'Venda em espera salva.')
            ->body(sprintf(
                '%s — Espera #%d — R$ %s',
                $rotulo,
                $espera->sequencia,
                ErpMoney::formatBr($total),
            ))
            ->success()
            ->send();
    }

    /** @deprecated Use openSuspenderVendaModal + confirmarSuspenderVenda. */
    public function suspenderVendaEmEspera(): void
    {
        $this->openSuspenderVendaModal();
    }

    public function openVendasEsperaModal(): void
    {
        if (! $this->caixaAberto || ! $this->caixaSessaoId) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        $this->recuperarEsperasOrfasEmUso();
        $this->vendaEsperaSearch = '';
        $this->selectedVendaEsperaIndex = null;
        $this->vendaEsperaMotivoDescarte = '';
        $this->refreshVendasEsperaResults();
        $this->openPdvModal('vendas_espera');
        $this->dispatch('erp-pdv-focus-vendas-espera');
    }

    public function updatedVendaEsperaSearch(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');
        if ($this->vendaEsperaSearch !== $upper) {
            $this->vendaEsperaSearch = $upper;
        }

        $this->refreshVendasEsperaResults();
    }

    public function updatedVendaEsperaMotivoDescarte(string $value): void
    {
        $upper = mb_strtoupper($value, 'UTF-8');
        if ($this->vendaEsperaMotivoDescarte !== $upper) {
            $this->vendaEsperaMotivoDescarte = $upper;
        }
    }

    public function refreshVendasEsperaResults(): void
    {
        $user = Auth::user();
        if (! $this->caixaSessaoId || ! $user) {
            $this->vendaEsperaResults = [];

            return;
        }

        $term = trim($this->vendaEsperaSearch);
        $query = PdvVendaEspera::query()
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->pendentes()
            ->latest('id');

        if ($term !== '') {
            $query->where(function ($builder) use ($term): void {
                $builder->where('sequencia', 'like', '%'.$term.'%')
                    ->orWhere('cliente_nome', 'like', '%'.$term.'%')
                    ->orWhere('vendedor_nome', 'like', '%'.$term.'%');
            });
        }

        $this->vendaEsperaResults = $query
            ->get()
            ->map(fn (PdvVendaEspera $espera): array => [
                'id' => $espera->id,
                'numero' => $espera->sequencia,
                'cliente' => $espera->cliente_nome ?: 'SEM NOME',
                'operador' => $espera->vendedor_nome ?: '—',
                'itens' => $espera->qtd_itens,
                'total' => ErpMoney::formatBr((float) $espera->total),
                'data' => $espera->updated_at
                    ? ErpTimezone::toLocal($espera->updated_at)->format('d/m/Y')
                    : ($espera->created_at ? ErpTimezone::toLocal($espera->created_at)->format('d/m/Y') : '—'),
                'hora' => $espera->updated_at
                    ? ErpTimezone::toLocal($espera->updated_at)->format('H:i')
                    : ($espera->created_at ? ErpTimezone::toLocal($espera->created_at)->format('H:i') : '—'),
            ])
            ->values()
            ->all();

        $this->selectedVendaEsperaIndex = $this->vendaEsperaResults === [] ? null : 0;
    }

    public function selectVendaEsperaRow(int $index): void
    {
        if (isset($this->vendaEsperaResults[$index])) {
            $this->selectedVendaEsperaIndex = $index;
            $this->dispatch('erp-pdv-focus-vendas-espera-motivo');
        }
    }

    /**
     * Navegação ↑/↓ é local (JS). Mantido só por compatibilidade / atalho Delete.
     */
    public function moveVendaEsperaSelection(int $direction): void
    {
        $count = count($this->vendaEsperaResults);
        if ($count === 0) {
            return;
        }

        $current = $this->selectedVendaEsperaIndex ?? 0;
        $this->selectedVendaEsperaIndex = max(0, min($count - 1, $current + $direction));
    }

    public function recuperarVendaEmEsperaAt(int $index): void
    {
        if (! isset($this->vendaEsperaResults[$index])) {
            return;
        }

        $this->selectedVendaEsperaIndex = $index;
        $this->recuperarVendaEmEspera();
    }

    public function confirmarExcluirVendaEmEsperaAt(int $index): void
    {
        if (! isset($this->vendaEsperaResults[$index])) {
            return;
        }

        $this->selectedVendaEsperaIndex = $index;
        $this->confirmarExcluirVendaEmEspera();
    }

    public function recuperarVendaEmEspera(): void
    {
        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.');

            return;
        }

        if ($this->mesaBloqueiaOperacao('Venda em espera')) {
            return;
        }

        $this->loadCupomFromSession();

        if ($this->cupomTemItens()) {
            $this->notifyPdvError('Há uma venda em andamento. Suspenda ou cancele antes de recuperar outra.');

            return;
        }

        $row = $this->vendaEsperaResults[$this->selectedVendaEsperaIndex ?? -1] ?? null;
        $user = Auth::user();
        if (! $row || ! $user || ! $this->caixaSessaoId) {
            return;
        }

        $espera = PdvVendaEspera::query()
            ->whereKey((int) $row['id'])
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->pendentes()
            ->first();

        if (! $espera) {
            $this->refreshVendasEsperaResults();
            $this->notifyPdvError('Esta venda em espera não está mais disponível.');

            return;
        }

        $snapshot = app(PdvVendaEsperaService::class)->decode($espera);
        if ($snapshot === null) {
            $this->notifyPdvError('Não foi possível recuperar esta venda em espera.');

            return;
        }

        $itens = array_values($snapshot['cupom_itens']);
        if ($itens === []) {
            $this->notifyPdvError('A espera não contém itens para recuperar.');

            return;
        }

        $this->cupomItens = $itens;
        $this->selectedCupomIndex = null;
        $this->pdvMostrarDetalheItem = false;
        $this->persistCupomToSession();

        $contexto = is_array($snapshot['contexto'] ?? null) ? $snapshot['contexto'] : [];
        $import = is_array($contexto['import'] ?? null) ? $contexto['import'] : [];
        $avisoReserva = $this->renovarReservaImportadaDaEspera($import);
        session([
            'erp.pdv.orcamento_id' => $import['orcamento_id'] ?? null,
            'erp.pdv.venda_id' => $import['venda_id'] ?? null,
            'erp.pdv.import_cliente_id' => $import['cliente_id'] ?? null,
            'erp.pdv.import_cliente_nome' => $import['cliente_nome'] ?? null,
            'erp.pdv.import_desconto_venda' => $import['desconto_venda'] ?? null,
            'erp.pdv.import_acrescimo_venda' => $import['acrescimo_venda'] ?? null,
            'erp.pdv.price_table_id' => $contexto['price_table_id'] ?? null,
            'erp.pdv.cupom_iniciado_em' => $contexto['cupom_iniciado_em']
                ?? ($espera->created_at?->toIso8601String()),
        ]);
        $this->loadPdvPriceTableFromSession();

        $vendedor = is_array($contexto['vendedor'] ?? null) ? $contexto['vendedor'] : [];
        if (isset($vendedor['id']) && (int) $vendedor['id'] > 0) {
            $this->vendedorId = (int) $vendedor['id'];
            $this->vendedor = (string) ($vendedor['nome'] ?? $this->vendedor);
            $this->persistVendedorToSession();
        }

        if ($this->pdvHotPathEnabled ?? false) {
            $this->dispatch('erp-pdv-hot-reload-cupom')->to(PdvHotPath::class);
        }

        // Mantém o registro: re-suspender atualiza a mesma espera; F7 encerra.
        $espera->forceFill(['status' => PdvVendaEspera::STATUS_EM_USO])->save();
        $this->rememberEsperaAtivaId((int) $espera->id);

        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');

        $identificador = trim((string) ($espera->cliente_nome ?: ''));
        Notification::make()
            ->title('Venda em espera recuperada.')
            ->body(sprintf(
                '%sEspera #%d — %d item(ns) — R$ %s',
                $identificador !== '' ? $identificador.' — ' : '',
                $espera->sequencia,
                count($itens),
                ErpMoney::formatBr($this->cupomTotalValor()),
            ))
            ->success()
            ->send();

        if ($avisoReserva !== null) {
            Notification::make()
                ->title('Atenção: documento importado')
                ->body($avisoReserva.' A finalização será bloqueada se ele já tiver sido faturado.')
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * @param  array<string, mixed>  $import
     */
    protected function renovarReservaImportadaDaEspera(array $import): ?string
    {
        $avisos = [];

        foreach ([
            PdvImportReserva::ORCAMENTO => (int) ($import['orcamento_id'] ?? 0),
            PdvImportReserva::PEDIDO => (int) ($import['venda_id'] ?? 0),
        ] as $tipo => $id) {
            if ($id > 0 && ($erro = $this->reservarDocumentoImportado($tipo, $id)) !== null) {
                $avisos[] = $erro;
            }
        }

        return $avisos === [] ? null : implode(' ', $avisos);
    }

    public function requestExcluirVendaEmEspera(): void
    {
        if ($this->selectedVendaEsperaIndex === null) {
            return;
        }

        $this->dispatch('erp-pdv-focus-vendas-espera-motivo');
    }

    public function confirmarExcluirVendaEmEspera(): void
    {
        $motivo = trim($this->vendaEsperaMotivoDescarte);

        if ($motivo === '' || mb_strlen($motivo, 'UTF-8') < 10) {
            Notification::make()
                ->title('Informe o motivo do descarte.')
                ->body('Mínimo de 10 caracteres.')
                ->warning()
                ->send();
            $this->dispatch('erp-pdv-focus-vendas-espera-motivo');

            return;
        }

        $user = Auth::user();
        $row = $this->vendaEsperaResults[$this->selectedVendaEsperaIndex ?? -1] ?? null;

        if (! $row || ! $user || ! $this->caixaSessaoId) {
            return;
        }

        $espera = PdvVendaEspera::query()
            ->whereKey((int) $row['id'])
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->pendentes()
            ->first();

        if (! $espera) {
            $this->vendaEsperaMotivoDescarte = '';
            $this->refreshVendasEsperaResults();

            return;
        }

        $sessao = $this->caixaSessaoAtual();
        if (! $sessao || $sessao->fechado_em !== null) {
            Notification::make()
                ->title('Caixa fechado.')
                ->body('Não foi possível registrar o descarte.')
                ->warning()
                ->send();

            return;
        }

        $snapshot = app(PdvVendaEsperaService::class)->decode($espera);
        $contexto = is_array($snapshot['contexto'] ?? null) ? $snapshot['contexto'] : [];
        $import = is_array($contexto['import'] ?? null) ? $contexto['import'] : [];

        (new PdvCaixaEsperaDescarteLog())->registrar($sessao, $espera, $motivo);
        $espera->delete();

        $this->liberarDocumentosImportados(
            (int) ($import['orcamento_id'] ?? 0),
            (int) ($import['venda_id'] ?? 0),
        );

        $this->vendaEsperaMotivoDescarte = '';
        $this->selectedVendaEsperaIndex = null;
        $this->refreshVendasEsperaResults();

        Notification::make()
            ->title('Venda em espera descartada.')
            ->success()
            ->send();
    }

    public function cancelVendaEmEspera(): void
    {
        $this->vendaEsperaMotivoDescarte = '';
        $this->closePdvModal();
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function vendasEmEsperaPendentesCount(): int
    {
        return $this->caixaSessaoId
            ? (int) PdvVendaEspera::query()
                ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
                ->pendentes()
                ->count()
            : 0;
    }

    /**
     * Após F7: encerra a espera ativa (histórico preservado, fora da lista de recuperação).
     */
    protected function encerrarEsperaAtivaAposVenda(?int $pdvVendaId): void
    {
        $id = $this->esperaAtivaId();
        if ($id <= 0 || ! $this->esperaStatusDisponivel()) {
            $this->forgetEsperaAtivaId();

            return;
        }

        $payload = [
            'status' => PdvVendaEspera::STATUS_FINALIZADA,
            'finalizada_em' => now(),
        ];
        if ($pdvVendaId !== null && $pdvVendaId > 0) {
            $payload['pdv_venda_id'] = $pdvVendaId;
        }

        PdvVendaEspera::query()
            ->whereKey($id)
            ->where('status', PdvVendaEspera::STATUS_EM_USO)
            ->update($payload);

        $this->forgetEsperaAtivaId();
    }

    /**
     * F6 confirmado sobre venda recuperada: descarta a espera (histórico no caixa)
     * para não voltar à lista de recuperação.
     */
    protected function descartarEsperaAtivaAposCancelamentoCupom(): void
    {
        $id = $this->esperaAtivaId();
        if ($id <= 0 || ! $this->caixaSessaoId) {
            return;
        }

        $user = Auth::user();
        $query = PdvVendaEspera::query()
            ->whereKey($id)
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId);

        if ($user) {
            $query->where('user_id', $user->id);
        }

        if ($this->esperaStatusDisponivel()) {
            $query->where('status', PdvVendaEspera::STATUS_EM_USO);
        }

        $espera = $query->first();
        if (! $espera) {
            $this->forgetEsperaAtivaId();

            return;
        }

        $sessao = $this->caixaSessaoAtual();
        $snapshot = app(PdvVendaEsperaService::class)->decode($espera);
        $contexto = is_array($snapshot['contexto'] ?? null) ? $snapshot['contexto'] : [];
        $import = is_array($contexto['import'] ?? null) ? $contexto['import'] : [];

        if ($sessao && $sessao->fechado_em === null) {
            (new PdvCaixaEsperaDescarteLog())->registrar(
                $sessao,
                $espera,
                'CANCELADA NO PDV (F6)',
            );
        }

        $espera->delete();

        $this->liberarDocumentosImportados(
            (int) ($import['orcamento_id'] ?? 0),
            (int) ($import['venda_id'] ?? 0),
        );

        $this->forgetEsperaAtivaId();
    }

    /**
     * Limpar cupom sem F6 (mesa/caixa): devolve a espera à lista com o último snapshot.
     * F6 chama descartarEsperaAtivaAposCancelamentoCupom antes e este método vira no-op.
     */
    protected function devolverEsperaAtivaSeAbandonada(): void
    {
        $id = $this->esperaAtivaId();
        if ($id <= 0) {
            return;
        }

        if ($this->esperaStatusDisponivel()) {
            PdvVendaEspera::query()
                ->whereKey($id)
                ->where('status', PdvVendaEspera::STATUS_EM_USO)
                ->update(['status' => PdvVendaEspera::STATUS_PENDENTE]);
        }

        $this->forgetEsperaAtivaId();
    }

    protected function esperaStatusDisponivel(): bool
    {
        return Schema::hasColumn('pdv_venda_esperas', 'status');
    }

    protected function esperaAtivaId(): int
    {
        return (int) session('erp.pdv.espera_id', 0);
    }

    protected function rememberEsperaAtivaId(int $id): void
    {
        if ($id > 0) {
            session(['erp.pdv.espera_id' => $id]);
        } else {
            $this->forgetEsperaAtivaId();
        }
    }

    protected function forgetEsperaAtivaId(): void
    {
        session()->forget('erp.pdv.espera_id');
    }

    protected function encontrarEsperaAtivaParaAtualizar(int $userId): ?PdvVendaEspera
    {
        $id = $this->esperaAtivaId();
        if ($id <= 0 || ! $this->caixaSessaoId) {
            return null;
        }

        return PdvVendaEspera::query()
            ->whereKey($id)
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $userId)
            ->where('status', PdvVendaEspera::STATUS_EM_USO)
            ->first();
    }

    protected function resolverIdentificadorSuspenderPadrao(): string
    {
        $ativa = null;
        $user = Auth::user();
        if ($user) {
            $ativa = $this->encontrarEsperaAtivaParaAtualizar((int) $user->id);
        }

        // Re-suspender: preserva o valor anterior (inclusive vazio, se o usuário limpou).
        if ($ativa !== null) {
            return filled($ativa->cliente_nome)
                ? mb_strtoupper(trim((string) $ativa->cliente_nome), 'UTF-8')
                : '';
        }

        $importNome = trim((string) session('erp.pdv.import_cliente_nome', ''));
        if ($importNome !== '') {
            return mb_strtoupper($importNome, 'UTF-8');
        }

        return '';
    }

    /**
     * Espera marcada em_uso sem sessão local (crash/aba fechada) volta a pendente.
     */
    protected function recuperarEsperasOrfasEmUso(): void
    {
        if (! $this->caixaSessaoId || ! Schema::hasColumn('pdv_venda_esperas', 'status')) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            return;
        }

        $ativaId = $this->esperaAtivaId();

        PdvVendaEspera::query()
            ->where('pdv_caixa_sessao_id', $this->caixaSessaoId)
            ->where('user_id', $user->id)
            ->where('status', PdvVendaEspera::STATUS_EM_USO)
            ->when($ativaId > 0, fn ($q) => $q->where('id', '!=', $ativaId))
            ->update(['status' => PdvVendaEspera::STATUS_PENDENTE]);
    }
}
