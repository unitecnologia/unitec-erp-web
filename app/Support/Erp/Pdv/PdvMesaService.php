<?php

namespace App\Support\Erp\Pdv;

use App\Models\Empresa;
use App\Models\PdvMesa;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Mesas do PDV: persiste os itens sem gerar venda, estoque, financeiro ou NFC-e.
 *
 * Edição exclusiva por reserva (token secreto + validade curta). Toda escrita exige o
 * token: quem perdeu a reserva (expirou e outro terminal assumiu) não sobrescreve nada.
 * A venda nasce só no fechamento normal do PDV, que baixa a mesa na mesma transação.
 */
final class PdvMesaService
{
    public const INATIVIDADE_PADRAO_MIN = 1;

    public const INATIVIDADE_MAXIMA_MIN = 120;

    public const QTD_PADRAO = 20;

    public const QTD_MAXIMA = 300;

    public const LIVRE = 0;

    public const MINHA = 1;

    public const OUTRO_TERMINAL = 2;

    /** Validade da reserva: igual ao tempo de inatividade da mesa (parâmetro da empresa). */
    private int $reservaSegundos;

    public function __construct(?int $reservaSegundos = null)
    {
        $this->reservaSegundos = $reservaSegundos !== null && $reservaSegundos > 0
            ? $reservaSegundos
            : self::INATIVIDADE_PADRAO_MIN * 60;
    }

    public static function make(?int $reservaSegundos = null): self
    {
        return new self($reservaSegundos);
    }

    public static function inatividadeMinutos(?Empresa $empresa): int
    {
        return self::normalizarMinutos($empresa?->param_pdv_mesa_inatividade_min);
    }

    public static function reservaSegundos(?Empresa $empresa): int
    {
        return self::inatividadeMinutos($empresa) * 60;
    }

    /** Para a rota sem sessão (pulso), que só tem o id da empresa. */
    public static function reservaSegundosDaEmpresa(int $empresaId): int
    {
        $minutos = $empresaId > 0
            ? Empresa::query()->whereKey($empresaId)->value('param_pdv_mesa_inatividade_min')
            : null;

        return self::normalizarMinutos($minutos) * 60;
    }

    private static function normalizarMinutos(mixed $minutos): int
    {
        $minutos = (int) ($minutos ?? 0);

        return $minutos > 0 ? min($minutos, self::INATIVIDADE_MAXIMA_MIN) : self::INATIVIDADE_PADRAO_MIN;
    }

    public static function quantidade(?Empresa $empresa): int
    {
        $qtd = (int) ($empresa?->param_pdv_qtd_mesas ?? 0);

        return $qtd > 0 ? min($qtd, self::QTD_MAXIMA) : self::QTD_PADRAO;
    }

