<?php

namespace App\Support\Fiscal;

use App\Models\Nfe;
use App\Models\NfeItem;
use Unitec\FiscalEngine\Exception\FiscalEngineException;

/**
 * Bloqueia na emissão cenários que o ERP ainda não implementa de fato,
 * evitando gravar tags zeradas (vII, vIPIDevol, grupos PISST/COFINSST) como se fossem válidas.
 *
 * PIS/COFINS CST 04 (monofásico) e 05 (ST da contribuição no grupo NT) → PISNT/COFINSNT
 * conforme o leiaute. O grupo opcional PISST/COFINSST (retenção) não é inventado nem emitido.
 */
final class NfeOperacoesNaoSuportadas
{
    public static function assertParaEmissaoNfe55(Nfe $nfe): void
    {
        $nfe->loadMissing('itens');

        foreach ($nfe->itens as $item) {
            self::assertItem($item);
        }
    }

    private static function assertItem(NfeItem $item): void
    {
        $cfop = preg_replace('/\D/', '', (string) ($item->cfop ?? '')) ?? '';
        $prefix = 'Item '.(string) ($item->item ?: $item->id).': ';

        // Importação (CFOP 3xxx): exige DI + vII — não emitir com vII=0.
        if ($cfop !== '' && str_starts_with($cfop, '3')) {
            throw new FiscalEngineException(
                $prefix.'CFOP de importação ('.$cfop.') exige Declaração de Importação e vII. '
                .'Esta emissão NF-e 55 ainda não suporta importação — não grave II zerado.',
            );
        }

        // IPI devolvido: sem estrutura de impostoDevol / vIPIDevol no ERP.
        $info = mb_strtoupper((string) ($item->info_adicionais ?? ''), 'UTF-8');
        if (str_contains($info, 'IPI DEVOL') || str_contains($info, 'IPIDEVOL')) {
            throw new FiscalEngineException(
                $prefix.'IPI devolvido (vIPIDevol) não é suportado nesta emissão. '
                .'Remova a indicação ou use processo fiscal adequado quando disponível.',
            );
        }

        // CST 03 (PISQtde/COFINSQtde) permanece bloqueado em PisCofinsCst::assertSuportado.
        // CST 04/05: permitidos via PISNT/COFINSNT — não confundir com o grupo PISST/COFINSST.
        // Grupo opcional PISST/COFINSST (retenção): sem campos no ERP não inventamos zeros;
        // se no futuro houver valores de retenção obrigatórios, bloquear emissão incompleta
        // em vez de omitir o grupo no XML.
    }
}
