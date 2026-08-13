using System.Runtime.InteropServices;

namespace Unitec.DeviceService.Infrastructure.Printing;

/// <summary>Envia bytes RAW direto ao spooler Windows (OpenPrinter / WritePrinter).</summary>
public static class RawPrinter
{
    public static void Send(string printerName, byte[] data)
    {
        ArgumentException.ThrowIfNullOrWhiteSpace(printerName);
        ArgumentNullException.ThrowIfNull(data);
        if (data.Length == 0)
        {
            throw new ArgumentException("Payload RAW vazio.", nameof(data));
        }

        var name = printerName.Trim();
        if (!NativeMethods.OpenPrinter(name, out var hPrinter, IntPtr.Zero))
        {
            throw new InvalidOperationException(
                $"Não foi possível abrir a impressora '{name}' (Win32={Marshal.GetLastWin32Error()}).");
        }

        try
        {
            var di = new NativeMethods.DOCINFOA
            {
                pDocName = "Unitec Device Service",
                pOutputFile = null,
                pDataType = "RAW",
            };

            // NÃO usar `ref` com DOCINFOA (classe) — corrompe o marshaling e derruba o processo.
            if (NativeMethods.StartDocPrinter(hPrinter, 1, di) <= 0)
            {
                throw new InvalidOperationException(
                    $"StartDocPrinter falhou para '{name}' (Win32={Marshal.GetLastWin32Error()}).");
            }

            try
            {
                if (!NativeMethods.StartPagePrinter(hPrinter))
                {
                    throw new InvalidOperationException(
                        $"StartPagePrinter falhou para '{name}' (Win32={Marshal.GetLastWin32Error()}).");
                }

                try
                {
                    var pinned = GCHandle.Alloc(data, GCHandleType.Pinned);
                    try
                    {
                        if (!NativeMethods.WritePrinter(
                                hPrinter,
                                pinned.AddrOfPinnedObject(),
                                data.Length,
                                out var written)
                            || written != data.Length)
                        {
                            throw new InvalidOperationException(
                                $"WritePrinter incompleto para '{name}' ({written}/{data.Length}).");
                        }
                    }
                    finally
                    {
                        pinned.Free();
                    }
                }
                finally
                {
                    NativeMethods.EndPagePrinter(hPrinter);
                }
            }
            finally
            {
                NativeMethods.EndDocPrinter(hPrinter);
            }
        }
        finally
        {
            NativeMethods.ClosePrinter(hPrinter);
        }
    }

    private static class NativeMethods
    {
        [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
        internal class DOCINFOA
        {
            [MarshalAs(UnmanagedType.LPStr)]
            public string? pDocName;

            [MarshalAs(UnmanagedType.LPStr)]
            public string? pOutputFile;

            [MarshalAs(UnmanagedType.LPStr)]
            public string? pDataType;
        }

        [DllImport("winspool.drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi)]
        internal static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

        [DllImport("winspool.drv", EntryPoint = "ClosePrinter", SetLastError = true)]
        internal static extern bool ClosePrinter(IntPtr hPrinter);

        [DllImport(
            "winspool.drv",
            EntryPoint = "StartDocPrinterA",
            SetLastError = true,
            CharSet = CharSet.Ansi,
            ExactSpelling = true)]
        internal static extern int StartDocPrinter(
            IntPtr hPrinter,
            int level,
            [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

        [DllImport("winspool.drv", EntryPoint = "EndDocPrinter", SetLastError = true)]
        internal static extern bool EndDocPrinter(IntPtr hPrinter);

        [DllImport("winspool.drv", EntryPoint = "StartPagePrinter", SetLastError = true)]
        internal static extern bool StartPagePrinter(IntPtr hPrinter);

        [DllImport("winspool.drv", EntryPoint = "EndPagePrinter", SetLastError = true)]
        internal static extern bool EndPagePrinter(IntPtr hPrinter);

        [DllImport("winspool.drv", EntryPoint = "WritePrinter", SetLastError = true)]
        internal static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, int dwCount, out int dwWritten);
    }
}
