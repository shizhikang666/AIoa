param(
    [switch]$SkipComposer,
    [string]$BackendBaseUrl = '',
    [switch]$NoTokenSmoke,
    [switch]$DevFileHttpSmoke,
    [switch]$DevEmailSmsHttpSmoke,
    [switch]$DevConfigHttpSmoke,
    [switch]$DevLogHttpSmoke,
    [switch]$DevJobHttpSmoke,
    [switch]$FileRelationHttpSmoke
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

Write-Host '[test-agent] smoke run completed'
