<?php

namespace App\Support\Erp\Import;

use App\Models\CaixaConta;
use App\Models\Cfop;
use App\Models\Contador;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use App\Models\Grupo;
use App\Models\Marca;
use App\Models\Terminal;
use App\Models\Unidade;
use App\Models\User;
use App\Models\Vendedor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Fase 1: cadastros auxiliares e operacionais do Firebird → ERP web.
 */
class FirebirdCadastrosImportService
{
    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importCfops(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
                $descricao = $this->fixFbText($row['DESCRICAO'] ?? $row['descricao'] ?? null);

                if ($codigo < 1 || $descricao === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = Cfop::query()->where('codigo', $codigo)->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $tipo = Str::upper(trim((string) ($row['TIPO'] ?? $row['tipo'] ?? 'E')));
                $operacao = Str::upper(trim((string) ($row['OPERACAO'] ?? $row['operacao'] ?? 'I')));

                $payload = [
                    'codigo' => $codigo,
                    'descricao' => $descricao,
                    'tipo' => in_array($tipo, ['E', 'S'], true) ? $tipo : 'E',
                    'operacao' => in_array($operacao, ['I', 'E'], true) ? $operacao : 'I',
                    'movimenta_estoque' => $this->snToBool($row['MOV_ES'] ?? $row['mov_es'] ?? 'S'),
                    'ativo' => $this->snToBool($row['ATIVO'] ?? $row['ativo'] ?? 'S'),
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    Cfop::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importGrupos(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        return $this->upsertByNome(
            $rows,
            Grupo::class,
            fn (array $row): ?string => $this->upperOrNull($row['DESCRICAO'] ?? $row['descricao'] ?? null),
            fn (string $nome, array $row): array => [
                'nome' => $nome,
                'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
            ],
            $updateExisting,
            $dryRun,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importMarcas(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        return $this->upsertByNome(
            $rows,
            Marca::class,
            fn (array $row): ?string => $this->upperOrNull($row['DESCRICAO'] ?? $row['descricao'] ?? null),
            fn (string $nome, array $row): array => [
                'nome' => $nome,
                'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
            ],
            $updateExisting,
            $dryRun,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importUnidades(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $sigla = Str::upper(trim((string) ($row['CODIGO'] ?? $row['codigo'] ?? '')));
                $descricao = trim((string) ($row['DESCRICAO'] ?? $row['descricao'] ?? ''));

                if ($sigla === '') {
                    $stats['skipped']++;

                    continue;
                }

                if ($descricao === '') {
                    $descricao = $sigla;
                }

                $existing = Unidade::query()->where('sigla', $sigla)->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $payload = [
                    'sigla' => $sigla,
                    'descricao' => Str::upper($descricao),
                    'ativo' => true,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    Unidade::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importFormasPagamento(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $contasByCodigo = CaixaConta::query()
            ->whereNotNull('codigo')
            ->pluck('id', 'codigo')
            ->all();

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $contasByCodigo, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
                $descricao = $this->upperOrNull($row['DESCRICAO'] ?? $row['descricao'] ?? null);

                if ($codigo < 1 || $descricao === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = FormaPagamento::query()->where('codigo', $codigo)->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $fkConta = filled($row['FKCONTADESTINO'] ?? $row['fkcontadestino'] ?? null)
                    ? (int) ($row['FKCONTADESTINO'] ?? $row['fkcontadestino'])
                    : null;

                $payload = [
                    'codigo' => $codigo,
                    'descricao' => $descricao,
                    'conta_destino_id' => $fkConta ? ($contasByCodigo[$fkConta] ?? null) : null,
                    'tipo' => $this->mapFormaTipo($row['TIPO'] ?? null, $descricao),
                    'taxa_cartao' => BrDecimalImport::parse($row['TAXA'] ?? 0),
                    'prazo_cartao' => (int) ($row['DIAS'] ?? 0),
                    'max_parcelas' => max(0, (int) ($row['PARCELAS'] ?? 0)),
                    'intervalo_parcelas' => max(0, (int) ($row['INTERVALO'] ?? 0)),
                    'atalho' => trim((string) ($row['ATALHO'] ?? '')) ?: null,
                    'tipo_movimento' => $this->mapTipoMovimento($row),
                    'usa_tef' => $this->snToBool($row['USA_TEF'] ?? 'N'),
                    'usa_super_tef' => $this->snToBool($row['USA_SUPERTEF'] ?? 'N'),
                    'aparece_venda' => $this->snToBool($row['USAVD'] ?? 'N'),
                    'aparece_contas_receber' => $this->snToBool($row['USACR'] ?? 'N'),
                    'nfce' => false,
                    'disponivel_mobile' => false,
                    'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    FormaPagamento::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importCaixaContas(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $codigo = (int) ($row['CODIGO'] ?? $row['codigo'] ?? 0);
                $nome = $this->upperOrNull($row['DESCRICAO'] ?? $row['descricao'] ?? null);

                if ($codigo < 1 || $nome === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = CaixaConta::query()->where('codigo', $codigo)->first()
                    ?? CaixaConta::query()->whereRaw('UPPER(nome) = ?', [$nome])->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $isGeral = $nome === CaixaConta::NOME_CAIXA_GERAL;
                $payload = [
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'tipo' => $isGeral ? CaixaConta::TIPO_SUBCAIXA : $this->mapContaTipo($row['TIPO'] ?? null),
                    'situacao' => $this->mapContaSituacao($row['SITUACAO'] ?? null),
                    'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
                    'sistema' => $isGeral,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    CaixaConta::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importVendedores(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $empresaId = Empresa::query()->orderBy('id')->value('id');

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $empresaId, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $codigo = trim((string) ($row['CODIGO'] ?? $row['codigo'] ?? ''));
                $nome = $this->upperOrNull($row['NOME'] ?? $row['nome'] ?? null);

                if ($codigo === '' || $nome === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = Vendedor::query()->where('codigo', $codigo)->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $empresaFb = filled($row['EMPRESA'] ?? null) ? (int) $row['EMPRESA'] : null;
                $empresaLocal = $empresaFb
                    ? (Empresa::query()->where('codigo', $empresaFb)->value('id') ?? $empresaId)
                    : $empresaId;

                $payload = [
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
                    'comissao_av' => BrDecimalImport::parse($row['CMA'] ?? 0),
                    'comissao_ap' => BrDecimalImport::parse($row['CMP'] ?? 0),
                    'empresa_id' => $empresaLocal,
                    'efetua_venda' => true,
                    'setor_vendas' => true,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $vendedor = $existing;
                    $stats['updated']++;
                } else {
                    $vendedor = Vendedor::query()->create($payload);
                    $stats['created']++;
                }

                if ($empresaLocal) {
                    $vendedor->empresas()->syncWithoutDetaching([$empresaLocal]);
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importUsuarios(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $empresaId = Empresa::query()->orderBy('id')->value('id');

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $empresaId, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $login = Str::upper(trim((string) ($row['LOGIN'] ?? $row['login'] ?? $row['NOME'] ?? '')));
                $senhaFb = trim((string) ($row['SENHA'] ?? $row['senha'] ?? ''));
                // FB grava SENHA via Dados.crypt('C', ...) — descriptografar antes do hash web.
                $senhaPlain = $senhaFb !== '' ? FirebirdDelphiCrypt::decrypt($senhaFb) : '';

                if ($login === '') {
                    $stats['skipped']++;

                    continue;
                }

                $existing = User::query()->whereRaw('UPPER(name) = ?', [$login])->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $fkVendedor = filled($row['FK_VENDEDOR'] ?? $row['fk_vendedor'] ?? null)
                    ? trim((string) ($row['FK_VENDEDOR'] ?? $row['fk_vendedor']))
                    : null;

                $vendedorId = $fkVendedor !== null && $fkVendedor !== ''
                    ? Vendedor::query()->where('codigo', $fkVendedor)->value('id')
                    : null;

                $payload = [
                    'name' => $login,
                    'empresa_id' => $empresaId,
                    'is_admin' => $this->snToBool($row['USU_MASTER'] ?? 'N'),
                    'ativo' => $this->snToBool($row['ATIVO'] ?? 'S'),
                    'vendedor_id' => $vendedorId,
                ];

                if ($senhaPlain !== '') {
                    $payload['password'] = Hash::make($senhaPlain);
                    $payload['senha'] = $senhaPlain;
                } elseif (! $existing) {
                    $payload['password'] = Hash::make(Str::random(12));
                    $payload['senha'] = null;
                }

                $appSenha = trim((string) ($row['SENHA_APP'] ?? $row['APP_SENHA'] ?? ''));
                if ($appSenha !== '') {
                    $payload['senha_app_forca_vendas'] = FirebirdDelphiCrypt::decrypt($appSenha);
                }

                if ($existing) {
                    $existing->fill($payload)->save();
                    $user = $existing;
                    $stats['updated']++;
                } else {
                    $user = User::query()->create($payload);
                    $stats['created']++;
                }

                if ($empresaId) {
                    $user->empresas()->syncWithoutDetaching([$empresaId]);
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importContadores(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $codigo = trim((string) ($row['CODIGO'] ?? $row['codigo'] ?? ''));
                $nome = $this->upperOrNull($row['NOME'] ?? $row['nome'] ?? null);

                if ($codigo === '' || $nome === null) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = Contador::query()->where('codigo', $codigo)->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $cnpj = preg_replace('/\D/', '', (string) ($row['CNPJ'] ?? '')) ?: null;
                $cpf = preg_replace('/\D/', '', (string) ($row['CPF'] ?? '')) ?: null;
                $email = trim((string) ($row['EMAIL'] ?? ''));
                if ($email !== '' && str_contains($email, ';')) {
                    $email = trim(explode(';', $email)[0]);
                }

                $payload = [
                    'codigo' => $codigo,
                    'nome' => $nome,
                    'cnpj_cpf' => $cnpj ?: $cpf,
                    'crc' => trim((string) ($row['CRC'] ?? '')) ?: null,
                    'cep' => preg_replace('/\D/', '', (string) ($row['CEP'] ?? '')) ?: null,
                    'endereco' => $this->upperOrNull($row['ENDERECO'] ?? null),
                    'numero' => trim((string) ($row['NUMERO'] ?? '')) ?: null,
                    'bairro' => $this->upperOrNull($row['BAIRRO'] ?? null),
                    'cidade' => null,
                    'uf' => $this->mapUf($row['UF'] ?? null),
                    'email' => $email !== '' ? $email : null,
                    'fone' => trim((string) ($row['FONE'] ?? '')) ?: null,
                ];

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    Contador::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    public function importTerminais(array $rows, bool $updateExisting = true, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $empresaFallback = Empresa::query()->orderBy('id')->value('id');

        DB::transaction(function () use ($rows, $updateExisting, $dryRun, $empresaFallback, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $nome = trim((string) ($row['NOME'] ?? $row['nome'] ?? ''));
                $ip = trim((string) ($row['IP'] ?? $row['ip'] ?? '')) ?: null;

                if ($nome === '') {
                    $stats['skipped']++;

                    continue;
                }

                $empresaFb = filled($row['EMPRESA'] ?? null) ? (int) $row['EMPRESA'] : null;
                $empresaId = $empresaFb
                    ? (int) (Empresa::query()->where('codigo', $empresaFb)->value('id') ?? $empresaFallback)
                    : (int) $empresaFallback;

                if (! $empresaId) {
                    $stats['skipped']++;

                    continue;
                }

                $existing = Terminal::query()
                    ->where('empresa_id', $empresaId)
                    ->where('nome', $nome)
                    ->first();

                if (! $existing && $ip) {
                    $existing = Terminal::query()
                        ->where('empresa_id', $empresaId)
                        ->where('ip', $ip)
                        ->first();
                }

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $payload = array_merge(Terminal::defaultAttributes($empresaId), [
                    'nome' => $nome,
                    'ip' => $ip,
                    'numero_loja' => filled($row['NUMERO_LOJA'] ?? null) ? (int) $row['NUMERO_LOJA'] : null,
                    'empresa_ativa' => filled($row['EMPRESA_ATIVA'] ?? null) ? (int) $row['EMPRESA_ATIVA'] : $empresaFb,
                    'numero_logico_terminal' => filled($row['NUMERO_LOGICO_TERMINAL'] ?? null)
                        ? (int) $row['NUMERO_LOGICO_TERMINAL']
                        : null,
                    'eh_caixa' => $this->snToBool($row['EH_CAIXA'] ?? 'S'),
                    'pdv' => $this->snToBool($row['PDV'] ?? 'S') || strtoupper(trim((string) ($row['FLAG'] ?? ''))) === 'P',
                    'ativo' => true,
                    'restaurante' => $this->snToBool($row['RESTAURANTE'] ?? 'N'),
                    'delivery' => $this->snToBool($row['DELIVERY'] ?? 'N'),
                    'logado' => $this->snToBool($row['LOGADO'] ?? 'N'),
                    'usa_tef' => $this->snToBool($row['USA_TEF'] ?? 'N'),
                    'usa_pos' => $this->snToBool($row['USA_POS'] ?? 'N'),
                    'exibe_f3' => $this->snToBool($row['EXIBE_F3'] ?? 'N'),
                    'exibe_f4' => $this->snToBool($row['EXIBE_F4'] ?? 'N'),
                    'exibe_f5' => $this->snToBool($row['EXIBE_F5'] ?? 'N'),
                    'exibe_f6' => $this->snToBool($row['EXIBE_F6'] ?? 'N'),
                    'pesquisa_rapida' => $this->snToBool($row['PESQUISA_RAPIDA'] ?? 'N'),
                    'ler_peso' => $this->snToBool($row['LER_PESO'] ?? 'N'),
                    'busca_balanca_barras' => $this->snToBool($row['BUSCA_BALANCA_BARRAS'] ?? 'S'),
                    'mensagem_pdv' => trim((string) ($row['MENSAGEM_PDV'] ?? '')) ?: null,
                    'mostrar_mensagem_pdv' => $this->snToBool($row['MOSTRAR_MENSAGEM_PDV'] ?? 'N'),
                    'mostrar_tela_caixa_livre' => $this->snToBool($row['MOSTRAR_TELA_CAIXA_LIVRE'] ?? 'N'),
                    'time_tela_caixa_livre' => filled($row['TIME_TELA_CAIXA_LIVRE'] ?? null)
                        ? (int) $row['TIME_TELA_CAIXA_LIVRE']
                        : null,
                    'imprime' => $this->snToBool($row['IMPRIME'] ?? 'S'),
                    'usa_gaveta' => $this->snToBool($row['USAGAVETA'] ?? 'N'),
                    'fab_impressora' => trim((string) ($row['FABIMPRESSORA'] ?? '')) ?: null,
                    'modelo' => trim((string) ($row['MODELO'] ?? '')) ?: null,
                    'porta' => trim((string) ($row['PORTA'] ?? '')) ?: null,
                    'velocidade' => filled($row['VELOCIDADE'] ?? null) ? (int) $row['VELOCIDADE'] : null,
                    'nvias' => max(1, (int) ($row['NVIAS'] ?? 1)),
                    'serie' => trim((string) ($row['SERIE'] ?? '')) ?: null,
                    'numeracao_inicial' => filled($row['NUMERACAO_INICIAL'] ?? null)
                        ? (int) $row['NUMERACAO_INICIAL']
                        : null,
                    'usar_numero_inicial' => $this->snToBool($row['USAR_NUMERO_INICIAL'] ?? 'N'),
                    'tipo_impressora' => trim((string) ($row['TIPOIMPRESSORA'] ?? '0')) ?: '0',
                    'tipo_fechamento' => trim((string) ($row['TIPOFECHAMENTO'] ?? '')) ?: null,
                    'meia_folha' => $this->snToBool($row['MEIAFOLHA'] ?? 'N'),
                    'pagina_codigo' => trim((string) ($row['PAGINA_CODIGO'] ?? '')) ?: null,
                    'margem_superior' => BrDecimalImport::parse($row['MARGEM_SUPERIOR'] ?? null),
                    'margem_inferior' => BrDecimalImport::parse($row['MARGEM_INFERIOR'] ?? null),
                    'margem_esquerda' => BrDecimalImport::parse($row['MARGEM_ESQUERDA'] ?? null),
                    'margem_direita' => BrDecimalImport::parse($row['MARGEM_DIREITA'] ?? null),
                    'largura_bobina' => filled($row['LARGURA_BOBINA'] ?? null) ? (int) $row['LARGURA_BOBINA'] : null,
                    'tamanho_fonte' => filled($row['TAMANHO_FONTE'] ?? null) ? (int) $row['TAMANHO_FONTE'] : null,
                    'balanca_porta' => trim((string) ($row['BALANCA_PORTA'] ?? '')) ?: null,
                    'balanca_velocidade' => trim((string) ($row['BALANCA_VELOCIDADE'] ?? '')) ?: null,
                    'balanca_marca' => trim((string) ($row['BALANCA_MARCA'] ?? '')) ?: null,
                    'balanca_paridade' => trim((string) ($row['BALANCA_PARIDADE'] ?? '')) ?: null,
                    'balanca_databits' => trim((string) ($row['BALANCA_DATABITS'] ?? '')) ?: null,
                    'balanca_stopbits' => trim((string) ($row['BALANCA_STOPBITS'] ?? '')) ?: null,
                    'balanca_handshaking' => trim((string) ($row['BALANCA_HANDSHAKING'] ?? '')) ?: null,
                    'qtd_tentativa_conect_bal' => filled($row['QTD_TENTATIVA_CONECT_BAL'] ?? null)
                        ? (int) $row['QTD_TENTATIVA_CONECT_BAL']
                        : null,
                    'caminho_sat_dll' => trim((string) ($row['CAMINHO_SAT_DLL'] ?? '')) ?: null,
                    'modelo_sat_dll' => trim((string) ($row['MODELO_SAT_DLL'] ?? '')) ?: null,
                    'tipo_sat_dll' => trim((string) ($row['TIPO_SAT_DLL'] ?? '')) ?: null,
                    'modelo_tef' => filled($row['MODELO_TEF'] ?? null) ? (int) $row['MODELO_TEF'] : null,
                    'tef_gerenciador' => filled($row['TEF_GERENCIADOR'] ?? null) ? (int) $row['TEF_GERENCIADOR'] : null,
                    'ip_servidor_tef' => trim((string) ($row['IP_SERVIDOR_TEF'] ?? '')) ?: null,
                    'porta_pin_pad' => filled($row['PORTA_PIN_PAD'] ?? null) ? (int) $row['PORTA_PIN_PAD'] : null,
                    'mensagem_pin_pad' => trim((string) ($row['MENSAGEM_PIN_PAD'] ?? '')) ?: null,
                    'tef_max_cartoes' => filled($row['TEF_MAX_CARTOES'] ?? null) ? (int) $row['TEF_MAX_CARTOES'] : null,
                    'tef_troco_maximo' => BrDecimalImport::parse($row['TEF_TROCO_MAXIMO'] ?? null),
                    'tef_via_reduzida' => $this->snToBool($row['TEF_VIA_REDUZIDA'] ?? 'N'),
                    'tef_multiplos_cartoes' => $this->snToBool($row['TEF_MULTIPLO_CARTOES'] ?? 'N'),
                    'caminho_cozinha' => trim((string) ($row['CAMINHO_COZINHA'] ?? '')) ?: null,
                    'caminho_bar' => trim((string) ($row['CAMINHO_BAR'] ?? '')) ?: null,
                ]);

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    Terminal::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  callable(array<string, mixed>): (?string)  $nomeResolver
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $payloadBuilder
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{created: int, updated: int, skipped: int}
     */
    protected function upsertByNome(
        array $rows,
        string $modelClass,
        callable $nomeResolver,
        callable $payloadBuilder,
        bool $updateExisting,
        bool $dryRun,
    ): array {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        DB::transaction(function () use ($rows, $modelClass, $nomeResolver, $payloadBuilder, $updateExisting, $dryRun, &$stats): void {
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    $stats['skipped']++;

                    continue;
                }

                $nome = $nomeResolver($row);

                if ($nome === null || $nome === '') {
                    $stats['skipped']++;

                    continue;
                }

                $existing = $modelClass::query()->whereRaw('UPPER(nome) = ?', [$nome])->first();

                if ($existing && ! $updateExisting) {
                    $stats['skipped']++;

                    continue;
                }

                if ($dryRun) {
                    $existing ? $stats['updated']++ : $stats['created']++;

                    continue;
                }

                $payload = $payloadBuilder($nome, $row);

                if ($existing) {
                    $existing->fill($payload)->save();
                    $stats['updated']++;
                } else {
                    $modelClass::query()->create($payload);
                    $stats['created']++;
                }
            }
        });

        return $stats;
    }

    protected function mapFormaTipo(mixed $tipo, string $descricao): ?string
    {
        $code = Str::upper(trim((string) ($tipo ?? '')));
        $desc = Str::upper(Str::ascii($descricao));

        return match ($code) {
            'D' => 'dinheiro',
            'I' => 'pix',
            'E' => 'cartao_debito',
            'C' => 'cartao_credito',
            'X' => 'deposito',
            'A' => 'tef',
            'T' => 'troca',
            'B' => 'boleto',
            'H' => 'cheque',
            'R' => 'crediario',
            default => match (true) {
                str_contains($desc, 'PIX') => 'pix',
                str_contains($desc, 'DINHEIRO') => 'dinheiro',
                str_contains($desc, 'DEBIT') => 'cartao_debito',
                str_contains($desc, 'CREDIT') => 'cartao_credito',
                str_contains($desc, 'TEF') => 'tef',
                str_contains($desc, 'DEPOSIT') => 'deposito',
                str_contains($desc, 'TROCA') => 'troca',
                default => null,
            },
        };
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function mapTipoMovimento(array $row): string
    {
        $tipo = Str::upper(trim((string) ($row['TIPO'] ?? '')));

        if ($tipo === 'T') {
            return 'troca';
        }

        if ($this->snToBool($row['GERACR'] ?? 'N')) {
            return 'contas_receber';
        }

        if ($this->snToBool($row['GERACH'] ?? 'N')) {
            return 'deposito';
        }

        if ($this->snToBool($row['USAVD'] ?? 'N') || in_array($tipo, ['D', 'I', 'E', 'C', 'A'], true)) {
            return 'caixa';
        }

        return 'nenhum';
    }

    protected function mapContaTipo(mixed $tipo): string
    {
        return match (Str::upper(trim((string) ($tipo ?? '')))) {
            'X', 'C', 'CAIXA', 'PDV' => CaixaConta::TIPO_PDV,
            'B', 'BANCO' => CaixaConta::TIPO_BANCO,
            'F', 'COFRE' => CaixaConta::TIPO_COFRE,
            default => CaixaConta::TIPO_SUBCAIXA,
        };
    }

    protected function mapContaSituacao(mixed $situacao): string
    {
        $value = Str::upper(trim((string) ($situacao ?? '')));

        return match ($value) {
            'F', 'FECHADO' => CaixaConta::SITUACAO_FECHADO,
            default => CaixaConta::SITUACAO_ABERTO,
        };
    }

    protected function mapUf(mixed $value): ?string
    {
        $uf = Str::upper(trim((string) ($value ?? '')));

        return strlen($uf) === 2 ? $uf : null;
    }

    protected function snToBool(mixed $value): bool
    {
        return in_array(Str::upper(trim((string) $value)), ['S', '1', 'T', 'Y', 'TRUE'], true);
    }

    protected function upperOrNull(mixed $value): ?string
    {
        $text = Str::upper(trim((string) ($value ?? '')));

        return $text !== '' ? $text : null;
    }

    protected function fixFbText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($text === '') {
            return null;
        }

        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @iconv('Windows-1252', 'UTF-8//IGNORE', $text);
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        return Str::upper($text);
    }
}
