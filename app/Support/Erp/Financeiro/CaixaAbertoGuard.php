<?php

namespace App\Support\Erp\Financeiro;

use App\Models\CaixaConta;
use App\Models\CaixaLivroMovimento;
use App\Models\PdvCaixaSessao;
use App\Support\Erp\ErpContext;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Regra global: CAIXA GERAL da empresa precisa estar aberto (F8) para
 * finalizar vendas e gravar movimentações financeiras normais.
 *
 * Contas SUBCAIXA/COFRE com controle próprio (F8) também exigem movimento aberto.
 * BANCO e PDV operacional não usam abertura F8 própria; PDV ainda depende do GERAL.
 */
final class CaixaAbertoGuard
{
    public const MENSAGEM = 'CAIXA FECHADO. Abra o caixa pelo F8 antes de realizar esta operação.';

    public function tabelaPronta(): bool
    {
        return Schema::hasTable('caixa_livro_movimentos');
    }

    public function mensagem(): string
    {
        return self::MENSAGEM;
    }

    public function caixaGeralId(): int
    {
        return (int) CaixaConta::ensureCaixaGeral()->id;
    }

    public function movimentoAberto(int $empresaId, int $contaId): ?CaixaLivroMovimento
    {
        if (! $this->tabelaPronta() || $empresaId <= 0 || $contaId <= 0) {
            return null;
        }

        return CaixaLivroMovimento::query()
            ->where('empresa_id', $empresaId)
            ->where('caixa_conta_id', $contaId)
            ->where('status', CaixaLivroMovimento::STATUS_ABERTO)
            ->orderByDesc('id')
            ->first();
    }

    public function caixaGeralAberto(int $empresaId): bool
    {
        if (! $this->tabelaPronta() || $empresaId <= 0) {
            return true;
        }

        return $this->movimentoAberto($empresaId, $this->caixaGeralId()) !== null;
    }

    /**
     * Bloqueia finalização de venda / operação financeira da empresa.
     */
    public function assertEmpresaOperacional(?int $empresaId = null): void
    {
        if (! $this->tabelaPronta()) {
            return;
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa logada não encontrada.');
        }

        if (! $this->caixaGeralAberto($empresaId)) {
            throw new InvalidArgumentException(self::MENSAGEM);
        }
    }

    /**
     * Alias semântico para hubs de venda.
     */
    public function assertPodeFinalizarVenda(?int $empresaId = null): void
    {
        $this->assertEmpresaOperacional($empresaId);
    }

    /**
     * Antes de gravar em caixa_lancamentos na conta destino.
     * Sempre exige CAIXA GERAL aberto da empresa; SUBCAIXA/COFRE exigem o próprio F8.
     */
    public function assertPodeLancarNaConta(?int $contaId, ?int $empresaId = null): void
    {
        if (! $this->tabelaPronta()) {
            return;
        }

        $empresaId = $empresaId ?: (int) (ErpContext::currentEmpresaId() ?? 0);
        if ($empresaId <= 0) {
            throw new InvalidArgumentException('Empresa logada não encontrada.');
        }

        $this->assertEmpresaOperacional($empresaId);

        $contaId = (int) ($contaId ?? 0);
        if ($contaId <= 0) {
            return;
        }

        $conta = CaixaConta::query()->whereKey($contaId)->first();
        if (! $conta) {
            throw new InvalidArgumentException('Conta de caixa inválida.');
        }

        if (! $conta->pertenceOuCompartilhadaCom($empresaId)) {
            throw new InvalidArgumentException('Conta de caixa não pertence à empresa logada.');
        }

        // CAIXA GERAL já validado acima.
        if ($conta->isSistema()) {
            return;
        }

        // BANCO / PDV: sem abertura F8 própria.
        $tipo = (string) $conta->tipo;
        if ($tipo === CaixaConta::TIPO_BANCO || $tipo === CaixaConta::TIPO_PDV) {
            return;
        }

        // SUBCAIXA / COFRE com controle próprio (F8).
        if (! in_array($tipo, [CaixaConta::TIPO_SUBCAIXA, CaixaConta::TIPO_COFRE], true)) {
            return;
        }

        if ($this->movimentoAberto($empresaId, $contaId) === null) {
            throw new InvalidArgumentException(self::MENSAGEM);
        }
    }

    /**
     * Quantidade de sessões PDV abertas da empresa (F2).
     */
    public function sessoesPdvAbertas(int $empresaId): int
    {
        if ($empresaId <= 0 || ! Schema::hasTable('pdv_caixa_sessoes')) {
            return 0;
        }

        return (int) PdvCaixaSessao::query()
            ->where('empresa_id', $empresaId)
            ->whereNull('fechado_em')
            ->count();
    }

    /**
     * Impede fechar o CAIXA GERAL enquanto houver PDV operacional aberto na empresa.
     */
    public function assertPodeFecharConta(CaixaConta $conta, int $empresaId): void
    {
        if (! $conta->isSistema()) {
            return;
        }

        $abertas = $this->sessoesPdvAbertas($empresaId);
        if ($abertas > 0) {
            throw new InvalidArgumentException(
                'Não é possível fechar o CAIXA GERAL enquanto houver '
                .$abertas.' sessão(ões) PDV aberta(s) nesta empresa. Feche o PDV (F2) antes.'
            );
        }
    }
}
