param(
    [string]$BackendBaseUrl = 'http://127.0.0.1:82',
    [string]$EnvPath = (Join-Path (Resolve-Path (Join-Path $PSScriptRoot '..')) '.env')
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$ProjectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $ProjectRoot

function Get-EnvMap {
    param([Parameter(Mandatory = $true)][string]$Path)

    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Missing local env file: $Path"
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

function Invoke-RawGet {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [string]$Token = ''
    )

    $args = @('-sS', '-X', 'GET', $Url)
    if ($Token.Trim() -ne '') {
        $args += @('-H', "Authorization: Bearer $Token")
    }

    $raw = & curl.exe @args
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
}

function Invoke-RawPostJson {
    param(
        [Parameter(Mandatory = $true)][string]$Url,
        [Parameter(Mandatory = $true)]$Data,
        [string]$Token = ''
    )

    $tmp = Join-Path ([System.IO.Path]::GetTempPath()) ("codex-team-project-task-{0}.json" -f ([Guid]::NewGuid().ToString('N')))
    try {
        ConvertTo-Json -InputObject $Data -Depth 12 -Compress | Set-Content -LiteralPath $tmp -Encoding ASCII
        $args = @('-sS', '-X', 'POST', $Url, '-H', 'Content-Type: application/json', '--data-binary', "@$tmp")
        if ($Token.Trim() -ne '') {
            $args += @('-H', "Authorization: Bearer $Token")
        }

        $raw = & curl.exe @args
        if ($LASTEXITCODE -ne 0) {
            throw "HTTP POST failed: $Url"
        }

        return [string]::Join('', [string[]]$raw)
    } finally {
        Remove-Item -LiteralPath $tmp -Force -ErrorAction SilentlyContinue
    }
}

function Read-JsonPath {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [switch]$Optional
    )

    $value = $Json | node (Join-Path $PSScriptRoot 'json-read.js') $Path
    $exitCode = $LASTEXITCODE
    if ($exitCode -eq 2 -and $Optional) {
        return $null
    }
    if ($exitCode -ne 0) {
        throw "JSON path missing or invalid: $Path"
    }

    return [string]$value
}

function Assert-Code {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][int]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = [int](Read-JsonPath -Json $Json -Path 'code')
    if ($code -ne $Expected) {
        throw "$Name returned code=$code expected=$Expected body=$Json"
    }
}

