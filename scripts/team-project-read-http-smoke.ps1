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
        [Parameter(Mandatory = $true)][string]$Token
    )

    $raw = & curl.exe -sS -X GET $Url -H "Authorization: Bearer $Token"
    if ($LASTEXITCODE -ne 0) {
        throw "HTTP GET failed: $Url"
    }

    return [string]::Join('', [string[]]$raw)
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

function Assert-Ok {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    $code = Read-JsonPath -Json $Json -Path 'code'
    if ([int]$code -ne 200) {
        throw "$Name returned code=$code"
    }
}

function Assert-Paths {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name,
        [Parameter(Mandatory = $true)][string[]]$Paths
    )

    foreach ($path in $Paths) {
        [void](Read-JsonPath -Json $Json -Path $path)
    }
}

function Has-Path {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Path
    )

    return $null -ne (Read-JsonPath -Json $Json -Path $Path -Optional)
}

function Enc {
    param([Parameter(Mandatory = $true)][string]$Value)

    return [System.Uri]::EscapeDataString($Value)
}

function Assert-PagedShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data.records', 'data.total', 'data.current', 'data.size', 'data.pages')
}

function Assert-ListShape {
    param(
        [Parameter(Mandatory = $true)][string]$Json,
        [Parameter(Mandatory = $true)][string]$Name
    )

    Assert-Ok -Json $Json -Name $Name
    Assert-Paths -Json $Json -Name $Name -Paths @('data')
}

function Assert-ProjectRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.name",
        "$Prefix.description",
        "$Prefix.projectStatus",
        "$Prefix.user",
        "$Prefix.userId",
        "$Prefix.headName",
        "$Prefix.org",
        "$Prefix.orgName",
        "$Prefix.currentMemberId",
        "$Prefix.currentRoleType",
        "$Prefix.tenantId"
    )
}

function Assert-MemberRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.teamProjectId",
        "$Prefix.projectName",
        "$Prefix.userId",
        "$Prefix.headName",
        "$Prefix.avatar",
        "$Prefix.roleType",
        "$Prefix.roleName",
        "$Prefix.permissionCode",
        "$Prefix.tenantId"
    )
}

function Assert-CategoryRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.teamProjectId",
        "$Prefix.title",
        "$Prefix.extJson",
        "$Prefix.sortCode",
        "$Prefix.tenantId"
    )
}

function Assert-TaskRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.teamProjectId",
        "$Prefix.teamProjectName",
        "$Prefix.teamProjectTaskCategoryId",
        "$Prefix.categoryTitle",
        "$Prefix.status",
        "$Prefix.title",
        "$Prefix.progress",
        "$Prefix.contentText",
        "$Prefix.createUserName",
        "$Prefix.version",
        "$Prefix.tenantId"
    )
}

function Assert-TaskUserRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.userId",
        "$Prefix.headName",
        "$Prefix.avatar",
        "$Prefix.teamProjectId",
        "$Prefix.teamProjectTaskId",
        "$Prefix.roleType",
        "$Prefix.extJson",
        "$Prefix.tenantId"
    )
}

function Assert-ProjectCommentRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.teamProjectId",
        "$Prefix.status",
        "$Prefix.statusColor",
        "$Prefix.contentText",
        "$Prefix.extJson",
        "$Prefix.createUserName",
        "$Prefix.avatar",
        "$Prefix.bizTeamProjectCommentReplies",
        "$Prefix.tenantId"
    )
}

function Assert-ReplyRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.targetId",
        "$Prefix.contentText",
        "$Prefix.extJson",
        "$Prefix.createUserName",
        "$Prefix.avatar",
        "$Prefix.tenantId"
    )
}

function Assert-TaskCommentRow {
    param([string]$Json, [string]$Prefix, [string]$Name)

    Assert-Paths -Json $Json -Name $Name -Paths @(
        "$Prefix.id",
        "$Prefix.teamProjectTaskId",
        "$Prefix.teamProjectId",
        "$Prefix.contentText",
        "$Prefix.category",
        "$Prefix.extJson",
        "$Prefix.createUserName",
        "$Prefix.avatar",
        "$Prefix.tenantId"
    )
}

$envMap = Get-EnvMap -Path $EnvPath
$account = Get-EnvValue -EnvMap $envMap -Key 'LOCAL_SUPER_ADMIN_ACCOUNT'
if ($account -eq '') {
    throw 'LOCAL_SUPER_ADMIN_ACCOUNT is required in .env'
}

