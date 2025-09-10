param(
    [string]$ServerPluginsRoot = "E:\MY_PROJECTS\Bearsampp\www\bearsampp\plugins",
    [string]$RepoRoot = "$PSScriptRoot\..",
    [switch]$UseJunctionFallback,
    [switch]$Remove
)

# Normalize roots to absolute paths
try { $RepoRoot = (Resolve-Path -LiteralPath $RepoRoot).Path } catch { $RepoRoot = [System.IO.Path]::GetFullPath($RepoRoot) }
if (-not (Test-Path -LiteralPath $ServerPluginsRoot)) { New-Item -ItemType Directory -Path $ServerPluginsRoot -Force | Out-Null }
try { $ServerPluginsRoot = (Resolve-Path -LiteralPath $ServerPluginsRoot).Path } catch { $ServerPluginsRoot = [System.IO.Path]::GetFullPath($ServerPluginsRoot) }

<#!
Usage examples:
  # Create links (run PowerShell as Administrator or enable Developer Mode)
  .\setup-links.ps1 

  # Force using junctions instead of symlinks
  .\setup-links.ps1 -UseJunctionFallback

  # Remove the links
  .\setup-links.ps1 -Remove

Parameters:
  -ServerPluginsRoot   Destination Joomla plugins root. Default: E:\MY_PROJECTS\Bearsampp\www\bearsampp\plugins
  -RepoRoot            Repository root. Defaults to parent of script folder.
  -UseJunctionFallback Use mklink /J instead of /D (helps if symlink privilege is unavailable).
  -Remove              Remove the created links.

This script links:
  Content plugin: $ServerPluginsRoot\content\bears_aichatbot -> $RepoRoot\package\plugins\content\bears_aichatbot
  Task plugin:    $ServerPluginsRoot\task\bears_aichatbot    -> $RepoRoot\package\plugins\task\bears_aichatbot
!#>

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Ensure-Dir($path) {
    if (-not (Test-Path -LiteralPath $path)) {
        New-Item -ItemType Directory -Path $path | Out-Null
    }
}

function Remove-IfExists($path) {
    if (Test-Path -LiteralPath $path) {
        try {
            # Remove directory, link, or junction
            Remove-Item -LiteralPath $path -Recurse -Force -ErrorAction Stop
        } catch {
            Write-Warning ("Failed to remove {0}: {1}" -f $path, $_)
            throw
        }
    }
}

# Paths computed after ensuring destination directories

Ensure-Dir (Join-Path $ServerPluginsRoot 'content')
Ensure-Dir (Join-Path $ServerPluginsRoot 'task')

# Resolve final absolute paths
$destContent = [System.IO.Path]::GetFullPath((Join-Path $ServerPluginsRoot "content\bears_aichatbot"))
$destTask    = [System.IO.Path]::GetFullPath((Join-Path $ServerPluginsRoot "task\bears_aichatbot"))
$srcContent  = (Resolve-Path -LiteralPath (Join-Path $RepoRoot "package\plugins\content\bears_aichatbot")).Path
$srcTask     = (Resolve-Path -LiteralPath (Join-Path $RepoRoot "package\plugins\task\bears_aichatbot")).Path

Write-Host ("RepoRoot: {0}" -f $RepoRoot) -ForegroundColor DarkGray
Write-Host ("ServerPluginsRoot: {0}" -f $ServerPluginsRoot) -ForegroundColor DarkGray
Write-Host ("Content src: {0}" -f $srcContent) -ForegroundColor DarkGray
Write-Host ("Task src:    {0}" -f $srcTask) -ForegroundColor DarkGray

# Verify sources exist
if (-not (Test-Path -LiteralPath $srcContent)) { throw "Source content plugin not found: $srcContent" }
if (-not (Test-Path -LiteralPath $srcTask))    { throw "Source task plugin not found: $srcTask" }

if ($Remove) {
    Write-Host "Removing links if they exist..." -ForegroundColor Yellow
    Remove-IfExists $destContent
    Remove-IfExists $destTask
    Write-Host "Done." -ForegroundColor Green
    return
}

# Remove any existing dirs/links at destination to avoid mklink failure
Remove-IfExists $destContent
Remove-IfExists $destTask

$mklinkType = if ($UseJunctionFallback) { '/J' } else { '/D' }

function New-Link($dest, $src, $type) {
    Write-Host "Creating link: $dest -> $src ($type)" -ForegroundColor Cyan
    $cmd = ('mklink {0} "{1}" "{2}"' -f $type, $dest, $src)
    $p = Start-Process -FilePath cmd.exe -ArgumentList '/c', $cmd -NoNewWindow -PassThru -Wait
    return $p.ExitCode
}

# Try preferred type first, fallback to junctions if /D fails and fallback not explicitly disabled
$exit = New-Link -dest $destContent -src $srcContent -type $mklinkType
if ($exit -ne 0 -and $mklinkType -eq '/D' -and -not $UseJunctionFallback) {
    Write-Warning "mklink /D failed for content. Retrying with /J (junction)."
    $exit = New-Link -dest $destContent -src $srcContent -type '/J'
}
if ($exit -ne 0) { throw "Failed to create link: $destContent (exit $exit)" }

$exit = New-Link -dest $destTask -src $srcTask -type $mklinkType
if ($exit -ne 0 -and $mklinkType -eq '/D' -and -not $UseJunctionFallback) {
    Write-Warning "mklink /D failed for task. Retrying with /J (junction)."
    $exit = New-Link -dest $destTask -src $srcTask -type '/J'
}
if ($exit -ne 0) { throw "Failed to create link: $destTask (exit $exit)" }

Write-Host "All links created successfully." -ForegroundColor Green
Write-Host "Verify in Explorer and Joomla admin (Extensions -> Manage -> Discover/Plugins)." -ForegroundColor Green
