using Unitec.DeviceService.Application;

namespace Unitec.DeviceService.Tests;

public class FilizolaScaleParserTests
{
    [Fact]
    public void Parse_FiveDigits_ReturnsKilogramsWithThreeDecimals()
    {
        var response = "\x02" + "00423" + "\x03";

        var result = FilizolaScaleParser.Parse(response);

        Assert.True(result.Ok);
        Assert.Equal(0.423m, result.WeightKg);
        Assert.Contains("0,423", result.Message.Replace('.', ','));
    }

    [Fact]
    public void Parse_ZeroWeight_ReturnsFailure()
    {
        var response = "\x02" + "00000" + "\x03";

        var result = FilizolaScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Null(result.WeightKg);
    }

    [Fact]
    public void Parse_Unstable_ReturnsFailure()
    {
        var response = "\x02" + "IIIII" + "\x03";

        var result = FilizolaScaleParser.Parse(response);

        Assert.False(result.Ok);
        Assert.Contains("instável", result.Message, StringComparison.OrdinalIgnoreCase);
    }

    [Fact]
    public void Parse_WithoutStxEtx_ReturnsFailure()
    {
        var result = FilizolaScaleParser.Parse("00423");

        Assert.False(result.Ok);
        Assert.Contains("STX/ETX", result.Message, StringComparison.OrdinalIgnoreCase);
    }
}
