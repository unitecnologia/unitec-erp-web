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
            if (int.TryParse(File.ReadAllText(pidFile).Trim(), out var pid))
            {
                using var p = Process.GetProcessById(pid);
                p.Kill(entireProcessTree: true);
            }
        }
        catch
        {
            // ignore
        }
        finally
        {
            try { File.Delete(pidFile); } catch { }
        }
    }

    public static void WritePidFile(string appPath, int pid)
    {
        File.WriteAllText(Path.Combine(appPath, ".unitec-serve.pid"), pid.ToString());
    }

    /// <summary>
    /// Encerra processos php.exe do PHP embutido desta instalacao (pai + workers).
    /// </summary>
    public static void KillAppPhpServers(string appPath)
    {
        var embeddedPhpDir = Path.Combine(appPath, "tools", "php");
        string? embeddedPhpFull = null;
        try
        {
            if (Directory.Exists(embeddedPhpDir))
            {
                embeddedPhpFull = Path.GetFullPath(embeddedPhpDir);
            }
        }
        catch
        {
            return;
        }

        if (string.IsNullOrWhiteSpace(embeddedPhpFull))
        {
            return;
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
                if (moduleDir is null)
                {
                    continue;
                }

                if (!moduleDir.StartsWith(embeddedPhpFull, StringComparison.OrdinalIgnoreCase))
                {
                    continue;
                }

                p.Kill(entireProcessTree: true);
            }
            catch
            {
                // ignore
            }
        }
    }
}
