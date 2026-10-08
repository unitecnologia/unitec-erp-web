<?php

namespace App\Support\Fiscal;

use App\Models\PdvVendaNfce;
use App\Models\Terminal;
use App\Models\VendasParametro;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Numeração fiscal NFC-e única por empresa + modelo + série + ambiente.
 *
 * - Contador em nfce_numeracoes, travado com SELECT ... FOR UPDATE em conexão própria (o número
 *   consumido sobrevive ao rollback da venda);
 * - todo número entregue é gravado em nfce_numeros_usados (índice único): mesmo com terminais
 *   simultâneos, nenhum número sai duas vezes; números já usados (offline, inutilizados,
 *   legados) são pulados;
 * - inicialização: maior entre NFC-e gravadas, livro de números e contadores antigos
 *   (terminal / parâmetros) — nunca reutiliza número consumido.
 */
final class NfceNumeracao
{
    public const MODELO = '65';

    public const ORIGEM_EMISSAO = 'emissao';

    public const ORIGEM_OFFLINE = 'offline';

    public const ORIGEM_INUTILIZACAO = 'inutilizacao';

    public const ORIGEM_LEGADO = 'legado';

    private const CONTADOR = 'nfce_numeracoes';

    private const USADOS = 'nfce_numeros_usados';

    private const LIMITE_SALTOS = 5000;

    private const LIMITE_FAIXA_INUTILIZACAO = 10000;

    /** Número consumido há menos tempo que isso e ainda sem NFC-e gravada = emissão em andamento. */
    private const MINUTOS_EMISSAO_EM_ANDAMENTO = 10;

    /** Status que ocupam o número na SEFAZ (ou podem ocupar). */
    public const STATUS_OCUPAM_NUMERO = [
        PdvVendaNfce::STATUS_AUTORIZADA,
        PdvVendaNfce::STATUS_CANCELADA,
        PdvVendaNfce::STATUS_CONTINGENCIA,
        PdvVendaNfce::STATUS_PENDENTE,
        PdvVendaNfce::STATUS_DENEGADA,
    ];

    /** Por que uma NFC-e neste status não pode ter o número inutilizado (null = pode). */
    public static function motivoStatusNaoInutilizavel(string $status): ?string
    {
        return match ($status) {
            PdvVendaNfce::STATUS_AUTORIZADA => 'está autorizada na SEFAZ — para anular use F2 | Cancelar',
            PdvVendaNfce::STATUS_CANCELADA => 'já está cancelada na SEFAZ — o número não pode ser inutilizado',
            PdvVendaNfce::STATUS_PENDENTE => 'está gravada aguardando retorno da SEFAZ — consulte com F4 | Recuperar antes de inutilizar',
            PdvVendaNfce::STATUS_CONTINGENCIA => 'está em contingência — transmita com F5 | Transmitir',
            PdvVendaNfce::STATUS_DENEGADA => 'teve uso denegado — o número fica registrado na SEFAZ e não pode ser inutilizado',
            PdvVendaNfce::STATUS_INUTILIZADA => 'já está inutilizada',
            PdvVendaNfce::STATUS_SIMULADA => 'é cupom simulado, sem numeração fiscal na SEFAZ',
            default => null,
        };
    }

    private static bool $disponivel = false;

    public static function disponivel(): bool
    {
        if (self::$disponivel) {
            return true;
        }

        try {
            return self::$disponivel = Schema::hasTable(self::CONTADOR) && Schema::hasTable(self::USADOS);
        } catch (Throwable) {
            return false;
        }
    }

    public static function ambiente(VendasParametro $parametros): int
    {
        return NfceFiscalCertificateResolver::ambienteNfce($parametros);
    }

    public static function serieInt(int|string|null $serie): int
    {
        return ((int) ltrim(trim((string) $serie), '0')) ?: 1;
    }

