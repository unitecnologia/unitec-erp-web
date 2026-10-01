using System.Diagnostics;
using System.Text;

namespace Unitec.ErpCommon;

public static class DesktopLog
{
    private static readonly object Gate = new();

    public static void Write(string appPath, string message)
    {
        try
        {
            var path = ErpPaths.LogPath(appPath);
            Directory.CreateDirectory(Path.GetDirectoryName(path)!);
            var line = $"{DateTime.Now:yyyy-MM-dd HH:mm:ss} {message}{Environment.NewLine}";
            lock (Gate)
            {
                File.AppendAllText(path, line, Encoding.UTF8);
            }
        }
        catch
        {
            // Nunca interromper abertura por falha de log.
        }
    }
}

public static class ProcessHelper
{
    public static Process StartHidden(string fileName, string arguments, string workingDirectory)
    {
        var psi = new ProcessStartInfo
        {
            FileName = fileName,
            Arguments = arguments,
            WorkingDirectory = workingDirectory,
            UseShellExecute = false,
            CreateNoWindow = true,
            WindowStyle = ProcessWindowStyle.Hidden,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };

        var proc = Process.Start(psi)
            ?? throw new InvalidOperationException($"Nao foi possivel iniciar: {fileName}");
        return proc;
    }

    /// <summary>
    /// Processo longo (ex.: gateway WhatsApp) com stdout/stderr anexados a um arquivo de log.
    /// </summary>
    public static Process StartHiddenAppendingLog(
        string fileName,
        string arguments,
        string workingDirectory,
        string logPath)
    {
        Directory.CreateDirectory(Path.GetDirectoryName(logPath)!);

        var psi = new ProcessStartInfo
        {
            FileName = fileName,
            Arguments = arguments,
            WorkingDirectory = workingDirectory,
            UseShellExecute = false,
            CreateNoWindow = true,
            WindowStyle = ProcessWindowStyle.Hidden,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };

        var proc = Process.Start(psi)
            ?? throw new InvalidOperationException($"Nao foi possivel iniciar: {fileName}");

        void AppendLine(string? line)
        {
            if (string.IsNullOrEmpty(line))
            {
                return;
            }

            try
            {
                File.AppendAllText(
                    logPath,
                    $"{DateTime.Now:yyyy-MM-dd HH:mm:ss} {line}{Environment.NewLine}",
                    Encoding.UTF8);
            }
            catch
            {
                // ignore
            }
        }

        try
        {
            File.AppendAllText(
                logPath,
                $"{DateTime.Now:yyyy-MM-dd HH:mm:ss} --- start {fileName} {arguments}{Environment.NewLine}",
                Encoding.UTF8);
        }
        catch
        {
            // ignore
        }

        proc.OutputDataReceived += (_, e) => AppendLine(e.Data);
        proc.ErrorDataReceived += (_, e) => AppendLine(e.Data);
        proc.BeginOutputReadLine();
        proc.BeginErrorReadLine();

        return proc;
    }

    public static int RunHidden(string fileName, string arguments, string workingDirectory, int timeoutMs = 600_000)
    {
        using var proc = StartHidden(fileName, arguments, workingDirectory);
        if (!proc.WaitForExit(timeoutMs))
        {
            try { proc.Kill(entireProcessTree: true); } catch { }
            throw new TimeoutException($"Timeout ao executar: {fileName} {arguments}");
        }

        return proc.ExitCode;
    }

    public static void KillPidFile(string appPath)
    {
        var pidFile = Path.Combine(appPath, ".unitec-serve.pid");
        if (!File.Exists(pidFile))
        {
            return;
        }

        try
        {
            if (!int.TryParse(File.ReadAllText(pidFile).Trim(), out var pid))
            {
                return;
            }

            // O DEV (porta 8000) grava o mesmo pid. Parar o serviço da 8765 não pode derrubá-lo.
            if (!IsListeningOnPort(pid, ErpPaths.Port))
            {
                return;
            }

            KillProcessTree(pid);
            try { File.Delete(pidFile); } catch { }
        }
        catch
        {
            // ignore
        }
    }

    public static void WritePidFile(string appPath, int pid)
    {
        File.WriteAllText(Path.Combine(appPath, ".unitec-serve.pid"), pid.ToString());
    }

    /// <summary>
    /// Encerra processos php.exe do PHP embutido desta instalacao (pai + workers).
    /// Mantido para limpar legado php -S; o HTTP oficial e FrankenPHP.
    /// </summary>
    public static void KillAppPhpServers(string appPath)
    {
        KillProcessesUnderDir(Path.Combine(appPath, "tools", "php"), "php");
    }

    /// <summary>
    /// Encerra só o HTTP da porta do ERP (8765). Não mata o FrankenPHP de desenvolvimento (8000).
    /// </summary>
    public static void KillAppHttpServers(string appPath)
    {
        foreach (var pid in GetListeningPids(ErpPaths.Port))
        {
            KillProcessTree(pid);
        }
    }

    private static void KillProcessTree(int pid)
    {
        try
        {
            using var p = Process.GetProcessById(pid);
            if (p.HasExited)
            {
                return;
            }

            p.Kill(entireProcessTree: true);
            p.WaitForExit(3000);
        }
        catch
        {
            // ignore
        }
    }

    private static bool IsListeningOnPort(int pid, int port)
        => GetListeningPids(port).Contains(pid);

    private static HashSet<int> GetListeningPids(int port)
    {
        var pids = new HashSet<int>();

        try
        {
            var psi = new ProcessStartInfo
            {
                FileName = "netstat",
                Arguments = "-ano -p tcp",
                UseShellExecute = false,
                RedirectStandardOutput = true,
                CreateNoWindow = true,
            };

            using var proc = Process.Start(psi);
            if (proc is null)
            {
                return pids;
            }

            var output = proc.StandardOutput.ReadToEnd();
            proc.WaitForExit(3000);

            var suffix = ":" + port;
            foreach (var raw in output.Split('\n'))
            {
                if (raw.IndexOf("LISTENING", StringComparison.OrdinalIgnoreCase) < 0)
                {
                    continue;
                }

                var parts = raw.Split(' ', StringSplitOptions.RemoveEmptyEntries);
                if (parts.Length < 5 || !parts[1].EndsWith(suffix, StringComparison.Ordinal))
                {
                    continue;
                }

                if (int.TryParse(parts[^1], out var listenPid))
                {
                    pids.Add(listenPid);
                }
            }
        }
        catch
        {
            // ignore
        }

        return pids;
    }

    private static void KillProcessesUnderDir(string dir, string processName)
    {
        if (!Directory.Exists(dir))
        {
            return;
        }

        string fullDir;
        try
        {
            fullDir = Path.GetFullPath(dir);
        }
        catch
        {
            return;
        }

        foreach (var p in Process.GetProcessesByName(processName))
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
                if (moduleDir is null)
                {
                    continue;
                }

                if (!moduleDir.StartsWith(fullDir, StringComparison.OrdinalIgnoreCase))
                {
                    continue;
                }

                if (!p.HasExited)
                {
                    p.Kill(entireProcessTree: true);
                    p.WaitForExit(3000);
                }
            }
            catch
            {
                // ignore
            }
        }
    }
}
