using System.Text;

namespace Unitec.DeviceService.Infrastructure.EscPos;

/// <summary>Builder mínimo ESC/POS (útil para gaveta / testes).</summary>
public sealed class EscPosBuilder
{
    private readonly MemoryStream _stream = new();

    public EscPosBuilder Init()
    {
        Write(0x1B, 0x40);
        return this;
    }

    public EscPosBuilder Text(string text, Encoding? encoding = null)
    {
        encoding ??= Encoding.GetEncoding(850);
        var bytes = encoding.GetBytes(text);
        _stream.Write(bytes, 0, bytes.Length);
        return this;
    }

    public EscPosBuilder Feed(int lines = 1)
    {
        for (var i = 0; i < Math.Max(0, lines); i++)
        {
            Write(0x0A);
        }

        return this;
    }

    public EscPosBuilder Cut()
    {
        Write(0x1D, 0x56, 0x00);
        return this;
    }

    /// <summary>Pulso padrão para gaveta (pin 2, 50ms on / 50ms off).</summary>
    public EscPosBuilder OpenDrawer(byte pin = 0x00, byte onTime = 0x19, byte offTime = 0x19)
    {
        Write(0x1B, 0x70, pin, onTime, offTime);
        return this;
    }

    public byte[] Build() => _stream.ToArray();

    private void Write(params byte[] bytes) => _stream.Write(bytes, 0, bytes.Length);
}
