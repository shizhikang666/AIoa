param(
    [switch]$SkipComposer,
    [string]$BackendBaseUrl = '',
    [switch]$NoTokenSmoke,
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
