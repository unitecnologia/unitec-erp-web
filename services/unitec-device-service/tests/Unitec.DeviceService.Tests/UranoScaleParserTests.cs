using Unitec.DeviceService.Application;

namespace Unitec.DeviceService.Tests;

public class UranoScaleParserTests
{
    [Fact]
    public void Parse_RealUsPopFrame_PrefersWeightWithKgOverEscCommandDigit()
    {
        // Frame observado no simulador/Form1: ESC T 2 ... 1,007 kg ...
        var response =
            "\x1bT2\x1bB\x1bN0          1,007 kg       0,00\x1bN1    0,00";

        var result = UranoScaleParser.Parse(response);

        Assert.True(result.Ok);
        Assert.Equal(1.007m, result.WeightKg);
        Assert.Contains("1,007", result.Message.Replace('.', ','));
    }

    [Fact]
    public void Parse_Uran12PesoField_ReturnsKilograms()
    {
        var response = "\x1bI1\x1bA13 PESO:1,001k \x1bP01";

        var result = UranoScaleParser.Parse(response);

        Assert.True(result.Ok);
        Assert.Equal(1.001m, result.WeightKg);
    }

    [Fact]
    public void Parse_Std04Grams_ConvertsToKilograms()
    {
        var response = "\x02" + "01001" + "\x03";

        var result = UranoScaleParser.Parse(response);

        Assert.True(result.Ok);
        Assert.Equal(1.001m, result.WeightKg);
    }

    [Fact]
    public void Parse_StdUnstable_ReturnsFailure()
    {
        var response = "\x02" + "IIIIII" + "\x03";

        var result = UranoScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Null(result.WeightKg);
        Assert.Contains("instável", result.Message, StringComparison.OrdinalIgnoreCase);
    }
}
