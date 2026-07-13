param(
    [string]$FrontendBaseUrl = 'https://oa.fucity.cn',
    [string]$ApiPrefix = '/backend',
    [string]$TenantId = '2018244380532912130',
    [string]$Account = 'superAdminTwo',
    [string]$Password = '123456',
    [string]$Token = '',
    [string]$OrgId = '',
    [switch]$IncludeMobileGrant
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$baseUrl = $FrontendBaseUrl.TrimEnd('/') + '/' + $ApiPrefix.Trim('/')
$marker = 'CODEX_ROLE_SMOKE_' + (Get-Date -Format 'yyyyMMddHHmmss')
$createdRoleId = ''
$results = New-Object System.Collections.Generic.List[object]

Add-Type -AssemblyName System.Web.Extensions
$jsonSerializer = [System.Web.Script.Serialization.JavaScriptSerializer]::new()
$jsonSerializer.MaxJsonLength = [int]::MaxValue

function Convert-JsonValue {
    param([object]$Value)

    if ($null -eq $Value) {
        return $null
    }

    if ($Value -is [System.Collections.IDictionary]) {
        $normalized = [ordered]@{}
        foreach ($keyObject in $Value.Keys) {
            $key = [string]$keyObject
            $existingKey = $null
            foreach ($candidate in @($normalized.Keys)) {
                if ($candidate.Equals($key, [System.StringComparison]::OrdinalIgnoreCase)) {
                    $existingKey = $candidate
                    break
                }
            }

            $converted = Convert-JsonValue -Value $Value[$keyObject]
            if ($null -ne $existingKey) {
                $currentStartsLower = $key.Length -gt 0 -and [char]::IsLower($key[0])
                $existingStartsLower = $existingKey.Length -gt 0 -and [char]::IsLower($existingKey[0])
                if ($currentStartsLower -or -not $existingStartsLower) {
                    $normalized.Remove($existingKey)
                    $normalized[$key] = $converted
                }
            } else {
                $normalized[$key] = $converted
            }
        }

        return [pscustomobject]$normalized
    }

    if ($Value -is [System.Collections.IEnumerable] -and -not ($Value -is [string])) {
        $items = @()
        foreach ($item in $Value) {
            $items += ,(Convert-JsonValue -Value $item)
        }

        return ,$items
    }

    return $Value
}

function Convert-JsonResponse {
    param([Parameter(Mandatory = $true)][string]$Text)

    $clean = $Text.TrimStart([char]0xFEFF).Trim()
    if ($clean -eq '') {
        return [pscustomobject]@{ code = 'empty'; msg = 'empty response'; data = $null }
    }

    return Convert-JsonValue -Value $script:jsonSerializer.DeserializeObject($clean)
}

function Convert-ErrorResponse {
    param([Parameter(Mandatory = $true)]$ErrorRecord)

    $response = $ErrorRecord.Exception.Response
    if ($null -ne $response) {
        try {
            $stream = $response.GetResponseStream()
            if ($null -ne $stream) {
                $reader = [System.IO.StreamReader]::new($stream)
                try {
                    $text = $reader.ReadToEnd()
                } finally {
                    $reader.Dispose()
                }
                if (-not [string]::IsNullOrWhiteSpace($text)) {
                    try {
                        return Convert-JsonResponse -Text $text
                    } catch {
                    }
                }
            }
        } catch {
        }
    }

    return [pscustomobject]@{
        code = 'http-error'
        msg = $ErrorRecord.Exception.Message
        data = $null
    }
}

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
            $webResponse = Invoke-WebRequest -UseBasicParsing -Method Get -Uri $uri -Headers $allHeaders
        } else {
            $json = if ($null -eq $Body) { '{}' } else { ConvertTo-Json -InputObject $Body -Depth 40 -Compress }
            $webResponse = Invoke-WebRequest -UseBasicParsing -Method $Method -Uri $uri -Headers $allHeaders -ContentType 'application/json' -Body $json
        }
    } catch {
        if ($AllowFailure) {
            return Convert-ErrorResponse -ErrorRecord $_
        }
        throw
    }

    $response = Convert-JsonResponse -Text $webResponse.Content

    if (-not $AllowFailure -and [int]$response.code -ne 200) {
        throw "$Method $Path failed: code=$($response.code) msg=$($response.msg)"
    }

    return $response
}

