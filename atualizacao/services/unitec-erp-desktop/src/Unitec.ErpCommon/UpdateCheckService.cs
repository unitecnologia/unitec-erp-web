using System.Net;
using System.Net.Http.Headers;
using System.Security.Cryptography;
using System.Text;
using System.Text.Json;
using System.Text.RegularExpressions;

namespace Unitec.ErpCommon;

/// <summary>
/// Verifica manifest remoto (sem ZIP) e baixa arquivos soltos para atualizacao/.
/// Destino = appPath\atualizacao\ igualzinho ao GitHub update-files/atualizacao/.
/// </summary>
public static class UpdateCheckService
{
    private static readonly HttpClient Http = CreateClient();
    private static int _running;

    private static HttpClient CreateClient()
    {
        var c = new HttpClient { Timeout = TimeSpan.FromMinutes(30) };
        c.DefaultRequestHeaders.UserAgent.ParseAdd("UnitecErpServer/1.0");
        c.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("*/*"));
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

    public static string ResolveManifestUrl(string appPath)
    {
        var config = Path.Combine(appPath, "config", "unitec.php");
        if (File.Exists(config))
        {
            try
            {
                var text = File.ReadAllText(config);
                var m = Regex.Match(
                    text,
                    @"['""]update_manifest_url['""]\s*=>\s*env\(\s*['""][^'""]+['""]\s*,\s*['""]([^'""]+)['""]\s*\)",
                    RegexOptions.IgnoreCase | RegexOptions.Singleline);
                if (m.Success)
                {
                    return m.Groups[1].Value.Trim();
                }

                m = Regex.Match(
                    text,
                    @"['""]update_manifest_url['""]\s*=>\s*['""]([^'""]+)['""]",
                    RegexOptions.IgnoreCase);
                if (m.Success)
                {
                    return m.Groups[1].Value.Trim();
                }
            }
            catch
            {
                // fall through
            }
        }

        return "https://raw.githubusercontent.com/unitecnologia/unitec-erp-web/update-files/atualizacao/manifest.json";
    }

    /// <summary>URLs tentadas em ordem (pasta atualizacao no GitHub + compat raiz).</summary>
    private static IEnumerable<string> ManifestUrlCandidates(string appPath)
    {
        var primary = ResolveManifestUrl(appPath);
        yield return primary;

        const string folderUrl =
            "https://raw.githubusercontent.com/unitecnologia/unitec-erp-web/update-files/atualizacao/manifest.json";
        const string rootUrl =
            "https://raw.githubusercontent.com/unitecnologia/unitec-erp-web/update-files/manifest.json";

        if (!string.Equals(primary, folderUrl, StringComparison.OrdinalIgnoreCase))
        {
            yield return folderUrl;
        }

        if (!string.Equals(primary, rootUrl, StringComparison.OrdinalIgnoreCase))
        {
            yield return rootUrl;
        }
    }

    /// <summary>
    /// Fire-and-forget seguro (não bloqueia o loop do serviço).
    /// </summary>
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
        string? json = null;
        string? usedManifestUrl = null;

        foreach (var manifestUrl in ManifestUrlCandidates(appPath))
        {
            DesktopLog.Write(appPath, "UpdateCheck: consultando " + manifestUrl);
            try
            {
                using var resp = Http.GetAsync(manifestUrl).GetAwaiter().GetResult();
                if (!resp.IsSuccessStatusCode)
                {
                    DesktopLog.Write(appPath, $"UpdateCheck: manifest HTTP {(int)resp.StatusCode} em {manifestUrl}");
                    continue;
                }

                json = resp.Content.ReadAsStringAsync().GetAwaiter().GetResult();
                usedManifestUrl = manifestUrl;
                break;
            }
            catch (Exception ex)
            {
                DesktopLog.Write(appPath, "UpdateCheck: falha manifest " + manifestUrl + " — " + ex.Message);
            }
        }

        if (string.IsNullOrWhiteSpace(json) || string.IsNullOrWhiteSpace(usedManifestUrl))
        {
            DesktopLog.Write(appPath, "UpdateCheck: nenhum manifest acessivel.");
            return;
        }

        using var doc = JsonDocument.Parse(json);
        var root = doc.RootElement;

        var remoteVersion = root.TryGetProperty("version", out var verEl)
            ? (verEl.GetString() ?? "").Trim()
            : "";
        if (string.IsNullOrWhiteSpace(remoteVersion))
        {
            DesktopLog.Write(appPath, "UpdateCheck: manifest sem version");
            return;
        }

        var installed = ReadInstalledVersion(appPath) ?? "0";
        if (CompareVersions(remoteVersion, installed) <= 0)
        {
            DesktopLog.Write(appPath, $"UpdateCheck: local {installed} ja atualizado (remoto {remoteVersion})");
            return;
        }

        var destRoot = AtualizacaoDir(appPath);
        Directory.CreateDirectory(destRoot);

        // Já baixado completo?
        if (IsAtualizacaoComplete(appPath, remoteVersion))
        {
            DesktopLog.Write(appPath, "UpdateCheck: atualizacao/ ja pronta para " + remoteVersion);
            return;
        }

