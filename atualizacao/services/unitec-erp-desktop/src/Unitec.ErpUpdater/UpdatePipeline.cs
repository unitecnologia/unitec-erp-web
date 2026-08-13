using Unitec.ErpCommon;

namespace Unitec.ErpUpdater;

/// <summary>
/// Atualizador legado: o caminho feliz é UnitecErpServer baixar arquivos para atualizacao/
/// e o login perguntar Sim/Não. Aqui só abre o ERP.
/// </summary>
internal static class UpdatePipeline
{
    public static string? Run(
        string appPath,
        string? zipPath,
        bool cacheOnly,
        Action<string, string, int>? progress = null)
    {
        void Report(string phase, string message, int percent)
        {
            progress?.Invoke(phase, message, percent);
            DesktopLog.Write(appPath, $"[{phase}] {message}");
        }

        DesktopLog.Write(appPath, "Atualizador: modo avisos — update automatico via pasta atualizacao/");
        Report("starting", "As atualizações agora vão para a pasta atualizacao/…", 20);

        if (!string.IsNullOrWhiteSpace(zipPath) && File.Exists(zipPath))
        {
            Report("extracting", "Extraindo arquivos para atualizacao/ (sem manter ZIP)…", 50);
            ExtractZipToAtualizacao(appPath, zipPath!);
        }
        else
        {
            Report("finalizing", "Solicitando verificação de atualizações…", 60);
            try
            {
                UpdateCheckService.CheckAndDownload(appPath);
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "Aviso UpdateCheck: " + ex.Message);
            }
        }

        Report("finalizing", "Abrindo o Unitec ERP…", 90);
        TryLaunchErp(appPath);

        var version = UpdateCheckService.ReadInstalledVersion(appPath) ?? "ok";
        Report("completed", $"Pronto. Se houver update, o login perguntará. Versão atual: {version}", 100);
        return version;
    }

    private static void ExtractZipToAtualizacao(string appPath, string zipPath)
    {
        var dest = UpdateCheckService.AtualizacaoDir(appPath);
        var temp = Path.Combine(Path.GetTempPath(), "unitec-upd-"+Guid.NewGuid().ToString("N"));
        Directory.CreateDirectory(temp);
        try
        {
            System.IO.Compression.ZipFile.ExtractToDirectory(zipPath, temp, overwriteFiles: true);
            var root = temp;
            var nested = Directory.GetDirectories(temp);
            if (nested.Length == 1 && File.Exists(Path.Combine(nested[0], "artisan")))
            {
                root = nested[0];
            }

            if (Directory.Exists(dest))
            {
                Directory.Delete(dest, recursive: true);
            }

            Directory.CreateDirectory(Path.GetDirectoryName(dest)!);
            CopyDirectory(root, dest);

            var ver = ReadVersionFromTree(dest) ?? "desconhecida";
            var ready =
                "{\"ready\":true,\"version\":\"" + ver.Replace("\"", "'") +
                "\",\"deposited_at\":\"" + DateTime.UtcNow.ToString("o") + "\"}";
            File.WriteAllText(UpdateCheckService.ReadyPath(appPath), ready);
            DesktopLog.Write(appPath, "ZIP extraido para atualizacao/ v" + ver + " (ZIP nao permanece na pasta)");
        }
        finally
        {
            try { Directory.Delete(temp, recursive: true); } catch { /* ignore */ }
        }
    }

    private static string? ReadVersionFromTree(string root)
    {
        var cfg = Path.Combine(root, "config", "unitec.php");
        if (!File.Exists(cfg))
        {
            return null;
        }

        var text = File.ReadAllText(cfg);
        var m = System.Text.RegularExpressions.Regex.Match(
            text, @"['""]versao['""]\s*=>\s*['""]([^'""]+)['""]",
            System.Text.RegularExpressions.RegexOptions.IgnoreCase);
        return m.Success ? m.Groups[1].Value.Trim() : null;
    }

    private static void CopyDirectory(string source, string dest)
    {
        Directory.CreateDirectory(dest);
        foreach (var file in Directory.GetFiles(source))
        {
            File.Copy(file, Path.Combine(dest, Path.GetFileName(file)), overwrite: true);
        }

        foreach (var dir in Directory.GetDirectories(source))
        {
            CopyDirectory(dir, Path.Combine(dest, Path.GetFileName(dir)));
        }
    }

    private static void TryLaunchErp(string appPath)
    {
        var exe = Path.Combine(appPath, "bin", "Unitec ERP.exe");
        if (!File.Exists(exe))
        {
            return;
        }

        try
        {
            System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo
            {
                FileName = exe,
                UseShellExecute = true,
                WorkingDirectory = Path.GetDirectoryName(exe)!,
            });
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "Aviso ao abrir ERP: " + ex.Message);
        }
    }
}
