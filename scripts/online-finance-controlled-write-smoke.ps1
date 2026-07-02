param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$marker = 'CODEX_FINANCE_SMOKE_' + (Get-Date -Format 'yyyyMMddHHmmss')
$shortObjectPrefix = 'CF' + (Get-Date -Format 'MMddHHmmss')

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
        device = 'CODEX_ONLINE_FINANCE_WRITE_SMOKE'
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

function Assert-DecimalEqual {
    param(
        [object]$Actual,
        [object]$Expected,
        [string]$Name
    )
    if ([decimal]$Actual -ne [decimal]$Expected) {
        throw "$Name expected '$Expected', got '$Actual'"
    }
}

function Assert-Gone {
    param(
        [object]$Response,
        [string]$Name
    )
    if ([string]$Response.code -eq '200') {
        throw "$Name should be gone, got code=200"
    }
}

$created = New-Object System.Collections.Generic.List[object]
$results = New-Object System.Collections.Generic.List[object]

try {
    $finance = New-Session -Account 'cscw001'
    $headers = $finance.Headers
    $accountName = "$marker account"
    $payerTime = '2026-12-31 10:00:00'

    $accountAdd = Invoke-OaApi -Method POST -Path '/biz/settlementaccount/add' -Headers $headers -Body @{
        accountName = $accountName
        accountNumber = "$marker-NO"
        initialAmount = '1000.00'
        accountStatus = 'ENABLE'
        sortCode = 999
    }
    $accountId = [string]$accountAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'account'; id = $accountId; headers = $headers }) | Out-Null

    $accountDetail = Invoke-OaApi -Method GET -Path ("/biz/settlementaccount/detail?id={0}" -f [uri]::EscapeDataString($accountId)) -Headers $headers
    Assert-Equal -Actual $accountDetail.data.accountName -Expected $accountName -Name 'settlement account name'
    Assert-DecimalEqual -Actual $accountDetail.data.currentAmount -Expected '1000.00' -Name 'settlement account initial current amount'
    $results.Add([pscustomobject]@{ scope = 'finance'; account = 'cscw001'; endpoint = '/biz/settlementaccount/add'; id = $accountId; ok = $true }) | Out-Null

    $paymentRemark = "$marker payment"
    $paymentAdd = Invoke-OaApi -Method POST -Path '/biz/settlementaccount/payment/add' -Headers $headers -Body @{
        targetId = $accountId
        settlementCategory = 'CODX_INCOME'
        payer = 'codex finance payer'
        payerTime = $payerTime
        amount = '120.50'
        objectId = "$shortObjectPrefix-PAY"
        bankName = 'codex finance bank'
        bankAccount = 'codex finance account'
        remark = $paymentRemark
    }
    $paymentId = [string]$paymentAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'payment'; id = $paymentId; headers = $headers }) | Out-Null

    $paymentDetail = Invoke-OaApi -Method GET -Path ("/biz/bizpaymentrecord/detail?id={0}" -f [uri]::EscapeDataString($paymentId)) -Headers $headers
    Assert-Equal -Actual $paymentDetail.data.remark -Expected $paymentRemark -Name 'payment remark'
    Assert-DecimalEqual -Actual $paymentDetail.data.amount -Expected '120.50' -Name 'payment amount'
    Assert-DecimalEqual -Actual $paymentAdd.data.beforeAmount -Expected '1000.00' -Name 'payment before amount'
    Assert-DecimalEqual -Actual $paymentAdd.data.afterAmount -Expected '1120.50' -Name 'payment after amount'
    $results.Add([pscustomobject]@{ scope = 'finance'; account = 'cscw001'; endpoint = '/biz/settlementaccount/payment/add'; id = $paymentId; ok = $true }) | Out-Null

    $receiptRemark = "$marker collection receipt"
    $receiptAdd = Invoke-OaApi -Method POST -Path '/biz/bizcollectionreceipt/add' -Headers $headers -Body @{
        paymentRecordId = $paymentId
        amount = '50.00'
        settlementAmount = '0.00'
        remark = $receiptRemark
    }
    $receiptId = [string]$receiptAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'receipt'; id = $receiptId; headers = $headers }) | Out-Null

    $receiptDetail = Invoke-OaApi -Method GET -Path ("/biz/bizcollectionreceipt/detail?id={0}" -f [uri]::EscapeDataString($receiptId)) -Headers $headers
    Assert-Equal -Actual $receiptDetail.data.remark -Expected $receiptRemark -Name 'collection receipt remark'
    Assert-DecimalEqual -Actual $receiptDetail.data.amount -Expected '50.00' -Name 'collection receipt amount'
    Assert-DecimalEqual -Actual $receiptDetail.data.settlementAmount -Expected '0.00' -Name 'collection receipt settlement amount'
    $results.Add([pscustomobject]@{ scope = 'finance'; account = 'cscw001'; endpoint = '/biz/bizcollectionreceipt/add'; id = $receiptId; ok = $true }) | Out-Null

    $expenditureRemark = "$marker expenditure"
    $expenditureAdd = Invoke-OaApi -Method POST -Path '/biz/settlementaccount/expenses/add' -Headers $headers -Body @{
        targetId = $accountId
        settlementCategory = 'CODX_EXPENSE'
        payer = 'codex finance receiver'
        payerTime = $payerTime
        amount = '70.25'
        objectId = "$shortObjectPrefix-EXP"
        bankName = 'codex expense bank'
        bankAccount = 'codex expense account'
        remark = $expenditureRemark
    }
    $expenditureId = [string]$expenditureAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'expenditure'; id = $expenditureId; headers = $headers }) | Out-Null

    $expenditureDetail = Invoke-OaApi -Method GET -Path ("/biz/bizexpenditurerecord/detail?id={0}" -f [uri]::EscapeDataString($expenditureId)) -Headers $headers
    Assert-Equal -Actual $expenditureDetail.data.remark -Expected $expenditureRemark -Name 'expenditure remark'
    Assert-DecimalEqual -Actual $expenditureDetail.data.amount -Expected '70.25' -Name 'expenditure amount'
    Assert-DecimalEqual -Actual $expenditureAdd.data.beforeAmount -Expected '1120.50' -Name 'expenditure before amount'
    Assert-DecimalEqual -Actual $expenditureAdd.data.afterAmount -Expected '1050.25' -Name 'expenditure after amount'
    $results.Add([pscustomobject]@{ scope = 'finance'; account = 'cscw001'; endpoint = '/biz/settlementaccount/expenses/add'; id = $expenditureId; ok = $true }) | Out-Null

    $debitRemark = "$marker debit note"
    $debitAdd = Invoke-OaApi -Method POST -Path '/biz/bizdebitnote/add' -Headers $headers -Body @{
        expenditureRecordId = $expenditureId
        amount = '20.00'
        settlementAmount = '0.00'
        remark = $debitRemark
    }
    $debitId = [string]$debitAdd.data.id
    $created.Add([pscustomobject]@{ kind = 'debit'; id = $debitId; headers = $headers }) | Out-Null

    $debitDetail = Invoke-OaApi -Method GET -Path ("/biz/bizdebitnote/detail?id={0}" -f [uri]::EscapeDataString($debitId)) -Headers $headers
    Assert-Equal -Actual $debitDetail.data.remark -Expected $debitRemark -Name 'debit note remark'
    Assert-DecimalEqual -Actual $debitDetail.data.amount -Expected '20.00' -Name 'debit note amount'
    Assert-DecimalEqual -Actual $debitDetail.data.settlementAmount -Expected '0.00' -Name 'debit note settlement amount'
    $results.Add([pscustomobject]@{ scope = 'finance'; account = 'cscw001'; endpoint = '/biz/bizdebitnote/add'; id = $debitId; ok = $true }) | Out-Null

    $debitDelete = Invoke-OaApi -Method POST -Path '/biz/bizdebitnote/delete' -Headers $headers -Body @{ id = $debitId }
    Assert-Equal -Actual $debitDelete.data.count -Expected 1 -Name 'debit note delete count'
    $created.RemoveAt($created.Count - 1)
    Assert-Gone -Response (Invoke-OaApi -Method GET -Path ("/biz/bizdebitnote/detail?id={0}" -f [uri]::EscapeDataString($debitId)) -Headers $headers -AllowFailure) -Name 'debit note detail after delete'

    $expenditureDelete = Invoke-OaApi -Method POST -Path '/biz/bizexpenditurerecord/delete' -Headers $headers -Body @{ id = $expenditureId }
    Assert-Equal -Actual $expenditureDelete.data.count -Expected 1 -Name 'expenditure delete count'
    $created.RemoveAt($created.Count - 1)
    Assert-Gone -Response (Invoke-OaApi -Method GET -Path ("/biz/bizexpenditurerecord/detail?id={0}" -f [uri]::EscapeDataString($expenditureId)) -Headers $headers -AllowFailure) -Name 'expenditure detail after delete'

    $receiptDelete = Invoke-OaApi -Method POST -Path '/biz/bizcollectionreceipt/delete' -Headers $headers -Body @{ id = $receiptId }
    Assert-Equal -Actual $receiptDelete.data.count -Expected 1 -Name 'collection receipt delete count'
    $created.RemoveAt($created.Count - 1)
    Assert-Gone -Response (Invoke-OaApi -Method GET -Path ("/biz/bizcollectionreceipt/detail?id={0}" -f [uri]::EscapeDataString($receiptId)) -Headers $headers -AllowFailure) -Name 'collection receipt detail after delete'

    $paymentDelete = Invoke-OaApi -Method POST -Path '/biz/bizpaymentrecord/delete' -Headers $headers -Body @{ id = $paymentId }
    Assert-Equal -Actual $paymentDelete.data.count -Expected 1 -Name 'payment delete count'
    $created.RemoveAt($created.Count - 1)
    Assert-Gone -Response (Invoke-OaApi -Method GET -Path ("/biz/bizpaymentrecord/detail?id={0}" -f [uri]::EscapeDataString($paymentId)) -Headers $headers -AllowFailure) -Name 'payment detail after delete'

    $accountAfterRecords = Invoke-OaApi -Method GET -Path ("/biz/settlementaccount/detail?id={0}" -f [uri]::EscapeDataString($accountId)) -Headers $headers
    Assert-DecimalEqual -Actual $accountAfterRecords.data.currentAmount -Expected '1000.00' -Name 'settlement account current amount after record deletes'

    $accountDelete = Invoke-OaApi -Method POST -Path '/biz/settlementaccount/delete' -Headers $headers -Body @{ id = $accountId }
    Assert-Equal -Actual $accountDelete.data.count -Expected 1 -Name 'settlement account delete count'
    $created.RemoveAt($created.Count - 1)
    Assert-Gone -Response (Invoke-OaApi -Method GET -Path ("/biz/settlementaccount/detail?id={0}" -f [uri]::EscapeDataString($accountId)) -Headers $headers -AllowFailure) -Name 'settlement account detail after delete'

    [pscustomobject]@{
        ok = $true
        marker = $marker
        results = $results
        cleanup = [pscustomobject]@{
            debitNoteDeleted = $debitId
            expenditureDeleted = $expenditureId
            collectionReceiptDeleted = $receiptId
            paymentRecordDeleted = $paymentId
            settlementAccountDeleted = $accountId
            accountAmountRestoredBeforeAccountDelete = '1000.00'
        }
    } | ConvertTo-Json -Depth 10
} finally {
    for ($i = $created.Count - 1; $i -ge 0; $i--) {
        $item = $created[$i]
        try {
            if ($item.kind -eq 'debit') {
                Invoke-OaApi -Method POST -Path '/biz/bizdebitnote/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            } elseif ($item.kind -eq 'expenditure') {
                Invoke-OaApi -Method POST -Path '/biz/bizexpenditurerecord/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            } elseif ($item.kind -eq 'receipt') {
                Invoke-OaApi -Method POST -Path '/biz/bizcollectionreceipt/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            } elseif ($item.kind -eq 'payment') {
                Invoke-OaApi -Method POST -Path '/biz/bizpaymentrecord/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            } elseif ($item.kind -eq 'account') {
                Invoke-OaApi -Method POST -Path '/biz/settlementaccount/delete' -Headers $item.headers -Body @{ id = $item.id } -AllowFailure | Out-Null
            }
        } catch {
            Write-Warning "cleanup failed for $($item.kind) $($item.id): $($_.Exception.Message)"
        }
    }
}
