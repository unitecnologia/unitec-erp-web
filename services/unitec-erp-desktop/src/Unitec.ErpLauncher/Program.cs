using System.Diagnostics;
using System.Management;
using System.Runtime.InteropServices;
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

        DesktopLog.Write(appPath, "Launcher iniciado");

        // Antes de qualquer WMI: clique extra sai na hora, sem Sleep e sem varrer o navegador.
        using var mutex = new Mutex(false, MutexName);
        var mutexOwned = false;
        try
        {
            mutexOwned = mutex.WaitOne(0);
        }
        catch (AbandonedMutexException)
        {
            mutexOwned = true;
        }

        if (!mutexOwned)
        {
            DesktopLog.Write(appPath, "Mutex ocupado — saindo");
            return;
        }

        try
        {
            DesktopLog.Write(appPath, "Mutex adquirido");

            if (!TryAcquireLockFile(lockPath))
            {
                DesktopLog.Write(appPath, "Abertura ja em andamento — saindo");
                return;
            }

            OpeningSplash.Show();

            try
            {
                DesktopLog.Write(appPath, "Verificacao do navegador existente: inicio");
                var alreadyOpen = FocusExistingBrowserApp(maximize: true);
                DesktopLog.Write(appPath, "Verificacao do navegador existente: fim");
                if (alreadyOpen)
                {
                    OpeningSplash.Close();
                    return;
                }

                RunAsync(appPath).GetAwaiter().GetResult();
            }
            catch (Exception ex)
            {
                OpeningSplash.Close();
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
        finally
        {
            try { mutex.ReleaseMutex(); } catch { /* ignore */ }
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
        DesktopLog.Write(appPath, "Health: inicio da espera");
        var health = await HealthClient.ProbeAsync().ConfigureAwait(false);
        if (health.Ok)
        {
            DesktopLog.Write(appPath, "Health OK");
            OpenOrFocusBrowser(appPath);
            UpdateCheckService.CheckAndDownloadAsync(appPath);
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

        var serviceOwnsStack = false;
        if (WindowsServiceControl.Exists())
        {
            if (!WindowsServiceControl.IsRunning())
            {
                DesktopLog.Write(appPath, "Iniciando servico UnitecErpServer");
                serviceOwnsStack = WindowsServiceControl.TryStart();
                if (!serviceOwnsStack)
                {
                    DesktopLog.Write(appPath,
                        "Aviso: nao foi possivel iniciar o servico. Usando stack direto.");
                }
            }
            else
            {
                serviceOwnsStack = true;
                DesktopLog.Write(appPath,
                    "UnitecErpServer ja Running — aguardando health sem iniciar outro PHP");
            }
        }

        if (serviceOwnsStack)
        {
            health = await HealthClient.WaitHealthyAsync(maxAttempts: 180, delayMs: 500)
                .ConfigureAwait(false);

            if (health.Ok)
            {
                DesktopLog.Write(appPath, "Health OK");
                DesktopLog.Write(appPath, "Health OK pelo servico — abrindo ERP");
                OpenOrFocusBrowser(appPath);
                UpdateCheckService.CheckAndDownloadAsync(appPath);
                return;
            }

            if (health.Kind == "app_error")
            {
                throw new InvalidOperationException(
                    "O servidor respondeu, mas /api/health falhou.\n" + health.Message);
            }

            if (WindowsServiceControl.IsRunning())
            {
                throw new InvalidOperationException(
                    "O UnitecErpServer esta em execucao, mas o ERP nao respondeu na porta 8765.\n"
                    + "O launcher nao iniciou um segundo PHP para evitar conflito.\n"
                    + health.Message);
            }

            DesktopLog.Write(appPath,
                "Servico parou durante a espera — iniciando stack direto como fallback");
        }

        health = await HealthClient.ProbeAsync().ConfigureAwait(false);
        if (!health.Ok && health.Kind != "app_error")
        {
            DesktopLog.Write(appPath,
                "Servico ausente/parado — EnsureMariaDb + EnsurePhpServer direto");
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

        DesktopLog.Write(appPath, "Health OK");
        OpenOrFocusBrowser(appPath);
        UpdateCheckService.CheckAndDownloadAsync(appPath);
    }

    private static void OpenOrFocusBrowser(string appPath)
    {
        if (FocusExistingBrowserApp(maximize: true))
        {
            OpeningSplash.Close();
            return;
        }

        var browser = FindBrowser();
        var url = ErpPaths.DefaultAppUrl.TrimEnd('/') + "/admin/login";
        var profile = GetProfileDir();
        Directory.CreateDirectory(profile);
        ClearOrphanBrowserLocks(profile, appPath);

        if (browser is null)
        {
            OpeningSplash.Close();
            Process.Start(new ProcessStartInfo { FileName = url, UseShellExecute = true });
            DesktopLog.Write(appPath, "Navegador disparado");
            return;
        }

        var args =
            $"--app={url} --user-data-dir=\"{profile}\" --start-maximized " +
            "--no-first-run --no-default-browser-check";

        try
        {
            OpeningSplash.Close();
            var started = Process.Start(new ProcessStartInfo
            {
                FileName = browser,
                Arguments = args,
                UseShellExecute = true,
            });

            DesktopLog.Write(appPath, "Navegador disparado");
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

/// <summary>
/// Janela leve de "abrindo", em thread propria, para pintar enquanto o health bloqueia.
/// Forca exibicao normal: o atalho da area de trabalho nasce minimizado.
/// </summary>
internal static class OpeningSplash
{
    private const int SwShowNormal = 1;
    private const int SwRestore = 9;

    private static Form? _form;
    private static int _closeRequested;

    public static void Show()
    {
        using var ready = new ManualResetEventSlim(false);
        var thread = new Thread(() =>
        {
            Application.EnableVisualStyles();
            var form = BuildForm();
            _ = form.Handle;
            _form = form;
            ready.Set();
            if (Volatile.Read(ref _closeRequested) != 0)
            {
                return;
            }

            Application.Run(form);
        });
        thread.IsBackground = true;
        thread.SetApartmentState(ApartmentState.STA);
        thread.Start();
        ready.Wait(TimeSpan.FromSeconds(3));
    }

    public static void Close()
    {
        Interlocked.Exchange(ref _closeRequested, 1);
        var form = _form;
        if (form is null)
        {
            return;
        }

        try
        {
            if (!form.IsDisposed && form.IsHandleCreated)
            {
                form.BeginInvoke(new Action(() =>
                {
                    if (!form.IsDisposed)
                    {
                        form.Close();
                    }
                }));
            }
        }
        catch
        {
            // Janela ja encerrada.
        }
    }

    private static Form BuildForm()
    {
        var form = new Form
        {
            Text = "Unitec ERP",
            FormBorderStyle = FormBorderStyle.FixedDialog,
            ControlBox = false,
            MaximizeBox = false,
            MinimizeBox = false,
            ShowInTaskbar = true,
            TopMost = true,
            StartPosition = FormStartPosition.CenterScreen,
            ClientSize = new Size(380, 88),
            ShowIcon = false,
        };

        form.Controls.Add(new Label
        {
            Text = "Abrindo o Unitec ERP...",
            Dock = DockStyle.Fill,
            TextAlign = ContentAlignment.MiddleCenter,
        });

        form.Shown += (_, _) => form.BeginInvoke(new Action(() => ForceVisible(form)));
        return form;
    }

    private static void ForceVisible(Form form)
    {
        if (form.IsDisposed)
        {
            return;
        }

        form.WindowState = FormWindowState.Normal;
        ShowWindow(form.Handle, SwShowNormal);
        ShowWindow(form.Handle, SwRestore);
        SetForegroundWindow(form.Handle);
    }

    [DllImport("user32.dll")]
    private static extern bool ShowWindow(IntPtr hWnd, int nCmdShow);

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);
}