function Assert-PathEquals {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$Expected,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $actual = [string](Read-JsonPath -Json $Json -Path $Path)
    if ($actual -ne $Expected) {
        throw "$Name expected $Path=$Expected actual=$actual body=$Json"
    }
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Invoke-Php {
    param([Parameter(Mandatory = $true)][string]$Code)

    $output = & php -r $Code
    if ($LASTEXITCODE -ne 0) {
        throw 'php inline command failed'
    }
    if ($null -eq $output) {
        return ''
    }

    return [string]::Join('', [string[]]$output)
}

function Invoke-PhpJson {
    param([Parameter(Mandatory = $true)][string]$Code)

    $raw = Invoke-Php -Code $Code
    if ([string]::IsNullOrWhiteSpace($raw)) {
        throw 'php inline json command returned no output'
    }

    return $raw | ConvertFrom-Json
}

function New-SmokeId {
    param([Parameter(Mandatory = $true)][string]$Prefix)

    return $Prefix + ([Guid]::NewGuid().ToString('N').Substring(0, 20 - $Prefix.Length))
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$sessionCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_TEAM_PROJECT_TASK_WRITE_HTTP_SMOKE';
`$tenantId = (string)(`$user['TENANT_ID'] ?? '1');
if (`$tenantId === '') { `$tenantId = '1'; }
`$orgId = (string)(`$user['ORG_ID'] ?? '');
if (`$orgId === '') {
    `$orgId = (string)(think\facade\Db::name('sys_org')->where(function (`$query) {
        `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE');
    })->value('ID') ?? '');
}
echo json_encode([
    'token' => (new app\service\auth\TokenService())->create(`$user, `$auth),
    'userId' => (string)`$user['ID'],
    'tenantId' => `$tenantId,
    'orgId' => `$orgId,
], JSON_UNESCAPED_SLASHES);
"@

$session = Invoke-PhpJson -Code $sessionCode
$token = [string]$session.token
$adminUserId = [string]$session.userId
$tenantId = [string]$session.tenantId
$orgId = [string]$session.orgId
if ($token.Trim() -eq '' -or $adminUserId.Trim() -eq '' -or $tenantId.Trim() -eq '' -or $orgId.Trim() -eq '') {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')
$prefix = 'tpt' + ([Guid]::NewGuid().ToString('N').Substring(0, 9))
$tempUserId = New-SmokeId -Prefix 'TPU'
$missingCategoryId = New-SmokeId -Prefix 'TPC'
$missingTaskId = New-SmokeId -Prefix 'TPT'

$safePrefix = $prefix.Replace("'", "\'")
$safeTempUserId = $tempUserId.Replace("'", "\'")
$safeAdminUserId = $adminUserId.Replace("'", "\'")
$safeTenantId = $tenantId.Replace("'", "\'")
$safeOrgId = $orgId.Replace("'", "\'")

$cleanupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$projectIds = think\facade\Db::name('biz_team_project')->whereLike('DESCRIPTION', '$safePrefix%')->column('ID');
`$projectIds = array_values(array_filter(array_map('strval', `$projectIds)));
if (`$projectIds !== []) {
    think\facade\Db::name('biz_team_project_task_comment')->whereIn('TEAM_PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_team_project_task_user')->whereIn('TEAM_PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_team_project_task')->whereIn('TEAM_PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_team_project_task_category')->whereIn('TEAM_PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_team_project_comment_reply')->whereIn('TARGET_ID', function (`$query) use (`$projectIds) {
        `$query->name('biz_team_project_comment')->whereIn('TEAM_PROJECT_ID', `$projectIds)->field('ID');
    })->delete();
    think\facade\Db::name('biz_team_project_comment')->whereIn('TEAM_PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_team_project_user')->whereIn('TEAM_PROJECT_ID', `$projectIds)->delete();
    think\facade\Db::name('biz_relation')->whereIn('OBJECT_ID', `$projectIds)->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')->delete();
    think\facade\Db::name('biz_team_project')->whereIn('ID', `$projectIds)->delete();
}
think\facade\Db::name('biz_relation')->where('TARGET_ID', '$safeTempUserId')->where('CATEGORY', 'TEAM_PROJECT_USER_HAS_RESOURCE_PERMISSION')->delete();
think\facade\Db::name('sys_user')->where('ID', '$safeTempUserId')->delete();
think\facade\Db::name('sys_user')->whereLike('ACCOUNT', '$safePrefix%')->delete();
echo 'ok';
"@

Invoke-Php -Code $cleanupCode | Out-Null

$setupCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$now = date('Y-m-d H:i:s');
think\facade\Db::name('sys_user')->insert([
    'ID' => '$safeTempUserId',
    'ACCOUNT' => '$safePrefix',
    'NAME' => '$safePrefix user',
    'ORG_ID' => '$safeOrgId',
    'USER_STATUS' => 'ENABLE',
    'DELETE_FLAG' => 'NOT_DELETE',
    'CREATE_TIME' => `$now,
    'CREATE_USER' => '$safeAdminUserId',
    'TENANT_ID' => '$safeTenantId',
    'BANK_NAME' => '',
    'BANK_ACCOUNT' => '',
    'BASIC_SALARY' => '0.00',
]);
echo 'ok';
"@

$projectId = ''
$categoryAId = ''
$categoryBId = ''
$taskId = ''
$commentId = ''

try {
    Invoke-Php -Code $setupCode | Out-Null

    $noToken = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/add" -Data @{
        teamProjectId = 'missing-project'
        title = 'no-token'
    }
    Assert-Code -Json $noToken -Expected 401 -Name 'team project task category add without token'

    $projectAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamproject/add" -Token $token -Data @{
        name = 'CODEX_TPTASK'
        description = "$prefix project"
    }
    Assert-Code -Json $projectAdd -Expected 200 -Name 'team project add'
    $projectId = [string](Read-JsonPath -Json $projectAdd -Path 'data.id')
    if ($projectId.Trim() -eq '') {
        throw 'team project add did not return data.id'
    }

    $memberAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojectuser/add" -Token $token -Data @{
        teamProjectId = $projectId
        user = @($tempUserId)
    }
    Assert-Code -Json $memberAdd -Expected 200 -Name 'team project user add'
    Assert-PathEquals -Json $memberAdd -Path 'data.count' -Expected '1' -Name 'team project user add'

    $badCategoryAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/add" -Token $token -Data @{
        teamProjectId = $projectId
    }
    Assert-Code -Json $badCategoryAdd -Expected 400 -Name 'team project task category add missing title'

    $categoryAAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/add" -Token $token -Data @{
        teamProjectId = $projectId
        title = "$prefix TODO"
        sortCode = 5
        extJson = '{"lane":"todo"}'
    }
    Assert-Code -Json $categoryAAdd -Expected 200 -Name 'team project task category add A'
    $categoryAId = [string](Read-JsonPath -Json $categoryAAdd -Path 'data.id')
    Assert-PathEquals -Json $categoryAAdd -Path 'data.title' -Expected "$prefix TODO" -Name 'team project task category add A'

    $categoryBAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/add" -Token $token -Data @{
        teamProjectId = $projectId
        title = "$prefix DONE"
        sortCode = 6
        extJson = '{"lane":"done"}'
    }
    Assert-Code -Json $categoryBAdd -Expected 200 -Name 'team project task category add B'
    $categoryBId = [string](Read-JsonPath -Json $categoryBAdd -Path 'data.id')

    $categorySort = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/sort/edit" -Token $token -Data @(
        @{ id = $categoryBId },
        @{ id = $categoryAId }
    )
    Assert-Code -Json $categorySort -Expected 200 -Name 'team project task category sort edit'
    Assert-PathEquals -Json $categorySort -Path 'data.count' -Expected '2' -Name 'team project task category sort edit'

    $categoryAEdit = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/edit" -Token $token -Data @{
        id = $categoryAId
        teamProjectId = $projectId
        title = "$prefix DOING"
        sortCode = 2
        extJson = '{"lane":"doing"}'
    }
    Assert-Code -Json $categoryAEdit -Expected 200 -Name 'team project task category edit A'

    $categoryADetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcategory/detail?id=$(Enc $categoryAId)" -Token $token
    Assert-Code -Json $categoryADetail -Expected 200 -Name 'team project task category detail A'
    Assert-PathEquals -Json $categoryADetail -Path 'data.title' -Expected "$prefix DOING" -Name 'team project task category detail A'
    Assert-PathEquals -Json $categoryADetail -Path 'data.sortCode' -Expected '2' -Name 'team project task category detail A'

    $badTaskAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/add" -Token $token -Data @{
        teamProjectId = $projectId
        teamProjectTaskCategoryId = $missingCategoryId
        title = "$prefix bad task"
    }
    Assert-Code -Json $badTaskAdd -Expected 404 -Name 'team project task add missing category'

    $taskAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/add" -Token $token -Data @{
        teamProjectId = $projectId
        teamProjectTaskCategoryId = $categoryAId
        title = "$prefix task"
        contentText = "$prefix content"
        sortCode = 4
        extJson = '{"tag":"smoke"}'
        user = @($tempUserId)
    }
    Assert-Code -Json $taskAdd -Expected 200 -Name 'team project task add'
    $taskId = [string](Read-JsonPath -Json $taskAdd -Path 'data.id')
    Assert-PathEquals -Json $taskAdd -Path 'data.status' -Expected 'TODO' -Name 'team project task add'
    Assert-PathEquals -Json $taskAdd -Path 'data.progress' -Expected '0' -Name 'team project task add'

    $blockedCategoryDelete = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/delete" -Token $token -Data @(
        @{ id = $categoryAId }
    )
    Assert-Code -Json $blockedCategoryDelete -Expected 400 -Name 'team project task category delete non-empty'

    $taskUserRemove = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/user/edit" -Token $token -Data @{
        id = $taskId
        user = @($adminUserId)
    }
    Assert-Code -Json $taskUserRemove -Expected 200 -Name 'team project task user remove'

    $taskUserReadd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/user/edit" -Token $token -Data @{
        id = $taskId
        user = @($adminUserId, $tempUserId)
    }
    Assert-Code -Json $taskUserReadd -Expected 200 -Name 'team project task user readd'

    $taskDetailForUserSync = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttask/detail?id=$(Enc $taskId)" -Token $token
    Assert-Code -Json $taskDetailForUserSync -Expected 200 -Name 'team project task detail for user sync'
    $taskDetailForUserSyncObj = $taskDetailForUserSync | ConvertFrom-Json
    $detailUsers = @($taskDetailForUserSyncObj.data.users)
    if ($detailUsers.Count -lt 2) {
        throw "team project task detail for user sync expected at least 2 users body=$taskDetailForUserSync"
    }
    foreach ($detailUser in $detailUsers) {
        if ([string]$detailUser.id -ne [string]$detailUser.userId) {
            throw "team project task detail user id must be userId body=$taskDetailForUserSync"
        }
        if ([string]$detailUser.taskUserId -eq '') {
            throw "team project task detail user missing taskUserId body=$taskDetailForUserSync"
        }
    }
    $taskUserDetailRoundTrip = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/user/edit" -Token $token -Data @{
        id = $taskId
        user = $detailUsers
    }
    Assert-Code -Json $taskUserDetailRoundTrip -Expected 200 -Name 'team project task user detail round trip'

    $badCommentAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcomment/add" -Token $token -Data @{
        teamProjectTaskId = $missingTaskId
        contentText = "$prefix bad comment"
        files = @()
    }
    Assert-Code -Json $badCommentAdd -Expected 404 -Name 'team project task comment add missing task'

    $commentAdd = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcomment/add" -Token $token -Data @{
        teamProjectTaskId = $taskId
        contentText = "$prefix comment"
        files = @(
            @{ name = 'file-a.txt'; url = 'http://127.0.0.1/file-a.txt' }
        )
    }
    Assert-Code -Json $commentAdd -Expected 200 -Name 'team project task comment add'
    $commentId = [string](Read-JsonPath -Json $commentAdd -Path 'data.id')

    $commentEdit = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcomment/edit" -Token $token -Data @{
        id = $commentId
        contentText = "$prefix edited comment"
        files = @(
            @{ name = 'file-b.txt'; url = 'http://127.0.0.1/file-b.txt' }
        )
    }
    Assert-Code -Json $commentEdit -Expected 200 -Name 'team project task comment edit'

    $commentDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcomment/detail?id=$(Enc $commentId)" -Token $token
    Assert-Code -Json $commentDetail -Expected 200 -Name 'team project task comment detail'
    Assert-PathEquals -Json $commentDetail -Path 'data.contentText' -Expected "$prefix edited comment" -Name 'team project task comment detail'

    $badTaskEdit = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/edit" -Token $token -Data @{
        id = $taskId
        status = 'INVALID'
    }
    Assert-Code -Json $badTaskEdit -Expected 400 -Name 'team project task edit invalid status'

    $taskEdit = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/edit" -Token $token -Data @{
        id = $taskId
        teamProjectId = $projectId
        teamProjectTaskCategoryId = $categoryBId
        title = "$prefix task edited"
        status = 'COMPLETE'
        progress = 75
        contentText = "$prefix content edited"
        sortCode = 8
        extJson = '{"tag":"edited"}'
    }
    Assert-Code -Json $taskEdit -Expected 200 -Name 'team project task edit'

    $taskDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttask/detail?id=$(Enc $taskId)" -Token $token
    Assert-Code -Json $taskDetail -Expected 200 -Name 'team project task detail'
    Assert-PathEquals -Json $taskDetail -Path 'data.teamProjectTaskCategoryId' -Expected $categoryBId -Name 'team project task detail'
    Assert-PathEquals -Json $taskDetail -Path 'data.status' -Expected 'COMPLETE' -Name 'team project task detail'
    Assert-PathEquals -Json $taskDetail -Path 'data.progress' -Expected '75' -Name 'team project task detail'

    $commentDelete = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcomment/delete" -Token $token -Data @(
        @{ id = $commentId }
    )
    Assert-Code -Json $commentDelete -Expected 200 -Name 'team project task comment delete'

    $commentAfterDelete = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcomment/detail?id=$(Enc $commentId)" -Token $token
    Assert-Code -Json $commentAfterDelete -Expected 404 -Name 'team project task comment detail after delete'

    $taskDelete = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttask/delete" -Token $token -Data @(
        @{ id = $taskId }
    )
    Assert-Code -Json $taskDelete -Expected 200 -Name 'team project task delete'

    $taskAfterDelete = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttask/detail?id=$(Enc $taskId)" -Token $token
    Assert-Code -Json $taskAfterDelete -Expected 404 -Name 'team project task detail after delete'

    $categoryDelete = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamprojecttaskcategory/delete" -Token $token -Data @(
        @{ id = $categoryAId },
        @{ id = $categoryBId }
    )
    Assert-Code -Json $categoryDelete -Expected 200 -Name 'team project task category delete'
    Assert-PathEquals -Json $categoryDelete -Path 'data.count' -Expected '2' -Name 'team project task category delete'

    $projectDelete = Invoke-RawPostJson -Url "$baseUrl/biz/bizteamproject/delete" -Token $token -Data @(
        @{ id = $projectId }
    )
    Assert-Code -Json $projectDelete -Expected 200 -Name 'team project delete'

    $safeProjectId = $projectId.Replace("'", "\'")
    $safeCategoryAId = $categoryAId.Replace("'", "\'")
    $safeCategoryBId = $categoryBId.Replace("'", "\'")
    $safeTaskId = $taskId.Replace("'", "\'")
    $safeCommentId = $commentId.Replace("'", "\'")
    $verifyCode = @"
