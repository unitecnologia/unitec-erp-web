<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVendaNfce;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Histórico imutável das tentativas fiscais da NFC-e. Antes de qualquer sobrescrita de número,
 * chave ou XML, o estado completo do registro é arquivado na tabela nfce_tentativas (entra no
 * dump de backup) e em arquivo JSON (cópia; também vai no pacote fiscal do backup), mais log de
 * operação. Se nenhuma das duas cópias puder ser gravada, a sobrescrita é abortada.
 */
final class NfceTentativaHistorico
{
    private const DIRETORIO = 'app/fiscal/nfce-tentativas';

    private const TABELA = 'nfce_tentativas';

    private const CAMPOS = [
        'id', 'pdv_venda_id', 'empresa_id', 'operacao', 'modelo', 'serie', 'numero', 'cnf', 'chave',
        'protocolo', 'protocolo_cancelamento', 'status', 'ambiente', 'tipo_emissao', 'simulada',
        'qr_code_conteudo', 'xml', 'xml_cancelamento', 'motivo_rejeicao', 'motivo_contingencia',
        'autorizada_em', 'cancelada_em', 'created_at', 'updated_at',
    ];

    public static function arquivar(PdvVendaNfce $nfce, string $motivo): void
    {
        if (blank($nfce->getRawOriginal('chave')) && blank($nfce->getRawOriginal('xml'))) {
            return;
        }

        $registro = [];
        foreach (self::CAMPOS as $campo) {
            $registro[$campo] = $nfce->getRawOriginal($campo);
        }

        $diretorio = storage_path(self::DIRETORIO.'/'.((int) $nfce->empresa_id).'/'.now()->format('Y-m'));
        $arquivo = $diretorio.DIRECTORY_SEPARATOR.self::prefixo($nfce, (string) $nfce->getRawOriginal('chave'))
            .now()->format('Ymd-His-v').'.json';

        $json = json_encode([
            'arquivado_em' => now()->toIso8601String(),
            'motivo' => $motivo,
            'registro' => $registro,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $gravadoBanco = self::gravarNoBanco($nfce, $registro, $motivo);

        try {
            File::ensureDirectoryExists($diretorio);
            $gravadoArquivo = $json !== false && file_put_contents($arquivo, $json, LOCK_EX) !== false;
        } catch (Throwable) {
            $gravadoArquivo = false;
        }

        if (! $gravadoBanco && ! $gravadoArquivo) {
            throw new RuntimeException(
                'Não foi possível arquivar a tentativa anterior da NFC-e nº '.$nfce->numero
                .' (banco e storage/'.self::DIRETORIO.'). Nada foi alterado.'
            );
        }

        try {
            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'NFCE_TENTATIVA_ARQUIVADA',
                resumo: 'NFC-e nº '.$nfce->numero.' ('.$registro['status'].'): tentativa arquivada — '.$motivo,
                origem: 'nfce',
                documentoTipo: 'pdv_venda_nfce',
                documentoId: (int) $nfce->id,
                documentoNumero: (string) $nfce->numero,
                detalhes: [
                    'pdv_venda_id' => $registro['pdv_venda_id'],
                    'chave' => $registro['chave'],
                    'serie' => $registro['serie'],
                    'numero' => $registro['numero'],
                    'status' => $registro['status'],
                    'protocolo' => $registro['protocolo'],
                    'motivo_rejeicao' => $registro['motivo_rejeicao'],
                    'arquivo' => str_replace(storage_path().DIRECTORY_SEPARATOR, '', $arquivo),
                ],
                empresaId: $nfce->empresa_id ? (int) $nfce->empresa_id : null,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Tentativa arquivada deste registro com a chave informada (mais recente).
     *
     * @return array<string, mixed>|null
     */
    public static function localizar(PdvVendaNfce $nfce, string $chave): ?array
    {
        if (preg_match('/^\d{44}$/', $chave) !== 1) {
            return null;
        }

        try {
            if (Schema::hasTable(self::TABELA)) {
                $linhas = DB::table(self::TABELA)
                    ->where('pdv_venda_nfce_id', (int) $nfce->id)
                    ->where('chave', $chave)
                    ->orderByDesc('id')
                    ->pluck('registro');

                foreach ($linhas as $json) {
                    $registro = json_decode((string) $json, true);

                    if (is_array($registro) && filled($registro['xml'] ?? null)) {
                        return $registro;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        $padrao = storage_path(self::DIRETORIO.'/'.((int) $nfce->empresa_id).'/*/'.self::prefixo($nfce, $chave).'*.json');
        $arquivos = glob($padrao) ?: [];
        rsort($arquivos);

        foreach ($arquivos as $arquivo) {
            $dados = json_decode((string) @file_get_contents($arquivo), true);
            $registro = is_array($dados) ? ($dados['registro'] ?? null) : null;

            if (is_array($registro) && (string) ($registro['chave'] ?? '') === $chave && filled($registro['xml'] ?? null)) {
                return $registro;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $registro
     */
    private static function gravarNoBanco(PdvVendaNfce $nfce, array $registro, string $motivo): bool
    {
        try {
            if (! Schema::hasTable(self::TABELA)) {
                return false;
            }

            $json = json_encode($registro, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

            if ($json === false) {
                return false;
            }

            DB::table(self::TABELA)->insert([
                'pdv_venda_nfce_id' => (int) $nfce->id,
                'pdv_venda_id' => $registro['pdv_venda_id'] ?? null,
                'empresa_id' => $registro['empresa_id'] ?? null,
                'chave' => $registro['chave'] ?? null,
                'serie' => $registro['serie'] ?? null,
                'numero' => $registro['numero'] ?? null,
                'status' => $registro['status'] ?? null,
                'protocolo' => isset($registro['protocolo']) ? mb_substr((string) $registro['protocolo'], 0, 30, 'UTF-8') : null,
                'motivo' => mb_substr($motivo, 0, 500, 'UTF-8'),
                'registro' => $json,
                'user_id' => Auth::id(),
                'arquivado_em' => now(),
            ]);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }

    private static function prefixo(PdvVendaNfce $nfce, string $chave): string
    {
        return 'nfce-'.$nfce->id.'-'.($chave !== '' ? $chave : 'sem-chave').'-';
    }
}
