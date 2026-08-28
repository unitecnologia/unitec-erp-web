using System.IO.Ports;
using System.Text.RegularExpressions;
using Unitec.DeviceService.Domain.Dtos;

namespace Unitec.DeviceService.Application;

/// <summary>
/// Leitura pontual de peso para diagnóstico. Não mantém a porta aberta entre chamadas.
/// </summary>
public sealed class ScaleReadService
{
    public async Task<ScaleReadResponse> ReadAsync(ScaleReadRequest request, CancellationToken ct = default)
    {
        var port = (request.Port ?? string.Empty).Trim();

        if (!Regex.IsMatch(port, @"^COM\d+$", RegexOptions.IgnoreCase))
        {
            return new ScaleReadResponse(false, "Informe uma porta serial COM válida.");
        }

        if (request.BaudRate is < 110 or > 115200 || request.DataBits is < 5 or > 8)
        {
            return new ScaleReadResponse(false, "Configuração serial inválida.");
        }

        try
        {
            var marca = NormalizeMarca(request.Marca);

            var response = await Task.Run(() => ReadPort(request, port), ct).ConfigureAwait(false);

            if (string.IsNullOrWhiteSpace(response))
            {
                return new ScaleReadResponse(
                    false,
                    "Falha de comunicação com a Balança. Verifique a COM ou reinicie o computador."
                );
            }

            return marca switch
            {
                "baltoledo" => ToledoScaleParser.Parse(response),
                "balfilizola" => FilizolaScaleParser.Parse(response),
                _ => UranoScaleParser.Parse(response),
            };
        }
        catch (UnauthorizedAccessException)
        {
            return new ScaleReadResponse(
                false,
                $"A porta {port} está em uso. No com0com, deixe o simulador em uma COM e o ERP na outra."
            );
        }
        catch (TimeoutException)
        {
            return new ScaleReadResponse(
                false,
                $"Tempo esgotado em {port}. Confira a outra COM do par e habilite Monitorar Requisição no simulador."
            );
        }
        catch (Exception ex)
        {
            return new ScaleReadResponse(false, $"Falha na comunicação serial: {ex.Message}");
        }
    }

    private static string ReadPort(ScaleReadRequest request, string port)
    {
        using var serial = new SerialPort(
            port,
            request.BaudRate,
            ParseParity(request.Parity),
            request.DataBits,
            ParseStopBits(request.StopBits)
        )
        {
            Handshake = ParseHandshake(request.Handshake),
            ReadTimeout = 2000,
            WriteTimeout = 2000,
            Encoding = System.Text.Encoding.ASCII,
            DtrEnable = true,
            RtsEnable = false,
        };

        serial.Open();
        serial.DiscardInBuffer();
        serial.DiscardOutBuffer();

        // Manuais Urano: a solicitação sob demanda aceita ENQ (05h) ou EOT (04h).
        serial.Write(new byte[] { 0x05 }, 0, 1);
        var response = TryRead(serial);

        if (string.IsNullOrWhiteSpace(response))
        {
            serial.Write(new byte[] { 0x04 }, 0, 1);
            response = TryRead(serial);
        }

        return response;
    }

    private static string TryRead(SerialPort serial)
    {
        // Os frames Std terminam em ETX. Uran12 pode trazer ESCs e não usar CR,
        // então reunimos o buffer por até 2 s sem depender de um terminador textual.
        var response = string.Empty;
        var quietAttempts = 0;

        for (var attempt = 0; attempt < 20; attempt++)
        {
            Thread.Sleep(100);

            if (serial.BytesToRead > 0)
            {
                response += serial.ReadExisting();
                quietAttempts = 0;

                if (response.Contains('\x02') && response.Contains('\x03'))
                {
                    return response;
                }

                continue;
            }

            if (!string.IsNullOrEmpty(response) && ++quietAttempts >= 2)
            {
                return response;
            }
        }

        return response;
    }

    private static string NormalizeMarca(string? marca) =>
        (marca ?? string.Empty).Trim().ToLowerInvariant();

    private static Parity ParseParity(string? value) =>
        Enum.TryParse<Parity>(value, true, out var result) ? result : Parity.None;

    private static StopBits ParseStopBits(string? value) => value?.Trim() switch
    {
        "2" => StopBits.Two,
        "1.5" => StopBits.OnePointFive,
        _ => StopBits.One,
    };

    private static Handshake ParseHandshake(string? value) => value?.Trim() switch
    {
        "XOnXOff" => Handshake.XOnXOff,
        "RTS" or "RequestToSend" => Handshake.RequestToSend,
        "RequestToSendXOnXOff" => Handshake.RequestToSendXOnXOff,
        _ => Handshake.None,
    };
}
