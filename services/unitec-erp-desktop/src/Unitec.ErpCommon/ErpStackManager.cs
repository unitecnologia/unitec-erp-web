using System.Diagnostics;
using System.Net.Sockets;

namespace Unitec.ErpCommon;

public sealed class StackStatus
{
    public bool MariaDbRunning { get; init; }
    public bool PhpRunning { get; init; }
    public HealthResult Health { get; init; } = new();
}

public static class ErpStackManager
{
    public static StackStatus GetStatus(string appPath)
    {
        var maria = IsMariaDbListening();
        var health = HealthClient.ProbeAsync().GetAwaiter().GetResult();
        return new StackStatus
        {
            MariaDbRunning = maria,
            PhpRunning = health.PortOpen,
            Health = health,
        };
    }

    public static bool IsMariaDbListening(int port = 3306)
    {
        try
        {
            using var client = new TcpClient();
            var task = client.ConnectAsync("127.0.0.1", port);
            return task.Wait(800) && client.Connected;
        }
        catch
        {
            return false;
        }
    }

    public static void EnsureMariaDb(string appPath)
    {
        if (IsMariaDbListening())
        {
            return;
        }

        var mysqld = ErpPaths.ResolveMysqldExe(appPath);
        if (string.IsNullOrWhiteSpace(mysqld))
        {
            throw new InvalidOperationException("MariaDB embutido nao encontrado em tools\\mysql.");
        }

        var mysqlRoot = Path.GetFullPath(Path.Combine(Path.GetDirectoryName(mysqld)!, ".."));
        var dataDir = Path.Combine(mysqlRoot, "data");
        Directory.CreateDirectory(dataDir);

        var args = $"--datadir=\"{dataDir}\" --console";
        var ini = Path.Combine(mysqlRoot, "my.ini");
        if (File.Exists(ini))
        {
            args = $"--defaults-file=\"{ini}\" --console";
        }

        DesktopLog.Write(appPath, $"Iniciando MariaDB: {mysqld}");
        ProcessHelper.StartHidden(mysqld, args, mysqlRoot);

        for (var i = 0; i < 40; i++)
        {
            if (IsMariaDbListening())
            {
                return;
            }

            Thread.Sleep(500);
        }

        throw new InvalidOperationException("MariaDB nao iniciou a tempo (porta 3306).");
    }

    public static bool IsAppPhpRunning(string appPath)
    {
        var pidFile = Path.Combine(appPath, ".unitec-serve.pid");
        if (File.Exists(pidFile))
        {
            try
            {
                if (int.TryParse(File.ReadAllText(pidFile).Trim(), out var pid))
                {
                    using var p = Process.GetProcessById(pid);
                    if (!p.HasExited)
                    {
                        return true;
                    }
                }
            }
            catch
            {
                // PID morto ou inacessivel — cai no fallback abaixo.
            }
        }

        var frankenDir = Path.Combine(appPath, "tools", "frankenphp");
        if (Directory.Exists(frankenDir))
        {
            string frankenFull;
            try
            {
                frankenFull = Path.GetFullPath(frankenDir);
            }
            catch
            {
                frankenFull = string.Empty;
            }

            if (!string.IsNullOrWhiteSpace(frankenFull))
            {
                foreach (var p in Process.GetProcessesByName("frankenphp"))
                {
                    try
                    {
                        string? modulePath = null;
                        try { modulePath = p.MainModule?.FileName; } catch { }
                        if (string.IsNullOrWhiteSpace(modulePath))
                        {
                            continue;
                        }

                        var moduleDir = Path.GetDirectoryName(Path.GetFullPath(modulePath));
                        if (moduleDir is not null
                            && moduleDir.StartsWith(frankenFull, StringComparison.OrdinalIgnoreCase)
                            && !p.HasExited)
                        {
                            return true;
                        }
                    }
                    catch
                    {
                        // ignore
                    }
                }
            }
        }

        return false;
    }