    public static function rotulo(int $numero): string
    {
        return 'Mesa '.str_pad((string) $numero, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array{user_id: int|null, terminal_id: int|null, nome: string}  $dono
     * @param  string|null  $tokenAnterior  token desta estação (reabertura após F5); gera um novo de qualquer forma
     * @return array{ok: true, mesa: PdvMesa, token: string}|array{ok: false, erro: string}
     */
    public function reservar(int $empresaId, int $numero, ?string $tokenAnterior, array $dono): array
    {
        if ($empresaId <= 0 || $numero < 1 || $numero > self::QTD_MAXIMA) {
            return ['ok' => false, 'erro' => 'Mesa inválida.'];
        }

        $mesa = $this->localizarOuCriar($empresaId, $numero);
        $token = Str::random(40);
        $agora = now();

        PdvMesa::query()
            ->toBase()
            ->where('id', $mesa->id)
            ->where(function ($query) use ($agora, $tokenAnterior): void {
                $query->whereNull('reserva_token')
                    ->orWhereNull('reservado_ate')
                    ->orWhere('reservado_ate', '<', $agora);

                if (filled($tokenAnterior)) {
                    $query->orWhere('reserva_token', $tokenAnterior);
                }
            })
            ->update($this->camposReserva($token, $dono, $agora));

        $mesa->refresh();

        if ($mesa->reserva_token !== $token) {
            $quem = trim((string) $mesa->reservado_nome);

            return [
                'ok' => false,
                'erro' => $mesa->rotulo().' em atendimento'.($quem !== '' ? ' em '.$quem : ' em outro terminal')
                    .'. Aguarde a liberação.',
            ];
        }

        return ['ok' => true, 'mesa' => $mesa, 'token' => $token];
    }

    public function renovar(int $mesaId, string $token, ?int $empresaId = null): bool
    {
        if ($mesaId <= 0 || $token === '') {
            return false;
        }

        $afetadas = $this->porToken($mesaId, $token, $empresaId)
            ->update(['reservado_ate' => now()->addSeconds($this->reservaSegundos)]);

        return $afetadas > 0 || $this->tokenAtual($mesaId) === $token;
    }

    public function liberar(int $mesaId, string $token, ?int $empresaId = null): void
    {
        if ($mesaId <= 0 || $token === '') {
            return;
        }

        $this->porToken($mesaId, $token, $empresaId)->update($this->camposSemReserva());
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return bool false quando a reserva não pertence mais a este token
     */
    public function salvarItens(int $mesaId, string $token, array $itens): bool
    {
        $itens = array_values($itens);
        $agora = now();
        $vazia = $itens === [];

        // Mesa aguardando fechamento (pré-conta impressa) não aceita alteração de itens.
        $afetadas = $this->porToken($mesaId, $token)->where('situacao', PdvMesa::SITUACAO_ATENDIMENTO)->update([
            'itens' => $vazia ? null : json_encode($itens, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'qtd_itens' => count($itens),
            'total' => $this->somaItens($itens),
            'aberta_em' => $vazia ? null : DB::raw("COALESCE(aberta_em, '".$agora->format('Y-m-d H:i:s')."')"),
            'reservado_ate' => $agora->copy()->addSeconds($this->reservaSegundos),
            'updated_at' => $agora,
        ]);

        return $afetadas > 0 || $this->tokenAtual($mesaId) === $token;
    }

    /**
     * Pré-conta confirmada (true) ou mesa reaberta (false). Exige a reserva deste terminal.
     *
     * @return bool false quando a reserva não pertence mais a este token
     */
    public function definirAguardandoFechamento(int $mesaId, string $token, bool $aguardando): bool
    {
        if ($mesaId <= 0 || $token === '') {
            return false;
        }

        $agora = now();
        $query = $this->porToken($mesaId, $token);

        if ($aguardando) {
            $query->where('qtd_itens', '>', 0);
        }

        $afetadas = $query->update([
            'situacao' => $aguardando ? PdvMesa::SITUACAO_AGUARDANDO_FECHAMENTO : PdvMesa::SITUACAO_ATENDIMENTO,
            'parcial_em' => $aguardando ? $agora : null,
            'reservado_ate' => $agora->copy()->addSeconds($this->reservaSegundos),
            'updated_at' => $agora,
        ]);

        return $afetadas > 0;
    }

    /**
     * Move os itens para uma mesa livre. As duas linhas ficam travadas na mesma ordem
     * (por id) para não haver deadlock com outra transferência simultânea.
     *
     * @param  array{user_id: int|null, terminal_id: int|null, nome: string}  $dono
     * @return array{ok: true, mesa: PdvMesa, token: string}|array{ok: false, erro: string}
     */
    public function transferir(int $empresaId, int $origemId, string $token, int $destinoNumero, array $dono): array
    {
        if ($destinoNumero < 1 || $destinoNumero > self::QTD_MAXIMA) {
            return ['ok' => false, 'erro' => 'Mesa de destino inválida.'];
        }

        $destinoId = (int) $this->localizarOuCriar($empresaId, $destinoNumero)->id;

        if ($destinoId === $origemId) {
            return ['ok' => false, 'erro' => 'Informe uma mesa diferente da atual.'];
        }

        return DB::transaction(function () use ($empresaId, $origemId, $destinoId, $token, $dono): array {
            $linhas = PdvMesa::query()
                ->whereIn('id', [$origemId, $destinoId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $origem = $linhas->get($origemId);
            $destino = $linhas->get($destinoId);
            $agora = now();

            if (! $origem || ! $destino || $origem->empresa_id !== $empresaId || $origem->reserva_token !== $token) {
                return ['ok' => false, 'erro' => 'A reserva desta mesa expirou ou foi assumida por outro terminal. Reabra a mesa.'];
            }

            if ($origem->aguardandoFechamento()) {
                return ['ok' => false, 'erro' => $origem->rotulo().' está aguardando fechamento. Reabra a mesa para transferir.'];
            }

            if ($origem->qtd_itens <= 0) {
                return ['ok' => false, 'erro' => $origem->rotulo().' não possui itens para transferir.'];
            }

            if ($destino->qtd_itens > 0) {
                return ['ok' => false, 'erro' => $destino->rotulo().' já está ocupada. Escolha uma mesa livre.'];
            }

            if ($destino->reserva_token !== null && $destino->reservado_ate !== null && $destino->reservado_ate->greaterThan($agora)) {
                $quem = trim((string) $destino->reservado_nome);

                return ['ok' => false, 'erro' => $destino->rotulo().' está em atendimento'.($quem !== '' ? ' em '.$quem : '').'.'];
            }

            $novoToken = Str::random(40);

            $destino->forceFill([
                'itens' => $origem->itens,
                'qtd_itens' => $origem->qtd_itens,
                'total' => $origem->total,
                'situacao' => PdvMesa::SITUACAO_ATENDIMENTO,
                'parcial_em' => null,
                'aberta_em' => $origem->aberta_em ?? $agora,
                ...$this->camposReserva($novoToken, $dono, $agora),
            ])->save();

            $origem->forceFill([
                'itens' => null,
                'qtd_itens' => 0,
                'total' => 0,
                'aberta_em' => null,
                ...$this->camposSemReserva(),
            ])->save();

            return ['ok' => true, 'mesa' => $destino->fresh(), 'token' => $novoToken];
        });
    }

    /**
     * Chamar dentro da transação que grava a venda: se a reserva não for mais deste
     * terminal, a exceção desfaz venda, estoque e financeiro (nada duplicado).
     */
    public function baixarNaVenda(int $mesaId, string $token, int $pdvVendaId): void
    {
        $mesa = PdvMesa::query()->whereKey($mesaId)->lockForUpdate()->first();

        if (! $mesa || $mesa->reserva_token !== $token) {
            throw new RuntimeException(
                ($mesa?->rotulo() ?? 'A mesa').' foi assumida por outro terminal após a reserva expirar. '
                .'A venda não foi gravada; reabra a mesa e confira os itens.'
            );
        }

        $mesa->forceFill([
            'itens' => null,
            'qtd_itens' => 0,
            'total' => 0,
            'situacao' => PdvMesa::SITUACAO_ATENDIMENTO,
            'parcial_em' => null,
            'aberta_em' => null,
            'ultima_pdv_venda_id' => $pdvVendaId,
            ...$this->camposSemReserva(),
        ])->save();
    }

    /**
     * Mesas ocupadas ou reservadas da empresa (as livres não vêm).
     *
     * @return list<array{0: int, 1: int, 2: float, 3: int, 4: string, 5: int}> [numero, qtd_itens, total, reserva, nome, situacao]
     */
    public function painel(int $empresaId, ?string $meuToken): array
    {
        $agora = now()->format('Y-m-d H:i:s');

        return PdvMesa::query()
            ->toBase()
            ->where('empresa_id', $empresaId)
            ->where(function ($query) use ($agora): void {
                $query->where('qtd_itens', '>', 0)
                    ->orWhere('reservado_ate', '>', $agora);
            })
            ->orderBy('numero')
            ->get(['numero', 'qtd_itens', 'total', 'situacao', 'reserva_token', 'reservado_nome', 'reservado_ate'])
            ->map(function (object $row) use ($agora, $meuToken): array {
                $token = $row->reserva_token;
                $ativa = $token !== null && $row->reservado_ate !== null && (string) $row->reservado_ate > $agora;

                $reserva = match (true) {
                    $token !== null && $meuToken !== null && hash_equals((string) $token, $meuToken) => self::MINHA,
                    $ativa => self::OUTRO_TERMINAL,
                    default => self::LIVRE,
                };

                return [
                    (int) $row->numero,
                    (int) $row->qtd_itens,
                    round((float) $row->total, 2),
                    $reserva,
                    $reserva === self::OUTRO_TERMINAL ? (string) ($row->reservado_nome ?? '') : '',
                    (int) $row->qtd_itens > 0 ? (int) $row->situacao : PdvMesa::SITUACAO_ATENDIMENTO,
                ];
            })
            ->values()
            ->all();
    }

    public function mesaOcupada(int $empresaId, int $numero): bool
    {
        return PdvMesa::query()
            ->where('empresa_id', $empresaId)
            ->where('numero', $numero)
            ->where('qtd_itens', '>', 0)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itens(PdvMesa $mesa): array
    {
        $decoded = json_decode((string) $mesa->itens, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
    }

    private function localizarOuCriar(int $empresaId, int $numero): PdvMesa
    {
        $mesa = PdvMesa::query()->where('empresa_id', $empresaId)->where('numero', $numero)->first();

        if ($mesa) {
            return $mesa;
        }

        try {
            return PdvMesa::query()->create(['empresa_id' => $empresaId, 'numero' => $numero]);
        } catch (QueryException) {
            // Outro terminal criou a mesma mesa ao mesmo tempo (índice único).
            return PdvMesa::query()->where('empresa_id', $empresaId)->where('numero', $numero)->firstOrFail();
        }
    }

    private function porToken(int $mesaId, string $token, ?int $empresaId = null): \Illuminate\Database\Query\Builder
    {
        return PdvMesa::query()
            ->toBase()
            ->where('id', $mesaId)
            ->where('reserva_token', $token)
            ->when($empresaId !== null, fn ($query) => $query->where('empresa_id', $empresaId));
    }

    private function tokenAtual(int $mesaId): ?string
    {
        $token = PdvMesa::query()->toBase()->where('id', $mesaId)->value('reserva_token');

        return $token !== null ? (string) $token : null;
    }

    /**
     * @param  array{user_id: int|null, terminal_id: int|null, nome: string}  $dono
     * @return array<string, mixed>
     */
    private function camposReserva(string $token, array $dono, Carbon $agora): array
    {
        return [
            'reserva_token' => $token,
            'reservado_user_id' => $dono['user_id'] ?? null,
            'reservado_terminal_id' => $dono['terminal_id'] ?? null,
            'reservado_nome' => mb_substr(trim((string) ($dono['nome'] ?? '')), 0, 120) ?: null,
            'reservado_ate' => $agora->copy()->addSeconds($this->reservaSegundos),
            'updated_at' => $agora,
        ];
    }

    /**
     * @return array<string, null>
     */
    private function camposSemReserva(): array
    {
        return [
            'reserva_token' => null,
            'reservado_user_id' => null,
            'reservado_terminal_id' => null,
            'reservado_nome' => null,
            'reservado_ate' => null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     */
    private function somaItens(array $itens): float
    {
        return round(array_sum(array_map(fn (array $item): float => (float) ($item['total'] ?? 0), $itens)), 2);
    }
}