    public static function consumir(int $empresaId, int $serie, VendasParametro $parametros, ?int $terminalId = null): int
    {
        $ambiente = self::ambiente($parametros);
        $conexao = NfceTerminalSequencia::sequenciaConnectionName();

        return DB::connection($conexao)->transaction(function () use ($conexao, $empresaId, $serie, $ambiente, $parametros, $terminalId): int {
            $db = DB::connection($conexao);
            $contador = self::contadorTravado($db, $empresaId, $serie, $ambiente, $parametros);
            $numero = (int) $contador->ultimo_numero;
            $agora = now();
            $obtido = false;

            for ($i = 0; $i < self::LIMITE_SALTOS; $i++) {
                $numero++;

                $nfceLegada = self::nfceComNumero($db, $empresaId, $serie, $ambiente, $numero);

                if ($nfceLegada !== null) {
                    $db->table(self::USADOS)->insertOrIgnore(self::linha($empresaId, $serie, $ambiente, $numero, self::ORIGEM_LEGADO, null, $nfceLegada, 'NFC-e existente sem registro no livro.', $agora));

                    continue;
                }

                if ($db->table(self::USADOS)->insertOrIgnore(self::linha($empresaId, $serie, $ambiente, $numero, self::ORIGEM_EMISSAO, $terminalId, null, null, $agora)) === 1) {
                    $obtido = true;
                    break;
                }
            }

            if (! $obtido) {
                throw new FiscalEngineException('Não foi possível obter número NFC-e livre na série '.$serie.' (faixa ocupada). Verifique a numeração.');
            }

            $db->table(self::CONTADOR)->where('id', $contador->id)->update(['ultimo_numero' => $numero, 'updated_at' => $agora]);
            self::sincronizarContadoresAntigos($db, $empresaId, $serie, $parametros, $terminalId, $numero + 1);

            return $numero;
        }, 3);
    }

    /** Garante que o próximo número entregue seja >= $proximo (ex.: após 539 ou ajuste manual). */
    public static function garantirPeloMenos(int $empresaId, int $serie, VendasParametro $parametros, int $proximo, ?int $terminalId = null): void
    {
        $ambiente = self::ambiente($parametros);
        $conexao = NfceTerminalSequencia::sequenciaConnectionName();

        DB::connection($conexao)->transaction(function () use ($conexao, $empresaId, $serie, $ambiente, $parametros, $proximo, $terminalId): void {
            $db = DB::connection($conexao);
            $contador = self::contadorTravado($db, $empresaId, $serie, $ambiente, $parametros);

            if ((int) $contador->ultimo_numero < $proximo - 1) {
                $db->table(self::CONTADOR)->where('id', $contador->id)->update(['ultimo_numero' => $proximo - 1, 'updated_at' => now()]);
            }

            self::sincronizarContadoresAntigos($db, $empresaId, $serie, $parametros, $terminalId, $proximo);
        }, 3);
    }

    /** Próximo número previsto (sem consumir, sem travar) — exibição, carga do PDV offline. */
    public static function proximo(int $empresaId, int $serie, VendasParametro $parametros): int
    {
        $ambiente = self::ambiente($parametros);
        $db = DB::connection();

        $contador = $db->table(self::CONTADOR)
            ->where(self::chave($empresaId, $serie, $ambiente))
            ->first(['ultimo_numero', 'inicializado_em']);

        $ultimo = $contador !== null && $contador->inicializado_em !== null
            ? (int) $contador->ultimo_numero
            : self::ultimoConsumidoLegado($db, $empresaId, $serie, $ambiente, $parametros);

        return $ultimo + 1;
    }

    /** Maior número registrado no livro (emissão, offline, inutilização, legado); 0 se vazio. */
    public static function ultimoUsado(int $empresaId, int $serie, int $ambiente): int
    {
        if (! self::disponivel()) {
            return 0;
        }

        return (int) (DB::table(self::USADOS)->where(self::chave($empresaId, $serie, $ambiente))->max('numero') ?? 0);
    }

