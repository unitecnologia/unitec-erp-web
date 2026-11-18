<?php

namespace App\Filament\Pages\Concerns;

use App\Models\Vendedor;
use App\Support\Erp\EmpresaParametros;
use App\Support\Erp\ErpContext;
use App\Support\Erp\Rh\OperadorDoUsuario;
use App\Support\Erp\VendedorAtivoEmpresa;
use Illuminate\Support\Facades\Auth;

/**
 * Operador da venda: sempre o vinculado ao usuário logado (RH → Operador).
 * Troca manual de operador no PDV foi removida (F3 virou Acesso Rápido).
 * Com "Solicitar vendedor a cada venda", o fechamento pede um vendedor da empresa
 * só para gravar na venda — o operador da sessão não muda.
 */
trait ManagesPdvVendedor
{
    public const PDV_VENDEDOR_VENDA_SESSION = 'erp.pdv.vendedor_venda';

    public ?int $vendedorId = null;

    /** @var list<array{id: int, codigo: string, nome: string, busca: string}> */
    public array $vendedorVendaOpcoes = [];

    protected function loadVendedorFromSession(): void
    {
        // Sem Operador no usuário logado não opera PDV (sem fallback LOJA).
        if (! $this->garantirOperadorDoUsuarioLogado(notify: false)) {
            $this->vendedorId = null;
            $this->vendedor = '';
            $this->persistVendedorToSession();

            return;
        }

        $sessionId = session('erp.pdv.vendedor_id');
        $empresaId = (int) (ErpContext::currentEmpresaId() ?? 0);

        if ($sessionId) {
            $vendedor = Vendedor::query()
                ->whereKey((int) $sessionId)
                ->where('ativo', true)
                ->first();

            if ($vendedor && $empresaId > 0
                && ! $vendedor->empresas()->where('empresas.id', $empresaId)->exists()) {
                $vendedor = null;
            }

            if ($vendedor) {
                $this->vendedorId = (int) $vendedor->id;
                $this->vendedor = mb_strtoupper((string) $vendedor->nome, 'UTF-8');
                $this->persistVendedorToSession();

                return;
            }
        }

        $this->aplicarVendedorDoUsuarioLogado();
    }

    /**
     * Garante Operador ativo vinculado ao usuário logado.
     * Sem vínculo: trava e orienta o cadastro.
     */
    protected function garantirOperadorDoUsuarioLogado(bool $notify = true): bool
    {
        $user = Auth::user();

        if (! $user) {
            if ($notify) {
                $this->notificarOperadorObrigatorio();
            }

            return false;
        }

        $vendedor = OperadorDoUsuario::vendedorAtivo(
            $user,
            (int) (ErpContext::currentEmpresaId() ?? 0) ?: null,
        );

        if (! $vendedor || ! $vendedor->ativo) {
            $this->vendedorId = null;
            $this->vendedor = '';
            $this->persistVendedorToSession();

            if ($notify) {
                $this->notificarOperadorObrigatorio();
            }

            return false;
        }

        return true;
    }

    protected function notificarOperadorObrigatorio(bool $voltarDashboard = false): void
    {
        $this->openPdvAcessoNegado(
            'Operador não vinculado ao usuário.',
            [
                'Não é possível abrir o caixa sem vínculo.',
                '1. Em <strong>RH → Funcionários</strong>, marque a aba <strong>Operador</strong> e vincule o usuário.',
                '2. Confira empresas/caixas em <strong>Permissões / Usuários</strong>.',
                '3. Confira PDV liberado e caixa.',
            ],
            $voltarDashboard,
            'Depois tente abrir o PDV novamente.',
        );
    }

    /**
     * Usa o Operador do usuário na empresa logada (RH → user_id + empresa; fallback users.vendedor_id).
     */
    protected function aplicarVendedorDoUsuarioLogado(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        $vendedor = OperadorDoUsuario::vendedorAtivo(
            $user,
            (int) (ErpContext::currentEmpresaId() ?? 0) ?: null,
        );

        if (! $vendedor || ! $vendedor->ativo) {
            return false;
        }

        $this->vendedorId = (int) $vendedor->id;
        $this->vendedor = mb_strtoupper((string) $vendedor->nome, 'UTF-8');
        $this->persistVendedorToSession();

        return true;
    }

