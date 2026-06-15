param(
    [string]$BackendBaseUrl = 'http://127.0.0.1:82',
    [string]$BackendPath = '/think',
    [string]$FrontendBaseUrl = 'http://127.0.0.1:83',
    [string]$FrontendPath = '/',
    [int]$TcpTimeoutMilliseconds = 800,
    [int]$HttpTimeoutSeconds = 10,
    [switch]$SkipHttp
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

function Join-Url {
    param(
        [string]$BaseUrl,
        [string]$Path
    )

    $trimmedBase = $BaseUrl.TrimEnd('/')
    if ([string]::IsNullOrWhiteSpace($Path) -or $Path -eq '/') {
        return "$trimmedBase/"
    }

    return "$trimmedBase/$($Path.TrimStart('/'))"
}

function Test-WebTarget {
    param(
        [string]$Name,
        [string]$BaseUrl,
        [string]$Path,
        [int]$TcpTimeoutMilliseconds,
        [int]$HttpTimeoutSeconds,
        [bool]$SkipHttp
    )

    $uri = [System.Uri]$BaseUrl
    $port = if ($uri.IsDefaultPort) {
        if ($uri.Scheme -eq 'https') { 443 } else { 80 }
    } else {
        $uri.Port
    }

    $hostName = $uri.Host
    $tcpReady = Test-TcpPort -HostName $hostName -Port $port -TimeoutMilliseconds $TcpTimeoutMilliseconds
    $tcpStatus = if ($tcpReady) { 'LISTENING' } else { 'UNAVAILABLE' }
    Write-Host "$Name TCP $hostName`:$port $tcpStatus"

    if (-not $tcpReady) {
        return $false
    }

    if ($SkipHttp) {
        return $true
    }

    $url = Join-Url -BaseUrl $BaseUrl -Path $Path
    try {
        $response = Invoke-WebRequest -Uri $url -Method GET -UseBasicParsing -TimeoutSec $HttpTimeoutSeconds
        $statusCode = [int]$response.StatusCode
        $httpReady = $statusCode -ge 200 -and $statusCode -lt 400
        $httpStatus = if ($httpReady) { 'OK' } else { "HTTP_$statusCode" }
        Write-Host "$Name HTTP $url $httpStatus"
        return $httpReady
    } catch {
        Write-Host "$Name HTTP $url FAILED"
        return $false
    }
}

$allReady = $true

$backendReady = Test-WebTarget `
    -Name 'Backend' `
    -BaseUrl $BackendBaseUrl `
    -Path $BackendPath `
    -TcpTimeoutMilliseconds $TcpTimeoutMilliseconds `
    -HttpTimeoutSeconds $HttpTimeoutSeconds `
    -SkipHttp ([bool]$SkipHttp)

if (-not $backendReady) {
    $allReady = $false
}

$frontendReady = Test-WebTarget `
    -Name 'Frontend' `
    -BaseUrl $FrontendBaseUrl `
    -Path $FrontendPath `
    -TcpTimeoutMilliseconds $TcpTimeoutMilliseconds `
    -HttpTimeoutSeconds $HttpTimeoutSeconds `
    -SkipHttp ([bool]$SkipHttp)

if (-not $frontendReady) {
    $allReady = $false
}

if (-not $allReady) {
    Write-Host ''
    Write-Host 'Start local web services before browser or authenticated HTTP smoke tests:'
    Write-Host 'Backend:  php think run --host 127.0.0.1 --port 82'
    Write-Host 'Frontend: Set-Location .\snowy-admin-web; npm run dev'
    Write-Host 'Do not append extra host/port arguments to npm run dev; the project script and .env.development already define them.'
    exit 1
}
