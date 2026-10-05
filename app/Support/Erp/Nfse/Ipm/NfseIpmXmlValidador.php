<?php

namespace App\Support\Erp\Nfse\Ipm;

use DOMDocument;

/**
 * Valida GerarNfseEnvio contra o abrasf.xsd oficial da IPM (resources/nfse/xsd/ipm).
 * A cópia local do XSD só teve a declaração de encoding corrigida para UTF-8, que é o encoding real do arquivo.
 */
class NfseIpmXmlValidador
{
    /**
     * @return list<string>
     */
    public function erros(string $xml): array
    {
        $xsd = base_path('resources/nfse/xsd/ipm/abrasf.xsd');

        if (! is_file($xsd)) {
            return ['XSD oficial IPM não encontrado para validação local.'];
        }

        $anterior = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument;
        $valido = $doc->loadXML($xml, LIBXML_NONET) && $doc->schemaValidate($xsd, LIBXML_NONET);
        $erros = [];

        foreach (libxml_get_errors() as $erro) {
            if ($erro->level < LIBXML_ERR_ERROR) {
                continue;
            }

            $mensagem = trim(preg_replace('/\{[^}]+\}/', '', $erro->message) ?? $erro->message);
            $erros[] = 'Linha '.$erro->line.': '.$mensagem;
        }

        libxml_clear_errors();
        libxml_use_internal_errors($anterior);

        if (! $valido && $erros === []) {
            $erros[] = 'XML inválido para o XSD oficial IPM.';
        }

        return array_values(array_unique($erros));
    }
}
