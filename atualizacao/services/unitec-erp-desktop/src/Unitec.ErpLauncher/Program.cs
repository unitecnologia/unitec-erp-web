using System.Diagnostics;
using System.Management;
using Unitec.ErpCommon;

namespace Unitec.ErpLauncher;

internal static class Program
{
    private const string MutexName = "Local\\UnitecErpLauncherSingleInstance";
    private const int SwMaximize = 3;

    [STAThread]
    private static void Main(string[] args)
    {
        ApplicationConfiguration.Initialize();

        var appPath = ErpPaths.ResolveAppPath(GetArg(args, "--app"));
        var lockPath = Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "UnitecERP",
            "launcher.lock");
        Directory.CreateDirectory(Path.GetDirectoryName(lockPath)!);

        // Se ja existe janela do ERP (--app + perfil Unitec), so foca/maximiza.
        if (FocusExistingBrowserApp(maximize: true))
        {
            return;
        }

        using var mutex = new Mutex(true, MutexName, out var created);
        if (!created)
        {
            // Outro launcher esta no meio da abertura.
            Thread.Sleep(800);
            FocusExistingBrowserApp(maximize: true);
            return;
        }

        if (!TryAcquireLockFile(lockPath))
        {
            Thread.Sleep(800);
            FocusExistingBrowserApp(maximize: true);
            return;
        }

        DesktopLog.Write(appPath, "Launcher iniciado");

