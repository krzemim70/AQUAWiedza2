param(
    [switch] $Stop,
    [switch] $NoServe
)

$ErrorActionPreference = 'Stop'

function Invoke-DevCommand {
    param([string] $Command)

    & $Command @args
    if ($LASTEXITCODE -ne 0) {
        throw "Polecenie $Command $($args -join ' ') zwraca kod $LASTEXITCODE."
    }
}

function Test-MySqlPort {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        return $client.ConnectAsync('127.0.0.1', 3306).Wait(500) -and $client.Connected
    } catch [System.AggregateException] {
        return $false
    } finally {
        $client.Dispose()
    }
}

try {
    $repoRoot = Split-Path -Parent $PSScriptRoot
    $toolsPath = $env:AIKWARIUM_TOOLS
    if (-not $toolsPath) {
        $toolsPath = Join-Path $repoRoot '..\tools'
    }
    if (-not (Test-Path -LiteralPath $toolsPath -PathType Container)) {
        throw "Brak katalogu: $toolsPath. Ustaw AIKWARIUM_TOOLS na katalog z PHP, Composerem, Node.js i MySQL."
    }
    $toolsRoot = (Resolve-Path -LiteralPath $toolsPath).Path
    Set-Location -LiteralPath $repoRoot

    $envScript = Join-Path $toolsRoot 'env.ps1'
    if (Test-Path -LiteralPath $envScript -PathType Leaf) {
        . $envScript
    } else {
        $env:PATH = "$toolsRoot\php;$toolsRoot\composer;$toolsRoot\node;$toolsRoot\mysql\bin;$env:PATH"
        $env:COMPOSER_HOME = "$toolsRoot\composer\home"
        $env:COMPOSER_CACHE_DIR = "$toolsRoot\composer\cache"
        $env:npm_config_cache = "$toolsRoot\node\npm-cache"
        $env:npm_config_prefix = "$toolsRoot\node"
        $env:TMP = "$toolsRoot\tmp"
        $env:TEMP = "$toolsRoot\tmp"
    }

    if ($Stop) {
        Write-Host 'Zatrzymywanie MySQL...'
        & (Join-Path $toolsRoot 'mysql-stop.ps1') | Out-Null
        exit 0
    }

    if (-not (Test-MySqlPort)) {
        Write-Host 'Uruchamianie MySQL...'
        & (Join-Path $toolsRoot 'mysql-start.ps1') | Out-Null
        $deadline = (Get-Date).AddSeconds(30)
        while (-not (Test-MySqlPort)) {
            if ((Get-Date) -ge $deadline) {
                throw 'MySQL nie odpowiada na porcie 3306.'
            }
            Start-Sleep -Milliseconds 250
        }
    }

    if (-not (Test-Path -LiteralPath 'vendor' -PathType Container)) {
        Write-Host 'Instalacja bibliotek PHP...'
        Invoke-DevCommand composer install
    }
    if (-not (Test-Path -LiteralPath 'node_modules' -PathType Container)) {
        Write-Host 'Instalacja bibliotek Node.js...'
        Invoke-DevCommand npm.cmd ci
    }
    if (-not (Test-Path -LiteralPath '.env' -PathType Leaf)) {
        Copy-Item -LiteralPath '.env.example' -Destination '.env'
        Invoke-DevCommand php artisan key:generate
    }
    if (-not (Test-Path -LiteralPath 'public/storage')) {
        Invoke-DevCommand php artisan storage:link
    }

    Write-Host 'Aktualizacja bazy danych...'
    Invoke-DevCommand php artisan migrate --force
    if (-not $NoServe) {
        Write-Host 'Uruchamianie aplikacji...'
        Invoke-DevCommand composer dev
    }
} catch {
    [Console]::Error.WriteLine($_.Exception.Message)
    exit 1
}
