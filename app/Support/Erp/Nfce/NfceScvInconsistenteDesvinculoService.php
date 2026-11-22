<?php

namespace App\Support\Erp\Nfce;

use App\Models\PdvVenda;
use App\Models\PdvVendaNfce;
use App\Support\Erp\Audit\ErpOperacaoLogService;
use App\Support\Erp\ErpSchema;
use App\Support\Fiscal\NfceContingenciaConsistencia;
use App\Support\Fiscal\NfceXmlProtocolo;
use App\Support\Fiscal\PdvNfceConsultaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Libera venda/pedido cuja NFC-e de contingência veio inconsistente do SCV (sem protocolo SEFAZ),
 * arquivando o documento antigo e removendo só o vínculo que bloqueia nova emissão.
 * Não emite nem transmite NFC-e.
 */
final class NfceScvInconsistenteDesvinculoService
{
    public const MOTIVO = 'XML inconsistente importado do SCV';

    /** Cabe em pdv_venda_nfce.operacao (varchar 32). */
    public const OPERACAO_ARQUIVADA = 'arquivada_scv_inconsistente';

    private const RELATORIO_DIR = 'app/fiscal/nfce-desvinculo-scv';

    /**
     * @param  list<int>  $numeros
     * @return array{
     *     elegiveis: list<array<string, mixed>>,
     *     bloqueados: list<array<string, mixed>>,
     *     nao_encontrados: list<int>
     * }
     */
    public function analisar(int $empresaId, string $serie, array $numeros, bool $autorizacaoResponsavel = false): array
    {
        $serie = ltrim($serie, '0') ?: '1';
        $numeros = array_values(array_unique(array_map('intval', $numeros)));
        sort($numeros);

        $encontrados = PdvVendaNfce::query()
            ->where('empresa_id', $empresaId)
            ->where('serie', $serie)
            ->whereIn('numero', $numeros)
            ->orderBy('numero')
            ->get()
            ->keyBy(fn (PdvVendaNfce $n): int => (int) $n->numero);

        $elegiveis = [];
        $bloqueados = [];
        $naoEncontrados = [];

        foreach ($numeros as $numero) {
            $nfce = $encontrados->get($numero);

            if (! $nfce instanceof PdvVendaNfce) {
                $naoEncontrados[] = $numero;

                continue;
            }

            $mapa = $this->mapearVinculos($nfce);
            $elegibilidade = $this->avaliarElegibilidade($nfce, $autorizacaoResponsavel);

            $linha = array_merge($mapa, [
                'nfce_id' => (int) $nfce->id,
                'numero' => (int) $nfce->numero,
                'serie' => (string) $nfce->serie,
                'chave' => (string) $nfce->chave,
                'status' => (string) $nfce->status,
                'operacao' => (string) $nfce->operacao,
                'protocolo' => $nfce->protocolo,
                'autorizada_em' => $nfce->autorizada_em?->toDateTimeString(),
                'motivo_rejeicao' => $nfce->motivo_rejeicao,
                'elegivel' => $elegibilidade['elegivel'],
                'motivo_elegibilidade' => $elegibilidade['motivo'],
                'evidencias' => $elegibilidade['evidencias'],
            ]);

            if ($elegibilidade['elegivel']) {
                $elegiveis[] = $linha;
            } else {
                $bloqueados[] = $linha;
            }
        }

        return [
            'elegiveis' => $elegiveis,
            'bloqueados' => $bloqueados,
            'nao_encontrados' => $naoEncontrados,
        ];
    }