    /**
     * Registra número usado fora do contador (PDV offline). Não move o contador: o número só é
     * pulado quando chegar a vez dele.
     *
     * @return int|null id da outra NFC-e que já ocupa o número (conflito) ou null
     */
    public static function registrarUsado(
        int $empresaId,
        int $serie,
        int $ambiente,
        int $numero,
        string $origem,
        ?int $nfceId = null,
        ?int $terminalId = null,
        ?string $observacao = null,
    ): ?int {
        $db = DB::connection();
        $db->table(self::USADOS)->insertOrIgnore(self::linha($empresaId, $serie, $ambiente, $numero, $origem, $terminalId, $nfceId, $observacao, now()));

        if ($nfceId === null) {
            return null;
        }

        $linha = $db->table(self::USADOS)->where(self::chave($empresaId, $serie, $ambiente))->where('numero', $numero)->first(['id', 'pdv_venda_nfce_id']);

        if ($linha === null) {
            return null;
        }

        if ($linha->pdv_venda_nfce_id === null) {
            $db->table(self::USADOS)->where('id', $linha->id)->whereNull('pdv_venda_nfce_id')->update(['pdv_venda_nfce_id' => $nfceId]);

            return null;
        }

        $dono = (int) $linha->pdv_venda_nfce_id;

        if ($dono === $nfceId) {
            return null;
        }

        $statusDono = PdvVendaNfce::query()->whereKey($dono)->value('status');

        // Dono anterior não ocupa o número (rejeitada/inutilizada/removida): o vínculo passa para esta NFC-e.
        if ($statusDono === null || ! in_array((string) $statusDono, self::STATUS_OCUPAM_NUMERO, true)) {
            $db->table(self::USADOS)->where('id', $linha->id)->where('pdv_venda_nfce_id', $dono)->update([
                'pdv_venda_nfce_id' => $nfceId,
                'observacao' => mb_substr('Vínculo transferido da NFC-e id '.$dono.' ('.($statusDono ?? 'removida').').', 0, 255, 'UTF-8'),
            ]);

            return null;
        }

        return $dono;
    }

    /**
     * Antes de gravar/enviar: o número não pode pertencer a outra NFC-e (livro ou documento válido).
     *
     * @throws FiscalEngineException
     */
    public static function assegurarLivre(int $empresaId, int|string|null $serie, int $ambiente, int $numero, ?int $nfceIdAtual): void
    {
        if (! self::disponivel() || $empresaId <= 0 || $numero <= 0) {
            return;
        }

        $serieInt = self::serieInt($serie);
        $db = DB::connection();

        $dono = $db->table(self::USADOS)
            ->where(self::chave($empresaId, $serieInt, $ambiente))
            ->where('numero', $numero)
            ->value('pdv_venda_nfce_id');

        $outra = PdvVendaNfce::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', NfceTerminalSequencia::seriesEquivalentes((string) $serieInt))
            ->where('numero', $numero)
            ->where('ambiente', $ambiente)
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->whereIn('status', self::STATUS_OCUPAM_NUMERO)
            ->when($nfceIdAtual !== null, fn ($q) => $q->whereKeyNot($nfceIdAtual))
            ->first(['id', 'pdv_venda_id', 'status']);

        if ($outra !== null) {
            throw new FiscalEngineException(
                'NFC-e nº '.$numero.' série '.$serieInt.' já pertence à venda PDV #'.$outra->pdv_venda_id.' ('.$outra->status.'). Envio bloqueado para não duplicar a numeração.'
            );
        }

        if ($dono !== null && (int) $dono !== $nfceIdAtual) {
            $statusDono = PdvVendaNfce::query()->whereKey((int) $dono)->value('status');

            if ($statusDono !== null && in_array((string) $statusDono, self::STATUS_OCUPAM_NUMERO, true)) {
                throw new FiscalEngineException(
                    'NFC-e nº '.$numero.' série '.$serieInt.' já está registrada para outro documento no livro de numeração. Envio bloqueado.'
                );
            }
        }
    }

