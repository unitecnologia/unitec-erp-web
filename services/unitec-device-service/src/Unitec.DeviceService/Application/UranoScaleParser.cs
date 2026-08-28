using System.Globalization;
using System.Text.RegularExpressions;
using Unitec.DeviceService.Domain.Dtos;

namespace Unitec.DeviceService.Application;

/// <summary>
/// Interpreta frames seriais Urano (Uran12 / Std01–05 / emuladores US POP).
/// </summary>
public static class UranoScaleParser
{
    private static readonly Regex WeightWithUnitPattern = new(
        @"(?<weight>[+-]?\d+[.,]\d+)\s*(?<unit>kg|k|g)\b",
        RegexOptions.Compiled | RegexOptions.IgnoreCase
    );

    private static readonly Regex WeightPattern = new(
        @"[+-]?\d+(?:[.,]\d+)?",
        RegexOptions.Compiled
    );

    private static readonly Regex Uran12WeightPattern = new(
        @"PESO\s*:\s*(?<weight>[+-]?\d+(?:[.,]\d+)?)\s*(?<unit>[kKgG])?",
        RegexOptions.Compiled | RegexOptions.IgnoreCase
    );

    private static readonly Regex DecimalFramePattern = new(
        @"^[\s+-]?\d+(?:\.\d+)?$",
        RegexOptions.Compiled
    );

    public static ScaleReadResponse Parse(string response)
    {
        var raw = Regex.Replace(response, @"[\x00-\x1F\x7F]+", " ").Trim();
        var stdFrame = TryGetStdFrame(response);

        if (stdFrame is not null)
        {
            if (stdFrame.All(character => character == 'I'))
            {
                return new ScaleReadResponse(false, "A balança informou peso instável.", Raw: raw);
            }

            if (stdFrame.All(character => character == 'N'))
            {
                return new ScaleReadResponse(false, "A balança informou peso negativo ou subcarga.", Raw: raw);
            }

            if (stdFrame.All(character => character == 'S'))
            {
                return new ScaleReadResponse(false, "A balança informou sobrecarga.", Raw: raw);
            }

            // Std04/05: cinco dígitos entre STX/ETX, enviados em gramas.
            if (stdFrame.Length == 5 && stdFrame.All(char.IsDigit))
            {
                return WeightResponse(decimal.Parse(stdFrame, CultureInfo.InvariantCulture) / 1000m, raw);
            }

            // Std01–03: peso em kg, sinal opcional e ponto decimal.
            if (DecimalFramePattern.IsMatch(stdFrame)
                && TryParseWeight(stdFrame, out var stdWeight))
            {
                return WeightResponse(stdWeight, raw);
            }
        }

        // Uran12: campo PESO, seguido da unidade k (kg) ou g (gramas).
        var uran12 = Uran12WeightPattern.Match(response);

        if (uran12.Success && TryParseWeight(uran12.Groups["weight"].Value, out var uran12Weight))
        {
            if (string.Equals(uran12.Groups["unit"].Value, "g", StringComparison.OrdinalIgnoreCase))
            {
                uran12Weight /= 1000m;
            }

            return WeightResponse(uran12Weight, raw);
        }

        // Emuladores US POP / frames com ESC: preferir número colado em kg/g
        // (evita pegar "2" de "ESC T 2" antes de "1,007 kg").
        if (TryParseWeightWithUnit(raw, out var unitWeight))
        {
            return WeightResponse(unitWeight, raw);
        }

        // Compatibilidade com respostas que trazem apenas um valor textual.
        var match = WeightPattern.Match(raw);

        if (match.Success
            && match.Value.IndexOfAny([',', '.']) >= 0
            && TryParseWeight(match.Value, out var fallbackWeight))
        {
            return WeightResponse(fallbackWeight, raw);
        }

        return new ScaleReadResponse(false, "Resposta recebida, mas o peso não foi identificado.", Raw: raw);
    }

    private static bool TryParseWeightWithUnit(string raw, out decimal weightKg)
    {
        weightKg = 0m;
        var matches = WeightWithUnitPattern.Matches(raw);

        if (matches.Count == 0)
        {
            return false;
        }

        // Preferir o primeiro valor com unidade (normalmente o peso líquido).
        var match = matches[0];

        if (!TryParseWeight(match.Groups["weight"].Value, out var weight))
        {
            return false;
        }

        var unit = match.Groups["unit"].Value;

        if (string.Equals(unit, "g", StringComparison.OrdinalIgnoreCase))
        {
            weight /= 1000m;
        }

        weightKg = weight;

        return true;
    }

    private static string? TryGetStdFrame(string response)
    {
        var stx = response.IndexOf('\x02');

        if (stx < 0)
        {
            return null;
        }

        var etx = response.IndexOf('\x03', stx + 1);

        return etx > stx ? response[(stx + 1)..etx] : null;
    }

    private static bool TryParseWeight(string value, out decimal weight) =>
        decimal.TryParse(
            value.Trim().Replace(',', '.'),
            NumberStyles.AllowLeadingSign | NumberStyles.AllowDecimalPoint,
            CultureInfo.InvariantCulture,
            out weight
        );

    private static ScaleReadResponse WeightResponse(decimal weight, string raw) =>
        new(true, $"Peso lido: {weight:0.###} kg.", weight, raw);
}
