param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$results = New-Object System.Collections.Generic.List[object]

function Convert-JsonResponse {
    param([Parameter(Mandatory = $true)][string]$Text)

    $clean = $Text.TrimStart([char]0xFEFF).Trim()
    try {
        return $clean | ConvertFrom-Json
    } catch {
        $match = [regex]::Match($clean, '"code"\s*:\s*"?([^",}\s]+)"?')
        if ($match.Success) {
            $msgMatch = [regex]::Match($clean, '"msg"\s*:\s*"([^"]*)"')
            return [pscustomobject]@{
                code = $match.Groups[1].Value
                msg = if ($msgMatch.Success) { $msgMatch.Groups[1].Value } else { 'raw JSON parsed for code only' }
                data = $null
            }
        }
        throw
    }
}

function Convert-ErrorResponse {
    param([Parameter(Mandatory = $true)]$ErrorRecord)

    $response = $ErrorRecord.Exception.Response
    if ($null -ne $response) {
        try {
            $stream = $response.GetResponseStream()
            if ($null -ne $stream) {
                $reader = [System.IO.StreamReader]::new($stream)
                try {
                    $text = $reader.ReadToEnd()
                } finally {
                    $reader.Dispose()
                }
                if (-not [string]::IsNullOrWhiteSpace($text)) {
                    try {
                        return Convert-JsonResponse -Text $text
                    } catch {
                    }
                }
            }
        } catch {
        }
    }

    return [pscustomobject]@{
        code = 'http-error'
        msg = $ErrorRecord.Exception.Message
        data = $null
    }
}

function Invoke-OaApi {
    param(
        [Parameter(Mandatory = $true)][string]$Method,
        [Parameter(Mandatory = $true)][string]$Path,
        [hashtable]$Headers = @{},
        [object]$Body = $null,
        [switch]$AllowFailure
    )

    $uri = $script:baseUrl + $Path
    $allHeaders = @{}
    foreach ($key in $Headers.Keys) {
        $allHeaders[$key] = $Headers[$key]
    }
    if (-not $allHeaders.ContainsKey('tenantId')) {
        $allHeaders['tenantId'] = $script:TenantId
    }

    try {
        if ($Method.ToUpperInvariant() -eq 'GET') {
            $response = Invoke-RestMethod -Method Get -Uri $uri -Headers $allHeaders
        } else {
            $json = if ($null -eq $Body) { '{}' } else { ConvertTo-Json -InputObject $Body -Depth 30 -Compress }
            $response = Invoke-RestMethod -Method $Method -Uri $uri -Headers $allHeaders -ContentType 'application/json' -Body $json
        }
    } catch {
        if ($AllowFailure) {
            return Convert-ErrorResponse -ErrorRecord $_
        }
        throw
    }

    if ($response -is [string]) {
        $response = Convert-JsonResponse -Text $response
    }

    if (-not $AllowFailure -and [int]$response.code -ne 200) {
        throw "$Method $Path failed: code=$($response.code) msg=$($response.msg)"
    }

    return $response
}

function New-Session {
    param([Parameter(Mandatory = $true)][string]$Account)

    $login = Invoke-OaApi -Method POST -Path '/auth/b/doLogin' -Body @{
        account = $Account
        password = $script:Password
        tenantId = $script:TenantId
        device = 'CODEX_ONLINE_ROLE_PERMISSION_SMOKE'
    }
    if ([string]::IsNullOrWhiteSpace([string]$login.data)) {
        throw "login returned empty token for $Account"
    }

    $headers = @{
        Authorization = "Bearer $($login.data)"
        tenantId = $script:TenantId
    }

    return [pscustomobject]@{
        Account = $Account
        Headers = $headers
        User = (Invoke-OaApi -Method GET -Path '/auth/b/getLoginUser' -Headers $headers).data
        Menu = (Invoke-OaApi -Method GET -Path '/sys/userCenter/loginMenu' -Headers $headers).data
    }
}

function Count-MenuNodes {
    param([object]$Nodes)

    if ($null -eq $Nodes) {
        return 0
    }

    $count = 0
    foreach ($node in @($Nodes)) {
        if ($null -eq $node) {
            continue
        }
        $count++
        if ($node.PSObject.Properties.Name -contains 'children') {
            $count += Count-MenuNodes -Nodes $node.children
        }
    }
    return $count
}

