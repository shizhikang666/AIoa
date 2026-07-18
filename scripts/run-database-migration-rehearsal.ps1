[CmdletBinding()]
param(
    [string]$JavaConnectionFile = '',

    [Parameter(Mandatory = $true)]
    [string]$PhpEnvFile,

    [switch]$SourceFromPhpEnv,
    [switch]$AllowRemoteWorkflowConverter,

    [string]$SourceDatabase = 'oa2026',
    [string]$TemplateDatabase = '',
    [string]$TargetDatabase = 'oa2026_rehearsal_20260717_migrated',
    [string]$QuarantineDatabase = 'oa2026_quarantine_20260717',
    [string]$KnownOrphans = '',
    [string]$ManifestDirectory = '',
    [switch]$Apply,
    [string]$ConfirmToken = '',
    [switch]$SourceFrozen,

    [string]$PhpBinary = 'E:\project\socket\AI\testPhp\files\tools\php\php.exe',
    [string]$MysqlBinary = 'E:\project\socket\AI\testPhp\files\tools\mysql\bin\mysql.exe',
    [string]$MysqldumpBinary = 'E:\project\socket\AI\testPhp\files\tools\mysql\bin\mysqldump.exe',
    [string]$WorkflowConverter = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeBackupRoot = Join-Path $projectRoot 'runtime\backup'
$migrationScript = Join-Path $PSScriptRoot 'migrate-legacy-database.php'
if ($WorkflowConverter -eq '') {
    $WorkflowConverter = Join-Path $PSScriptRoot 'migrate-java-workflow-variables.php'
}
if ($ManifestDirectory -eq '') {
    $ManifestDirectory = Join-Path $projectRoot ('runtime\backup\database-migration-live-dry-run-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
}

$requiredFiles = @(
    $PhpEnvFile,
    $PhpBinary,
    $MysqlBinary,
    $MysqldumpBinary,
    $migrationScript,
    $WorkflowConverter
)
if (-not $SourceFromPhpEnv) {
    $requiredFiles += $JavaConnectionFile
}
foreach ($requiredFile in $requiredFiles) {
    if (-not (Test-Path -LiteralPath $requiredFile -PathType Leaf)) {
        throw "Required file does not exist: $requiredFile"
    }
}

function Read-JavaConnection([string]$path) {
    $values = @()
    foreach ($line in [IO.File]::ReadAllLines($path, [Text.Encoding]::UTF8)) {
        $parts = $line -split [char]0xFF1A, 2
        if ($parts.Count -eq 2) {
            $values += $parts[1].Trim()
        }
    }
    if ($values.Count -lt 4 -or $values[1] -notmatch '^\d+$') {
        throw 'Unable to parse the Java database connection file'
    }

    return @{
        Host = $values[0]
        Port = $values[1]
        User = $values[2]
        Password = $values[3]
    }
}

function Read-PhpEnvironment([string]$path) {
    $values = @{}
    foreach ($line in [IO.File]::ReadAllLines($path, [Text.Encoding]::UTF8)) {
        if ($line -match '^\s*([^#=]+?)\s*=\s*(.*)\s*$') {
            $values[$matches[1].Trim()] = $matches[2].Trim().Trim('"').Trim("'")
        }
    }
    foreach ($name in @('DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS')) {
        if (-not $values.ContainsKey($name)) {
            throw "PHP environment is missing $name"
        }
    }

    return $values
}

function ConvertTo-ClientOption([string]$value) {
    if ($value -match "[\r\n]") {
        throw 'MySQL client option values may not contain line breaks'
    }
    return '"' + $value.Replace('\', '\\').Replace('"', '\"') + '"'
}

function Remove-CredentialFile([string]$path) {
    if ($path -eq '' -or -not (Test-Path -LiteralPath $path)) {
        return
    }
    try {
        [IO.File]::WriteAllText($path, '', [Text.UTF8Encoding]::new($false))
        Remove-Item -LiteralPath $path -Force -ErrorAction Stop
    }
    finally {
        if (Test-Path -LiteralPath $path) {
            throw "Temporary credential file cleanup failed: $path"
        }
    }
}

function Set-PrivateDirectoryAcl([string]$path) {
    New-Item -ItemType Directory -Path $path -Force | Out-Null
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    $rule = New-Object Security.AccessControl.FileSystemAccessRule(
        $identity,
        [Security.AccessControl.FileSystemRights]::FullControl,
        [Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit',
        [Security.AccessControl.PropagationFlags]::None,
        [Security.AccessControl.AccessControlType]::Allow
    )
    $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $path -AclObject $acl -ErrorAction Stop
    $verified = Get-Acl -LiteralPath $path -ErrorAction Stop
    $rules = @($verified.Access)
    if (-not $verified.AreAccessRulesProtected -or $rules.Count -ne 1) {
        throw 'Private migration directory ACL verification failed'
    }
    $actual = $rules[0]
    if ($actual.IdentityReference.Value -ne $identity `
        -or $actual.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow `
        -or (($actual.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -ne [Security.AccessControl.FileSystemRights]::FullControl) `
        -or (($actual.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ContainerInherit) -eq 0) `
        -or (($actual.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ObjectInherit) -eq 0)) {
        throw 'Private migration directory ACL does not match the current Windows account'
    }
}

function Resolve-NoReparsePath([string]$path, [bool]$mustExist) {
    $full = [IO.Path]::GetFullPath($path)
    if ($mustExist -and -not (Test-Path -LiteralPath $full)) {
        throw "Required private path does not exist: $full"
    }
    $cursor = $full
    while (-not (Test-Path -LiteralPath $cursor)) {
        $parent = Split-Path -Parent $cursor
        if ($parent -eq '' -or $parent -eq $cursor) {
            throw "Unable to resolve a private path ancestor: $full"
        }
        $cursor = $parent
    }
    while ($cursor -ne '') {
        $item = Get-Item -LiteralPath $cursor -Force
        if (($item.Attributes -band [IO.FileAttributes]::ReparsePoint) -eq [IO.FileAttributes]::ReparsePoint) {
            throw "Private path contains a filesystem reparse point: $cursor"
        }
        $parent = Split-Path -Parent $cursor
        if ($parent -eq '' -or $parent -eq $cursor) {
            break
        }
        $cursor = $parent
    }
    return $full
}

function Assert-PrivateRuntimePath([string]$path, [bool]$mustExist) {
    $root = (Resolve-NoReparsePath $runtimeBackupRoot $true).TrimEnd('\', '/')
    $full = (Resolve-NoReparsePath $path $mustExist).TrimEnd('\', '/')
    if ($full -eq $root) {
        throw 'Migration manifests must use a dedicated child directory, not the shared runtime/backup root'
    }
    if (-not $full.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Migration manifests must stay under the private runtime/backup directory'
    }
    return $full
}

function New-PrivateTempDirectory([string]$prefix) {
    if ($prefix -notmatch '^[A-Za-z0-9-]+$') {
        throw 'Private temporary directory prefix is unsafe'
    }
    $path = Join-Path ([IO.Path]::GetFullPath($env:TEMP)) ($prefix + [guid]::NewGuid().ToString('N'))
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    $rule = New-Object Security.AccessControl.FileSystemAccessRule(
        $identity,
        [Security.AccessControl.FileSystemRights]::FullControl,
        [Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit',
        [Security.AccessControl.PropagationFlags]::None,
        [Security.AccessControl.AccessControlType]::Allow
    )
    $acl.AddAccessRule($rule)
    try {
        $directory = New-Object IO.DirectoryInfo($path)
        $directory.Create($acl)
        $actualAcl = Get-Acl -LiteralPath $path
        $actualRules = @($actualAcl.Access)
        if (-not $actualAcl.AreAccessRulesProtected -or $actualRules.Count -ne 1) {
            throw 'Private temporary directory ACL verification failed'
        }
        $actual = $actualRules[0]
        if ($actual.IdentityReference.Value -ne $identity `
            -or $actual.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow `
            -or (($actual.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -ne [Security.AccessControl.FileSystemRights]::FullControl) `
            -or (($actual.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ContainerInherit) -eq 0) `
            -or (($actual.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ObjectInherit) -eq 0)) {
            throw 'Private temporary directory ACL does not match the current Windows account'
        }
        return $path
    }
    catch {
        if (Test-Path -LiteralPath $path) {
            Remove-Item -LiteralPath $path -Force -ErrorAction SilentlyContinue
        }
        throw
    }
}

function New-ClientDefaults([hashtable]$connection) {
    if ($script:credentialDirectory -eq '' -or -not (Test-Path -LiteralPath $script:credentialDirectory -PathType Container)) {
        throw 'Private credential directory is unavailable'
    }
    $path = Join-Path $script:credentialDirectory ('client-' + [guid]::NewGuid().ToString('N') + '.cnf')
    $hostName = [string]$connection['Host']
    $hostPort = [string]$connection['Port']
    $userName = [string]$connection['User']
    $password = [string]$connection['Password']
    if ($hostName -eq '' -or $hostPort -notmatch '^\d+$' -or $userName -eq '') {
        throw 'Refusing to create an incomplete MySQL client option file'
    }
    try {
        $lines = @(
            '[client]',
            ('host=' + (ConvertTo-ClientOption $hostName)),
            ('port=' + (ConvertTo-ClientOption $hostPort)),
            ('user=' + (ConvertTo-ClientOption $userName)),
            ('password=' + (ConvertTo-ClientOption $password)),
            'default-character-set=utf8mb4'
        )
        [IO.File]::WriteAllLines($path, $lines, [Text.UTF8Encoding]::new($false))

        $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
        $acl = New-Object Security.AccessControl.FileSecurity
        $acl.SetAccessRuleProtection($true, $false)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule(
            $identity,
            [Security.AccessControl.FileSystemRights]::FullControl,
            [Security.AccessControl.AccessControlType]::Allow
        )
        $acl.AddAccessRule($rule)
        Set-Acl -LiteralPath $path -AclObject $acl -ErrorAction Stop
        $verified = Get-Acl -LiteralPath $path -ErrorAction Stop
        $rules = @($verified.Access)
        if (-not $verified.AreAccessRulesProtected -or $rules.Count -ne 1) {
            throw 'Temporary MySQL client option file ACL verification failed'
        }
        $actual = $rules[0]
        if ($actual.IdentityReference.Value -ne $identity `
            -or $actual.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow `
            -or (($actual.FileSystemRights -band [Security.AccessControl.FileSystemRights]::FullControl) -ne [Security.AccessControl.FileSystemRights]::FullControl)) {
            throw 'Temporary MySQL client option file ACL does not match the current Windows account'
        }

        return $path
    }
    catch {
        Remove-CredentialFile $path
        throw
    }
}

function Assert-ClientDefaults([string]$path, [string]$phpBinary, [string]$label) {
    $validation = '$p=parse_ini_file($argv[1],true,INI_SCANNER_RAW);'
    $validation += '$c=is_array($p)?($p[''client'']??$p):null;'
    $validation += 'exit(is_array($c)&&trim((string)($c[''user'']??''''))!==''''?0:2);'
    & $phpBinary -r $validation $path
    if ($LASTEXITCODE -ne 0) {
        throw "$label MySQL client option file failed the PHP parser self-check"
    }
}

$ManifestDirectory = Assert-PrivateRuntimePath $ManifestDirectory $false
$script:credentialDirectory = New-PrivateTempDirectory 'oa-migration-secure-'
$script:previousManifestMarker = [Environment]::GetEnvironmentVariable('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY', 'Process')
$sourceDefaults = $null
$targetDefaults = $null
try {
    $phpEnvironment = Read-PhpEnvironment $PhpEnvFile
    if ($TemplateDatabase -eq '') {
        $TemplateDatabase = [string]$phpEnvironment.DB_NAME
    }
    $target = @{
        Host = [string]$phpEnvironment.DB_HOST
        Port = [string]$phpEnvironment.DB_PORT
        User = [string]$phpEnvironment.DB_USER
        Password = [string]$phpEnvironment.DB_PASS
    }
    $source = if ($SourceFromPhpEnv) {
        $target
    }
    else {
        Read-JavaConnection $JavaConnectionFile
    }

    $sourceDefaults = New-ClientDefaults $source
    $targetDefaults = New-ClientDefaults $target
    Assert-ClientDefaults $sourceDefaults $PhpBinary 'Source'
    Assert-ClientDefaults $targetDefaults $PhpBinary 'Target'
    Set-PrivateDirectoryAcl $ManifestDirectory
    $ManifestDirectory = Assert-PrivateRuntimePath $ManifestDirectory $true
    [Environment]::SetEnvironmentVariable('OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY', $ManifestDirectory, 'Process')

    $arguments = @(
        $migrationScript,
        "--source-defaults=$sourceDefaults",
        "--source-db=$SourceDatabase",
        "--target-defaults=$targetDefaults",
        "--template-db=$TemplateDatabase",
        "--target-db=$TargetDatabase",
        "--quarantine-db=$QuarantineDatabase",
        "--allow-target=$TargetDatabase",
        "--workflow-converter=$WorkflowConverter",
        "--manifest-dir=$ManifestDirectory",
        "--mysql-bin=$MysqlBinary",
        "--mysqldump-bin=$MysqldumpBinary",
        "--php-bin=$PhpBinary"
    )
    if ($KnownOrphans -ne '') {
        $arguments += "--known-orphans=$KnownOrphans"
    }
    if ($AllowRemoteWorkflowConverter) {
        $arguments += '--allow-remote-workflow-converter'
    }
    if ($Apply) {
        if (-not $SourceFrozen -or $ConfirmToken -eq '') {
            throw 'Apply requires both -SourceFrozen and -ConfirmToken'
        }
        $arguments += '--apply'
        $arguments += "--confirm-token=$ConfirmToken"
        $arguments += '--source-freeze-token=JAVA_STOPPED_AND_SOURCE_FROZEN'
    }

    & $PhpBinary @arguments
    exit $LASTEXITCODE
}
finally {
    [Environment]::SetEnvironmentVariable(
        'OA_MIGRATION_PRIVATE_MANIFEST_DIRECTORY',
        $script:previousManifestMarker,
        'Process'
    )
    foreach ($temporaryFile in @($sourceDefaults, $targetDefaults)) {
        if ($temporaryFile) {
            Remove-CredentialFile $temporaryFile
        }
    }
    if ($script:credentialDirectory -ne '' -and (Test-Path -LiteralPath $script:credentialDirectory -PathType Container)) {
        if (@(Get-ChildItem -LiteralPath $script:credentialDirectory -Force).Count -ne 0) {
            throw 'Private credential directory is not empty after cleanup'
        }
        $tempRoot = [IO.Path]::GetFullPath($env:TEMP).TrimEnd('\', '/')
        $credentialPath = [IO.Path]::GetFullPath($script:credentialDirectory)
        if (-not $credentialPath.StartsWith($tempRoot + [IO.Path]::DirectorySeparatorChar + 'oa-migration-secure-', [StringComparison]::OrdinalIgnoreCase)) {
            throw 'Private credential directory cleanup guard rejected the path'
        }
        Remove-Item -LiteralPath $script:credentialDirectory -Force -ErrorAction Stop
    }
}