require getcwd() . '/vendor/autoload.php';
(new think\App(getcwd()))->initialize();
`$task = think\facade\Db::name('biz_team_project_task')->where('ID', '$safeTaskId')->find();
`$comment = think\facade\Db::name('biz_team_project_task_comment')->where('ID', '$safeCommentId')->find();
`$categories = think\facade\Db::name('biz_team_project_task_category')->whereIn('ID', ['$safeCategoryAId', '$safeCategoryBId'])->column('DELETE_FLAG', 'ID');
`$projectFlag = (string)think\facade\Db::name('biz_team_project')->where('ID', '$safeProjectId')->value('DELETE_FLAG');
`$memberFlags = think\facade\Db::name('biz_team_project_user')->where('TEAM_PROJECT_ID', '$safeProjectId')->column('DELETE_FLAG');
`$taskUserTotal = think\facade\Db::name('biz_team_project_task_user')->where('TEAM_PROJECT_TASK_ID', '$safeTaskId')->count();
`$taskUserActive = think\facade\Db::name('biz_team_project_task_user')->where('TEAM_PROJECT_TASK_ID', '$safeTaskId')->where(function (`$query) { `$query->whereNull('DELETE_FLAG')->whereOr('DELETE_FLAG', '=', 'NOT_DELETE'); })->count();
echo json_encode([
    'taskDeleteFlag' => (string)(`$task['DELETE_FLAG'] ?? ''),
    'taskStatus' => (string)(`$task['STATUS'] ?? ''),
    'taskCategoryId' => (string)(`$task['TEAM_PROJECT_TASK_CATEGORY_ID'] ?? ''),
    'taskProgress' => (int)(`$task['PROGRESS'] ?? -1),
    'taskVersion' => (int)(`$task['VERSION'] ?? -1),
    'commentDeleteFlag' => (string)(`$comment['DELETE_FLAG'] ?? ''),
    'commentText' => (string)(`$comment['CONTENT_TEXT'] ?? ''),
    'commentExtJson' => (string)(`$comment['EXT_JSON'] ?? ''),
    'categoryAFlag' => (string)(`$categories['$safeCategoryAId'] ?? ''),
    'categoryBFlag' => (string)(`$categories['$safeCategoryBId'] ?? ''),
    'projectFlag' => `$projectFlag,
    'memberFlags' => array_values(`$memberFlags),
    'taskUserTotal' => (int)`$taskUserTotal,
    'taskUserActive' => (int)`$taskUserActive,
], JSON_UNESCAPED_SLASHES);
"@
    $verify = Invoke-PhpJson -Code $verifyCode
    if ([string]$verify.taskDeleteFlag -ne 'DELETED' -or [string]$verify.taskStatus -ne 'COMPLETE' -or [string]$verify.taskCategoryId -ne $categoryBId -or [int]$verify.taskProgress -ne 75 -or [int]$verify.taskVersion -lt 2) {
        throw "task database verification failed: $($verify | ConvertTo-Json -Compress)"
    }
    if ([string]$verify.commentDeleteFlag -ne 'DELETED' -or [string]$verify.commentText -ne "$prefix edited comment" -or ([string]$verify.commentExtJson) -notlike '*file-b.txt*') {
        throw "task comment database verification failed: $($verify | ConvertTo-Json -Compress)"
    }
    if ([string]$verify.categoryAFlag -ne 'DELETED' -or [string]$verify.categoryBFlag -ne 'DELETED' -or [string]$verify.projectFlag -ne 'DELETED') {
        throw "category/project database verification failed: $($verify | ConvertTo-Json -Compress)"
    }
    if ([int]$verify.taskUserTotal -lt 3 -or [int]$verify.taskUserActive -ne 0) {
        throw "task user database verification failed: $($verify | ConvertTo-Json -Compress)"
    }
    foreach ($flag in @($verify.memberFlags)) {
        if ([string]$flag -ne 'DELETED') {
            throw "team project member database verification failed: $($verify | ConvertTo-Json -Compress)"
        }
    }
} finally {
    Invoke-Php -Code $cleanupCode | Out-Null
}

Write-Host 'team project task write HTTP smoke passed'