    /// <summary>
    /// Runtime HTTP inválido = listener na porta do ERP sem FrankenPHP desta instalação.
    /// </summary>
    public static bool IsInvalidPhpBuiltInServerOnPort(string appPath, int port = ErpPaths.Port)
    {
        if (!IsTcpPortOpen(port))
        {
            return false;
        }

        // FrankenPHP desta instalação vivo → runtime válido.
        if (IsFrankenPhpRunning(appPath))
        {
            return false;
        }

        // Porta ocupada sem FrankenPHP = php -S / outro servidor — inválido.
        return true;
    }

    private static bool IsFrankenPhpRunning(string appPath)
    {
        var frankenDir = Path.Combine(appPath, "tools", "frankenphp");
        if (!Directory.Exists(frankenDir))
        {
            return false;
        }

        string frankenFull;
        try
        {
            frankenFull = Path.GetFullPath(frankenDir);
        }
        catch
        {
            return false;
        }

        foreach (var p in Process.GetProcessesByName("frankenphp"))
        {
            try
            {
                string? modulePath = null;
                try { modulePath = p.MainModule?.FileName; } catch { }
                if (string.IsNullOrWhiteSpace(modulePath))
                {
                    continue;
                }

                var moduleDir = Path.GetDirectoryName(Path.GetFullPath(modulePath));
                if (moduleDir is not null
                    && moduleDir.StartsWith(frankenFull, StringComparison.OrdinalIgnoreCase)
                    && !p.HasExited)
                {
                    return true;
                }
            }
            catch
            {
                // ignore
            }
        }

        return false;
    }

    public static Process EnsurePhpServer(string appPath)
    {
        // Serviço (Session 0) e launcher (sessão do usuário) precisam compartilhar
        // a mesma trava. Mutex Global permite que os dois matem/subam o HTTP juntos.
        using var gate = new Mutex(false, @"Global\UnitecErpEnsurePhp");
        var acquired = false;
        try
        {
            try
            {
                acquired = gate.WaitOne(TimeSpan.FromSeconds(60));
            }
            catch (AbandonedMutexException)
            {
                acquired = true;
            }

            if (!acquired)
            {
                throw new InvalidOperationException(
                    "Timeout aguardando EnsurePhpServer (outro processo esta iniciando o HTTP).");
            }

            return EnsurePhpServerCore(appPath);
        }
        finally
        {
            if (acquired)
            {
                try { gate.ReleaseMutex(); } catch { /* ignore */ }
            }
        }
    }

    private static Process EnsurePhpServerCore(string appPath)
    {
        if (IsInvalidPhpBuiltInServerOnPort(appPath))
        {
            DesktopLog.Write(appPath,
                "RUNTIME INVÁLIDO: porta ocupada sem FrankenPHP ("
                + ErpPaths.Port + ") — encerrando e exigindo FrankenPHP.");
            ProcessHelper.KillPidFile(appPath);
            ProcessHelper.KillAppHttpServers(appPath);
            WaitPortFree(ErpPaths.Port, 3000);
        }

        var health = HealthClient.ProbeAsync().GetAwaiter().GetResult();
        if (health.Ok && IsFrankenPhpRunning(appPath))
        {
            DesktopLog.Write(appPath,
                $"Runtime HTTP: FrankenPHP | Porta: {ErpPaths.Port} (já ativo, kind={health.Kind})");
            return Process.GetCurrentProcess();
        }

        if (health.PortOpen && IsFrankenPhpRunning(appPath))
        {
            DesktopLog.Write(appPath,
                $"FrankenPHP já responde na porta {ErpPaths.Port} (kind={health.Kind}) — mantendo processo.");
            return Process.GetCurrentProcess();
        }

        var franken = ErpPaths.ResolveFrankenPhpExe(appPath);
        if (string.IsNullOrWhiteSpace(franken))
        {
            throw new InvalidOperationException(
                "FrankenPHP não iniciou: binário ausente em tools\\frankenphp\\frankenphp.exe. "
                + "O ERP exige FrankenPHP (sem fallback para php -S / artisan serve).");
        }

        if (!File.Exists(Path.Combine(appPath, "artisan")))
        {
            throw new InvalidOperationException("artisan ausente em " + appPath);
        }

        if (!File.Exists(Path.Combine(appPath, "vendor", "autoload.php")))
        {
            throw new InvalidOperationException("vendor/autoload.php ausente — instalacao incompleta.");
        }

        Directory.CreateDirectory(Path.Combine(appPath, "storage", "logs"));
        Directory.CreateDirectory(Path.Combine(appPath, "storage", "app"));
        Directory.CreateDirectory(Path.Combine(appPath, "bootstrap", "cache"));

        var publicIndex = Path.Combine(appPath, "public", "index.php");
        if (!File.Exists(publicIndex))
        {
            throw new InvalidOperationException("public\\index.php ausente — instalacao incompleta.");
        }

        EnsureFrankenPhpIni(appPath);

        Exception? lastError = null;
        for (var attempt = 1; attempt <= 3; attempt++)
        {
            try
            {
                return StartFrankenPhpServerAttempt(appPath, franken, attempt);
            }
            catch (Exception ex)
            {
                lastError = ex;
                DesktopLog.Write(appPath, $"EnsurePhpServer (FrankenPHP) tentativa {attempt}/3 falhou: {ex.Message}");
                ProcessHelper.KillPidFile(appPath);
                ProcessHelper.KillAppHttpServers(appPath);
                WaitPortFree(ErpPaths.Port, 2000);
                Thread.Sleep(500);
            }
        }

        throw lastError ?? new InvalidOperationException(
            "FrankenPHP não iniciou. Veja storage\\logs\\frankenphp-start.log e unitec-erp-desktop.log");
    }

