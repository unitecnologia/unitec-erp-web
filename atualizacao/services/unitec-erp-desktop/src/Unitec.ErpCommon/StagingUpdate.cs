using System.Diagnostics;
using System.Text;
using System.Text.Json;

namespace Unitec.ErpCommon;

/// <summary>
/// Atualização por staging: o pacote fica em staging/pending/ e só é aplicado
/// na abertura do Unitec ERP.exe (ou no start do serviço).
/// </summary>
public static class StagingUpdate
{
    public const string ZipFileName = "Unitec-ERP-Update.zip";
    public const string ReadyFileName = "ready.json";
    public const string CacheOnlyFlagName = "cache-only.flag";

    public static string PendingDir(string appPath)
        => Path.Combine(appPath, "staging", "pending");

    public static string PendingZipPath(string appPath)
        => Path.Combine(PendingDir(appPath), ZipFileName);

    public static string ReadyJsonPath(string appPath)
        => Path.Combine(PendingDir(appPath), ReadyFileName);

    public static string CacheOnlyFlagPath(string appPath)
        => Path.Combine(PendingDir(appPath), CacheOnlyFlagName);

    public static string UpdatesDir(string appPath)
        => Path.Combine(appPath, "storage", "app", "private", "updates");

    public static string UpdatesZipPath(string appPath)
        => Path.Combine(UpdatesDir(appPath), ZipFileName);

    public static bool HasPendingPackage(string appPath)
    {
        var zip = PendingZipPath(appPath);
        return File.Exists(zip) && new FileInfo(zip).Length > 1024;
    }

    public static bool HasCacheOnlyFlag(string appPath)
        => File.Exists(CacheOnlyFlagPath(appPath));

    public static bool HasPendingWork(string appPath)
        => HasPendingPackage(appPath) || HasCacheOnlyFlag(appPath);

    /// <summary>
    /// Copia o ZIP para staging/pending e grava ready.json. Nao aplica na pasta viva.
    /// </summary>
    public static string DepositPackage(string appPath, string sourceZipPath, string? packageVersion = null)
    {
        if (!File.Exists(sourceZipPath))
        {
            throw new FileNotFoundException("ZIP de atualizacao nao encontrado.", sourceZipPath);
        }

        var pending = PendingDir(appPath);
        Directory.CreateDirectory(pending);

        var target = PendingZipPath(appPath);
        var same = string.Equals(
            Path.GetFullPath(sourceZipPath),
            Path.GetFullPath(target),
            StringComparison.OrdinalIgnoreCase);

        if (!same)
        {
            File.Copy(sourceZipPath, target, overwrite: true);
        }

        var info = new FileInfo(target);
        var sha = Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(File.ReadAllBytes(target)))
            .ToLowerInvariant();

        var ready = new StringBuilder();
        ready.Append('{');
        ready.Append("\"ready\":true,");
        ready.Append($"\"package_bytes\":{info.Length},");
        ready.Append($"\"package_sha256\":\"{sha}\",");
        if (!string.IsNullOrWhiteSpace(packageVersion))
        {
            ready.Append($"\"package_version\":\"{EscapeJson(packageVersion)}\",");
        }

        ready.Append($"\"deposited_at\":\"{DateTime.UtcNow:O}\"");
        ready.Append('}');
        File.WriteAllText(ReadyJsonPath(appPath), ready.ToString(), Encoding.UTF8);

        // Remove flag cache-only se havia — pacote completo tem prioridade.
        try { File.Delete(CacheOnlyFlagPath(appPath)); } catch { /* ignore */ }

