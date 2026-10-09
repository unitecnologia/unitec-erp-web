<?php

namespace App\Support\Erp\Hotfix;

use App\Models\Empresa;
use App\Support\Erp\Atualizacao\AtualizacaoApplyService;
use App\Support\Erp\ErpUpdateProcessLauncher;
use App\Support\Erp\ErpUpdateService;
use App\Support\Erp\License\LicencaHttpClient;
use App\Support\Erp\License\LicencaRemotaService;
use App\Support\Erp\License\LicencaSnapshot;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use ZipArchive;

/**
 * Hotfix por cliente: só arquivos corrigidos da versão instalada, sem alterar config/unitec.php.
 *
 * Pacote no GitHub (release "hotfix-{versao}", nunca o release "update"):
 *   Unitec-ERP-Hotfix.zip         hotfix.json + arquivos/{caminho relativo}
 *   Unitec-ERP-Hotfix.zip.sha256  "{sha256}  Unitec-ERP-Hotfix.zip" + "size={bytes}"
 *
 *                                 + "sig={base64}" (RSA-SHA256, ver HotfixAssinatura)
 *
 * Hotfixes são cumulativos por versão base: a revisão N contém tudo das anteriores.
 */
final class HotfixService
{
    public const DOWNLOAD_BASE = 'https://github.com/unitecnologia/unitec-erp-web/releases/download';

    public const ASSET_ZIP = 'Unitec-ERP-Hotfix.zip';

    public const MANIFESTO = 'hotfix.json';

    public const PASTA_ARQUIVOS = 'arquivos';

    public const TIPO_MANIFESTO = 'unitec-hotfix';

    /** Pastas que um hotfix pode alterar. Migrations, config, rotas e vendor exigem atualização oficial. */
    private const PREFIXOS_PERMITIDOS = ['app/', 'resources/views/', 'lang/'];

    private const EXTENSOES_PERMITIDAS = ['php', 'json'];

    private const TAMANHO_MAXIMO_ZIP = 20 * 1024 * 1024;

    private const ESPERA_APOS_FALHA_SEGUNDOS = 6 * 3600;

    private const BACKUPS_MANTIDOS = 10;

    private const SUFIXO_TEMPORARIO = '.hotfix-tmp';

    /** O próprio mecanismo de hotfix só muda por atualização oficial (um hotfix ruim não pode desligá-lo). */
    private const CAMINHOS_BLOQUEADOS = ['app/Support/Erp/Hotfix/', 'app/Console/Commands/HotfixCommand.php'];

    private const ESTADOS_ATUALIZACAO_OFICIAL_ATIVA = ['starting', 'copying', 'discovering', 'migrating', 'caching', 'finalizing'];

    public static function tag(string $versao): string
    {
        return 'hotfix-'.$versao;
    }

    public static function urlZip(string $versao): string
    {
        return self::DOWNLOAD_BASE.'/'.self::tag($versao).'/'.self::ASSET_ZIP;
    }

    /**
     * @return array{resultado: string, mensagem: string}
     */
    public function executar(bool $agendado = false): array
    {
        if (! HotfixEstado::adquirirLock()) {
            return $this->resultado('em_andamento', 'Outra verificação de hotfix já está em andamento.');
        }

        try {
            HotfixPortao::fechar();

            return $this->executarComLock($agendado);
        } catch (\Throwable $e) {
            HotfixLog::line('Erro', $e->getMessage());

            return $this->resultado('erro', $e->getMessage());
        } finally {
            $this->limparTemporarios();
            HotfixEstado::liberarLock();
        }
    }

