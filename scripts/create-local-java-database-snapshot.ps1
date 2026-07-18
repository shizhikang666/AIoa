[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$JavaConnectionFile,

    [Parameter(Mandatory = $true)]
    [string]$PhpEnvFile,

    [string]$SourceDatabase = 'oa2026',
    [string]$SnapshotDatabase = 'oa2026_java_snapshot_20260717',
    [string]$DumpDirectory = '',
    [string]$ExistingDumpPath = '',
    [string]$ExistingDumpMetadataPath = '',
    [string]$ExpectedDumpSha256 = '',
    [switch]$VerifyOnly,
    [switch]$SourceFrozen,
    [string]$RowReferenceDatabase = '',

    [string]$PhpBinary = 'E:\project\socket\AI\testPhp\files\tools\php\php.exe',
    [string]$MysqlBinary = 'E:\project\socket\AI\testPhp\files\tools\mysql\bin\mysql.exe',
    [string]$MysqldumpBinary = 'E:\project\socket\AI\testPhp\files\tools\mysql\bin\mysqldump.exe'
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot
$runtimeBackupRoot = Join-Path $projectRoot 'runtime\backup'
if ($DumpDirectory -eq '') {
    $DumpDirectory = Join-Path $projectRoot 'runtime\backup\local-java-snapshot-20260717'
}
if ($SourceDatabase -notmatch '^[A-Za-z0-9_]+$') {
    throw 'Source database identifier is unsafe'
}
if ($SnapshotDatabase -notmatch '^oa2026_java_snapshot_[0-9]{8}(?:_[a-z0-9]+)?$') {
    throw 'Snapshot database must use the guarded oa2026_java_snapshot_YYYYMMDD name'
}
if (-not $SourceFrozen -and $RowReferenceDatabase -eq '') {
    throw 'Snapshot fidelity requires either a frozen source or an immutable local row-reference database'
}
if ($SourceFrozen -and $RowReferenceDatabase -ne '') {
    throw 'Choose either frozen-source row verification or a local row-reference database, not both'
}

$snapshotVerifier = Join-Path $PSScriptRoot 'verify-local-java-snapshot.php'
$dumpValidator = Join-Path $PSScriptRoot 'validate-java-snapshot-dump.php'
foreach ($requiredFile in @($JavaConnectionFile, $PhpEnvFile, $PhpBinary, $MysqlBinary, $MysqldumpBinary, $snapshotVerifier, $dumpValidator)) {
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
    foreach ($name in @('DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS')) {
        if (-not $values.ContainsKey($name)) {
            throw "PHP environment is missing $name"
        }
    }

    return $values
}

function Normalize-Host([string]$value) {
    $normalized = $value.Trim().ToLowerInvariant()
    if ($normalized.StartsWith('[') -and $normalized.EndsWith(']')) {
        $normalized = $normalized.Substring(1, $normalized.Length - 2)
    }
    return $normalized
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

function Set-PrivatePathAcl([string]$path) {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $item = Get-Item -LiteralPath $path
    if ($item.PSIsContainer) {
        $acl = New-Object Security.AccessControl.DirectorySecurity
        $rule = New-Object Security.AccessControl.FileSystemAccessRule(
            $identity,
            [Security.AccessControl.FileSystemRights]::FullControl,
            [Security.AccessControl.InheritanceFlags]'ContainerInherit, ObjectInherit',
            [Security.AccessControl.PropagationFlags]::None,
            [Security.AccessControl.AccessControlType]::Allow
        )
    }
    else {
        $acl = New-Object Security.AccessControl.FileSecurity
        $rule = New-Object Security.AccessControl.FileSystemAccessRule(
            $identity,
            [Security.AccessControl.FileSystemRights]::FullControl,
            [Security.AccessControl.AccessControlType]::Allow
        )
    }
    $acl.SetAccessRuleProtection($true, $false)
    $acl.AddAccessRule($rule)
    Set-Acl -LiteralPath $path -AclObject $acl -ErrorAction Stop
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
    if ($full -ne $root -and -not $full.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Snapshot artifacts must stay under the private runtime/backup directory'
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
        if (-not $actualAcl.AreAccessRulesProtected -or @($actualAcl.Access).Count -ne 1) {
            throw 'Private temporary directory ACL verification failed'
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

function New-ClientDefaults([hashtable]$connection, [string]$initCommand = '') {
    if ($script:credentialDirectory -eq '' -or -not (Test-Path -LiteralPath $script:credentialDirectory -PathType Container)) {
        throw 'Private credential directory is unavailable'
    }
    $path = Join-Path $script:credentialDirectory ('client-' + [guid]::NewGuid().ToString('N') + '.cnf')
    try {
        $lines = @(
            '[client]',
            ('host=' + (ConvertTo-ClientOption ([string]$connection.Host))),
            ('port=' + (ConvertTo-ClientOption ([string]$connection.Port))),
            ('user=' + (ConvertTo-ClientOption ([string]$connection.User))),
            ('password=' + (ConvertTo-ClientOption ([string]$connection.Password))),
            'default-character-set=utf8mb4'
        )
        if ($initCommand -ne '') {
            if ($initCommand -notmatch '^SET SESSION default_collation_for_utf8mb4=[A-Za-z0-9_]+$') {
                throw 'Snapshot import init command is outside the guarded collation assignment'
            }
            $lines += ('init-command=' + (ConvertTo-ClientOption $initCommand))
        }
        [IO.File]::WriteAllLines($path, $lines, [Text.UTF8Encoding]::new($false))
        Set-PrivatePathAcl $path

        return $path
    }
    catch {
        Remove-CredentialFile $path
        throw
    }
}

function Invoke-MysqlQuery([string]$defaultsFile, [string]$sql) {
    $output = & $MysqlBinary "--defaults-extra-file=$defaultsFile" --batch --skip-column-names "--execute=$sql" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ('MySQL query failed: ' + (($output | Out-String).Trim()))
    }
    return @($output)
}

$DumpDirectory = Assert-PrivateRuntimePath $DumpDirectory $false
$script:credentialDirectory = New-PrivateTempDirectory 'oa-snapshot-secure-'
$sourceDefaults = $null
$targetDefaults = $null
$importDefaults = $null
try {
    $source = Read-JavaConnection $JavaConnectionFile
    $phpEnvironment = Read-PhpEnvironment $PhpEnvFile
    $target = @{
        Host = [string]$phpEnvironment.DB_HOST
        Port = [string]$phpEnvironment.DB_PORT
        User = [string]$phpEnvironment.DB_USER
        Password = [string]$phpEnvironment.DB_PASS
    }
    if ((Normalize-Host $target.Host) -notin @('127.0.0.1', 'localhost', '::1')) {
        throw 'Snapshot import refuses every non-loopback target MySQL host'
    }

    $sourceDefaults = New-ClientDefaults $source
    $targetDefaults = New-ClientDefaults $target
    $existingOutput = @(Invoke-MysqlQuery $targetDefaults "SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$SnapshotDatabase'")
    if ($existingOutput.Count -ne 1 -or [string]$existingOutput[0] -notmatch '^[01]$') {
        throw 'Local snapshot existence query returned an unexpected result'
    }
    $existing = [int]$existingOutput[0]
    if ($VerifyOnly -and $existing -ne 1) {
        throw "Verify-only mode requires the existing local snapshot database $SnapshotDatabase"
    }
    if (-not $VerifyOnly -and $existing -ne 0) {
        throw "Refusing to reuse existing local snapshot database $SnapshotDatabase (count=$existing)"
    }

    $sourceSchemaOutput = @(Invoke-MysqlQuery $sourceDefaults "SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='$SourceDatabase'")
    if ($sourceSchemaOutput.Count -ne 1) {
        throw 'Source database charset/collation query returned an unexpected result'
    }
    $sourceSchema = [string]$sourceSchemaOutput[0] -split ([char]9)
    if ($sourceSchema.Count -ne 2 -or $sourceSchema[0] -notmatch '^[A-Za-z0-9_]+$' -or $sourceSchema[1] -notmatch '^[A-Za-z0-9_]+$') {
        throw 'Source database charset/collation metadata is unavailable'
    }
    if ($sourceSchema[0] -ne 'utf8mb4' -or $sourceSchema[1] -ne 'utf8mb4_general_ci') {
        throw 'Source database charset/collation differs from the audited utf8mb4_general_ci baseline'
    }
    if (-not $VerifyOnly) {
        $importDefaults = New-ClientDefaults $target ("SET SESSION default_collation_for_utf8mb4=" + $sourceSchema[1])
    }
    $sourceStructureOutput = @(Invoke-MysqlQuery $sourceDefaults "SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE'), (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$SourceDatabase'), (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SourceDatabase' AND TABLE_TYPE='BASE TABLE' AND UPPER(COALESCE(ENGINE,'')) <> 'INNODB')")
    if ($sourceStructureOutput.Count -ne 1) {
        throw 'Source structure/engine query returned an unexpected result'
    }
    $sourceStructure = [string]$sourceStructureOutput[0] -split ([char]9)
    if ($sourceStructure.Count -ne 3 -or [int]$sourceStructure[0] -ne 121 -or [int]$sourceStructure[1] -ne 1836 -or [int]$sourceStructure[2] -ne 0) {
        throw 'Online snapshot requires the audited 121-table/1836-column all-InnoDB source'
    }

    $reusedExistingDump = $ExistingDumpPath -ne ''
    if ($VerifyOnly -and -not $reusedExistingDump) {
        throw 'Verify-only mode requires -ExistingDumpPath for immutable dump provenance'
    }
    if ($reusedExistingDump) {
        if ($ExistingDumpMetadataPath -eq '' -or $ExpectedDumpSha256 -notmatch '^[A-Fa-f0-9]{64}$') {
            throw 'Existing dump reuse requires metadata and an explicit reviewed SHA-256'
        }
        $dumpPath = Assert-PrivateRuntimePath $ExistingDumpPath $true
        $dumpMetadataPath = Assert-PrivateRuntimePath $ExistingDumpMetadataPath $true
        if (-not (Test-Path -LiteralPath $dumpPath -PathType Leaf) -or (Get-Item -LiteralPath $dumpPath).Length -eq 0) {
            throw 'Existing snapshot dump is missing or empty'
        }
        if (-not (Test-Path -LiteralPath $dumpMetadataPath -PathType Leaf)) {
            throw 'Existing snapshot dump metadata is missing'
        }
        $actualDumpSha256 = (Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash.ToLowerInvariant()
        $expectedDumpSha256Normalized = $ExpectedDumpSha256.ToLowerInvariant()
        if ($actualDumpSha256 -ne $expectedDumpSha256Normalized) {
            throw 'Existing snapshot dump SHA-256 differs from the reviewed value'
        }
        $dumpMetadata = Get-Content -LiteralPath $dumpMetadataPath -Raw | ConvertFrom-Json
        $metadataChecks = @(
            (([int]$dumpMetadata.version) -eq 1)
            (([string]$dumpMetadata.sourceMode) -eq 'single-transaction-read-only-dump')
            (([string]$dumpMetadata.sourceDatabase) -eq $SourceDatabase)
            (([int]$dumpMetadata.sourceTableCount) -eq 121)
            (([int]$dumpMetadata.sourceColumnCount) -eq 1836)
            (([string]$dumpMetadata.sourceCharset) -eq $sourceSchema[0])
            (([string]$dumpMetadata.sourceCollation) -eq $sourceSchema[1])
            (([string]$dumpMetadata.dumpFileName) -eq [IO.Path]::GetFileName($dumpPath))
            (([long]$dumpMetadata.dumpSize) -eq (Get-Item -LiteralPath $dumpPath).Length)
            (([string]$dumpMetadata.dumpSha256) -eq $actualDumpSha256)
        )
        if ($metadataChecks -contains $false) {
            throw 'Existing snapshot dump metadata differs from the reviewed source and file'
        }
        Set-PrivatePathAcl $dumpPath
        Set-PrivatePathAcl $dumpMetadataPath
    }
    else {
        New-Item -ItemType Directory -Path $DumpDirectory -Force | Out-Null
        $DumpDirectory = Assert-PrivateRuntimePath $DumpDirectory $true
        Set-PrivatePathAcl $DumpDirectory
        $dumpPath = Join-Path $DumpDirectory ($SourceDatabase + '-single-transaction.sql')
        $dumpMetadataPath = Join-Path $DumpDirectory ($SourceDatabase + '-single-transaction.metadata.json')
        if (Test-Path -LiteralPath $dumpPath) {
            throw "Refusing to overwrite existing snapshot dump $dumpPath"
        }
        if (Test-Path -LiteralPath $dumpMetadataPath) {
            throw "Refusing to overwrite existing snapshot metadata $dumpMetadataPath"
        }

        $dumpArguments = @(
            "--defaults-extra-file=$sourceDefaults",
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            '--hex-blob',
            '--column-statistics=0',
            '--set-gtid-purged=OFF',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            "--result-file=$dumpPath",
            $SourceDatabase
        )
        $dumpProcess = Start-Process -FilePath $MysqldumpBinary -ArgumentList $dumpArguments -Wait -PassThru -NoNewWindow
        if ($dumpProcess.ExitCode -ne 0 -or -not (Test-Path -LiteralPath $dumpPath) -or (Get-Item -LiteralPath $dumpPath).Length -eq 0) {
            throw 'Consistent old-Java snapshot dump failed'
        }
        Set-PrivatePathAcl $dumpPath
        $actualDumpSha256 = (Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash.ToLowerInvariant()
    }

    $dumpValidationOutput = & $PhpBinary $dumpValidator `
        "--path=$dumpPath" `
        "--sha256=$actualDumpSha256" `
        "--source-db=$SourceDatabase" 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ('Snapshot SQL validation failed: ' + (($dumpValidationOutput | Out-String).Trim()))
    }
    $dumpValidation = ($dumpValidationOutput | Out-String) | ConvertFrom-Json
    $validationChecks = @(
        ($dumpValidation.status -eq 'passed')
        (([int]$dumpValidation.createTableCount) -eq 121)
        (([int]$dumpValidation.dropTableCount) -eq 121)
        (([int]$dumpValidation.forbiddenStatements) -eq 0)
    )
    if ($validationChecks -contains $false) {
        throw 'Snapshot SQL validation returned an invalid result'
    }
    if (-not $reusedExistingDump) {
        $metadata = [ordered]@{
            version = 1
            sourceMode = 'single-transaction-read-only-dump'
            sourceDatabase = $SourceDatabase
            sourceTableCount = 121
            sourceColumnCount = 1836
            sourceCharset = [string]$sourceSchema[0]
            sourceCollation = [string]$sourceSchema[1]
            dumpFileName = [IO.Path]::GetFileName($dumpPath)
            dumpSize = (Get-Item -LiteralPath $dumpPath).Length
            dumpSha256 = $actualDumpSha256
            sqlValidation = $dumpValidation
            createdAt = (Get-Date).ToUniversalTime().ToString('o')
        } | ConvertTo-Json -Depth 8
        [IO.File]::WriteAllText($dumpMetadataPath, $metadata + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))
        Set-PrivatePathAcl $dumpMetadataPath
    }

    if (-not $VerifyOnly) {
        $quote = [char]96
        $createSql = "CREATE DATABASE $quote$SnapshotDatabase$quote CHARACTER SET $($sourceSchema[0]) COLLATE $($sourceSchema[1])"
        Invoke-MysqlQuery $targetDefaults $createSql | Out-Null
        $probeTable = '__oa_snapshot_collation_probe'
        Invoke-MysqlQuery $importDefaults "CREATE TABLE $quote$SnapshotDatabase$quote.$quote$probeTable$quote (ID int NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4" | Out-Null
        $probeCollationOutput = @(Invoke-MysqlQuery $targetDefaults "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SnapshotDatabase' AND TABLE_NAME='$probeTable'")
        if ($probeCollationOutput.Count -ne 1 -or [string]$probeCollationOutput[0] -ne $sourceSchema[1]) {
            throw 'Snapshot import session did not pin the audited utf8mb4 collation'
        }
        Invoke-MysqlQuery $targetDefaults "DROP TABLE $quote$SnapshotDatabase$quote.$quote$probeTable$quote" | Out-Null
        $importProcess = Start-Process -FilePath $MysqlBinary -ArgumentList @("--defaults-extra-file=$importDefaults", $SnapshotDatabase) -RedirectStandardInput $dumpPath -WindowStyle Hidden -Wait -PassThru
        if ($importProcess.ExitCode -ne 0) {
            throw "Snapshot import failed; isolated database $SnapshotDatabase was left in place for diagnosis"
        }
    }

    $countOutput = @(Invoke-MysqlQuery $targetDefaults "SELECT (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$SnapshotDatabase' AND TABLE_TYPE='BASE TABLE'), (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$SnapshotDatabase')")
    if ($countOutput.Count -ne 1) {
        throw 'Snapshot structure count query returned an unexpected result'
    }
    $counts = [string]$countOutput[0] -split ([char]9)
    if ($counts.Count -ne 2 -or [int]$counts[0] -ne 121 -or [int]$counts[1] -ne 1836) {
        throw 'Snapshot structure count differs from the audited 121-table/1836-column baseline'
    }

    if ($RowReferenceDatabase -ne '' -and $RowReferenceDatabase -notmatch '^oa2026_java_snapshot_[0-9]{8}(?:_[a-z0-9]+)?$') {
        throw 'Row-reference database must use the guarded local snapshot namespace'
    }
    $verificationArguments = @(
        $snapshotVerifier,
        "--source-defaults=$sourceDefaults",
        "--source-db=$SourceDatabase",
        "--snapshot-defaults=$targetDefaults",
        "--snapshot-db=$SnapshotDatabase"
    )
    if ($SourceFrozen) {
        $verificationArguments += '--source-frozen=1'
    }
    if ($RowReferenceDatabase -ne '') {
        $verificationArguments += "--row-reference-defaults=$targetDefaults"
        $verificationArguments += "--row-reference-db=$RowReferenceDatabase"
    }
    $verificationOutput = & $PhpBinary @verificationArguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw ('Snapshot fidelity verification failed: ' + (($verificationOutput | Out-String).Trim()))
    }
    $verification = ($verificationOutput | Out-String) | ConvertFrom-Json
    if ($verification.status -ne 'passed' -or -not $verification.schemaEquivalent) {
        throw 'Snapshot fidelity verification returned an invalid result'
    }
    if (($SourceFrozen -or $RowReferenceDatabase -ne '') -and -not $verification.perTableRowCountsEquivalent) {
        throw 'Snapshot fidelity verification returned an invalid result'
    }

    [ordered]@{
        status = 'passed'
        sourceMode = if ($VerifyOnly) { 'verify-existing-local-snapshot' } elseif ($reusedExistingDump) { 'verified-existing-single-transaction-dump' } else { 'single-transaction-read-only-dump' }
        finalCutoverSnapshot = $false
        sourceDatabase = $SourceDatabase
        localSnapshotDatabase = $SnapshotDatabase
        localTargetLoopback = $true
        tableCount = [int]$counts[0]
        columnCount = [int]$counts[1]
        schemaEquivalent = $true
        importDefaultCollationPinned = if ($VerifyOnly) { $null } else { $true }
        perTableRowCountsVerified = [bool]$verification.perTableRowCountsVerified
        perTableRowCountsEquivalent = $verification.perTableRowCountsEquivalent
        dumpSize = (Get-Item -LiteralPath $dumpPath).Length
        dumpSha256 = (Get-FileHash -LiteralPath $dumpPath -Algorithm SHA256).Hash.ToLowerInvariant()
        dumpPath = $dumpPath
        dumpMetadataPath = $dumpMetadataPath
        dumpSqlValidated = $true
        productionWritesPerformed = $false
        localWritesPerformed = -not $VerifyOnly
    } | ConvertTo-Json
}
finally {
    foreach ($temporaryFile in @($sourceDefaults, $targetDefaults, $importDefaults)) {
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
        if (-not $credentialPath.StartsWith($tempRoot + [IO.Path]::DirectorySeparatorChar + 'oa-snapshot-secure-', [StringComparison]::OrdinalIgnoreCase)) {
            throw 'Private credential directory cleanup guard rejected the path'
        }
        Remove-Item -LiteralPath $script:credentialDirectory -Force -ErrorAction Stop
    }
}