    private static void EnsureFrankenPhpIni(string appPath)
    {
        var frankenDir = Path.Combine(appPath, "tools", "frankenphp");
        if (!Directory.Exists(frankenDir))
        {
            return;
        }

        var opcacheDir = Path.Combine(frankenDir, "opcache");
        Directory.CreateDirectory(opcacheDir);
        var opcachePosix = opcacheDir.Replace('\\', '/');
        var targetIni = Path.Combine(frankenDir, "php.ini");

        // Nao copiar tools\php\php.ini (CLI 8.4) — FrankenPHP embute PHP 8.5 e no Windows
        // o OPcache exige file_cache por causa do ASLR.
        var ini =
            "; Unitec ERP — php.ini do FrankenPHP (ASLR-safe).\r\n"
            + "extension_dir = \"ext\"\r\n"
            + "\r\n"
            + "zend_extension=opcache\r\n"
            + "opcache.enable=1\r\n"
            + "opcache.enable_cli=1\r\n"
            + "opcache.memory_consumption=256\r\n"
            + "opcache.interned_strings_buffer=32\r\n"
            + "opcache.max_accelerated_files=20000\r\n"
            + "opcache.validate_timestamps=1\r\n"
            + "opcache.revalidate_freq=0\r\n"
            + $"opcache.file_cache={opcachePosix}\r\n"
            + "opcache.file_cache_fallback=1\r\n"
            + "opcache.jit=0\r\n"
            + "\r\n"
            + "extension=curl\r\n"
            + "extension=fileinfo\r\n"
            + "extension=gd\r\n"
            + "extension=intl\r\n"
            + "extension=mbstring\r\n"
            + "extension=mysqli\r\n"
            + "extension=openssl\r\n"
            + "extension=pdo_mysql\r\n"
            + "extension=pdo_sqlite\r\n"
            + "extension=sqlite3\r\n"
            + "extension=zip\r\n"
            + "\r\n"
            + "memory_limit=512M\r\n"
            + "max_execution_time=300\r\n"
            + "upload_max_filesize=64M\r\n"
            + "post_max_size=64M\r\n"
            + "date.timezone=America/Sao_Paulo\r\n";

        File.WriteAllText(targetIni, ini);
    }

    private static string PrepareFrankenPhpCaddyfile(string appPath)
    {
        var target = ErpPaths.FrankenPhpCaddyfilePath(appPath);
        var template = Path.Combine(appPath, "tools", "frankenphp", "Caddyfile.template");
        if (File.Exists(template))
        {
            File.Copy(template, target, overwrite: true);
            return target;
        }

        var fallback = """
{
	admin off
	frankenphp {
		num_threads {$FRANKENPHP_NUM_THREADS:8}
	}
	auto_https off
}

:{$UNITEC_PORT:8765} {
	bind {$UNITEC_BIND:0.0.0.0}
	root * {$UNITEC_PUBLIC:public/}
	encode gzip
	php_server {
		env SERVER_NAME {$UNITEC_HTTP_HOST:127.0.0.1:8765}
		env HTTP_HOST {$UNITEC_HTTP_HOST:127.0.0.1:8765}
	}
	file_server
}
""";
        File.WriteAllText(target, fallback);
        return target;
    }

