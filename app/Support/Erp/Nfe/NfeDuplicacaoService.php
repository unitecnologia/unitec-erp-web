<?php

namespace App\Support\Erp\Nfe;

use App\Models\Nfe;
use App\Models\NfeEvento;
use App\Models\NfeFatura;
use App\Models\NfeItem;
use App\Models\VendasParametro;
use App\Support\Erp\ErpTimezone;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class NfeDuplicacaoService
{
    /** @var list<string> */
    private const SITUACOES_PERMITIDAS = [
        Nfe::STATUS_ABERTA,
        Nfe::STATUS_TRANSMITIDA,
        Nfe::STATUS_CANCELADA,
    ];

    /** @var list<string> */
    private const CAMPOS_CABECALHO_EXCLUIDOS = [
        'id',
        'chave',
        'chave_nfe_referenciada',
        'protocolo',
        'protocolo_cancelamento',
        'cnf',
        'xml',
        'xml_cancelamento',
        'venda_id',
        'pdv_venda_id',
        'devolucao_compra_id',
        'ordem_servico_id',
        'estoque_baixado',
        'motivo_contingencia',
        'created_at',
        'updated_at',
    ];

    /**
     * Cria uma nova NF-e ABERTA com os dados comerciais/fiscais da origem,
     * sem vínculos operacionais nem dados de transmissão.
     *
     * @throws DomainException
     * @throws RuntimeException
     */
    public function duplicar(Nfe $origem, int $empresaLogadaId): Nfe
    {
        if ($empresaLogadaId <= 0) {
            throw new DomainException('Empresa logada não identificada para duplicar a NF-e.');
        }

        if ((int) $origem->empresa_id !== $empresaLogadaId) {
            throw new DomainException('Só é permitido duplicar NF-e da empresa logada.');
        }

        $status = (string) ($origem->status ?: '');
        if (! in_array($status, self::SITUACOES_PERMITIDAS, true)) {
            throw new DomainException(
                'Não é permitido duplicar NF-e com situação '.$this->statusLabel($status).'.'
            );
        }

        $origem->loadMissing(['itens', 'faturas']);

        return DB::transaction(function () use ($origem, $empresaLogadaId): Nfe {
            $params = VendasParametro::forEmpresa($empresaLogadaId);
            $numero = (string) ($params->peekNumeroNfe() ?? Nfe::nextNumero($empresaLogadaId));
            $serie = (string) ($origem->serie ?: ($params->serie_nfe ?? 1));

            $duplicada = Nfe::query()
                ->where('empresa_id', $empresaLogadaId)
                ->where('numero', $numero)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->exists();

            if ($duplicada) {
                throw new RuntimeException('Número de NF-e já utilizado nesta série.');
            }

            $hoje = ErpTimezone::today();
            $hora = ErpTimezone::nowLocal()->format('H:i:s');

            $payload = collect($origem->getAttributes())
                ->except(self::CAMPOS_CABECALHO_EXCLUIDOS)
                ->all();

            $payload['empresa_id'] = $empresaLogadaId;
            $payload['numero'] = $numero;
            $payload['serie'] = $serie;
            $payload['modelo'] = '55';
            $payload['data_emissao'] = $hoje;
            $payload['data_saida'] = $hoje;
            $payload['hora_emissao'] = $hora;
            $payload['hora_saida'] = $hora;
            $payload['situacao'] = Nfe::SITUACAO_ABERTA;
            $payload['status'] = Nfe::STATUS_ABERTA;
            $payload['estoque_baixado'] = false;
            $payload['venda_id'] = null;
            $payload['pdv_venda_id'] = null;
            $payload['devolucao_compra_id'] = null;
            $payload['ordem_servico_id'] = null;
            $payload['chave'] = null;
            $payload['chave_nfe_referenciada'] = null;
            $payload['protocolo'] = null;
            $payload['protocolo_cancelamento'] = null;
            $payload['cnf'] = null;
            $payload['xml'] = null;
            $payload['xml_cancelamento'] = null;
            $payload['motivo_contingencia'] = null;

            $nova = Nfe::query()->create($payload);
            $params->consumeNumeroNfe();

            foreach ($origem->itens as $item) {
                $itemPayload = collect($item->getAttributes())
                    ->except(['id', 'nfe_id', 'created_at', 'updated_at'])
                    ->all();

                $itemPayload['nfe_id'] = $nova->id;
                $itemPayload['situacao'] = Nfe::SITUACAO_ABERTA;

                NfeItem::query()->create($itemPayload);
            }

            foreach ($origem->faturas as $fatura) {
                NfeFatura::query()->create([
                    'nfe_id' => $nova->id,
                    'empresa_id' => $empresaLogadaId,
                    'numero' => $fatura->numero,
                    'data_vencimento' => $fatura->data_vencimento,
                    'valor' => $fatura->valor,
                    'path_pdf_boleto' => null,
                ]);
            }

            NfeEventoLogger::registrar(
                nfeId: (int) $nova->id,
                tipo: NfeEvento::TIPO_CRIADA,
                titulo: 'NF-e duplicada',
                descricao: 'NF-e criada por duplicação da nota '.$origem->numero
                    .' (série '.(string) $origem->serie.', id '.$origem->id.').',
                metadata: [
                    'origem_nfe_id' => (int) $origem->id,
                    'origem_numero' => (string) $origem->numero,
                    'origem_serie' => (string) $origem->serie,
                    'origem_status' => (string) $origem->status,
                ],
            );

            return $nova->fresh(['itens', 'faturas', 'cliente', 'transportadora', 'empresa']) ?? $nova;
        });
    }

    private function statusLabel(string $status): string
    {
        return Nfe::statusLabels()[$status] ?? (filled($status) ? $status : 'desconhecida');
    }
}
