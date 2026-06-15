param(
    [switch]$SkipComposer,
    [switch]$SkipFrontendApiMethod,
    [string]$BackendBaseUrl = '',
    [switch]$NoTokenSmoke,
    [switch]$DevFileHttpSmoke,
    [switch]$DevEmailSmsHttpSmoke,
    [switch]$DevConfigHttpSmoke,
    [switch]$DevLogHttpSmoke,
    [switch]$DevJobHttpSmoke,
    [switch]$GenConfigHttpSmoke,
    [switch]$SaleProjectInvoicingHttpSmoke,
    [switch]$FileRelationHttpSmoke,
    [switch]$SysModuleHttpSmoke,
    [switch]$SysMenuHttpSmoke,
    [switch]$SysButtonHttpSmoke,
    [switch]$SysFieldHttpSmoke,
    [switch]$MobileModuleHttpSmoke,
    [switch]$MobileMenuHttpSmoke,
    [switch]$MobileButtonHttpSmoke,
    [switch]$TeamProjectHttpSmoke
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

function Invoke-TestStep {
    param(
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][scriptblock]$Action
    )

    Write-Host "[test-agent] running: $Name"
    & $Action
    Write-Host "[test-agent] passed: $Name"
}

function Invoke-External {
    param(
        [Parameter(Mandatory = $true)][string]$FilePath,
        [Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments
    )

    & $FilePath @Arguments
    if ($LASTEXITCODE -ne 0) {
        throw "Command failed: $FilePath $($Arguments -join ' ')"
    }
}

function Read-WebExceptionContent {
    param([Parameter(Mandatory = $true)]$Response)

    if ($Response.PSObject.Properties.Name -contains 'Content' -and $Response.Content -is [string]) {
        return [string]$Response.Content
    }

    if ($Response.PSObject.Methods.Name -contains 'GetResponseStream') {
        $stream = $Response.GetResponseStream()
        if ($null -eq $stream) {
            return ''
        }

        $reader = [System.IO.StreamReader]::new($stream)
        try {
            return $reader.ReadToEnd()
        } finally {
            $reader.Dispose()
        }
    }

    if ($Response.PSObject.Properties.Name -contains 'Content' -and $Response.Content -and $Response.Content.PSObject.Methods.Name -contains 'ReadAsStringAsync') {
        return $Response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
    }

    return ''
}

function Get-EnvMap {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        return @{}
    }

    $map = @{}
    foreach ($line in Get-Content -LiteralPath $Path) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) {
            continue
        }

        $parts = $trimmed -split '=', 2
        if ($parts.Count -ne 2) {
            continue
        }

        $key = $parts[0].Trim()
        $value = $parts[1].Trim()
        if (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'"))) {
            $value = $value.Substring(1, $value.Length - 2)
        }

        $map[$key] = $value
    }

    return $map
}

function Get-EnvValue {
    param(
        [Parameter(Mandatory = $true)][hashtable]$EnvMap,
        [Parameter(Mandatory = $true)][string]$Key,
        [string]$Default = ''
    )

    if ($EnvMap.ContainsKey($Key) -and [string]$EnvMap[$Key] -ne '') {
        return [string]$EnvMap[$Key]
    }

    return $Default
}

Invoke-TestStep 'composer dump-autoload' {
    if (-not $SkipComposer) {
        Invoke-External composer dump-autoload
    }
}

Invoke-TestStep 'ThinkPHP console bootstrap' {
    Invoke-External php think
}

$routeListText = ''
Invoke-TestStep 'ThinkPHP route list and required route coverage' {
    $script:routeListText = (& php think route:list 2>&1 | Out-String)
    if ($LASTEXITCODE -ne 0) {
        Write-Host $script:routeListText
        throw 'php think route:list failed'
    }

    $requiredRoutes = @(
        'sys/user/downloadImportUserTemplate',
        'sys/user/export',
        'sys/user/exportUserInfo',
        'biz/user/export',
        'biz/user/exportUserInfo',
        'dev/message/createSseConnect',
        'dev/file/delete',
        'dev/email/delete',
        'dev/sms/delete',
        'dev/config/add',
        'dev/config/edit',
        'dev/config/delete',
        'dev/log/delete',
        'dev/job/delete',
        'gen/config/editBatch',
        'sys/module/add',
        'sys/module/edit',
        'sys/module/delete',
        'sys/menu/add',
        'sys/menu/edit',
        'sys/menu/changeModule',
        'sys/menu/delete',
        'sys/button/add',
        'sys/button/edit',
        'sys/button/delete',
        'sys/field/add',
        'sys/field/edit',
        'sys/field/delete',
        'mobile/module/add',
        'mobile/module/edit',
        'mobile/module/delete',
        'mobile/menu/add',
        'mobile/menu/edit',
        'mobile/menu/changeModule',
        'mobile/menu/delete',
        'mobile/button/add',
        'mobile/button/edit',
        'mobile/button/delete',
        'biz/saleprojectinvoicing/complete',
        'biz/bizteamproject/add',
        'biz/bizteamproject/edit',
        'biz/bizteamproject/delete',
        'biz/bizteamprojectuser/edit',
        'biz/dict/page',
        'biz/org/page',
        'biz/user/page',
        'biz/position/page'
    )

    foreach ($route in $requiredRoutes) {
        if (-not $script:routeListText.Contains($route)) {
            throw "Required route missing: $route"
        }
    }
}

Invoke-TestStep 'PHP syntax lint for app, config, and route' {
    $files = Get-ChildItem -Recurse app,config,route -Include *.php
    foreach ($file in $files) {
        & php -l $file.FullName | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "PHP lint failed: $($file.FullName)"
        }
    }

    Write-Host "[test-agent] linted PHP files: $($files.Count)"
}

if (-not $SkipFrontendApiMethod) {
    Invoke-TestStep 'frontend API method smoke' {
        & (Join-Path $PSScriptRoot 'frontend-api-method-smoke.ps1')
        if ($LASTEXITCODE -ne 0) {
            throw 'frontend API method smoke failed'
        }
    }
}

Invoke-TestStep 'git diff whitespace check' {
    Invoke-External git diff --check
}

if ($NoTokenSmoke) {
    Invoke-TestStep 'no-token backend smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -NoTokenSmoke is used'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $routes = @(
            'sys/user/downloadImportUserTemplate',
            'sys/user/export',
            'sys/user/exportUserInfo',
            'biz/user/export',
            'biz/user/exportUserInfo'
        )

        foreach ($route in $routes) {
            $response = $null
            $statusCode = $null
            $content = ''
            try {
                $response = Invoke-WebRequest -Uri "$base/$route" -Method GET -UseBasicParsing -TimeoutSec 10
                $statusCode = [int]$response.StatusCode
                $content = [string]$response.Content
            } catch {
                $response = $_.Exception.Response
                if ($null -eq $response) {
                    throw
                }

                $statusCode = [int]$response.StatusCode
                $content = Read-WebExceptionContent -Response $response
            }

            if ($statusCode -ne 401 -and -not $content.Contains('"code":401') -and -not $content.Contains('"code": 401')) {
                throw "Expected unauthenticated response for $route, got HTTP $($statusCode): $content"
            }
        }
    }
}

