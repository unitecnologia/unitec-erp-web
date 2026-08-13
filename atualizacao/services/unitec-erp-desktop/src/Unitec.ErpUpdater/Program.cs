using Unitec.ErpCommon;

namespace Unitec.ErpUpdater;

internal static class Program
{
    [STAThread]
    private static void Main(string[] args)
    {
        ApplicationConfiguration.Initialize();
        var appPath = ErpPaths.ResolveAppPath(GetArg(args, "--app"));
        var zip = GetArg(args, "--zip");
        var quiet = args.Any(a => string.Equals(a, "--quiet", StringComparison.OrdinalIgnoreCase)
            || string.Equals(a, "--no-ui", StringComparison.OrdinalIgnoreCase));
        var cacheOnly = args.Any(a => string.Equals(a, "--cache-only", StringComparison.OrdinalIgnoreCase));

        if (!quiet)
        {
            Application.Run(new UpdateProgressForm(appPath, zip, cacheOnly));
            return;
        }

        // Disparado pelo ERP: sem janela.
        try
        {
            UpdatePipeline.Run(appPath, zip, cacheOnly);
            DesktopLog.Write(appPath, "Atualizacao concluida com sucesso");
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "Updater erro: " + ex.Message);
            Environment.ExitCode = 1;
        }
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