    protected function persistVendedorToSession(): void
    {
        session([
            'erp.pdv.vendedor_id' => $this->vendedorId,
            'erp.pdv.vendedor' => $this->vendedor,
        ]);
    }

    /**
     * Abre a escolha de vendedor antes do fechamento. Não altera o operador da sessão.
     */
    protected function abrirSelecaoVendedorDaVenda(): void
    {
        $this->esquecerVendedorDaVenda();
        $this->vendedorVendaOpcoes = $this->listarVendedoresAtivosDaEmpresa(
            (int) (ErpContext::currentEmpresaId() ?? 0),
        );
        $this->openPdvModal('vendedor_venda');

        if ($this->vendedorVendaOpcoes === []) {
            $this->notifyPdvError(
                'Nenhum vendedor ativo nesta empresa.',
                'Cadastre em RH um vendedor ativo, que efetua venda e está vinculado à empresa.',
            );
        }
    }

    public function confirmarVendedorDaVenda(int $vendedorId): void
    {
        $vendedor = $this->buscarVendedorAtivoDaEmpresa($vendedorId);

        if ($vendedor === null) {
            $this->notifyPdvError('Selecione um vendedor ativo da empresa.');

            return;
        }

        $rotulo = VendedorAtivoEmpresa::rotulo($vendedor);

        session([
            self::PDV_VENDEDOR_VENDA_SESSION => [
                'id' => (int) $vendedor->id,
                'nome' => $rotulo,
            ],
        ]);
        $this->vendedorVendaOpcoes = [];
        $this->resetFinalizarForm();
        $this->openPdvModal('finalizar');
        $this->dispatch('erp-pdv-focus-finalizar');
    }

    public function cancelarVendedorDaVenda(): void
    {
        $this->esquecerVendedorDaVenda();
        $this->vendedorVendaOpcoes = [];
        $this->activeModal = null;
        $this->dispatch('erp-pdv-focus-search');
    }

    protected function esquecerVendedorDaVenda(): void
    {
        session()->forget(self::PDV_VENDEDOR_VENDA_SESSION);
    }

    /**
     * Vendedor gravado em pdv_vendas. Com o parâmetro desligado, permanece o operador.
     *
     * @return array{id: ?int, nome: ?string}
     */
    protected function vendedorParaGravarNaVenda(): array
    {
        if (! EmpresaParametros::solicitarVendedorCadaVenda()) {
            return [
                'id' => $this->vendedorId,
                'nome' => $this->vendedor,
            ];
        }

        $id = (int) (session(self::PDV_VENDEDOR_VENDA_SESSION.'.id') ?? 0);
        $vendedor = $this->buscarVendedorAtivoDaEmpresa($id);

        if ($vendedor === null) {
            return ['id' => null, 'nome' => null];
        }

        return [
            'id' => (int) $vendedor->id,
            'nome' => VendedorAtivoEmpresa::rotulo($vendedor),
        ];
    }

    protected function vendedorDaVendaConfirmado(): bool
    {
        if (! EmpresaParametros::solicitarVendedorCadaVenda()) {
            return true;
        }

        $id = (int) (session(self::PDV_VENDEDOR_VENDA_SESSION.'.id') ?? 0);

        return $this->buscarVendedorAtivoDaEmpresa($id) !== null;
    }

    protected function buscarVendedorAtivoDaEmpresa(int $vendedorId): ?Vendedor
    {
        if ($vendedorId <= 0) {
            return null;
        }

        return VendedorAtivoEmpresa::buscar(
            (int) (ErpContext::currentEmpresaId() ?? 0),
            $vendedorId,
        );
    }

    /**
     * @return list<array{id: int, codigo: string, nome: string, busca: string}>
     */
    protected function listarVendedoresAtivosDaEmpresa(int $empresaId): array
    {
        return VendedorAtivoEmpresa::listar($empresaId);
    }
}