    /**
     * @param  list<int>  $numeros
     * @return array{relatorio: string, aplicados: list<array<string, mixed>>, bloqueados: list<array<string, mixed>>, nao_encontrados: list<int>}
     */
    public function executar(
        int $empresaId,
        string $serie,
        array $numeros,
        bool $dryRun = true,
        bool $autorizacaoResponsavel = false,
    ): array {
        $analise = $this->analisar($empresaId, $serie, $numeros, $autorizacaoResponsavel);
        $aplicados = [];

        if ($dryRun) {
            $relatorio = $this->gravarRelatorio($empresaId, [
                'modo' => 'dry-run',
                'empresa_id' => $empresaId,
                'serie' => $serie,
                'numeros' => $numeros,
                'autorizacao_responsavel' => $autorizacaoResponsavel,
                'analise' => $analise,
                'aplicados' => [],
            ]);

            return [
                'relatorio' => $relatorio,
                'aplicados' => [],
                'bloqueados' => $analise['bloqueados'],
                'nao_encontrados' => $analise['nao_encontrados'],
                'elegiveis' => $analise['elegiveis'],
            ];
        }

        DB::transaction(function () use ($analise, $autorizacaoResponsavel, &$aplicados): void {
            foreach ($analise['elegiveis'] as $linha) {
                $aplicados[] = $this->desvincularUm((int) $linha['nfce_id'], $autorizacaoResponsavel);
            }
        });

        $relatorio = $this->gravarRelatorio($empresaId, [
            'modo' => 'apply',
            'empresa_id' => $empresaId,
            'serie' => $serie,
            'numeros' => $numeros,
            'autorizacao_responsavel' => $autorizacaoResponsavel,
            'autorizacao_texto' => $autorizacaoResponsavel
                ? 'Responsável confirmou que as NFC-e não foram transmitidas; liberação autorizada para nova emissão.'
                : null,
            'analise' => $analise,
            'aplicados' => $aplicados,
            'reversao' => [
                'instrucao' => 'Use nfce:desvincular-scv-inconsistente --reverter=ARQUIVO com este relatório.',
                'campos_restauraveis' => ['pdv_venda_id', 'status', 'operacao', 'motivo_rejeicao', 'autorizada_em', 'pdv_arquivo_id'],
            ],
        ]);

        return [
            'relatorio' => $relatorio,
            'aplicados' => $aplicados,
            'bloqueados' => $analise['bloqueados'],
            'nao_encontrados' => $analise['nao_encontrados'],
            'elegiveis' => $analise['elegiveis'],
        ];
    }

    /**
     * Restaura vínculos a partir de um relatório de apply (somente se a venda ainda estiver sem NFC-e).
     *
     * @return list<array<string, mixed>>
     */
    public function reverterDoRelatorio(string $caminhoRelatorio): array
    {
        $dados = json_decode((string) file_get_contents($caminhoRelatorio), true);

        if (! is_array($dados) || ($dados['modo'] ?? '') !== 'apply') {
            throw new RuntimeException('Relatório inválido para reversão (esperado modo=apply).');
        }

        $restaurados = [];

        DB::transaction(function () use ($dados, &$restaurados): void {
            foreach ($dados['aplicados'] ?? [] as $item) {
                $restaurados[] = $this->reverterUm($item);
            }
        });

        return $restaurados;
    }

    public static function bloqueiaRetransmissao(PdvVendaNfce $nfce): ?string
    {
        if ((string) $nfce->operacao === self::OPERACAO_ARQUIVADA) {
            return 'NFC-e arquivada ('.self::MOTIVO.') — retransmissão bloqueada. Emita nova NFC-e pelo pedido/venda original.';
        }

        return null;
    }

