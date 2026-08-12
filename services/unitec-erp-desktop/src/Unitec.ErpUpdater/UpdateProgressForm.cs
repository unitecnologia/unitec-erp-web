using System.Diagnostics;

namespace Unitec.ErpUpdater;

internal sealed class UpdateProgressForm : Form
{
    private static readonly (string Key, string Label)[] Steps =
    [
        ("starting", "Preparar processo"),
        ("downloading", "Baixar pacote"),
        ("extracting", "Verificar / extrair"),
        ("applying", "Copiar arquivos"),
        ("migrating", "Migrations"),
        ("finalizing", "Limpeza de cache"),
        ("completed", "Atualização concluída"),
    ];

    private readonly string _appPath;
    private readonly bool _cacheOnly;
    private string? _zipPath;

    private readonly Label _statusLabel = new();
    private readonly ProgressBar _mainBar = new();
    private readonly Label _percentLabel = new();
    private readonly Label _detailLabel = new();
    private readonly Label _elapsedLabel = new();
    private readonly Label _hintLabel = new();
    private readonly Button _pickZipButton = new();
    private readonly Button _closeButton = new();
    private readonly Panel _stepsPanel = new();
    private readonly Dictionary<string, Label> _stepPctLabels = new(StringComparer.OrdinalIgnoreCase);
    private readonly Dictionary<string, ProgressBar> _stepBars = new(StringComparer.OrdinalIgnoreCase);
    private readonly Dictionary<string, Label> _stepNameLabels = new(StringComparer.OrdinalIgnoreCase);
    private readonly System.Windows.Forms.Timer _pollTimer = new() { Interval = 750 };
    private readonly Stopwatch _elapsed = new();

    private bool _running;
    private bool _finished;
    private string _localPhase = string.Empty;

    public UpdateProgressForm(string appPath, string? zipPath, bool cacheOnly)
    {
        _appPath = appPath;
        _zipPath = zipPath;
        _cacheOnly = cacheOnly;

        Text = "Unitec Atualizador";
        StartPosition = FormStartPosition.CenterScreen;
        FormBorderStyle = FormBorderStyle.FixedDialog;
        MaximizeBox = false;
        MinimizeBox = true;
        ShowInTaskbar = true;
        ClientSize = new Size(520, 520);
        BackColor = Color.FromArgb(248, 250, 252);
        Font = new Font("Segoe UI", 9.5f, FontStyle.Regular, GraphicsUnit.Point);

        BuildLayout();
        _pollTimer.Tick += (_, _) => OnPollTick();
        Shown += async (_, _) => await OnShownAsync();
        FormClosing += OnFormClosing;
    }

    private void BuildLayout()
    {
        var title = new Label
        {
            Text = "Atualização do Unitec ERP",
            Font = new Font("Segoe UI Semibold", 13f, FontStyle.Bold),
            ForeColor = Color.FromArgb(15, 23, 42),
            AutoSize = false,
            Location = new Point(20, 16),
            Size = new Size(480, 28),
        };

        _statusLabel.Text = "Preparando…";
        _statusLabel.ForeColor = Color.FromArgb(30, 41, 59);
        _statusLabel.AutoSize = false;
        _statusLabel.Location = new Point(20, 52);
        _statusLabel.Size = new Size(480, 42);

        _mainBar.Location = new Point(20, 100);
        _mainBar.Size = new Size(420, 18);
        _mainBar.Minimum = 0;
        _mainBar.Maximum = 100;
        _mainBar.Style = ProgressBarStyle.Continuous;

        _percentLabel.Text = "0%";
        _percentLabel.TextAlign = ContentAlignment.MiddleRight;
        _percentLabel.Location = new Point(450, 96);
        _percentLabel.Size = new Size(50, 24);
        _percentLabel.Font = new Font("Segoe UI Semibold", 10f, FontStyle.Bold);

        _detailLabel.Text = string.Empty;
        _detailLabel.ForeColor = Color.FromArgb(100, 116, 139);
        _detailLabel.AutoSize = false;
        _detailLabel.Location = new Point(20, 126);
        _detailLabel.Size = new Size(480, 22);

        _elapsedLabel.Text = "Tempo: 00:00";
        _elapsedLabel.ForeColor = Color.FromArgb(100, 116, 139);
        _elapsedLabel.AutoSize = false;
        _elapsedLabel.Location = new Point(20, 148);
        _elapsedLabel.Size = new Size(480, 20);

        _stepsPanel.Location = new Point(20, 176);
        _stepsPanel.Size = new Size(480, 260);
        _stepsPanel.AutoScroll = false;
        BuildSteps();

        _hintLabel.Text = "Não feche esta janela até a instalação terminar.";
        _hintLabel.ForeColor = Color.FromArgb(71, 85, 105);
        _hintLabel.AutoSize = false;
        _hintLabel.Location = new Point(20, 444);
        _hintLabel.Size = new Size(250, 40);

        _pickZipButton.Text = "Escolher ZIP…";
        _pickZipButton.Location = new Point(280, 448);
        _pickZipButton.Size = new Size(110, 32);
        _pickZipButton.Visible = true;
        _pickZipButton.Enabled = true;
        _pickZipButton.Click += (_, _) => PickZipAndStart();

        _closeButton.Text = "Fechar";
        _closeButton.Location = new Point(400, 448);
        _closeButton.Size = new Size(100, 32);
        _closeButton.Enabled = false;
        _closeButton.Click += (_, _) => Close();

        Controls.Add(title);
        Controls.Add(_statusLabel);
        Controls.Add(_mainBar);
        Controls.Add(_percentLabel);
        Controls.Add(_detailLabel);
        Controls.Add(_elapsedLabel);
        Controls.Add(_stepsPanel);
        Controls.Add(_hintLabel);
        Controls.Add(_pickZipButton);
        Controls.Add(_closeButton);
    }