        var baseUrl = root.TryGetProperty("base_url", out var baseEl)
            ? (baseEl.GetString() ?? "").Trim().TrimEnd('/')
            : "";

        if (string.IsNullOrWhiteSpace(baseUrl))
        {
            baseUrl = "https://raw.githubusercontent.com/unitecnologia/unitec-erp-web/update-files/atualizacao";
        }

        if (!root.TryGetProperty("files", out var filesEl) || filesEl.ValueKind != JsonValueKind.Array)
        {
            DesktopLog.Write(appPath, "UpdateCheck: manifest invalido (files)");
            return;
        }

        // Nova versão remota vs parcial local → limpa. Mesma versão → retoma por SHA.
        var localPartialVersion = ReadLocalAtualizacaoVersion(destRoot);
        if (!string.Equals(localPartialVersion, remoteVersion, StringComparison.OrdinalIgnoreCase))
        {
            DesktopLog.Write(appPath,
                $"UpdateCheck: limpando atualizacao/ (local={localPartialVersion ?? "vazio"} -> remoto={remoteVersion})");
            ClearAtualizacaoContents(destRoot);
            Directory.CreateDirectory(destRoot);
        }
        else
        {
            TryDeleteFile(ReadyPath(appPath));
            DesktopLog.Write(appPath, "UpdateCheck: retomando download incompleto de " + remoteVersion);
        }

        // Grava manifest cedo para retomar na próxima rodada sem limpar.
        File.WriteAllText(Path.Combine(destRoot, "manifest.json"), json, Encoding.UTF8);

        var total = filesEl.GetArrayLength();
        DesktopLog.Write(appPath,
            $"UpdateCheck: baixando {remoteVersion} ({total} arquivos) de {baseUrl} -> {destRoot}");

        var ok = 0;
        var skipped = 0;
        var fail = 0;
        var processed = 0;

        foreach (var file in filesEl.EnumerateArray())
        {
            var rel = file.TryGetProperty("path", out var p) ? (p.GetString() ?? "").Replace('\\', '/').Trim('/') : "";
            if (string.IsNullOrWhiteSpace(rel) || rel is "ready.json" or "manifest.json")
            {
                continue;
            }

            var sha = file.TryGetProperty("sha256", out var s) ? (s.GetString() ?? "").Trim().ToLowerInvariant() : "";
            var url = baseUrl + "/" + string.Join("/", rel.Split('/').Select(Uri.EscapeDataString));
            var target = Path.Combine(destRoot, rel.Replace('/', Path.DirectorySeparatorChar));
            processed++;

            try
            {
                if (FileMatchesSha(target, sha))
                {
                    ok++;
                    skipped++;
                }
                else
                {
                    var parent = Path.GetDirectoryName(target);
                    if (!string.IsNullOrWhiteSpace(parent))
                    {
                        Directory.CreateDirectory(parent);
                    }

                    DownloadFile(appPath, url, target, sha);
                    ok++;
                }

                if (processed % 500 == 0)
                {
                    DesktopLog.Write(appPath,
                        $"UpdateCheck: progresso {ok}/{total} (skip={skipped} fail={fail}) em atualizacao/");
                }

                // Evita rate limit do raw.githubusercontent.com em pacotes grandes.
                if ((ok - skipped) > 0 && (ok - skipped) % 40 == 0)
                {
                    Thread.Sleep(250);
                }
            }
            catch (Exception ex)
            {
                fail++;
                if (fail <= 40 || fail % 50 == 0)
                {
                    DesktopLog.Write(appPath, $"UpdateCheck falha ({fail}) {rel}: {ex.Message}");
                }

                // Continua a lista inteira — não aborta por contagem de erros.
                Thread.Sleep(Math.Min(5000, 300 + (fail * 20)));
            }
        }

        var hasVendor = File.Exists(Path.Combine(destRoot, "vendor", "autoload.php"));
        var hasConfig = File.Exists(Path.Combine(destRoot, "config", "unitec.php"));
        var hasPublic = File.Exists(Path.Combine(destRoot, "public", "index.php"));
        var hasBin = Directory.Exists(Path.Combine(destRoot, "bin"));
        var expectedMin = Math.Max(1, (int)Math.Ceiling(total * 0.995));

        if (ok < expectedMin || !hasVendor || !hasConfig || !hasPublic || !hasBin)
        {
            DesktopLog.Write(appPath,
                $"UpdateCheck INCOMPLETO ok={ok} total={total} fail={fail} skip={skipped} "
                + $"vendor={hasVendor} config={hasConfig} public={hasPublic} bin={hasBin} — SEM ready.json. "
                + "Pasta parcial em atualizacao/ (proxima rodada retoma por sha).");
            throw new InvalidOperationException(
                $"Download incompleto para atualizacao/ (ok={ok}/{total}, fail={fail}).");
        }

