param(
  [string]$EnvFile = ".env"
)

$root = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $root $EnvFile
if (Test-Path $envPath) {
  Get-Content $envPath | ForEach-Object {
    if ($_ -match '^\s*#' -or $_ -notmatch '=') { return }
    $parts = $_ -split '=', 2
    [Environment]::SetEnvironmentVariable($parts[0].Trim(), $parts[1].Trim(), 'Process')
  }
}

$sshHost = $env:SSH_HOST
$sshPort = if ($env:SSH_PORT) { $env:SSH_PORT } else { "22" }
$sshUser = $env:SSH_USER
$sshKeyPath = $env:SSH_KEY_PATH

if (-not $sshHost -or -not $sshUser) {
  throw "Faltan SSH_HOST o SSH_USER en $envPath"
}

$args = @("-p", $sshPort)
if ($sshKeyPath) {
  $expandedKeyPath = $sshKeyPath -replace '^~', $env:USERPROFILE
  $args += @("-i", $expandedKeyPath)
}
$args += "$sshUser@$sshHost"

ssh @args
