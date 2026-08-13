using System.Reflection;
using System.Text;
using Unitec.DeviceService.Application;
using Unitec.DeviceService.Domain;
using Unitec.DeviceService.Domain.Drivers;
using Unitec.DeviceService.Domain.Dtos;
using Unitec.DeviceService.Infrastructure.Printing;
using Unitec.DeviceService.Host;

Encoding.RegisterProvider(CodePagesEncodingProvider.Instance);

var builder = WebApplication.CreateBuilder(args);

builder.Host.UseWindowsService();

var urls = builder.Configuration["Urls"] ?? "http://127.0.0.1:9330";
builder.WebHost.UseUrls(urls);

builder.Services.AddSingleton<WindowsPrinterEnumerator>();
builder.Services.AddSingleton<IPrinterDriver, WindowsRawPrinterDriver>();
builder.Services.AddSingleton<PrintService>();
builder.Services.AddHostedService<TrayIconHostedService>();
builder.Services.AddCors(options =>
{
    // ERP no navegador (outro host/porta) chama 127.0.0.1:9330 no PC do caixa.
    options.AddDefaultPolicy(policy =>
        policy.AllowAnyHeader()
            .AllowAnyMethod()
            .SetIsOriginAllowed(_ => true));
});

var app = builder.Build();

app.UseCors();

app.Use(async (ctx, next) =>
{
    var remote = ctx.Connection.RemoteIpAddress;
    if (remote is not null && !System.Net.IPAddress.IsLoopback(remote))
    {
        ctx.Response.StatusCode = StatusCodes.Status403Forbidden;
        await ctx.Response.WriteAsJsonAsync(new { ok = false, message = "Somente localhost." });
        return;
    }

    var requireKey = builder.Configuration.GetValue("DeviceService:RequireApiKey", false);
    var configuredKey = builder.Configuration["DeviceService:ApiKey"] ?? string.Empty;
    if (requireKey && !string.IsNullOrWhiteSpace(configuredKey))
    {
        if (!ctx.Request.Headers.TryGetValue("X-Unitec-Key", out var provided)
            || !string.Equals(provided.ToString(), configuredKey, StringComparison.Ordinal))
        {
            ctx.Response.StatusCode = StatusCodes.Status401Unauthorized;
            await ctx.Response.WriteAsJsonAsync(new { ok = false, message = "X-Unitec-Key inválida." });
            return;
        }
    }

    await next();
});

var version = Assembly.GetExecutingAssembly().GetName().Version?.ToString(3) ?? "1.0.0";

app.MapGet("/api/status", () =>
{
    var uri = new Uri(urls.Split(';')[0].Trim());
    return Results.Json(new StatusResponse(
        Service: "Unitecnologia Device Service",
        Version: version,
        Online: true,
        Host: uri.Host,
        Port: uri.Port,
        UtcNow: DateTimeOffset.UtcNow
    ));
});

app.MapGet("/api/printers", (PrintService print) => Results.Json(print.ListPrinters()));

app.MapPost("/api/print/raw", async (PrintRawRequest body, PrintService print, CancellationToken ct) =>
{
    var result = await print.PrintRawAsync(body, ct);
    return result.Ok ? Results.Json(result) : Results.BadRequest(result);
});

app.MapPost("/api/print/pdf", (PrintPdfRequest body, PrintService print) =>
{
    var result = print.PrintPdf(body);
    return Results.Json(result, statusCode: StatusCodes.Status501NotImplemented);
});

app.MapPost("/api/open-drawer", async (OpenDrawerRequest body, PrintService print, CancellationToken ct) =>
{
    var result = await print.OpenDrawerAsync(body, ct);
    return result.Ok ? Results.Json(result) : Results.BadRequest(result);
});

app.Run();
