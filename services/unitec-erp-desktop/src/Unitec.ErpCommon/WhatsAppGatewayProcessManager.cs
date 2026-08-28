using System.Diagnostics;
using System.Net.Http;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;

namespace Unitec.ErpCommon;

/// <summary>
/// Mantém o gateway WhatsApp (Node/Baileys) sob o UnitecErpServer.
/// Falha não derruba o ERP.
/// </summary>
public static class WhatsAppGatewayProcessManager
{
    private static readonly object Gate = new();
    private static readonly HttpClient Http = new()
    {
        Timeout = TimeSpan.FromSeconds(2),
    };

    public static void Ensure(string appPath)
    {
        lock (Gate)
        {
            try
            {
                var index = ErpPaths.WhatsAppGatewayIndex(appPath);
                if (!File.Exists(index))
                {
                    DesktopLog.Write(appPath, "WhatsApp gateway: index.js nao encontrado — ignorado.");
                    return;
                }

                if (!File.Exists(ErpPaths.WhatsAppBaileysPackage(appPath)))
                {
                    DesktopLog.Write(appPath,
                        "WhatsApp gateway: dependencias (Baileys) ausentes — rode npm install em services\\erp-whatsapp-gateway.");
                    return;
                }

                var port = ErpPaths.ResolveWhatsAppGatewayPort(appPath);
                if (IsHealthy(port))
                {
                    return;
                }

                if (TryGetManagedPid(appPath, out var existingPid) && IsProcessAlive(existingPid))
                {
                    // Processo vivo mas /health ainda nao — aguarda o watchdog.
                    return;
                }

                var nodeExe = ErpPaths.ResolveNodeExe(appPath);
                if (!File.Exists(nodeExe) && !string.Equals(nodeExe, "node", StringComparison.OrdinalIgnoreCase))
                {
                    DesktopLog.Write(appPath,
                        "WhatsApp gateway: Node.js nao encontrado (tools\\node ou instalacao do sistema).");
                    return;
                }

                EnsureMinimalConfig(appPath, port, nodeExe);

                TryStopManagedProcess(appPath);
                TryDeletePidFile(appPath);

                var gatewayDir = ErpPaths.WhatsAppGatewayRoot(appPath);
                var logPath = ErpPaths.WhatsAppGatewayLogPath(appPath);
                DesktopLog.Write(appPath, $"Iniciando WhatsApp gateway: {nodeExe} index.js (porta {port})");

                var proc = ProcessHelper.StartHiddenAppendingLog(
                    nodeExe,
                    "index.js",
                    gatewayDir,
                    logPath);

                Directory.CreateDirectory(Path.GetDirectoryName(ErpPaths.WhatsAppPidPath(appPath))!);
                File.WriteAllText(ErpPaths.WhatsAppPidPath(appPath), proc.Id.ToString(), Encoding.UTF8);

                Thread.Sleep(1200);
                if (proc.HasExited)
                {
                    DesktopLog.Write(appPath,
                        $"WhatsApp gateway encerrou imediatamente (exit={proc.ExitCode}). Veja storage\\logs\\whatsapp-gateway.log");
                    TryDeletePidFile(appPath);
                    return;
                }

                for (var i = 0; i < 8; i++)
                {
                    if (IsHealthy(port))
                    {
                        DesktopLog.Write(appPath, $"WhatsApp gateway OK (pid={proc.Id}, porta={port})");
                        return;
                    }

                    Thread.Sleep(500);
                }

                DesktopLog.Write(appPath,
                    $"WhatsApp gateway iniciado (pid={proc.Id}) mas /health ainda nao respondeu na porta {port}.");
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "WhatsApp gateway Ensure: " + ex.Message);
            }
        }
    }

    public static void Stop(string appPath)
    {
        lock (Gate)
        {
            try
            {
                TryStopManagedProcess(appPath);
                TryStopByPort(ErpPaths.ResolveWhatsAppGatewayPort(appPath));
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "WhatsApp gateway Stop: " + ex.Message);
            }
            finally
            {
                TryDeletePidFile(appPath);
            }
        }
    }

    private static void EnsureMinimalConfig(string appPath, int port, string nodeExe)
    {
        var configPath = ErpPaths.WhatsAppConfigPath(appPath);
        Directory.CreateDirectory(Path.GetDirectoryName(configPath)!);
        var sessionsPath = Path.Combine(appPath, "storage", "app", "whatsapp", "sessions");
        Directory.CreateDirectory(sessionsPath);

        if (File.Exists(configPath))
        {
            return;
        }

        var key = Convert.ToHexString(RandomNumberGenerator.GetBytes(32)).ToLowerInvariant();
        var payload = new Dictionary<string, object>
        {
            ["port"] = port,
            ["key"] = key,
            ["sessionsPath"] = sessionsPath,
            ["host"] = "127.0.0.1",
            ["nodeExecutable"] = nodeExe,
        };

        var json = JsonSerializer.Serialize(payload, new JsonSerializerOptions { WriteIndented = true });
        File.WriteAllText(configPath, json, new UTF8Encoding(encoderShouldEmitUTF8Identifier: false));
        DesktopLog.Write(appPath, "WhatsApp gateway: gateway-config.json inicial criado.");
    }

    private static bool IsHealthy(int port)
    {
        try
        {
            using var response = Http.GetAsync($"http://127.0.0.1:{port}/health")
                .GetAwaiter()
                .GetResult();
            return response.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }

    private static void TryStopManagedProcess(string appPath)
    {
        if (!TryGetManagedPid(appPath, out var pid) || !IsProcessAlive(pid))
        {
            return;
        }

        try
        {
            using var p = Process.GetProcessById(pid);
            p.Kill(entireProcessTree: true);
            p.WaitForExit(3000);
        }
        catch
        {
            // ignore
        }
    }

    private static void TryStopByPort(int port)
    {
        try
        {
            ProcessHelper.RunHidden(
                "powershell",
                $"-NoProfile -Command \"$p = Get-NetTCPConnection -LocalPort {port} -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1 -ExpandProperty OwningProcess; if ($p) {{ Stop-Process -Id $p -Force -ErrorAction SilentlyContinue }}\"",
                Environment.SystemDirectory,
                timeoutMs: 15_000);
        }
        catch
        {
            // ignore
        }
    }

    private static bool TryGetManagedPid(string appPath, out int pid)
    {
        pid = 0;
        try
        {
            var pidPath = ErpPaths.WhatsAppPidPath(appPath);
            if (!File.Exists(pidPath))
            {
                return false;
            }

            var text = File.ReadAllText(pidPath).Trim();
            if (string.Equals(text, "windows-background", StringComparison.OrdinalIgnoreCase))
            {
                return false;
            }

            return int.TryParse(text, out pid) && pid > 0;
        }
        catch
        {
            return false;
        }
    }

    private static bool IsProcessAlive(int pid)
    {
        try
        {
            using var p = Process.GetProcessById(pid);
            return !p.HasExited;
        }
        catch
        {
            return false;
        }
    }

    private static void TryDeletePidFile(string appPath)
    {
        try
        {
            var pidPath = ErpPaths.WhatsAppPidPath(appPath);
            if (File.Exists(pidPath))
            {
                File.Delete(pidPath);
            }
        }
        catch
        {
            // ignore
        }
    }
}
