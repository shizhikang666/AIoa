param(
    [int]$DashboardLines = 90,
    [int]$ProblemLines = 80,
    [int]$StatusTail = 140,
    [switch]$IncludeWorktreeSummary,
    [switch]$CheckRuntime,
    [switch]$CheckWeb,
    [switch]$SkipStatusTail,
    [switch]$Lean
)

$ErrorActionPreference = 'Stop'

if ($Lean) {
    if (-not $PSBoundParameters.ContainsKey('DashboardLines')) {
        $DashboardLines = 35
    }

    if (-not $PSBoundParameters.ContainsKey('ProblemLines')) {
        $ProblemLines = 20
    }

    $SkipStatusTail = $true
}

function Write-Section {
    param([string]$Title)
    Write-Host ""
    Write-Host "== $Title =="
}

function Show-FileHead {
    param(
        [string]$Path,
        [int]$Lines
    )

    if (Test-Path -LiteralPath $Path) {
        Get-Content -LiteralPath $Path -TotalCount $Lines
    } else {
        Write-Host "Missing: $Path"
    }
}

function Show-FileTail {
    param(
        [string]$Path,
        [int]$Lines
    )

    if (Test-Path -LiteralPath $Path) {
        Get-Content -LiteralPath $Path -Tail $Lines
    } else {
        Write-Host "Missing: $Path"
    }
}

function Show-DashboardLean {
    $path = 'docs/tasks/refactor-progress-dashboard.md'
    if (-not (Test-Path -LiteralPath $path)) {
        Write-Host "Missing: $path"
        return
    }

    $plainPatterns = @(
        '^Last updated:',
        '^Overall production-ready completion:',
        '^Read-only API compatibility completion:'
    )

    foreach ($pattern in $plainPatterns) {
        Select-String -Path $path -Pattern $pattern | ForEach-Object { $_.Line }
    }

    $metricPatterns = @(
        '^\| Frontend endpoints already routed \|',
        '^\| Explicit safe frontend read wrappers missing \|',
        '^\| Frontend missing read/selector candidates \|',
        '^\| Frontend deferred write/side-effect candidates \|',
        '^\| Current branch \|'
    )

    foreach ($pattern in $metricPatterns) {
        Select-String -Path $path -Pattern $pattern | ForEach-Object {
            $columns = $_.Line -split '\|'
            if ($columns.Count -ge 3) {
                $name = $columns[1].Trim()
                $value = $columns[2].Trim()
                Write-Host "${name}: $value"
            }
        }
    }

    $notePatterns = @(
        '^- 2026-06-22 workflow payment approval:',
        '^- 2026-06-22 workflow payment-out approval:',
        '^- 2026-06-22 workflow procure approval:',
        '^- 2026-06-22 workflow procure warehouse approval:',
        '^- 2026-06-22 workflow project init approval:',
        '^- 2026-06-22 workflow project delivery approval:',
        '^- 2026-06-22 workflow project play approval:',
        '^- 2026-06-22 workflow general start runtime:',
        '^- 2026-06-18 sale-project product-list mutation smoke:',
        '^- 2026-06-18 sale-project invoicing write smoke:',
        '^- 2026-06-18 return-order write smoke:',
        '^- 2026-06-18 team-project task write smoke:',
        '^- 2026-06-18 sale-project foundation closure:',
        '^- 2026-06-18 feature-closure workflow baseline:',
        '^- 2026-06-18 sale-project deal edit:',
        '^- 2026-06-18 sale-project delete:',
        '^- 2026-06-18 sale-project repeal:',
        '^- 2026-06-18 sale-project cancel:',
        '^- 2026-06-18 payroll import:',
        '^- 2026-06-18 sale-project amount edit:',
        '^- 2026-06-18 sale-project visibility edit:',
        '^- 2026-06-15 selector pagination compatibility:',
        '^- 2026-06-15 continuation speed helpers:',
        '^- 2026-06-15 local preflight bundle:'
    )

    foreach ($pattern in $notePatterns) {
        Select-String -Path $path -Pattern $pattern | ForEach-Object {
            $line = $_.Line
            if ($line.Length -gt 180) {
                $line = $line.Substring(0, 177) + '...'
            }

            $line
        }
    }
}