    /**
     * Reverte o último hotfix aplicado (suporte/revogação). O mesmo pacote não é reaplicado automaticamente.
     *
     * @return array{resultado: string, mensagem: string}
     */
    public function reverterUltimo(): array
    {
        if (! HotfixEstado::adquirirLock()) {
            return $this->resultado('em_andamento', 'Outra operação de hotfix está em andamento.');
        }

        try {
            $estado = HotfixEstado::ler();
            $backup = (string) ($estado['backup'] ?? '');
            $arquivos = is_array($estado['arquivos_alterados'] ?? null) ? $estado['arquivos_alterados'] : [];

            if ($backup === '' || $arquivos === [] || ! is_dir($backup)) {
                return $this->resultado('nada_a_reverter', 'Nenhum hotfix com backup para reverter.');
            }

            HotfixPortao::fechar();
            $this->comPortao(fn () => $this->restaurar($arquivos, $backup));

            $anterior = is_array($estado['anterior'] ?? null) ? $estado['anterior'] : [];
            HotfixEstado::gravar([
                ...$anterior,
                'ultima_verificacao_ts' => $estado['ultima_verificacao_ts'] ?? null,
                'recusado' => [
                    'sha256' => $estado['sha256_pacote'] ?? null,
                    'motivo' => 'revertido manualmente',
                    'definitivo' => true,
                    'em' => now()->toIso8601String(),
                ],
            ]);

            HotfixEstado::registrarHistorico([
                'evento' => 'revertido',
                'id' => $estado['id'] ?? null,
                'versao_base' => $estado['versao_base'] ?? null,
                'revisao' => $estado['revisao'] ?? null,
            ]);
            HotfixLog::line('Revertido', (string) ($estado['id'] ?? ''));

            return $this->resultado('revertido', 'Hotfix '.($estado['id'] ?? '').' revertido.');
        } catch (\Throwable $e) {
            HotfixLog::line('Erro', 'reverter: '.$e->getMessage());

            return $this->resultado('erro', $e->getMessage());
        } finally {
            HotfixEstado::liberarLock();
        }
    }

    /**
     * Carrega no processo atual as classes alteradas. Erro fatal encerra o processo com código != 0.
     *
     * @param  list<string>  $classes
     */
    public static function verificarClasses(array $classes): bool
    {
        foreach ($classes as $classe) {
            if (! class_exists($classe) && ! interface_exists($classe) && ! trait_exists($classe) && ! enum_exists($classe)) {
                HotfixLog::line('Verificacao', 'classe nao encontrada: '.$classe);

                return false;
            }
        }

        return true;
    }

    /**
     * @return array{resultado: string, mensagem: string}
     */
    private function executarComLock(bool $agendado): array
    {
        if ($this->atualizacaoOficialAtiva()) {
            return $this->resultado('atualizacao_oficial', 'Atualização oficial em andamento; hotfix adiado.');
        }

        if ($agendado && ! HotfixEstado::algumaPermiteHotfix()) {
            return $this->resultado('nao_autorizado', 'Nenhuma empresa com hotfix liberado na última consulta.');
        }

        if (! HotfixAssinatura::configurada()) {
            return $this->resultado('sem_chave', 'Esta versão não tem chave pública de hotfix; nenhum pacote é aceito.');
        }

        $negado = $this->autorizarInstalacao();

        if ($negado !== null) {
            return $this->resultado('nao_autorizado', $negado);
        }

        $versao = trim(ErpUpdateService::readInstalledVersion());

        if ($versao === '') {
            throw new RuntimeException('Versão instalada não identificada em config/unitec.php.');
        }

        HotfixEstado::atualizar(['ultima_verificacao_ts' => time()]);

        $integridade = $this->buscarIntegridade($versao);

        if ($integridade === null) {
            return $this->resultado('sem_hotfix', 'Nenhum hotfix publicado para a versão '.$versao.'.');
        }

        if (! HotfixAssinatura::valida($versao, $integridade['sha256'], $integridade['size'], $integridade['assinatura'])) {
            HotfixLog::line('Assinatura', 'invalida ou ausente sha256='.$integridade['sha256']);

            return $this->resultado('recusado', 'Assinatura do hotfix inválida ou ausente; pacote ignorado.');
        }

        $estado = HotfixEstado::ler();

        if (($estado['versao_base'] ?? null) === $versao && ($estado['sha256_pacote'] ?? null) === $integridade['sha256']) {
            return $this->resultado('ja_aplicado', 'Hotfix '.($estado['id'] ?? '').' já aplicado.');
        }

        $recusado = is_array($estado['recusado'] ?? null) ? $estado['recusado'] : [];

        if (($recusado['sha256'] ?? null) === $integridade['sha256']) {
            $desde = strtotime((string) ($recusado['em'] ?? '')) ?: 0;

            if (! empty($recusado['definitivo']) || time() - $desde < self::ESPERA_APOS_FALHA_SEGUNDOS) {
                return $this->resultado('recusado', 'Pacote recusado anteriormente: '.($recusado['motivo'] ?? ''));
            }
        }

        HotfixLog::line('Inicio', 'versao='.$versao.' sha256='.$integridade['sha256'].' size='.$integridade['size']);

        try {
            $zip = $this->baixar($versao, $integridade);
            $pasta = $this->extrair($zip);
            $manifesto = $this->lerManifesto($pasta, $versao);
        } catch (HotfixRecusadoException $e) {
            $this->recusar($integridade['sha256'], $e->getMessage(), $e->definitivo);

            return $this->resultado('recusado', $e->getMessage());
        }

        if (($estado['versao_base'] ?? null) === $versao && (int) ($estado['revisao'] ?? 0) >= $manifesto['revisao']) {
            $this->recusar($integridade['sha256'], 'revisão '.$manifesto['revisao'].' não é mais nova que a aplicada ('.$estado['revisao'].')', true);

            return $this->resultado('recusado', 'Revisão já aplicada ou mais antiga.');
        }

        try {
            $plano = $this->planejar($pasta, $manifesto);
        } catch (HotfixRecusadoException $e) {
            $this->recusar($integridade['sha256'], $e->getMessage(), $e->definitivo);

            return $this->resultado('recusado', $e->getMessage());
        }

        return $this->aplicar($versao, $integridade['sha256'], $manifesto, $plano, $estado);
    }