    private static int ResolveFrankenPhpThreads(string appPath)
    {
        try
        {
            var envPath = Path.Combine(appPath, ".env");
            if (File.Exists(envPath))
            {
                foreach (var line in File.ReadLines(envPath))
                {
                    var t = line.Trim();
                    if (t.StartsWith("FRANKENPHP_NUM_THREADS=", StringComparison.OrdinalIgnoreCase)
                        && int.TryParse(t.Split('=', 2)[1].Trim().Trim('"'), out var n)
                        && n >= 2)
                    {
                        return n;
                    }
                }
            }
        }
        catch
        {
            // ignore
        }

        return 8;
    }

    private static Process StartFrankenPhpServerAttempt(
        string appPath,
        string frankenExe,
        int attempt)
    {
        var health = HealthClient.ProbeAsync().GetAwaiter().GetResult();
        if (health.Ok && IsFrankenPhpRunning(appPath))
        {
            return Process.GetCurrentProcess();
        }

        DesktopLog.Write(appPath,
            $"Limpando HTTP anterior antes da tentativa FrankenPHP {attempt} (port={health.PortOpen})");
        ProcessHelper.KillPidFile(appPath);
        ProcessHelper.KillAppHttpServers(appPath);
        WaitPortFree(ErpPaths.Port, 2500);
        Thread.Sleep(400);

        var caddy = PrepareFrankenPhpCaddyfile(appPath);
        var threads = ResolveFrankenPhpThreads(appPath);
        var frankenIni = Path.Combine(Path.GetDirectoryName(frankenExe)!, "php.ini");
        var startLog = Path.Combine(appPath, "storage", "logs", "frankenphp-start.log");
        var httpHost = $"127.0.0.1:{ErpPaths.Port}";

        var psi = new ProcessStartInfo
        {
            FileName = frankenExe,
            Arguments = $"run --config \"{caddy}\"",
            WorkingDirectory = appPath,
            UseShellExecute = false,
            CreateNoWindow = true,
            WindowStyle = ProcessWindowStyle.Hidden,
            RedirectStandardOutput = false,
            RedirectStandardError = false,
        };
        psi.Environment["UNITEC_BIND"] = "0.0.0.0";
        psi.Environment["UNITEC_PORT"] = ErpPaths.Port.ToString();
        psi.Environment["UNITEC_PUBLIC"] = "public/";
        psi.Environment["UNITEC_HTTP_HOST"] = httpHost;
        psi.Environment["FRANKENPHP_NUM_THREADS"] = threads.ToString();
        if (File.Exists(frankenIni))
        {
            psi.Environment["PHPRC"] = frankenIni;
        }

        DesktopLog.Write(appPath,
            $"Iniciando FrankenPHP (tentativa {attempt}): threads={threads} porta={ErpPaths.Port}");
        DesktopLog.Write(appPath, $"Runtime HTTP: FrankenPHP | Porta: {ErpPaths.Port}");

        var proc = Process.Start(psi)
            ?? throw new InvalidOperationException("Falha ao iniciar FrankenPHP.");

        ProcessHelper.WritePidFile(appPath, proc.Id);
        try
        {
            File.WriteAllText(ErpPaths.RuntimeMarkerPath(appPath), "frankenphp");
        }
        catch
        {
            // ignore
        }

        for (var i = 0; i < 80; i++)
        {
            if (proc.HasExited)
            {
                AppendFrankenStartLog(startLog, frankenExe, caddy, $"exit={proc.ExitCode}");
                throw new InvalidOperationException(
                    $"FrankenPHP não iniciou (encerrou com exit {proc.ExitCode}). "
                    + "Veja storage\\logs\\frankenphp-start.log");
            }

            var probe = HealthClient.ProbeAsync().GetAwaiter().GetResult();
            if ((probe.Ok || probe.HttpResponded || probe.PortOpen) && !proc.HasExited)
            {
                DesktopLog.Write(appPath,
                    $"FrankenPHP no ar PID={proc.Id} (ok={probe.Ok} http={probe.HttpResponded} port={probe.PortOpen})");
                DesktopLog.Write(appPath, $"Runtime HTTP: FrankenPHP | Porta: {ErpPaths.Port}");
                return proc;
            }

            Thread.Sleep(250);
        }

        if (!proc.HasExited)
        {
            DesktopLog.Write(appPath,
                "Timeout no probe — mantendo FrankenPHP vivo (PID " + proc.Id + ").");
            return proc;
        }

        AppendFrankenStartLog(startLog, frankenExe, caddy, $"exit={proc.ExitCode}");
        throw new InvalidOperationException(
            "FrankenPHP não iniciou. Veja storage\\logs\\frankenphp-start.log");
    }