        var readyJson =
            "{"
            + "\"ready\":true,"
            + $"\"version\":\"{Escape(remoteVersion)}\","
            + $"\"deposited_at\":\"{DateTime.UtcNow:O}\","
            + $"\"files_ok\":{ok},"
            + $"\"files_fail\":{fail},"
            + $"\"files_skipped\":{skipped},"
            + $"\"manifest_url\":\"{Escape(usedManifestUrl)}\""
            + "}";
        File.WriteAllText(ReadyPath(appPath), readyJson, Encoding.UTF8);

        DesktopLog.Write(appPath,
            $"UpdateCheck: atualizacao/ COMPLETA v{remoteVersion} (ok={ok} fail={fail} skip={skipped})");
    }

    private static bool IsAtualizacaoComplete(string appPath, string remoteVersion)
    {
        var readyPath = ReadyPath(appPath);
        if (!File.Exists(readyPath))
        {
            return false;
        }

        try
        {
            using var readyDoc = JsonDocument.Parse(File.ReadAllText(readyPath));
            if (!readyDoc.RootElement.TryGetProperty("version", out var rv)
                || !string.Equals(rv.GetString()?.Trim(), remoteVersion, StringComparison.OrdinalIgnoreCase)
                || !readyDoc.RootElement.TryGetProperty("ready", out var ready)
                || ready.ValueKind != JsonValueKind.True)
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
            && File.Exists(Path.Combine(dest, "public", "index.php"))
            && Directory.Exists(Path.Combine(dest, "bin"));
    }

    private static string? ReadLocalAtualizacaoVersion(string destRoot)
    {
        var manifest = Path.Combine(destRoot, "manifest.json");
        if (!File.Exists(manifest))
        {
            return null;
        }

        try
        {
            using var doc = JsonDocument.Parse(File.ReadAllText(manifest));
            if (doc.RootElement.TryGetProperty("version", out var v))
            {
                var s = (v.GetString() ?? "").Trim();
                return string.IsNullOrWhiteSpace(s) ? null : s;
            }
        }
        catch
        {
            // ignore
        }

        return null;
    }

    private static bool FileMatchesSha(string path, string expectedSha)
    {
        if (string.IsNullOrWhiteSpace(expectedSha) || !File.Exists(path))
        {
            return false;
        }

        try
        {
            var bytes = File.ReadAllBytes(path);
            var hash = Convert.ToHexString(SHA256.HashData(bytes)).ToLowerInvariant();
            return string.Equals(hash, expectedSha, StringComparison.OrdinalIgnoreCase);
        }
        catch
        {
            return false;
        }
    }

    private static void DownloadFile(string appPath, string url, string target, string expectedSha)
    {
        Exception? last = null;
        const int maxAttempts = 8;

        for (var attempt = 1; attempt <= maxAttempts; attempt++)
        {
            try
            {
                using var resp = Http.GetAsync(url).GetAwaiter().GetResult();
                if (resp.StatusCode == HttpStatusCode.TooManyRequests
                    || (int)resp.StatusCode == 403
                    || (int)resp.StatusCode == 429)
                {
                    var waitMs = ReadRetryAfterMs(resp) ?? (1000 * attempt * attempt);
                    waitMs = Math.Clamp(waitMs, 1000, 60000);
                    DesktopLog.Write(appPath,
                        $"UpdateCheck: rate limit HTTP {(int)resp.StatusCode} — pausa {waitMs}ms ({attempt}/{maxAttempts})");
                    Thread.Sleep(waitMs);
                    last = new HttpRequestException($"HTTP {(int)resp.StatusCode} rate limit");
                    continue;
                }

                resp.EnsureSuccessStatusCode();
                var bytes = resp.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult();
                if (!string.IsNullOrWhiteSpace(expectedSha))
                {
                    var hash = Convert.ToHexString(SHA256.HashData(bytes)).ToLowerInvariant();
                    if (!string.Equals(hash, expectedSha, StringComparison.OrdinalIgnoreCase))
                    {
                        throw new InvalidOperationException($"SHA256 divergente (esp={expectedSha} calc={hash})");
                    }
                }

                File.WriteAllBytes(target, bytes);
                return;
            }
            catch (Exception ex) when (attempt < maxAttempts)
            {
                last = ex;
                Thread.Sleep(Math.Min(30000, 500 * attempt * attempt));
            }
            catch (Exception ex)
            {
                last = ex;
            }
        }

        throw last ?? new InvalidOperationException("Falha ao baixar " + url);
    }

    private static int? ReadRetryAfterMs(HttpResponseMessage resp)
    {
        if (resp.Headers.RetryAfter?.Delta is TimeSpan delta)
        {
            return (int)Math.Clamp(delta.TotalMilliseconds, 1000, 120000);
        }

        if (resp.Headers.RetryAfter?.Date is DateTimeOffset date)
        {
            var ms = (int)(date - DateTimeOffset.UtcNow).TotalMilliseconds;
            return Math.Clamp(ms, 1000, 120000);
        }

        return null;
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

    /// <summary>
    /// Limpa o conteudo de atualizacao/ sem apagar a pasta no meio de um download.
    /// </summary>
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