    /**
     * @param  array{id: string, revisao: int, descricao: string, arquivos: list<array<string, mixed>>}  $manifesto
     * @param  list<array{caminho: string, origem: string, destino: string, sha256_novo: string, sha256_atual: ?string}>  $plano
     * @param  array<string, mixed>  $estadoAnterior
     * @return array{resultado: string, mensagem: string}
     */
    private function aplicar(string $versao, string $sha256Pacote, array $manifesto, array $plano, array $estadoAnterior): array
    {
        $pendentes = array_values(array_filter($plano, static fn (array $p): bool => $p['sha256_atual'] !== $p['sha256_novo']));
        $backup = null;
        $alterados = [];

        if ($pendentes !== []) {
            $healthAntes = $this->probeHealth();
            $backup = $this->criarBackup($versao, $manifesto['revisao'], $pendentes);
            $restaurado = false;

            try {
                $this->prepararTemporarios($pendentes);

                $this->comPortao(function () use ($pendentes, $backup, &$alterados, &$restaurado): void {
                    try {
                        foreach ($pendentes as $item) {
                            $this->substituir($item['destino'].self::SUFIXO_TEMPORARIO, $item['destino'], $item['sha256_novo']);
                            $alterados[] = ['caminho' => $item['caminho'], 'sha256_anterior' => $item['sha256_atual']];
                        }
                    } catch (\Throwable $e) {
                        $restaurado = true;
                        $this->restaurarComLog($alterados, $backup);

                        throw $e;
                    }
                });

                $this->verificarPosAplicacao($pendentes, $healthAntes);
            } catch (\Throwable $e) {
                HotfixLog::line('Falha', $manifesto['id'].': '.$e->getMessage().' — revertendo');

                if (! $restaurado) {
                    $this->comPortao(fn () => $this->restaurarComLog($alterados, $backup));
                }

                $this->removerTemporarios($pendentes);

                $definitivo = $e instanceof HotfixRecusadoException && $e->definitivo;
                $this->recusar($sha256Pacote, $e->getMessage(), $definitivo);
                HotfixEstado::registrarHistorico([
                    'evento' => 'falhou',
                    'id' => $manifesto['id'],
                    'versao_base' => $versao,
                    'revisao' => $manifesto['revisao'],
                    'erro' => $e->getMessage(),
                ]);

                return $this->resultado('falhou', 'Hotfix '.$manifesto['id'].' revertido: '.$e->getMessage());
            }
        }

        $anterior = ($estadoAnterior['versao_base'] ?? null) === $versao
            ? array_intersect_key($estadoAnterior, array_flip(['versao_base', 'revisao', 'id', 'descricao', 'sha256_pacote', 'aplicado_em', 'arquivos', 'arquivos_alterados', 'backup']))
            : [];

        HotfixEstado::gravar([
            'versao_base' => $versao,
            'revisao' => $manifesto['revisao'],
            'id' => $manifesto['id'],
            'descricao' => $manifesto['descricao'],
            'sha256_pacote' => $sha256Pacote,
            'aplicado_em' => now()->toIso8601String(),
            'arquivos' => array_map(static fn (array $p): array => ['caminho' => $p['caminho'], 'sha256' => $p['sha256_novo']], $plano),
            'arquivos_alterados' => $alterados,
            'backup' => $backup,
            'anterior' => $anterior !== [] ? $anterior : null,
            'ultima_verificacao_ts' => time(),
        ]);

        HotfixEstado::registrarHistorico([
            'evento' => 'aplicado',
            'id' => $manifesto['id'],
            'versao_base' => $versao,
            'revisao' => $manifesto['revisao'],
            'arquivos_alterados' => array_column($alterados, 'caminho'),
        ]);
        HotfixLog::line('Aplicado', $manifesto['id'].' arquivos='.count($alterados));
        $this->limparBackupsAntigos();

        return $this->resultado('aplicado', 'Hotfix '.$manifesto['id'].' aplicado ('.count($alterados).' arquivo(s)).');
    }

