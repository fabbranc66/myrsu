$Path = "C:\Users\fabbr\Desktop\PDR_Sitem_2026_2029_Modello_Marelli_PRESENZA_MINIMO_500.odt"
$TempPath = Join-Path $env:TEMP ("odt_clean_" + [guid]::NewGuid().ToString())
$CleanPath = "$Path.clean"

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

New-Item -ItemType Directory -Path $TempPath | Out-Null
[System.IO.Compression.ZipFile]::ExtractToDirectory($Path, $TempPath)

$ContentPath = Join-Path $TempPath "content.xml"
$Content = [System.IO.File]::ReadAllText($ContentPath, [System.Text.Encoding]::UTF8)

$C3 = [string][char]0x00C3
$C2 = [string][char]0x00C2
$Content = $Content.Replace($C3 + [string][char]0x00A8, [string][char]0x00E8)
$Content = $Content.Replace($C3 + [string][char]0x00A9, [string][char]0x00E9)
$Content = $Content.Replace($C3 + [string][char]0x00A0, [string][char]0x00E0)
$Content = $Content.Replace($C3 + [string][char]0x00B2, [string][char]0x00F2)
$Content = $Content.Replace($C3 + [string][char]0x00B9, [string][char]0x00F9)
$Content = $Content.Replace($C3 + [string][char]0x00AC, [string][char]0x00EC)
$Content = $Content.Replace($C3 + [string][char]0x02C6, [string][char]0x00C8)
$Content = $Content.Replace($C2, "")

$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($ContentPath, $Content, $Utf8NoBom)

if (Test-Path $CleanPath) { Remove-Item -LiteralPath $CleanPath -Force }
$Zip = [System.IO.Compression.ZipFile]::Open($CleanPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    $MimePath = Join-Path $TempPath "mimetype"
    if (Test-Path $MimePath) {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($Zip, $MimePath, "mimetype", [System.IO.Compression.CompressionLevel]::NoCompression) | Out-Null
    }
    Get-ChildItem -LiteralPath $TempPath -Recurse -File |
        Where-Object { $_.Name -ne "mimetype" } |
        ForEach-Object {
            $RelativePath = $_.FullName.Substring($TempPath.Length + 1).Replace("\", "/")
            [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($Zip, $_.FullName, $RelativePath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        }
}
finally {
    $Zip.Dispose()
}

Move-Item -LiteralPath $CleanPath -Destination $Path -Force
Remove-Item -LiteralPath $TempPath -Recurse -Force
Write-Output $Path