    /** Vincula a NFC-e gravada ao número do livro (cria o registro se o número veio de fora do contador). */
    public static function vincular(PdvVendaNfce $nfce, string $origem = self::ORIGEM_EMISSAO): ?int
    {
        if (! self::disponivel() || $nfce->simulada || blank($nfce->numero) || ! $nfce->empresa_id) {
            return null;
        }

        try {
            return self::registrarUsado(
                (int) $nfce->empresa_id,
                self::serieInt($nfce->serie),
                (int) ($nfce->ambiente ?: PdvVendaNfce::AMBIENTE_HOMOLOGACAO),
                (int) $nfce->numero,
                $origem,
                (int) $nfce->id,
            );
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * F3: reserva a faixa no livro antes de ir à SEFAZ (o contador passa a pular esses números).
     * Bloqueia se algum número pertence a NFC-e autorizada/cancelada/contingência/pendente ou a
     * emissão em andamento.
     *
     * @return list<int> números reservados agora (para liberar se a SEFAZ recusar)
     *
     * @throws FiscalEngineException
     */
    public static function reservarInutilizacao(int $empresaId, int $serie, VendasParametro $parametros, int $inicial, int $final): array
    {
        self::verificarFaixaInutilizavel($empresaId, $serie, $parametros, $inicial, $final);
        $ambiente = self::ambiente($parametros);

        if (! self::disponivel()) {
            return [];
        }

        $conexao = NfceTerminalSequencia::sequenciaConnectionName();

        return DB::connection($conexao)->transaction(function () use ($conexao, $empresaId, $serie, $ambiente, $parametros, $inicial, $final): array {
            $db = DB::connection($conexao);
            self::contadorTravado($db, $empresaId, $serie, $ambiente, $parametros);

            $existentes = $db->table(self::USADOS)
                ->where(self::chave($empresaId, $serie, $ambiente))
                ->whereBetween('numero', [$inicial, $final])
                ->pluck('numero')
                ->map(fn ($n): int => (int) $n)
                ->flip();

            $reservados = [];
            $agora = now();
            $lote = [];

            for ($numero = $inicial; $numero <= $final; $numero++) {
                if ($existentes->has($numero)) {
                    continue;
                }

                $lote[] = self::linha($empresaId, $serie, $ambiente, $numero, self::ORIGEM_INUTILIZACAO, null, null, 'Inutilização enviada à SEFAZ — aguardando retorno.', $agora);
                $reservados[] = $numero;

                if (count($lote) === 500) {
                    $db->table(self::USADOS)->insertOrIgnore($lote);
                    $lote = [];
                }
            }

            if ($lote !== []) {
                $db->table(self::USADOS)->insertOrIgnore($lote);
            }

            return $reservados;
        }, 3);
    }

    /**
     * SEFAZ recusou a inutilização: números ainda à frente do contador voltam a ficar livres;
     * os que o contador já pulou continuam reservados (precisam ser inutilizados de novo).
     *
     * @param  list<int>  $reservados
     */
    public static function liberarReservaInutilizacao(int $empresaId, int $serie, VendasParametro $parametros, array $reservados, string $motivo): void
    {
        if ($reservados === [] || ! self::disponivel()) {
            return;
        }

        $ambiente = self::ambiente($parametros);
        $conexao = NfceTerminalSequencia::sequenciaConnectionName();

        DB::connection($conexao)->transaction(function () use ($conexao, $empresaId, $serie, $ambiente, $parametros, $reservados, $motivo): void {
            $db = DB::connection($conexao);
            $contador = self::contadorTravado($db, $empresaId, $serie, $ambiente, $parametros);
            $ultimo = (int) $contador->ultimo_numero;
            $base = $db->table(self::USADOS)
                ->where(self::chave($empresaId, $serie, $ambiente))
                ->where('origem', self::ORIGEM_INUTILIZACAO)
                ->whereNull('pdv_venda_nfce_id');

            foreach (array_chunk($reservados, 500) as $bloco) {
                (clone $base)->whereIn('numero', array_filter($bloco, fn (int $n): bool => $n > $ultimo))->delete();
                (clone $base)->whereIn('numero', array_filter($bloco, fn (int $n): bool => $n <= $ultimo))
                    ->update(['observacao' => mb_substr('Inutilização recusada ('.$motivo.'): número pulado, inutilize novamente.', 0, 255, 'UTF-8')]);
            }
        }, 3);
    }

    public static function confirmarInutilizacao(int $empresaId, int $serie, int $ambiente, int $inicial, int $final, string $protocolo): void
    {
        if (! self::disponivel()) {
            return;
        }

        DB::table(self::USADOS)
            ->where(self::chave($empresaId, $serie, $ambiente))
            ->whereBetween('numero', [$inicial, $final])
            ->where('origem', self::ORIGEM_INUTILIZACAO)
            ->update(['observacao' => 'Inutilizado na SEFAZ — protocolo '.($protocolo ?: '—').'.']);
    }

    /**
     * @return list<string>
     */
    /**
     * Checagem local (sem SEFAZ) da faixa a inutilizar.
     *
     * @throws FiscalEngineException
     */
    public static function verificarFaixaInutilizavel(int $empresaId, int $serie, VendasParametro $parametros, int $inicial, int $final): void
    {
        if ($inicial < 1 || $final < $inicial) {
            throw new FiscalEngineException('Faixa de numeração inválida para inutilização.');
        }

        if ($final - $inicial + 1 > self::LIMITE_FAIXA_INUTILIZACAO) {
            throw new FiscalEngineException('Faixa muito grande para inutilização (máximo '.self::LIMITE_FAIXA_INUTILIZACAO.' números por vez).');
        }

        $bloqueios = self::bloqueiosInutilizacao($empresaId, max(1, $serie), self::ambiente($parametros), $inicial, $final);

        if ($bloqueios !== []) {
            throw new FiscalEngineException('Inutilização bloqueada: '.implode('; ', array_slice($bloqueios, 0, 5)).(count($bloqueios) > 5 ? '; …' : '').'.');
        }
    }

    private static function bloqueiosInutilizacao(int $empresaId, int $serie, int $ambiente, int $inicial, int $final): array
    {
        $bloqueios = PdvVendaNfce::query()
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', NfceTerminalSequencia::seriesEquivalentes((string) $serie))
            ->where('ambiente', $ambiente)
            ->whereBetween('numero', [$inicial, $final])
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->whereIn('status', [...self::STATUS_OCUPAM_NUMERO, PdvVendaNfce::STATUS_INUTILIZADA])
            ->orderBy('numero')
            ->limit(20)
            ->get(['numero', 'status', 'pdv_venda_id'])
            ->map(fn (PdvVendaNfce $n): string => 'nº '.$n->numero.' '.(self::motivoStatusNaoInutilizavel((string) $n->status) ?? 'está '.$n->status))
            ->all();

        if (! self::disponivel()) {
            return $bloqueios;
        }

        $emAndamento = DB::table(self::USADOS)
            ->where(self::chave($empresaId, $serie, $ambiente))
            ->whereBetween('numero', [$inicial, $final])
            ->whereIn('origem', [self::ORIGEM_EMISSAO, self::ORIGEM_OFFLINE])
            ->whereNull('pdv_venda_nfce_id')
            ->where('created_at', '>=', now()->subMinutes(self::MINUTOS_EMISSAO_EM_ANDAMENTO))
            ->orderBy('numero')
            ->limit(20)
            ->pluck('numero')
            ->map(fn ($n): string => 'nº '.$n.' acabou de ser reservado para uma emissão em andamento')
            ->all();

        return array_merge($bloqueios, $emAndamento);
    }

    private static function contadorTravado(ConnectionInterface $db, int $empresaId, int $serie, int $ambiente, VendasParametro $parametros): object
    {
        $chave = self::chave($empresaId, $serie, $ambiente);
        $contador = $db->table(self::CONTADOR)->where($chave)->lockForUpdate()->first();

        if ($contador === null) {
            $db->table(self::CONTADOR)->insertOrIgnore($chave + [
                'ultimo_numero' => 0,
                'inicializado_em' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $contador = $db->table(self::CONTADOR)->where($chave)->lockForUpdate()->first();
        }

        if ($contador->inicializado_em === null) {
            $ultimo = max((int) $contador->ultimo_numero, self::ultimoConsumidoLegado($db, $empresaId, $serie, $ambiente, $parametros));

            $db->table(self::CONTADOR)->where('id', $contador->id)->update([
                'ultimo_numero' => $ultimo,
                'inicializado_em' => now(),
                'updated_at' => now(),
            ]);

            Log::info('NFC-e: contador de numeração inicializado.', [
                'empresa_id' => $empresaId,
                'serie' => $serie,
                'ambiente' => $ambiente,
                'ultimo_numero' => $ultimo,
            ]);

            $contador->ultimo_numero = $ultimo;
            $contador->inicializado_em = now();
        }

        return $contador;
    }

    /**
     * Último número já consumido na série/ambiente por qualquer meio anterior ao contador único.
     * Contadores antigos (terminal/parâmetros) não distinguiam ambiente: valem para o ambiente
     * configurado hoje.
     */
    private static function ultimoConsumidoLegado(ConnectionInterface $db, int $empresaId, int $serie, int $ambiente, VendasParametro $parametros): int
    {
        $series = NfceTerminalSequencia::seriesEquivalentes((string) $serie);

        $ultimo = (int) ($db->table('pdv_venda_nfce')
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', $series)
            ->where('ambiente', $ambiente)
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->max('numero') ?? 0);

        $ultimo = max($ultimo, (int) ($db->table(self::USADOS)->where(self::chave($empresaId, $serie, $ambiente))->max('numero') ?? 0));

        if ($ambiente !== self::ambiente($parametros)) {
            return $ultimo;
        }

        // Depois que a série já tem contador (em qualquer ambiente), terminal/parâmetros são só espelho.
        $serieJaControlada = $db->table(self::CONTADOR)
            ->where('empresa_id', $empresaId)
            ->where('modelo', self::MODELO)
            ->where('serie', $serie)
            ->where('ambiente', '<>', $ambiente)
            ->whereNotNull('inicializado_em')
            ->exists();

        if ($serieJaControlada) {
            return $ultimo;
        }

        foreach (Terminal::on($db->getName())->where('empresa_id', $empresaId)->get(['id', 'empresa_id', 'serie', 'numeracao_inicial']) as $terminal) {
            if (NfceTerminalSequencia::serieEfetivaInt($terminal, $parametros) === $serie) {
                $ultimo = max($ultimo, (int) ($terminal->numeracao_inicial ?: 1) - 1);
            }
        }

        if (NfceTerminalSequencia::mesmaSerie((string) $serie, (string) $parametros->serie)) {
            $ultimo = max($ultimo, (int) ($parametros->numero ?: 1) - 1);
        }

        return $ultimo;
    }

    /** Mantém terminal/parâmetros coerentes (telas antigas e PDV offline ainda os exibem). */
    private static function sincronizarContadoresAntigos(ConnectionInterface $db, int $empresaId, int $serie, VendasParametro $parametros, ?int $terminalId, int $proximo): void
    {
        if ($terminalId !== null) {
            $db->table('terminais')
                ->where('id', $terminalId)
                ->where(fn ($q) => $q->whereNull('numeracao_inicial')->orWhere('numeracao_inicial', '<', $proximo))
                ->update(['numeracao_inicial' => $proximo, 'usar_numero_inicial' => true]);
        }

        if (NfceTerminalSequencia::mesmaSerie((string) $serie, (string) $parametros->serie)) {
            $db->table('vendas_parametros')
                ->where('empresa_id', $empresaId)
                ->where(fn ($q) => $q->whereNull('numero')->orWhere('numero', '<', $proximo))
                ->update(['numero' => $proximo]);
        }
    }

    private static function nfceComNumero(ConnectionInterface $db, int $empresaId, int $serie, int $ambiente, int $numero): ?int
    {
        $id = $db->table('pdv_venda_nfce')
            ->where('empresa_id', $empresaId)
            ->whereIn('serie', NfceTerminalSequencia::seriesEquivalentes((string) $serie))
            ->where('ambiente', $ambiente)
            ->where('numero', $numero)
            ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{empresa_id: int, modelo: string, serie: int, ambiente: int}
     */
    private static function chave(int $empresaId, int $serie, int $ambiente): array
    {
        return ['empresa_id' => $empresaId, 'modelo' => self::MODELO, 'serie' => $serie, 'ambiente' => $ambiente];
    }

    /**
     * @return array<string, mixed>
     */
    private static function linha(int $empresaId, int $serie, int $ambiente, int $numero, string $origem, ?int $terminalId, ?int $nfceId, ?string $observacao, mixed $agora): array
    {
        return self::chave($empresaId, $serie, $ambiente) + [
            'numero' => $numero,
            'origem' => $origem,
            'terminal_id' => $terminalId,
            'pdv_venda_nfce_id' => $nfceId,
            'observacao' => $observacao,
            'created_at' => $agora,
        ];
    }
}
