using System.Net.Sockets;
using System.Text.Json;

namespace Unitec.ErpCommon;

public sealed class HealthResult
{
    public bool PortOpen { get; init; }
    public bool HttpResponded { get; init; }
    public int? StatusCode { get; init; }
    public bool Ok { get; init; }
    public string? Version { get; init; }
    public string Message { get; init; } = string.Empty;

    public string Kind =>
        !PortOpen ? "port_closed" :
        !HttpResponded ? "http_unreachable" :
        Ok ? "healthy" :
        "app_error";
}

public static class HealthClient
{
    public static async Task<HealthResult> ProbeAsync(
        string baseUrl = ErpPaths.DefaultAppUrl,
        int timeoutMs = 1500,
        CancellationToken cancellationToken = default)
    {
        // HTTP primeiro (nao depender do TcpClient — no Windows ja deu falso "porta fechada"
        // com php artisan serve imprimindo "Server running").
        var urls = BuildProbeUrls(baseUrl);
        Exception? lastHttpError = null;

        foreach (var url in urls)
        {
            try
            {
                using var cts = CancellationTokenSource.CreateLinkedTokenSource(cancellationToken);
                cts.CancelAfter(timeoutMs);
                using var client = new HttpClient { Timeout = TimeSpan.FromMilliseconds(timeoutMs) };
                using var response = await client.GetAsync(url, cts.Token).ConfigureAwait(false);
                var body = await response.Content.ReadAsStringAsync(cts.Token).ConfigureAwait(false);
                var code = (int)response.StatusCode;

                // Qualquer 2xx em /api/health = servidor no ar (nao exigir JSON status=ok).
                if (response.IsSuccessStatusCode)
                {
                    string? version = null;
                    try
                    {
                        using var doc = JsonDocument.Parse(body);
                        if (doc.RootElement.TryGetProperty("version", out var ver))
                        {
                            version = ver.GetString();
                        }
                    }
                    catch
                    {
                        // Body nao-JSON (ex.: HTML) — ainda assim 2xx conta como OK.
                    }

                    return new HealthResult
                    {
                        PortOpen = true,
                        HttpResponded = true,
                        StatusCode = code,
                        Ok = true,
                        Version = version,
                        Message = "Servidor saudavel.",
                    };
                }

                return new HealthResult
                {
                    PortOpen = true,
                    HttpResponded = true,
                    StatusCode = code,
                    Ok = false,
                    Message = $"Aplicacao respondeu HTTP {code} em {ErpPaths.HealthPath}.",
                };
            }
            catch (Exception ex) when (ex is not OperationCanceledException || cancellationToken.IsCancellationRequested)
            {
                if (cancellationToken.IsCancellationRequested)
                {
                    throw;
                }

                lastHttpError = ex;
            }
            catch (OperationCanceledException) when (!cancellationToken.IsCancellationRequested)
            {
                // Timeout HTTP: porta pode estar aberta com app lenta no boot.
                if (IsPortOpen("127.0.0.1", ErpPaths.Port, Math.Min(800, timeoutMs))
                    || IsPortOpen("localhost", ErpPaths.Port, Math.Min(800, timeoutMs)))
                {
                    return new HealthResult
                    {
                        PortOpen = true,
                        HttpResponded = false,
                        Ok = false,
                        Message = $"Porta {ErpPaths.Port} aberta, mas /api/health nao respondeu a tempo.",
                    };
                }

                lastHttpError = new TimeoutException("HTTP health timeout.");
            }
        }

        var portOpen = IsPortOpen("127.0.0.1", ErpPaths.Port, Math.Min(800, timeoutMs))
            || IsPortOpen("localhost", ErpPaths.Port, Math.Min(800, timeoutMs));

        if (portOpen)
        {
            return new HealthResult
            {
                PortOpen = true,
                HttpResponded = false,
                Ok = false,
                Message = $"Porta {ErpPaths.Port} aberta, mas HTTP falhou: {lastHttpError?.Message ?? "sem resposta"}",
            };
        }

        return new HealthResult
        {
            PortOpen = false,
            Message = $"Porta {ErpPaths.Port} fechada (servidor parado).",
        };
    }

    public static async Task<HealthResult> WaitHealthyAsync(
        string baseUrl = ErpPaths.DefaultAppUrl,
        int maxAttempts = 20,
        int delayMs = 500,
        CancellationToken cancellationToken = default)
    {
        HealthResult last = new() { Message = "Sem tentativas." };

        for (var i = 1; i <= maxAttempts; i++)
        {
            cancellationToken.ThrowIfCancellationRequested();
            last = await ProbeAsync(baseUrl, 2000, cancellationToken).ConfigureAwait(false);
            if (last.Ok)
            {
                return last;
            }

            await Task.Delay(delayMs, cancellationToken).ConfigureAwait(false);
        }

        return last;
    }

    private static string[] BuildProbeUrls(string baseUrl)
    {
        var path = ErpPaths.HealthPath;
        var list = new List<string>();
        var primary = (baseUrl ?? ErpPaths.DefaultAppUrl).TrimEnd('/') + path;
        list.Add(primary);

        var alt = $"http://localhost:{ErpPaths.Port}{path}";
        if (!string.Equals(primary, alt, StringComparison.OrdinalIgnoreCase))
        {
            list.Add(alt);
        }

        return list.ToArray();
    }

    private static bool IsPortOpen(string host, int port, int timeoutMs)
    {
        try
        {
            using var socket = new Socket(AddressFamily.InterNetwork, SocketType.Stream, ProtocolType.Tcp);
            var ar = socket.BeginConnect(host, port, null, null);
            if (!ar.AsyncWaitHandle.WaitOne(timeoutMs, true))
            {
                try { socket.Close(); } catch { /* ignore */ }
                return false;
            }

            socket.EndConnect(ar);
            return true;
        }
        catch
        {
            return false;
        }
    }
}
