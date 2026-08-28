using System.Globalization;
using System.Text.RegularExpressions;
using Unitec.DeviceService.Domain.Dtos;

namespace Unitec.DeviceService.Application;

/// <summary>
/// Interpreta frames seriais Toledo P05 / P05A (PDV).
/// Manual: ENQ → STX + 5 ASCII + ETX; peso = 2 inteiros + 3 decimais (01014 = 1,014 kg).
/// </summary>
public static class ToledoScaleParser
{
    public static ScaleReadResponse Parse(string response)
    {
        var raw = Regex.Replace(response ?? string.Empty, @"[\x00-\x1F\x7F]+", " ").Trim();
        var frame = TryGetStxEtxFrame(response ?? string.Empty);

        if (frame is null)
        {
            return new ScaleReadResponse(false, "Resposta Toledo sem frame STX/ETX.", Raw: raw);
        }

        var payload = frame.Trim();

        if (payload.Length == 0)
        {
            return new ScaleReadResponse(false, "Frame Toledo vazio.", Raw: raw);
        }

        if (payload.All(character => character == 'I'))
        {
            return new ScaleReadResponse(false, "A balança informou peso instável.", Raw: raw);
        }

        if (payload.All(character => character == 'N'))
        {
            return new ScaleReadResponse(false, "A balança informou peso negativo ou subcarga.", Raw: raw);
        }

        if (payload.All(character => character == 'S'))
        {
            return new ScaleReadResponse(false, "A balança informou sobrecarga.", Raw: raw);
        }

        // P05: cinco dígitos = 2 inteiros + 3 decimais.
        if (payload.Length == 5 && payload.All(char.IsDigit))
        {
            var weightKg = decimal.Parse(payload, CultureInfo.InvariantCulture) / 1000m;

            if (weightKg <= 0m)
            {
                return new ScaleReadResponse(false, "Peso lido inválido (zero ou negativo).", Raw: raw);
            }

            return new ScaleReadResponse(true, $"Peso lido: {weightKg:0.###} kg.", weightKg, raw);
        }

        return new ScaleReadResponse(
            false,
            "Resposta Toledo recebida, mas o peso não foi identificado (espere P05: STX+5 dígitos+ETX).",
            Raw: raw
        );
    }

    private static string? TryGetStxEtxFrame(string response)
    {
        var stx = response.IndexOf('\x02');

        if (stx < 0)
        {
            return null;
        }

        var etx = response.IndexOf('\x03', stx + 1);

        return etx > stx ? response[(stx + 1)..etx] : null;
    }
}
