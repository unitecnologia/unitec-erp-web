namespace Unitec.DeviceService.Domain.Dtos;

public sealed record StatusResponse(
    string Service,
    string Version,
    bool Online,
    string Host,
    int Port,
    DateTimeOffset UtcNow
);

public sealed record PrinterInfoDto(
    string Name,
    bool IsDefault,
    string? Status,
    string? PortName
);

public sealed record PrintersResponse(IReadOnlyList<PrinterInfoDto> Printers);

public sealed class PrintRawRequest
{
    public string Printer { get; set; } = string.Empty;

    /// <summary>Payload ESC/POS (ou outro RAW) em Base64.</summary>
    public string Data { get; set; } = string.Empty;

    public int Copies { get; set; } = 1;
}

public sealed record PrintRawResponse(bool Ok, string Message);

public sealed class OpenDrawerRequest
{
    public string Printer { get; set; } = string.Empty;

    /// <summary>Opcional: bytes RAW da gaveta em Base64. Se vazio, usa pulso padrão ESC/POS.</summary>
    public string? Data { get; set; }
}

public sealed record OpenDrawerResponse(bool Ok, string Message);

public sealed class PrintPdfRequest
{
    public string Printer { get; set; } = string.Empty;
    public string Data { get; set; } = string.Empty;
}

public sealed record PrintPdfResponse(bool Ok, string Message);

public sealed class ScaleReadRequest
{
    public string Port { get; set; } = string.Empty;
    public int BaudRate { get; set; } = 9600;
    public int DataBits { get; set; } = 8;
    public string Parity { get; set; } = "None";
    public string StopBits { get; set; } = "1";
    public string Handshake { get; set; } = "None";
    public string Marca { get; set; } = string.Empty;
}

public sealed record ScaleReadResponse(
    bool Ok,
    string Message,
    decimal? WeightKg = null,
    string? Raw = null
);
