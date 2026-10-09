<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvMesa;

/**
 * Mesa aberta nesta estação (sessão do navegador). Sem mesa aberta, tudo aqui é
 * leitura de sessão em memória: o PDV de quem não usa Mesas não faz nenhuma query.
 */
final class PdvMesaSessao
{
    public const SEM_MESA = 'sem_mesa';

    public const OK = 'ok';

    public const MUDOU = 'mudou';

    public const PERDIDA = 'perdida';

    /** Mesa aguardando fechamento: nada é gravado. */
    public const BLOQUEADA = 'bloqueada';

    private const CHAVE = 'erp.pdv.mesa';

    /**
     * @return array{id: int, numero: int, token: string, ocupada: bool, seg: int, aguardando: bool}|null
     */
    public static function atual(): ?array
    {
        $mesa = session(self::CHAVE);

        if (! is_array($mesa) || (int) ($mesa['id'] ?? 0) <= 0 || ! filled($mesa['token'] ?? null)) {
            return null;
        }

        return [
            'id' => (int) $mesa['id'],
            'numero' => (int) ($mesa['numero'] ?? 0),
            'token' => (string) $mesa['token'],
            'ocupada' => (bool) ($mesa['ocupada'] ?? false),
            'seg' => (int) ($mesa['seg'] ?? 0),
            'aguardando' => (bool) ($mesa['aguardando'] ?? false),
        ];
    }

    /**
     * @param  int  $reservaSegundos  validade da reserva (tempo de inatividade da empresa)
     */
    public static function definir(PdvMesa $mesa, string $token, int $reservaSegundos): void
    {
        session([self::CHAVE => [
            'id' => (int) $mesa->id,
            'numero' => (int) $mesa->numero,
            'token' => $token,
            'ocupada' => (int) $mesa->qtd_itens > 0,
            'seg' => $reservaSegundos,
            'aguardando' => $mesa->aguardandoFechamento(),
        ]]);
    }

    public static function aguardando(): bool
    {
        return (bool) (self::atual()['aguardando'] ?? false);
    }

    public static function marcarAguardando(bool $aguardando): void
    {
        if (self::atual() !== null) {
            session([self::CHAVE.'.aguardando' => $aguardando]);
        }
    }

    public static function esquecer(): void
    {
        session()->forget(self::CHAVE);
    }

    /**
     * Grava o cupom na mesa aberta. MUDOU = a mesa passou de livre para ocupada (ou o
     * contrário), para o painel atualizar a cor sem esperar o próximo pulso.
     *
     * @param  list<array<string, mixed>>  $itens
     */
    public static function sincronizarCupom(array $itens): string
    {
        $mesa = self::atual();

        if ($mesa === null) {
            return self::SEM_MESA;
        }

        if ($mesa['aguardando']) {
            return self::BLOQUEADA;
        }

        if (! PdvMesaService::make($mesa['seg'])->salvarItens($mesa['id'], $mesa['token'], $itens)) {
            return self::PERDIDA;
        }

        $ocupada = $itens !== [];

        if ($ocupada !== $mesa['ocupada']) {
            session([self::CHAVE.'.ocupada' => $ocupada]);

            return self::MUDOU;
        }

        return self::OK;
    }
}
