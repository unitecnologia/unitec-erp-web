<?php

namespace App\Support\Erp\Nfse;

use App\Models\Empresa;
use App\Models\Nfse;
use App\Models\NfseDpsSequencia;
use App\Models\Person;
use App\Models\Product;
use App\Support\Erp\CepLookupService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class NfseGravarService
{
    /**
     * @param  array{
     *     tomador_id: int,
     *     tomador_nome: string,
     *     tomador_cpf_cnpj: ?string,
     *     tomador_telefone: ?string,
     *     tomador_endereco: ?string,
     *     tomador_numero: ?string,
     *     tomador_bairro: ?string,
     *     tomador_cep: ?string,
     *     tomador_cidade: ?string,
     *     tomador_uf: ?string,
     *     tomador_cidade_codigo: ?string,
     *     tomador_email: ?string,
     *     competencia: string,
     *     data_emissao: string,
     *     municipio_incidencia: ?string,
     *     municipio_prestacao_codigo: string,
     *     municipio_prestacao_nome: ?string,
     *     municipio_prestacao_uf: ?string,
     *     trib_issqn: string,
     *     tp_ret_issqn: string,
     *     aliquota_iss?: ?string,
     *     valor_servicos: string,
     *     desconto: string,
     *     iss: string,
     *     total: string
     * }  $cabecalho
     * @param  list<array{
     *     product_id: ?int,
     *     codigo: string,
     *     descricao: string,
     *     unidade: ?string,
     *     quantidade: string,
     *     valor: string,
     *     total: string,
     *     c_trib_nac?: ?string,
     *     c_nbs?: ?string,
     *     c_trib_mun?: ?string,
     *     c_ind_op?: ?string
     * }>  $itens
     */
    public function gravar(int $empresaId, ?int $nfseId, array $cabecalho, array $itens): Nfse
    {
        $this->validar($empresaId, $cabecalho, $itens);

        return DB::transaction(function () use ($empresaId, $nfseId, $cabecalho, $itens): Nfse {
            if ($nfseId === null) {
                [$serie, $numero] = $this->reservarNumero($empresaId);
                $nfse = new Nfse;
                $nfse->empresa_id = $empresaId;
                $nfse->serie_dps = $serie;
                $nfse->numero_dps = $numero;
                $nfse->numero_nfse = null;
                $nfse->chave = null;
                $nfse->protocolo = null;
                $nfse->status = Nfse::STATUS_ABERTA;
            } else {
                $nfse = Nfse::query()->whereKey($nfseId)->lockForUpdate()->first();

                if ($nfse === null || (int) $nfse->empresa_id !== $empresaId) {
                    throw new NfseNaoGravada('NFS-e não encontrada.');
                }

                if ($nfse->status !== Nfse::STATUS_ABERTA) {
                    throw new NfseNaoGravada('Só é possível alterar NFS-e aberta.');
                }

                $empresa = Empresa::query()->whereKey($empresaId)->first();

                if (
                    $empresa instanceof Empresa
                    && strtolower(trim((string) $empresa->nfse_provedor)) === 'ipm'
                    && $nfse->numero_nfse === null
                    && strtoupper(trim((string) $nfse->serie_dps)) !== strtoupper(trim((string) $empresa->nfse_serie_rps))
                ) {
                    [$nfse->serie_dps, $nfse->numero_dps] = $this->reservarNumeroIpm($empresa);
                }
            }

            $nfse->fill([
                'ordem_servico_id' => (
                    array_key_exists('ordem_servico_id', $cabecalho)
                    && $cabecalho['ordem_servico_id'] !== null
                    && (int) $cabecalho['ordem_servico_id'] > 0
                ) ? (int) $cabecalho['ordem_servico_id'] : $nfse->ordem_servico_id,
                'tomador_id' => $cabecalho['tomador_id'],
                'tomador_nome' => $cabecalho['tomador_nome'],
                'tomador_cpf_cnpj' => $cabecalho['tomador_cpf_cnpj'],
                'tomador_telefone' => $cabecalho['tomador_telefone'],
                'tomador_endereco' => $cabecalho['tomador_endereco'],
                'tomador_numero' => $cabecalho['tomador_numero'],
                'tomador_bairro' => $cabecalho['tomador_bairro'],
                'tomador_cep' => $cabecalho['tomador_cep'],
                'tomador_cidade' => $cabecalho['tomador_cidade'],
                'tomador_uf' => $cabecalho['tomador_uf'],
                'tomador_cidade_codigo' => $cabecalho['tomador_cidade_codigo'],
                'tomador_email' => $cabecalho['tomador_email'],
                'competencia' => $cabecalho['competencia'],
                'data_emissao' => $cabecalho['data_emissao'],
                'municipio_incidencia' => $cabecalho['municipio_incidencia'],
                'municipio_prestacao_codigo' => preg_replace('/\D/', '', (string) $cabecalho['municipio_prestacao_codigo']) ?: null,
                'municipio_prestacao_nome' => $cabecalho['municipio_prestacao_nome'],
                'municipio_prestacao_uf' => $cabecalho['municipio_prestacao_uf'],
                'trib_issqn' => $cabecalho['trib_issqn'],
                'tp_ret_issqn' => $cabecalho['tp_ret_issqn'],
                'aliquota_iss' => $cabecalho['aliquota_iss'] ?? null,
                'valor_servicos' => $cabecalho['valor_servicos'],
                'desconto' => $cabecalho['desconto'] ?? '0.00',
                'iss' => '0.00',
                'total' => $cabecalho['total'],
            ]);
            $nfse->save();

            $nfse->itens()->delete();

            $produtos = $this->produtosFiscais($itens);

            foreach ($itens as $indice => $item) {
                $productId = $item['product_id'];
                $produto = $productId !== null ? $produtos->get($productId) : null;

                if ($produto === null) {
                    $productId = null;
                    $fiscal = $this->snapshotFiscalDoItem($item);
                } else {
                    $fiscal = $this->snapshotFiscalDoProduto($produto);
                }

                $tamanhoObra = NfseObra::erroTamanho($fiscal['c_trib_nac'], $item);

                if ($tamanhoObra !== null) {
                    throw new NfseNaoGravada($tamanhoObra);
                }

                $nfse->itens()->create([
                    'product_id' => $productId,
                    'ordem' => $indice + 1,
                    'codigo' => $item['codigo'],
                    'descricao' => $item['descricao'],
                    'unidade' => $item['unidade'],
                    'quantidade' => $item['quantidade'],
                    'valor' => $item['valor'],
                    'total' => $item['total'],
                    ...$fiscal,
                    ...NfseObra::colunas($fiscal['c_trib_nac'], $item),
                ]);
            }

            return $nfse->fresh(['itens']);
        });
    }

    /**
     * Nacional continua na série DPS. IPM reserva a série e o próximo RPS configurados.
     *
     * @return array{0: string, 1: int}
     */
    private function reservarNumero(int $empresaId): array
    {
        $empresa = Empresa::query()->whereKey($empresaId)->first();

        if ($empresa instanceof Empresa && strtolower(trim((string) $empresa->nfse_provedor)) === 'ipm') {
            return $this->reservarNumeroIpm($empresa);
        }

        $serie = NfseDpsSequencia::serieEmUso($empresaId);

        return [$serie, NfseDpsSequencia::proximo($empresaId, $serie)];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function reservarNumeroIpm(Empresa $empresa): array
    {
        $serie = strtoupper(trim((string) $empresa->nfse_serie_rps));

        if (preg_match('/^[A-Z0-9]{1,5}$/', $serie) !== 1) {
            throw new NfseNaoGravada('Informe a série RPS da NFS-e.');
        }

        $desejado = max(1, (int) ($empresa->nfse_proximo_rps ?? 1));
        $agora = now();

        NfseDpsSequencia::query()->insertOrIgnore([
            'empresa_id' => $empresa->id,
            'serie_dps' => $serie,
            'ultimo_numero' => 0,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $sequencia = NfseDpsSequencia::query()
            ->where('empresa_id', $empresa->id)
            ->where('serie_dps', $serie)
            ->lockForUpdate()
            ->first();

        if ($sequencia === null) {
            throw new NfseNaoGravada('Não foi possível reservar o número do RPS.');
        }

        $maxUsado = (int) (Nfse::query()
            ->where('empresa_id', $empresa->id)
            ->where('serie_dps', $serie)
            ->max('numero_dps') ?? 0);

        if ((int) $sequencia->ultimo_numero < ($desejado - 1) && $maxUsado < $desejado) {
            $sequencia->ultimo_numero = $desejado - 1;
            $sequencia->save();
        }

        return [$serie, NfseDpsSequencia::proximo((int) $empresa->id, $serie)];
    }

    /**
     * @param  list<array<string, mixed>>  $itens
     * @return Collection<int, Product>
     */
    private function produtosFiscais(array $itens): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn (array $item): int => (int) ($item['product_id'] ?? 0),
            $itens,
        ))));

        if ($ids === []) {
            return collect();
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'c_trib_nac', 'c_nbs', 'c_trib_mun', 'c_ind_op'])
            ->keyBy('id');
    }

    /**
     * @return array{c_trib_nac: ?string, c_nbs: ?string, c_trib_mun: ?string, c_ind_op: ?string}
     */
    private function snapshotFiscalDoProduto(Product $produto): array
    {
        return [
            'c_trib_nac' => $this->textoFiscal($produto->c_trib_nac),
            'c_nbs' => $this->textoFiscal($produto->c_nbs),
            'c_trib_mun' => $this->textoFiscal($produto->c_trib_mun),
            'c_ind_op' => $this->textoFiscal($produto->c_ind_op),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array{c_trib_nac: ?string, c_nbs: ?string, c_trib_mun: ?string, c_ind_op: ?string}
     */
    private function snapshotFiscalDoItem(array $item): array
    {
        return [
            'c_trib_nac' => $this->textoFiscal($item['c_trib_nac'] ?? null),
            'c_nbs' => $this->textoFiscal($item['c_nbs'] ?? null),
            'c_trib_mun' => $this->textoFiscal($item['c_trib_mun'] ?? null),
            'c_ind_op' => $this->textoFiscal($item['c_ind_op'] ?? null),
        ];
    }

    private function textoFiscal(mixed $value): ?string
    {
        $texto = trim((string) $value);

        return $texto === '' ? null : $texto;
    }

    /**
     * @param  array<string, mixed>  $cabecalho
     * @param  list<array<string, mixed>>  $itens
     */
    private function validar(int $empresaId, array $cabecalho, array $itens): void
    {
        if ($empresaId < 1) {
            throw new NfseNaoGravada('Nenhuma empresa selecionada.');
        }

        $tomadorId = (int) ($cabecalho['tomador_id'] ?? 0);

        if ($tomadorId < 1 || ! Person::query()->whereKey($tomadorId)->exists()) {
            throw new NfseNaoGravada('Selecione o tomador.');
        }

        if (trim((string) ($cabecalho['tomador_nome'] ?? '')) === '') {
            throw new NfseNaoGravada('Selecione o tomador.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($cabecalho['competencia'] ?? '')) !== 1) {
            throw new NfseNaoGravada('Informe a competência.');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($cabecalho['data_emissao'] ?? '')) !== 1) {
            throw new NfseNaoGravada('Informe a data de emissão.');
        }

        $codigoMunicipio = preg_replace('/\D/', '', (string) ($cabecalho['municipio_prestacao_codigo'] ?? '')) ?? '';

        if (! CepLookupService::isValidIbgeCode($codigoMunicipio)) {
            throw new NfseNaoGravada('Falta o código IBGE do município da prestação.');
        }

        if ($itens === []) {
            throw new NfseNaoGravada('Informe ao menos um serviço.');
        }

        $soma = '0.00';
        $somaBrutos = '0.00';
        $somaDescontos = '0.00';

        foreach ($itens as $item) {
            $codigo = trim((string) ($item['codigo'] ?? ''));
            $descricao = trim((string) ($item['descricao'] ?? ''));
            $quantidade = (string) ($item['quantidade'] ?? '');
            $valor = (string) ($item['valor'] ?? '');
            $desconto = (string) ($item['desconto'] ?? '0.00');
            $acrescimo = (string) ($item['acrescimo'] ?? '0.00');
            $total = (string) ($item['total'] ?? '');

            if ($codigo === '' || $descricao === '') {
                throw new NfseNaoGravada('Serviço sem código ou descrição não pode ser gravado.');
            }

            if (bccomp($quantidade, '0', 3) !== 1) {
                throw new NfseNaoGravada('Informe a quantidade do serviço.');
            }

            if (bccomp($valor, '0', 2) === -1) {
                throw new NfseNaoGravada('Informe o valor do serviço.');
            }

            if (bccomp($desconto, '0', 2) === -1 || bccomp($acrescimo, '0', 2) === -1) {
                throw new NfseNaoGravada('Desconto e acréscimo não podem ser negativos.');
            }

            $bruto = $this->arredondar(bcmul($quantidade, $valor, 8), 2);
            $esperado = $this->arredondar(bcadd(bcsub($bruto, $desconto, 8), $acrescimo, 8), 2);

            if (bccomp($esperado, $total, 2) !== 0) {
                throw new NfseNaoGravada('O total do serviço não confere com quantidade, valor, desconto e acréscimo.');
            }

            $soma = $this->arredondar(bcadd($soma, $total, 8), 2);
            $somaBrutos = $this->arredondar(bcadd($somaBrutos, bcadd($bruto, $acrescimo, 8), 8), 2);
            $somaDescontos = $this->arredondar(bcadd($somaDescontos, $desconto, 8), 2);
        }

        if (bccomp($somaBrutos, (string) ($cabecalho['valor_servicos'] ?? ''), 2) !== 0
            || bccomp($somaDescontos, (string) ($cabecalho['desconto'] ?? '0.00'), 2) !== 0
            || bccomp($soma, (string) ($cabecalho['total'] ?? ''), 2) !== 0) {
            throw new NfseNaoGravada('O total da NFS-e não confere com os serviços.');
        }
    }

    private function arredondar(string $value, int $scale): string
    {
        $negative = str_starts_with($value, '-');
        $absolute = $negative ? substr($value, 1) : $value;
        $increment = '0.'.str_repeat('0', $scale).'5';
        $rounded = bcadd($absolute, $increment, $scale);

        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            return '-'.$rounded;
        }

        return $rounded;
    }
}