$safeAccount = $account.Replace("'", "\'")
$tokenCode = @"
require getcwd() . '/vendor/autoload.php';
`$app = (new think\App(getcwd()))->initialize();
`$user = think\facade\Db::name('sys_user')->where('ACCOUNT', '$safeAccount')->find();
if (!`$user) { throw new RuntimeException('local smoke account not found'); }
`$auth = (new app\service\auth\RbacService())->buildForUser(`$user);
`$auth['device'] = 'CODEX_TEAM_PROJECT_READ_HTTP_SMOKE';
echo (new app\service\auth\TokenService())->create(`$user, `$auth);
"@

$token = & php -r $tokenCode
if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($token)) {
    throw 'failed to create local smoke auth token'
}

$baseUrl = $BackendBaseUrl.TrimEnd('/')

$projectPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamproject/page?current=1&size=1" -Token $token
Assert-PagedShape -Json $projectPage -Name 'biz team project page'
if (Has-Path -Json $projectPage -Path 'data.records.0') {
    Assert-ProjectRow -Json $projectPage -Prefix 'data.records.0' -Name 'biz team project page first row'
}

$projectId = [string](Read-JsonPath -Json $projectPage -Path 'data.records.0.id' -Optional)
$projectQuery = 'current=1&size=1'
if ($projectId.Trim() -ne '') {
    $encodedProjectId = Enc $projectId.Trim()
    $projectQuery = "teamProjectId=$encodedProjectId&current=1&size=1"
    $projectDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamproject/detail?id=$encodedProjectId" -Token $token
    Assert-Ok -Json $projectDetail -Name 'biz team project detail'
    Assert-Paths -Json $projectDetail -Name 'biz team project detail wrapper' -Paths @('data.project', 'data.user')
    Assert-ProjectRow -Json $projectDetail -Prefix 'data.project' -Name 'biz team project detail project'
    Assert-MemberRow -Json $projectDetail -Prefix 'data.user' -Name 'biz team project detail current user'
}

$memberPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectuser/page?$projectQuery" -Token $token
Assert-PagedShape -Json $memberPage -Name 'biz team project user page'
if (Has-Path -Json $memberPage -Path 'data.records.0') {
    Assert-MemberRow -Json $memberPage -Prefix 'data.records.0' -Name 'biz team project user page first row'
}

$memberList = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectuser/list?$projectQuery" -Token $token
Assert-ListShape -Json $memberList -Name 'biz team project user list'
if (Has-Path -Json $memberList -Path 'data.0') {
    Assert-MemberRow -Json $memberList -Prefix 'data.0' -Name 'biz team project user list first row'
}

$memberFirstId = [string](Read-JsonPath -Json $memberPage -Path 'data.records.0.id' -Optional)
if ($memberFirstId.Trim() -ne '') {
    $encodedId = Enc $memberFirstId.Trim()
    $memberDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectuser/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $memberDetail -Name 'biz team project user detail'
    Assert-MemberRow -Json $memberDetail -Prefix 'data' -Name 'biz team project user detail'
}

$categoryPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcategory/page?$projectQuery" -Token $token
Assert-PagedShape -Json $categoryPage -Name 'biz team project task category page'
if (Has-Path -Json $categoryPage -Path 'data.records.0') {
    Assert-CategoryRow -Json $categoryPage -Prefix 'data.records.0' -Name 'biz team project task category page first row'
}

$categoryList = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcategory/list?$projectQuery" -Token $token
Assert-ListShape -Json $categoryList -Name 'biz team project task category list'
if (Has-Path -Json $categoryList -Path 'data.0') {
    Assert-CategoryRow -Json $categoryList -Prefix 'data.0' -Name 'biz team project task category list first row'
}

$categoryFirstId = [string](Read-JsonPath -Json $categoryPage -Path 'data.records.0.id' -Optional)
if ($categoryFirstId.Trim() -ne '') {
    $encodedId = Enc $categoryFirstId.Trim()
    $categoryDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcategory/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $categoryDetail -Name 'biz team project task category detail'
    Assert-CategoryRow -Json $categoryDetail -Prefix 'data' -Name 'biz team project task category detail'
}

$taskPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttask/page?$projectQuery" -Token $token
Assert-PagedShape -Json $taskPage -Name 'biz team project task page'
if (Has-Path -Json $taskPage -Path 'data.records.0') {
    Assert-TaskRow -Json $taskPage -Prefix 'data.records.0' -Name 'biz team project task page first row'
}

$taskList = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttask/list?$projectQuery" -Token $token
Assert-ListShape -Json $taskList -Name 'biz team project task list'
if (Has-Path -Json $taskList -Path 'data.0') {
    Assert-TaskRow -Json $taskList -Prefix 'data.0' -Name 'biz team project task list first row'
}

$taskFirstId = [string](Read-JsonPath -Json $taskPage -Path 'data.records.0.id' -Optional)
if ($taskFirstId.Trim() -ne '') {
    $encodedId = Enc $taskFirstId.Trim()
    $taskDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttask/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $taskDetail -Name 'biz team project task detail'
    Assert-TaskRow -Json $taskDetail -Prefix 'data' -Name 'biz team project task detail'
    Assert-Paths -Json $taskDetail -Name 'biz team project task detail users' -Paths @('data.users')
}

$taskUserPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskuser/page?$projectQuery" -Token $token
Assert-PagedShape -Json $taskUserPage -Name 'biz team project task user page'
if (Has-Path -Json $taskUserPage -Path 'data.records.0') {
    Assert-TaskUserRow -Json $taskUserPage -Prefix 'data.records.0' -Name 'biz team project task user page first row'
}

$taskUserFirstId = [string](Read-JsonPath -Json $taskUserPage -Path 'data.records.0.id' -Optional)
if ($taskUserFirstId.Trim() -ne '') {
    $encodedId = Enc $taskUserFirstId.Trim()
    $taskUserDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskuser/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $taskUserDetail -Name 'biz team project task user detail'
    Assert-TaskUserRow -Json $taskUserDetail -Prefix 'data' -Name 'biz team project task user detail'
}

$projectCommentPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectcomment/page?$projectQuery" -Token $token
Assert-PagedShape -Json $projectCommentPage -Name 'biz team project comment page'
if (Has-Path -Json $projectCommentPage -Path 'data.records.0') {
    Assert-ProjectCommentRow -Json $projectCommentPage -Prefix 'data.records.0' -Name 'biz team project comment page first row'
}

$projectCommentList = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectcomment/list?$projectQuery" -Token $token
Assert-ListShape -Json $projectCommentList -Name 'biz team project comment list'
if (Has-Path -Json $projectCommentList -Path 'data.0') {
    Assert-ProjectCommentRow -Json $projectCommentList -Prefix 'data.0' -Name 'biz team project comment list first row'
}

$projectCommentFirstId = [string](Read-JsonPath -Json $projectCommentPage -Path 'data.records.0.id' -Optional)
if ($projectCommentFirstId.Trim() -ne '') {
    $encodedId = Enc $projectCommentFirstId.Trim()
    $projectCommentDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectcomment/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $projectCommentDetail -Name 'biz team project comment detail'
    Assert-ProjectCommentRow -Json $projectCommentDetail -Prefix 'data' -Name 'biz team project comment detail'
}

$replyPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectcommentreply/page?$projectQuery" -Token $token
Assert-PagedShape -Json $replyPage -Name 'biz team project comment reply page'
if (Has-Path -Json $replyPage -Path 'data.records.0') {
    Assert-ReplyRow -Json $replyPage -Prefix 'data.records.0' -Name 'biz team project comment reply page first row'
}

$replyFirstId = [string](Read-JsonPath -Json $replyPage -Path 'data.records.0.id' -Optional)
if ($replyFirstId.Trim() -ne '') {
    $encodedId = Enc $replyFirstId.Trim()
    $replyDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojectcommentreply/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $replyDetail -Name 'biz team project comment reply detail'
    Assert-ReplyRow -Json $replyDetail -Prefix 'data' -Name 'biz team project comment reply detail'
}

$taskCommentPage = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcomment/page?$projectQuery" -Token $token
Assert-PagedShape -Json $taskCommentPage -Name 'biz team project task comment page'
if (Has-Path -Json $taskCommentPage -Path 'data.records.0') {
    Assert-TaskCommentRow -Json $taskCommentPage -Prefix 'data.records.0' -Name 'biz team project task comment page first row'
}

$taskCommentList = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcomment/list?$projectQuery" -Token $token
Assert-ListShape -Json $taskCommentList -Name 'biz team project task comment list'
if (Has-Path -Json $taskCommentList -Path 'data.0') {
    Assert-TaskCommentRow -Json $taskCommentList -Prefix 'data.0' -Name 'biz team project task comment list first row'
}

$taskCommentFirstId = [string](Read-JsonPath -Json $taskCommentPage -Path 'data.records.0.id' -Optional)
if ($taskCommentFirstId.Trim() -ne '') {
    $encodedId = Enc $taskCommentFirstId.Trim()
    $taskCommentDetail = Invoke-RawGet -Url "$baseUrl/biz/bizteamprojecttaskcomment/detail?id=$encodedId" -Token $token
    Assert-Ok -Json $taskCommentDetail -Name 'biz team project task comment detail'
    Assert-TaskCommentRow -Json $taskCommentDetail -Prefix 'data' -Name 'biz team project task comment detail'
}

Write-Host 'team project read HTTP smoke passed'
