<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseItem;
use App\Support\Erp\ErpTimezone;

class NfseDpsBuilder
{
    /**
     * Estrutura da DPS em memória, só com o que já está na NFS-e e nos itens.
     * O prestador vem da empresa da nota. Tomador e serviços não são relidos.
     *
     * @return array{
     *     dps: array{tp_amb: string, dh_emi: string, ver_aplic: string, serie: string, numero: int, competencia: string, data_emissao: string, tp_emit: string},
     *     prestador: array<string, mixed>,
     *     tomador: array<string, mixed>,
     *     municipio_prestacao: array{codigo: ?string, nome: ?string, uf: ?string},
     *     servicos: list<array<string, mixed>>,
     *     valores: array{valor_servicos: string, desconto: string, iss: string, total: string, trib_issqn: ?string, tp_ret_issqn: ?string, ind_tot_trib: string}
     * }
     */
    public function montar(Nfse $nfse): array
    {
        if ($nfse->status !== Nfse::STATUS_ABERTA) {
            throw new NfseDpsNaoMontada('Só é possível montar a DPS de uma NFS-e aberta.');
        }

        $nfse->loadMissing(['empresa', 'itens']);

        $empresa = $nfse->empresa;

        if (! $empresa instanceof Empresa) {
            throw new NfseDpsNaoMontada('Empresa da NFS-e não encontrada.');
        }

        return [
            'dps' => [
                'tp_amb' => NfseSefinAmbiente::daEmpresa($empresa->nfse_ambiente)->tpAmb(),
                'dh_emi' => $this->dhEmi($nfse),
                'ver_aplic' => trim((string) config('unitec.versao')),
                'serie' => (string) $nfse->serie_dps,
                'numero' => (int) $nfse->numero_dps,
                'competencia' => $nfse->competencia?->format('Y-m') ?? '',
                'data_emissao' => $nfse->data_emissao?->format('Y-m-d') ?? '',
                'tp_emit' => NfseEmitente::Prestador->value,
            ],
            'prestador' => $this->prestador($empresa),
            'tomador' => $this->tomador($nfse),
            'municipio_prestacao' => [
                'codigo' => $this->texto($nfse->municipio_prestacao_codigo),
                'nome' => $this->texto($nfse->municipio_prestacao_nome),
                'uf' => $this->texto($nfse->municipio_prestacao_uf),
            ],
            'servicos' => $nfse->itens
                ->map(fn (NfseItem $item): array => $this->servico($item))
                ->values()
                ->all(),
            'valores' => [
                'valor_servicos' => $this->decimal($nfse->valor_servicos),
                'desconto' => $this->decimal($nfse->desconto),
                'iss' => $this->decimal($nfse->iss),
                'total' => $this->decimal($nfse->total),
                'trib_issqn' => $this->texto($nfse->trib_issqn),
                'tp_ret_issqn' => $this->texto($nfse->tp_ret_issqn),
                'ind_tot_trib' => NfseIndicadorTotalTributos::NaoInformar->value,
            ],
        ];
    }

    private function dhEmi(Nfse $nfse): string
    {
        if ($nfse->created_at === null) {
            return '';
        }

        return ErpTimezone::toLocal($nfse->created_at)->format('Y-m-d\TH:i:s').'-03:00';
    }

    /**
     * @return array<string, mixed>
     */
    private function prestador(Empresa $empresa): array
    {
        $nome = $this->texto($empresa->razao_social) ?? $this->texto($empresa->nome);

        return [
            'cnpj' => $this->texto($empresa->cnpj),
            'nome' => $nome,
            'fantasia' => $this->texto($empresa->fantasia),
            'inscricao_municipal' => $this->texto($empresa->im),
            'email' => $this->texto($empresa->email),
            'telefone' => $this->texto($empresa->telefone),
            'endereco' => $this->texto($empresa->endereco),
            'numero' => $this->texto($empresa->numero),
            'bairro' => $this->texto($empresa->bairro),
            'cep' => $this->texto($empresa->cep),
            'cidade' => $this->texto($empresa->cidade),
            'cidade_codigo' => $this->texto($empresa->cidade_codigo),
            'uf' => $this->texto($empresa->uf),
            'op_simp_nac' => NfseRegimeTributario::opSimpNac($empresa->regime_tributario),
            'reg_esp_trib' => $this->texto($empresa->nfse_reg_esp_trib),
            'reg_ap_trib_sn' => $this->texto($empresa->nfse_reg_ap_trib_sn),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tomador(Nfse $nfse): array
    {
        return [
            'nome' => $this->texto($nfse->tomador_nome),
            'cpf_cnpj' => $this->texto($nfse->tomador_cpf_cnpj),
            'telefone' => $this->texto($nfse->tomador_telefone),
            'email' => $this->texto($nfse->tomador_email),
            'endereco' => $this->texto($nfse->tomador_endereco),
            'numero' => $this->texto($nfse->tomador_numero),
            'bairro' => $this->texto($nfse->tomador_bairro),
            'cep' => $this->texto($nfse->tomador_cep),
            'cidade' => $this->texto($nfse->tomador_cidade),
            'cidade_codigo' => $this->texto($nfse->tomador_cidade_codigo),
            'uf' => $this->texto($nfse->tomador_uf),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function servico(NfseItem $item): array
    {
        $servico = [
            'codigo' => $this->texto($item->codigo),
            'descricao' => $this->texto($item->descricao),
            'unidade' => $this->texto($item->unidade),
            'quantidade' => $this->decimal($item->quantidade, 3),
            'valor' => $this->decimal($item->valor),
            'total' => $this->decimal($item->total),
            'cTribNac' => $this->texto($item->c_trib_nac),
            'cNBS' => $this->texto($item->c_nbs),
        ];

        $municipal = $this->texto($item->c_trib_mun);
        $indicador = $this->texto($item->c_ind_op);

        if ($municipal !== null) {
            $servico['cTribMun'] = $municipal;
        }

        if ($indicador !== null) {
            $servico['cIndOp'] = $indicador;
        }

        $obra = NfseObra::paraXml($item->c_trib_nac, [
            'obra_tipo' => $item->obra_tipo,
            'obra_insc_imob_fisc' => $item->obra_insc_imob_fisc,
            'obra_c_obra' => $item->obra_c_obra,
            'obra_c_cib' => $item->obra_c_cib,
            'obra_cep' => $item->obra_cep,
            'obra_logradouro' => $item->obra_logradouro,
            'obra_numero' => $item->obra_numero,
            'obra_complemento' => $item->obra_complemento,
            'obra_bairro' => $item->obra_bairro,
        ]);

        if ($obra !== null) {
            $servico['obra'] = $obra;
        }

        return $servico;
    }

    private function texto(mixed $value): ?string
    {
        $texto = trim((string) $value);

        return $texto === '' ? null : $texto;
    }

    private function decimal(mixed $value, int $scale = 2): string
    {
        $texto = trim((string) $value);

        if ($texto === '' || ! is_numeric($texto)) {
            return $scale === 3 ? '0.000' : '0.00';
        }

        return bcadd($texto, '0', $scale);
    }
}