    /**
     * @return array{elegivel: bool, motivo: string, evidencias: array<string, mixed>}
     */
    private function avaliarElegibilidade(PdvVendaNfce $nfce, bool $autorizacaoResponsavel = false): array
    {
        $xml = (string) ($nfce->xml ?? '');
        $nfe = NfceXmlProtocolo::nfe($xml);
        $temProt = is_string($xml) && (str_contains($xml, '<protNFe') || preg_match('/<nProt>\d+/', $xml) === 1);
        $consistencia = NfceContingenciaConsistencia::motivo($nfce);
        $provaSefazNegativa = $this->provaSefazNaoAutorizada($nfce);
        $divergenciaQr = $this->divergenciaChaveQrXml($nfce, $nfe);

        $evidencias = [
            'status' => (string) $nfce->status,
            'operacao' => (string) $nfce->operacao,
            'protocolo_preenchido' => filled($nfce->protocolo),
            'protocolo_cancelamento' => filled($nfce->protocolo_cancelamento),
            'xml_tem_protNFe_ou_nProt' => $temProt,
            'autorizada_em' => $nfce->autorizada_em?->toDateTimeString(),
            'consistencia_contingencia' => $consistencia,
            'prova_sefaz_negativa' => $provaSefazNegativa,
            'divergencia_qr_xml' => $divergenciaQr,
            'qr_campo_vazio' => trim((string) ($nfce->qr_code_conteudo ?? '')) === '',
            'autorizacao_responsavel' => $autorizacaoResponsavel,
            'ja_arquivada' => (string) $nfce->operacao === self::OPERACAO_ARQUIVADA,
        ];

        if ((string) $nfce->operacao === self::OPERACAO_ARQUIVADA) {
            return ['elegivel' => false, 'motivo' => 'Já arquivada/desvinculada.', 'evidencias' => $evidencias];
        }

        if (filled($nfce->protocolo) || $temProt) {
            return [
                'elegivel' => false,
                'motivo' => 'Há protocolo/autorização no XML — regularizar pela numeração original; não desvincular automaticamente.',
                'evidencias' => $evidencias,
            ];
        }

        if ((string) $nfce->status === PdvVendaNfce::STATUS_AUTORIZADA) {
            return [
                'elegivel' => false,
                'motivo' => 'Status autorizada sem protocolo inconsistente — revisar manualmente.',
                'evidencias' => $evidencias,
            ];
        }

        if ((string) $nfce->operacao !== NfceListDavNumero::OPERACAO_SCV_HIST
            && (string) $nfce->status !== PdvVendaNfce::STATUS_CONTINGENCIA) {
            return [
                'elegivel' => false,
                'motivo' => 'Fora do escopo (esperado contingência migracao_scv_hist).',
                'evidencias' => $evidencias,
            ];
        }

        // Contingência internamente coerente sem prova SEFAZ negativa = risco de emissão efetiva.
        if ($consistencia === null && ! $provaSefazNegativa['ok'] && $nfe !== null
            && trim((string) ($nfce->qr_code_conteudo ?? '')) !== '' && ! ($divergenciaQr['divergente'] ?? false)) {
            return [
                'elegivel' => false,
                'motivo' => 'Contingência parece internamente consistente — risco de emissão efetiva; regularizar pela numeração original.',
                'evidencias' => $evidencias,
            ];
        }

        if ((string) $nfce->status !== PdvVendaNfce::STATUS_CONTINGENCIA
            && (string) $nfce->operacao !== NfceListDavNumero::OPERACAO_SCV_HIST) {
            return ['elegivel' => false, 'motivo' => 'Status/operação não elegíveis.', 'evidencias' => $evidencias];
        }

        $inconsistenteScv = $consistencia !== null || ($divergenciaQr['divergente'] ?? false);

        if (! $inconsistenteScv) {
            return [
                'elegivel' => false,
                'motivo' => 'Sem inconsistência estrutural SCV comprovada e sem prova SEFAZ — vínculo preservado.',
                'evidencias' => $evidencias,
            ];
        }

        // Sem prova SEFAZ: só libera com autorização explícita do responsável + inconsistência SCV.
        if (! $provaSefazNegativa['ok']) {
            $extra = ($divergenciaQr['divergente'] ?? false)
                ? ' Evidência local: QR do XML aponta chave tpEmis='.($divergenciaQr['tpEmis_qr'] ?? '?')
                    .' distinta da chave gravada tpEmis='.($divergenciaQr['tpEmis_chave'] ?? '?').'.'
                : (($consistencia !== null) ? ' Evidência local: '.$consistencia.'.' : '');

            if (! $autorizacaoResponsavel) {
                return [
                    'elegivel' => false,
                    'motivo' => 'Vínculo preservado: XML inconsistente SCV, porém sem comprovação SEFAZ (cStat 217/libera-reenvio) na chave de contingência.'
                        .$extra
                        .' Corrija o certificado e consulte (F4) as chaves contingência e emissão normal antes de desvincular.',
                    'evidencias' => $evidencias,
                ];
            }

            return [
                'elegivel' => true,
                'motivo' => 'Elegível por autorização do responsável (não transmitidas) + '.self::MOTIVO
                    .($consistencia !== null ? ' ('.$consistencia.')' : '')
                    .(($divergenciaQr['divergente'] ?? false) ? '; QR/chave divergentes' : '')
                    .'.',
                'evidencias' => $evidencias,
            ];
        }

        return [
            'elegivel' => true,
            'motivo' => 'Elegível: '.self::MOTIVO.' ('.$provaSefazNegativa['detalhe']
                .($consistencia !== null ? '; '.$consistencia : '')
                .(($divergenciaQr['divergente'] ?? false) ? '; QR/chave divergentes' : '')
                .').',
            'evidencias' => $evidencias,
        ];
    }