    private static void AppendFrankenStartLog(string startLog, string exe, string caddy, string note)
    {
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(startLog)!);
            File.AppendAllText(startLog,
                $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] {note}{Environment.NewLine}"
                + $"cmd: {exe} run --config {caddy}{Environment.NewLine}");
        }
        catch
        {
            // ignore
        }
    }

    private static void WaitPortFree(int port, int timeoutMs)
    {
        var deadline = Environment.TickCount64 + timeoutMs;
        while (Environment.TickCount64 < deadline)
        {
            if (!IsTcpPortOpen(port))
            {
                return;
            }

            Thread.Sleep(100);
        }
    }

    private static bool IsTcpPortOpen(int port)
    {
        try
        {
            using var client = new TcpClient();
            var task = client.ConnectAsync("127.0.0.1", port);
            return task.Wait(200) && client.Connected;
        }
        catch
        {
            return false;
        }
    }

    /// <summary>
    /// Encerra o PHP desta instalacao e aguarda a porta do ERP ser liberada.
    /// Retorna true quando a porta ficou livre dentro do prazo.
    /// </summary>
    public static bool StopPhpServer(string appPath, int waitPortFreeMs = 5000)
    {
        ProcessHelper.KillPidFile(appPath);
        ProcessHelper.KillAppHttpServers(appPath);

        var deadline = Environment.TickCount64 + Math.Max(0, waitPortFreeMs);
        while (Environment.TickCount64 < deadline)
        {
            if (!IsTcpPortOpen(ErpPaths.Port))
            {
                return true;
            }

            Thread.Sleep(100);
        }

        return !IsTcpPortOpen(ErpPaths.Port);
    }

    public static async Task ClearAndRebuildCachesAsync(string appPath, CancellationToken cancellationToken = default)
    {
        var php = ErpPaths.ResolvePhpExe(appPath);
        var cacheDir = Path.Combine(appPath, "bootstrap", "cache");
        if (Directory.Exists(cacheDir))
        {
            foreach (var file in Directory.GetFiles(cacheDir, "*.php"))
            {
                try { File.Delete(file); } catch { }
            }
        }

        // Nunca executar optimize:clear na abertura normal — so no atualizador.
        ProcessHelper.RunHidden(php, "artisan optimize:clear", appPath, 180_000);
        cancellationToken.ThrowIfCancellationRequested();
        ProcessHelper.RunHidden(php, "artisan config:cache", appPath, 180_000);
        ProcessHelper.RunHidden(php, "artisan route:cache", appPath, 180_000);
        ProcessHelper.RunHidden(php, "artisan view:cache", appPath, 180_000);

        // Garantia: nenhum cache deve apontar para o path de desenvolvimento.
        foreach (var file in Directory.GetFiles(cacheDir, "*.php"))
        {
            var text = await File.ReadAllTextAsync(file, cancellationToken).ConfigureAwait(false);
            if (text.Contains(@"C:\Projetos\unitec-erp-web", StringComparison.OrdinalIgnoreCase)
                || text.Contains(@"C:/Projetos/unitec-erp-web", StringComparison.OrdinalIgnoreCase))
            {
                throw new InvalidOperationException(
                    $"Cache contaminado com caminho de desenvolvimento em {Path.GetFileName(file)}.");
            }
        }
    }
}