        try
        {
            RunAsync(appPath).GetAwaiter().GetResult();
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "Launcher erro: " + ex.Message);
            MessageBox.Show(
                "Nao foi possivel abrir o Unitec ERP.\n\n" + ex.Message +
                "\n\nConsulte storage\\logs\\unitec-erp-desktop.log",
                "Unitec ERP",
                MessageBoxButtons.OK,
                MessageBoxIcon.Error);
        }
        finally
        {
            try { File.Delete(lockPath); } catch { /* ignore */ }
        }
    }

    private static bool TryAcquireLockFile(string lockPath)
    {
        try
        {
            if (File.Exists(lockPath))
            {
                var text = File.ReadAllText(lockPath).Trim();
                if (int.TryParse(text, out var pid))
                {
                    try
                    {
                        using var existing = Process.GetProcessById(pid);
                        if (!existing.HasExited)
                        {
                            return false;
                        }
                    }
                    catch
                    {
                        // PID morto — segue.
                    }
                }
            }

            File.WriteAllText(lockPath, Environment.ProcessId.ToString());
            return true;
        }
        catch
        {
            return true;
        }
    }

    private static async Task RunAsync(string appPath)
    {
        // Download de atualizacao em background (arquivos soltos → atualizacao/).
        UpdateCheckService.CheckAndDownloadAsync(appPath);

        var health = await HealthClient.ProbeAsync().ConfigureAwait(false);
        if (health.Ok)
        {
            OpenOrFocusBrowser(appPath);
            return;
        }

        if (health.Kind == "app_error")
        {
            throw new InvalidOperationException(
                "O servidor respondeu, mas /api/health falhou.\n" + health.Message +
                "\nIsso indica problema na aplicacao/cache, nao servidor parado.");
        }

        if (!ErpStackManager.IsMariaDbListening())
        {
            DesktopLog.Write(appPath, "MariaDB parado — iniciando via servico/stack");
        }

        if (WindowsServiceControl.Exists())
        {
            if (!WindowsServiceControl.IsRunning())
            {
                DesktopLog.Write(appPath, "Iniciando servico UnitecErpServer");
                try
                {
                    WindowsServiceControl.Start();
                }
                catch (Exception ex)
                {
                    DesktopLog.Write(appPath, "Aviso: nao iniciou servico (" + ex.Message + "). Subindo PHP direto.");
                }
            }
            else
            {
                DesktopLog.Write(appPath, "UnitecErpServer ja Running — validando porta 8765");
            }
        }

        health = await HealthClient.ProbeAsync().ConfigureAwait(false);
        if (!health.Ok && health.Kind != "app_error")
        {
            DesktopLog.Write(appPath, "Health nao OK apos servico — EnsureMariaDb + EnsurePhpServer");
            try
            {
                ErpStackManager.EnsureMariaDb(appPath);
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "Aviso MariaDB: " + ex.Message);
            }

            ErpStackManager.EnsurePhpServer(appPath);
        }

        health = await HealthClient.WaitHealthyAsync(maxAttempts: 20, delayMs: 500)
            .ConfigureAwait(false);

        if (!health.Ok)
        {
            var status = ErpStackManager.GetStatus(appPath);
            var detail = health.Message;
            if (!status.MariaDbRunning)
            {
                detail += "\nMariaDB: parado (porta 3306).";
            }

            throw new InvalidOperationException(
                "Nao foi possivel iniciar o servidor do ERP.\n" + detail);
        }

        OpenOrFocusBrowser(appPath);
    }

    private static void OpenOrFocusBrowser(string appPath)
    {
        if (FocusExistingBrowserApp(maximize: true))
        {
            return;
        }

        var browser = FindBrowser();
        var url = ErpPaths.DefaultAppUrl.TrimEnd('/') + "/admin/login";
        var profile = GetProfileDir();
        Directory.CreateDirectory(profile);
        ClearOrphanBrowserLocks(profile, appPath);

        if (browser is null)
        {
            Process.Start(new ProcessStartInfo { FileName = url, UseShellExecute = true });
            return;
        }

        var args =
            $"--app={url} --user-data-dir=\"{profile}\" --start-maximized " +
            "--no-first-run --no-default-browser-check";

        try
        {
            var started = Process.Start(new ProcessStartInfo
            {
                FileName = browser,
                Arguments = args,
                UseShellExecute = true,
            });

            DesktopLog.Write(appPath, "Browser iniciado: " + browser + " pid=" + (started?.Id.ToString() ?? "?"));

            // Garante maximizar apos a janela --app aparecer.
            _ = Task.Run(async () =>
            {
                for (var i = 0; i < 20; i++)
                {
                    await Task.Delay(250).ConfigureAwait(false);
                    if (FocusExistingBrowserApp(maximize: true))
                    {
                        return;
                    }
                }
            });
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "Falha ao iniciar browser: " + ex.Message);
            throw;
        }
    }

    private static string GetProfileDir()
        => Path.Combine(
            Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData),
            "UnitecERP",
            "browser-profile");

    /// <summary>
    /// So considera a janela do Unitec: command line com --app= e o perfil isolado.
    /// </summary>
    private static bool FocusExistingBrowserApp(bool maximize)
    {
        var profile = GetProfileDir();
        var profileNorm = NormalizePath(profile);

        foreach (var name in new[] { "chrome", "msedge" })
        {
            foreach (var proc in Process.GetProcessesByName(name))
            {
                try
                {
                    var cmd = GetProcessCommandLine(proc.Id);
                    if (string.IsNullOrWhiteSpace(cmd))
                    {
                        continue;
                    }

                    if (!IsUnitecAppProcess(cmd, profileNorm))
                    {
                        continue;
                    }

                    if (NativeWindowFocus.FocusProcess(proc.Id, maximize ? SwMaximize : 9))
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

        return false;
    }

    private static bool IsUnitecAppProcess(string commandLine, string profileNorm)
    {
        if (commandLine.IndexOf("--app=", StringComparison.OrdinalIgnoreCase) < 0)
        {
            return false;
        }

        // Perfil isolado do Unitec (user-data-dir).
        if (commandLine.IndexOf("UnitecERP", StringComparison.OrdinalIgnoreCase) >= 0
            && commandLine.IndexOf("browser-profile", StringComparison.OrdinalIgnoreCase) >= 0)
        {
            return true;
        }

        var profileInCmd = commandLine.IndexOf(profileNorm, StringComparison.OrdinalIgnoreCase) >= 0
            || commandLine.IndexOf(profileNorm.Replace('\\', '/'), StringComparison.OrdinalIgnoreCase) >= 0;

        return profileInCmd;
    }

    private static void ClearOrphanBrowserLocks(string profile, string appPath)
    {
        if (!Directory.Exists(profile))
        {
            return;
        }

        if (HasLiveUnitecBrowserProcess(profile))
        {
            return;
        }

        foreach (var name in new[] { "SingletonLock", "SingletonCookie", "SingletonSocket" })
        {
            var path = Path.Combine(profile, name);
            try
            {
                if (File.Exists(path) || Directory.Exists(path))
                {
                    File.Delete(path);
                    DesktopLog.Write(appPath, "Removido lock orfao do browser: " + name);
                }
            }
            catch (Exception ex)
            {
                try
                {
                    if (Directory.Exists(path))
                    {
                        Directory.Delete(path, recursive: true);
                        DesktopLog.Write(appPath, "Removido lock orfao (dir): " + name);
                    }
                }
                catch (Exception ex2)
                {
                    DesktopLog.Write(appPath, "Nao foi possivel limpar " + name + ": " + ex.Message + " / " + ex2.Message);
                }
            }
        }
    }

    private static bool HasLiveUnitecBrowserProcess(string profile)
    {
        var profileNorm = NormalizePath(profile);

        foreach (var name in new[] { "chrome", "msedge" })
        {
            foreach (var proc in Process.GetProcessesByName(name))
            {
                try
                {
                    var cmd = GetProcessCommandLine(proc.Id);
                    if (!string.IsNullOrWhiteSpace(cmd) && IsUnitecAppProcess(cmd, profileNorm))
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

        return false;
    }

    private static string? GetProcessCommandLine(int processId)
    {
        try
        {
            using var searcher = new ManagementObjectSearcher(
                $"SELECT CommandLine FROM Win32_Process WHERE ProcessId = {processId}");
            using var results = searcher.Get();
            foreach (ManagementBaseObject obj in results)
            {
                return obj["CommandLine"]?.ToString();
            }
        }
        catch
        {
            // WMI indisponivel — sem command line nao focamos processos genericos.
        }

        return null;
    }

    private static string NormalizePath(string path)
        => Path.GetFullPath(path).TrimEnd('\\', '/');

    private static string? FindBrowser()
    {
        var candidates = new[]
        {
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), @"Google\Chrome\Application\chrome.exe"),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), @"Google\Chrome\Application\chrome.exe"),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData), @"Google\Chrome\Application\chrome.exe"),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86), @"Microsoft\Edge\Application\msedge.exe"),
            Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles), @"Microsoft\Edge\Application\msedge.exe"),
        };

        return candidates.FirstOrDefault(File.Exists);
    }

    private static string? GetArg(string[] args, string name)
    {
        for (var i = 0; i < args.Length - 1; i++)
        {
            if (string.Equals(args[i], name, StringComparison.OrdinalIgnoreCase))
            {
                return args[i + 1];
            }
        }

        return null;
    }
}
