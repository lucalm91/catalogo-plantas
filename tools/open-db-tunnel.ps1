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
$localPort = if ($env:SSH_LOCAL_DB_PORT) { $env:SSH_LOCAL_DB_PORT } else { "3307" }
$remoteDbHost = if ($env:SSH_REMOTE_DB_HOST) { $env:SSH_REMOTE_DB_HOST } else { "127.0.0.1" }
$remoteDbPort = if ($env:SSH_REMOTE_DB_PORT) { $env:SSH_REMOTE_DB_PORT } else { "3306" }

if (-not $sshHost -or -not $sshUser) {
  throw "Faltan SSH_HOST o SSH_USER en $envPath"
}

Write-Host "Abriendo tunnel MySQL local: 127.0.0.1:$localPort -> $remoteDbHost:$remoteDbPort via $sshUser@$sshHost:$sshPort"
Write-Host "Deja esta ventana abierta mientras uses la base de datos."

$args = @("-p", $sshPort)
if ($sshKeyPath) {
  $expandedKeyPath = $sshKeyPath -replace '^~', $env:USERPROFILE
  $args += @("-i", $expandedKeyPath)
}
$args += @("-N", "-L", "${localPort}:${remoteDbHost}:${remoteDbPort}", "$sshUser@$sshHost")

ssh @args
