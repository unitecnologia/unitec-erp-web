<?php

namespace App\Filament\Inventario\Pages;

use App\Models\Empresa;
use App\Models\User;
use App\Support\Erp\ErpContext;
use App\Support\Inventario\InventarioAcesso;
use App\Support\Inventario\InventarioContagemService;
use App\Support\Inventario\InventarioException;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class InventarioPage extends Page
{
    protected static ?string $slug = '/';

    protected static ?string $title = 'Inventário';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.inventario.page';

    public string $etapa = 'inicio';

    public string $busca = '';

    public string $nomeEtapa = '';

    /** @var list<array<string, mixed>> */
    public array $resultados = [];

    /** @var array<string, mixed>|null */
    public ?array $produto = null;

    public string $quantidade = '';

    /** @var list<array<string, mixed>> */
    public array $itens = [];

    /** @var list<array<string, mixed>> */
    public array $historico = [];

    /** @var list<array<string, mixed>> */
    public array $historicoItens = [];

    public ?int $historicoAberto = null;

    /** @var array<string, mixed> */
    public array $resumo = [];

    public string $bloqueio = '';

    public ?int $empresaId = null;

    public bool $salvando = false;

    /** @var array<int, string> */
    public array $empresasOpcoes = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User && InventarioAcesso::podeEntrar($user);
    }

    public function mount(): void
    {
        $this->empresasOpcoes = $this->listaEmpresas();
        $this->recarregar();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function podeContar(): bool
    {
        return InventarioAcesso::pode(Auth::user(), InventarioAcesso::CONTAGEM);
    }

    public function podeAplicar(): bool
    {
        return InventarioAcesso::pode(Auth::user(), InventarioAcesso::FINALIZAR);
    }

    /**
     * @return array<int, string>
     */
    public function listaEmpresas(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $ids = $user->accessibleEmpresaIds();

        if ($ids === []) {
            return [];
        }

        return Empresa::query()
            ->whereIn('id', $ids)
            ->where('ativo', true)
            ->orderBy('nome')
            ->pluck('nome', 'id')
            ->mapWithKeys(fn ($nome, $id): array => [(int) $id => (string) $nome])
            ->all();
    }

    public function trocarEmpresa(int $empresaId): void
    {
        $user = $this->usuario();

        if ($empresaId <= 0 || ! ErpContext::userCanAccessEmpresa($empresaId, $user)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        session(['erp_empresa_id' => $empresaId]);
        ErpContext::clearMemo();
        $this->redirect(static::getUrl(panel: 'inventario'));
    }

    public function irInicio(): void
    {
        $this->usuario();
        $this->etapa = 'inicio';
        $this->produto = null;
        $this->resultados = [];
        $this->busca = '';
        $this->quantidade = '';
        $this->recarregar();
    }

    public function abrirBalanco(): void
    {
        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            app(InventarioContagemService::class)->abrirBalanco($user);
            $this->recarregar();
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function criarEtapa(): void
    {
        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            app(InventarioContagemService::class)->criarEtapa($user, $this->nomeEtapa);
            $this->nomeEtapa = '';
            $this->entrarEtapa();
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function continuarEtapa(): void
    {
        $this->usuario();
        $this->entrarEtapa();
    }

    public function fecharEtapa(): void
    {
        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            app(InventarioContagemService::class)->fecharEtapa($user);
            $abertaId = (int) ($this->resumo['etapa_aberta_id'] ?? 0);
            $etapas = $this->resumo['etapas'] ?? [];

            foreach ($etapas as &$setor) {
                if ((int) ($setor['id'] ?? 0) === $abertaId) {
                    $setor['status'] = 'fechada';
                    $setor['aberta'] = false;
                }
            }
            unset($setor);

            $this->resumo['etapas'] = $etapas;
            $this->resumo['etapa_aberta'] = null;
            $this->resumo['etapa_aberta_id'] = null;
            $this->resumo['contados'] = 0;
            $this->etapa = 'inicio';
            $this->itens = [];
            $this->resultados = [];
            $this->produto = null;
            $this->busca = '';
            $this->quantidade = '';
            Notification::make()->title('Setor fechado. O estoque não foi alterado de novo.')->success()->send();
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function encerrarBalanco(): void
    {
        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            app(InventarioContagemService::class)->encerrarBalanco($user);
            Notification::make()->title('Balanço encerrado. Nenhum ajuste novo foi aplicado.')->success()->send();
            $this->irInicio();
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function updatedBusca(string $value): void
    {
        if ($this->etapa !== 'setor' || mb_strlen(trim($value)) < 2) {
            $this->resultados = [];

            return;
        }

        $this->buscar($value);
    }

    public function buscar(?string $termo = null): void
    {
        if ($termo !== null) {
            $this->busca = $termo;
        }

        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            $service = app(InventarioContagemService::class);
            $exato = $service->produtoExatoId($this->busca);

            if ($exato !== null) {
                $this->abrirProduto($exato);

                return;
            }

            $this->resultados = $service->buscar($user, $this->busca);
            $this->produto = null;
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function abrirProduto(int $productId): void
    {
        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            $this->produto = app(InventarioContagemService::class)->abrirProduto($user, $productId);
            $this->quantidade = '';
            $this->etapa = 'produto';
            $this->resultados = [];
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function salvarQuantidade(?string $valor = null): void
    {
        if ($valor !== null) {
            $this->quantidade = $valor;
        }

        if ($this->salvando) {
            return;
        }

        $productId = (int) ($this->produto['id'] ?? 0);
        $token = (string) ($this->produto['token'] ?? '');
        $cursor = (int) ($this->produto['cursor'] ?? 0);

        if ($productId <= 0 || $token === '') {
            $this->avisar('Abra o produto de novo antes de salvar.');

            return;
        }

        $this->salvando = true;

        try {
            $user = InventarioAcesso::exigir($this->usuario(), InventarioAcesso::CONTAGEM);
            $resultado = app(InventarioContagemService::class)->salvar(
                $user,
                $productId,
                $this->quantidade,
                $token,
                $cursor,
            );
            $notification = Notification::make()->title((string) $resultado['mensagem']);

            if ($resultado['repetido']) {
                $notification->info();
            } else {
                $notification->success();
            }

            $notification->send();
            $linha = $resultado['linha'] ?? null;

            if (is_array($linha) && ! $this->linhaJaListada((int) ($linha['id'] ?? 0))) {
                array_unshift($this->itens, $linha);
                $this->resumo['contados'] = count($this->itens);
            }

            $this->quantidade = '';
            $this->produto = null;
            $this->busca = '';
            $this->resultados = [];
            $this->etapa = 'setor';
            $this->dispatch('inventario-focar-busca');
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        } catch (AuthorizationException $e) {
            $this->avisar($e->getMessage() !== '' ? $e->getMessage() : 'Sem permissão para aplicar o ajuste.');
        } finally {
            $this->salvando = false;
        }
    }

    public function irHistorico(): void
    {
        $this->usuario();
        $this->etapa = 'historico';
        $this->historicoAberto = null;
        $this->historicoItens = [];
        $this->carregarHistorico();
    }

    public function abrirHistorico(int $contagemId): void
    {
        try {
            $user = $this->usuario();

            if (! InventarioAcesso::podeEntrar($user)) {
                throw new AuthorizationException('Sem permissão para esta operação.');
            }

            $this->historicoAberto = $contagemId;
            $this->historicoItens = app(InventarioContagemService::class)->historicoItens($user, $contagemId);
        } catch (InventarioException $e) {
            $this->avisar($e->getMessage());
        }
    }

    public function sair(): void
    {
        Auth::logout();
        session()->forget('erp_empresa_id');
        session()->invalidate();
        session()->regenerateToken();
        $this->redirect(filament()->getLoginUrl());
    }

    private function entrarEtapa(): void
    {
        $this->etapa = 'setor';
        $this->produto = null;
        $this->resultados = [];
        $this->carregarItens();
        $this->dispatch('inventario-focar-busca');
    }

    private function recarregar(): void
    {
        try {
            $user = $this->usuario();
            $this->bloqueio = '';
            $this->resumo = app(InventarioContagemService::class)->resumo($user);
            $this->empresaId = (int) ($this->resumo['empresa_id'] ?? 0);
        } catch (InventarioException $e) {
            $this->bloqueio = $e->getMessage();
            $this->resumo = [];
        }
    }

    private function carregarItens(): void
    {
        try {
            $this->itens = app(InventarioContagemService::class)->itensEtapa($this->usuario());
            $this->recarregar();
        } catch (InventarioException $e) {
            $this->itens = [];
            $this->avisar($e->getMessage());
        }
    }

    private function carregarHistorico(): void
    {
        try {
            $this->historico = app(InventarioContagemService::class)->historico($this->usuario());
        } catch (InventarioException $e) {
            $this->historico = [];
            $this->avisar($e->getMessage());
        }
    }

    private function linhaJaListada(int $id): bool
    {
        foreach ($this->itens as $item) {
            if ((int) ($item['id'] ?? 0) === $id) {
                return true;
            }
        }

        return false;
    }

    private function usuario(): User
    {
        $user = Auth::user();

        if (! $user instanceof User || ! InventarioAcesso::podeEntrar($user)) {
            throw new AuthorizationException('Sem permissão para esta operação.');
        }

        return $user;
    }

    private function avisar(string $mensagem): void
    {
        Notification::make()->title($mensagem)->warning()->send();
    }
}
