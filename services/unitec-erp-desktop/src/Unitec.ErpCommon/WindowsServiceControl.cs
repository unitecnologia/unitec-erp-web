using System.Diagnostics;
using System.Runtime.InteropServices;
using System.ServiceProcess;

namespace Unitec.ErpCommon;

public static class WindowsServiceControl
{
    public static bool Exists(string serviceName = ErpPaths.ServiceName)
    {
        try
        {
            return ServiceController.GetServices()
                .Any(s => string.Equals(s.ServiceName, serviceName, StringComparison.OrdinalIgnoreCase));
        }
        catch
        {
            return false;
        }
    }

    public static ServiceControllerStatus? GetStatus(string serviceName = ErpPaths.ServiceName)
    {
        try
        {
            using var sc = new ServiceController(serviceName);
            return sc.Status;
        }
        catch
        {
            return null;
        }
    }

    public static bool IsRunning(string serviceName = ErpPaths.ServiceName)
    {
        var status = GetStatus(serviceName);
        return status is ServiceControllerStatus.Running or ServiceControllerStatus.StartPending;
    }

    /// <summary>
    /// Tenta iniciar o serviço. Nunca lança exceção (Cannot open / sem admin).
    /// </summary>
    public static bool TryStart(string serviceName = ErpPaths.ServiceName, int timeoutSeconds = 30)
    {
        try
        {
            using var sc = new ServiceController(serviceName);
            if (sc.Status is ServiceControllerStatus.Running or ServiceControllerStatus.StartPending)
            {
                return true;
            }

            sc.Start();
            sc.WaitForStatus(ServiceControllerStatus.Running, TimeSpan.FromSeconds(timeoutSeconds));
            return true;
        }
        catch (Exception)
        {
            return false;
        }
    }

    public static void Start(string serviceName = ErpPaths.ServiceName, int timeoutSeconds = 30)
    {
        if (!TryStart(serviceName, timeoutSeconds))
        {
            throw new InvalidOperationException(
                $"Nao foi possivel iniciar o servico '{serviceName}'. Execute o Atualizador como Administrador.");
        }
    }

    public static bool TryStop(string serviceName = ErpPaths.ServiceName, int timeoutSeconds = 30)
    {
        try
        {
            using var sc = new ServiceController(serviceName);
            if (sc.Status is ServiceControllerStatus.Stopped or ServiceControllerStatus.StopPending)
            {
                return true;
            }

            sc.Stop();
            sc.WaitForStatus(ServiceControllerStatus.Stopped, TimeSpan.FromSeconds(timeoutSeconds));
            return true;
        }
        catch (Exception)
        {
            return false;
        }
    }

    public static void Stop(string serviceName = ErpPaths.ServiceName, int timeoutSeconds = 30)
    {
        if (!TryStop(serviceName, timeoutSeconds))
        {
            throw new InvalidOperationException(
                $"Nao foi possivel parar o servico '{serviceName}'. Execute o Atualizador como Administrador.");
        }
    }

    public static int RunSc(string arguments)
    {
        var psi = new ProcessStartInfo
        {
            FileName = "sc.exe",
            Arguments = arguments,
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };

        using var proc = Process.Start(psi);
        if (proc is null)
        {
            return -1;
        }

        proc.WaitForExit(60_000);
        return proc.ExitCode;
    }
}

public static class NativeWindowFocus
{
    private const int SwRestore = 9;

    [DllImport("user32.dll")]
    private static extern bool SetForegroundWindow(IntPtr hWnd);

    [DllImport("user32.dll")]
    private static extern bool ShowWindowAsync(IntPtr hWnd, int nCmdShow);

    public static bool FocusProcess(int processId, int showCmd = SwRestore)
    {
        try
        {
            using var proc = Process.GetProcessById(processId);
            if (proc.MainWindowHandle == IntPtr.Zero)
            {
                return false;
            }

            ShowWindowAsync(proc.MainWindowHandle, showCmd);
            return SetForegroundWindow(proc.MainWindowHandle);
        }
        catch
        {
            return false;
        }
    }
}