    /**
     * Prova gravada de que a SEFAZ não tem a chave autorizada (consulta F4 prévia).
     *
     * @return array{ok: bool, detalhe: string}
     */
    private function provaSefazNaoAutorizada(PdvVendaNfce $nfce): array
    {
        $motivo = (string) ($nfce->motivo_rejeicao ?? '');

        foreach (PdvNfceConsultaService::CSTAT_LIBERA_REENVIO as $cStat) {
            if (str_contains($motivo, 'cStat '.$cStat)) {
                return ['ok' => true, 'detalhe' => 'consulta SEFAZ cStat '.$cStat.' gravada no motivo'];
            }
        }

        if (str_contains(mb_strtolower($motivo), 'não consta na sefaz')
            || str_contains(mb_strtolower($motivo), 'nao consta na sefaz')) {
            return ['ok' => true, 'detalhe' => 'consulta SEFAZ “não consta” gravada no motivo'];
        }

        return ['ok' => false, 'detalhe' => 'sem cStat de liberação gravado'];
    }

    /**
     * SCV costuma gravar Id/chave tpEmis=9 com QR ainda apontando emissão normal (tpEmis=1).
     *
     * @return array{divergente: bool, chave_gravada: string, chave_qr: ?string, tpEmis_chave: ?string, tpEmis_qr: ?string}
     */
    private function divergenciaChaveQrXml(PdvVendaNfce $nfce, ?string $nfe): array
    {
        $chave = (string) ($nfce->chave ?? '');
        $qr = trim((string) ($nfce->qr_code_conteudo ?? ''));
        if ($qr === '' && is_string($nfe)) {
            $qr = trim(NfceXmlProtocolo::qrCodeDoXml($nfe));
        }

        $chaveQr = null;
        if ($qr !== '' && preg_match('/(\d{44})/', $qr, $m) === 1) {
            $chaveQr = $m[1];
        }

        $tpChave = strlen($chave) === 44 ? $chave[34] : null;
        $tpQr = is_string($chaveQr) && strlen($chaveQr) === 44 ? $chaveQr[34] : null;

        return [
            'divergente' => is_string($chaveQr) && $chave !== '' && $chaveQr !== $chave,
            'chave_gravada' => $chave,
            'chave_qr' => $chaveQr,
            'tpEmis_chave' => $tpChave,
            'tpEmis_qr' => $tpQr,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapearVinculos(PdvVendaNfce $nfce): array
    {
        $pdv = PdvVenda::query()->find($nfce->pdv_venda_id);
        $vendaId = (int) ($pdv?->venda_id ?? 0);
        $venda = $vendaId > 0 ? DB::table('vendas')->where('id', $vendaId)->first() : null;

        $fv = null;
        $pedido = null;

        if ($vendaId > 0 && Schema::hasTable('forca_vendas_orders')) {
            $fv = DB::table('forca_vendas_orders')->where('venda_id', $vendaId)->first();
        }

        if ($fv && Schema::hasTable('pedidos')) {
            $pedidoId = (int) ($fv->pedido_id ?? $fv->id ?? 0);
            if ($pedidoId > 0) {
                $pedido = DB::table('pedidos')->where('id', $pedidoId)->first();
            }
        }

        return [
            'pdv_venda_id' => (int) ($pdv?->id ?? 0),
            'pdv_numero' => $pdv?->numero,
            'pdv_origem' => $pdv?->origem,
            'pdv_total' => $pdv?->total,
            'venda_id' => $vendaId ?: null,
            'venda_numero' => $venda->numero ?? null,
            'forca_vendas_order_id' => isset($fv->id) ? (int) $fv->id : null,
            'pedido_id' => isset($pedido->id) ? (int) $pedido->id : (isset($fv->id) ? (int) $fv->id : null),
            'pedido_numero' => $pedido->numero ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function desvincularUm(int $nfceId, bool $autorizacaoResponsavel = false): array
    {
        $nfce = PdvVendaNfce::query()->whereKey($nfceId)->lockForUpdate()->first();

        if (! $nfce instanceof PdvVendaNfce) {
            throw new RuntimeException('NFC-e id '.$nfceId.' não encontrada no lock.');
        }

        // Idempotência: já arquivada nesta rotina — não move de novo.
        if ((string) $nfce->operacao === self::OPERACAO_ARQUIVADA) {
            return [
                'antes' => [
                    'nfce_id' => (int) $nfce->id,
                    'pdv_venda_id' => (int) $nfce->pdv_venda_id,
                    'numero' => (int) $nfce->numero,
                    'status' => (string) $nfce->status,
                    'operacao' => (string) $nfce->operacao,
                ],
                'depois' => [
                    'idempotente' => true,
                    'nfce_id' => (int) $nfce->id,
                    'pdv_venda_id' => (int) $nfce->pdv_venda_id,
                    'status' => (string) $nfce->status,
                    'operacao' => (string) $nfce->operacao,
                    'venda_ainda_com_nfce' => false,
                ],
                'pdv_arquivo_id' => (int) $nfce->pdv_venda_id,
            ];
        }

        $elegibilidade = $this->avaliarElegibilidade($nfce, $autorizacaoResponsavel);

        if (! $elegibilidade['elegivel']) {
            throw new RuntimeException('NFC-e nº '.$nfce->numero.' deixou de ser elegível: '.$elegibilidade['motivo']);
        }

        $pdvOriginal = PdvVenda::query()->whereKey($nfce->pdv_venda_id)->lockForUpdate()->first();

        if (! $pdvOriginal instanceof PdvVenda) {
            throw new RuntimeException('pdv_venda '.$nfce->pdv_venda_id.' não encontrada.');
        }

        $antes = [
            'nfce_id' => (int) $nfce->id,
            'pdv_venda_id' => (int) $pdvOriginal->id,
            'venda_id' => $pdvOriginal->venda_id,
            'status' => (string) $nfce->status,
            'operacao' => (string) $nfce->operacao,
            'chave' => (string) $nfce->chave,
            'numero' => (int) $nfce->numero,
            'serie' => (string) $nfce->serie,
            'protocolo' => $nfce->protocolo,
            'motivo_rejeicao' => $nfce->motivo_rejeicao,
            'autorizada_em' => $nfce->autorizada_em?->toDateTimeString(),
            'pdv_total' => $pdvOriginal->total,
            'pdv_itens' => $pdvOriginal->itens()->count(),
            'pdv_pagamentos' => $pdvOriginal->pagamentos()->count(),
        ];

        NfceTentativaHistorico::arquivar($nfce, self::MOTIVO);

        $attrsArquivo = [
            'pdv_caixa_sessao_id' => null,
            'user_id' => $pdvOriginal->user_id,
            'orcamento_id' => null,
            'venda_id' => null,
            'person_id' => $pdvOriginal->person_id,
            'cpf_nota' => $pdvOriginal->cpf_nota,
            'vendedor_id' => $pdvOriginal->vendedor_id,
            'vendedor_nome' => $pdvOriginal->vendedor_nome,
            'numero' => $pdvOriginal->numero,
            'subtotal' => $pdvOriginal->subtotal,
            'desconto' => $pdvOriginal->desconto,
            'acrescimo' => $pdvOriginal->acrescimo,
            'total' => $pdvOriginal->total,
            'forma_pagamento' => $pdvOriginal->forma_pagamento,
            'fiscal' => false,
            'nfce_operacao' => null,
            'observacoes' => 'Arquivo fiscal SCV: NFC-e nº '.$nfce->numero
                .' desvinculada da pdv_venda '.$pdvOriginal->id
                .' / venda '.($pdvOriginal->venda_id ?: '—')
                .' — '.self::MOTIVO,
            'troco' => 0,
            'dinheiro' => 0,
            'situacao' => 'C',
            'fechado_em' => $pdvOriginal->fechado_em,
            'aberto_em' => $pdvOriginal->aberto_em,
            'origem' => PdvVenda::ORIGEM_NFCE_ARQUIVADA_SCV,
        ];

        if (ErpSchema::hasColumn('pdv_vendas', 'nome_nota')) {
            $attrsArquivo['nome_nota'] = $pdvOriginal->nome_nota;
        }

        $pdvArquivo = PdvVenda::query()->create($attrsArquivo);

        $nfce->forceFill([
            'pdv_venda_id' => $pdvArquivo->id,
            'status' => PdvVendaNfce::STATUS_REJEITADA,
            'operacao' => self::OPERACAO_ARQUIVADA,
            'motivo_rejeicao' => self::MOTIVO,
            'autorizada_em' => null,
        ])->save();

        try {
            app(ErpOperacaoLogService::class)->registrar(
                operacao: 'NFCE_SCV_DESVINCULADA',
                resumo: 'NFC-e nº '.$nfce->numero.' arquivada e desvinculada — '.self::MOTIVO,
                origem: 'nfce',
                documentoTipo: 'pdv_venda_nfce',
                documentoId: (int) $nfce->id,
                documentoNumero: (string) $nfce->numero,
                detalhes: [
                    'pdv_venda_original_id' => (int) $pdvOriginal->id,
                    'pdv_venda_arquivo_id' => (int) $pdvArquivo->id,
                    'venda_id' => $pdvOriginal->venda_id,
                    'chave' => $antes['chave'],
                    'status_antes' => $antes['status'],
                    'operacao_antes' => $antes['operacao'],
                ],
                empresaId: $nfce->empresa_id ? (int) $nfce->empresa_id : null,
            );
        } catch (Throwable $e) {
            report($e);
        }

        $depois = [
            'nfce_id' => (int) $nfce->id,
            'pdv_venda_id' => (int) $pdvArquivo->id,
            'pdv_venda_original_id' => (int) $pdvOriginal->id,
            'venda_id_preservada' => $pdvOriginal->venda_id,
            'status' => (string) $nfce->fresh()?->status,
            'operacao' => (string) $nfce->fresh()?->operacao,
            'motivo_rejeicao' => (string) $nfce->fresh()?->motivo_rejeicao,
            'venda_ainda_com_nfce' => PdvVendaNfce::query()->where('pdv_venda_id', $pdvOriginal->id)->exists(),
            'pdv_original_itens' => $pdvOriginal->itens()->count(),
            'pdv_original_pagamentos' => $pdvOriginal->pagamentos()->count(),
            'pdv_original_total' => $pdvOriginal->total,
        ];

        return [
            'antes' => $antes,
            'depois' => $depois,
            'pdv_arquivo_id' => (int) $pdvArquivo->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function reverterUm(array $item): array
    {
        $antes = $item['antes'] ?? null;
        $pdvArquivoId = (int) ($item['pdv_arquivo_id'] ?? $item['depois']['pdv_venda_id'] ?? 0);

        if (! is_array($antes) || $pdvArquivoId <= 0) {
            throw new RuntimeException('Item de reversão incompleto.');
        }

        $nfceId = (int) $antes['nfce_id'];
        $pdvOriginalId = (int) $antes['pdv_venda_id'];

        $nfce = PdvVendaNfce::query()->whereKey($nfceId)->lockForUpdate()->first();

        if (! $nfce instanceof PdvVendaNfce) {
            throw new RuntimeException('NFC-e '.$nfceId.' não encontrada para reversão.');
        }

        if ((string) $nfce->operacao !== self::OPERACAO_ARQUIVADA) {
            throw new RuntimeException('NFC-e nº '.$nfce->numero.' não está arquivada SCV; reversão abortada.');
        }

        if (PdvVendaNfce::query()->where('pdv_venda_id', $pdvOriginalId)->where('id', '!=', $nfceId)->exists()) {
            throw new RuntimeException(
                'pdv_venda '.$pdvOriginalId.' já possui outra NFC-e — não é seguro reverter automaticamente.'
            );
        }

        $nfce->forceFill([
            'pdv_venda_id' => $pdvOriginalId,
            'status' => $antes['status'],
            'operacao' => $antes['operacao'],
            'motivo_rejeicao' => $antes['motivo_rejeicao'],
            'autorizada_em' => $antes['autorizada_em'],
        ])->save();

        $arquivo = PdvVenda::query()->whereKey($pdvArquivoId)->lockForUpdate()->first();

        if ($arquivo instanceof PdvVenda
            && (string) $arquivo->origem === PdvVenda::ORIGEM_NFCE_ARQUIVADA_SCV
            && ! PdvVendaNfce::query()->where('pdv_venda_id', $arquivo->id)->exists()
        ) {
            $arquivo->delete();
        }

        return [
            'nfce_id' => $nfceId,
            'pdv_venda_id_restaurado' => $pdvOriginalId,
            'pdv_arquivo_id' => $pdvArquivoId,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gravarRelatorio(int $empresaId, array $payload): string
    {
        $dir = storage_path(self::RELATORIO_DIR.'/'.$empresaId);
        File::ensureDirectoryExists($dir);
        $arquivo = $dir.DIRECTORY_SEPARATOR.'desvinculo-'.now()->format('Ymd-His-v').'.json';
        $json = json_encode(
            array_merge(['gerado_em' => now()->toIso8601String(), 'motivo' => self::MOTIVO], $payload),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );

        if ($json === false || file_put_contents($arquivo, $json, LOCK_EX) === false) {
            throw new RuntimeException('Falha ao gravar relatório em '.$arquivo);
        }

        return $arquivo;
    }
}
