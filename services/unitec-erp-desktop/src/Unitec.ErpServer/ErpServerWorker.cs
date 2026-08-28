using System.Diagnostics;
using Unitec.ErpCommon;

namespace Unitec.ErpServer;

public sealed class ErpServerWorker : BackgroundService
{
    private readonly ILogger<ErpServerWorker> _logger;
    private readonly string _appPath;
    private Process? _php;
    private DateTime _nextUpdateCheckUtc = DateTime.MinValue;
    private int _stopping;

    public ErpServerWorker(ILogger<ErpServerWorker> logger)
    {
        _logger = logger;
        _appPath = ErpPaths.ResolveAppPath(
            Environment.GetEnvironmentVariable("UNITEC_APP_PATH"));
    }

    protected override async Task ExecuteAsync(CancellationToken stoppingToken)
    {
        DesktopLog.Write(_appPath, $"UnitecErpServer start begin em {_appPath}");
        _logger.LogInformation("Unitec ERP Server iniciando em {AppPath}", _appPath);

        try
        {
            // Um stop/start rapido pode deixar o PHP anterior vivo por alguns segundos.
            // Limpa somente os processos PHP desta instalacao e espera a 8765 liberar.
            DesktopLog.Write(_appPath, "Start: limpando PHP orfao e aguardando porta 8765 livre");
            var startPortFree = ErpStackManager.StopPhpServer(_appPath, waitPortFreeMs: 5000);
            DesktopLog.Write(_appPath, startPortFree
                ? "Start: porta 8765 livre"
                : "Start: AVISO porta 8765 ainda ocupada apos limpeza");

            if (IsStopping(stoppingToken))
            {
                return;
            }

            DesktopLog.Write(_appPath, "Start: EnsureMariaDb");
            ErpStackManager.EnsureMariaDb(_appPath);
            DesktopLog.Write(_appPath, "Start: MariaDB OK");

            if (IsStopping(stoppingToken))
            {
                return;
            }

            DesktopLog.Write(_appPath, "Start: EnsurePhpServer");
            _php = ErpStackManager.EnsurePhpServer(_appPath);
            DesktopLog.Write(_appPath, "Start: EnsurePhpServer concluido");

            if (IsStopping(stoppingToken))
            {
                return;
            }

            CloudflaredManager.Ensure(_appPath);
            WhatsAppGatewayProcessManager.Ensure(_appPath);

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

            // O sistema deve abrir primeiro. Download/extracao do ZIP comeca depois,
            // evitando disputa de disco/rede durante o boot.
            _nextUpdateCheckUtc = DateTime.UtcNow.AddMinutes(2);
            DesktopLog.Write(_appPath, "UpdateCheck agendado para 2 minutos apos o start");
        }
        catch (OperationCanceledException) when (IsStopping(stoppingToken))
        {
            DesktopLog.Write(_appPath, "Start cancelado durante parada do servico");
            return;
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
                if (IsStopping(stoppingToken))
                {
                    break;
                }

                if (!ErpStackManager.IsMariaDbListening())
                {
                    if (IsStopping(stoppingToken))
                    {
                        break;
                    }

                    DesktopLog.Write(_appPath, "MariaDB caiu — reiniciando");
                    ErpStackManager.EnsureMariaDb(_appPath);
                }

                if (IsStopping(stoppingToken))
                {
                    break;
                }

                var health = await HealthClient.ProbeAsync(cancellationToken: stoppingToken)
                    .ConfigureAwait(false);

                if (!health.PortOpen)
                {
                    if (ErpStackManager.IsAppPhpRunning(_appPath))
                    {
                        DesktopLog.Write(_appPath,
                            "Porta 8765 fechada com PHP vivo — limpando processo travado e reiniciando");
                    }
                    else
                    {
                        DesktopLog.Write(_appPath, "PHP caiu — reiniciando");
                    }

                    if (IsStopping(stoppingToken))
                    {
                        break;
                    }

                    _php = ErpStackManager.EnsurePhpServer(_appPath);
                }
                else if (!health.Ok)
                {
                    DesktopLog.Write(_appPath, $"App unhealthy: {health.Message}");
                }

                if (IsStopping(stoppingToken))
                {
                    break;
                }

                CloudflaredManager.Ensure(_appPath);
                WhatsAppGatewayProcessManager.Ensure(_appPath);

                if (!IsStopping(stoppingToken) && DateTime.UtcNow >= _nextUpdateCheckUtc)
                {
                    UpdateCheckService.CheckAndDownloadAsync(_appPath);
                    _nextUpdateCheckUtc = DateTime.UtcNow.AddHours(5);
                }
            }
            catch (Exception ex) when (ex is not OperationCanceledException)
            {
                DesktopLog.Write(_appPath, "Monitor erro: " + ex.Message);
            }

            try
            {
                await Task.Delay(TimeSpan.FromSeconds(5), stoppingToken).ConfigureAwait(false);
            }
            catch (OperationCanceledException) when (IsStopping(stoppingToken))
            {
                break;
            }
        }
    }

    public override async Task StopAsync(CancellationToken cancellationToken)
    {
        Interlocked.Exchange(ref _stopping, 1);
        DesktopLog.Write(_appPath, "UnitecErpServer stop begin");

        // Primeiro cancela e aguarda o loop de monitoramento. Assim ele nao pode
        // interpretar o PHP encerrado abaixo como queda e religa-lo no meio do stop.
        await base.StopAsync(cancellationToken).ConfigureAwait(false);

        try
        {
            CloudflaredManager.Stop(_appPath);
            WhatsAppGatewayProcessManager.Stop(_appPath);
            var portFree = ErpStackManager.StopPhpServer(_appPath, waitPortFreeMs: 5000);

            try
            {
                if (_php is { HasExited: false })
                {
                    _php.Kill(entireProcessTree: true);
                    _php.WaitForExit(3000);
                }
            }
            catch
            {
                // Processo pode ja ter encerrado durante StopPhpServer.
            }

            DesktopLog.Write(_appPath, portFree
                ? "UnitecErpServer stop: PHP encerrado e porta 8765 livre"
                : "UnitecErpServer stop: AVISO porta 8765 ainda ocupada");
        }
        catch (Exception ex)
        {
            DesktopLog.Write(_appPath, "UnitecErpServer stop erro: " + ex.Message);
        }

        DesktopLog.Write(_appPath, "UnitecErpServer stop concluido");
    }

    private bool IsStopping(CancellationToken stoppingToken)
        => Volatile.Read(ref _stopping) != 0 || stoppingToken.IsCancellationRequested;
}
