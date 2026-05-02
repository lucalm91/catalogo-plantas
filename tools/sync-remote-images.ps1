$ErrorActionPreference = 'Stop'

$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$envPath = Join-Path $root '.env'
if (-not (Test-Path $envPath)) {
    throw "No se encontro .env"
}

$envValues = @{}
Get-Content $envPath | ForEach-Object {
    $line = $_.Trim()
    if ($line -eq '' -or $line.StartsWith('#') -or -not $line.Contains('=')) {
        return
    }
    $parts = $line.Split('=', 2)
    $envValues[$parts[0].Trim()] = $parts[1].Trim()
}

$sshHost = $envValues['SSH_HOST']
$sshPort = $envValues['SSH_PORT']
$sshUser = $envValues['SSH_USER']
$sshKey = $envValues['SSH_KEY_PATH']

if (-not $sshHost -or -not $sshPort -or -not $sshUser -or -not $sshKey) {
    throw "Faltan SSH_HOST, SSH_PORT, SSH_USER o SSH_KEY_PATH en .env"
}

if ($sshKey.StartsWith('~/')) {
    $sshKey = Join-Path $HOME $sshKey.Substring(2)
}

$remoteImages = "/home/$sshUser/domains/plantas.lucanor.casa/public_html/images/"
$localImages = Join-Path $root 'images'
New-Item -ItemType Directory -Force -Path $localImages | Out-Null

scp -P $sshPort -i $sshKey -r "${sshUser}@${sshHost}:$remoteImages*" $localImages