function Convert-ProblemLine {
    param([string]$Line)

    $columns = @($Line -split '\|' | ForEach-Object { $_.Trim() } | Where-Object { $_ -ne '' })
    if ($columns.Count -lt 9) {
        return $Line
    }

    $id = $columns[0]
    $area = $columns[2]
    $problem = $columns[3]
    $status = $columns[$columns.Count - 1]
    if ($problem.Length -gt 100) {
        $problem = $problem.Substring(0, 97) + '...'
    }

    "$id | $area | $status | $problem"
}

function Show-ProblemsLean {
    $path = 'docs/tasks/problem-optimization-log.md'
    if (-not (Test-Path -LiteralPath $path)) {
        Write-Host "Missing: $path"
        return
    }

    Write-Host "Problem log: $path"
    $problemRows = Select-String -Path $path -Pattern '^\| P-\d+ '
    $openRows = $problemRows | Where-Object { $_.Line -match '\| Open \|$' }
    Write-Host "Open problems: $($openRows.Count)"

    $recentRows = $problemRows | Select-Object -Last 8
    if ($recentRows) {
        Write-Host 'Recent:'
        $recentRows | ForEach-Object { Convert-ProblemLine -Line $_.Line }
    }
}

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

Write-Section 'Repo'
Write-Host "Path: $repoRoot"
git status --short --branch

Write-Section 'Lean Startup Files'
Write-Host 'Read these only if the current task needs details:'
Write-Host '- AGENTS.md'
Write-Host '- docs/tasks/new-conversation-bootstrap.md'
Write-Host '- docs/tasks/lean-continuation-workflow.md'
Write-Host '- docs/tasks/context-handoff.md'
Write-Host '- docs/tasks/problem-optimization-log.md'
Write-Host '- docs/tasks/refactor-progress-dashboard.md'
Write-Host '- STATUS.md'

if ($Lean) {
    Write-Section 'Progress Dashboard Summary'
    Show-DashboardLean
} else {
    Write-Section 'Progress Dashboard Head'
    Show-FileHead -Path 'docs/tasks/refactor-progress-dashboard.md' -Lines $DashboardLines
}

Write-Section 'Next Execution Order'
if (Test-Path -LiteralPath 'docs/tasks/api-gap-map.md') {
    Select-String -Path 'docs/tasks/api-gap-map.md' -Pattern '^## Next Execution Order' -Context 0,12 |
        ForEach-Object { $_.Line; $_.Context.PostContext }
} else {
    Write-Host 'Missing: docs/tasks/api-gap-map.md'
}

Write-Section 'Problem Optimization Log'
if ($Lean) {
    Show-ProblemsLean
} else {
    Show-FileHead -Path 'docs/tasks/problem-optimization-log.md' -Lines $ProblemLines
    if (Test-Path -LiteralPath 'docs/tasks/problem-optimization-log.md') {
        $recentProblems = Select-String -Path 'docs/tasks/problem-optimization-log.md' -Pattern '^\| P-\d+ ' |
            Select-Object -Last 8

        if ($recentProblems) {
            Write-Section 'Recent Problem Rows'
            $recentProblems | ForEach-Object { $_.Line }
        }
    }
}

Write-Section 'Context Handoff'
Write-Host 'Open a new conversation when this thread is too large for precise inspection or the next feature block is broad/risky.'
Write-Host 'Starter doc: docs/tasks/context-handoff.md'

Write-Section 'Commit Guardrail'
Write-Host 'Do not commit unless the current user explicitly asks for a commit or the main merge/coordinator explicitly approves committing the completed feature block.'

if ($CheckRuntime) {
    Write-Section 'Runtime Readiness'
    & (Join-Path $PSScriptRoot 'runtime-ready.ps1')
}

