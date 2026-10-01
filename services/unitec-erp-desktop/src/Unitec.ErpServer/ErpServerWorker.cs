using System.Diagnostics;
using Unitec.ErpCommon;

namespace Unitec.ErpServer;

public sealed class ErpServerWorker : BackgroundService
{
    private static readonly TimeSpan ScheduleInterval = TimeSpan.FromMinutes(1);
    private static readonly TimeSpan ScheduleRunTimeout = TimeSpan.FromMinutes(10);

    private readonly ILogger<ErpServerWorker> _logger;
    private readonly string _appPath;
    private Process? _php;
    private DateTime _nextUpdateCheckUtc = DateTime.MinValue;
    private DateTime _nextScheduleRunUtc = DateTime.MinValue;
    private int _scheduleRunBusy;
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

            // Laravel Scheduler (erp:backup --scheduled, etc.). Só dispara schedule:run;
            // o intervalo do backup permanece no comando PHP.
            // Alinhado ao minuto do relógio para o backup cair no mesmo minuto do último.
            _nextScheduleRunUtc = NextAlignedScheduleUtc(DateTime.UtcNow);
            DesktopLog.Write(_appPath,
                $"Laravel schedule:run alinhado a cada 1 min (próximo UTC {_nextScheduleRunUtc:HH:mm:ss})");
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

                TryQueueLaravelScheduleRun();
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

    /// <summary>
    /// Dispara <c>php artisan schedule:run</c> a cada minuto.
    /// Não chama backup diretamente — o Laravel decide o que executar.
    /// </summary>
    private void TryQueueLaravelScheduleRun()
    {
        if (Volatile.Read(ref _stopping) != 0)
        {
            return;
        }

        if (DateTime.UtcNow < _nextScheduleRunUtc)
        {
            return;
        }

        // Agenda o próximo minuto do relógio, não "agora + 1".
        _nextScheduleRunUtc = NextAlignedScheduleUtc(DateTime.UtcNow);

        if (Interlocked.CompareExchange(ref _scheduleRunBusy, 1, 0) != 0)
        {
            DesktopLog.Write(_appPath, "schedule:run pulado — execução anterior ainda em andamento");
            _logger.LogWarning("schedule:run pulado: execução anterior ainda em andamento");
            return;
        }

        _ = Task.Run(() =>
        {
            try
            {
                RunLaravelScheduleOnce();
            }
            finally
            {
                Interlocked.Exchange(ref _scheduleRunBusy, 0);
            }
        });
    }

    private void RunLaravelScheduleOnce()
    {
        string php;
        try
        {
            php = ErpPaths.ResolvePhpExe(_appPath);
        }
        catch (Exception ex)
        {
            DesktopLog.Write(_appPath, "schedule:run erro ao resolver PHP: " + ex.Message);
            _logger.LogWarning(ex, "Falha ao resolver PHP para schedule:run");
            return;
        }

        DesktopLog.Write(_appPath, $"schedule:run begin cwd={_appPath} php={php} args=artisan schedule:run");

        try
        {
            using var proc = ProcessHelper.StartHidden(php, "artisan schedule:run", _appPath);

            // Drena stdout/stderr para não travar o pipe no Windows.
            var stdoutTask = proc.StandardOutput.ReadToEndAsync();
            var stderrTask = proc.StandardError.ReadToEndAsync();

            if (!proc.WaitForExit((int)ScheduleRunTimeout.TotalMilliseconds))
            {
                try { proc.Kill(entireProcessTree: true); } catch { /* ignore */ }

                DesktopLog.Write(_appPath, "schedule:run TIMEOUT — processo encerrado; MariaDB/PHP do ERP seguem ativos");
                _logger.LogWarning("schedule:run excedeu {TimeoutMinutes} minutos e foi encerrado", ScheduleRunTimeout.TotalMinutes);
                return;
            }

            var stdout = stdoutTask.GetAwaiter().GetResult().Trim();
            var stderr = stderrTask.GetAwaiter().GetResult().Trim();

            if (proc.ExitCode != 0)
            {
                DesktopLog.Write(_appPath,
                    $"schedule:run FALHOU exit={proc.ExitCode}"
                    + (stderr.Length > 0 ? $" stderr={TruncateForLog(stderr)}" : "")
                    + (stdout.Length > 0 ? $" stdout={TruncateForLog(stdout)}" : ""));
                _logger.LogWarning(
                    "schedule:run falhou exit={ExitCode}. stderr={Stderr}",
                    proc.ExitCode,
                    TruncateForLog(stderr));
                return;
            }

            DesktopLog.Write(_appPath,
                "schedule:run OK"
                + (stdout.Length > 0 ? $" stdout={TruncateForLog(stdout)}" : ""));
        }
        catch (Exception ex)
        {
            // Nunca derrubar MariaDB/PHP/ERP por falha do scheduler.
            DesktopLog.Write(_appPath, "schedule:run erro: " + ex.Message);
            _logger.LogWarning(ex, "Falha ao executar artisan schedule:run (ERP continua ativo)");
        }
    }

    /// <summary>
    /// Próximo instante UTC alinhado ao minuto do relógio.
    /// Sempre estritamente no futuro em relação a <paramref name="utcNow"/>.
    /// Ex.: 22:17:00 → 22:18:00; 22:17:01 → 22:18:00; 22:59:40 → 23:00:00.
    /// </summary>
    internal static DateTime NextAlignedScheduleUtc(DateTime utcNow)
    {
        var utc = utcNow.Kind == DateTimeKind.Utc
            ? utcNow
            : DateTime.SpecifyKind(utcNow.ToUniversalTime(), DateTimeKind.Utc);

        var currentSlot = new DateTime(
            utc.Year, utc.Month, utc.Day, utc.Hour, utc.Minute, 0, DateTimeKind.Utc);

        return currentSlot.Add(ScheduleInterval);
    }

    private static string TruncateForLog(string text, int max = 500)
    {
        if (string.IsNullOrEmpty(text))
        {
            return string.Empty;
        }

        var oneLine = text.Replace("\r", " ").Replace("\n", " ").Trim();
        return oneLine.Length <= max ? oneLine : oneLine[..max] + "…";
    }
}
