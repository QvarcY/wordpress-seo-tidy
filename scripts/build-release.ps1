param(
    [string] $Version = ""
)

$ErrorActionPreference = "Stop"

$Repo = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
Set-Location $Repo

$Package = Get-Content "package.json" -Raw | ConvertFrom-Json

if (!$Version) {
    $Version = $Package.version
}

if ($Version -ne $Package.version -or
    $Version -notmatch '^[0-9]+\.[0-9]+\.[0-9]+(?:-[a-z0-9.]+)?$') {
    throw "STOP: Invalid or mismatched version"
}

$Plugin = Get-Content "seo-tidy.php" -Raw

if (!$Plugin.Contains("Version: $Version") -or
    !$Plugin.Contains("define('SEO_TIDY_VERSION', '$Version')")) {
    throw "STOP: Plugin version mismatch"
}

$Files = @("seo-tidy.php", "LICENSE")

foreach ($Folder in @("includes", "build", "languages", "assets")) {
    if (!(Test-Path $Folder -PathType Container)) {
        throw "STOP: Missing directory: $Folder"
    }

    $Files += @(
        Get-ChildItem $Folder -File -Recurse |
            ForEach-Object {
                $_.FullName.Substring($Repo.Length + 1)
            }
    )
}

$Required = @(
    "seo-tidy.php",
    "LICENSE",
    "build/index.js",
    "build/index.asset.php",
    "build/editor/editor.js",
    "build/editor/editor.asset.php",
    "assets/seo-tidy-hero.webp",
    "assets/seo-tidy-social-preview.png"
)

foreach ($Item in $Required) {
    if (!(Test-Path $Item -PathType Leaf)) {
        throw "STOP: Missing required file: $Item"
    }
}

$Dist = Join-Path $Repo "dist"
$Zip = Join-Path $Dist "seo-tidy-$Version.zip"
$Candidate = Join-Path $Dist "seo-tidy-$Version.tmp"

New-Item -ItemType Directory -Force $Dist | Out-Null

if (Test-Path $Candidate) {
    throw "STOP: Temporary ZIP already exists"
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

try {
    $Stream = [System.IO.File]::Open(
        $Candidate,
        [System.IO.FileMode]::CreateNew
    )

    try {
        $Archive = New-Object System.IO.Compression.ZipArchive(
            $Stream,
            [System.IO.Compression.ZipArchiveMode]::Create,
            $true
        )

        try {
            foreach ($File in $Files) {
                $Name = "seo-tidy/" + $File.Replace('\', '/')
                $Full = Join-Path $Repo $File

                [void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
                    $Archive,
                    $Full,
                    $Name,
                    [System.IO.Compression.CompressionLevel]::Optimal
                )
            }
        }
        finally {
            $Archive.Dispose()
        }
    }
    finally {
        $Stream.Dispose()
    }

    $Archive = [System.IO.Compression.ZipFile]::OpenRead($Candidate)

    try {
        $Entries = @(
            $Archive.Entries |
                ForEach-Object { $_.FullName }
        )

        foreach ($Item in $Required) {
            if ($Entries -cnotcontains ("seo-tidy/" + $Item)) {
                throw "STOP: ZIP missing: $Item"
            }
        }

        if (
            $Entries.Count -ne $Files.Count -or
            @($Entries | Select-Object -Unique).Count -ne $Entries.Count
        ) {
            throw "STOP: ZIP entry count mismatch"
        }

        foreach ($Entry in $Entries) {
            if (
                !$Entry.StartsWith("seo-tidy/") -or
                $Entry.Contains('\') -or
                $Entry -match '(^|/)(node_modules|src|tests|\.git)/' -or
                $Entry -match '(^|/)\.\.?(/|$)'
            ) {
                throw "STOP: Invalid ZIP entry: $Entry"
            }
        }
    }
    finally {
        $Archive.Dispose()
    }

    Move-Item $Candidate $Zip -Force
}
finally {
    if (Test-Path $Candidate) {
        Remove-Item $Candidate -Force
    }
}

$Hash = (Get-FileHash $Zip -Algorithm SHA256).Hash

Write-Host "PASS: Release ZIP created"
Write-Host "PASS: $($Files.Count) files packaged"
Write-Host "PASS: Portable paths verified"
Write-Host "ZIP: $Zip"
Write-Host "SHA256: $Hash"