function New-Session {
    if (-not [string]::IsNullOrWhiteSpace($script:Token)) {
        return @{
            Authorization = "Bearer $script:Token"
            tenantId = $script:TenantId
        }
    }

    $login = Invoke-OaApi -Method POST -Path '/auth/b/doLogin' -Body @{
        account = $script:Account
        password = $script:Password
        tenantId = $script:TenantId
        device = 'CODEX_ROLE_CRUD_GRANT_SMOKE'
    }
    if ([string]::IsNullOrWhiteSpace([string]$login.data)) {
        throw "login returned empty token for $script:Account"
    }

    return @{
        Authorization = "Bearer $($login.data)"
        tenantId = $script:TenantId
    }
}

function Add-Result {
    param(
        [string]$Step,
        [string]$Status = 'PASS',
        [string]$Detail = ''
    )

    $script:results.Add([pscustomobject]@{
        step = $Step
        status = $Status
        detail = $Detail
    }) | Out-Null
}

function Assert-True {
    param(
        [bool]$Condition,
        [string]$Message
    )

    if (-not $Condition) {
        throw $Message
    }
}

function Get-Prop {
    param(
        [object]$Object,
        [string[]]$Names
    )

    if ($null -eq $Object) {
        return $null
    }

    foreach ($name in $Names) {
        if ($Object.PSObject.Properties.Name -contains $name) {
            return $Object.$name
        }
    }

    return $null
}

function Get-Array {
    param([object]$Value)

    if ($null -eq $Value) {
        return @()
    }
    if ($Value -is [array]) {
        return @($Value)
    }

    return @($Value)
}

function Find-FirstOrgId {
    param([object]$Nodes)

    foreach ($node in (Get-Array $Nodes)) {
        $id = [string](Get-Prop -Object $node -Names @('id', 'ID', 'value'))
        if (-not [string]::IsNullOrWhiteSpace($id) -and $id -ne 'GLOBAL') {
            return $id
        }

        $children = Get-Prop -Object $node -Names @('children')
        $childId = Find-FirstOrgId -Nodes $children
        if (-not [string]::IsNullOrWhiteSpace($childId)) {
            return $childId
        }
    }

    return ''
}

function Find-FirstMenuGrant {
    param(
        [object]$Tree,
        [switch]$SkipSystemModule
    )

    foreach ($module in (Get-Array $Tree)) {
        $moduleCode = ([string](Get-Prop -Object $module -Names @('code', 'CODE'))).ToLowerInvariant()
        if ($SkipSystemModule -and $moduleCode -eq 'system') {
            continue
        }

        foreach ($menu in (Get-Array (Get-Prop -Object $module -Names @('menu')))) {
            $menuId = [string](Get-Prop -Object $menu -Names @('id', 'ID'))
            if ([string]::IsNullOrWhiteSpace($menuId)) {
                continue
            }

            $buttonIds = New-Object System.Collections.Generic.List[string]
            foreach ($button in (Get-Array (Get-Prop -Object $menu -Names @('button')))) {
                $buttonId = [string](Get-Prop -Object $button -Names @('id', 'ID'))
                if (-not [string]::IsNullOrWhiteSpace($buttonId)) {
                    $buttonIds.Add($buttonId) | Out-Null
                    break
                }
            }

            return [pscustomobject]@{
                menuId = $menuId
                buttonInfo = @($buttonIds)
            }
        }
    }

    return $null
}

function Get-Records {
    param([object]$Response)

    if ($null -eq $Response -or $null -eq $Response.data) {
        return @()
    }
    if ($Response.data.PSObject.Properties.Name -contains 'records') {
        return Get-Array $Response.data.records
    }

    return @()
}

function Assert-RoleRowShape {
    param(
        [object]$Row,
        [string]$ExpectedName
    )

    foreach ($key in @('id', 'name', 'category', 'sortCode', 'orgId')) {
        Assert-True -Condition ($Row.PSObject.Properties.Name -contains $key) -Message "role row missing frontend key: $key"
    }

    Assert-True -Condition ([string]$Row.id -ne '') -Message 'role row id is empty'
    Assert-True -Condition ([string]$Row.name -eq $ExpectedName) -Message "role row name mismatch: expected $ExpectedName, got $($Row.name)"
    Assert-True -Condition ([string]$Row.category -eq 'ORG') -Message "role row category mismatch: $($Row.category)"
}

function Find-RoleById {
    param(
        [object[]]$Records,
        [string]$RoleId
    )

    foreach ($record in $Records) {
        if ([string](Get-Prop -Object $record -Names @('id', 'ID')) -eq $RoleId) {
            return $record
        }
    }

    return $null
}

