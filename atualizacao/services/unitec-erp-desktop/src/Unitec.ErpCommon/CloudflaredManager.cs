using System.Diagnostics;
using System.Text;
using System.Text.Json;

namespace Unitec.ErpCommon;

/// <summary>
/// Mantém o cloudflared sob o UnitecErpServer (opcional — falha não derruba o ERP).
/// </summary>
public static class CloudflaredManager
{
    private static readonly object Gate = new();

    public static void Ensure(string appPath)
    {
        lock (Gate)
        {
            try
            {
                Directory.CreateDirectory(ErpPaths.CloudflaredProgramDataDir);

                if (!File.Exists(ErpPaths.CloudflaredConfigPath))
                {
                    WriteStatus(
                        online: false,
                        pid: null,
                        message: "Config ausente: C:\\ProgramData\\Unitec\\cloudflared\\config.yml");
                    return;
                }

                if (TryGetManagedPid(out var existingPid) && IsProcessAlive(existingPid))
                {
                    WriteStatus(online: true, pid: existingPid, message: "");
                    return;
                }

                var exe = ErpPaths.ResolveCloudflaredExe(appPath);
                if (!File.Exists(exe) && exe == "cloudflared")
                {
                    WriteStatus(
                        online: false,
                        pid: null,
                        message: "cloudflared.exe nao encontrado (ProgramData ou tools\\cloudflared).");
                    return;
                }

                // Evita PID órfão.
                TryDeletePidFile();

                var args = $"tunnel --config \"{ErpPaths.CloudflaredConfigPath}\" run";
                DesktopLog.Write(appPath, $"Iniciando cloudflared: {exe} {args}");

                var proc = ProcessHelper.StartHidden(exe, args, ErpPaths.CloudflaredProgramDataDir);
                File.WriteAllText(ErpPaths.CloudflaredPidPath, proc.Id.ToString(), Encoding.UTF8);

                // Pequena espera: se morrer na hora, status fica offline.
                Thread.Sleep(800);
                if (proc.HasExited)
                {
                    WriteStatus(
                        online: false,
                        pid: null,
                        message: $"cloudflared encerrou imediatamente (exit={proc.ExitCode}).");
                    TryDeletePidFile();
                    return;
                }

                WriteStatus(online: true, pid: proc.Id, message: "");
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "cloudflared Ensure: " + ex.Message);
                WriteStatus(online: false, pid: null, message: "Erro ao iniciar cloudflared: " + ex.Message);
            }
        }
    }

    public static void Stop(string appPath)
    {
        lock (Gate)
        {
            try
            {
                if (TryGetManagedPid(out var pid) && IsProcessAlive(pid))
                {
                    try
                    {
                        using var p = Process.GetProcessById(pid);
                        p.Kill(entireProcessTree: true);
                    }
                    catch
                    {
                        // ignore
                    }
                }
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "cloudflared Stop: " + ex.Message);
            }
            finally
            {
                TryDeletePidFile();
                WriteStatus(online: false, pid: null, message: "UnitecErpServer parou o túnel.");
            }
        }
    }

    public static void RefreshStatusOnly()
    {
        lock (Gate)
        {
            if (TryGetManagedPid(out var pid) && IsProcessAlive(pid))
            {
                WriteStatus(online: true, pid: pid, message: "");
                return;
            }

            WriteStatus(online: false, pid: null, message: "Processo cloudflared gerenciado nao esta em execucao.");
        }
    }

    private static bool TryGetManagedPid(out int pid)
    {
        pid = 0;
        try
        {
            if (!File.Exists(ErpPaths.CloudflaredPidPath))
            {
                return false;
            }

            var text = File.ReadAllText(ErpPaths.CloudflaredPidPath).Trim();
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

    private static void TryDeletePidFile()
    {
        try
        {
            if (File.Exists(ErpPaths.CloudflaredPidPath))
            {
                File.Delete(ErpPaths.CloudflaredPidPath);
            }
        }
        catch
        {
            // ignore
        }
    }

    private static void WriteStatus(bool online, int? pid, string message)
    {
        Directory.CreateDirectory(ErpPaths.CloudflaredProgramDataDir);

        string? lastOnlineAt = null;
        try
        {
            if (File.Exists(ErpPaths.CloudflaredStatusPath))
            {
                using var doc = JsonDocument.Parse(File.ReadAllText(ErpPaths.CloudflaredStatusPath));
                if (doc.RootElement.TryGetProperty("last_online_at", out var lo)
                    && lo.ValueKind == JsonValueKind.String)
                {
                    lastOnlineAt = lo.GetString();
                }
            }
        }
        catch
        {
            // ignore
        }

        var now = DateTimeOffset.Now.ToString("o");
        if (online)
        {
            lastOnlineAt = now;
        }

        var payload = new Dictionary<string, object?>
        {
            ["online"] = online,
            ["checked_at"] = now,
            ["last_online_at"] = lastOnlineAt,
            ["pid"] = pid,
            ["message"] = message ?? "",
        };

        var json = JsonSerializer.Serialize(payload, new JsonSerializerOptions
        {
            WriteIndented = true,
        });

        File.WriteAllText(ErpPaths.CloudflaredStatusPath, json, new UTF8Encoding(encoderShouldEmitUTF8Identifier: false));

        // Espelho opcional no storage do ERP (fallback de leitura no PHP).
        try
        {
            var appPath = ErpPaths.ResolveAppPath();
            var mirror = Path.Combine(appPath, "storage", "app", "cloudflared-status.json");
            Directory.CreateDirectory(Path.GetDirectoryName(mirror)!);
            File.WriteAllText(mirror, json, new UTF8Encoding(encoderShouldEmitUTF8Identifier: false));
        }
        catch
        {
            // ignore
        }
    }
}