    private void BuildSteps()
    {
        var y = 0;
        for (var i = 0; i < Steps.Length; i++)
        {
            var (key, label) = Steps[i];
            var index = new Label
            {
                Text = (i + 1).ToString(),
                Location = new Point(0, y),
                Size = new Size(22, 20),
                ForeColor = Color.FromArgb(100, 116, 139),
            };

            var name = new Label
            {
                Text = label,
                Location = new Point(26, y),
                Size = new Size(360, 18),
                ForeColor = Color.FromArgb(51, 65, 85),
            };
            _stepNameLabels[key] = name;

            var pct = new Label
            {
                Text = "0%",
                TextAlign = ContentAlignment.MiddleRight,
                Location = new Point(400, y),
                Size = new Size(60, 18),
                ForeColor = Color.FromArgb(100, 116, 139),
            };
            _stepPctLabels[key] = pct;

            var bar = new ProgressBar
            {
                Location = new Point(26, y + 20),
                Size = new Size(434, 8),
                Minimum = 0,
                Maximum = 100,
                Style = ProgressBarStyle.Continuous,
            };
            _stepBars[key] = bar;

            _stepsPanel.Controls.Add(index);
            _stepsPanel.Controls.Add(name);
            _stepsPanel.Controls.Add(pct);
            _stepsPanel.Controls.Add(bar);
            y += 32;
        }
    }

    private async Task OnShownAsync()
    {
        var updatesDir = Path.Combine(_appPath, "storage", "app", "private", "updates");
        var localZip = Path.Combine(updatesDir, "Unitec-ERP-Update.zip");

        if (string.IsNullOrWhiteSpace(_zipPath) && !_cacheOnly && UpdatePipeline.IsLocalPackageReady(updatesDir, localZip))
        {
            _zipPath = localZip;
            SetStatus("Usando pacote local já baixado…", 5);
            MarkStep("downloading", 100);
        }

        if (string.IsNullOrWhiteSpace(_zipPath) && !_cacheOnly)
        {
            SetFailed(
                "Nenhum ZIP de atualização informado e nenhum pacote local pronto.",
                "Clique em \"Escolher ZIP…\" ou use --zip \"caminho\\Unitec-ERP-Update.zip\".");
            return;
        }

        await StartPipelineAsync();
    }

