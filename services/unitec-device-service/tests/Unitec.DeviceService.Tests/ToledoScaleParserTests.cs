using Unitec.DeviceService.Application;

namespace Unitec.DeviceService.Tests;

public class ToledoScaleParserTests
{
    [Fact]
    public void Parse_P05FiveDigits_ReturnsKilogramsWithThreeDecimals()
    {
        var response = "\x02" + "01014" + "\x03";

        var result = ToledoScaleParser.Parse(response);

        Assert.True(result.Ok);
        Assert.Equal(1.014m, result.WeightKg);
        Assert.Contains("1,014", result.Message.Replace('.', ','));
    }

    [Fact]
    public void Parse_P05ZeroWeight_ReturnsFailure()
    {
        var response = "\x02" + "00000" + "\x03";

        var result = ToledoScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Null(result.WeightKg);
        Assert.Contains("inválido", result.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void Parse_P05AUnstable_ReturnsFailure()
    {
        var response = "\x02" + "IIIII" + "\x03";

        var result = ToledoScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Null(result.WeightKg);
        Assert.Contains("instável", result.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void Parse_P05AOverload_ReturnsFailure()
    {
        var response = "\x02" + "SSSSS" + "\x03";

        var result = ToledoScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Contains("sobrecarga", result.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void Parse_P05ANegative_ReturnsFailure()
    {
        var response = "\x02" + "NNNNN" + "\x03";

        var result = ToledoScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Contains("negativo", result.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void Parse_WithoutStxEtx_ReturnsFailure()
    {
        var result = ToledoScaleParser.Parse("01014");

        Assert.False(result.Ok);
        Assert.Contains("STX/ETX", result.Message, StringComparison.OrdinalIgnoreCase);
    }
}
