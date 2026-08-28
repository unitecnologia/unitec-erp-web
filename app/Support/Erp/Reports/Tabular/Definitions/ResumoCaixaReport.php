<?php

namespace App\Support\Erp\Reports\Tabular\Definitions;

use App\Models\PdvCaixaSessao;
use App\Models\User;
use App\Support\Erp\ErpTimezone;
use App\Support\Erp\Reports\Tabular\AbstractTabularReport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

/**
 * Relatório do menu = mesmo resumo de sessão do PDV (Data + Usuário + Caixa).
 */
class ResumoCaixaReport extends AbstractTabularReport
{
    public function slug(): string
    {
        return 'resumo-caixa';
    }

    public function title(): string
    {
        return 'RESUMO CAIXA';
    }

    public function permission(): string
    {
        return 'caixa.print';
    }

    public function columns(): array
    {
        return [
            'forma' => 'FORMA',
            'entrada' => 'ENTRADA',
            'saida' => 'SAÍDA',
        ];
    }

    public function defaultColumns(): array
    {
        return array_keys($this->columns());
    }

    public function numericColumns(): array
    {
        return ['entrada', 'saida'];
    }

    public function filterFields(): array
    {
        $request = request();
        $data = $this->resolveDataString($request);
        $usuarioId = (int) $request->query('usuario', 0);
        $sessoes = $this->sessoesDoDia($data, $usuarioId > 0 ? $usuarioId : null);

        return [
            [
                'key' => 'data',
                'label' => 'Data',
                'type' => 'date',
            ],
            [
                'key' => 'usuario',
                'label' => 'Usuário',
                'type' => 'select',
                'options' => $this->usuarioOptions($data),
            ],
            [
                'key' => 'caixa',
                'label' => 'Caixa',
                'type' => 'select',
                'options' => $this->caixaOptions($sessoes),
            ],
        ];
    }

    public function build(Request $request): array
    {
        $data = $this->resolveDataString($request);
        $usuarioId = (int) $request->query('usuario', 0);
        $caixaId = (int) $request->query('caixa', 0);
        $columns = $this->defaultColumns();

        $filters = [
            'data' => $data,
            'usuario' => $usuarioId > 0 ? (string) $usuarioId : '',
            'caixa' => $caixaId > 0 ? (string) $caixaId : '',
        ];

        if ($caixaId <= 0) {
            return $this->result(
                $filters,
                $columns,
                [],
                ['Selecione Data, Usuário e Caixa (abertura/fechamento) para ver o resumo.'],
                withTotals: false,
            );
        }

        $sessao = $this->findSessaoPermitida($caixaId, $data, $usuarioId > 0 ? $usuarioId : null);

        if (! $sessao) {
            return $this->result(
                $filters,
                $columns,
                [],
                ['Sessão de caixa não encontrada para os filtros informados.'],
                withTotals: false,
            );
        }

        $sessao->loadMissing(['user', 'terminal']);

        $filters['usuario'] = (string) (int) $sessao->user_id;
        $filters['caixa'] = (string) (int) $sessao->id;
        $filters['pdv_resumo_url'] = route('erp.reports.pdv-resumo-caixa', ['sessao' => $sessao->id]);

        // Preview usa iframe do cupom PDV; grade tabular não é exibida.
        return $this->result(
            $filters,
            $columns,
            [],
            [],
            withTotals: false,
        );
    }

    protected function resolveDataString(?Request $request = null): string
    {
        $request ??= request();
        $raw = trim((string) $request->query('data', ''));
        $hoje = ErpTimezone::toLocal()->toDateString();

        if ($raw === '') {
            return $hoje;
        }

        try {
            return Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return $hoje;
        }
    }

    protected function currentEmpresaId(): ?int
    {
        $id = (int) (session('erp_empresa_id') ?: Auth::user()?->empresa_id ?: 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return Collection<int, PdvCaixaSessao>
     */
    protected function sessoesDoDia(string $data, ?int $usuarioId = null): Collection
    {
        $query = $this->baseSessoesQuery($data);

        if ($usuarioId !== null && $usuarioId > 0) {
            $query->where('user_id', $usuarioId);
        }

        return $query
            ->orderBy('aberto_em')
            ->orderBy('id')
            ->get();
    }

    protected function baseSessoesQuery(string $data): Builder
    {
        [$inicioUtc, $fimUtc] = $this->diaUtcBounds($data);

        $query = PdvCaixaSessao::query()
            ->whereBetween('aberto_em', [$inicioUtc, $fimUtc]);

        $empresaId = $this->currentEmpresaId();

        if ($empresaId !== null && Schema::hasColumn((new PdvCaixaSessao)->getTable(), 'empresa_id')) {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function diaUtcBounds(string $data): array
    {
        $inicioLocal = Carbon::createFromFormat('Y-m-d H:i:s', $data.' 00:00:00', ErpTimezone::DEFAULT);
        $fimLocal = Carbon::createFromFormat('Y-m-d H:i:s', $data.' 23:59:59', ErpTimezone::DEFAULT);

        return [
            $inicioLocal->copy()->utc(),
            $fimLocal->copy()->utc(),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function usuarioOptions(string $data): array
    {
        $userIds = $this->baseSessoesQuery($data)
            ->whereNotNull('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $options = ['' => 'Selecione...'];

        if ($userIds === []) {
            return $options;
        }

        $users = User::query()
            ->whereIn('id', $userIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        foreach ($users as $user) {
            $options[(string) $user->id] = mb_strtoupper(trim((string) $user->name), 'UTF-8');
        }

        return $options;
    }

    /**
     * @param  Collection<int, PdvCaixaSessao>  $sessoes
     * @return array<string, string>
     */
    protected function caixaOptions(Collection $sessoes): array
    {
        $options = ['' => 'Selecione...'];

        foreach ($sessoes as $sessao) {
            $options[(string) $sessao->id] = $this->labelHorarioSessao($sessao);
        }

        return $options;
    }

    protected function labelHorarioSessao(PdvCaixaSessao $sessao): string
    {
        $abertura = $sessao->aberto_em
            ? ErpTimezone::toLocal($sessao->aberto_em)->format('H:i')
            : '--:--';

        if ($sessao->fechado_em === null) {
            return $abertura.' - (aberto)';
        }

        $fechamento = ErpTimezone::toLocal($sessao->fechado_em)->format('H:i');

        return $abertura.' - '.$fechamento;
    }

    protected function findSessaoPermitida(int $caixaId, string $data, ?int $usuarioId): ?PdvCaixaSessao
    {
        $query = $this->baseSessoesQuery($data)->where('id', $caixaId);

        if ($usuarioId !== null && $usuarioId > 0) {
            $query->where('user_id', $usuarioId);
        }

        return $query->first();
    }
}
