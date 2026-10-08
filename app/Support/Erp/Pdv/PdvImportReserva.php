<?php

namespace App\Support\Erp\Pdv;

use App\Models\PdvCaixaSessao;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

/**
 * Reserva de documento importado no PDV (pedido/orçamento) enquanto está no cupom
 * ou em venda em espera. Impede que outro caixa importe o mesmo documento ao mesmo
 * tempo. A reserva pertence à sessão de caixa e expira quando ela é fechada.
 *
 * A barreira definitiva contra venda duplicada é a revalidação com lock de linha
 * na finalização; esta reserva evita o conflito já na importação.
 */
final class PdvImportReserva
{
    public const PEDIDO = 'pedido';

    public const ORCAMENTO = 'orcamento';

    private const TTL_SEGUNDOS = 86400;

    /**
     * @return string|null mensagem de bloqueio; null quando reservado para esta sessão
     */
    public static function reservar(string $tipo, int $id, int $sessaoId, string $operador): ?string
    {
        if ($id <= 0 || $sessaoId <= 0) {
            return 'Documento ou caixa inválido para importação.';
        }

        try {
            return Cache::lock(self::chaveLock($tipo, $id), 10)->block(5, function () use ($tipo, $id, $sessaoId, $operador): ?string {
                $dono = self::donoAtivo($tipo, $id);

                if ($dono !== null && (int) $dono['sessao'] !== $sessaoId) {
                    $quem = trim((string) ($dono['operador'] ?? ''));

                    return self::rotulo($tipo).' em uso em outro caixa'
                        .($quem !== '' ? ' ('.$quem.')' : '')
                        .'. Aguarde a finalização ou o cancelamento.';
                }

                Cache::put(self::chave($tipo, $id), [
                    'sessao' => $sessaoId,
                    'operador' => mb_strtoupper($operador, 'UTF-8'),
                    'em' => now()->toIso8601String(),
                ], self::TTL_SEGUNDOS);

                return null;
            });
        } catch (LockTimeoutException) {
            return self::rotulo($tipo).' está sendo importado em outro caixa. Tente novamente.';
        }
    }

    public static function liberar(string $tipo, int $id, int $sessaoId): void
    {
        if ($id <= 0) {
            return;
        }

        $dono = Cache::get(self::chave($tipo, $id));

        if (is_array($dono) && $sessaoId > 0 && (int) ($dono['sessao'] ?? 0) !== $sessaoId) {
            return;
        }

        Cache::forget(self::chave($tipo, $id));
    }

    /**
     * @return array{sessao: int, operador: string, em: string}|null
     */
    public static function donoAtivo(string $tipo, int $id): ?array
    {
        $dono = Cache::get(self::chave($tipo, $id));

        if (! is_array($dono)) {
            return null;
        }

        $sessaoId = (int) ($dono['sessao'] ?? 0);
        $aberta = $sessaoId > 0 && PdvCaixaSessao::query()
            ->whereKey($sessaoId)
            ->whereNull('fechado_em')
            ->exists();

        if (! $aberta) {
            Cache::forget(self::chave($tipo, $id));

            return null;
        }

        return [
            'sessao' => $sessaoId,
            'operador' => (string) ($dono['operador'] ?? ''),
            'em' => (string) ($dono['em'] ?? ''),
        ];
    }

    private static function rotulo(string $tipo): string
    {
        return $tipo === self::ORCAMENTO ? 'Orçamento' : 'Pedido';
    }

    private static function chave(string $tipo, int $id): string
    {
        return 'erp:pdv:import-reserva:'.$tipo.':'.$id;
    }

    private static function chaveLock(string $tipo, int $id): string
    {
        return 'erp:pdv:import-reserva-lock:'.$tipo.':'.$id;
    }
}
