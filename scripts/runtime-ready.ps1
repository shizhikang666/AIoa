param(
    [string]$HostName = '127.0.0.1',
    [int[]]$Ports = @(3306, 6379, 9000),
    [int]$TimeoutMilliseconds = 800
)

$ErrorActionPreference = 'Stop'

function Test-TcpPort {
    param(
        [string]$HostName,
        [int]$Port,
        [int]$TimeoutMilliseconds
    )

    $client = [System.Net.Sockets.TcpClient]::new()
    try {
        $async = $client.BeginConnect($HostName, $Port, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne($TimeoutMilliseconds, $false)) {
            return $false
        }

        $client.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

$allReady = $true
foreach ($port in $Ports) {
    $ready = Test-TcpPort -HostName $HostName -Port $port -TimeoutMilliseconds $TimeoutMilliseconds
    $status = if ($ready) { 'LISTENING' } else { 'UNAVAILABLE' }
    Write-Host "$HostName`:$port $status"
    if (-not $ready) {
        $allReady = $false
    }
}

if (-not $allReady) {
    Write-Host ''
    Write-Host 'Start the local runtime bundle before DB/HTTP smoke tests:'
    Write-Host 'Set-Location E:\project\socket\AI\testPhp\files'
    Write-Host '.\startServer1.bat'
    exit 1
}