if ($DevFileHttpSmoke) {
    Invoke-TestStep 'authenticated dev file delete HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -DevFileHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -DevFileHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEV_FILE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_FILE_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.txt')
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $fileId = ''
        $storedPath = ''

        try {
            Set-Content -LiteralPath $tmp -Value 'codex http file delete smoke' -Encoding ASCII

            $uploadRaw = & curl.exe -sS -X POST "$base/dev/file/uploadLocalReturnFile" -H "Authorization: Bearer $token" -F "file=@$tmp;type=text/plain"
            if ($LASTEXITCODE -ne 0) {
                throw 'file upload request failed'
            }
            $upload = $uploadRaw | ConvertFrom-Json
            if ([int]$upload.code -ne 200 -or -not $upload.data.id) {
                throw "unexpected file upload response: $uploadRaw"
            }
            $fileId = [string]$upload.data.id
            $storedPath = [string]$upload.data.storagePath

            $badBody = @(@{ id = $fileId }, @{ id = '' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $badBody -Encoding ASCII
            $badDeleteRaw = & curl.exe -sS -X POST "$base/dev/file/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'bad file delete request failed'
            }
            $badDelete = $badDeleteRaw | ConvertFrom-Json
            if ([int]$badDelete.code -eq 200) {
                throw "bad file delete payload should fail: $badDeleteRaw"
            }
            $preDeleteFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_file')->where('ID', '$fileId')->value('DELETE_FLAG');"
            if ([string]$preDeleteFlag -ne 'NOT_DELETE') {
                throw 'bad delete payload should not partially delete valid ids'
            }

            $body = @(@{ id = $fileId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/dev/file/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'file delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected file delete response: $deleteRaw"
            }
            if ($null -ne $delete.data) {
                throw "file delete response data should be null: $deleteRaw"
            }

            $deleteFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_file')->where('ID', '$fileId')->value('DELETE_FLAG');"
            if ([string]$deleteFlag -ne 'DELETED') {
                throw 'dev file delete did not mark row deleted'
            }
            if ($storedPath -ne '' -and -not (Test-Path -LiteralPath $storedPath)) {
                throw 'logical delete should keep physical file on disk'
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$fileId = '$fileId';
if (`$fileId !== '') {
    `$path = (string)think\facade\Db::name('dev_file')->where('ID', `$fileId)->value('STORAGE_PATH');
    think\facade\Db::name('dev_file')->where('ID', `$fileId)->delete();
    if (`$path !== '' && is_file(`$path)) { @unlink(`$path); }
}
"@
            & php -r $cleanupCode | Out-Null
            foreach ($path in @($tmp, $jsonTmp)) {
                if (Test-Path -LiteralPath $path) {
                    Remove-Item -LiteralPath $path -Force
                }
            }
        }
    }
}

if ($DevEmailSmsHttpSmoke) {
    Invoke-TestStep 'authenticated dev email and SMS delete HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -DevEmailSmsHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -DevEmailSmsHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEV_NOTIFY_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_NOTIFY_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $baseId = [Int64]600300000000000000 + [Int64](Get-Random -Minimum 1000000 -Maximum 9999999)
        $emailId = [string]$baseId
        $smsId = [string]($baseId + 1)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $phpTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.php')

        try {
            $insertCode = @"
<?php
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('dev_email')->insert([
    'ID' => '$emailId',
    'ENGINE' => 'LOCAL',
    'SEND_ACCOUNT' => 'codex@example.invalid',
    'SEND_USER' => 'codex',
    'RECEIVE_ACCOUNTS' => 'receiver@example.invalid',
    'SUBJECT' => '$prefix email',
    'CONTENT' => 'codex email http smoke',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => 'codex-smoke',
    'TENANT_ID' => '1',
]);
think\facade\Db::name('dev_sms')->insert([
    'ID' => '$smsId',
    'ENGINE' => 'ALIYUN',
    'PHONE_NUMBERS' => '13800138000',
    'SIGN_NAME' => '$prefix',
    'TEMPLATE_CODE' => 'CODEX_TEMPLATE',
    'TEMPLATE_PARAM' => '{}',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => 'codex-smoke',
    'TENANT_ID' => '1',
]);
"@
            [System.IO.File]::WriteAllText($phpTmp, $insertCode, [System.Text.UTF8Encoding]::new($false))
            & php $phpTmp
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to insert dev email/SMS smoke rows'
            }

            $badBody = @(@{ id = $emailId }, @{ id = '' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $badBody -Encoding ASCII
            $badEmailRaw = & curl.exe -sS -X POST "$base/dev/email/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'bad email delete request failed'
            }
            $badEmail = $badEmailRaw | ConvertFrom-Json
            if ([int]$badEmail.code -eq 200) {
                throw "bad email delete payload should fail: $badEmailRaw"
            }
            $emailFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_email')->where('ID', '$emailId')->value('DELETE_FLAG');"
            if ([string]$emailFlag -ne 'NOT_DELETE') {
                throw 'bad email delete payload should not partially delete valid ids'
            }

            $badBody = @(@{ id = $smsId }, @{ id = '' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $badBody -Encoding ASCII
            $badSmsRaw = & curl.exe -sS -X POST "$base/dev/sms/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'bad SMS delete request failed'
            }
            $badSms = $badSmsRaw | ConvertFrom-Json
            if ([int]$badSms.code -eq 200) {
                throw "bad SMS delete payload should fail: $badSmsRaw"
            }
            $smsFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_sms')->where('ID', '$smsId')->value('DELETE_FLAG');"
            if ([string]$smsFlag -ne 'NOT_DELETE') {
                throw 'bad SMS delete payload should not partially delete valid ids'
            }

            $body = @(@{ id = $emailId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $emailRaw = & curl.exe -sS -X POST "$base/dev/email/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'email delete request failed'
            }
            $emailDelete = $emailRaw | ConvertFrom-Json
            if ([int]$emailDelete.code -ne 200 -or $null -ne $emailDelete.data) {
                throw "unexpected email delete response: $emailRaw"
            }

            $body = @(@{ id = $smsId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $smsRaw = & curl.exe -sS -X POST "$base/dev/sms/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'SMS delete request failed'
            }
            $smsDelete = $smsRaw | ConvertFrom-Json
            if ([int]$smsDelete.code -ne 200 -or $null -ne $smsDelete.data) {
                throw "unexpected SMS delete response: $smsRaw"
            }

            $emailFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_email')->where('ID', '$emailId')->value('DELETE_FLAG');"
            if ([string]$emailFlag -ne 'DELETED') {
                throw 'dev email delete did not mark row deleted'
            }
            $smsFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_sms')->where('ID', '$smsId')->value('DELETE_FLAG');"
            if ([string]$smsFlag -ne 'DELETED') {
                throw 'dev SMS delete did not mark row deleted'
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_email')->where('ID', '$emailId')->delete();
think\facade\Db::name('dev_sms')->where('ID', '$smsId')->delete();
"@
            & php -r $cleanupCode | Out-Null
            foreach ($path in @($jsonTmp, $phpTmp)) {
                if (Test-Path -LiteralPath $path) {
                    Remove-Item -LiteralPath $path -Force
                }
            }
        }
    }
}

if ($DevConfigHttpSmoke) {
    Invoke-TestStep 'authenticated dev config HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -DevConfigHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -DevConfigHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEV_CONFIG_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_CONFIG_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $configId = ''
        $sysId = '60050000000000' + [string](Get-Random -Minimum 100000 -Maximum 999999)

        try {
            $body = @{ configKey = ($prefix + '_KEY'); configValue = 'value-a'; remark = 'codex http config smoke'; sortCode = 99 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/dev/config/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'config add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or $null -ne $add.data) {
                throw "unexpected config add response: $addRaw"
            }
            $configId = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_config')->where('CONFIG_KEY', '$($prefix)_KEY')->where('CATEGORY', 'BIZ_DEFINE')->where('DELETE_FLAG', 'NOT_DELETE')->value('ID');"
            if ([string]::IsNullOrWhiteSpace($configId)) {
                throw 'config row not found after HTTP add'
            }

            $body = @{ id = $configId; configKey = ($prefix + '_KEY_EDITED'); configValue = 'value-b'; remark = 'codex http config smoke edited'; sortCode = 88 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/dev/config/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'config edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or $null -ne $edit.data) {
                throw "unexpected config edit response: $editRaw"
            }
            $editedValue = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_config')->where('ID', '$configId')->value('CONFIG_VALUE');"
            if ([string]$editedValue -ne 'value-b') {
                throw 'config edit did not update value'
            }

            $badBody = @(@{ id = $configId }, @{ id = '' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $badBody -Encoding ASCII
            $badDeleteRaw = & curl.exe -sS -X POST "$base/dev/config/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'bad config delete request failed'
            }
            $badDelete = $badDeleteRaw | ConvertFrom-Json
            if ([int]$badDelete.code -eq 200) {
                throw "bad config delete payload should fail: $badDeleteRaw"
            }
            $deleteFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_config')->where('ID', '$configId')->value('DELETE_FLAG');"
            if ([string]$deleteFlag -ne 'NOT_DELETE') {
                throw 'bad config delete payload should not partially delete valid ids'
            }

            & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); think\facade\Db::name('dev_config')->insert(['ID' => '$sysId', 'CONFIG_KEY' => '$($prefix)_SYS', 'CONFIG_VALUE' => 'sys', 'CATEGORY' => 'SYS_BASE', 'SORT_CODE' => 1, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);" | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to insert temporary system config row'
            }
            $body = @(@{ id = $sysId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $sysDeleteRaw = & curl.exe -sS -X POST "$base/dev/config/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'system config delete request failed'
            }
            $sysDelete = $sysDeleteRaw | ConvertFrom-Json
            if ([int]$sysDelete.code -eq 200) {
                throw "system config delete should fail: $sysDeleteRaw"
            }

            $body = @(@{ id = $configId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/dev/config/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'config delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200 -or $null -ne $delete.data) {
                throw "unexpected config delete response: $deleteRaw"
            }
            $deleteFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_config')->where('ID', '$configId')->value('DELETE_FLAG');"
            if ([string]$deleteFlag -ne 'DELETED') {
                throw 'config delete did not mark row deleted'
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$configId = '$configId';
`$sysId = '$sysId';
if (`$configId !== '') { think\facade\Db::name('dev_config')->where('ID', `$configId)->delete(); }
if (`$sysId !== '') { think\facade\Db::name('dev_config')->where('ID', `$sysId)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($DevLogHttpSmoke) {
    Invoke-TestStep 'authenticated dev log HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -DevLogHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -DevLogHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEV_LOG_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_LOG_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $category = $prefix + '_TARGET'
        $otherCategory = $prefix + '_OTHER'
        $targetId = '60060000000000' + [string](Get-Random -Minimum 100000 -Maximum 999999)
        $otherId = '60060000000001' + [string](Get-Random -Minimum 100000 -Maximum 999999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')

        try {
            $insertCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_log')->insert(['ID' => '$targetId', 'CATEGORY' => '$category', 'NAME' => 'codex dev log target', 'EXE_STATUS' => 'SUCCESS', 'TENANT_ID' => '1', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'OP_TIME' => date('Y-m-d H:i:s')]);
think\facade\Db::name('dev_log')->insert(['ID' => '$otherId', 'CATEGORY' => '$otherCategory', 'NAME' => 'codex dev log other', 'EXE_STATUS' => 'SUCCESS', 'TENANT_ID' => '1', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'OP_TIME' => date('Y-m-d H:i:s')]);
"@
            & php -r $insertCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to insert temporary log rows'
            }

            $body = @{ category = $category } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/dev/log/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'dev log delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200 -or $null -ne $delete.data) {
                throw "unexpected dev log delete response: $deleteRaw"
            }

            $counts = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (int)think\facade\Db::name('dev_log')->where('ID', '$targetId')->count() . ':' . (int)think\facade\Db::name('dev_log')->where('ID', '$otherId')->count();"
            if ([string]$counts -ne '0:1') {
                throw "dev log delete affected unexpected rows: $counts"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_log')->whereIn('ID', ['$targetId', '$otherId'])->delete();
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($DevJobHttpSmoke) {
    Invoke-TestStep 'authenticated dev job HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -DevJobHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -DevJobHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_DEV_JOB_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_JOB_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jobId = '60080000000000' + [string](Get-Random -Minimum 100000 -Maximum 999999)
        $otherId = '60080000000001' + [string](Get-Random -Minimum 100000 -Maximum 999999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')

        try {
            $insertCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_job')->insert(['ID' => '$jobId', 'NAME' => '$($prefix)_TARGET', 'CODE' => '$($prefix)_TARGET', 'CATEGORY' => 'LOCAL', 'ACTION_CLASS' => 'codex.TargetJob', 'CRON_EXPRESSION' => '0 0 * * * ?', 'JOB_STATUS' => 'STOPPED', 'SORT_CODE' => 99, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
think\facade\Db::name('dev_job')->insert(['ID' => '$otherId', 'NAME' => '$($prefix)_OTHER', 'CODE' => '$($prefix)_OTHER', 'CATEGORY' => 'LOCAL', 'ACTION_CLASS' => 'codex.OtherJob', 'CRON_EXPRESSION' => '0 0 * * * ?', 'JOB_STATUS' => 'STOPPED', 'SORT_CODE' => 99, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
"@
            & php -r $insertCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to insert temporary job rows'
            }

            $badBody = @(@{ id = $jobId }, @{ id = '' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $badBody -Encoding ASCII
            $badDeleteRaw = & curl.exe -sS -X POST "$base/dev/job/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'bad dev job delete request failed'
            }
            $badDelete = $badDeleteRaw | ConvertFrom-Json
            if ([int]$badDelete.code -eq 200) {
                throw "bad dev job delete payload should fail: $badDeleteRaw"
            }
            $badFlag = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_job')->where('ID', '$jobId')->value('DELETE_FLAG');"
            if ([string]$badFlag -ne 'NOT_DELETE') {
                throw 'bad dev job delete payload should not partially delete valid ids'
            }

            $body = @(@{ id = $jobId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/dev/job/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'dev job delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200 -or $null -ne $delete.data) {
                throw "unexpected dev job delete response: $deleteRaw"
            }

            $flags = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('dev_job')->where('ID', '$jobId')->value('DELETE_FLAG') . ':' . (string)think\facade\Db::name('dev_job')->where('ID', '$otherId')->value('DELETE_FLAG');"
            if ([string]$flags -ne 'DELETED:NOT_DELETE') {
                throw "dev job delete affected unexpected rows: $flags"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('dev_job')->whereIn('ID', ['$jobId', '$otherId'])->delete();
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($GenConfigHttpSmoke) {
    Invoke-TestStep 'authenticated gen config editBatch HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -GenConfigHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -GenConfigHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_GEN_CONFIG_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_GENCFG_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $baseId = [Int64]601200000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999)
        $idA = [string]$baseId
        $idB = [string]($baseId + 1)
        $deletedId = [string]($baseId + 2)
        $basicId = [string]([Int64]601300000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999))
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')

        function New-GenConfigSmokePayload {
            param(
                [string]$Id,
                [string]$BasicId,
                [string]$FieldName,
                [string]$Remark,
                [int]$SortCode
            )

            return @{
                id = $Id
                basicId = $BasicId
                isTableKey = 'N'
                fieldName = $FieldName
                fieldRemark = $Remark
                fieldType = 'varchar(255)'
                fieldJavaType = 'String'
                effectType = 'input'
                dictTypeCode = ''
                whetherTable = 'Y'
                whetherRetract = 'N'
                whetherAddUpdate = 'Y'
                whetherRequired = 'N'
                queryWhether = 'Y'
                queryType = 'like'
                sortCode = $SortCode
                deleteFlag = 'DELETED'
                updateUser = 'client-spoof'
            }
        }

        try {
            $insertCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
`$rows = [];
foreach ([['$idA', '$($prefix)_FIELD_A', 'NOT_DELETE'], ['$idB', '$($prefix)_FIELD_B', 'NOT_DELETE'], ['$deletedId', '$($prefix)_FIELD_DELETED', 'DELETED']] as `$row) {
    `$rows[] = [
        'ID' => `$row[0],
        'BASIC_ID' => '$basicId',
        'IS_TABLE_KEY' => 'N',
        'FIELD_NAME' => `$row[1],
        'FIELD_REMARK' => `$row[1] . ' remark',
        'FIELD_TYPE' => 'varchar(255)',
        'FIELD_JAVA_TYPE' => 'String',
        'EFFECT_TYPE' => 'input',
        'WHETHER_TABLE' => 'Y',
        'WHETHER_RETRACT' => 'N',
        'WHETHER_ADD_UPDATE' => 'Y',
        'WHETHER_REQUIRED' => 'N',
        'QUERY_WHETHER' => 'N',
        'SORT_CODE' => 10,
        'DELETE_FLAG' => `$row[2],
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
    ];
}
think\facade\Db::name('gen_config')->insertAll(`$rows);
"@
            & php -r $insertCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to insert temporary gen_config rows'
            }

            $badBody = @(
                (New-GenConfigSmokePayload -Id $idA -BasicId $basicId -FieldName ($prefix + '_FIELD_A') -Remark ($prefix + '_SHOULD_NOT_SAVE') -SortCode 31),
                (New-GenConfigSmokePayload -Id $deletedId -BasicId $basicId -FieldName ($prefix + '_FIELD_DELETED') -Remark ($prefix + '_DELETED') -SortCode 32)
            ) | ConvertTo-Json -Depth 4 -Compress
            Set-Content -LiteralPath $jsonTmp -Value $badBody -Encoding ASCII
            $badRaw = & curl.exe -sS -X POST "$base/gen/config/editBatch" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'bad gen config editBatch request failed'
            }
            $bad = $badRaw | ConvertFrom-Json
            if ([int]$bad.code -eq 200) {
                throw "bad gen config editBatch payload should fail: $badRaw"
            }
            $remark = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('gen_config')->where('ID', '$idA')->value('FIELD_REMARK');"
            if ([string]$remark -eq ($prefix + '_SHOULD_NOT_SAVE')) {
                throw 'bad editBatch payload should not partially update valid rows'
            }

            $body = @(
                (New-GenConfigSmokePayload -Id $idA -BasicId $basicId -FieldName ($prefix + '_FIELD_A') -Remark ($prefix + '_A_HTTP_EDITED') -SortCode 41),
                (New-GenConfigSmokePayload -Id $idB -BasicId $basicId -FieldName ($prefix + '_FIELD_B') -Remark ($prefix + '_B_HTTP_EDITED') -SortCode 42)
            ) | ConvertTo-Json -Depth 4 -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/gen/config/editBatch" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'gen config editBatch request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or $null -ne $edit.data) {
                throw "unexpected gen config editBatch response: $editRaw"
            }

            $state = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$a = think\facade\Db::name('gen_config')->where('ID', '$idA')->find(); `$b = think\facade\Db::name('gen_config')->where('ID', '$idB')->find(); echo `$a['FIELD_REMARK'] . ':' . `$b['FIELD_REMARK'] . ':' . `$a['DELETE_FLAG'];"
            if ([string]$state -ne "$($prefix)_A_HTTP_EDITED:$($prefix)_B_HTTP_EDITED:NOT_DELETE") {
                throw "gen config editBatch did not update expected rows: $state"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('gen_config')->whereIn('ID', ['$idA', '$idB', '$deletedId'])->delete();
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($SaleProjectInvoicingHttpSmoke) {
    Invoke-TestStep 'authenticated sale project invoicing complete HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -SaleProjectInvoicingHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -SaleProjectInvoicingHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_INVOICING_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_INVOICING_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $baseId = [Int64]602200000000000000 + [Int64](Get-Random -Minimum 100000 -Maximum 999999)
        $projectId = [string]$baseId
        $invoicingId = [string]($baseId + 1)
        $otherTenantProjectId = [string]($baseId + 2)
        $otherTenantInvoicingId = [string]($baseId + 3)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')

        try {
            $insertCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('biz_sale_project')->insertAll([
    [
        'ID' => '$projectId',
        'CUSTOMER' => '0',
        'PROJECT_NAME' => '$($prefix)_PROJECT',
        'PROJECT_STATE' => 'SHIPPED',
        'PLAY_STATE' => 'NORMAL',
        'VISIBILITY' => 'PUBLIC',
        'INIT_PRICE' => 0,
        'TOTAL_PRICE' => 0,
        'AMOUNT_COLLECTED' => 0,
        'PROJECT_CATEGORY' => 'SALE_PROJECT',
        'USER' => 'codexUser',
        'ORG' => '0',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => '1',
        'VERSION' => 0,
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => 0,
    ],
    [
        'ID' => '$otherTenantProjectId',
        'CUSTOMER' => '0',
        'PROJECT_NAME' => '$($prefix)_TENANT2_PROJECT',
        'PROJECT_STATE' => 'SHIPPED',
        'PLAY_STATE' => 'NORMAL',
        'VISIBILITY' => 'PUBLIC',
        'INIT_PRICE' => 0,
        'TOTAL_PRICE' => 0,
        'AMOUNT_COLLECTED' => 0,
        'PROJECT_CATEGORY' => 'SALE_PROJECT',
        'USER' => 'codexUser',
        'ORG' => '0',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => '2',
        'VERSION' => 0,
        'DEAL_AMOUNT' => 0,
        'HISTORY_AMOUNT' => 0,
    ],
]);
foreach ([['$invoicingId', '$projectId', '1', '$($prefix)_COMPANY'], ['$otherTenantInvoicingId', '$otherTenantProjectId', '2', '$($prefix)_TENANT2_COMPANY']] as `$row) {
    think\facade\Db::name('biz_sale_project_invoicing')->insert([
        'ID' => `$row[0],
        'PROJECT_ID' => `$row[1],
        'AMOUNT' => 123.45,
        'INVOICING_STATE' => 'INVOICING_STATE_WAIT',
        'INVOICING_CATEGORY' => 'COMMON',
        'PROCESS_ID' => '$($prefix)_PROCESS',
        'REMARK' => 'codex http smoke',
        'COMPANY_NAME' => `$row[3],
        'CUSTOMER_COMPANY' => '$($prefix)_CUSTOMER',
        'UNIT' => '$($prefix)_UNIT',
        'DELETE_FLAG' => 'NOT_DELETE',
        'CREATE_TIME' => `$now,
        'CREATE_USER' => 'codex-smoke',
        'TENANT_ID' => `$row[2],
    ]);
}
"@
            & php -r $insertCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to insert temporary invoicing rows'
            }

            $body = @{ id = $otherTenantInvoicingId } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $badRaw = & curl.exe -sS -X POST "$base/biz/saleprojectinvoicing/complete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'cross-tenant invoicing complete request failed'
            }
            $bad = $badRaw | ConvertFrom-Json
            if ([int]$bad.code -eq 200) {
                throw "cross-tenant invoicing complete should fail: $badRaw"
            }
            $otherState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$otherTenantInvoicingId')->value('INVOICING_STATE');"
            if ([string]$otherState -ne 'INVOICING_STATE_WAIT') {
                throw 'cross-tenant invoicing complete should not update row'
            }

            $body = @{ id = $invoicingId } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $completeRaw = & curl.exe -sS -X POST "$base/biz/saleprojectinvoicing/complete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'invoicing complete request failed'
            }
            $complete = $completeRaw | ConvertFrom-Json
            if ([int]$complete.code -ne 200 -or $null -ne $complete.data) {
                throw "unexpected invoicing complete response: $completeRaw"
            }

            $state = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('biz_sale_project_invoicing')->where('ID', '$invoicingId')->value('INVOICING_STATE');"
            if ([string]$state -ne 'INVOICING_STATE_COMPLETE') {
                throw 'invoicing complete did not update state'
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('biz_sale_project_invoicing')->whereIn('ID', ['$invoicingId', '$otherTenantInvoicingId'])->delete();
think\facade\Db::name('biz_sale_project')->whereIn('ID', ['$projectId', '$otherTenantProjectId'])->delete();
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($FileRelationHttpSmoke) {
    Invoke-TestStep 'authenticated file relation HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -FileRelationHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -FileRelationHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_FILE_RELATION_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_REL_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.txt')
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $fileId = ''
        $relationId = ''

        try {
            Set-Content -LiteralPath $tmp -Value 'codex http relation smoke' -Encoding ASCII

            $uploadRaw = & curl.exe -sS -X POST "$base/dev/file/uploadLocalReturnFile" -H "Authorization: Bearer $token" -F "file=@$tmp;type=text/plain"
            if ($LASTEXITCODE -ne 0) {
                throw 'file upload request failed'
            }
            $upload = $uploadRaw | ConvertFrom-Json
            if ([int]$upload.code -ne 200 -or -not $upload.data.id) {
                throw "unexpected file upload response: $uploadRaw"
            }
            $fileId = [string]$upload.data.id

            $body = @{ objectId = ($prefix + '_OBJECT'); targetId = $fileId; category = 'SALE_PROJECT' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/biz/bizfilerelation/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'file relation add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200) {
                throw "unexpected file relation add response: $addRaw"
            }

            $relationId = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('biz_file_relation')->where('OBJECT_ID', '$($prefix)_OBJECT')->where('TARGET_ID', '$fileId')->where('DELETE_FLAG', 'NOT_DELETE')->value('ID');"
            if ([string]::IsNullOrWhiteSpace($relationId)) {
                throw 'relation row not found after HTTP add'
            }

            $deleteRaw = & curl.exe -sS -X GET "$base/biz/bizfilerelation/projectCase/del?id=$relationId" -H "Authorization: Bearer $token"
            if ($LASTEXITCODE -ne 0) {
                throw 'file relation projectCase delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected file relation delete response: $deleteRaw"
            }

            $activeCount = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('biz_file_relation')->where('ID', '$relationId')->where('DELETE_FLAG', 'NOT_DELETE')->count();"
            if ([int]$activeCount -ne 0) {
                throw 'projectCase delete did not mark relation deleted'
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$fileId = '$fileId';
`$relationId = '$relationId';
if (`$relationId !== '') { think\facade\Db::name('biz_file_relation')->where('ID', `$relationId)->delete(); }
if (`$fileId !== '') {
    `$path = (string)think\facade\Db::name('dev_file')->where('ID', `$fileId)->value('STORAGE_PATH');
    think\facade\Db::name('dev_file')->where('ID', `$fileId)->delete();
    if (`$path !== '' && is_file(`$path)) { @unlink(`$path); }
}
"@
            & php -r $cleanupCode | Out-Null
            foreach ($path in @($tmp, $jsonTmp)) {
                if (Test-Path -LiteralPath $path) {
                    Remove-Item -LiteralPath $path -Force
                }
            }
        }
    }
}

if ($SysModuleHttpSmoke) {
    Invoke-TestStep 'authenticated sys module write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -SysModuleHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -SysModuleHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SYS_MODULE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_MODULE_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $moduleId = ''
        $menuId = ''
        $relationId = ''

        try {
            $body = @{ title = ($prefix + '_MODULE'); icon = 'AppstoreOutlined'; color = '#1677FF'; sortCode = 99; extJson = @{ smoke = $true } } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/sys/module/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys module add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id) {
                throw "unexpected sys module add response: $addRaw"
            }
            $moduleId = [string]$add.data.id

            $pageRaw = & curl.exe -sS -X GET "$base/sys/module/page?searchKey=$prefix" -H "Authorization: Bearer $token"
            $page = $pageRaw | ConvertFrom-Json
            $records = @($page.data.records)
            if ([int]$page.code -ne 200 -or -not ($records | Where-Object { [string]$_.id -eq $moduleId })) {
                throw "sys module page did not include created module: $pageRaw"
            }

            $body = @{ title = ($prefix + '_MODULE'); icon = 'AppstoreOutlined'; color = '#1677FF'; sortCode = 98 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/sys/module/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "sys module duplicate title should fail: $duplicateRaw"
            }

            $body = @{ id = $moduleId; title = ($prefix + '_EDITED'); icon = 'SettingOutlined'; color = '#13C2C2'; sortCode = 97; extJson = '{}' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/sys/module/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys module edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.title -ne "$($prefix)_EDITED") {
                throw "unexpected sys module edit response: $editRaw"
            }

            $menuId = 'codex-menu-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $relationId = 'codex-rel-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $prepCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('sys_resource')->insert(['ID' => '$menuId', 'PARENT_ID' => '0', 'TITLE' => '$prefix' . '_MENU', 'CODE' => '$prefix' . '_MENU', 'CATEGORY' => 'MENU', 'MODULE' => '$moduleId', 'SORT_CODE' => 9999, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
think\facade\Db::name('sys_relation')->insert(['ID' => '$relationId', 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$menuId', 'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE', 'EXT_JSON' => '{}']);
"@
            & php -r $prepCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to prepare sys module delete smoke rows'
            }

            $body = @(@{ id = $moduleId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/sys/module/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys module delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected sys module delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('sys_resource')->where('ID', '$moduleId')->value('DELETE_FLAG') . ':' . (string)think\facade\Db::name('sys_resource')->where('ID', '$menuId')->value('DELETE_FLAG') . ':' . (string)think\facade\Db::name('sys_relation')->where('ID', '$relationId')->count();"
            if ([string]$deleteState -ne 'DELETED:DELETED:0') {
                throw "sys module delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$moduleId = '$moduleId';
`$menuId = '$menuId';
`$relationId = '$relationId';
if (`$relationId !== '') { think\facade\Db::name('sys_relation')->where('ID', `$relationId)->delete(); }
foreach ([`$menuId, `$moduleId] as `$id) { if (`$id !== '') { think\facade\Db::name('sys_resource')->where('ID', `$id)->delete(); } }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($SysMenuHttpSmoke) {
    Invoke-TestStep 'authenticated sys menu write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -SysMenuHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -SysMenuHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SYS_MENU_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_SYS_MENU_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $moduleAId = ''
        $moduleBId = ''
        $rootId = ''
        $childId = ''
        $buttonId = ''
        $relationId = ''

        try {
            $moduleCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$service = new app\service\sys\ResourceService();
`$payload = ['userId' => 'codex-smoke'];
`$moduleA = `$service->moduleAdd(['title' => '$prefix' . '_MODULE_A', 'icon' => 'AppstoreOutlined', 'color' => '#1677FF', 'sortCode' => 9999], `$payload);
`$moduleB = `$service->moduleAdd(['title' => '$prefix' . '_MODULE_B', 'icon' => 'SettingOutlined', 'color' => '#13C2C2', 'sortCode' => 9998], `$payload);
echo `$moduleA['id'] . ':' . `$moduleB['id'];
"@
            $moduleState = & php -r $moduleCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($moduleState)) {
                throw 'failed to prepare sys menu module fixtures'
            }
            $moduleParts = ([string]$moduleState).Split(':')
            $moduleAId = $moduleParts[0]
            $moduleBId = $moduleParts[1]

            $body = @{ parentId = '0'; title = ($prefix + '_ROOT'); menuType = 'MENU'; module = $moduleAId; path = '/codex-sys-root'; name = ($prefix + '_ROOT_NAME'); component = 'codex/sys/root'; icon = 'AppstoreOutlined'; visible = 'TRUE'; sortCode = 9999; extJson = @{ smoke = $true } } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/sys/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys menu add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id -or [string]$add.data.category -ne 'MENU') {
                throw "unexpected sys menu add response: $addRaw"
            }
            $rootId = [string]$add.data.id

            $treeRaw = & curl.exe -sS -X GET "$base/sys/menu/tree?module=$moduleAId&searchKey=$prefix" -H "Authorization: Bearer $token"
            $tree = $treeRaw | ConvertFrom-Json
            $treeRows = @($tree.data)
            if ([int]$tree.code -ne 200 -or -not ($treeRows | Where-Object { [string]$_.id -eq $rootId })) {
                throw "sys menu tree did not include created root menu: $treeRaw"
            }

            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/sys/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "sys menu duplicate title should fail: $duplicateRaw"
            }

            $body = @{ parentId = $rootId; title = ($prefix + '_CHILD'); menuType = 'MENU'; module = $moduleAId; path = '/codex-sys-child'; name = ($prefix + '_CHILD_NAME'); component = 'codex/sys/child'; icon = 'BarsOutlined'; visible = 'TRUE'; sortCode = 9997 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $childRaw = & curl.exe -sS -X POST "$base/sys/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $child = $childRaw | ConvertFrom-Json
            if ([int]$child.code -ne 200 -or -not $child.data.id) {
                throw "unexpected sys child menu add response: $childRaw"
            }
            $childId = [string]$child.data.id

            $body = @{ parentId = $rootId; title = ($prefix + '_BAD_CHILD'); menuType = 'MENU'; module = $moduleBId; path = '/codex-sys-bad-child'; name = ($prefix + '_BAD_CHILD_NAME'); component = 'codex/sys/badChild'; sortCode = 9996 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $badChildRaw = & curl.exe -sS -X POST "$base/sys/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $badChild = $badChildRaw | ConvertFrom-Json
            if ([int]$badChild.code -eq 200) {
                throw "sys menu parent/module mismatch should fail: $badChildRaw"
            }

            $body = @{ id = $childId; parentId = $rootId; title = ($prefix + '_CHILD_EDITED'); menuType = 'IFRAME'; module = $moduleAId; path = 'https://example.test/sys'; visible = 'FALSE'; sortCode = 9995 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/sys/menu/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys menu edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.menuType -ne 'IFRAME' -or [string]$edit.data.visible -ne 'FALSE' -or $null -ne $edit.data.component) {
                throw "unexpected sys menu edit response: $editRaw"
            }

            $body = @{ id = $childId; parentId = $rootId; title = ($prefix + '_CHILD_EDITED'); menuType = 'MENU'; module = $moduleAId; path = '/codex-sys-child-edited'; name = ($prefix + '_CHILD_EDITED_NAME'); component = 'codex/sys/childEdited'; visible = 'TRUE'; sortCode = 9995 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editMenuRaw = & curl.exe -sS -X POST "$base/sys/menu/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $editMenu = $editMenuRaw | ConvertFrom-Json
            if ([int]$editMenu.code -ne 200 -or [string]$editMenu.data.component -ne 'codex/sys/childEdited') {
                throw "unexpected sys menu edit-back response: $editMenuRaw"
            }

            $body = @{ id = $rootId; parentId = $childId; title = ($prefix + '_ROOT'); menuType = 'MENU'; module = $moduleAId; path = '/codex-sys-root'; name = ($prefix + '_ROOT_NAME'); component = 'codex/sys/root'; visible = 'TRUE'; sortCode = 9999 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $badParentRaw = & curl.exe -sS -X POST "$base/sys/menu/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $badParent = $badParentRaw | ConvertFrom-Json
            if ([int]$badParent.code -eq 200) {
                throw "sys menu parent self/child edit should fail: $badParentRaw"
            }

            $body = @{ id = $childId; module = $moduleBId } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $badChangeRaw = & curl.exe -sS -X POST "$base/sys/menu/changeModule" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $badChange = $badChangeRaw | ConvertFrom-Json
            if ([int]$badChange.code -eq 200) {
                throw "child sys menu changeModule should fail: $badChangeRaw"
            }

            $body = @{ id = $rootId; module = $moduleBId } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $changeRaw = & curl.exe -sS -X POST "$base/sys/menu/changeModule" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $change = $changeRaw | ConvertFrom-Json
            if ([int]$change.code -ne 200 -or [int]$change.data.count -ne 2) {
                throw "unexpected sys menu changeModule response: $changeRaw"
            }

            $fixtureCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$service = new app\service\sys\ResourceService();
`$button = `$service->buttonAdd(['parentId' => '$childId', 'title' => '$prefix' . '_BUTTON', 'code' => '$prefix' . '_BUTTON_CODE', 'sortCode' => 9994], ['userId' => 'codex-smoke']);
think\facade\Db::name('sys_relation')->insert(['ID' => 'codex-rel-' . random_int(100000, 999999), 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$childId', 'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE', 'EXT_JSON' => json_encode(['buttonInfo' => [`$button['id']]], JSON_UNESCAPED_SLASHES)]);
`$relationId = (string)think\facade\Db::name('sys_relation')->where('TARGET_ID', '$childId')->where('CATEGORY', 'SYS_ROLE_HAS_RESOURCE')->order('ID', 'desc')->value('ID');
echo `$button['id'] . ':' . `$relationId;
"@
            $fixtureState = & php -r $fixtureCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($fixtureState)) {
                throw 'failed to prepare sys menu delete fixtures'
            }
            $fixtureParts = ([string]$fixtureState).Split(':')
            $buttonId = $fixtureParts[0]
            $relationId = $fixtureParts[1]

            $body = @(@{ id = $rootId }, @{ id = 'missing-sys-menu' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/sys/menu/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys menu delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected sys menu delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$flags = think\facade\Db::name('sys_resource')->whereIn('ID', ['$rootId', '$childId', '$buttonId'])->column('DELETE_FLAG', 'ID'); `$relationCount = think\facade\Db::name('sys_relation')->where('ID', '$relationId')->count(); echo implode(',', [`$flags['$rootId'] ?? '', `$flags['$childId'] ?? '', `$flags['$buttonId'] ?? '']) . ':' . `$relationCount;"
            if ([string]$deleteState -ne 'DELETED,DELETED,DELETED:0') {
                throw "sys menu delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$ids = array_values(array_filter(['$moduleAId', '$moduleBId', '$rootId', '$childId', '$buttonId']));
if ('$relationId' !== '') { think\facade\Db::name('sys_relation')->where('ID', '$relationId')->delete(); }
if (`$ids !== []) { think\facade\Db::name('sys_resource')->whereIn('ID', `$ids)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($SysButtonHttpSmoke) {
    Invoke-TestStep 'authenticated sys button write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -SysButtonHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -SysButtonHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SYS_BUTTON_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_BUTTON_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $buttonId = ''
        $relationId = ''
        $createdMenuId = ''

        try {
            $parentCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$parentId = (string)think\facade\Db::name('sys_resource')->where('CATEGORY', 'MENU')->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })->value('ID');
if (`$parentId === '') {
    `$parentId = 'codex-menu-' . random_int(100000, 999999);
    think\facade\Db::name('sys_resource')->insert(['ID' => `$parentId, 'TITLE' => '$prefix' . '_MENU', 'CODE' => '$prefix' . '_MENU', 'CATEGORY' => 'MENU', 'SORT_CODE' => 9999, 'DELETE_FLAG' => 'NOT_DELETE', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
    echo `$parentId . ':created';
} else {
    echo `$parentId . ':existing';
}
"@
            $parentState = & php -r $parentCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($parentState)) {
                throw 'failed to prepare button parent menu'
            }
            $parentParts = ([string]$parentState).Split(':')
            $parentId = $parentParts[0]
            if ($parentParts.Length -gt 1 -and $parentParts[1] -eq 'created') {
                $createdMenuId = $parentId
            }

            $body = @{ parentId = $parentId; title = ($prefix + '_BUTTON'); code = ($prefix + '_CODE'); sortCode = 9999; extJson = @{ smoke = $true } } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/sys/button/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys button add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id) {
                throw "unexpected sys button add response: $addRaw"
            }
            $buttonId = [string]$add.data.id

            $pageRaw = & curl.exe -sS -X GET "$base/sys/button/page?parentId=$parentId&searchKey=$prefix" -H "Authorization: Bearer $token"
            $page = $pageRaw | ConvertFrom-Json
            $records = @($page.data.records)
            if ([int]$page.code -ne 200 -or -not ($records | Where-Object { [string]$_.id -eq $buttonId })) {
                throw "sys button page did not include created button: $pageRaw"
            }

            $body = @{ parentId = $parentId; title = ($prefix + '_DUP'); code = ($prefix + '_CODE'); sortCode = 9998 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/sys/button/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "sys button duplicate code should fail: $duplicateRaw"
            }

            $body = @{ id = $buttonId; parentId = $parentId; title = ($prefix + '_EDITED'); code = ($prefix + '_CODE_EDITED'); sortCode = 9997; extJson = '{}' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/sys/button/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys button edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.title -ne "$($prefix)_EDITED") {
                throw "unexpected sys button edit response: $editRaw"
            }

            $relationId = 'codex-rel-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $relationCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('sys_relation')->insert(['ID' => '$relationId', 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$parentId', 'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE', 'EXT_JSON' => json_encode(['buttonInfo' => ['$buttonId', 'keep-button']], JSON_UNESCAPED_SLASHES)]);
"@
            & php -r $relationCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to prepare sys relation smoke row'
            }

            $body = @(@{ id = $buttonId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/sys/button/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys button delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected sys button delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$flag = (string)think\facade\Db::name('sys_resource')->where('ID', '$buttonId')->value('DELETE_FLAG'); `$ext = json_decode((string)think\facade\Db::name('sys_relation')->where('ID', '$relationId')->value('EXT_JSON'), true); echo `$flag . ':' . implode(',', `$ext['buttonInfo'] ?? []);"
            if ([string]$deleteState -ne 'DELETED:keep-button') {
                throw "sys button delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$buttonId = '$buttonId';
`$relationId = '$relationId';
`$createdMenuId = '$createdMenuId';
if (`$relationId !== '') { think\facade\Db::name('sys_relation')->where('ID', `$relationId)->delete(); }
if (`$buttonId !== '') { think\facade\Db::name('sys_resource')->where('ID', `$buttonId)->delete(); }
if (`$createdMenuId !== '') { think\facade\Db::name('sys_resource')->where('ID', `$createdMenuId)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($SysFieldHttpSmoke) {
    Invoke-TestStep 'authenticated sys field write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -SysFieldHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -SysFieldHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_SYS_FIELD_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_FIELD_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $moduleId = ''
        $menuId = ''
        $fieldId = ''
        $otherFieldId = ''
        $relationId = ''

        try {
            $fixtureCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$service = new app\service\sys\ResourceService();
`$payload = ['userId' => 'codex-smoke'];
`$module = `$service->moduleAdd(['title' => '$prefix' . '_MODULE', 'icon' => 'AppstoreOutlined', 'color' => '#1677FF', 'sortCode' => 9999], `$payload);
`$menu = `$service->menuAdd(['parentId' => '0', 'title' => '$prefix' . '_MENU', 'menuType' => 'MENU', 'module' => `$module['id'], 'path' => '/codex-http-field', 'name' => '$prefix' . '_MENU_NAME', 'component' => 'codex/http/field', 'visible' => 'TRUE', 'sortCode' => 9998], `$payload);
echo `$module['id'] . ':' . `$menu['id'];
"@
            $fixtureState = & php -r $fixtureCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($fixtureState)) {
                throw 'failed to prepare sys field fixtures'
            }
            $fixtureParts = ([string]$fixtureState).Split(':')
            $moduleId = $fixtureParts[0]
            $menuId = $fixtureParts[1]

            $body = @{ category = 'FIELD'; parentId = $menuId; title = ($prefix + '_FIELD'); code = ($prefix + '_CODE'); sortCode = 99; extJson = @{ smoke = $true } } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/sys/field/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys field add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id -or [string]$add.data.category -ne 'FIELD' -or [string]$add.data.parentId -ne $menuId) {
                throw "unexpected sys field add response: $addRaw"
            }
            $fieldId = [string]$add.data.id

            $pageRaw = & curl.exe -sS -X GET "$base/sys/field/page?parentId=$menuId&searchKey=$prefix" -H "Authorization: Bearer $token"
            $page = $pageRaw | ConvertFrom-Json
            $records = @($page.data.records)
            if ([int]$page.code -ne 200 -or -not ($records | Where-Object { [string]$_.id -eq $fieldId })) {
                throw "sys field page did not include created field: $pageRaw"
            }

            $detailRaw = & curl.exe -sS -X GET "$base/sys/field/detail?id=$fieldId" -H "Authorization: Bearer $token"
            $detail = $detailRaw | ConvertFrom-Json
            if ([int]$detail.code -ne 200 -or [string]$detail.data.id -ne $fieldId) {
                throw "unexpected sys field detail response: $detailRaw"
            }

            $body = @{ category = 'FIELD'; parentId = $menuId; title = ($prefix + '_DUP'); code = ($prefix + '_CODE'); sortCode = 98 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/sys/field/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "sys field duplicate code should fail: $duplicateRaw"
            }

            $body = @{ id = $fieldId; category = 'FIELD'; parentId = $menuId; title = ($prefix + '_FIELD_EDITED'); code = ($prefix + '_CODE_EDITED'); sortCode = 97; extJson = '{}' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/sys/field/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys field edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.title -ne "$($prefix)_FIELD_EDITED" -or [int]$edit.data.sortCode -ne 97) {
                throw "unexpected sys field edit response: $editRaw"
            }

            $otherCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$field = (new app\service\sys\ResourceService())->fieldAdd(['parentId' => '$menuId', 'title' => '$prefix' . '_FIELD_OTHER', 'code' => '$prefix' . '_CODE_OTHER', 'sortCode' => 96], ['userId' => 'codex-smoke']);
echo `$field['id'];
"@
            $otherFieldId = & php -r $otherCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($otherFieldId)) {
                throw 'failed to prepare sys field sibling'
            }
            $otherFieldId = [string]$otherFieldId

            $relationId = 'cfr' + (Get-Random -Minimum 100000 -Maximum 999999)
            $relationCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('sys_relation')->insert(['ID' => '$relationId', 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$fieldId', 'CATEGORY' => 'SYS_ROLE_HAS_RESOURCE', 'EXT_JSON' => '{}']);
"@
            & php -r $relationCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to prepare sys field relation smoke row'
            }

            $body = @(@{ id = $fieldId }, @{ id = 'missing-sys-field' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/sys/field/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'sys field delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200 -or [int]$delete.data.count -ne 1) {
                throw "unexpected sys field delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$fieldFlag = (string)think\facade\Db::name('sys_resource')->where('ID', '$fieldId')->value('DELETE_FLAG'); `$otherFlag = (string)think\facade\Db::name('sys_resource')->where('ID', '$otherFieldId')->value('DELETE_FLAG'); `$relationCount = think\facade\Db::name('sys_relation')->where('ID', '$relationId')->count(); echo `$fieldFlag . ':' . `$otherFlag . ':' . `$relationCount;"
            if ([string]$deleteState -ne 'DELETED:NOT_DELETE:0') {
                throw "sys field delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$ids = array_values(array_filter(['$moduleId', '$menuId', '$fieldId', '$otherFieldId']));
if ('$relationId' !== '') { think\facade\Db::name('sys_relation')->where('ID', '$relationId')->delete(); }
if (`$ids !== []) { think\facade\Db::name('sys_resource')->whereIn('ID', `$ids)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($MobileModuleHttpSmoke) {
    Invoke-TestStep 'authenticated mobile module write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -MobileModuleHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -MobileModuleHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_MOBILE_MODULE_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_MOBILE_MODULE_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $moduleId = ''
        $menuId = ''
        $childMenuId = ''
        $relationId = ''

        try {
            $body = @{ title = ($prefix + '_MODULE'); icon = 'HomeOutlined'; color = '#1677FF'; sortCode = 9999; extJson = @{ smoke = $true } } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/mobile/module/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile module add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id -or [string]$add.data.category -ne 'MODULE') {
                throw "unexpected mobile module add response: $addRaw"
            }
            $moduleId = [string]$add.data.id

            $pageRaw = & curl.exe -sS -X GET "$base/mobile/module/page?searchKey=$prefix" -H "Authorization: Bearer $token"
            $page = $pageRaw | ConvertFrom-Json
            $records = @($page.data.records)
            if ([int]$page.code -ne 200 -or -not ($records | Where-Object { [string]$_.id -eq $moduleId })) {
                throw "mobile module page did not include created module: $pageRaw"
            }

            $body = @{ title = ($prefix + '_MODULE'); icon = 'HomeOutlined'; color = '#1677FF'; sortCode = 9998 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/mobile/module/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "mobile module duplicate title should fail: $duplicateRaw"
            }

            $body = @{ id = $moduleId; title = ($prefix + '_EDITED'); icon = 'SettingOutlined'; color = '#13C2C2'; sortCode = 9997; extJson = '{}' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/mobile/module/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile module edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.title -ne "$($prefix)_EDITED" -or [string]$edit.data.icon -ne 'SettingOutlined') {
                throw "unexpected mobile module edit response: $editRaw"
            }

            $menuId = 'cmmenu-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $childMenuId = 'cmchild-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $relationId = 'cmodrel-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $fixtureCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('mobile_resource')->insert(['ID' => '$menuId', 'PARENT_ID' => '0', 'TITLE' => '$prefix' . '_MENU', 'CODE' => '$prefix' . '_MENU', 'CATEGORY' => 'MENU', 'MODULE' => '$moduleId', 'SORT_CODE' => 9999, 'DELETE_FLAG' => 'NOT_DELETE', 'TENANT_ID' => '0', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
think\facade\Db::name('mobile_resource')->insert(['ID' => '$childMenuId', 'PARENT_ID' => '$menuId', 'TITLE' => '$prefix' . '_CHILD_MENU', 'CODE' => '$prefix' . '_CHILD_MENU', 'CATEGORY' => 'MENU', 'MODULE' => '$moduleId', 'SORT_CODE' => 9998, 'DELETE_FLAG' => 'NOT_DELETE', 'TENANT_ID' => '0', 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
think\facade\Db::name('sys_relation')->insert(['ID' => '$relationId', 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$childMenuId', 'CATEGORY' => 'SYS_ROLE_HAS_MOBILE_MENU', 'EXT_JSON' => json_encode(['buttonInfo' => ['keep-mobile-button']], JSON_UNESCAPED_SLASHES)]);
"@
            & php -r $fixtureCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to prepare mobile module fixtures'
            }

            $body = @(@{ id = $moduleId }, @{ id = 'missing-mobile-module' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/mobile/module/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile module delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected mobile module delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$flags = think\facade\Db::name('mobile_resource')->whereIn('ID', ['$moduleId', '$menuId', '$childMenuId'])->column('DELETE_FLAG', 'ID'); `$relationCount = think\facade\Db::name('sys_relation')->where('ID', '$relationId')->count(); echo implode(',', [`$flags['$moduleId'] ?? '', `$flags['$menuId'] ?? '', `$flags['$childMenuId'] ?? '']) . ':' . `$relationCount;"
            if ([string]$deleteState -ne 'DELETED,DELETED,DELETED:0') {
                throw "mobile module delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$ids = array_values(array_filter(['$moduleId', '$menuId', '$childMenuId']));
if ('$relationId' !== '') { think\facade\Db::name('sys_relation')->where('ID', '$relationId')->delete(); }
if (`$ids !== []) { think\facade\Db::name('mobile_resource')->whereIn('ID', `$ids)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($MobileMenuHttpSmoke) {
    Invoke-TestStep 'authenticated mobile menu write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -MobileMenuHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -MobileMenuHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_MOBILE_MENU_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_MOBILE_MENU_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $moduleAId = ''
        $moduleBId = ''
        $rootId = ''
        $childId = ''
        $buttonId = ''
        $relationId = ''

        try {
            $moduleCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$service = new app\service\mobile\MobileResourceService();
`$payload = ['userId' => 'codex-smoke', 'tenantId' => '0'];
`$moduleA = `$service->moduleAdd(['title' => '$prefix' . '_MODULE_A', 'icon' => 'HomeOutlined', 'color' => '#1677FF', 'sortCode' => 9999], `$payload);
`$moduleB = `$service->moduleAdd(['title' => '$prefix' . '_MODULE_B', 'icon' => 'SettingOutlined', 'color' => '#13C2C2', 'sortCode' => 9998], `$payload);
echo `$moduleA['id'] . ':' . `$moduleB['id'];
"@
            $moduleState = & php -r $moduleCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($moduleState)) {
                throw 'failed to prepare mobile menu module fixtures'
            }
            $moduleParts = ([string]$moduleState).Split(':')
            $moduleAId = $moduleParts[0]
            $moduleBId = $moduleParts[1]

            $body = @{ parentId = '0'; title = ($prefix + '_ROOT'); category = 'MENU'; module = $moduleAId; menuType = 'MENU'; path = '/codex-mobile-root'; icon = 'HomeOutlined'; color = '#1677FF'; regType = 'YES'; status = 'ENABLE'; sortCode = 9999 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/mobile/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile menu add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id -or [string]$add.data.category -ne 'MENU') {
                throw "unexpected mobile menu add response: $addRaw"
            }
            $rootId = [string]$add.data.id

            $treeRaw = & curl.exe -sS -X GET "$base/mobile/menu/tree?module=$moduleAId&searchKey=$prefix" -H "Authorization: Bearer $token"
            $tree = $treeRaw | ConvertFrom-Json
            $treeRows = @($tree.data)
            if ([int]$tree.code -ne 200 -or -not ($treeRows | Where-Object { [string]$_.id -eq $rootId })) {
                throw "mobile menu tree did not include created root menu: $treeRaw"
            }

            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/mobile/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "mobile menu duplicate title should fail: $duplicateRaw"
            }

            $body = @{ parentId = $rootId; title = ($prefix + '_CHILD'); category = 'MENU'; module = $moduleAId; menuType = 'MENU'; path = '/codex-mobile-child'; icon = 'BarsOutlined'; color = '#722ED1'; regType = 'YES'; status = 'ENABLE'; sortCode = 9997 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $childRaw = & curl.exe -sS -X POST "$base/mobile/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $child = $childRaw | ConvertFrom-Json
            if ([int]$child.code -ne 200 -or -not $child.data.id) {
                throw "unexpected mobile child menu add response: $childRaw"
            }
            $childId = [string]$child.data.id

            $body = @{ parentId = $rootId; title = ($prefix + '_BAD_CHILD'); category = 'MENU'; module = $moduleBId; menuType = 'MENU'; path = '/codex-mobile-bad-child'; icon = 'BarsOutlined'; color = '#722ED1'; regType = 'YES'; status = 'ENABLE'; sortCode = 9996 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $badChildRaw = & curl.exe -sS -X POST "$base/mobile/menu/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $badChild = $badChildRaw | ConvertFrom-Json
            if ([int]$badChild.code -eq 200) {
                throw "mobile menu parent/module mismatch should fail: $badChildRaw"
            }

            $body = @{ id = $childId; parentId = $rootId; title = ($prefix + '_CHILD_EDITED'); category = 'MENU'; module = $moduleAId; menuType = 'IFRAME'; path = 'https://example.test/mobile'; icon = 'LinkOutlined'; color = '#13C2C2'; regType = 'NO'; status = 'DISABLE'; sortCode = 9995 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/mobile/menu/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile menu edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.menuType -ne 'IFRAME' -or [string]$edit.data.status -ne 'DISABLE') {
                throw "unexpected mobile menu edit response: $editRaw"
            }

            $body = @{ id = $childId; module = $moduleBId } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $badChangeRaw = & curl.exe -sS -X POST "$base/mobile/menu/changeModule" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $badChange = $badChangeRaw | ConvertFrom-Json
            if ([int]$badChange.code -eq 200) {
                throw "child mobile menu changeModule should fail: $badChangeRaw"
            }

            $body = @{ id = $rootId; module = $moduleBId } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $changeRaw = & curl.exe -sS -X POST "$base/mobile/menu/changeModule" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $change = $changeRaw | ConvertFrom-Json
            if ([int]$change.code -ne 200 -or [int]$change.data.count -ne 2) {
                throw "unexpected mobile menu changeModule response: $changeRaw"
            }

            $fixtureCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$service = new app\service\mobile\MobileResourceService();
`$button = `$service->buttonAdd(['parentId' => '$childId', 'title' => '$prefix' . '_BUTTON', 'code' => '$prefix' . '_BUTTON_CODE', 'sortCode' => 9994], ['userId' => 'codex-smoke', 'tenantId' => '0']);
think\facade\Db::name('sys_relation')->insert(['ID' => 'cmnrel-' . random_int(100000, 999999), 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$childId', 'CATEGORY' => 'SYS_ROLE_HAS_MOBILE_MENU', 'EXT_JSON' => json_encode(['buttonInfo' => [`$button['id']]], JSON_UNESCAPED_SLASHES)]);
`$relationId = (string)think\facade\Db::name('sys_relation')->where('TARGET_ID', '$childId')->where('CATEGORY', 'SYS_ROLE_HAS_MOBILE_MENU')->order('ID', 'desc')->value('ID');
echo `$button['id'] . ':' . `$relationId;
"@
            $fixtureState = & php -r $fixtureCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($fixtureState)) {
                throw 'failed to prepare mobile menu delete fixtures'
            }
            $fixtureParts = ([string]$fixtureState).Split(':')
            $buttonId = $fixtureParts[0]
            $relationId = $fixtureParts[1]

            $body = @(@{ id = $rootId }, @{ id = 'missing-mobile-menu' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/mobile/menu/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile menu delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected mobile menu delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$flags = think\facade\Db::name('mobile_resource')->whereIn('ID', ['$rootId', '$childId', '$buttonId'])->column('DELETE_FLAG', 'ID'); `$relationCount = think\facade\Db::name('sys_relation')->where('ID', '$relationId')->count(); echo implode(',', [`$flags['$rootId'] ?? '', `$flags['$childId'] ?? '', `$flags['$buttonId'] ?? '']) . ':' . `$relationCount;"
            if ([string]$deleteState -ne 'DELETED,DELETED,NOT_DELETE:0') {
                throw "mobile menu delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$ids = array_values(array_filter(['$moduleAId', '$moduleBId', '$rootId', '$childId', '$buttonId']));
if ('$relationId' !== '') { think\facade\Db::name('sys_relation')->where('ID', '$relationId')->delete(); }
if (`$ids !== []) { think\facade\Db::name('mobile_resource')->whereIn('ID', `$ids)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($MobileButtonHttpSmoke) {
    Invoke-TestStep 'authenticated mobile button write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -MobileButtonHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -MobileButtonHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_MOBILE_BUTTON_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_MOBILE_BUTTON_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $buttonId = ''
        $relationId = ''
        $createdMenuId = ''

        try {
            $parentCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$parent = think\facade\Db::name('mobile_resource')->where('CATEGORY', 'MENU')->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })->field(['ID', 'TENANT_ID'])->find();
`$parentId = (string)(`$parent['ID'] ?? '');
`$tenantId = (string)(`$parent['TENANT_ID'] ?? '0');
if (`$parentId === '') {
    `$parentId = 'cmenu-' . random_int(100000, 999999);
    think\facade\Db::name('mobile_resource')->insert(['ID' => `$parentId, 'TITLE' => '$prefix' . '_MENU', 'CODE' => '$prefix' . '_MENU', 'CATEGORY' => 'MENU', 'SORT_CODE' => 9999, 'DELETE_FLAG' => 'NOT_DELETE', 'TENANT_ID' => `$tenantId, 'CREATE_TIME' => date('Y-m-d H:i:s'), 'CREATE_USER' => 'codex-smoke']);
    echo `$parentId . ':created';
} else {
    echo `$parentId . ':existing';
}
"@
            $parentState = & php -r $parentCode
            if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($parentState)) {
                throw 'failed to prepare mobile button parent menu'
            }
            $parentParts = ([string]$parentState).Split(':')
            $parentId = $parentParts[0]
            if ($parentParts.Length -gt 1 -and $parentParts[1] -eq 'created') {
                $createdMenuId = $parentId
            }

            $body = @{ parentId = $parentId; title = ($prefix + '_BUTTON'); code = ($prefix + '_CODE'); sortCode = 9999; extJson = @{ smoke = $true } } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/mobile/button/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile button add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id) {
                throw "unexpected mobile button add response: $addRaw"
            }
            $buttonId = [string]$add.data.id

            $pageRaw = & curl.exe -sS -X GET "$base/mobile/button/page?parentId=$parentId&searchKey=$prefix" -H "Authorization: Bearer $token"
            $page = $pageRaw | ConvertFrom-Json
            $records = @($page.data.records)
            if ([int]$page.code -ne 200 -or -not ($records | Where-Object { [string]$_.id -eq $buttonId })) {
                throw "mobile button page did not include created button: $pageRaw"
            }

            $body = @{ parentId = $parentId; title = ($prefix + '_DUP'); code = ($prefix + '_CODE'); sortCode = 9998 } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $duplicateRaw = & curl.exe -sS -X POST "$base/mobile/button/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            $duplicate = $duplicateRaw | ConvertFrom-Json
            if ([int]$duplicate.code -eq 200) {
                throw "mobile button duplicate code should fail: $duplicateRaw"
            }

            $body = @{ id = $buttonId; parentId = $parentId; title = ($prefix + '_EDITED'); code = ($prefix + '_CODE_EDITED'); sortCode = 9997; extJson = '{}' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/mobile/button/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile button edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200 -or [string]$edit.data.title -ne "$($prefix)_EDITED") {
                throw "unexpected mobile button edit response: $editRaw"
            }

            $relationId = 'cmobrel-' + (Get-Random -Minimum 100000 -Maximum 999999)
            $relationCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
think\facade\Db::name('sys_relation')->insert(['ID' => '$relationId', 'OBJECT_ID' => 'codex-role', 'TARGET_ID' => '$parentId', 'CATEGORY' => 'SYS_ROLE_HAS_MOBILE_MENU', 'EXT_JSON' => json_encode(['buttonInfo' => ['$buttonId', 'keep-mobile-button']], JSON_UNESCAPED_SLASHES)]);
"@
            & php -r $relationCode | Out-Null
            if ($LASTEXITCODE -ne 0) {
                throw 'failed to prepare mobile relation smoke row'
            }

            $body = @(@{ id = $buttonId }, @{ id = 'missing-mobile-btn' }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/mobile/button/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'mobile button delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected mobile button delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$flag = (string)think\facade\Db::name('mobile_resource')->where('ID', '$buttonId')->value('DELETE_FLAG'); `$ext = json_decode((string)think\facade\Db::name('sys_relation')->where('ID', '$relationId')->value('EXT_JSON'), true); echo `$flag . ':' . implode(',', `$ext['buttonInfo'] ?? []);"
            if ([string]$deleteState -ne 'DELETED:keep-mobile-button') {
                throw "mobile button delete did not update expected state: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$buttonId = '$buttonId';
`$relationId = '$relationId';
`$createdMenuId = '$createdMenuId';
if (`$relationId !== '') { think\facade\Db::name('sys_relation')->where('ID', `$relationId)->delete(); }
if (`$buttonId !== '') { think\facade\Db::name('mobile_resource')->where('ID', `$buttonId)->delete(); }
if (`$createdMenuId !== '') { think\facade\Db::name('mobile_resource')->where('ID', `$createdMenuId)->delete(); }
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

if ($TeamProjectHttpSmoke) {
    Invoke-TestStep 'authenticated team project base write HTTP smoke' {
        if ($BackendBaseUrl.Trim() -eq '') {
            throw 'BackendBaseUrl is required when -TeamProjectHttpSmoke is used'
        }

        $envMap = Get-EnvMap -Path (Join-Path $ProjectRoot '.env')
        $account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
        if ($account -eq '') {
            throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env when -TeamProjectHttpSmoke is used'
        }

        $safeAccount = $account.Replace("'", "\'")
        $tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_TEAM_PROJECT_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@
        $token = & php -r $tokenCode
        if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
            throw 'failed to create local smoke auth token'
        }

        $base = $BackendBaseUrl.TrimEnd('/')
        $prefix = 'CODEX_HTTP_TP_' + (Get-Date -Format 'yyyyMMddHHmmss') + '_' + (Get-Random -Minimum 1000 -Maximum 9999)
        $jsonTmp = Join-Path ([System.IO.Path]::GetTempPath()) ($prefix + '.json')
        $projectId = ''
        $memberId = ''

        try {
            $body = @{ name = 'CODEX_HTTP_TP'; description = ($prefix + '_ADD') } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $addRaw = & curl.exe -sS -X POST "$base/biz/bizteamproject/add" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'team project add request failed'
            }
            $add = $addRaw | ConvertFrom-Json
            if ([int]$add.code -ne 200 -or -not $add.data.id) {
                throw "unexpected team project add response: $addRaw"
            }
            $projectId = [string]$add.data.id

            $createdState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$p = think\facade\Db::name('biz_team_project')->where('ID', '$projectId')->find(); `$m = think\facade\Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', '$projectId')->where('ROLE_TYPE', 'LEADER')->find(); `$r = think\facade\Db::name('biz_relation')->where('OBJECT_ID', '$projectId')->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')->find(); echo (string)`$p['DESCRIPTION'] . ':' . (string)`$m['ROLE_TYPE'] . ':' . (string)(str_contains((string)`$r['EXT_JSON'], 'delProject') ? 'PERM' : 'NOPERM') . ':' . (string)`$m['ID'];"
            $createdParts = ([string]$createdState).Split(':')
            if ($createdParts.Length -ne 4 -or $createdParts[0] -ne "$($prefix)_ADD" -or $createdParts[1] -ne 'LEADER' -or $createdParts[2] -ne 'PERM' -or [string]::IsNullOrWhiteSpace($createdParts[3])) {
                throw "team project add did not create expected rows: $createdState"
            }
            $memberId = $createdParts[3]

            $body = @{ id = $memberId; roleType = 'MEMBER' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $memberEditRaw = & curl.exe -sS -X POST "$base/biz/bizteamprojectuser/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'team project user edit request failed'
            }
            $memberEdit = $memberEditRaw | ConvertFrom-Json
            if ([int]$memberEdit.code -ne 200) {
                throw "unexpected team project user edit response: $memberEditRaw"
            }
            $memberState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$m = think\facade\Db::name('biz_team_project_user')->where('ID', '$memberId')->find(); `$r = think\facade\Db::name('biz_relation')->where('OBJECT_ID', '$projectId')->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')->find(); echo (string)`$m['ROLE_TYPE'] . ':' . (string)(str_contains((string)`$r['EXT_JSON'], 'delProject') ? 'PERM' : 'NOPERM') . ':' . (string)(empty(`$m['UPDATE_TIME']) ? 'NOAUDIT' : 'AUDIT');"
            if ([string]$memberState -ne 'LEADER:PERM:AUDIT') {
                throw "team project user edit should not mutate role or permissions: $memberState"
            }

            $body = @{ id = $projectId; description = ($prefix + '_EDIT'); projectStatus = 'COMPLETE'; completionTime = '2026-06-08 10:00:00' } | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $editRaw = & curl.exe -sS -X POST "$base/biz/bizteamproject/edit" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'team project edit request failed'
            }
            $edit = $editRaw | ConvertFrom-Json
            if ([int]$edit.code -ne 200) {
                throw "unexpected team project edit response: $editRaw"
            }

            $editedState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); `$p = think\facade\Db::name('biz_team_project')->where('ID', '$projectId')->find(); echo (string)`$p['DESCRIPTION'] . ':' . (string)`$p['PROJECT_STATUS'] . ':' . (int)`$p['VERSION'];"
            $editedParts = ([string]$editedState).Split(':')
            if ($editedParts.Length -ne 3 -or $editedParts[0] -ne "$($prefix)_EDIT" -or $editedParts[1] -ne 'COMPLETE' -or [int]$editedParts[2] -lt 1) {
                throw "team project edit did not update expected fields: $editedState"
            }

            $body = @(@{ id = $projectId }) | ConvertTo-Json -Compress
            Set-Content -LiteralPath $jsonTmp -Value $body -Encoding ASCII
            $deleteRaw = & curl.exe -sS -X POST "$base/biz/bizteamproject/delete" -H "Authorization: Bearer $token" -H 'Content-Type: application/json' --data-binary "@$jsonTmp"
            if ($LASTEXITCODE -ne 0) {
                throw 'team project delete request failed'
            }
            $delete = $deleteRaw | ConvertFrom-Json
            if ([int]$delete.code -ne 200) {
                throw "unexpected team project delete response: $deleteRaw"
            }

            $deleteState = & php -r "require getcwd() . '/vendor/autoload.php'; (new think\App(getcwd()))->initialize(); echo (string)think\facade\Db::name('biz_team_project')->where('ID', '$projectId')->value('DELETE_FLAG') . ':' . (string)think\facade\Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', '$projectId')->value('DELETE_FLAG');"
            if ([string]$deleteState -ne 'DELETED:DELETED') {
                throw "team project delete did not soft-delete expected rows: $deleteState"
            }
        } finally {
            $cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$projectId = '$projectId';
if (`$projectId !== '') {
    think\facade\Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', `$projectId)->delete();
    think\facade\Db::name('biz_relation')->where('OBJECT_ID', `$projectId)->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')->delete();
    think\facade\Db::name('biz_team_project')->where('ID', `$projectId)->delete();
}
"@
            & php -r $cleanupCode | Out-Null
            if (Test-Path -LiteralPath $jsonTmp) {
                Remove-Item -LiteralPath $jsonTmp -Force
            }
        }
    }
}

Write-Host '[test-agent] smoke run completed'
