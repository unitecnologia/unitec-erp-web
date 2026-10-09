<?php

namespace App\Filament\Pages\Concerns;

use App\Livewire\Erp\PdvHotPath;
use App\Models\PdvMesa;
use App\Support\Erp\ErpMoney;
use App\Support\Erp\Pdv\PdvDavCupomLayout;
use App\Support\Erp\Pdv\PdvMesaCredencial;
use App\Support\Erp\Pdv\PdvMesaService;
use App\Support\Erp\Pdv\PdvMesaSessao;
use App\Support\Erp\Printing\Documents\PdvMesaCupomPrintDocument;
use App\Support\Erp\Printing\PrintFacade;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

/**
 * Mesas no PDV. A mesa aberta é o próprio cupom do PDV: mesma pesquisa, lançamento,
 * descontos e fechamento (F7, inclusive "Dividir a conta por"). Cada alteração do cupom
 * é gravada na mesa; venda, estoque, financeiro e NFC-e só nascem no fechamento.
 *
 * O painel lateral é JS puro (wire:ignore) alimentado por PdvMesasPulsoController:
 * não re-renderiza a página e não existe para terminais sem a flag Mesas.
 */
trait ManagesPdvMesas
{
    public string $mesaTransferirDestino = '';

    /**
     * @return array{quantidade: int, inatividade: int, url: string, credencial: string, mesa: array{numero: int, id: int, token: string, aguardando: bool}|null}
     */
    public function getPdvMesasPainelProperty(): array
    {
        $config = $this->pdvConfig();
        $mesa = PdvMesaSessao::atual();

        return [
            'quantidade' => PdvMesaService::quantidade($config->empresa()),
            'inatividade' => PdvMesaService::inatividadeMinutos($config->empresa()),
            'url' => route('erp.pdv.mesas.pulso'),
            'credencial' => $this->emitirCredencialMesas(),
            'mesa' => $mesa ? ['numero' => $mesa['numero'], 'id' => $mesa['id'], 'token' => $mesa['token'], 'aguardando' => $mesa['aguardando']] : null,
        ];
    }

    public function renovarCredencialMesas(): string
    {
        $this->skipRender();

        return $this->pdvExibeMesas ? $this->emitirCredencialMesas() : '';
    }

    public function abrirMesa(int $numero): void
    {
        if (! $this->pdvExibeMesas) {
            return;
        }

        if (! $this->caixaAberto) {
            $this->notifyPdvError('Caixa fechado.', 'Abra o caixa com F2 antes de abrir uma mesa.');

            return;
        }

        if ($this->activeModal !== null) {
            return;
        }

        $atual = PdvMesaSessao::atual();

        if ($atual !== null && $atual['numero'] === $numero) {
            $this->dispatchMesaContexto();
            $this->dispatch('erp-pdv-focus-search');

            return;
        }

        $empresaId = (int) ($this->pdvConfig()->empresa()?->id ?? 0);
        $service = $this->mesaService();

        if ($numero < 1
            || ($numero > PdvMesaService::quantidade($this->pdvConfig()->empresa()) && ! $service->mesaOcupada($empresaId, $numero))) {
            $this->notifyPdvError('Mesa inexistente.', 'Informe um número entre 1 e '.PdvMesaService::quantidade($this->pdvConfig()->empresa()).'.');

            return;
        }

        $this->loadCupomFromSession();

        if ($atual === null && $this->cupomTemItens()) {
            $this->notifyPdvError(
                'Há uma venda de balcão em andamento.',
                'Finalize (F7), suspenda ou cancele (F6) antes de abrir uma mesa.',
            );

            return;
        }

        try {
            $resultado = $service->reservar($empresaId, $numero, null, $this->donoReservaMesa());

            if (! $resultado['ok']) {
                $this->notifyPdvError($resultado['erro']);
                $this->dispatch('erp-pdv-mesas-poll');

                return;
            }

            if ($atual !== null) {
                $service->liberar($atual['id'], $atual['token']);
            }
        } catch (\Throwable $e) {
            report($e);
            $this->notifyPdvError('Não foi possível abrir a mesa.', 'Verifique a conexão com o banco e tente novamente.');

            return;
        }

        $this->carregarMesaNoCupom($resultado['mesa'], $resultado['token']);
    }

