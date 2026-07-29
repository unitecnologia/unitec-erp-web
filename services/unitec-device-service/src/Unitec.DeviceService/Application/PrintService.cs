using Unitec.DeviceService.Domain;
using Unitec.DeviceService.Domain.Dtos;
using Unitec.DeviceService.Infrastructure.EscPos;
using Unitec.DeviceService.Infrastructure.Printing;

namespace Unitec.DeviceService.Application;

public sealed class PrintService
{
    private readonly IPrinterDriver _driver;
    private readonly WindowsPrinterEnumerator _printers;

    public PrintService(IPrinterDriver driver, WindowsPrinterEnumerator printers)
    {
        _driver = driver;
        _printers = printers;
    }

    public PrintersResponse ListPrinters() => new(_printers.List());

    public async Task<PrintRawResponse> PrintRawAsync(PrintRawRequest request, CancellationToken ct = default)
    {
        if (string.IsNullOrWhiteSpace(request.Printer))
        {
            return new PrintRawResponse(false, "Informe o nome da impressora.");
        }

        if (string.IsNullOrWhiteSpace(request.Data))
        {
            return new PrintRawResponse(false, "Informe o payload Base64 (data).");
        }

        byte[] bytes;
        try
        {
            bytes = Convert.FromBase64String(request.Data.Trim());
        }
        catch (FormatException)
        {
            return new PrintRawResponse(false, "Base64 inválido em data.");
        }

        var copies = Math.Clamp(request.Copies <= 0 ? 1 : request.Copies, 1, 5);

        try
        {
            for (var i = 0; i < copies; i++)
            {
                await _driver.PrintRawAsync(request.Printer.Trim(), bytes, ct).ConfigureAwait(false);
            }

            return new PrintRawResponse(true, $"Impresso ({copies}x) em '{request.Printer.Trim()}'.");
        }
        catch (Exception ex)
        {
            return new PrintRawResponse(false, ex.Message);
        }
    }

    public async Task<OpenDrawerResponse> OpenDrawerAsync(OpenDrawerRequest request, CancellationToken ct = default)
    {
        if (string.IsNullOrWhiteSpace(request.Printer))
        {
            return new OpenDrawerResponse(false, "Informe o nome da impressora.");
        }

        byte[] bytes;
        if (!string.IsNullOrWhiteSpace(request.Data))
        {
            try
            {
                bytes = Convert.FromBase64String(request.Data.Trim());
            }
            catch (FormatException)
            {
                return new OpenDrawerResponse(false, "Base64 inválido em data.");
            }
        }
        else
        {
            bytes = new EscPosBuilder().Init().OpenDrawer().Build();
        }

        try
        {
            await _driver.PrintRawAsync(request.Printer.Trim(), bytes, ct).ConfigureAwait(false);
            return new OpenDrawerResponse(true, "Comando de gaveta enviado.");
        }
        catch (Exception ex)
        {
            return new OpenDrawerResponse(false, ex.Message);
        }
    }

    public PrintPdfResponse PrintPdf(PrintPdfRequest request)
    {
        // Fase 2 — PDF via driver Windows / Sumatra / GDI.
        _ = request;
        return new PrintPdfResponse(false, "Impressão PDF ainda não implementada neste MVP.");
    }
}
