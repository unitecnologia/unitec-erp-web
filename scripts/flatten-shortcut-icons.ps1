# Flatten shortcut PNGs onto solid white (#FFFFFF).
param(
    [string]$Directory = (Join-Path $PSScriptRoot '..\public\img\erp\shortcuts')
)

Add-Type -AssemblyName System.Drawing

function Test-BackgroundPixel([System.Drawing.Color]$c) {
    if ($c.A -lt 16) { return $true }

    $r = [int]$c.R
    $g = [int]$c.G
    $b = [int]$c.B
    $max = [Math]::Max($r, [Math]::Max($g, $b))
    $min = [Math]::Min($r, [Math]::Min($g, $b))
    $avg = ($r + $g + $b) / 3.0
    $spread = $max - $min

    if ($avg -le 50 -and $spread -le 45) { return $true }
    if ($spread -le 32 -and $avg -ge 140) { return $true }

    return $false
}

function Flatten-ShortcutIcon([string]$path) {
    $loaded = [System.Drawing.Bitmap]::FromFile($path)
    $maxSide = [Math]::Max($loaded.Width, $loaded.Height)
    if ($maxSide -gt 512) {
        $ratio = 512.0 / $maxSide
        $nw = [int][Math]::Round($loaded.Width * $ratio)
        $nh = [int][Math]::Round($loaded.Height * $ratio)
        $src = New-Object System.Drawing.Bitmap $nw, $nh, ([System.Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $gs = [System.Drawing.Graphics]::FromImage($src)
        $gs.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $gs.DrawImage($loaded, 0, 0, $nw, $nh)
        $gs.Dispose()
        $loaded.Dispose()
    } else {
        $src = $loaded
    }

    $w = $src.Width
    $h = $src.Height
    $visited = New-Object 'bool[,]' $w, $h
    $mask = New-Object 'bool[,]' $w, $h
    $queue = New-Object System.Collections.Generic.Queue[object]
    $white = [System.Drawing.Color]::FromArgb(255, 255, 255)

    function Add-Seed([int]$x, [int]$y) {
        if ($x -lt 0 -or $y -lt 0 -or $x -ge $w -or $y -ge $h) { return }
        if ($visited[$x, $y]) { return }
        $visited[$x, $y] = $true
        if (Test-BackgroundPixel $src.GetPixel($x, $y)) {
            $queue.Enqueue(@($x, $y))
        }
    }

    for ($x = 0; $x -lt $w; $x++) {
        Add-Seed $x 0
        Add-Seed $x ($h - 1)
    }
    for ($y = 0; $y -lt $h; $y++) {
        Add-Seed 0 $y
        Add-Seed ($w - 1) $y
    }

    while ($queue.Count -gt 0) {
        $p = $queue.Dequeue()
        $x = $p[0]
        $y = $p[1]
        $mask[$x, $y] = $true
        Add-Seed ($x - 1) $y
        Add-Seed ($x + 1) $y
        Add-Seed $x ($y - 1)
        Add-Seed $x ($y + 1)
    }

    $flat = New-Object System.Drawing.Bitmap $w, $h, ([System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
    $minX = $w
    $minY = $h
    $maxX = 0
    $maxY = 0

    for ($y = 0; $y -lt $h; $y++) {
        for ($x = 0; $x -lt $w; $x++) {
            if ($mask[$x, $y]) {
                $flat.SetPixel($x, $y, $white)
                continue
            }

            $c = $src.GetPixel($x, $y)
            $a = $c.A / 255.0
            $r = [int][Math]::Round($c.R * $a + 255 * (1 - $a))
            $g = [int][Math]::Round($c.G * $a + 255 * (1 - $a))
            $b = [int][Math]::Round($c.B * $a + 255 * (1 - $a))
            $pixel = [System.Drawing.Color]::FromArgb($r, $g, $b)
            $flat.SetPixel($x, $y, $pixel)

            if ($x -lt $minX) { $minX = $x }
            if ($y -lt $minY) { $minY = $y }
            if ($x -gt $maxX) { $maxX = $x }
            if ($y -gt $maxY) { $maxY = $y }
        }
    }

    $src.Dispose()

    if ($maxX -lt $minX) {
        $flat.Dispose()
        throw "No icon content found in $path"
    }

    $pad = 2
    $minX = [Math]::Max(0, $minX - $pad)
    $minY = [Math]::Max(0, $minY - $pad)
    $maxX = [Math]::Min($w - 1, $maxX + $pad)
    $maxY = [Math]::Min($h - 1, $maxY + $pad)
    $cw = $maxX - $minX + 1
    $ch = $maxY - $minY + 1

    $crop = New-Object System.Drawing.Bitmap $cw, $ch
    $gc = [System.Drawing.Graphics]::FromImage($crop)
    $gc.DrawImage(
        $flat,
        (New-Object System.Drawing.Rectangle 0, 0, $cw, $ch),
        (New-Object System.Drawing.Rectangle $minX, $minY, $cw, $ch),
        [System.Drawing.GraphicsUnit]::Pixel
    )
    $gc.Dispose()
    $flat.Dispose()

    $out = New-Object System.Drawing.Bitmap 128, 128, ([System.Drawing.Imaging.PixelFormat]::Format24bppRgb)
    $g = [System.Drawing.Graphics]::FromImage($out)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::HighQuality
    $g.PixelOffsetMode = [System.Drawing.Drawing2D.PixelOffsetMode]::HighQuality
    $g.Clear($white)

    $scale = [Math]::Min(128.0 / $cw, 128.0 / $ch) * 0.9
    $nw = [int][Math]::Round($cw * $scale)
    $nh = [int][Math]::Round($ch * $scale)
    $ox = [int][Math]::Round((128 - $nw) / 2.0)
    $oy = [int][Math]::Round((128 - $nh) / 2.0)
    $g.DrawImage($crop, $ox, $oy, $nw, $nh)
    $g.Dispose()
    $crop.Dispose()

    $tmp = "$path.tmp"
    $out.Save($tmp, [System.Drawing.Imaging.ImageFormat]::Png)
    $out.Dispose()
    Move-Item -Force $tmp $path

    # Remove isolated light-gray plates/shadows not reached by edge flood fill.
    $final = [System.Drawing.Bitmap]::FromFile($path)
    for ($y = 0; $y -lt $final.Height; $y++) {
        for ($x = 0; $x -lt $final.Width; $x++) {
            $c = $final.GetPixel($x, $y)
            $r = [int]$c.R; $g = [int]$c.G; $b = [int]$c.B
            $max = [Math]::Max($r, [Math]::Max($g, $b))
            $min = [Math]::Min($r, [Math]::Min($g, $b))
            $avg = ($r + $g + $b) / 3.0
            $spread = $max - $min
            if ($spread -le 30 -and $avg -ge 168) {
                $final.SetPixel($x, $y, $white)
            }
        }
    }
    $tmp2 = "$path.tmp"
    $final.Save($tmp2, [System.Drawing.Imaging.ImageFormat]::Png)
    $final.Dispose()
    Move-Item -Force $tmp2 $path
}

$dir = (Resolve-Path $Directory).Path
Get-ChildItem $dir -Filter '*.png' | ForEach-Object {
    Flatten-ShortcutIcon $_.FullName
    Write-Output "flattened $($_.Name)"
}
