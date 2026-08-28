using System.IO.Compression;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace Unitec.ErpCommon;

/// <summary>
/// Baixa Unitec-ERP-Update.zip (GitHub Releases), valida SHA256, extrai em atualizacao/.
/// Login pergunta Sim/Não para aplicar.
/// </summary>
public static class UpdateCheckService
{
    private const string DefaultZipUrl =
        "https://github.com/unitecnologia/unitec-erp-web/releases/download/update/Unitec-ERP-Update.zip";

    private static readonly HttpClient Http = CreateClient();
    private static int _running;

    private static HttpClient CreateClient()
    {
        var c = new HttpClient { Timeout = TimeSpan.FromMinutes(60) };
        c.DefaultRequestHeaders.UserAgent.ParseAdd("UnitecErpServer/1.0");
        return c;
    }

    public static string AtualizacaoDir(string appPath)
        => Path.Combine(appPath, "atualizacao");

    public static string ReadyPath(string appPath)
        => Path.Combine(AtualizacaoDir(appPath), "ready.json");

    public static string? ReadInstalledVersion(string appPath)
    {
        var config = Path.Combine(appPath, "config", "unitec.php");
        if (!File.Exists(config))
        {
            return null;
        }

        try
        {
            var text = File.ReadAllText(config);
            var m = Regex.Match(text, @"['""]versao['""]\s*=>\s*['""]([^'""]+)['""]", RegexOptions.IgnoreCase);
            return m.Success ? m.Groups[1].Value.Trim() : null;
        }
        catch
        {
            return null;
        }
    }

    public static string ResolveZipUrl(string appPath)
    {
        var config = Path.Combine(appPath, "config", "unitec.php");
        if (File.Exists(config))
        {
            try
            {
                var text = File.ReadAllText(config);
                var m = Regex.Match(
                    text,
                    @"['""]update_download_url['""]\s*=>\s*env\(\s*['""][^'""]+['""]\s*,\s*['""]([^'""]+)['""]\s*\)",
                    RegexOptions.IgnoreCase | RegexOptions.Singleline);
                if (m.Success)
                {
                    var url = m.Groups[1].Value.Trim();
                    if (LooksLikeZipUrl(url))
                    {
                        return url;
                    }
                }

                m = Regex.Match(
                    text,
                    @"['""]update_download_url['""]\s*=>\s*['""]([^'""]+)['""]",
                    RegexOptions.IgnoreCase);
                if (m.Success)
                {
                    var url = m.Groups[1].Value.Trim();
                    if (LooksLikeZipUrl(url))
                    {
                        return url;
                    }
                }
            }
            catch
            {
                // fall through
            }
        }

        return DefaultZipUrl;
    }

    private static bool LooksLikeZipUrl(string url)
        => url.EndsWith(".zip", StringComparison.OrdinalIgnoreCase)
           || url.Contains("/Unitec-ERP-Update.zip", StringComparison.OrdinalIgnoreCase);

