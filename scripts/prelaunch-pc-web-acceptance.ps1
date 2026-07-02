param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Password = '123456',
    [string]$ChromePath = 'C:\Program Files\Google\Chrome\Application\chrome.exe',
    [switch]$RunControlledWrites,
    [switch]$SkipControlledWrites,
    [switch]$SkipRolePermission,
    [switch]$SkipBrowserPages,
    [int]$InitialWaitMs = 16000,
    [int]$AfterClickWaitMs = 8000,
    [string]$SshHost = '120.24.76.240',
    [int]$SshPort = 22,
    [string]$SshUser = 'root',
    [string]$SshKeyPath = 'C:\Users\Win10\.ssh\oa_fucity_deploy',
    [string]$RemoteRoot = '/www/wwwroot/oa.fucity.cn'
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

if ($RunControlledWrites -and $SkipControlledWrites) {
    throw 'Use either -RunControlledWrites or -SkipControlledWrites, not both.'
}

$results = New-Object System.Collections.Generic.List[object]

function Add-Result {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string]$Status,
        [string]$Detail = ''
    )

    $script:results.Add([pscustomobject]@{
        name = $Name
        status = $Status
        detail = $Detail
    }) | Out-Null
}

function Invoke-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )

    Write-Host "==> $Name"
    try {
        & $Action
        Add-Result -Name $Name -Status 'PASS'
        Write-Host "PASS $Name"
    } catch {
        Add-Result -Name $Name -Status 'FAIL' -Detail $_.Exception.Message
        Write-Host "FAIL $Name"
        Write-Host $_.Exception.Message
        throw
    }
}

$pcRolePages = @(
    @{ role = 'sales'; account = 'csyw001'; path = '/biz/customer'; minRows = 1 },
    @{ role = 'sales'; account = 'csyw001'; path = '/biz/saleproject'; minRows = 1 },
    @{ role = 'sales'; account = 'csyw001'; path = '/biz/copytask'; minRows = 0 },
    @{ role = 'finance'; account = 'cscw001'; path = '/biz/settlementaccount'; minRows = 1 },
    @{ role = 'finance'; account = 'cscw001'; path = '/biz/paymentrecord'; minRows = 1 },
    @{ role = 'finance'; account = 'cscw001'; path = '/biz/bizexpenditurerecord'; minRows = 1 },
    @{ role = 'finance'; account = 'cscw001'; path = '/biz/bizcollectionreceipt'; minRows = 1 },
    @{ role = 'finance'; account = 'cscw001'; path = '/biz/bizdebitnote'; minRows = 1 },
    @{ role = 'executive'; account = 'cszjb001'; path = '/biz/bizpayroll'; minRows = 1 },
    @{ role = 'executive'; account = 'cszjb001'; path = '/biz/bizleaveapplication'; minRows = 1 },
    @{ role = 'tech'; account = 'csjs001'; path = '/biz/bizproduct'; minRows = 1 },
    @{ role = 'tech'; account = 'csjs001'; path = '/biz/inventory'; minRows = 1 },
    @{ role = 'tech'; account = 'csjs001'; path = '/biz/saleprojectproductinfo'; minRows = 1 },
    @{ role = 'admin'; account = 'superAdminTwo'; path = '/sys/org'; minRows = 1 },
    @{ role = 'admin'; account = 'superAdminTwo'; path = '/sys/user'; minRows = 1 },
    @{ role = 'admin'; account = 'superAdminTwo'; path = '/sys/position'; minRows = 1 },
    @{ role = 'admin'; account = 'superAdminTwo'; path = '/sys/role'; minRows = 1 }
)

if (-not $SkipRolePermission) {
    Invoke-Step -Name 'Online role login/menu/API permission smoke' -Action {
        & .\scripts\online-role-permission-smoke.ps1 `
            -FrontendBaseUrl $FrontendBaseUrl `
            -ApiPrefix $ApiPrefix `
            -TenantId $TenantId `
            -Password $Password
    }
} else {
    Add-Result -Name 'Online role login/menu/API permission smoke' -Status 'SKIP' -Detail 'Skipped by -SkipRolePermission.'
}

if (-not $SkipBrowserPages) {
    foreach ($page in $pcRolePages) {
        $name = "PC role browser page [$($page.role)] $($page.account) $($page.path)"
        Invoke-Step -Name $name -Action {
            & .\scripts\online-role-browser-smoke.ps1 `
                -FrontendBaseUrl $FrontendBaseUrl `
                -ApiPrefix $ApiPrefix `
                -TenantId $TenantId `
                -Password $Password `
                -ChromePath $ChromePath `
                -InitialWaitMs $InitialWaitMs `
                -AfterClickWaitMs $AfterClickWaitMs `
                -Account $page.account `
                -TargetPath $page.path `
                -MinRows ([int]$page.minRows)
        }
    }
} else {
    Add-Result -Name 'PC role browser pages' -Status 'SKIP' -Detail 'Skipped by -SkipBrowserPages.'
}

if ($RunControlledWrites) {
    Invoke-Step -Name 'Online controlled write smoke: customer follow-up, leave, software package' -Action {
        & .\scripts\online-controlled-write-smoke.ps1 `
            -FrontendBaseUrl $FrontendBaseUrl `
            -ApiPrefix $ApiPrefix `
            -TenantId $TenantId `
            -Password $Password
    }

    Invoke-Step -Name 'Online finance controlled write smoke' -Action {
        & .\scripts\online-finance-controlled-write-smoke.ps1 `
            -FrontendBaseUrl $FrontendBaseUrl `
            -ApiPrefix $ApiPrefix `
            -TenantId $TenantId `
            -Password $Password
    }

    Invoke-Step -Name 'Online project-init workflow approve/reject/cancel smoke' -Action {
        & .\scripts\online-project-init-workflow-smoke.ps1 `
            -FrontendBaseUrl $FrontendBaseUrl `
            -ApiPrefix $ApiPrefix `
            -TenantId $TenantId `
            -Password $Password `
            -SshHost $SshHost `
            -SshPort $SshPort `
            -SshUser $SshUser `
            -SshKeyPath $SshKeyPath `
            -RemoteRoot $RemoteRoot
    }

    Invoke-Step -Name 'Online payroll controlled smoke' -Action {
        & .\scripts\online-payroll-controlled-smoke.ps1 `
            -FrontendBaseUrl $FrontendBaseUrl `
            -ApiPrefix $ApiPrefix `
            -TenantId $TenantId `
            -Password $Password `
            -SshHost $SshHost `
            -SshPort $SshPort `
            -SshUser $SshUser `
            -SshKeyPath $SshKeyPath `
            -RemoteRoot $RemoteRoot
    }
} elseif ($SkipControlledWrites) {
    Add-Result -Name 'Online controlled write/API smokes' -Status 'SKIP' -Detail 'Skipped by -SkipControlledWrites.'
} else {
    Add-Result -Name 'Online controlled write/API smokes' -Status 'SKIP' -Detail 'Add -RunControlledWrites after backup/log readiness to execute target write smokes.'
}

$summary = [pscustomobject]@{
    ok = -not ($results | Where-Object { $_.status -eq 'FAIL' })
    frontendBaseUrl = $FrontendBaseUrl
    apiPrefix = $ApiPrefix
    tenantId = $TenantId
    mobileExcluded = $true
    controlledWritesExecuted = [bool]$RunControlledWrites
    results = $results
}

$summary | ConvertTo-Json -Depth 8
