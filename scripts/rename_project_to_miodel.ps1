param([switch]$Apply)

$ErrorActionPreference = 'Stop'
$source = [IO.Path]::GetFullPath((Split-Path -Parent $PSScriptRoot)).TrimEnd('\')
$expectedSource = 'C:\xampp2\htdocs\myrsu'
$destination = [IO.Path]::GetFullPath((Join-Path (Split-Path -Parent $source) 'miodel')).TrimEnd('\')
if ($source -ine $expectedSource -or $destination -ine 'C:\xampp2\htdocs\miodel') { throw 'Percorsi inattesi: rinomina interrotta.' }
if ((Get-Item -LiteralPath $source).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'La sorgente non deve essere un collegamento.' }
if (Test-Path -LiteralPath $destination) { throw 'La destinazione esiste gia: nessuna sovrascrittura.' }
$template = Join-Path $source 'scripts\deployment\legacy-myrsu\.htaccess'
if (-not (Test-Path -LiteralPath $template -PathType Leaf)) { throw 'Manca la compatibilita per i vecchi indirizzi.' }
Write-Output "Sorgente verificata: $source"
Write-Output "Destinazione verificata: $destination"
if (-not $Apply) { Write-Output 'Controllo completato. Usare -Apply per eseguire la rinomina.'; exit }
Set-Location -LiteralPath (Split-Path -Parent $source)
[Environment]::CurrentDirectory = Split-Path -Parent $source
$moved = $false
$stubCreated = $false
try {
    Rename-Item -LiteralPath $source -NewName 'miodel'
    $moved = $true
    New-Item -ItemType Directory -Path $source | Out-Null
    $stubCreated = $true
    Copy-Item -LiteralPath (Join-Path $destination 'scripts\deployment\legacy-myrsu\.htaccess') -Destination (Join-Path $source '.htaccess')
    Write-Output 'Progetto rinominato in miodel. La cartella myrsu contiene soltanto il reindirizzamento dei vecchi URL.'
} catch {
    if ($stubCreated) {
        $stub = Join-Path $source '.htaccess'
        if (Test-Path -LiteralPath $stub -PathType Leaf) { Remove-Item -LiteralPath $stub }
        if (@(Get-ChildItem -LiteralPath $source -Force).Count -eq 0) { Remove-Item -LiteralPath $source }
    }
    if ($moved -and -not (Test-Path -LiteralPath $source)) { Rename-Item -LiteralPath $destination -NewName 'myrsu' }
    if (-not $moved) { Write-Warning 'Fermare Apache da XAMPP e chiudere Codex e i terminali aperti sul progetto, poi riprovare da PowerShell esterno.' }
    throw
}