    /** Sai da mesa (itens continuam gravados) e volta ao balcão. */
    public function sairDaMesa(): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            $this->skipRender();
            $this->dispatchMesaContexto();

            return;
        }

        if ($this->activeModal !== null) {
            return;
        }

        $cupom = session('erp.pdv.cupom', []);
        $qtdItens = is_array($cupom) ? count($cupom) : 0;

        $this->limparCupom();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title('Balcão.')
            ->body($qtdItens > 0
                ? PdvMesaService::rotulo($mesa['numero']).' mantida com '.$qtdItens.' item(ns).'
                : PdvMesaService::rotulo($mesa['numero']).' liberada.')
            ->success()
            ->send();
    }

    /**
     * Chamado pelo painel após o tempo de inatividade da empresa sem tecla/clique do operador.
     * Grava o que está na sessão, libera a reserva e volta ao balcão; a comanda fica na mesa.
     * Com modal aberto (ex.: Finalizar aguardando pagamento) não sai: a operação está em andamento.
     */
    public function sairDaMesaPorInatividade(): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            $this->skipRender();
            $this->dispatchMesaContexto();

            return;
        }

        if ($this->activeModal !== null || $this->overlayPersonOpen || $this->overlayProductOpen) {
            $this->skipRender();

            return;
        }

        $this->loadCupomFromSession();
        $qtdItens = count($this->cupomItens);

        if (PdvMesaSessao::sincronizarCupom(array_values($this->cupomItens)) === PdvMesaSessao::PERDIDA) {
            $this->tratarReservaMesaPerdida();

            return;
        }

        $this->limparCupom();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title(PdvMesaService::rotulo($mesa['numero']).' fechada por inatividade.')
            ->body($qtdItens > 0
                ? 'Comanda salva com '.$qtdItens.' item(ns). PDV em modo balcão.'
                : 'PDV em modo balcão.')
            ->info()
            ->send();
    }

    public function openTransferirMesa(): void
    {
        if (! $this->pdvExibeMesas) {
            return;
        }

        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            $this->notifyPdvError('Nenhuma mesa aberta.', 'Abra a mesa (duplo clique no painel Mesas) para transferir.');

            return;
        }

        if ($mesa['aguardando']) {
            $this->notifyPdvError(
                PdvMesaService::rotulo($mesa['numero']).' aguardando fechamento.',
                'Reabra a mesa para transferir.',
            );

            return;
        }

        $this->loadCupomFromSession();

        if (! $this->cupomTemItens()) {
            $this->notifyPdvError('A mesa não possui itens para transferir.');

            return;
        }

        $this->mesaTransferirDestino = '';
        $this->activeModal = 'mesa_transferir';
        $this->dispatch('erp-pdv-modal-opened', modal: 'mesa_transferir');
    }

    public function confirmarTransferirMesa(): void
    {
        $mesa = PdvMesaSessao::atual();
        $destino = (int) preg_replace('/\D/', '', $this->mesaTransferirDestino);

        if ($mesa === null) {
            $this->cancelTransferirMesa();

            return;
        }

        $empresa = $this->pdvConfig()->empresa();
        $empresaId = (int) ($empresa?->id ?? 0);

        if ($destino < 1 || $destino > PdvMesaService::quantidade($empresa)) {
            $this->notifyPdvError('Mesa de destino inválida.', 'Informe um número entre 1 e '.PdvMesaService::quantidade($empresa).'.');

            return;
        }

        // Garante que a mesa de origem tem exatamente o que está na tela antes de mover.
        $this->loadCupomFromSession();
        if (PdvMesaSessao::sincronizarCupom(array_values($this->cupomItens)) === PdvMesaSessao::PERDIDA) {
            $this->activeModal = null;
            $this->tratarReservaMesaPerdida();

            return;
        }

        $resultado = $this->mesaService()->transferir($empresaId, $mesa['id'], $mesa['token'], $destino, $this->donoReservaMesa());

        if (! $resultado['ok']) {
            $this->notifyPdvError($resultado['erro']);
            $this->dispatch('erp-pdv-mesas-poll');

            return;
        }

        $this->activeModal = null;
        $this->mesaTransferirDestino = '';
        PdvMesaSessao::definir($resultado['mesa'], $resultado['token'], $this->mesaReservaSegundos());
        $this->dispatchMesaContexto();
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title(PdvMesaService::rotulo($mesa['numero']).' transferida para a '.$resultado['mesa']->rotulo().'.')
            ->success()
            ->send();
    }

    public function cancelTransferirMesa(): void
    {
        $this->mesaTransferirDestino = '';
        $this->activeModal = null;
        $this->dispatch('erp-pdv-focus-search');
    }

    /**
     * Outro terminal assumiu a mesa (reserva expirada). Descarta só o cupom local;
     * o que está gravado na mesa permanece.
     */
    #[On('erp-pdv-mesa-reserva-perdida')]
    public function tratarReservaMesaPerdida(): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            return;
        }

        PdvMesaSessao::esquecer();
        $this->limparCupom();

        Notification::make()
            ->title(PdvMesaService::rotulo($mesa['numero']).' foi assumida por outro terminal.')
            ->body('A reserva expirou por inatividade. Lançamentos feitos depois disso nesta tela não foram gravados; reabra a mesa para conferir.')
            ->warning()
            ->persistent()
            ->send();
    }

    /** Pedido completo da mesa (Ctrl+S). Não fecha nem bloqueia a mesa. */
    public function imprimirPedidoMesa(): void
    {
        if ($this->mesaAguardandoSoParcial()) {
            return;
        }

        $mesa = $this->mesaParaImprimir();

        if ($mesa === null) {
            return;
        }

        $this->enviarImpressaoMesa($mesa, PdvDavCupomLayout::MESA_PEDIDO, null, $this->pdvConfig()->viasPedido());

        Notification::make()
            ->title('Pedido da '.$mesa->rotulo().' enviado para impressão.')
            ->success()
            ->send();
    }

    /** Item da linha (ícone de impressora na grade) ou o item selecionado (Ctrl+E). */
    #[On('erp-pdv-mesa-imprimir-item')]
    public function imprimirItemMesa(?int $index = null): void
    {
        if ($this->mesaAguardandoSoParcial()) {
            return;
        }

        if ($index === null) {
            $this->loadCupomFromSession();
            $index = $this->pdvMostrarDetalheItem ? $this->selectedCupomIndex : null;
        }

        if ($index === null) {
            if (PdvMesaSessao::atual() !== null) {
                $this->notifyPdvError('Selecione o item.', 'Clique no item da grade (ou no ícone de impressora da linha) para imprimir.');
            } else {
                $this->notifyPdvError('Nenhuma mesa aberta.', 'Abra a mesa (duplo clique no painel Mesas) para imprimir.');
            }

            return;
        }

        $mesa = $this->mesaParaImprimir();

        if ($mesa === null) {
            return;
        }

        $item = $this->cupomItens[$index] ?? null;

        if (! is_array($item)) {
            $this->notifyPdvError('Item não encontrado na mesa.');

            return;
        }

        $this->enviarImpressaoMesa($mesa, PdvDavCupomLayout::MESA_ITEM, $index, $this->pdvConfig()->viasImpressao());

        Notification::make()
            ->title('Item enviado para impressão.')
            ->body($mesa->rotulo().' · '.mb_strtoupper((string) ($item['descricao'] ?? ''), 'UTF-8'))
            ->success()
            ->send();
    }

    /**
     * Pré-conta (sem valor fiscal). Na primeira impressão pergunta se saiu corretamente:
     * só com "Sim" a mesa passa a aguardar fechamento. Falha/cancelamento não bloqueia nada.
     * Com a mesa já aguardando, é a reimpressão.
     */
    public function imprimirParcialMesa(): void
    {
        $mesa = $this->mesaParaImprimir();

        if ($mesa === null) {
            return;
        }

        $this->enviarImpressaoMesa($mesa, PdvDavCupomLayout::MESA_PARCIAL, null, $this->pdvConfig()->viasImpressao());

        if ($mesa->aguardandoFechamento()) {
            Notification::make()
                ->title('Pré-conta da '.$mesa->rotulo().' reenviada para impressão.')
                ->success()
                ->send();

            return;
        }

        $this->activeModal = 'mesa_parcial_confirmar';
        $this->dispatch('erp-pdv-modal-opened', modal: 'mesa_parcial_confirmar');
    }

    public function confirmarParcialMesa(): void
    {
        $mesa = PdvMesaSessao::atual();
        $this->activeModal = null;

        if ($mesa === null) {
            $this->dispatch('erp-pdv-focus-search');

            return;
        }

        if (! $this->mesaService()->definirAguardandoFechamento($mesa['id'], $mesa['token'], true)) {
            $this->tratarFalhaSituacaoMesa($mesa);

            return;
        }

        PdvMesaSessao::marcarAguardando(true);
        $this->selectedCupomIndex = null;
        $this->pdvMostrarDetalheItem = false;
        $this->recarregarGradeMesa();
        $this->dispatchMesaContexto();
        $this->dispatch('erp-pdv-mesas-poll');
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title(PdvMesaService::rotulo($mesa['numero']).' aguardando fechamento.')
            ->body('Itens bloqueados. Finalize (F7), reimprima a parcial ou reabra a mesa.')
            ->warning()
            ->send();
    }

    public function cancelarParcialMesa(): void
    {
        $this->activeModal = null;
        $this->dispatch('erp-pdv-focus-search');

        $mesa = PdvMesaSessao::atual();

        if ($mesa !== null && ! $mesa['aguardando']) {
            Notification::make()
                ->title(PdvMesaService::rotulo($mesa['numero']).' continua em atendimento.')
                ->body('Imprima a parcial novamente quando a impressora estiver pronta.')
                ->info()
                ->send();
        }
    }

    public function openReabrirMesa(): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null || ! $mesa['aguardando']) {
            return;
        }

        $this->activeModal = 'mesa_reabrir';
        $this->dispatch('erp-pdv-modal-opened', modal: 'mesa_reabrir');
    }

    public function confirmarReabrirMesa(): void
    {
        $mesa = PdvMesaSessao::atual();
        $this->activeModal = null;

        if ($mesa === null) {
            $this->dispatch('erp-pdv-focus-search');

            return;
        }

        if (! $this->mesaService()->definirAguardandoFechamento($mesa['id'], $mesa['token'], false)) {
            $this->tratarFalhaSituacaoMesa($mesa);

            return;
        }

        PdvMesaSessao::marcarAguardando(false);
        $this->recarregarGradeMesa();
        $this->dispatchMesaContexto();
        $this->dispatch('erp-pdv-mesas-poll');
        $this->dispatch('erp-pdv-focus-search');

        Notification::make()
            ->title(PdvMesaService::rotulo($mesa['numero']).' reaberta.')
            ->body('A mesa voltou a receber lançamentos.')
            ->success()
            ->send();
    }

    public function cancelReabrirMesa(): void
    {
        $this->activeModal = null;
        $this->dispatch('erp-pdv-focus-search');
    }

    /** Atalhos do menu Opções (Ctrl+S / Ctrl+N / Ctrl+E / Ctrl+B / Ctrl+M) do módulo Mesas. */
    public function moduleStubMesa(string $acao): void
    {
        if (! $this->pdvExibeMesas) {
            return;
        }

        if ($this->activeModal === 'options') {
            $this->activeModal = null;
        }

        match ($acao) {
            'Imprimir Pedido' => $this->imprimirPedidoMesa(),
            'Imprimir Item' => $this->imprimirItemMesa(),
            'Imprimir Parcial' => $this->imprimirParcialMesa(),
            'Abrir Mesa' => $this->dispatch('erp-pdv-mesas-focus'),
            'Transferir Mesa' => $this->openTransferirMesa(),
            'Atualiza Mesas' => $this->dispatch('erp-pdv-mesas-poll'),
            default => null,
        };
    }

    public function getCaixaTituloProperty(): string
    {
        if (! $this->caixaAberto) {
            return 'CAIXA FECHADO';
        }

        $mesa = PdvMesaSessao::atual();

        return $mesa !== null
            ? 'CAIXA ABERTO · '.mb_strtoupper(PdvMesaService::rotulo($mesa['numero']), 'UTF-8')
            : 'CAIXA ABERTO';
    }

    /** Após recarregar a página (F5) a estação retoma a mesa, se ninguém a assumiu. */
    protected function restaurarMesaAoMontar(): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            return;
        }

        if (! $this->pdvExibeMesas || ! $this->caixaAberto) {
            PdvMesaService::make()->liberar($mesa['id'], $mesa['token']);
            PdvMesaSessao::esquecer();
            session()->forget('erp.pdv.cupom');
            $this->forgetCupomIniciadoEm();
            $this->cupomItens = [];

            return;
        }

        $empresaId = (int) ($this->pdvConfig()->empresa()?->id ?? 0);
        $resultado = $this->mesaService()->reservar($empresaId, $mesa['numero'], $mesa['token'], $this->donoReservaMesa());

        if (! $resultado['ok']) {
            PdvMesaSessao::esquecer();
            session()->forget('erp.pdv.cupom');
            $this->forgetCupomIniciadoEm();
            $this->cupomItens = [];

            Notification::make()
                ->title(PdvMesaService::rotulo($mesa['numero']).' não pôde ser retomada.')
                ->body($resultado['erro'])
                ->warning()
                ->send();

            return;
        }

        $this->carregarMesaNoCupom($resultado['mesa'], $resultado['token']);
    }

    /** Chamado a cada gravação do cupom na sessão (persistCupomToSession). */
    protected function sincronizarMesaComCupom(): void
    {
        $status = PdvMesaSessao::sincronizarCupom(array_values($this->cupomItens));

        if ($status === PdvMesaSessao::PERDIDA) {
            $this->tratarReservaMesaPerdida();
        } elseif ($status === PdvMesaSessao::MUDOU) {
            $this->dispatch('erp-pdv-mesas-poll');
        }
    }

    /** Libera a reserva e solta a mesa desta estação. Os itens gravados ficam na mesa. */
    protected function desvincularMesaAtual(): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            return;
        }

        PdvMesaService::make()->liberar($mesa['id'], $mesa['token']);
        PdvMesaSessao::esquecer();
        $this->dispatchMesaContexto();
    }

    /** F6 na mesa: cancela todo o consumo gravado. */
    protected function zerarItensMesaAtual(): void
    {
        if (PdvMesaSessao::atual() !== null) {
            PdvMesaSessao::sincronizarCupom([]);
        }
    }

    /** Dentro da transação da venda: baixa a mesa ou aborta tudo se a reserva foi perdida. */
    protected function baixarMesaNaVenda(int $pdvVendaId): void
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa !== null) {
            PdvMesaService::make()->baixarNaVenda($mesa['id'], $mesa['token'], $pdvVendaId);
        }
    }

    protected function mesaBloqueiaOperacao(string $operacao): bool
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null) {
            return false;
        }

        $this->notifyPdvError(
            $operacao.' indisponível com mesa aberta.',
            PdvMesaService::rotulo($mesa['numero']).' está em atendimento. Use "Balcão" no painel Mesas.',
        );

        return true;
    }

    /**
     * Mesa aguardando fechamento: lançar, excluir ou alterar itens fica bloqueado
     * (Finalizar, Reimprimir Parcial e Reabrir continuam liberados).
     */
    protected function mesaBloqueiaAlteracao(): bool
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null || ! $mesa['aguardando']) {
            return false;
        }

        $this->notifyPdvError(
            PdvMesaService::rotulo($mesa['numero']).' aguardando fechamento.',
            'Itens bloqueados após a pré-conta. Use "Reabrir" no painel Mesas para alterar.',
        );
        $this->dispatch('erp-pdv-erro-beep');

        return true;
    }

    /**
     * Rede de segurança do persistCupomToSession: alteração que escapou dos bloqueios
     * é descartada e a tela volta ao que está gravado.
     */
    protected function descartarAlteracaoMesaAguardando(): bool
    {
        if (! PdvMesaSessao::aguardando()) {
            return false;
        }

        $this->mesaBloqueiaAlteracao();
        $this->loadCupomFromSession();
        $this->clearPdvSearch();

        if ($this->pdvHotPathEnabled ?? false) {
            $this->dispatch('erp-pdv-hot-reload-cupom')->to(PdvHotPath::class);
        }

        return true;
    }

    /** Sincroniza o cupom com a mesa e devolve a linha atualizada (ou null com aviso). */
    protected function mesaParaImprimir(): ?PdvMesa
    {
        if (! $this->pdvExibeMesas) {
            return null;
        }

        $atual = PdvMesaSessao::atual();

        if ($atual === null) {
            $this->notifyPdvError('Nenhuma mesa aberta.', 'Abra a mesa (duplo clique no painel Mesas) para imprimir.');

            return null;
        }

        $this->loadCupomFromSession();

        if (! $this->cupomTemItens()) {
            $this->notifyPdvError(PdvMesaService::rotulo($atual['numero']).' sem itens para imprimir.');

            return null;
        }

        if (PdvMesaSessao::sincronizarCupom(array_values($this->cupomItens)) === PdvMesaSessao::PERDIDA) {
            $this->tratarReservaMesaPerdida();

            return null;
        }

        $mesa = PdvMesa::query()->find($atual['id']);

        if (! $mesa || $mesa->reserva_token !== $atual['token']) {
            $this->tratarReservaMesaPerdida();

            return null;
        }

        return $mesa;
    }

    protected function enviarImpressaoMesa(PdvMesa $mesa, string $tipo, ?int $itemIndex, int $vias): void
    {
        $config = $this->pdvConfig();

        $this->js(PrintFacade::livewireOpenJs(new PdvMesaCupomPrintDocument(
            mesa: $mesa,
            tipo: $tipo,
            itemIndex: $itemIndex,
            empresa: $config->empresa(),
            atendente: (string) (Auth::user()?->name ?? ''),
            terminal: trim((string) ($config->terminal()?->nome ?? '')),
        ), $vias));
    }

    /** Aguardando fechamento: só Finalizar, Reimprimir Parcial e Reabrir. */
    private function mesaAguardandoSoParcial(): bool
    {
        $mesa = PdvMesaSessao::atual();

        if ($mesa === null || ! $mesa['aguardando']) {
            return false;
        }

        $this->notifyPdvError(
            PdvMesaService::rotulo($mesa['numero']).' aguardando fechamento.',
            'Disponível: Finalizar (F7), Reimprimir Parcial ou Reabrir.',
        );

        return true;
    }

    /** A coluna de impressão de item da grade depende da situação da mesa. */
    private function recarregarGradeMesa(): void
    {
        if ($this->pdvHotPathEnabled ?? false) {
            $this->dispatch('erp-pdv-hot-reload-cupom')->to(PdvHotPath::class);
        }
    }

    /** @param  array{id: int, numero: int, token: string}  $mesa */
    private function tratarFalhaSituacaoMesa(array $mesa): void
    {
        if (PdvMesa::query()->toBase()->where('id', $mesa['id'])->value('reserva_token') !== $mesa['token']) {
            $this->tratarReservaMesaPerdida();

            return;
        }

        $this->notifyPdvError('Não foi possível atualizar a '.PdvMesaService::rotulo($mesa['numero']).'.', 'Confira os itens e tente novamente.');
        $this->dispatch('erp-pdv-mesas-poll');
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function carregarMesaNoCupom(PdvMesa $mesa, string $token): void
    {
        $itens = PdvMesaService::make()->itens($mesa);

        PdvMesaSessao::definir($mesa, $token, $this->mesaReservaSegundos());

        $this->cupomItens = $itens;
        $this->selectedCupomIndex = null;
        $this->pdvMostrarDetalheItem = false;
        $this->pdvFlashQtd = null;
        $this->pdvFlashPreco = null;
        $this->pdvFlashTotal = null;
        $this->pdvPreviewFotoUrl = null;
        $this->pdvPreviewProductName = null;
        $this->clearPdvSearch();

        session(['erp.pdv.cupom' => $itens]);

        if ($mesa->aberta_em !== null) {
            session(['erp.pdv.cupom_iniciado_em' => $mesa->aberta_em->toIso8601String()]);
        } else {
            $this->forgetCupomIniciadoEm();
        }

        if ($this->pdvHotPathEnabled ?? false) {
            $this->dispatch('erp-pdv-hot-reload-cupom')->to(PdvHotPath::class);
        }

        $this->dispatchMesaContexto();
        $this->dispatch('erp-pdv-focus-search');

        if ($itens !== [] && $mesa->aguardandoFechamento()) {
            Notification::make()
                ->title($mesa->rotulo().' aguardando fechamento.')
                ->body(sprintf('%d item(ns) — R$ %s. Finalize (F7), reimprima a parcial ou reabra a mesa.', count($itens), ErpMoney::formatBr($this->cupomTotalValor())))
                ->warning()
                ->send();
        } elseif ($itens !== []) {
            Notification::make()
                ->title($mesa->rotulo().' aberta.')
                ->body(sprintf('%d item(ns) — R$ %s', count($itens), ErpMoney::formatBr($this->cupomTotalValor())))
                ->success()
                ->send();
        }
    }

    protected function dispatchMesaContexto(): void
    {
        $mesa = PdvMesaSessao::atual();

        $this->dispatch(
            'erp-pdv-mesa-contexto',
            numero: $mesa['numero'] ?? null,
            id: $mesa['id'] ?? null,
            token: $mesa['token'] ?? null,
            aguardando: (bool) ($mesa['aguardando'] ?? false),
        );
    }

    /**
     * @return array{user_id: int|null, terminal_id: int|null, nome: string}
     */
    protected function donoReservaMesa(): array
    {
        $terminal = trim((string) ($this->pdvConfig()->terminal()?->nome ?? ''));
        $usuario = trim((string) (Auth::user()?->name ?? ''));

        return [
            'user_id' => Auth::id() !== null ? (int) Auth::id() : null,
            'terminal_id' => $this->pdvConfig()->terminal()?->id,
            'nome' => mb_strtoupper(trim($terminal.($usuario !== '' ? ' ('.$usuario.')' : '')), 'UTF-8'),
        ];
    }

    protected function mesaReservaSegundos(): int
    {
        return PdvMesaService::reservaSegundos($this->pdvConfig()->empresa());
    }

    protected function mesaService(): PdvMesaService
    {
        return PdvMesaService::make($this->mesaReservaSegundos());
    }

    private function emitirCredencialMesas(): string
    {
        return PdvMesaCredencial::emitir(
            (int) ($this->pdvConfig()->empresa()?->id ?? 0),
            $this->pdvConfig()->terminal()?->id,
            Auth::id() !== null ? (int) Auth::id() : null,
        );
    }
}