    /**
     * @return array{sha256: string, size: int, assinatura: string}|null null quando não há hotfix publicado para a versão
     */
    private function buscarIntegridade(string $versao): ?array
    {
        $response = LicencaHttpClient::make()
            ->timeout(15)
            ->connectTimeout(5)
            ->withUserAgent('UnitecErpHotfix/1.0')
            ->get(self::urlZip($versao).'.sha256');

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' ao consultar hotfix no GitHub.');
        }

        $sha256 = '';
        $size = 0;
        $assinatura = '';

        foreach (preg_split('/\R/', (string) $response->body()) ?: [] as $linha) {
            $linha = trim($linha);

            if (preg_match('/^size=(\d+)$/i', $linha, $m) === 1) {
                $size = (int) $m[1];
            } elseif (preg_match('/^sig=([A-Za-z0-9+\/=]+)$/', $linha, $m) === 1) {
                $assinatura = $m[1];
            } elseif (preg_match('/^([a-f0-9]{64})\b/i', $linha, $m) === 1) {
                $sha256 = strtolower($m[1]);
            }
        }

        if ($sha256 === '' || $size <= 0 || $size > self::TAMANHO_MAXIMO_ZIP) {
            throw new RuntimeException('Arquivo .sha256 do hotfix inválido (hash/size).');
        }

        return ['sha256' => $sha256, 'size' => $size, 'assinatura' => $assinatura];
    }

    /**
     * @param  array{sha256: string, size: int}  $integridade
     */
    private function baixar(string $versao, array $integridade): string
    {
        $dir = HotfixEstado::path('download');
        File::ensureDirectoryExists($dir);
        $destino = $dir.DIRECTORY_SEPARATOR.self::ASSET_ZIP;
        @unlink($destino);

        $response = LicencaHttpClient::make(['sink' => $destino])
            ->timeout(300)
            ->connectTimeout(10)
            ->withUserAgent('UnitecErpHotfix/1.0')
            ->get(self::urlZip($versao));

        if (! $response->successful()) {
            throw new RuntimeException('HTTP '.$response->status().' ao baixar o hotfix.');
        }

        clearstatcache(true, $destino);
        $tamanho = is_file($destino) ? (int) filesize($destino) : 0;

        if ($tamanho !== $integridade['size']) {
            throw new HotfixRecusadoException('Tamanho do hotfix divergente (esperado '.$integridade['size'].', recebido '.$tamanho.').', false);
        }

        if (hash_file('sha256', $destino) !== $integridade['sha256']) {
            throw new HotfixRecusadoException('SHA256 do hotfix divergente.', false);
        }

        return $destino;
    }

    private function extrair(string $zipPath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new HotfixRecusadoException('ZIP do hotfix inválido.', true);
        }

        $pasta = HotfixEstado::path('tmp'.DIRECTORY_SEPARATOR.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($pasta);

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $nome = str_replace('\\', '/', (string) $zip->getNameIndex($i));

                if ($nome === '' || str_ends_with($nome, '/')) {
                    continue;
                }

                if ($nome !== self::MANIFESTO && ! str_starts_with($nome, self::PASTA_ARQUIVOS.'/')) {
                    throw new HotfixRecusadoException('Entrada inesperada no ZIP: '.$nome, true);
                }

                if (! $this->caminhoSeguro($nome)) {
                    throw new HotfixRecusadoException('Caminho inseguro no ZIP: '.$nome, true);
                }

                $conteudo = $zip->getFromIndex($i);

                if ($conteudo === false) {
                    throw new HotfixRecusadoException('Falha ao ler '.$nome.' do ZIP.', true);
                }

                $alvo = $pasta.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $nome);
                File::ensureDirectoryExists(dirname($alvo));
                file_put_contents($alvo, $conteudo);
            }
        } finally {
            $zip->close();
        }

        return $pasta;
    }

    /**
     * @return array{id: string, revisao: int, descricao: string, arquivos: list<array<string, mixed>>}
     */
    private function lerManifesto(string $pasta, string $versao): array
    {
        $path = $pasta.DIRECTORY_SEPARATOR.self::MANIFESTO;

        try {
            $data = json_decode((string) @file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new HotfixRecusadoException('hotfix.json ausente ou inválido.', true);
        }

        if (! is_array($data) || ($data['tipo'] ?? null) !== self::TIPO_MANIFESTO) {
            throw new HotfixRecusadoException('hotfix.json com tipo inválido.', true);
        }

        if (trim((string) ($data['versao_base'] ?? '')) !== $versao) {
            throw new HotfixRecusadoException('Hotfix para a versão '.($data['versao_base'] ?? '?').', instalada '.$versao.'.', true);
        }

        $revisao = (int) ($data['revisao'] ?? 0);
        $arquivos = $data['arquivos'] ?? null;

        if ($revisao < 1 || ! is_array($arquivos) || $arquivos === []) {
            throw new HotfixRecusadoException('hotfix.json sem revisão ou arquivos.', true);
        }

        $vistos = [];

        foreach ($arquivos as $arquivo) {
            $caminho = is_array($arquivo) ? str_replace('\\', '/', trim((string) ($arquivo['caminho'] ?? ''))) : '';

            if (! $this->caminhoPermitido($caminho) || isset($vistos[$caminho])) {
                throw new HotfixRecusadoException('Arquivo não permitido em hotfix: '.$caminho, true);
            }

            $vistos[$caminho] = true;

            if (! $this->hashValido($arquivo['sha256_novo'] ?? null)
                || (($arquivo['sha256_original'] ?? null) !== null && ! $this->hashValido($arquivo['sha256_original']))) {
                throw new HotfixRecusadoException('Hash inválido no manifesto para '.$caminho, true);
            }

            foreach ((array) ($arquivo['sha256_anteriores'] ?? []) as $anterior) {
                if (! $this->hashValido($anterior)) {
                    throw new HotfixRecusadoException('Hash anterior inválido no manifesto para '.$caminho, true);
                }
            }
        }

        return [
            'id' => trim((string) ($data['id'] ?? '')) ?: $versao.'-hf'.$revisao,
            'revisao' => $revisao,
            'descricao' => trim((string) ($data['descricao'] ?? '')),
            'arquivos' => array_values($arquivos),
        ];
    }

    /**
     * Confere pacote e instalação antes de tocar em qualquer arquivo.
     *
     * @param  array{id: string, revisao: int, descricao: string, arquivos: list<array<string, mixed>>}  $manifesto
     * @return list<array{caminho: string, origem: string, destino: string, sha256_novo: string, sha256_atual: ?string}>
     */
    private function planejar(string $pasta, array $manifesto): array
    {
        $plano = [];

        foreach ($manifesto['arquivos'] as $arquivo) {
            $caminho = str_replace('\\', '/', trim((string) $arquivo['caminho']));
            $novo = strtolower((string) $arquivo['sha256_novo']);
            $original = isset($arquivo['sha256_original']) ? strtolower((string) $arquivo['sha256_original']) : null;
            $aceitos = array_map('strtolower', array_filter([$original, ...((array) ($arquivo['sha256_anteriores'] ?? []))]));

            $origem = $pasta.DIRECTORY_SEPARATOR.self::PASTA_ARQUIVOS.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $caminho);

            if (! is_file($origem) || hash_file('sha256', $origem) !== $novo) {
                throw new HotfixRecusadoException('Arquivo do pacote ausente ou com hash divergente: '.$caminho, true);
            }

            $this->validarSintaxe($caminho, $origem);

            $destino = base_path(str_replace('/', DIRECTORY_SEPARATOR, $caminho));
            $atual = is_file($destino) ? hash_file('sha256', $destino) : null;

            $compativel = $atual === $novo
                || ($atual === null && $original === null)
                || ($atual !== null && in_array($atual, $aceitos, true));

            if (! $compativel) {
                throw new HotfixRecusadoException('Instalação incompatível: '.$caminho.' difere da versão base esperada.', true);
            }

            $plano[] = [
                'caminho' => $caminho,
                'origem' => $origem,
                'destino' => $destino,
                'sha256_novo' => $novo,
                'sha256_atual' => $atual,
            ];
        }

        return $plano;
    }

    private function validarSintaxe(string $caminho, string $arquivo): void
    {
        $conteudo = (string) file_get_contents($arquivo);

        try {
            if (str_ends_with($caminho, '.json')) {
                json_decode($conteudo, false, 512, JSON_THROW_ON_ERROR);

                return;
            }

            if (str_ends_with($caminho, '.blade.php')) {
                $conteudo = app('blade.compiler')->compileString($conteudo);
            }

            token_get_all($conteudo, TOKEN_PARSE);
        } catch (\Throwable $e) {
            throw new HotfixRecusadoException('Erro de sintaxe em '.$caminho.': '.$e->getMessage(), true);
        }
    }

    /**
     * @param  list<array{caminho: string, origem: string, destino: string, sha256_novo: string, sha256_atual: ?string}>  $pendentes
     */
    private function criarBackup(string $versao, int $revisao, array $pendentes): string
    {
        $dir = HotfixEstado::path('backups'.DIRECTORY_SEPARATOR.$versao.'-r'.$revisao.'-'.date('Ymd-His'));
        File::ensureDirectoryExists($dir);
        $lista = [];

        foreach ($pendentes as $item) {
            if ($item['sha256_atual'] !== null) {
                $copia = $dir.DIRECTORY_SEPARATOR.'arquivos'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $item['caminho']);
                File::ensureDirectoryExists(dirname($copia));

                if (! @copy($item['destino'], $copia) || hash_file('sha256', $copia) !== $item['sha256_atual']) {
                    throw new RuntimeException('Falha ao criar backup de '.$item['caminho']);
                }
            }

            $lista[] = ['caminho' => $item['caminho'], 'sha256_anterior' => $item['sha256_atual']];
        }

        file_put_contents($dir.DIRECTORY_SEPARATOR.'backup.json', json_encode([
            'versao_base' => $versao,
            'revisao' => $revisao,
            'criado_em' => now()->toIso8601String(),
            'arquivos' => $lista,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $dir;
    }

    /**
     * Copia e confere todos os arquivos novos ao lado do destino antes de fechar o portão,
     * para que com o portão fechado só aconteçam renomeações (milissegundos).
     *
     * @param  list<array{caminho: string, origem: string, destino: string, sha256_novo: string, sha256_atual: ?string}>  $pendentes
     */
    private function prepararTemporarios(array $pendentes): void
    {
        foreach ($pendentes as $item) {
            File::ensureDirectoryExists(dirname($item['destino']));
            $tmp = $item['destino'].self::SUFIXO_TEMPORARIO;

            if (! @copy($item['origem'], $tmp) || hash_file('sha256', $tmp) !== $item['sha256_novo']) {
                @unlink($tmp);

                throw new RuntimeException('Falha ao preparar '.$item['caminho']);
            }
        }
    }

    /**
     * @param  list<array{destino: string}>  $pendentes
     */
    private function removerTemporarios(array $pendentes): void
    {
        foreach ($pendentes as $item) {
            @unlink($item['destino'].self::SUFIXO_TEMPORARIO);
        }
    }

    private function comPortao(callable $acao): void
    {
        $aberto = HotfixPortao::abrir();

        try {
            $acao();
        } finally {
            if ($aberto) {
                HotfixPortao::fechar();
            }
        }
    }

    /**
     * @param  list<array{caminho: string, sha256_anterior: ?string}>  $alterados
     */
    private function restaurarComLog(array $alterados, string $backup): void
    {
        try {
            $this->restaurar($alterados, $backup);
            HotfixLog::line('Rollback', 'ok');
        } catch (\Throwable $rollback) {
            HotfixLog::line('Rollback', 'FALHOU: '.$rollback->getMessage().' backup='.$backup);
        }
    }

    private function copiarAtomico(string $origem, string $destino, string $sha256): void
    {
        File::ensureDirectoryExists(dirname($destino));
        $tmp = $destino.self::SUFIXO_TEMPORARIO;

        if (! @copy($origem, $tmp)) {
            throw new RuntimeException('Falha ao copiar '.$origem);
        }

        $this->substituir($tmp, $destino, $sha256);
    }

    private function substituir(string $tmp, string $destino, string $sha256): void
    {
        if (! @rename($tmp, $destino)) {
            $ok = @copy($tmp, $destino);
            @unlink($tmp);

            if (! $ok) {
                throw new RuntimeException('Falha ao gravar '.$destino);
            }
        }

        clearstatcache(true, $destino);

        if (hash_file('sha256', $destino) !== $sha256) {
            throw new RuntimeException('Hash divergente após gravar '.$destino);
        }
    }

    /**
     * @param  list<array{caminho: string, sha256_anterior: ?string}>  $alterados
     */
    private function restaurar(array $alterados, string $backup): void
    {
        $erros = [];

        foreach (array_reverse($alterados) as $item) {
            $destino = base_path(str_replace('/', DIRECTORY_SEPARATOR, (string) $item['caminho']));

            try {
                if ($item['sha256_anterior'] === null) {
                    if (is_file($destino) && ! @unlink($destino)) {
                        throw new RuntimeException('não foi possível remover');
                    }

                    continue;
                }

                $copia = $backup.DIRECTORY_SEPARATOR.'arquivos'.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, (string) $item['caminho']);
                $this->copiarAtomico($copia, $destino, (string) $item['sha256_anterior']);
            } catch (\Throwable $e) {
                $erros[] = $item['caminho'].': '.$e->getMessage();
            }
        }

        if ($erros !== []) {
            throw new RuntimeException('Rollback incompleto: '.implode('; ', $erros));
        }
    }

    /**
     * @param  list<array{caminho: string, origem: string, destino: string, sha256_novo: string, sha256_atual: ?string}>  $pendentes
     */
    private function verificarPosAplicacao(array $pendentes, ?bool $healthAntes): void
    {
        $classes = [];

        foreach ($pendentes as $item) {
            if (str_starts_with($item['caminho'], 'app/') && str_ends_with($item['caminho'], '.php')) {
                $classes[] = 'App\\'.str_replace('/', '\\', substr($item['caminho'], 4, -4));
            }
        }

        if ($classes !== []) {
            $lista = HotfixEstado::path('tmp'.DIRECTORY_SEPARATOR.'verificar-'.bin2hex(random_bytes(4)).'.json');
            File::ensureDirectoryExists(dirname($lista));
            file_put_contents($lista, json_encode($classes));

            $php = ErpUpdateProcessLauncher::resolvePhpBinary(base_path());
            $processo = Process::path(base_path())
                ->timeout(120)
                ->run([$php, '-d', 'opcache.enable_cli=0', base_path('artisan'), 'unitec:hotfix', '--verificar='.$lista]);
            @unlink($lista);

            if (! $processo->successful()) {
                $saida = trim($processo->output().' '.$processo->errorOutput());

                throw new HotfixRecusadoException('Classes alteradas não carregam: '.mb_substr($saida, 0, 400), true);
            }
        }

        if ($healthAntes === true && $this->probeHealth(tentativas: 6) !== true) {
            throw new RuntimeException('ERP deixou de responder /api/health após o hotfix.');
        }
    }

    /**
     * true = saudável, false = respondeu com erro, null = servidor não respondeu (não dá para avaliar).
     */
    private function probeHealth(int $tentativas = 1): ?bool
    {
        $resultado = null;

        for ($i = 0; $i < $tentativas; $i++) {
            if ($i > 0) {
                usleep(500_000);
            }

            foreach ($this->urlsHealth() as $url) {
                $contexto = stream_context_create(['http' => [
                    'method' => 'GET',
                    'timeout' => 3,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nConnection: close\r\n",
                ]]);

                $corpo = @file_get_contents($url, false, $contexto);
                $status = null;
                $cabecalhos = function_exists('http_get_last_response_headers')
                    ? (http_get_last_response_headers() ?? [])
                    : ($http_response_header ?? []);

                foreach ($cabecalhos as $linha) {
                    if (preg_match('/^HTTP\/\S+\s+(\d+)/', $linha, $m) === 1) {
                        $status = (int) $m[1];
                    }
                }

                if ($status === null) {
                    continue;
                }

                $json = is_string($corpo) ? json_decode($corpo, true) : null;
                $resultado = $status >= 200 && $status < 300 && is_array($json) && strcasecmp((string) ($json['status'] ?? ''), 'ok') === 0;

                if ($resultado) {
                    return true;
                }

                break;
            }
        }

        return $resultado;
    }

    /**
     * @return list<string>
     */
    private function urlsHealth(): array
    {
        $portas = [];
        $url = parse_url((string) config('app.url', ''));

        if (is_array($url) && in_array(strtolower((string) ($url['host'] ?? '')), ['127.0.0.1', 'localhost'], true) && isset($url['port'])) {
            $portas[] = (int) $url['port'];
        }

        $portas[] = 8765;
        $portas[] = 8000;

        return array_map(static fn (int $p): string => 'http://127.0.0.1:'.$p.'/api/health', array_values(array_unique($portas)));
    }

    private function recusar(string $sha256, string $motivo, bool $definitivo): void
    {
        HotfixEstado::atualizar(['recusado' => [
            'sha256' => $sha256,
            'motivo' => $motivo,
            'definitivo' => $definitivo,
            'em' => now()->toIso8601String(),
        ]]);
        HotfixLog::line('Recusado', ($definitivo ? '[definitivo] ' : '[tenta de novo em 6h] ').$motivo);
    }

    /**
     * O hotfix troca arquivos da instalação inteira, então só é aplicado se TODAS as empresas ativas
     * estiverem com licença válida e modo "hotfix", confirmado agora no portal (cache/grace não valem).
     *
     * @return string|null motivo da recusa, ou null quando autorizado
     */
    private function autorizarInstalacao(): ?string
    {
        $cnpjs = $this->cnpjsEmpresasAtivas();

        if ($cnpjs === []) {
            return 'Nenhuma empresa ativa com CNPJ para consultar a permissão.';
        }

        $licencas = app(LicencaRemotaService::class);
        $modos = [];

        foreach ($cnpjs as $cnpj) {
            $snapshot = $licencas->checkCnpj($cnpj, forceRefresh: true);

            if ($snapshot->fromCache || $snapshot->status === LicencaSnapshot::STATUS_INDISPONIVEL) {
                HotfixEstado::lembrarPermissoes($modos);

                return 'Portal indisponível para o CNPJ '.$cnpj.'; hotfix adiado.';
            }

            $modos[$cnpj] = $snapshot->modoAtualizacao;

            if (! $snapshot->isAllowed() || ! $snapshot->permiteHotfix()) {
                HotfixEstado::lembrarPermissoes($modos);

                return 'Hotfix não liberado para o CNPJ '.$cnpj.' (modo='.($snapshot->modoAtualizacao ?? 'ausente').'); todas as empresas precisam estar em "hotfix".';
            }
        }

        HotfixEstado::lembrarPermissoes($modos, substituir: true);

        return null;
    }

    /**
     * @return list<string>
     */
    private function cnpjsEmpresasAtivas(): array
    {
        $empresa = new Empresa;
        $query = Empresa::query()->withoutGlobalScopes()->whereNotNull('cnpj')->where('cnpj', '!=', '');

        if (Schema::hasColumn($empresa->getTable(), 'ativo')) {
            $query->where(fn ($q) => $q->whereNull('ativo')->orWhere('ativo', true));
        }

        $cnpjs = [];

        foreach ($query->pluck('cnpj') as $cnpj) {
            $digitos = preg_replace('/\D/', '', (string) $cnpj) ?? '';

            if (strlen($digitos) === 14) {
                $cnpjs[] = $digitos;
            }
        }

        return array_values(array_unique($cnpjs));
    }

    private function atualizacaoOficialAtiva(): bool
    {
        $progresso = AtualizacaoApplyService::readProgress(base_path());

        return in_array((string) ($progresso['state'] ?? ''), self::ESTADOS_ATUALIZACAO_OFICIAL_ATIVA, true);
    }

    private function caminhoSeguro(string $caminho): bool
    {
        return $caminho !== ''
            && ! str_starts_with($caminho, '/')
            && preg_match('/^[A-Za-z]:/', $caminho) !== 1
            && ! in_array('..', explode('/', $caminho), true)
            && ! str_contains($caminho, "\0");
    }

    private function caminhoPermitido(string $caminho): bool
    {
        if (! $this->caminhoSeguro($caminho)) {
            return false;
        }

        foreach (self::CAMINHOS_BLOQUEADOS as $bloqueado) {
            if (str_starts_with($caminho, $bloqueado)) {
                return false;
            }
        }

        $permitido = false;

        foreach (self::PREFIXOS_PERMITIDOS as $prefixo) {
            if (str_starts_with($caminho, $prefixo)) {
                $permitido = true;
                break;
            }
        }

        return $permitido && in_array(strtolower(pathinfo($caminho, PATHINFO_EXTENSION)), self::EXTENSOES_PERMITIDAS, true);
    }

    private function hashValido(mixed $hash): bool
    {
        return is_string($hash) && preg_match('/^[a-f0-9]{64}$/i', $hash) === 1;
    }

    private function limparTemporarios(): void
    {
        try {
            File::deleteDirectory(HotfixEstado::path('tmp'));
            File::deleteDirectory(HotfixEstado::path('download'));
        } catch (\Throwable) {
        }
    }

    private function limparBackupsAntigos(): void
    {
        try {
            $dirs = glob(HotfixEstado::path('backups').DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [];
            usort($dirs, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

            foreach (array_slice($dirs, self::BACKUPS_MANTIDOS) as $antigo) {
                File::deleteDirectory($antigo);
            }
        } catch (\Throwable) {
        }
    }

    /**
     * @return array{resultado: string, mensagem: string}
     */
    private function resultado(string $resultado, string $mensagem): array
    {
        return ['resultado' => $resultado, 'mensagem' => $mensagem];
    }
}
