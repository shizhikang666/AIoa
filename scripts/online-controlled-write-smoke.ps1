param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$marker = 'CODEX_WRITE_SMOKE_' + (Get-Date -Format 'yyyyMMddHHmmss')

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
            return [pscustomobject]@{
                code = 'http-error'
                msg = $_.Exception.Message
                data = $null
            }
        }
        throw
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
        device = 'CODEX_ONLINE_CONTROLLED_WRITE_SMOKE'
    }
    if ([string]::IsNullOrWhiteSpace([string]$login.data)) {
        throw "login returned empty token for $Account"
    }

    $headers = @{
        Authorization = "Bearer $($login.data)"
        tenantId = $script:TenantId
    }
    $user = Invoke-OaApi -Method GET -Path '/auth/b/getLoginUser' -Headers $headers

    return [pscustomobject]@{
        Account = $Account
        Headers = $headers
        User = $user.data.user
    }
}

function Assert-Equal {
    param(
        [object]$Actual,
        [object]$Expected,
        [string]$Name
    )
    if ([string]$Actual -ne [string]$Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function Get-ArrayCount {
    param([object]$Value)
    if ($null -eq $Value) {
        return 0
    }
    if ($Value -is [array]) {
        return $Value.Count
    }
    return 1
}

$created = New-Object System.Collections.Generic.List[object]
$results = New-Object System.Collections.Generic.List[object]

try {
    $sales = New-Session -Account 'csyw001'
    $customerPage = Invoke-OaApi -Method GET -Path '/biz/customer/page?current=1&size=1' -Headers $sales.Headers
    if (-not $customerPage.data.records -or $customerPage.data.records.Count -lt 1) {
        throw 'sales customer page returned no records'
    }
    $customerId = [string]$customerPage.data.records[0].id
    $followContent = "$marker customer follow-up"
    $followAdd = Invoke-OaApi -Method POST -Path '/biz/customerfollowup/add' -Headers $sales.Headers -Body @{
        customerId = $customerId
        followUpTime = (Get-Date).ToString('yyyy-MM-dd HH:mm:ss')
        content = $followContent
        extJson = (@{ marker = $marker; smoke = 'customerFollowUp' } | ConvertTo-Json -Compress)
    }
    $followId = [string]$followAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'customerFollowUp'; id = $followId; headers = $sales.Headers }) | Out-Null
    $followDetail = Invoke-OaApi -Method GET -Path ("/biz/customerfollowup/detail?id={0}" -f [uri]::EscapeDataString($followId)) -Headers $sales.Headers
    Assert-Equal -Actual $followDetail.data.content -Expected $followContent -Name 'customer follow-up content'
    $followDelete = Invoke-OaApi -Method POST -Path '/biz/customerfollowup/delete' -Headers $sales.Headers -Body @{ id = $followId }
    Assert-Equal -Actual $followDelete.data.count -Expected 1 -Name 'customer follow-up delete count'
    $created.RemoveAt($created.Count - 1)
    $followPageAfter = Invoke-OaApi -Method GET -Path ("/biz/customerfollowup/page?current=1&size=10&content={0}" -f [uri]::EscapeDataString($followContent)) -Headers $sales.Headers
    Assert-Equal -Actual $followPageAfter.data.total -Expected 0 -Name 'customer follow-up after delete total'
    $results.Add([pscustomobject]@{ scope = 'sales'; account = 'csyw001'; endpoint = '/biz/customerfollowup/add/delete'; id = $followId; ok = $true }) | Out-Null

    $hr = New-Session -Account 'cszjb001'
    $hrUserId = [string]$hr.User.ID
    if ([string]::IsNullOrWhiteSpace($hrUserId)) {
        throw 'hr login user id is empty'
    }
    $leaveRemark = "$marker leave"
    $leaveStart = '2026-12-30 09:00:00'
    $leaveEnd = '2026-12-30 12:00:00'
    $leaveAdd = Invoke-OaApi -Method POST -Path '/biz/bizleaveapplication/add' -Headers $hr.Headers -Body @{
        userId = $hrUserId
        processId = "$marker-LEAVE"
        category = 'leave'
        amount = '0.50'
        startTime = $leaveStart
        endTime = $leaveEnd
        remark = $leaveRemark
        objectId = ''
    }
    $leaveId = [string]$leaveAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'leave'; id = $leaveId; headers = $hr.Headers }) | Out-Null
    $leaveDetail = Invoke-OaApi -Method GET -Path ("/biz/bizleaveapplication/detail?id={0}" -f [uri]::EscapeDataString($leaveId)) -Headers $hr.Headers
    Assert-Equal -Actual $leaveDetail.data.remark -Expected $leaveRemark -Name 'leave remark'
    $leaveDelete = Invoke-OaApi -Method POST -Path '/biz/bizleaveapplication/delete' -Headers $hr.Headers -Body @{ id = $leaveId }
    Assert-Equal -Actual $leaveDelete.data.count -Expected 1 -Name 'leave delete count'
    $created.RemoveAt($created.Count - 1)
    $leavePageAfter = Invoke-OaApi -Method GET -Path ("/biz/bizleaveapplication/page?current=1&size=10&searchKey={0}" -f [uri]::EscapeDataString($leaveRemark)) -Headers $hr.Headers
    Assert-Equal -Actual $leavePageAfter.data.total -Expected 0 -Name 'leave after delete total'
    $results.Add([pscustomobject]@{ scope = 'hr'; account = 'cszjb001'; endpoint = '/biz/bizleaveapplication/add/delete'; id = $leaveId; ok = $true }) | Out-Null

    $tech = New-Session -Account 'csjs001'
    $productPage = Invoke-OaApi -Method GET -Path '/biz/bizproduct/page?current=1&size=1' -Headers $tech.Headers
    if (-not $productPage.data.records -or $productPage.data.records.Count -lt 1) {
        throw 'tech product page returned no records'
    }
    $productId = [string]$productPage.data.records[0].id
    $packageContent = "$marker package content"
    $packageAdd = Invoke-OaApi -Method POST -Path '/biz/saleprojectproductinfo/add' -Headers $tech.Headers -Body @{
        productId = $productId
        targetId = $productId
        contentText = $packageContent
        alias = 'Codex controlled package'
        versionType = 'smoke'
        versionRemark = $marker
        abbreviation = 'CW'
        hardware = 'demo'
        oldCode = "$marker-OLD"
        remark = "$marker package remark"
        extJson = @{ marker = $marker; smoke = 'saleProjectProductInfo' }
    }
    $packageId = [string]$packageAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'package'; id = $packageId; headers = $tech.Headers }) | Out-Null
    $packageDetail = Invoke-OaApi -Method GET -Path ("/biz/saleprojectproductinfo/detail?id={0}" -f [uri]::EscapeDataString($packageId)) -Headers $tech.Headers
    Assert-Equal -Actual $packageDetail.data.contentText -Expected $packageContent -Name 'package content'
    $packageDelete = Invoke-OaApi -Method POST -Path '/biz/saleprojectproductinfo/delete' -Headers $tech.Headers -Body @{ id = $packageId }
    Assert-Equal -Actual $packageDelete.data.count -Expected 1 -Name 'package delete count'
    $created.RemoveAt($created.Count - 1)
    $packageListAfter = Invoke-OaApi -Method GET -Path ("/biz/saleprojectproductinfo/list?targetIds={0}&searchKey={1}" -f [uri]::EscapeDataString($productId), [uri]::EscapeDataString($packageContent)) -Headers $tech.Headers
    Assert-Equal -Actual (Get-ArrayCount $packageListAfter.data) -Expected 0 -Name 'package after delete count'
    $results.Add([pscustomobject]@{ scope = 'tech'; account = 'csjs001'; endpoint = '/biz/saleprojectproductinfo/add/delete'; id = $packageId; ok = $true }) | Out-Null

    [pscustomobject]@{
        ok = $true
        marker = $marker
        results = $results
    } | ConvertTo-Json -Depth 10
} finally {
    for ($i = $created.Count - 1; $i -ge 0; $i--) {
        $item = $created[$i]
        try {
            if ($item.kind -eq 'customerFollowUp') {
                Invoke-OaApi -Method POST -Path '/biz/customerfollowup/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            } elseif ($item.kind -eq 'leave') {
                Invoke-OaApi -Method POST -Path '/biz/bizleaveapplication/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            } elseif ($item.kind -eq 'package') {
                Invoke-OaApi -Method POST -Path '/biz/saleprojectproductinfo/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            }
        } catch {
            Write-Warning "cleanup failed for $($item.kind) $($item.id): $($_.Exception.Message)"
        }
    }
}
