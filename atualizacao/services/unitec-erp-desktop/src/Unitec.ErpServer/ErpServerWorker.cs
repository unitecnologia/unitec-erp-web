using System.Diagnostics;
using Unitec.ErpCommon;

namespace Unitec.ErpServer;

public sealed class ErpServerWorker : BackgroundService
{
    private readonly ILogger<ErpServerWorker> _logger;
    private readonly string _appPath;
    private Process? _php;
    private DateTime _nextUpdateCheckUtc = DateTime.MinValue;

    public ErpServerWorker(ILogger<ErpServerWorker> logger)
    {
        _logger = logger;
        _appPath = ErpPaths.ResolveAppPath(
            Environment.GetEnvironmentVariable("UNITEC_APP_PATH"));
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        DesktopLog.Write(_appPath, $"UnitecErpServer iniciando em {_appPath}");
        _logger.LogInformation("Unitec ERP Server iniciando em {AppPath}", _appPath);

        try
        {
            ErpStackManager.EnsureMariaDb(_appPath);
            _php = ErpStackManager.EnsurePhpServer(_appPath);
            CloudflaredManager.Ensure(_appPath);

            // Check imediato na abertura do serviço (arquivos → atualizacao/).
            UpdateCheckService.CheckAndDownloadAsync(_appPath);
            _nextUpdateCheckUtc = DateTime.UtcNow.AddHours(5);

            var health = await HealthClient.WaitHealthyAsync(
                maxAttempts: 40,
                delayMs: 500,
                cancellationToken: stoppingToken).ConfigureAwait(false);

            if (!health.Ok)
            {
                _logger.LogWarning("Health inicial: {Kind} - {Message}", health.Kind, health.Message);
                DesktopLog.Write(_appPath, $"Health inicial falhou: {health.Kind} {health.Message}");
            }
            else
            {
                DesktopLog.Write(_appPath, $"Health OK versao={health.Version}");
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Falha ao iniciar stack");
            DesktopLog.Write(_appPath, "ERRO start: " + ex.Message);
            throw;
        }

        while (!stoppingToken.IsCancellationRequested)
        {
            try
            {
                if (!ErpStackManager.IsMariaDbListening())
                {
                    DesktopLog.Write(_appPath, "MariaDB caiu — reiniciando");
                    ErpStackManager.EnsureMariaDb(_appPath);
                }

                var health = await HealthClient.ProbeAsync(cancellationToken: stoppingToken)
                    .ConfigureAwait(false);

                if (!health.PortOpen)
                {
                    if (ErpStackManager.IsAppPhpRunning(_appPath))
                    {
                        DesktopLog.Write(_appPath,
                            "Porta 8765 fechada no probe, mas processo PHP ainda vivo — nao reinicia.");
                    }
                    else
                    {
                        DesktopLog.Write(_appPath, "PHP caiu — reiniciando");
                        _php = ErpStackManager.EnsurePhpServer(_appPath);
                    }
                }
                else if (!health.Ok)
                {
                    DesktopLog.Write(_appPath, $"App unhealthy: {health.Message}");
                }

                CloudflaredManager.Ensure(_appPath);

                if (DateTime.UtcNow >= _nextUpdateCheckUtc)
                {
                    UpdateCheckService.CheckAndDownloadAsync(_appPath);
                    _nextUpdateCheckUtc = DateTime.UtcNow.AddHours(5);
                }
            }
            catch (Exception ex) when (ex is not OperationCanceledException)
            {
                DesktopLog.Write(_appPath, "Monitor erro: " + ex.Message);
            }

            await Task.Delay(TimeSpan.FromSeconds(5), stoppingToken).ConfigureAwait(false);
        }
    }

    public override Task StopAsync(CancellationToken cancellationToken)
    {
        DesktopLog.Write(_appPath, "UnitecErpServer parando");
        try
        {
            CloudflaredManager.Stop(_appPath);
            ErpStackManager.StopPhpServer(_appPath);
            _php?.Kill(entireProcessTree: true);
        }
        catch
        {
            // ignore
        }

        return base.StopAsync(cancellationToken);
    }
}
