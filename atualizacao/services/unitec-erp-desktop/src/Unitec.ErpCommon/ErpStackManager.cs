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

        // Fallback: algum php.exe desta instalacao (tools\php) ainda vivo.
        var embeddedPhpDir = Path.Combine(appPath, "tools", "php");
        if (!Directory.Exists(embeddedPhpDir))
        {
            return false;
        }

        string embeddedFull;
        try
        {
            embeddedFull = Path.GetFullPath(embeddedPhpDir);
        }
        catch
        {
            return false;
        }

        foreach (var p in Process.GetProcessesByName("php"))
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
                    && moduleDir.StartsWith(embeddedFull, StringComparison.OrdinalIgnoreCase)
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
        using var gate = new Mutex(false, @"Local\UnitecErpEnsurePhp");
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
                    "Timeout aguardando EnsurePhpServer (outro processo esta iniciando o PHP).");
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
        var health = HealthClient.ProbeAsync().GetAwaiter().GetResult();
        if (health.Ok)
        {
            return Process.GetCurrentProcess();
        }

        // Porta aberta e app saudavel parcialmente: nao reinicia se processo ainda vivo.
        if (health.PortOpen && IsAppPhpRunning(appPath))
        {
            DesktopLog.Write(appPath,
                $"PHP ja responde na porta {ErpPaths.Port} (kind={health.Kind}) — mantendo processo.");
            return Process.GetCurrentProcess();
        }

        var php = ErpPaths.ResolvePhpExe(appPath);
        if (!File.Exists(php) && php == "php")
        {
            throw new InvalidOperationException("PHP embutido nao encontrado em tools\\php.");
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
        Directory.CreateDirectory(Path.Combine(appPath, "bootstrap", "cache"));

        var serverPhp = Path.Combine(
            appPath, "vendor", "laravel", "framework", "src", "Illuminate", "Foundation", "resources", "server.php");
        if (!File.Exists(serverPhp))
        {
            throw new InvalidOperationException("server.php do Laravel ausente em vendor\\laravel\\framework\\...");
        }

        var publicDir = Path.Combine(appPath, "public");
        var publicIndex = Path.Combine(publicDir, "index.php");
        if (!File.Exists(publicIndex))
        {
            throw new InvalidOperationException("public\\index.php ausente — instalacao incompleta.");
        }

        Exception? lastError = null;
        for (var attempt = 1; attempt <= 3; attempt++)
        {
            try
            {
                return StartPhpServerAttempt(appPath, php, serverPhp, publicDir, attempt);
            }
            catch (Exception ex)
            {
                lastError = ex;
                DesktopLog.Write(appPath, $"EnsurePhpServer tentativa {attempt}/3 falhou: {ex.Message}");
                ProcessHelper.KillPidFile(appPath);
                ProcessHelper.KillAppPhpServers(appPath);
                WaitPortFree(ErpPaths.Port, 2000);
                Thread.Sleep(500);
            }
        }

        throw lastError ?? new InvalidOperationException(
            "php -S nao ficou no ar. Veja storage\\logs\\php-serve-start.log e unitec-erp-desktop.log");
    }

    private static Process StartPhpServerAttempt(
        string appPath,
        string php,
        string serverPhp,
        string publicDir,
        int attempt)
    {
        var health = HealthClient.ProbeAsync().GetAwaiter().GetResult();
        if (health.Ok)
        {
            return Process.GetCurrentProcess();
        }

        if (health.PortOpen || IsAppPhpRunning(appPath))
        {
            DesktopLog.Write(appPath,
                $"Limpando PHP anterior antes da tentativa {attempt} (port={health.PortOpen} running={IsAppPhpRunning(appPath)})");
            ProcessHelper.KillPidFile(appPath);
            ProcessHelper.KillAppPhpServers(appPath);
            WaitPortFree(ErpPaths.Port, 2500);
            Thread.Sleep(400);
        }
        else
        {
            ProcessHelper.KillPidFile(appPath);
            ProcessHelper.KillAppPhpServers(appPath);
            WaitPortFree(ErpPaths.Port, 1500);
        }

        var args = $"-S 127.0.0.1:{ErpPaths.Port} \"{serverPhp}\"";
        var startLog = Path.Combine(appPath, "storage", "logs", "php-serve-start.log");

        // Tentativa com stderr em arquivo (diagnostico). Processo definitivo sem redirect
        // (stdout no Windows quebra php://stdout / Livewire).
        if (attempt > 1)
        {
            TryCapturePhpStartFailure(appPath, php, args, publicDir, startLog);
        }

        var psi = new ProcessStartInfo
        {
            FileName = php,
            Arguments = args,
            WorkingDirectory = publicDir,
            UseShellExecute = false,
            CreateNoWindow = true,
            WindowStyle = ProcessWindowStyle.Hidden,
            RedirectStandardOutput = false,
            RedirectStandardError = false,
        };

        DesktopLog.Write(appPath,
            $"Iniciando PHP (tentativa {attempt}): {php} -S 127.0.0.1:{ErpPaths.Port} cwd=public (sem redirect stdio)");
        var proc = Process.Start(psi)
            ?? throw new InvalidOperationException("Falha ao iniciar php -S.");

        ProcessHelper.WritePidFile(appPath, proc.Id);

        for (var i = 0; i < 60; i++)
        {
            if (proc.HasExited)
            {
                TryCapturePhpStartFailure(appPath, php, args, publicDir, startLog);
                throw new InvalidOperationException(
                    $"php -S encerrou logo apos iniciar (exit {proc.ExitCode}). "
                    + "Veja storage\\logs\\php-serve-start.log");
            }

            var probe = HealthClient.ProbeAsync().GetAwaiter().GetResult();
            if (probe.Ok || probe.HttpResponded || probe.PortOpen)
            {
                DesktopLog.Write(appPath,
                    $"PHP no ar PID={proc.Id} (ok={probe.Ok} http={probe.HttpResponded} port={probe.PortOpen})");
                return proc;
            }

            Thread.Sleep(250);
        }

        if (!proc.HasExited)
        {
            DesktopLog.Write(appPath,
                "Timeout no probe — mantendo php -S vivo (PID " + proc.Id + ").");
            return proc;
        }

        TryCapturePhpStartFailure(appPath, php, args, publicDir, startLog);
        throw new InvalidOperationException(
            "php -S nao ficou no ar. Veja storage\\logs\\php-serve-start.log");
    }

    private static void TryCapturePhpStartFailure(
        string appPath,
        string php,
        string args,
        string publicDir,
        string startLog)
    {
        try
        {
            Directory.CreateDirectory(Path.GetDirectoryName(startLog)!);
            var psi = new ProcessStartInfo
            {
                FileName = php,
                Arguments = args,
                WorkingDirectory = publicDir,
                UseShellExecute = false,
                CreateNoWindow = true,
                WindowStyle = ProcessWindowStyle.Hidden,
                RedirectStandardOutput = true,
                RedirectStandardError = true,
            };

            using var probe = Process.Start(psi);
            if (probe is null)
            {
                return;
            }

            var stdout = probe.StandardOutput.ReadToEnd();
            var stderr = probe.StandardError.ReadToEnd();
            if (!probe.WaitForExit(2500))
            {
                try { probe.Kill(entireProcessTree: true); } catch { /* ignore */ }
            }

            var dump =
                $"[{DateTime.Now:yyyy-MM-dd HH:mm:ss}] exit={probe.ExitCode}{Environment.NewLine}"
                + $"cmd: {php} {args}{Environment.NewLine}"
                + $"cwd: {publicDir}{Environment.NewLine}"
                + $"stdout:{Environment.NewLine}{stdout}{Environment.NewLine}"
                + $"stderr:{Environment.NewLine}{stderr}{Environment.NewLine}";
            File.AppendAllText(startLog, dump);
            DesktopLog.Write(appPath, "Diagnostico php -S gravado em php-serve-start.log (exit " + probe.ExitCode + ")");
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "Falha ao capturar stderr do php -S: " + ex.Message);
        }
        finally
        {
            ProcessHelper.KillAppPhpServers(appPath);
            WaitPortFree(ErpPaths.Port, 1500);
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

    public static void StopPhpServer(string appPath)
    {
        ProcessHelper.KillPidFile(appPath);
        ProcessHelper.KillAppPhpServers(appPath);
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
