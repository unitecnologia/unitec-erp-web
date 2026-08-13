using System.Drawing;
using System.Reflection;
using System.Runtime.InteropServices;
using System.Windows.Forms;

namespace Unitec.DeviceService.Host;

/// <summary>Ícone na bandeja (system tray) com o logo Unitecnologia.</summary>
public sealed class TrayIconHostedService : IHostedService, IDisposable
{
    private readonly IHostApplicationLifetime _lifetime;
    private readonly ILogger<TrayIconHostedService> _logger;
    private NotifyIcon? _icon;
    private Icon? _brandIcon;
    private Thread? _uiThread;

    public TrayIconHostedService(IHostApplicationLifetime lifetime, ILogger<TrayIconHostedService> logger)
    {
        _lifetime = lifetime;
        _logger = logger;
    }

    public Task StartAsync(CancellationToken cancellationToken)
    {
        // Serviço Windows (Session 0) sem desktop interativo — só API.
        if (!OperatingSystem.IsWindows() || !Environment.UserInteractive)
        {
            return Task.CompletedTask;
        }

        _uiThread = new Thread(RunTray)
        {
            IsBackground = true,
            Name = "UnitecDeviceService.Tray",
        };
        _uiThread.SetApartmentState(ApartmentState.STA);
        _uiThread.Start();

        return Task.CompletedTask;
    }

    private void RunTray()
    {
        try
        {
            System.Windows.Forms.Application.SetHighDpiMode(HighDpiMode.SystemAware);
            System.Windows.Forms.Application.EnableVisualStyles();

            var menu = new ContextMenuStrip();
            menu.Items.Add("Status: online em 127.0.0.1:9330", null, (_, _) => { });
            menu.Items.Add(new ToolStripSeparator());
            var sair = new ToolStripMenuItem("Sair");
            sair.Click += (_, _) =>
            {
                var ok = MessageBox.Show(
                    "Encerrar o Unitecnologia Device Service?\n\nSem ele, o PDV volta a usar o diálogo de impressão do Windows.",
                    "Device Service",
                    MessageBoxButtons.YesNo,
                    MessageBoxIcon.Question);
                if (ok == DialogResult.Yes)
                {
                    _lifetime.StopApplication();
                }
            };
            menu.Items.Add(sair);

            _brandIcon = LoadBrandIcon();

            _icon = new NotifyIcon
            {
                Text = "Unitecnologia Device Service",
                Icon = _brandIcon,
                Visible = true,
                ContextMenuStrip = menu,
            };

            _icon.DoubleClick += (_, _) =>
            {
                try
                {
                    System.Diagnostics.Process.Start(new System.Diagnostics.ProcessStartInfo
                    {
                        FileName = "http://127.0.0.1:9330/api/status",
                        UseShellExecute = true,
                    });
                }
                catch (Exception ex)
                {
                    _logger.LogWarning(ex, "Falha ao abrir status no navegador.");
                }
            };

            System.Windows.Forms.Application.Run();
        }
        catch (Exception ex)
        {
            _logger.LogWarning(ex, "Tray indisponível (serviço sem desktop?). API continua.");
        }
    }

    private static Icon LoadBrandIcon()
    {
        try
        {
            var icoPath = Path.Combine(AppContext.BaseDirectory, "Assets", "unitec.ico");
            if (File.Exists(icoPath))
            {
                using var src = new Icon(icoPath);
                return new Icon(src, 32, 32);
            }

            var asm = Assembly.GetExecutingAssembly();
            using var stream = asm.GetManifestResourceStream("Unitec.DeviceService.Assets.unitec.ico");
            if (stream is not null)
            {
                using var src = new Icon(stream);
                return new Icon(src, 32, 32);
            }
        }
        catch
        {
            // fallback
        }

        return SystemIcons.Application;
    }

    public Task StopAsync(CancellationToken cancellationToken)
    {
        try
        {
            if (_icon is not null)
            {
                _icon.Visible = false;
            }

            System.Windows.Forms.Application.Exit();
        }
        catch
        {
            // ignore
        }

        return Task.CompletedTask;
    }

    public void Dispose()
    {
        _icon?.Dispose();
        _brandIcon?.Dispose();
    }
}
