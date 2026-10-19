using System.Diagnostics;
using System.Net;
using System.Runtime.InteropServices;
using System.Text;
using System.Text.Json;
using Unitec.ErpCommon;

namespace Unitec.ErpLauncher;

/// <summary>
/// Helper leve na sessão interativa do usuário (não é serviço Windows).
/// Expõe loopback 127.0.0.1:9331 para o PHP/ERP abrir FolderBrowserDialog
/// quando o runtime HTTP está sob UnitecErpServer (Session 0).
/// </summary>
internal static class UiHelperHost
{
    public const int Port = 9331;
    public const string MutexName = "Local\\UnitecErpUiHelperSingleInstance";
    private static readonly Uri BaseUri = new($"http://127.0.0.1:{Port}/");
    private static int _pickInFlight;
    private static Form? _activePickOwner;
    private static readonly object PickGate = new();

    public static void Run(string appPath)
    {
        using var mutex = new Mutex(true, MutexName, out var createdNew);
        if (!createdNew)
        {
            DesktopLog.Write(appPath, "UI Helper já em execução — saindo");
            return;
        }

        try
        {
            using var listener = new HttpListener();
            listener.Prefixes.Add(BaseUri.AbsoluteUri);
            listener.Start();
            DesktopLog.Write(appPath, $"UI Helper ouvindo {BaseUri.AbsoluteUri}");

            while (true)
            {
                HttpListenerContext ctx;
                try
                {
                    ctx = listener.GetContext();
                }
                catch (HttpListenerException ex)
                {
                    DesktopLog.Write(appPath, "UI Helper listener encerrado: " + ex.Message);
                    break;
                }
                catch (ObjectDisposedException)
                {
                    break;
                }

                ThreadPool.QueueUserWorkItem(_ => HandleRequest(ctx, appPath));
            }
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "UI Helper falhou ao iniciar: " + ex.Message);
            throw;
        }
    }

    /// <summary>
    /// Garante o processo --ui-helper na sessão atual (chamado pelo Launcher).
    /// </summary>
    public static void EnsureStarted(string appPath)
    {
        if (IsOnline(timeoutMs: 800))
        {
            return;
        }

        var exe = Environment.ProcessPath;
        if (string.IsNullOrWhiteSpace(exe) || !File.Exists(exe))
        {
            DesktopLog.Write(appPath, "UI Helper: ProcessPath inválido — não iniciado");
            return;
        }

        try
        {
            var psi = new ProcessStartInfo
            {
                FileName = exe,
                Arguments = $"--ui-helper --app \"{appPath}\"",
                UseShellExecute = false,
                CreateNoWindow = true,
                WindowStyle = ProcessWindowStyle.Hidden,
                WorkingDirectory = Path.GetDirectoryName(exe) ?? appPath,
            };

            using var started = Process.Start(psi);
            DesktopLog.Write(appPath,
                "UI Helper disparado pid=" + (started?.Id.ToString() ?? "?"));
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "UI Helper falha ao disparar: " + ex.Message);
            return;
        }

        for (var i = 0; i < 20; i++)
        {
            Thread.Sleep(150);
            if (IsOnline(timeoutMs: 500))
            {
                DesktopLog.Write(appPath, "UI Helper online");
                return;
            }
        }

        DesktopLog.Write(appPath, "UI Helper: timeout aguardando /api/status");
    }

    public static bool IsOnline(int timeoutMs = 1000)
    {
        try
        {
            using var client = new HttpClient { Timeout = TimeSpan.FromMilliseconds(Math.Max(200, timeoutMs)) };
            using var response = client.GetAsync(new Uri(BaseUri, "api/status")).GetAwaiter().GetResult();
            return response.IsSuccessStatusCode;
        }
        catch
        {
            return false;
        }
    }

    private static void HandleRequest(HttpListenerContext ctx, string appPath)
    {
        try
        {
            var remote = ctx.Request.RemoteEndPoint?.Address;
            if (remote is not null && !IPAddress.IsLoopback(remote))
            {
                WriteJson(ctx, 403, new { ok = false, message = "Somente localhost." });
                return;
            }

            if (HttpMethods.IsOptions(ctx.Request.HttpMethod))
            {
                WriteCors(ctx.Response);
                ctx.Response.StatusCode = 204;
                ctx.Response.Close();
                return;
            }

            var path = (ctx.Request.Url?.AbsolutePath ?? "/").TrimEnd('/').ToLowerInvariant();
            if (path.Length == 0)
            {
                path = "/";
            }

            if (path is "/api/status" && HttpMethods.IsGet(ctx.Request.HttpMethod))
            {
                WriteJson(ctx, 200, new
                {
                    ok = true,
                    online = true,
                    service = "Unitec ERP UI Helper",
                    port = Port,
                    utcNow = DateTimeOffset.UtcNow,
                });
                return;
            }

            if (path is "/api/pick-folder" && HttpMethods.IsPost(ctx.Request.HttpMethod))
            {
                HandlePickFolder(ctx, appPath);
                return;
            }

            if (path is "/api/cancel-pick" && HttpMethods.IsPost(ctx.Request.HttpMethod))
            {
                HandleCancelPick(ctx, appPath);
                return;
            }

            WriteJson(ctx, 404, new { ok = false, message = "Rota não encontrada." });
        }
        catch (Exception ex)
        {
            try
            {
                DesktopLog.Write(appPath, "UI Helper request erro: " + ex.Message);
                WriteJson(ctx, 500, new { ok = false, message = ex.Message });
            }
            catch
            {
                // ignore
            }
        }
    }

    private static void HandleCancelPick(HttpListenerContext ctx, string appPath)
    {
        var closed = TryCloseActivePick();
        DesktopLog.Write(appPath, closed
            ? "UI Helper cancel-pick: seletor fechado"
            : "UI Helper cancel-pick: nenhum seletor ativo");
        WriteJson(ctx, 200, new { ok = true, closed });
    }

    private static bool TryCloseActivePick()
    {
        Form? owner;
        lock (PickGate)
        {
            owner = _activePickOwner;
        }

        if (owner is null || owner.IsDisposed)
        {
            Interlocked.Exchange(ref _pickInFlight, 0);
            return false;
        }

        try
        {
            if (owner.IsHandleCreated)
            {
                owner.BeginInvoke(new Action(() =>
                {
                    try
                    {
                        owner.Close();
                    }
                    catch
                    {
                        // ignore
                    }
                }));
            }
            else
            {
                try { owner.Close(); } catch { /* ignore */ }
            }
        }
        catch
        {
            Interlocked.Exchange(ref _pickInFlight, 0);
            return false;
        }

        return true;
    }

    private static void HandlePickFolder(HttpListenerContext ctx, string appPath)
    {
        // Se já houver seletor preso (ex.: cancelamento no browser), fecha e segue.
        if (Interlocked.CompareExchange(ref _pickInFlight, 1, 0) != 0)
        {
            DesktopLog.Write(appPath, "UI Helper pick-folder: liberando seletor anterior");
            TryCloseActivePick();
            for (var i = 0; i < 40 && Volatile.Read(ref _pickInFlight) != 0; i++)
            {
                Thread.Sleep(50);
            }

            if (Interlocked.CompareExchange(ref _pickInFlight, 1, 0) != 0)
            {
                WriteJson(ctx, 409, new
                {
                    ok = false,
                    cancelled = false,
                    code = "PICK_IN_PROGRESS",
                    message = "Seletor anterior ainda está fechando. Clique de novo em alguns segundos.",
                });
                return;
            }
        }

        try
        {
            string? initialPath = null;
            string? description = null;

            try
            {
                using var reader = new StreamReader(ctx.Request.InputStream, ctx.Request.ContentEncoding);
                var body = reader.ReadToEnd();
                if (!string.IsNullOrWhiteSpace(body))
                {
                    using var doc = JsonDocument.Parse(body);
                    if (doc.RootElement.TryGetProperty("initialPath", out var initialProp)
                        && initialProp.ValueKind == JsonValueKind.String)
                    {
                        initialPath = initialProp.GetString();
                    }

                    if (doc.RootElement.TryGetProperty("description", out var descProp)
                        && descProp.ValueKind == JsonValueKind.String)
                    {
                        description = descProp.GetString();
                    }
                }
            }
            catch (Exception ex)
            {
                WriteJson(ctx, 400, new { ok = false, message = "JSON inválido: " + ex.Message });
                return;
            }

            var result = PickFolderSta(initialPath, description);
            if (result.Error is not null)
            {
                DesktopLog.Write(appPath, "UI Helper pick-folder erro: " + result.Error);
                WriteJson(ctx, 500, new { ok = false, message = result.Error });
                return;
            }

            if (result.Cancelled || string.IsNullOrWhiteSpace(result.Path))
            {
                WriteJson(ctx, 200, new { ok = false, cancelled = true });
                return;
            }

            WriteJson(ctx, 200, new { ok = true, cancelled = false, path = result.Path });
        }
        finally
        {
            lock (PickGate)
            {
                _activePickOwner = null;
            }

            Interlocked.Exchange(ref _pickInFlight, 0);
        }
    }

    private static PickResult PickFolderSta(string? initialPath, string? description)
    {
        string? path = null;
        var cancelled = true;
        string? error = null;

        var thread = new Thread(() =>
        {
            try
            {
                Application.EnableVisualStyles();

                var cleanInitial = SanitizePath(initialPath);
                var cleanDescription = string.IsNullOrWhiteSpace(description)
                    ? "Selecione a pasta"
                    : description.Replace("\"", "").Replace("'", "");

                ResolveDialogAnchor(out var cx, out var cy);
                var wa = Screen.FromPoint(new Point(cx, cy)).WorkingArea;

                // Owner visível na barra de tarefas — evita diálogo “invisível”
                // atrás do navegador (Opacity ~0 fazia parecer que o ERP travou).
                var owner = new Form
                {
                    Text = "Unitec ERP — selecione a pasta",
                    ShowInTaskbar = true,
                    FormBorderStyle = FormBorderStyle.FixedToolWindow,
                    MaximizeBox = false,
                    MinimizeBox = false,
                    ControlBox = true,
                    TopMost = true,
                    Width = 420,
                    Height = 120,
                    StartPosition = FormStartPosition.Manual,
                    Location = new Point(Math.Max(wa.Left + 40, cx - 210), Math.Max(wa.Top + 40, cy - 60)),
                    BackColor = Color.FromArgb(15, 23, 42),
                    ForeColor = Color.White,
                };
                var label = new Label
                {
                    Dock = DockStyle.Fill,
                    TextAlign = ContentAlignment.MiddleCenter,
                    Font = new Font("Segoe UI", 10f, FontStyle.Regular),
                    Text = "Escolha a pasta na janela do Windows.\nSe não aparecer, use Alt+Tab.",
                    Padding = new Padding(12),
                };
                owner.Controls.Add(label);

                using var dialog = new FolderBrowserDialog
                {
                    Description = cleanDescription,
                    ShowNewFolderButton = true,
                };

                try
                {
                    dialog.UseDescriptionForTitle = true;
                }
                catch
                {
                    // ignore em runtimes antigos
                }

                try
                {
                    dialog.AutoUpgradeEnabled = true;
                }
                catch
                {
                    // ignore em runtimes antigos
                }

                if (!string.IsNullOrWhiteSpace(cleanInitial) && Directory.Exists(cleanInitial))
                {
                    dialog.SelectedPath = cleanInitial;
                }

                lock (PickGate)
                {
                    _activePickOwner = owner;
                }

                owner.Show();
                Application.DoEvents();
                try
                {
                    NativeMethods.AllowSetForegroundWindow(NativeMethods.ASFW_ANY);
                    NativeMethods.SetForegroundWindow(owner.Handle);
                    owner.Activate();
                    owner.BringToFront();
                }
                catch
                {
                    // ignore
                }

                try
                {
                    var result = dialog.ShowDialog(owner);
                    if (result == DialogResult.OK && !string.IsNullOrWhiteSpace(dialog.SelectedPath))
                    {
                        path = dialog.SelectedPath.Replace('/', '\\');
                        cancelled = false;
                    }
                }
                finally
                {
                    lock (PickGate)
                    {
                        if (ReferenceEquals(_activePickOwner, owner))
                        {
                            _activePickOwner = null;
                        }
                    }

                    try
                    {
                        if (!owner.IsDisposed)
                        {
                            owner.Close();
                            owner.Dispose();
                        }
                    }
                    catch
                    {
                        // ignore
                    }
                }
            }
            catch (Exception ex)
            {
                error = ex.Message;
            }
        });

        thread.SetApartmentState(ApartmentState.STA);
        thread.IsBackground = true;
        thread.Start();

        if (!thread.Join(TimeSpan.FromMinutes(5)))
        {
            return new PickResult(null, true, "Timeout no seletor de pasta.");
        }

        return new PickResult(path, cancelled, error);
    }

    private static void ResolveDialogAnchor(out int cx, out int cy)
    {
        cx = 0;
        cy = 0;

        var fg = NativeMethods.GetForegroundWindow();
        if (fg != IntPtr.Zero
            && NativeMethods.GetWindowRect(fg, out var rect))
        {
            cx = (rect.Left + rect.Right) / 2;
            cy = (rect.Top + rect.Bottom) / 2;
        }

        if (cx == 0 && cy == 0)
        {
            var pt = Cursor.Position;
            cx = pt.X;
            cy = pt.Y;
        }

        var screen = Screen.FromPoint(new Point(cx, cy));
        var wa = screen.WorkingArea;
        if (cx < wa.Left || cx >= wa.Right || cy < wa.Top || cy >= wa.Bottom)
        {
            cx = wa.Left + (wa.Width / 2);
            cy = wa.Top + (wa.Height / 2);
        }
    }

    private static string? SanitizePath(string? path)
    {
        if (string.IsNullOrWhiteSpace(path))
        {
            return null;
        }

        return path.Replace("\"", "").Replace("'", "").Trim();
    }

    private static void WriteJson(HttpListenerContext ctx, int status, object payload)
    {
        var json = JsonSerializer.Serialize(payload);
        var bytes = Encoding.UTF8.GetBytes(json);
        WriteCors(ctx.Response);
        ctx.Response.StatusCode = status;
        ctx.Response.ContentType = "application/json; charset=utf-8";
        ctx.Response.ContentEncoding = Encoding.UTF8;
        ctx.Response.ContentLength64 = bytes.Length;
        ctx.Response.OutputStream.Write(bytes, 0, bytes.Length);
        ctx.Response.Close();
    }

    private static void WriteCors(HttpListenerResponse response)
    {
        response.Headers["Access-Control-Allow-Origin"] = "*";
        response.Headers["Access-Control-Allow-Methods"] = "GET, POST, OPTIONS";
        response.Headers["Access-Control-Allow-Headers"] = "Content-Type, Accept";
        // Chrome Private Network Access (página do ERP → loopback do helper).
        response.Headers["Access-Control-Allow-Private-Network"] = "true";
    }

    private readonly record struct PickResult(string? Path, bool Cancelled, string? Error);

    private static class HttpMethods
    {
        public static bool IsGet(string? method)
            => string.Equals(method, "GET", StringComparison.OrdinalIgnoreCase);

        public static bool IsPost(string? method)
            => string.Equals(method, "POST", StringComparison.OrdinalIgnoreCase);

        public static bool IsOptions(string? method)
            => string.Equals(method, "OPTIONS", StringComparison.OrdinalIgnoreCase);
    }

    private static class NativeMethods
    {
        public const int ASFW_ANY = -1;

        [DllImport("user32.dll")]
        public static extern IntPtr GetForegroundWindow();

        [DllImport("user32.dll")]
        public static extern bool SetForegroundWindow(IntPtr hWnd);

        [DllImport("user32.dll")]
        public static extern bool AllowSetForegroundWindow(int dwProcessId);

        [DllImport("user32.dll")]
        public static extern bool GetWindowRect(IntPtr hWnd, out Rect lpRect);

        [StructLayout(LayoutKind.Sequential)]
        public struct Rect
        {
            public int Left;
            public int Top;
            public int Right;
            public int Bottom;
        }
    }
}