function Assert-Code {
    param(
        [object]$Response,
        [int]$Expected,
        [string]$Name
    )

    if ([int]$Response.code -ne $Expected) {
        throw "$Name expected code=$Expected, got code=$($Response.code) msg=$($Response.msg)"
    }
}

function Invoke-CheckedGet {
    param(
        [object]$Session,
        [string]$Path,
        [int]$ExpectedCode
    )

    $response = Invoke-OaApi -Method GET -Path $Path -Headers $Session.Headers -AllowFailure
    Assert-Code -Response $response -Expected $ExpectedCode -Name "$($Session.Account) GET $Path"
    $script:results.Add([pscustomobject]@{
        account = $Session.Account
        path = $Path
        expectedCode = $ExpectedCode
        actualCode = [int]$response.code
    }) | Out-Null
}

$roleChecks = @(
    @{
        account = 'superAdminTwo'
        minMenuNodes = 60
        allowed = @('/sys/org/page?current=1&size=1', '/sys/user/page?current=1&size=1', '/sys/position/page?current=1&size=1', '/sys/role/page?current=1&size=1')
        denied = @()
    },
    @{
        account = 'csyw001'
        minMenuNodes = 20
        allowed = @('/biz/customer/page?current=1&size=1', '/biz/saleproject/page?current=1&size=1', '/biz/task/page?current=1&size=1', '/biz/ccrecords/page?current=1&size=1')
        denied = @('/biz/bizpayroll/page?current=1&size=1')
    },
    @{
        account = 'cscw001'
        minMenuNodes = 15
        allowed = @('/biz/settlementaccount/page?current=1&size=1', '/biz/bizcollectionreceipt/page?current=1&size=1', '/biz/bizdebitnote/page?current=1&size=1', '/biz/bizpaymentrecord/page?current=1&size=1', '/biz/bizexpenditurerecord/page?current=1&size=1', '/biz/bizpayroll/page?current=1&size=1')
        denied = @('/biz/customer/page?current=1&size=1')
    },
    @{
        account = 'cszjb001'
        minMenuNodes = 10
        allowed = @('/biz/bizpayroll/page?current=1&size=1', '/biz/bizleaveapplication/page?current=1&size=1', '/biz/task/page?current=1&size=1')
        denied = @('/biz/customer/page?current=1&size=1')
    },
    @{
        account = 'csjs001'
        minMenuNodes = 20
        allowed = @('/biz/task/page?current=1&size=1', '/biz/saleproject/page?current=1&size=1', '/biz/saleprojectproductinfo/page?current=1&size=1', '/biz/bizproduct/page?current=1&size=1', '/biz/warehouses/list')
        denied = @('/biz/bizpayroll/page?current=1&size=1')
    }
)

$sessions = New-Object System.Collections.Generic.List[object]

foreach ($check in $roleChecks) {
    $session = New-Session -Account $check.account
    $sessions.Add($session) | Out-Null
    $menuCount = Count-MenuNodes -Nodes $session.Menu
    if ($menuCount -lt [int]$check.minMenuNodes) {
        throw "$($check.account) expected at least $($check.minMenuNodes) menu nodes, got $menuCount"
    }
    $results.Add([pscustomobject]@{
        account = $check.account
        path = '/sys/userCenter/loginMenu'
        expectedCode = 200
        actualCode = 200
        menuNodes = $menuCount
    }) | Out-Null

    foreach ($path in @($check.allowed)) {
        Invoke-CheckedGet -Session $session -Path $path -ExpectedCode 200
    }

    foreach ($path in @($check.denied)) {
        Invoke-CheckedGet -Session $session -Path $path -ExpectedCode 403
    }
}

[pscustomobject]@{
    ok = $true
    frontendBaseUrl = $FrontendBaseUrl
    apiPrefix = $ApiPrefix
    tenantId = $TenantId
    accounts = @($sessions | ForEach-Object { $_.Account })
    checks = $results
} | ConvertTo-Json -Depth 8
