$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$loader = Join-Path $root '.tools/r2-migration/credentials.private.ps1'

if (-not (Test-Path -LiteralPath $loader -PathType Leaf)) {
    [Console]::Error.WriteLine('Client-preview runtime R2 credential loader is missing from the ignored .tools workspace.')
    exit 1
}

try {
    . $loader
} catch {
    [Console]::Error.WriteLine('Client-preview runtime R2 credential loader could not be loaded.')
    exit 1
}

if ([string]::IsNullOrWhiteSpace($env:R2_ACCESS_KEY_ID) -or [string]::IsNullOrWhiteSpace($env:R2_SECRET_ACCESS_KEY)) {
    [Console]::Error.WriteLine('Client-preview runtime R2 credentials are missing.')
    exit 1
}

# Migration credentials must never be inherited by either preview service.
Get-ChildItem Env:R2_MIGRATION_* | ForEach-Object { Remove-Item -LiteralPath $_.PSPath }
& node (Join-Path $root 'scripts/start-client-preview.mjs')
exit $LASTEXITCODE