if ($CheckWeb) {
    Write-Section 'Web Readiness'
    & (Join-Path $PSScriptRoot 'web-ready.ps1')
}

if (-not $SkipStatusTail) {
    Write-Section 'Latest STATUS Tail'
    Show-FileTail -Path 'STATUS.md' -Lines $StatusTail
}

if ($IncludeWorktreeSummary) {
    Write-Section 'Sibling Worktree Summary'
    $parent = Split-Path -Parent $repoRoot
    $mainBranch = 'refactor/thinkphp-main'
    $worktrees = @(
        'OA-auth',
        'OA-user',
        'OA-workflow',
        'OA-db',
        'OA-api',
        'OA-frontend',
        'OA-test',
        'OA-docs',
        'OA-ThinkPHP'
    )

    foreach ($name in $worktrees) {
        $path = Join-Path $parent $name
        if (-not (Test-Path -LiteralPath (Join-Path $path '.git'))) {
            continue
        }

        Push-Location $path
        try {
            $branch = git branch --show-current
            $status = git status --short
            if ($branch -eq $mainBranch) {
                Write-Host "[$name] branch=$branch integration"
            } else {
                $aheadBehind = git rev-list --left-right --count "$mainBranch...HEAD" 2>$null
                Write-Host "[$name] branch=$branch vs $mainBranch $aheadBehind"
            }

            if ($status) {
                $status
            } else {
                Write-Host 'clean'
            }
        } finally {
            Pop-Location
        }
    }
}