function Assert-GrantContains {
    param(
        [object]$OwnResponse,
        [string]$Key,
        [string]$Expected
    )

    $items = Get-Array $OwnResponse.data.grantInfoList
    foreach ($item in $items) {
        if ([string](Get-Prop -Object $item -Names @($Key)) -eq $Expected) {
            return
        }
    }

    throw "grantInfoList missing $Key=$Expected"
}

function Normalize-ApiUrl {
    param([object]$Value)

    $text = [string]$Value
    if ($text.Contains('[')) {
        return $text.Substring(0, $text.IndexOf('[')).Trim()
    }

    return $text.Trim()
}

$headers = $null

try {
    $headers = New-Session
    Add-Result -Step 'login'

    $targetOrgId = $OrgId
    if ([string]::IsNullOrWhiteSpace($targetOrgId)) {
        $orgTree = Invoke-OaApi -Method GET -Path '/sys/role/orgTreeSelector' -Headers $headers
        $targetOrgId = Find-FirstOrgId -Nodes $orgTree.data
    }
    Assert-True -Condition (-not [string]::IsNullOrWhiteSpace($targetOrgId)) -Message 'no target organization available for role smoke'
    Add-Result -Step 'select org' -Detail $targetOrgId

    $roleName = "$marker base"
    $roleAdd = Invoke-OaApi -Method POST -Path '/sys/role/add' -Headers $headers -Body @{
        name = $roleName
        category = 'ORG'
        orgId = $targetOrgId
        sortCode = 998
        extJson = @{ marker = $marker; smoke = 'roleCrudGrant' }
    }
    $createdRoleId = [string](Get-Prop -Object $roleAdd.data -Names @('id', 'ID'))
    Assert-True -Condition (-not [string]::IsNullOrWhiteSpace($createdRoleId)) -Message 'role add returned empty id'
    Assert-RoleRowShape -Row $roleAdd.data -ExpectedName $roleName
    Add-Result -Step 'add role' -Detail $createdRoleId

    $page = Invoke-OaApi -Method GET -Path ("/sys/role/page?current=1&size=10&searchKey={0}" -f [uri]::EscapeDataString($roleName)) -Headers $headers
    $addedRow = Find-RoleById -Records (Get-Records $page) -RoleId $createdRoleId
    Assert-True -Condition ($null -ne $addedRow) -Message 'created role not found in role page'
    Assert-RoleRowShape -Row $addedRow -ExpectedName $roleName
    Add-Result -Step 'page row frontend fields'

    $detail = Invoke-OaApi -Method GET -Path ("/sys/role/detail?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
    Assert-RoleRowShape -Row $detail.data -ExpectedName $roleName
    Add-Result -Step 'detail frontend fields'

    $editedName = "$marker edited"
    $edit = Invoke-OaApi -Method POST -Path '/sys/role/edit' -Headers $headers -Body @{
        id = $createdRoleId
        name = $editedName
        category = 'ORG'
        orgId = $targetOrgId
        sortCode = 999
        extJson = @{ marker = $marker; smoke = 'roleCrudGrantEdited' }
    }
    Assert-True -Condition ([string](Get-Prop -Object $edit.data -Names @('id', 'ID')) -eq $createdRoleId) -Message 'role edit returned wrong id'
    Assert-RoleRowShape -Row $edit.data -ExpectedName $editedName
    Add-Result -Step 'edit role'

    $ownResource = Invoke-OaApi -Method GET -Path ("/sys/role/ownResource?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
    Assert-True -Condition ([string]$ownResource.data.id -eq $createdRoleId) -Message 'ownResource returned wrong id'
    $resourceTree = Invoke-OaApi -Method GET -Path '/sys/role/resourceTreeSelector' -Headers $headers
    $resourceGrant = Find-FirstMenuGrant -Tree $resourceTree.data -SkipSystemModule
    if ($null -ne $resourceGrant) {
        Invoke-OaApi -Method POST -Path '/sys/role/grantResource' -Headers $headers -Body @{
            id = $createdRoleId
            grantInfoList = @($resourceGrant)
        } | Out-Null
        $ownResourceAfter = Invoke-OaApi -Method GET -Path ("/sys/role/ownResource?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
        Assert-GrantContains -OwnResponse $ownResourceAfter -Key 'menuId' -Expected $resourceGrant.menuId
        Add-Result -Step 'grant resource' -Detail $resourceGrant.menuId
    } else {
        Add-Result -Step 'grant resource' -Status 'SKIP' -Detail 'no non-system menu resource available'
    }

    $permissionTree = Invoke-OaApi -Method GET -Path '/sys/role/permissionTreeSelector' -Headers $headers
    $apiUrl = ''
    foreach ($candidate in (Get-Array $permissionTree.data)) {
        $apiUrl = Normalize-ApiUrl -Value $candidate
        if (-not [string]::IsNullOrWhiteSpace($apiUrl)) {
            break
        }
    }
    if ([string]::IsNullOrWhiteSpace($apiUrl)) {
        $apiUrl = '/sys/role/page'
    }

    $ownPermission = Invoke-OaApi -Method GET -Path ("/sys/role/ownPermission?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
    Assert-True -Condition ([string]$ownPermission.data.id -eq $createdRoleId) -Message 'ownPermission returned wrong id'
    Invoke-OaApi -Method POST -Path '/sys/role/grantPermission' -Headers $headers -Body @{
        id = $createdRoleId
        grantInfoList = @(
            @{
                apiUrl = $apiUrl
                scopeCategory = 'SCOPE_COMPANY_CHILD'
                scopeDefineOrgIdList = @()
            }
        )
    } | Out-Null
    $ownPermissionAfter = Invoke-OaApi -Method GET -Path ("/sys/role/ownPermission?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
    Assert-GrantContains -OwnResponse $ownPermissionAfter -Key 'apiUrl' -Expected $apiUrl
    Add-Result -Step 'grant permission' -Detail $apiUrl

    $userPage = Invoke-OaApi -Method GET -Path '/sys/role/userSelector?current=1&size=1' -Headers $headers
    $users = @(Get-Records $userPage)
    Assert-True -Condition ($users.Count -gt 0) -Message 'role userSelector returned no users'
    $userId = [string](Get-Prop -Object $users[0] -Names @('id', 'ID', 'value'))
    Assert-True -Condition (-not [string]::IsNullOrWhiteSpace($userId)) -Message 'role userSelector first user has empty id'
    $ownUser = Invoke-OaApi -Method GET -Path ("/sys/role/ownUser?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
    Assert-True -Condition ($null -ne $ownUser.data) -Message 'ownUser returned null data'
    Invoke-OaApi -Method POST -Path '/sys/role/grantUser' -Headers $headers -Body @{
        id = $createdRoleId
        grantInfoList = @($userId)
    } | Out-Null
    $ownUserAfter = Invoke-OaApi -Method GET -Path ("/sys/role/ownUser?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
    Assert-True -Condition ((Get-Array $ownUserAfter.data) -contains $userId) -Message "ownUser missing granted user $userId"
    Add-Result -Step 'grant user' -Detail $userId

    if ($IncludeMobileGrant) {
        $ownMobile = Invoke-OaApi -Method GET -Path ("/sys/role/ownMobileMenu?id={0}" -f [uri]::EscapeDataString($createdRoleId)) -Headers $headers
        Assert-True -Condition ([string]$ownMobile.data.id -eq $createdRoleId) -Message 'ownMobileMenu returned wrong id'
        $mobileTree = Invoke-OaApi -Method GET -Path '/sys/role/mobileMenuTreeSelector' -Headers $headers
        $mobileGrant = Find-FirstMenuGrant -Tree $mobileTree.data
        $mobileGrantList = if ($null -ne $mobileGrant) { @($mobileGrant) } else { @() }
        Invoke-OaApi -Method POST -Path '/sys/role/grantMobileMenu' -Headers $headers -Body @{
            id = $createdRoleId
            grantInfoList = $mobileGrantList
        } | Out-Null
        Add-Result -Step 'grant mobile resource' -Detail $(if ($null -ne $mobileGrant) { $mobileGrant.menuId } else { 'empty grant list' })
    }

    $delete = Invoke-OaApi -Method POST -Path '/sys/role/delete' -Headers $headers -Body @(@{ id = $createdRoleId })
    Assert-True -Condition ([int]$delete.data.count -ge 1) -Message 'role delete count was less than 1'
    Add-Result -Step 'delete cleanup' -Detail $createdRoleId
    $createdRoleId = ''

    [pscustomobject]@{
        ok = $true
        frontendBaseUrl = $FrontendBaseUrl
        apiPrefix = $ApiPrefix
        tenantId = $TenantId
        account = $Account
        marker = $marker
        results = $results
    } | ConvertTo-Json -Depth 10
} finally {
    if (-not [string]::IsNullOrWhiteSpace($createdRoleId) -and $null -ne $headers) {
        try {
            Invoke-OaApi -Method POST -Path '/sys/role/delete' -Headers $headers -Body @(@{ id = $createdRoleId }) -AllowFailure | Out-Null
        } catch {
            Write-Warning "cleanup failed for role ${createdRoleId}: $($_.Exception.Message)"
        }
    }
}
