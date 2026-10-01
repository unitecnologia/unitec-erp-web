<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Writer;
use Illuminate\Support\Collection;

/**
 * Dados de exibição do DANFSe v2.0. Não altera emissão, XML nem transmissão.
 */
final class NfseDanfseViewData
{
    public const CONSULTA_PUBLICA = 'https://www.nfse.gov.br/ConsultaPublica/?tpc=1&chave=';

    /**
     * @return array<string, mixed>
     */
    public static function for(Nfse $nfse, bool $autoPrint = false, bool $embedded = false): array
    {
        $nfse->loadMissing(['empresa', 'itens']);
        /** @var Empresa|null $empresa */
        $empresa = $nfse->empresa;
        /** @var Collection<int, NfseItem> $itens */
        $itens = $nfse->itens;

        $chave = preg_replace('/\D+/', '', (string) ($nfse->chave_acesso ?: $nfse->chave)) ?: '';
        $qrUrl = $chave !== '' ? self::CONSULTA_PUBLICA.$chave : null;

        return [
            'nfse' => $nfse,
            'empresa' => $empresa,
            'itens' => $itens,
            'autoPrint' => $autoPrint,
            'embedded' => $embedded,
            'danfse' => [
                'versao' => 'DANFSe v2.0',
                'subtitulo' => 'Documento Auxiliar da NFS-e',
                'chave' => $chave !== '' ? $chave : '-',
                'chave_formatada' => $chave !== '' ? self::formatarChave($chave) : '-',
                'numero_nfse' => self::texto($nfse->numero_nfse),
                'numero_dps' => self::texto($nfse->numero_dps),
                'serie_dps' => self::texto($nfse->serie_dps ?: Nfse::SERIE_DPS),
                'competencia' => self::data($nfse->competencia),
                'dh_emissao_nfse' => self::texto($nfse->data_hora_processamento) !== '-'
                    ? self::texto($nfse->data_hora_processamento)
                    : self::dataHora($nfse->data_emissao),
                'dh_emissao_dps' => self::dataHora($nfse->data_emissao),
                'sem_validade_juridica' => self::eHomologacao($nfse),
                'qr_url' => $qrUrl,
                'qr_data_uri' => $qrUrl !== null ? self::qrDataUri($qrUrl) : null,
                'qr_mensagem' => 'A autenticidade desta NFS-e e as informações adicionais estão disponíveis no portal nacional (www.nfse.gov.br/ConsultaPublica) ou através do QR Code.',
                'emitente' => self::emitente($empresa),
                'tomador' => self::tomador($nfse),
                'servico' => self::servico($nfse, $itens),
                'municipal' => self::municipal($nfse),
                'federal' => self::federal(),
                'totais' => self::totais($nfse),
                'tributos_aproximados' => [
                    'federais' => '-',
                    'estaduais' => '-',
                    'municipais' => '-',
                ],
                'complementares' => self::complementares($nfse),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function emitente(?Empresa $empresa): array
    {
        if (! $empresa instanceof Empresa) {
            return [
                'documento' => '-',
                'im' => '-',
                'telefone' => '-',
                'nome' => '-',
                'email' => '-',
                'endereco' => '-',
                'municipio_uf' => '-',
                'cep' => '-',
                'simples' => '-',
                'regime_sn' => '-',
            ];
        }

        $endereco = trim(implode(', ', array_filter([
            trim((string) $empresa->endereco),
            trim((string) $empresa->numero),
            trim((string) $empresa->complemento),
            trim((string) $empresa->bairro),
        ], static fn ($v) => $v !== '')));

        $opSimp = NfseRegimeTributario::opSimpNac($empresa->regime_tributario);
        $simplesLabel = match ($opSimp) {
            '2' => 'MEI',
            '3' => 'Optante pelo Simples Nacional',
            default => 'Não optante',
        };

        $regAp = trim((string) ($empresa->nfse_reg_ap_trib_sn ?? ''));
        $regimeSn = NfseRegimeTributario::regimesApuracaoSimples()[$regAp] ?? ($regAp !== '' ? $regAp : '-');

        return [
            'documento' => self::texto($empresa->cnpj),
            'im' => self::texto($empresa->im),
            'telefone' => self::texto($empresa->telefone),
            'nome' => self::texto($empresa->razao_social ?: $empresa->nome ?: $empresa->fantasia),
            'email' => self::texto($empresa->email),
            'endereco' => $endereco !== '' ? $endereco : '-',
            'municipio_uf' => self::municipioUf($empresa->cidade, $empresa->uf),
            'cep' => self::texto($empresa->cep),
            'simples' => $simplesLabel,
            'regime_sn' => $regimeSn,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function tomador(Nfse $nfse): array
    {
        $endereco = trim(implode(', ', array_filter([
            trim((string) $nfse->tomador_endereco),
            trim((string) $nfse->tomador_numero),
            trim((string) $nfse->tomador_bairro),
        ], static fn ($v) => $v !== '')));

        return [
            'documento' => self::texto($nfse->tomador_cpf_cnpj),
            'im' => '-',
            'telefone' => self::texto($nfse->tomador_telefone),
            'nome' => self::texto($nfse->tomador_nome),
            'email' => self::texto($nfse->tomador_email),
            'endereco' => $endereco !== '' ? $endereco : '-',
            'municipio_uf' => self::municipioUf($nfse->tomador_cidade, $nfse->tomador_uf),
            'cep' => self::texto($nfse->tomador_cep),
        ];
    }

    /**
     * @param  Collection<int, NfseItem>  $itens
     * @return array<string, string>
     */
    private static function servico(Nfse $nfse, Collection $itens): array
    {
        $primeiro = $itens->first();
        $descricoes = $itens
            ->map(static fn (NfseItem $item) => trim((string) $item->descricao))
            ->filter()
            ->values()
            ->all();

        $local = trim(implode(' / ', array_filter([
            trim((string) $nfse->municipio_prestacao_nome),
            trim((string) $nfse->municipio_prestacao_uf),
        ], static fn ($v) => $v !== '')));

        if ($local === '' && filled($nfse->municipio_prestacao_codigo)) {
            $local = (string) $nfse->municipio_prestacao_codigo;
        }

        return [
            'c_trib_nac' => self::texto($primeiro?->c_trib_nac),
            'c_trib_mun' => self::texto($primeiro?->c_trib_mun),
            'local_prestacao' => $local !== '' ? $local : '-',
            'pais_prestacao' => '-',
            'descricao' => $descricoes !== [] ? implode("\n", $descricoes) : '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function municipal(Nfse $nfse): array
    {
        $trib = trim((string) ($nfse->trib_issqn ?? ''));
        $ret = trim((string) ($nfse->tp_ret_issqn ?? ''));
        $regEsp = trim((string) ($nfse->empresa?->nfse_reg_esp_trib ?? ''));

        return [
            'trib_issqn' => Nfse::tributacoesIssqn()[$trib] ?? ($trib !== '' ? $trib : '-'),
            'pais_resultado' => '-',
            'municipio_incidencia' => self::texto($nfse->municipio_incidencia),
            'regime_especial' => NfseRegimeTributario::regimesEspeciais()[$regEsp] ?? ($regEsp !== '' ? $regEsp : '-'),
            'tipo_imunidade' => '-',
            'suspensao' => '-',
            'nro_processo_suspensao' => '-',
            'beneficio_municipal' => '-',
            'valor_servico' => self::money($nfse->valor_servicos),
            'desconto_incondicionado' => self::money($nfse->desconto),
            'total_deducoes' => '-',
            'calculo_bm' => '-',
            'bc_issqn' => '-',
            'aliquota' => '-',
            'retencao_issqn' => Nfse::retencoesIssqn()[$ret] ?? ($ret !== '' ? $ret : '-'),
            'issqn_apurado' => self::money($nfse->iss),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function federal(): array
    {
        return [
            'irrf' => '-',
            'cp' => '-',
            'csll' => '-',
            'pis' => '-',
            'cofins' => '-',
            'retencao_pis_cofins' => '-',
            'total' => '-',
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function totais(Nfse $nfse): array
    {
        $issRetido = trim((string) ($nfse->tp_ret_issqn ?? '')) === '2'
            ? self::money($nfse->iss)
            : self::money(0);

        return [
            'valor_servico' => self::money($nfse->valor_servicos),
            'desconto_condicionado' => '-',
            'desconto_incondicionado' => self::money($nfse->desconto),
            'issqn_retido' => $issRetido,
            'irrf_cp_csll_retidos' => '-',
            'pis_cofins_retidos' => '-',
            'valor_liquido' => self::money($nfse->total),
        ];
    }

    private static function complementares(Nfse $nfse): string
    {
        $partes = [];
        if (filled($nfse->protocolo)) {
            $partes[] = 'Protocolo: '.$nfse->protocolo;
        }
        if (filled($nfse->id_dps)) {
            $partes[] = 'Id DPS: '.$nfse->id_dps;
        }
        if (filled($nfse->versao_aplicativo)) {
            $partes[] = 'Versão aplicativo: '.$nfse->versao_aplicativo;
        }

        return $partes !== [] ? implode(' | ', $partes) : '-';
    }

    private static function eHomologacao(Nfse $nfse): bool
    {
        $tp = trim((string) ($nfse->tipo_ambiente ?? ''));

        return $tp === '2';
    }

    private static function qrDataUri(string $conteudo): string
    {
        // ~1,6 cm em 96 dpi (~61 px); especificação exige mínimo 1,52 cm.
        $renderer = new GDLibRenderer(72, 4);
        $png = (new Writer($renderer))->writeString($conteudo);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    private static function formatarChave(string $chave): string
    {
        $digits = preg_replace('/\D+/', '', $chave) ?: '';
        if ($digits === '') {
            return '-';
        }

        return trim(chunk_split($digits, 4, ' '));
    }

    private static function municipioUf(mixed $cidade, mixed $uf): string
    {
        $c = trim((string) $cidade);
        $u = trim((string) $uf);
        if ($c === '' && $u === '') {
            return '-';
        }
        if ($c === '') {
            return $u;
        }
        if ($u === '') {
            return $c;
        }

        return $c.' / '.$u;
    }

    private static function texto(mixed $valor): string
    {
        $t = trim((string) ($valor ?? ''));

        return $t !== '' ? $t : '-';
    }

    private static function data(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('m/Y');
        }
        $t = trim((string) ($valor ?? ''));
        if ($t === '') {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($t)->format('m/Y');
        } catch (\Throwable) {
            return $t;
        }
    }

    private static function dataHora(mixed $valor): string
    {
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('d/m/Y H:i:s');
        }
        $t = trim((string) ($valor ?? ''));
        if ($t === '') {
            return '-';
        }
        try {
            return \Carbon\Carbon::parse($t)->format('d/m/Y H:i:s');
        } catch (\Throwable) {
            return $t;
        }
    }

    private static function money(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '-';
        }
        $n = is_numeric($valor) ? (float) $valor : null;
        if ($n === null) {
            return '-';
        }

        return 'R$ '.number_format($n, 2, ',', '.');
    }
}
