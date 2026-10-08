<?php

namespace App\Support\Fiscal;

use App\Models\Empresa;
use App\Models\PdvVendaNfce;
use App\Models\VendasParametro;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\Pdv\PdvEstornoMotivo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Unitec\FiscalEngine\Dto\InutilizarNfceRequest;
use Unitec\FiscalEngine\Dto\InutilizarNfceResponse;
use Unitec\FiscalEngine\Exception\FiscalEngineException;
use Unitec\FiscalEngine\FiscalEngine;

/**
 * Inutilização NFC-e (F3). A faixa é conferida e reservada no livro de numeração antes da SEFAZ
 * (o contador não entrega esses números); números de NFC-e autorizada, cancelada, em contingência,
 * pendente ou em emissão bloqueiam. Protocolo e XML ficam gravados (nfce_inutilizacoes).
 */
final class PdvNfceInutilizacaoService
{
    public function __construct(
        private readonly PdvNfceFiscalPayloadBuilder $payloadBuilder = new PdvNfceFiscalPayloadBuilder(),
        private readonly FiscalEngine $engine = new FiscalEngine(),
    ) {}

    public function inutilizar(
        Empresa $empresa,
        int $serie,
        int $numeroInicial,
        int $numeroFinal,
        string $justificativa,
    ): InutilizarNfceResponse {
        $justificativa = PdvEstornoMotivo::normalize($justificativa);
        $erroMotivo = PdvEstornoMotivo::validate($justificativa);

        if ($erroMotivo !== null) {
            throw new FiscalEngineException($erroMotivo);
        }

        if ($numeroInicial < 1 || $numeroFinal < $numeroInicial) {
            throw new FiscalEngineException('Faixa de numeração inválida para inutilização.');
        }

        $serie = max(1, $serie);
        $parametros = VendasParametro::forEmpresa((int) $empresa->id);

        if (! $this->payloadBuilder->podeOperarReal($parametros, $empresa)) {
            throw new FiscalEngineException('Inutilização real de NFC-e não está configurada para esta empresa/UF.');
        }

        $certificate = NfceFiscalCertificateResolver::resolve($empresa, $parametros);
        $tpAmb = NfceFiscalCertificateResolver::tpAmb($parametros);
        $ambiente = NfceNumeracao::ambiente($parametros);

        $reservados = NfceNumeracao::reservarInutilizacao((int) $empresa->id, $serie, $parametros, $numeroInicial, $numeroFinal);

        try {
            $response = $this->engine->inutilizarNfce(new InutilizarNfceRequest(
                certificate: $certificate,
                cnpj: (string) $empresa->cnpj,
                tpAmb: $tpAmb,
                serie: $serie,
                numeroInicial: $numeroInicial,
                numeroFinal: $numeroFinal,
                justificativa: $justificativa,
            ));
        } catch (Throwable $exception) {
            NfceNumeracao::liberarReservaInutilizacao((int) $empresa->id, $serie, $parametros, $reservados, trim($exception->getMessage()));

            throw $exception;
        }

        $this->registrarInutilizacao($empresa, $serie, $ambiente, $numeroInicial, $numeroFinal, $justificativa, $response);

        return $response;
    }

    private function registrarInutilizacao(
        Empresa $empresa,
        int $serie,
        int $ambiente,
        int $inicial,
        int $final,
        string $justificativa,
        InutilizarNfceResponse $response,
    ): void {
        $empresaId = (int) $empresa->id;
        $protocolo = (string) $response->protocolo;

        try {
            NfceNumeracao::confirmarInutilizacao($empresaId, $serie, $ambiente, $inicial, $final, $protocolo);
        } catch (Throwable $e) {
            report($e);
        }

        $this->guardarXml($empresaId, $serie, $ambiente, $inicial, $final, $justificativa, $response);

        $marcadas = 0;

        try {
            $marcadas = PdvVendaNfce::query()
                ->where('empresa_id', $empresaId)
                ->whereIn('serie', NfceTerminalSequencia::seriesEquivalentes((string) $serie))
                ->where('ambiente', $ambiente)
                ->whereBetween('numero', [$inicial, $final])
                ->where(fn ($q) => $q->where('simulada', false)->orWhereNull('simulada'))
                ->whereIn('status', [PdvVendaNfce::STATUS_REJEITADA, PdvVendaNfce::STATUS_DUPLICIDADE])
                ->get()
                ->each(function (PdvVendaNfce $nfce) use ($protocolo): void {
                    $nfce->update([
                        'status' => PdvVendaNfce::STATUS_INUTILIZADA,
                        'motivo_rejeicao' => mb_substr(
                            trim((string) $nfce->motivo_rejeicao).' — número inutilizado na SEFAZ (protocolo '.($protocolo ?: '—').').',
                            0,
                            2000,
                            'UTF-8',
                        ),
                    ]);
                })
                ->count();
        } catch (Throwable $e) {
            report($e);
        }

        try {
            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'NFCE_INUTILIZACAO',
                resumo: 'NFC-e série '.$serie.': números '.$inicial.' a '.$final.' inutilizados (protocolo '.($protocolo ?: '—').').',
                origem: 'nfce',
                documentoTipo: 'nfce_inutilizacao',
                documentoNumero: $inicial.'-'.$final,
                detalhes: [
                    'serie' => $serie,
                    'ambiente' => $ambiente,
                    'numero_inicial' => $inicial,
                    'numero_final' => $final,
                    'protocolo' => $protocolo,
                    'justificativa' => $justificativa,
                    'nfce_marcadas_inutilizadas' => $marcadas,
                ],
                empresaId: $empresaId,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** Protocolo/XML da inutilização no banco (entra no backup); sem a tabela, em arquivo. */
    private function guardarXml(
        int $empresaId,
        int $serie,
        int $ambiente,
        int $inicial,
        int $final,
        string $justificativa,
        InutilizarNfceResponse $response,
    ): void {
        $dados = [
            'empresa_id' => $empresaId,
            'modelo' => NfceNumeracao::MODELO,
            'serie' => $serie,
            'ambiente' => $ambiente,
            'numero_inicial' => $inicial,
            'numero_final' => $final,
            'protocolo' => mb_substr((string) $response->protocolo, 0, 30, 'UTF-8'),
            'status_codigo' => mb_substr((string) $response->statusCodigo, 0, 5, 'UTF-8'),
            'justificativa' => mb_substr($justificativa, 0, 255, 'UTF-8'),
            'xml' => $response->xml,
            'user_id' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        try {
            if (Schema::hasTable('nfce_inutilizacoes')) {
                DB::table('nfce_inutilizacoes')->insert($dados);

                return;
            }
        } catch (Throwable $e) {
            report($e);
        }

        try {
            $dir = storage_path('app/fiscal/nfce-inutilizacoes/'.$empresaId);
            File::ensureDirectoryExists($dir);
            file_put_contents(
                $dir.DIRECTORY_SEPARATOR.'inut-s'.$serie.'-'.$inicial.'-'.$final.'-'.now()->format('Ymd-His').'.json',
                json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                LOCK_EX,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }
}
