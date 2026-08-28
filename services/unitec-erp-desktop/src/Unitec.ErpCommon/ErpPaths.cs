namespace Unitec.ErpCommon;

public static class ErpPaths
{
    public const string DefaultAppPath = @"C:\UNITECNOLOGIA_WEB";
    public const string DefaultAppUrl = "http://127.0.0.1:8765";
    public const string ServiceName = "UnitecErpServer";
    public const string ServiceDisplayName = "Unitec ERP Server";
    public const int Port = 8765;
    public const string HealthPath = "/api/health";

    public static string ResolveAppPath(string? overridePath = null)
    {
        if (!string.IsNullOrWhiteSpace(overridePath) && Directory.Exists(overridePath))
        {
            return Path.GetFullPath(overridePath);
        }

        var env = Environment.GetEnvironmentVariable("UNITEC_APP_PATH");
        if (!string.IsNullOrWhiteSpace(env) && Directory.Exists(env))
        {
            return Path.GetFullPath(env);
        }

        var beside = Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, "..", ".."));
        if (File.Exists(Path.Combine(beside, "artisan")))
        {
            return beside;
        }

        var parent = Path.GetFullPath(Path.Combine(AppContext.BaseDirectory, ".."));
        if (File.Exists(Path.Combine(parent, "artisan")))
        {
            return parent;
        }

        if (File.Exists(Path.Combine(AppContext.BaseDirectory, "artisan")))
        {
            return Path.GetFullPath(AppContext.BaseDirectory);
        }

        if (Directory.Exists(DefaultAppPath) && File.Exists(Path.Combine(DefaultAppPath, "artisan")))
        {
            return DefaultAppPath;
        }

        return DefaultAppPath;
    }

    public static string ResolvePhpExe(string appPath)
    {
        var direct = Path.Combine(appPath, "tools", "php", "php.exe");
        if (File.Exists(direct))
        {
            return direct;
        }

        var phpRoot = Path.Combine(appPath, "tools", "php");
        if (Directory.Exists(phpRoot))
        {
            var nested = Directory.GetDirectories(phpRoot)
                .Select(d => Path.Combine(d, "php.exe"))
                .FirstOrDefault(File.Exists);
            if (nested is not null)
            {
                return nested;
            }
        }

        return "php";
    }

    public static string ResolveMysqldExe(string appPath)
    {
        var candidates = new[]
        {
            Path.Combine(appPath, "tools", "mysql", "bin", "mysqld.exe"),
            Path.Combine(appPath, "tools", "mysql", "mariadb-11.4.5-winx64", "bin", "mysqld.exe"),
        };

        foreach (var path in candidates)
        {
            if (File.Exists(path))
            {
                return path;
            }
        }

        var mysqlRoot = Path.Combine(appPath, "tools", "mysql");
        if (!Directory.Exists(mysqlRoot))
        {
            return string.Empty;
        }

        return Directory.GetFiles(mysqlRoot, "mysqld.exe", SearchOption.AllDirectories)
            .FirstOrDefault() ?? string.Empty;
    }

    public static string LogPath(string appPath)
        => Path.Combine(appPath, "storage", "logs", "unitec-erp-desktop.log");

    public static string WhatsAppGatewayRoot(string appPath)
        => Path.Combine(appPath, "services", "erp-whatsapp-gateway");

    public static string WhatsAppGatewayIndex(string appPath)
        => Path.Combine(WhatsAppGatewayRoot(appPath), "index.js");

    public static string WhatsAppBaileysPackage(string appPath)
        => Path.Combine(WhatsAppGatewayRoot(appPath), "node_modules", "@whiskeysockets", "baileys", "package.json");

    public static string WhatsAppConfigPath(string appPath)
        => Path.Combine(appPath, "storage", "app", "whatsapp", "gateway-config.json");

    public static string WhatsAppPidPath(string appPath)
        => Path.Combine(appPath, "storage", "app", "whatsapp", "gateway.pid");

    public static string WhatsAppGatewayLogPath(string appPath)
        => Path.Combine(appPath, "storage", "logs", "whatsapp-gateway.log");

    public static string ResolveNodeExe(string appPath)
    {
        try
        {
            var configPath = WhatsAppConfigPath(appPath);
            if (File.Exists(configPath))
            {
                var json = File.ReadAllText(configPath);
                using var doc = System.Text.Json.JsonDocument.Parse(json);
                if (doc.RootElement.TryGetProperty("nodeExecutable", out var nodeProp)
                    && nodeProp.ValueKind == System.Text.Json.JsonValueKind.String)
                {
                    var configured = (nodeProp.GetString() ?? "").Trim();
                    if (configured.Length > 0 && File.Exists(configured))
                    {
                        return configured;
                    }
                }
            }
        }
        catch
        {
            // segue candidatos padrão
        }

        var direct = Path.Combine(appPath, "tools", "node", "node.exe");
        if (File.Exists(direct))
        {
            return direct;
        }

        var nodeRoot = Path.Combine(appPath, "tools", "node");
        if (Directory.Exists(nodeRoot))
        {
            var nested = Directory.GetDirectories(nodeRoot, "node-v*-win-x64")
                .Select(d => Path.Combine(d, "node.exe"))
                .Where(File.Exists)
                .OrderByDescending(p => p, StringComparer.OrdinalIgnoreCase)
                .FirstOrDefault();
            if (nested is not null)
            {
                return nested;
            }
        }

        var programFiles = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFiles);
        var programFilesX86 = Environment.GetFolderPath(Environment.SpecialFolder.ProgramFilesX86);
        var localAppData = Environment.GetFolderPath(Environment.SpecialFolder.LocalApplicationData);

        foreach (var candidate in new[]
                 {
                     Path.Combine(programFiles, "nodejs", "node.exe"),
                     Path.Combine(programFilesX86, "nodejs", "node.exe"),
                     Path.Combine(localAppData, "Programs", "node", "node.exe"),
                 })
        {
            if (File.Exists(candidate))
            {
                return candidate;
            }
        }

        return "node";
    }

    public static int ResolveWhatsAppGatewayPort(string appPath)
    {
        try
        {
            var configPath = WhatsAppConfigPath(appPath);
            if (File.Exists(configPath))
            {
                using var doc = System.Text.Json.JsonDocument.Parse(File.ReadAllText(configPath));
                if (doc.RootElement.TryGetProperty("port", out var portProp)
                    && portProp.TryGetInt32(out var port)
                    && port is >= 1024 and <= 65535)
                {
                    return port;
                }
            }
        }
        catch
        {
            // default
        }

        return 8091;
    }

    public const string CloudflaredProgramDataDir = @"C:\ProgramData\Unitec\cloudflared";

    public static string CloudflaredConfigPath
        => Path.Combine(CloudflaredProgramDataDir, "config.yml");

    public static string CloudflaredStatusPath
        => Path.Combine(CloudflaredProgramDataDir, "status.json");

    public static string CloudflaredPidPath
        => Path.Combine(CloudflaredProgramDataDir, ".unitec-cloudflared.pid");

    public static string CloudflaredRestartFlagPath
        => Path.Combine(CloudflaredProgramDataDir, "restart.flag");

    /// <summary>
    /// Copia o exe embarcado (resources/cloudflared) para ProgramData se ainda não existir.
    /// </summary>
    public static void EnsureCloudflaredExeInProgramData(string appPath)
    {
        var dest = Path.Combine(CloudflaredProgramDataDir, "cloudflared.exe");
        try
        {
            if (File.Exists(dest) && new FileInfo(dest).Length > 1_000_000)
            {
                return;
            }

            foreach (var src in CloudflaredEmbeddedCandidates(appPath))
            {
                if (!File.Exists(src) || new FileInfo(src).Length <= 1_000_000)
                {
                    continue;
                }

                Directory.CreateDirectory(CloudflaredProgramDataDir);
                File.Copy(src, dest, overwrite: true);
                return;
            }
        }
        catch
        {
            // Ensure trata exe ausente.
        }
    }

    public static string ResolveCloudflaredExe(string appPath)
    {
        EnsureCloudflaredExeInProgramData(appPath);

        var candidates = new[]
        {
            Path.Combine(CloudflaredProgramDataDir, "cloudflared.exe"),
            Path.Combine(appPath, "resources", "cloudflared", "cloudflared.exe"),
            Path.Combine(appPath, "tools", "cloudflared", "cloudflared.exe"),
            Path.Combine(appPath, "bin", "cloudflared.exe"),
        };

        foreach (var path in candidates)
        {
            if (File.Exists(path))
            {
                return path;
            }
        }

        return "cloudflared";
    }

    private static IEnumerable<string> CloudflaredEmbeddedCandidates(string appPath)
    {
        yield return Path.Combine(appPath, "resources", "cloudflared", "cloudflared.exe");
        yield return Path.Combine(appPath, "bin", "cloudflared.exe");
        yield return Path.Combine(appPath, "tools", "cloudflared", "cloudflared.exe");
    }
}