Write-Section 'Fast Commands'
Write-Host '.\scripts\project-progress.ps1 -Lean'
Write-Host '.\scripts\project-progress.ps1 -SkipStatusTail'
Write-Host '.\scripts\project-progress.ps1 -IncludeWorktreeSummary'
Write-Host '.\scripts\project-progress.ps1 -CheckRuntime -Lean'
Write-Host '.\scripts\project-progress.ps1 -CheckRuntime -CheckWeb -Lean'
Write-Host '.\scripts\project-preflight.ps1'
Write-Host '.\scripts\runtime-ready.ps1'
Write-Host '.\scripts\web-ready.ps1'
Write-Host '.\scripts\frontend-api-method-smoke.ps1'
Write-Host '.\scripts\frontend-api-route-gap-smoke.ps1'
Write-Host '.\scripts\frontend-deferred-write-wrapper-smoke.ps1'
Write-Host '.\scripts\role-selector-http-smoke.ps1'
Write-Host '.\scripts\tenant-read-http-smoke.ps1'
Write-Host '.\scripts\tenant-write-http-smoke.ps1'
Write-Host '.\scripts\message-sse-http-smoke.ps1'
Write-Host '.\scripts\inventory-delivery-read-http-smoke.ps1'
Write-Host '.\scripts\inventory-add-http-smoke.ps1'
Write-Host '.\scripts\delivery-record-add-http-smoke.ps1'
Write-Host '.\scripts\finance-read-http-smoke.ps1'
Write-Host '.\scripts\biz-collection-receipt-batch-expenditure-http-smoke.ps1'
Write-Host '.\scripts\biz-debit-note-history-add-http-smoke.ps1'
Write-Host '.\scripts\biz-debit-note-batch-repayment-http-smoke.ps1'
Write-Host '.\scripts\purchase-order-read-http-smoke.ps1'
Write-Host '.\scripts\purchase-order-audit-edit-http-smoke.ps1'
Write-Host '.\scripts\purchase-order-edit-http-smoke.ps1'
Write-Host '.\scripts\purchase-order-cancel-http-smoke.ps1'
Write-Host '.\scripts\purchase-order-warehouse-one-add-http-smoke.ps1'
Write-Host '.\scripts\purchase-order-warehouse-add-http-smoke.ps1'
Write-Host '.\scripts\sale-project-foundation-closure-http-smoke.ps1'
Write-Host '.\scripts\sale-project-deal-edit-http-smoke.ps1'
Write-Host '.\scripts\sale-project-delete-http-smoke.ps1'
Write-Host '.\scripts\sale-project-repeal-http-smoke.ps1'
Write-Host '.\scripts\sale-project-cancel-http-smoke.ps1'
Write-Host '.\scripts\sale-project-invoicing-write-http-smoke.ps1'
Write-Host '.\scripts\sale-project-product-item-mutation-http-smoke.ps1'
Write-Host '.\scripts\return-order-write-http-smoke.ps1'
Write-Host '.\scripts\settlement-account-expenses-add-http-smoke.ps1'
Write-Host '.\scripts\settlement-account-payment-add-http-smoke.ps1'
Write-Host '.\scripts\settlement-account-transfer-add-http-smoke.ps1'
Write-Host '.\scripts\settlement-account-payment-read-http-smoke.ps1'
Write-Host '.\scripts\settlement-account-read-http-smoke.ps1'
Write-Host '.\scripts\supplier-warehouse-read-http-smoke.ps1'
Write-Host '.\scripts\product-read-http-smoke.ps1'
Write-Host '.\scripts\hr-read-http-smoke.ps1'
Write-Host '.\scripts\biz-payment-record-edit-http-smoke.ps1'
Write-Host '.\scripts\biz-payment-record-edit-account-http-smoke.ps1'
Write-Host '.\scripts\biz-expenditure-record-edit-http-smoke.ps1'
Write-Host '.\scripts\biz-expenditure-record-edit-account-http-smoke.ps1'
Write-Host '.\scripts\biz-payroll-export-http-smoke.ps1'
Write-Host '.\scripts\biz-payroll-import-http-smoke.ps1'
Write-Host '.\scripts\biz-payroll-generate-add-http-smoke.ps1'
Write-Host '.\scripts\biz-user-vacation-write-http-smoke.ps1'
Write-Host '.\scripts\biz-cc-records-write-http-smoke.ps1'
Write-Host '.\scripts\workflow-general-start-http-smoke.ps1'
Write-Host '.\scripts\workflow-payment-approve-http-smoke.ps1'
Write-Host '.\scripts\workflow-payment-out-approve-http-smoke.ps1'
Write-Host '.\scripts\workflow-procure-approve-http-smoke.ps1'
Write-Host '.\scripts\workflow-procure-warehouse-approve-http-smoke.ps1'
Write-Host '.\scripts\workflow-project-delivery-approve-http-smoke.ps1'
Write-Host '.\scripts\workflow-project-play-approve-http-smoke.ps1'
Write-Host '.\scripts\team-project-read-http-smoke.ps1'
Write-Host '.\scripts\team-project-task-write-http-smoke.ps1'
Write-Host '.\scripts\datareport-read-http-smoke.ps1'
Write-Host '.\scripts\sale-project-visibility-edit-http-smoke.ps1'
Write-Host '.\scripts\sale-project-amount-edit-http-smoke.ps1'
Write-Host '.\scripts\resource-read-http-smoke.ps1'
Write-Host '.\scripts\dev-read-http-smoke.ps1'
Write-Host '.\scripts\dev-config-edit-batch-http-smoke.ps1'
Write-Host '.\scripts\dev-job-write-http-smoke.ps1'
Write-Host '.\scripts\gen-read-http-smoke.ps1'
Write-Host '.\scripts\gen-basic-write-http-smoke.ps1'
Write-Host '.\scripts\gen-config-write-http-smoke.ps1'
Write-Host '.\scripts\auth-index-read-http-smoke.ps1'
Write-Host 'curl.exe -sS <url> | node .\scripts\json-read.js data.records.0.id'
Write-Host 'Get-Content docs\tasks\context-handoff.md'
Write-Host 'Get-Content docs\tasks\problem-optimization-log.md'
Write-Host '.\scripts\test-agent-smoke.ps1 -SkipComposer'
Write-Host 'rg -n "<route|module|class|service>" app route docs PLANS.md IMPLEMENT.md STATUS.md snowy-admin-web/src'
