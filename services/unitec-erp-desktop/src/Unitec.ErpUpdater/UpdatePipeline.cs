using Unitec.ErpCommon;

namespace Unitec.ErpUpdater;

/// <summary>
/// Atualizador: ZIP opcional extrai para atualizacao/; senão o serviço baixa o ZIP do Releases.
/// Login pergunta Sim/Não.
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

        DesktopLog.Write(appPath, "Atualizador: update via ZIP → pasta atualizacao/");
        Report("starting", "Atualizações: ZIP → pasta atualizacao/…", 20);

        if (!string.IsNullOrWhiteSpace(zipPath) && File.Exists(zipPath))
        {
            Report("extracting", "Extraindo ZIP para atualizacao/…", 50);
            UpdateCheckService.ExtractZipToAtualizacao(appPath, zipPath!);
        }
        else
        {
            Report("finalizing", "Solicitando verificação de atualizações (ZIP)…", 60);
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
