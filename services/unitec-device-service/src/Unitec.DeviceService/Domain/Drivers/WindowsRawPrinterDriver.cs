using System.Text;
using Unitec.DeviceService.Domain;

namespace Unitec.DeviceService.Domain.Drivers;

/// <summary>Driver genérico RAW via spooler Windows.</summary>
public sealed class WindowsRawPrinterDriver : IPrinterDriver
{
    public string Name => "windows-raw";

    public Task PrintRawAsync(string printerName, byte[] data, CancellationToken cancellationToken = default)
    {
        cancellationToken.ThrowIfCancellationRequested();
        Infrastructure.Printing.RawPrinter.Send(printerName, data);
        return Task.CompletedTask;
    }
}
