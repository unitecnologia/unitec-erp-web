namespace Unitec.DeviceService.Domain;

/// <summary>Contrato de driver de impressora (ESC/POS, ZPL, etc).</summary>
public interface IPrinterDriver
{
    string Name { get; }

    Task PrintRawAsync(string printerName, byte[] data, CancellationToken cancellationToken = default);
}