    private void PickZipAndStart()
    {
        if (_running)
        {
            MessageBox.Show(
                this,
                "Aguarde a atualização em andamento terminar antes de escolher outro ZIP.",
                "Unitec Atualizador",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
            return;
        }

        using var dlg = new OpenFileDialog
        {
            Title = "Selecione Unitec-ERP-Update.zip",
            Filter = "Pacote Unitec ERP|Unitec-ERP-Update.zip;*.zip|Arquivos ZIP|*.zip",
            CheckFileExists = true,
        };

        if (dlg.ShowDialog(this) != DialogResult.OK)
        {
            return;
        }

        _zipPath = dlg.FileName;
        _finished = false;
        _closeButton.Enabled = false;
        ResetSteps();
        SetStatus("ZIP selecionado. Iniciando…", 2);
        _ = StartPipelineAsync();
    }

    private async Task StartPipelineAsync()
    {
        if (_running)
        {
            return;
        }

        _running = true;
        _finished = false;
        _closeButton.Enabled = false;
        _pickZipButton.Enabled = false;
        _elapsed.Restart();
        _pollTimer.Start();
        SetStatus("Iniciando atualização…", 2);
        MarkStep("starting", 20);

        try
        {
            var version = await Task.Run(() =>
                UpdatePipeline.Run(
                    _appPath,
                    _zipPath,
                    _cacheOnly,
                    OnPipelinePhase));

            _pollTimer.Stop();
            ApplyLocalPhase("completed", 100, $"Atualização concluída — versão {version ?? "ok"}");
            MarkAllDone();
            SetStatus($"Atualização concluída — versão {version ?? "ok"}", 100);
            _detailLabel.Text = string.Empty;
            _hintLabel.Text = "Pode fechar esta janela.";
            _hintLabel.ForeColor = Color.FromArgb(71, 85, 105);
            _statusLabel.ForeColor = Color.FromArgb(30, 41, 59);
            _finished = true;
            _closeButton.Enabled = true;
            Environment.ExitCode = 0;
        }
        catch (Exception ex)
        {
            _pollTimer.Stop();
            SetFailed("A atualização não foi concluída.", FormatFriendlyError(ex));
        }
        finally
        {
            _running = false;
            _pickZipButton.Enabled = true;
            _elapsed.Stop();
            UpdateElapsed();
        }
    }

    private void OnPipelinePhase(string phase, string message, int percent)
    {
        if (IsDisposed)
        {
            return;
        }

        try
        {
            BeginInvoke(() => ApplyLocalPhase(phase, percent, message));
        }
        catch
        {
            // form closing
        }
    }

    private void ApplyLocalPhase(string phase, int percent, string message)
    {
        _localPhase = phase;
        SetStatus(message, percent);
        MarkStep(phase, Math.Clamp(percent, 0, 100));

        // Mark prior steps done
        var order = Steps.Select(s => s.Key).ToList();
        var idx = order.FindIndex(k => string.Equals(k, phase, StringComparison.OrdinalIgnoreCase));
        if (idx > 0)
        {
            for (var i = 0; i < idx; i++)
            {
                MarkStep(order[i], 100);
            }
        }
    }

    private void OnPollTick()
    {
        UpdateElapsed();

        // Prefer PHP status file while apply-update is running
        if (!string.IsNullOrEmpty(_localPhase)
            && (_localPhase is "finalizing" or "completed" or "cache" or "health" or "service"))
        {
            return;
        }

        var snap = UpdateStatusReader.TryRead(_appPath);
        if (snap is null)
        {
            return;
        }

        if (snap.State is "idle" && snap.Percent <= 0 && string.IsNullOrWhiteSpace(snap.Message))
        {
            return;
        }

        if (snap.State is "failed")
        {
            // Pipeline will throw; keep showing message until catch
            SetStatus(snap.Message, Math.Max(0, snap.Percent));
            if (!string.IsNullOrWhiteSpace(snap.Detail))
            {
                _detailLabel.Text = snap.Detail!;
            }

            return;
        }

        if (!string.IsNullOrWhiteSpace(snap.Message))
        {
            _statusLabel.Text = snap.Message;
        }

        if (snap.Percent > 0)
        {
            SetPercent(snap.Percent);
        }

        if (!string.IsNullOrWhiteSpace(snap.Detail))
        {
            _detailLabel.Text = snap.Detail!;
        }
        else if (!string.IsNullOrWhiteSpace(snap.Command))
        {
            _detailLabel.Text = snap.Command!;
        }

        foreach (var (key, value) in snap.StepProgress)
        {
            MarkStep(key, value);
        }

        // Highlight active state even if step_progress lagging
        if (!string.IsNullOrWhiteSpace(snap.State) && snap.State is not ("idle" or "failed"))
        {
            var order = Steps.Select(s => s.Key).ToList();
            var idx = order.FindIndex(k => string.Equals(k, snap.State, StringComparison.OrdinalIgnoreCase));
            if (idx >= 0)
            {
                for (var i = 0; i < idx; i++)
                {
                    if (!_stepPctLabels.TryGetValue(order[i], out var pct) || pct.Text == "0%")
                    {
                        MarkStep(order[i], 100);
                    }
                }

                if (snap.StepProgress.TryGetValue(snap.State, out var activePct))
                {
                    MarkStep(snap.State, activePct);
                }
                else if (snap.Percent > 0)
                {
                    MarkStep(snap.State, Math.Clamp(snap.Percent, 5, 99));
                }
            }
        }
    }

    private void SetStatus(string message, int percent)
    {
        _statusLabel.Text = message;
        SetPercent(percent);
    }

    private void SetPercent(int percent)
    {
        percent = Math.Clamp(percent, 0, 100);
        _mainBar.Value = percent;
        _percentLabel.Text = percent + "%";
    }

    private void MarkStep(string key, int percent)
    {
        percent = Math.Clamp(percent, 0, 100);
        if (_stepBars.TryGetValue(key, out var bar))
        {
            bar.Value = percent;
        }

        if (_stepPctLabels.TryGetValue(key, out var pct))
        {
            pct.Text = percent + "%";
            pct.ForeColor = percent >= 100
                ? Color.FromArgb(22, 163, 74)
                : Color.FromArgb(100, 116, 139);
        }

        if (_stepNameLabels.TryGetValue(key, out var name))
        {
            name.ForeColor = percent >= 100
                ? Color.FromArgb(22, 163, 74)
                : percent > 0
                    ? Color.FromArgb(37, 99, 235)
                    : Color.FromArgb(51, 65, 85);
            name.Font = new Font(
                "Segoe UI",
                9.5f,
                percent > 0 && percent < 100 ? FontStyle.Bold : FontStyle.Regular);
        }
    }

    private void MarkAllDone()
    {
        foreach (var (key, _) in Steps)
        {
            MarkStep(key, 100);
        }
    }

    private void ResetSteps()
    {
        foreach (var (key, _) in Steps)
        {
            MarkStep(key, 0);
        }

        SetPercent(0);
        _detailLabel.Text = string.Empty;
        _hintLabel.Text = "Não feche esta janela até a instalação terminar.";
        _hintLabel.ForeColor = Color.FromArgb(71, 85, 105);
        _statusLabel.ForeColor = Color.FromArgb(30, 41, 59);
    }

    private void SetFailed(string title, string detail)
    {
        _finished = true;
        _closeButton.Enabled = true;
        _pickZipButton.Enabled = true;
        _pickZipButton.Visible = true;
        Environment.ExitCode = 1;
        _statusLabel.Text = title;
        _statusLabel.ForeColor = Color.FromArgb(185, 28, 28);
        _detailLabel.Text = detail;
        _hintLabel.Text = "Use \"Escolher ZIP…\" ou corrija e tente de novo.";
        _hintLabel.ForeColor = Color.FromArgb(185, 28, 28);
        SetPercent(Math.Max(_mainBar.Value, 0));
    }

    private static string FormatFriendlyError(Exception ex)
    {
        var msg = ex.Message ?? string.Empty;
        if (ex is MissingMethodException
            || msg.Contains("Method not found", StringComparison.OrdinalIgnoreCase)
            || msg.Contains("Unitec.ErpCommon", StringComparison.OrdinalIgnoreCase))
        {
            return "Arquivos do Atualizador desalinhados. Copie juntos para bin\\: "
                + "Unitec Atualizador.exe, Unitec Atualizador.dll e Unitec.ErpCommon.dll "
                + "(mesma versão). Depois escolha o ZIP novamente.";
        }

        if (msg.Contains("Cannot open", StringComparison.OrdinalIgnoreCase)
            && msg.Contains("UnitecErpServer", StringComparison.OrdinalIgnoreCase))
        {
            return "Sem permissão para o serviço Windows. Execute o Atualizador como Administrador "
                + "ou use o pacote completo 6.4.1.92+ (continua sem o serviço).";
        }

        // Unwrap AggregateException from Task.Run
        if (ex is AggregateException agg && agg.InnerException is not null)
        {
            return FormatFriendlyError(agg.InnerException);
        }

        return msg;
    }

    private void UpdateElapsed()
    {
        var ts = _elapsed.Elapsed;
        _elapsedLabel.Text = $"Tempo: {ts.Minutes:00}:{ts.Seconds:00}";
    }

    private void OnFormClosing(object? sender, FormClosingEventArgs e)
    {
        if (_running && !_finished)
        {
            e.Cancel = true;
            MessageBox.Show(
                this,
                "A atualização ainda está em andamento. Aguarde a conclusão.",
                "Unitec Atualizador",
                MessageBoxButtons.OK,
                MessageBoxIcon.Information);
        }
    }
}
