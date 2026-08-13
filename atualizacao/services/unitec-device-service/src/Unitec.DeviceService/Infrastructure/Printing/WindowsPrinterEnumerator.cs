using System.Drawing.Printing;
using Unitec.DeviceService.Domain.Dtos;

namespace Unitec.DeviceService.Infrastructure.Printing;

public sealed class WindowsPrinterEnumerator
{
    public IReadOnlyList<PrinterInfoDto> List()
    {
        var result = new List<PrinterInfoDto>();
        string? defaultName = null;

        try
        {
            defaultName = new PrinterSettings().PrinterName;
        }
        catch
        {
            // ignore
        }

        foreach (string name in PrinterSettings.InstalledPrinters)
        {
            if (string.IsNullOrWhiteSpace(name))
            {
                continue;
            }

            result.Add(new PrinterInfoDto(
                Name: name.Trim(),
                IsDefault: string.Equals(name, defaultName, StringComparison.OrdinalIgnoreCase),
                Status: null,
                PortName: null
            ));
        }

        return result
            .OrderByDescending(p => p.IsDefault)
            .ThenBy(p => p.Name, StringComparer.OrdinalIgnoreCase)
            .ToList();
    }
}