    public static void CheckAndDownloadAsync(string appPath)
    {
        if (Interlocked.CompareExchange(ref _running, 1, 0) != 0)
        {
            DesktopLog.Write(appPath, "UpdateCheck: ja em andamento — ignorado.");
            return;
        }

        _ = Task.Run(() =>
        {
            try
            {
                CheckAndDownload(appPath);
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "UpdateCheck erro: " + ex.Message);
            }
            finally
            {
                Interlocked.Exchange(ref _running, 0);
            }
        });
    }

    public static void CheckAndDownload(string appPath)
    {
        var zipUrl = ResolveZipUrl(appPath);
        DesktopLog.Write(appPath, "UpdateCheck: canal ZIP " + zipUrl);

        var installed = ReadInstalledVersion(appPath) ?? "0";
        var remoteVersion = ResolveRemoteVersion(appPath, zipUrl);

        if (!string.IsNullOrWhiteSpace(remoteVersion)
            && CompareVersions(remoteVersion, installed) <= 0)
        {
            DesktopLog.Write(appPath, $"UpdateCheck: local {installed} ja atualizado (remoto {remoteVersion})");
            return;
        }

        if (!string.IsNullOrWhiteSpace(remoteVersion)
            && IsAtualizacaoReady(appPath, remoteVersion))
        {
            DesktopLog.Write(appPath, "UpdateCheck: atualizacao/ ja pronta para " + remoteVersion);
            return;
        }

        var expected = FetchIntegrity(appPath, zipUrl);
        DesktopLog.Write(appPath,
            $"UpdateCheck: baixando ZIP (size={expected.Size}, sha={expected.Sha256[..12]}…) remoto={remoteVersion ?? "?"}");

        var updatesDir = Path.Combine(appPath, "storage", "app", "private", "updates");
        Directory.CreateDirectory(updatesDir);
        var partial = Path.Combine(updatesDir, "Unitec-ERP-Update.zip.partial");
        var zipPath = Path.Combine(updatesDir, "Unitec-ERP-Update.zip");

        DownloadResumable(appPath, zipUrl, partial, expected);
        AssertIntegrity(partial, expected);

        if (File.Exists(zipPath))
        {
            TryDeleteFile(zipPath);
        }

        File.Move(partial, zipPath);

        DesktopLog.Write(appPath, "UpdateCheck: ZIP OK — extraindo para atualizacao/");
        var extractedVersion = ExtractZipToAtualizacao(appPath, zipPath);

        TryDeleteFile(zipPath);
        TryDeleteFile(partial);

        DesktopLog.Write(appPath,
            $"UpdateCheck: atualizacao/ COMPLETA v{extractedVersion ?? remoteVersion ?? "ok"} (ZIP removido)");
    }

    /// <summary>
    /// Extrai ZIP para atualizacao/, grava ready.json, nao deixa o ZIP na pasta.
    /// </summary>
    public static string? ExtractZipToAtualizacao(string appPath, string zipPath)
    {
        if (!File.Exists(zipPath))
        {
            throw new FileNotFoundException("ZIP nao encontrado.", zipPath);
        }

        var dest = AtualizacaoDir(appPath);
        var temp = Path.Combine(Path.GetTempPath(), "unitec-upd-" + Guid.NewGuid().ToString("N"));
        Directory.CreateDirectory(temp);

        try
        {
            ZipFile.ExtractToDirectory(zipPath, temp, overwriteFiles: true);
            var root = temp;
            var nested = Directory.GetDirectories(temp);
            if (nested.Length == 1 && File.Exists(Path.Combine(nested[0], "artisan")))
            {
                root = nested[0];
            }
            else if (!File.Exists(Path.Combine(temp, "artisan")))
            {
                // ZIP com pasta unitec-erp-web/
                var candidate = nested.FirstOrDefault(d => File.Exists(Path.Combine(d, "artisan")));
                if (candidate != null)
                {
                    root = candidate;
                }
            }

            if (!File.Exists(Path.Combine(root, "artisan"))
                || !File.Exists(Path.Combine(root, "vendor", "autoload.php")))
            {
                throw new InvalidOperationException("ZIP invalido: falta artisan/vendor apos extracao.");
            }

            ClearAtualizacaoContents(dest);
            Directory.CreateDirectory(dest);
            CopyDirectory(root, dest);

            if (!File.Exists(Path.Combine(dest, "vendor", "autoload.php"))
                || !File.Exists(Path.Combine(dest, "config", "unitec.php"))
                || !File.Exists(Path.Combine(dest, "artisan")))
            {
                throw new InvalidOperationException("Extracao incompleta em atualizacao/.");
            }

            var ver = ReadInstalledVersion(dest) ?? "desconhecida";
            var ready =
                "{"
                + "\"ready\":true,"
                + $"\"version\":\"{Escape(ver)}\","
                + $"\"deposited_at\":\"{DateTime.UtcNow:O}\","
                + "\"source\":\"zip\""
                + "}";
            File.WriteAllText(ReadyPath(appPath), ready, Encoding.UTF8);
            return ver;
        }
        finally
        {
            try { Directory.Delete(temp, recursive: true); } catch { /* ignore */ }
        }
    }

    private static bool IsAtualizacaoReady(string appPath, string remoteVersion)
    {
        if (!File.Exists(ReadyPath(appPath)))
        {
            return false;
        }

        try
        {
            using var doc = JsonDocument.Parse(File.ReadAllText(ReadyPath(appPath)));
            if (!doc.RootElement.TryGetProperty("ready", out var ready) || ready.ValueKind != JsonValueKind.True)
            {
                return false;
            }

            if (!doc.RootElement.TryGetProperty("version", out var v)
                || !string.Equals(v.GetString()?.Trim(), remoteVersion, StringComparison.OrdinalIgnoreCase))
            {
                return false;
            }
        }
        catch
        {
            return false;
        }

        var dest = AtualizacaoDir(appPath);
        return File.Exists(Path.Combine(dest, "vendor", "autoload.php"))
            && File.Exists(Path.Combine(dest, "config", "unitec.php"))
            && File.Exists(Path.Combine(dest, "artisan"));
    }

    private static string? ResolveRemoteVersion(string appPath, string zipUrl)
    {
        try
        {
            using var req = new HttpRequestMessage(HttpMethod.Get,
                "https://api.github.com/repos/unitecnologia/unitec-erp-web/releases/tags/update");
            req.Headers.Accept.ParseAdd("application/vnd.github+json");
            using var resp = Http.Send(req);
            if (!resp.IsSuccessStatusCode)
            {
                DesktopLog.Write(appPath, $"UpdateCheck: API release HTTP {(int)resp.StatusCode}");
                return null;
            }

            var json = resp.Content.ReadAsStringAsync().GetAwaiter().GetResult();
            using var doc = JsonDocument.Parse(json);
            var name = "";
            if (doc.RootElement.TryGetProperty("name", out var n))
            {
                name = n.GetString() ?? "";
            }

            if (string.IsNullOrWhiteSpace(name) && doc.RootElement.TryGetProperty("body", out var body))
            {
                name = body.GetString() ?? "";
            }

            var m = Regex.Match(name, @"(\d+\.\d+\.\d+\.\d+)");
            if (m.Success)
            {
                return m.Groups[1].Value;
            }
        }
        catch (Exception ex)
        {
            DesktopLog.Write(appPath, "UpdateCheck: versao remota indisponivel — " + ex.Message);
        }

        // Fallback: se URL nao e github padrao, ainda baixa (sem comparar versao).
        _ = zipUrl;
        return null;
    }

    private sealed record Integrity(string Sha256, long Size);

    private static Integrity FetchIntegrity(string appPath, string zipUrl)
    {
        var shaUrl = zipUrl.EndsWith(".zip", StringComparison.OrdinalIgnoreCase)
            ? zipUrl + ".sha256"
            : zipUrl.TrimEnd('/') + ".sha256";

        DesktopLog.Write(appPath, "UpdateCheck: assinatura " + shaUrl);
        using var resp = Http.GetAsync(shaUrl).GetAwaiter().GetResult();
        resp.EnsureSuccessStatusCode();
        var text = resp.Content.ReadAsStringAsync().GetAwaiter().GetResult();
        if (string.IsNullOrWhiteSpace(text))
        {
            throw new InvalidOperationException("Arquivo .sha256 vazio.");
        }

        var sha256 = "";
        long size = 0;
        foreach (var rawLine in text.Split('\n'))
        {
            var line = rawLine.Trim();
            if (line.StartsWith("size=", StringComparison.OrdinalIgnoreCase)
                && long.TryParse(line.AsSpan(5), out var s))
            {
                size = s;
                continue;
            }

            var m = Regex.Match(line, @"^([a-fA-F0-9]{64})\b");
            if (m.Success)
            {
                sha256 = m.Groups[1].Value.ToLowerInvariant();
            }
        }

        if (sha256.Length != 64)
        {
            throw new InvalidOperationException("SHA256 invalido no sidecar.");
        }

        if (size <= 0)
        {
            // Fallback HEAD no ZIP
            using var head = new HttpRequestMessage(HttpMethod.Head, zipUrl);
            using var headResp = Http.Send(head);
            if (headResp.Content.Headers.ContentLength is long cl && cl > 0)
            {
                size = cl;
            }
        }

        if (size <= 1024)
        {
            throw new InvalidOperationException("Tamanho do ZIP invalido (size=" + size + ").");
        }

        return new Integrity(sha256, size);
    }

    private static void DownloadResumable(string appPath, string url, string partialPath, Integrity expected)
    {
        long existing = 0;
        if (File.Exists(partialPath))
        {
            existing = new FileInfo(partialPath).Length;
            if (existing > expected.Size)
            {
                TryDeleteFile(partialPath);
                existing = 0;
            }
            else if (existing == expected.Size)
            {
                DesktopLog.Write(appPath, "UpdateCheck: partial ja completo — validando");
                return;
            }
        }

        var parent = Path.GetDirectoryName(partialPath);
        if (!string.IsNullOrWhiteSpace(parent))
        {
            Directory.CreateDirectory(parent);
        }

        using var req = new HttpRequestMessage(HttpMethod.Get, url);
        if (existing > 0)
        {
            req.Headers.Range = new RangeHeaderValue(existing, null);
            DesktopLog.Write(appPath, $"UpdateCheck: retomando download em {existing} bytes");
        }

        using var resp = Http.Send(req, HttpCompletionOption.ResponseHeadersRead);
        if (existing > 0 && resp.StatusCode == System.Net.HttpStatusCode.OK)
        {
            // Servidor ignorou Range — recomeça
            TryDeleteFile(partialPath);
            existing = 0;
            DownloadResumable(appPath, url, partialPath, expected);
            return;
        }

        resp.EnsureSuccessStatusCode();

        using var net = resp.Content.ReadAsStream();
        using var fs = new FileStream(
            partialPath,
            existing > 0 ? FileMode.Append : FileMode.Create,
            FileAccess.Write,
            FileShare.None,
            1024 * 128);

        var buffer = new byte[1024 * 128];
        long written = existing;
        int read;
        var lastLog = DateTime.UtcNow;
        while ((read = net.Read(buffer, 0, buffer.Length)) > 0)
        {
            fs.Write(buffer, 0, read);
            written += read;
            if ((DateTime.UtcNow - lastLog).TotalSeconds >= 10)
            {
                var pct = expected.Size > 0 ? (int)(written * 100 / expected.Size) : 0;
                DesktopLog.Write(appPath, $"UpdateCheck: download {pct}% ({written}/{expected.Size})");
                lastLog = DateTime.UtcNow;
            }
        }

        if (written != expected.Size)
        {
            throw new InvalidOperationException($"Download incompleto: {written} != {expected.Size}");
        }
    }

    private static void AssertIntegrity(string path, Integrity expected)
    {
        var info = new FileInfo(path);
        if (!info.Exists || info.Length != expected.Size)
        {
            throw new InvalidOperationException(
                $"Tamanho divergente (esp={expected.Size} calc={info.Length})");
        }

        using var stream = File.OpenRead(path);
        var hash = Convert.ToHexString(SHA256.HashData(stream)).ToLowerInvariant();
        if (!string.Equals(hash, expected.Sha256, StringComparison.OrdinalIgnoreCase))
        {
            throw new InvalidOperationException($"SHA256 divergente (esp={expected.Sha256} calc={hash})");
        }
    }

    public static int CompareVersions(string a, string b)
    {
        var pa = a.Split('.', StringSplitOptions.RemoveEmptyEntries).Select(s => int.TryParse(s, out var n) ? n : 0).ToArray();
        var pb = b.Split('.', StringSplitOptions.RemoveEmptyEntries).Select(s => int.TryParse(s, out var n) ? n : 0).ToArray();
        var len = Math.Max(pa.Length, pb.Length);
        for (var i = 0; i < len; i++)
        {
            var va = i < pa.Length ? pa[i] : 0;
            var vb = i < pb.Length ? pb[i] : 0;
            if (va != vb)
            {
                return va.CompareTo(vb);
            }
        }

        return 0;
    }

    private static void ClearAtualizacaoContents(string destRoot)
    {
        if (!Directory.Exists(destRoot))
        {
            return;
        }

        foreach (var file in Directory.GetFiles(destRoot))
        {
            try { File.Delete(file); } catch { /* ignore */ }
        }

        foreach (var dir in Directory.GetDirectories(destRoot))
        {
            try { Directory.Delete(dir, recursive: true); } catch { /* ignore */ }
        }
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

    private static void TryDeleteFile(string path)
    {
        try
        {
            if (File.Exists(path))
            {
                File.Delete(path);
            }
        }
        catch
        {
            // ignore
        }
    }

    private static string Escape(string value)
        => value.Replace("\\", "\\\\").Replace("\"", "\\\"");
}
