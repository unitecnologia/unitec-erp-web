using System.Text.Json;

namespace Unitec.ErpUpdater;

internal sealed class UpdateStatusSnapshot
{
    public string State { get; init; } = "idle";
    public string Message { get; init; } = string.Empty;
    public string? Detail { get; init; }
    public string? Command { get; init; }
    public int Percent { get; init; }
    public string? StepLabel { get; init; }
    public Dictionary<string, int> StepProgress { get; init; } = new(StringComparer.OrdinalIgnoreCase);
}

internal static class UpdateStatusReader
{
    public static string StatusPath(string appPath) =>
        Path.Combine(appPath, "storage", "app", "private", "erp-update-status.json");

    public static UpdateStatusSnapshot? TryRead(string appPath)
    {
        var path = StatusPath(appPath);
        if (!File.Exists(path))
        {
            return null;
        }

        try
        {
            using var stream = File.Open(path, FileMode.Open, FileAccess.Read, FileShare.ReadWrite | FileShare.Delete);
            using var doc = JsonDocument.Parse(stream);
            var root = doc.RootElement;

            var steps = new Dictionary<string, int>(StringComparer.OrdinalIgnoreCase);
            if (root.TryGetProperty("step_progress", out var sp) && sp.ValueKind == JsonValueKind.Object)
            {
                foreach (var prop in sp.EnumerateObject())
                {
                    if (prop.Value.ValueKind == JsonValueKind.Number)
                    {
                        steps[prop.Name] = prop.Value.GetInt32();
                    }
                }
            }

            return new UpdateStatusSnapshot
            {
                State = root.TryGetProperty("state", out var state) ? state.GetString() ?? "idle" : "idle",
                Message = root.TryGetProperty("message", out var msg) ? msg.GetString() ?? string.Empty : string.Empty,
                Detail = root.TryGetProperty("detail", out var detail) ? detail.GetString() : null,
                Command = root.TryGetProperty("command", out var cmd) ? cmd.GetString() : null,
                Percent = root.TryGetProperty("percent", out var pct) && pct.ValueKind == JsonValueKind.Number
                    ? pct.GetInt32()
                    : 0,
                StepLabel = root.TryGetProperty("step_label", out var label) ? label.GetString() : null,
                StepProgress = steps,
            };
        }
        catch
        {
            return null;
        }
    }
}