        DesktopLog.Write(appPath, $"Staging: pacote depositado em {target} (sha={sha})");
        return target;
    }

    public static void DepositCacheOnlyFlag(string appPath)
    {
        Directory.CreateDirectory(PendingDir(appPath));
        File.WriteAllText(CacheOnlyFlagPath(appPath), DateTime.UtcNow.ToString("O"), Encoding.UTF8);
        DesktopLog.Write(appPath, "Staging: flag cache-only gravada.");
    }

    /// <summary>
    /// Na abertura: aplica staging (se houver), migrate, limpa caches se necessario.
    /// Retorna true se houve trabalho de update/cache.
    /// </summary>
    public static bool ApplyPendingOnBoot(string appPath)
    {
        if (!HasPendingWork(appPath))
        {
            // Sem staging: ainda tenta migrate (tabelas novas). Falha nao bloqueia abertura.
            try
            {
                TryMigrate(appPath);
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "Aviso migrate na abertura (sem staging): " + ex.Message);
            }

            return false;
        }

        DesktopLog.Write(appPath, "Staging pendente — aplicando na abertura.");
        ErpStackManager.EnsureMariaDb(appPath);

        var appliedPackage = false;
        if (HasPendingPackage(appPath))
        {
            // Libera arquivos da pasta viva para a copia.
            ErpStackManager.StopPhpServer(appPath);
            Thread.Sleep(500);

            PromotePendingZipToUpdates(appPath);

            var php = ErpPaths.ResolvePhpExe(appPath);
            DesktopLog.Write(appPath, "Staging: unitec:apply-update");
            var code = ProcessHelper.RunHidden(
                php,
                $"artisan unitec:apply-update --app-path=\"{appPath}\"",
                appPath,
                1_800_000);
            if (code != 0)
            {
                throw new InvalidOperationException(
                    $"Falha ao aplicar pacote do staging (exit {code}). "
                    + "Veja storage\\logs\\erp-update.log / unitec-erp-desktop.log");
            }

            appliedPackage = true;
            ClearPendingPackage(appPath);
        }

        TryMigrate(appPath);

        if (appliedPackage || HasCacheOnlyFlag(appPath))
        {
            DesktopLog.Write(appPath, "Staging: limpando e regenerando caches");
            ErpStackManager.ClearAndRebuildCachesAsync(appPath).GetAwaiter().GetResult();
            try { File.Delete(CacheOnlyFlagPath(appPath)); } catch { /* ignore */ }
        }

        DesktopLog.Write(appPath, "Staging: apply na abertura concluido.");
        return true;
    }

    public static void TryLaunchErpExe(string appPath)
    {
        var candidates = new[]
        {
            Path.Combine(appPath, "bin", "Unitec ERP.exe"),
            Path.Combine(appPath, "Unitec ERP.exe"),
        };

        foreach (var exe in candidates)
        {
            if (!File.Exists(exe))
            {
                continue;
            }

            try
            {
                Process.Start(new ProcessStartInfo
                {
                    FileName = exe,
                    WorkingDirectory = Path.GetDirectoryName(exe)!,
                    UseShellExecute = true,
                });
                DesktopLog.Write(appPath, "Staging: aberto " + exe);
                return;
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "Aviso ao abrir Unitec ERP.exe: " + ex.Message);
            }
        }

        DesktopLog.Write(appPath, "Aviso: Unitec ERP.exe nao encontrado para abrir apos staging.");
    }

    private static void PromotePendingZipToUpdates(string appPath)
    {
        var pendingZip = PendingZipPath(appPath);
        var updatesDir = UpdatesDir(appPath);
        Directory.CreateDirectory(updatesDir);

        var targetZip = UpdatesZipPath(appPath);
        File.Copy(pendingZip, targetZip, overwrite: true);

        var info = new FileInfo(targetZip);
        var sha = Convert.ToHexString(System.Security.Cryptography.SHA256.HashData(File.ReadAllBytes(targetZip)))
            .ToLowerInvariant();

        string? version = null;
        try
        {
            if (File.Exists(ReadyJsonPath(appPath)))
            {
                using var doc = JsonDocument.Parse(File.ReadAllText(ReadyJsonPath(appPath)));
                if (doc.RootElement.TryGetProperty("package_version", out var ver))
                {
                    version = ver.GetString();
                }
            }
        }
        catch
        {
            // ignore
        }

        var meta =
            "{"
            + "\"package_ready\":true,"
            + "\"download_state\":\"ready\","
            + $"\"package_bytes\":{info.Length},"
            + $"\"package_sha256\":\"{sha}\","
            + (string.IsNullOrWhiteSpace(version) ? "" : $"\"package_version\":\"{EscapeJson(version)}\",")
            + $"\"downloaded_at\":\"{DateTime.UtcNow:O}\","
            + "\"check_message\":\"Pacote promovido do staging\""
            + "}";
        File.WriteAllText(Path.Combine(updatesDir, "package.json"), meta, Encoding.UTF8);
        DesktopLog.Write(appPath, "Staging: ZIP promovido para storage/app/private/updates");
    }

    private static void ClearPendingPackage(string appPath)
    {
        try { File.Delete(PendingZipPath(appPath)); } catch { /* ignore */ }
        try { File.Delete(ReadyJsonPath(appPath)); } catch { /* ignore */ }
    }

    private static void TryMigrate(string appPath)
    {
        try
        {
            var php = ErpPaths.ResolvePhpExe(appPath);
            DesktopLog.Write(appPath, "Staging/boot: artisan migrate --force");
            ProcessHelper.RunHidden(php, "artisan migrate --force", appPath, 600_000);
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "Aviso migrate na abertura: " + ex.Message);
            throw;
        }
    }

    private static string EscapeJson(string value)
        => value.Replace("\\", "\\\\", StringComparison.Ordinal).Replace("\"", "\\\"", StringComparison.Ordinal);
}
