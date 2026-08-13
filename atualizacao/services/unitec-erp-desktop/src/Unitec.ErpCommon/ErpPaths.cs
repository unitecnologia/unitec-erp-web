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

    public const string CloudflaredProgramDataDir = @"C:\ProgramData\Unitec\cloudflared";

    public static string CloudflaredConfigPath
        => Path.Combine(CloudflaredProgramDataDir, "config.yml");

    public static string CloudflaredStatusPath
        => Path.Combine(CloudflaredProgramDataDir, "status.json");

    public static string CloudflaredPidPath
        => Path.Combine(CloudflaredProgramDataDir, ".unitec-cloudflared.pid");

    public static string ResolveCloudflaredExe(string appPath)
    {
        var candidates = new[]
        {
            Path.Combine(CloudflaredProgramDataDir, "cloudflared.exe"),
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
}
