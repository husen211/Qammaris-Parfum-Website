param(
    [ValidateSet('Initialize', 'CopyApiKey', 'CopyWebhookSecret', 'ClearClipboard')]
    [string]$Action = 'Initialize'
)

$ErrorActionPreference = 'Stop'
if ([Environment]::OSVersion.Platform -ne 'Win32NT') {
    throw 'This secret store requires Windows user-bound DPAPI.'
}

$vaultDirectory = Join-Path $env:LOCALAPPDATA 'QammarisWebsite/IntegrationSecrets'
$vaultFile = Join-Path $vaultDirectory 'staging-api-secrets.clixml'
$userSid = [Security.Principal.WindowsIdentity]::GetCurrent().User

function Read-StagingVault {
    if (-not (Test-Path -LiteralPath $vaultFile -PathType Leaf)) {
        throw 'The protected staging secret store has not been initialized.'
    }
    $stored = Import-Clixml -LiteralPath $vaultFile
    if ($stored.ApiKey -isnot [Security.SecureString] -or
        $stored.WebhookSecret -isnot [Security.SecureString] -or
        $stored.ApiKey.Length -ne 64 -or $stored.WebhookSecret.Length -ne 64) {
        throw 'The protected staging secret store is invalid; no values were printed.'
    }
    return $stored
}

if ($Action -eq 'Initialize') {
    if (-not (Test-Path -LiteralPath $vaultDirectory -PathType Container)) {
        New-Item -ItemType Directory -Path $vaultDirectory -Force | Out-Null
    }
    if ((Get-Item -LiteralPath $vaultDirectory).Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'The secret store must not be a directory link.'
    }
    $acl = Get-Acl -LiteralPath $vaultDirectory
    $allowedSids = @($userSid.Value, 'S-1-5-18')
    $unexpectedRules = @($acl.Access | Where-Object {
        $_.IdentityReference.Translate([Security.Principal.SecurityIdentifier]).Value -notin $allowedSids
    })
    if (-not $acl.AreAccessRulesProtected -or $unexpectedRules.Count -gt 0) {
        # Modify only the existing DACL; replacing the whole descriptor can request SACL privileges.
        $acl.SetAccessRuleProtection($true, $false)
        foreach ($existingRule in @($acl.Access)) { $acl.RemoveAccessRuleSpecific($existingRule) }
        foreach ($sid in @($userSid, [Security.Principal.SecurityIdentifier]::new('S-1-5-18'))) {
            $rule = [Security.AccessControl.FileSystemAccessRule]::new(
                $sid, 'FullControl', 'ContainerInherit,ObjectInherit', 'None', 'Allow'
            )
            $acl.AddAccessRule($rule)
        }
        Set-Acl -LiteralPath $vaultDirectory -AclObject $acl
    }

    # Existing values are reused: retries must not silently rotate either side.
    if (-not (Test-Path -LiteralPath $vaultFile -PathType Leaf)) {
        $random = [Security.Cryptography.RandomNumberGenerator]::Create()
        try {
            $apiBytes = [byte[]]::new(32)
            $secretBytes = [byte[]]::new(32)
            $random.GetBytes($apiBytes)
            do { $random.GetBytes($secretBytes) }
            while ([Convert]::ToBase64String($apiBytes) -eq [Convert]::ToBase64String($secretBytes))
            $apiKey = [BitConverter]::ToString($apiBytes).Replace('-', '').ToLowerInvariant()
            $webhookSecret = [BitConverter]::ToString($secretBytes).Replace('-', '').ToLowerInvariant()
            $stored = [pscustomobject]@{
                ApiKey = ConvertTo-SecureString $apiKey -AsPlainText -Force
                WebhookSecret = ConvertTo-SecureString $webhookSecret -AsPlainText -Force
                CreatedAt = [DateTimeOffset]::UtcNow.ToString('o')
            }
            $stored | Export-Clixml -LiteralPath $vaultFile
        } finally {
            $random.Dispose()
            $apiKey = $null
            $webhookSecret = $null
            [Array]::Clear($apiBytes, 0, $apiBytes.Length)
            [Array]::Clear($secretBytes, 0, $secretBytes.Length)
        }
    }
    $stored = Read-StagingVault
    Write-Output 'API key: terisi'
    Write-Output 'Webhook secret: terisi'
    exit 0
}

$stored = Read-StagingVault
if ($Action -eq 'ClearClipboard') {
    $clipboardText = Get-Clipboard -Raw
    foreach ($secureValue in @($stored.ApiKey, $stored.WebhookSecret)) {
        $plainValue = [Net.NetworkCredential]::new('', $secureValue).Password
        if ($clipboardText -ceq $plainValue) { Set-Clipboard -Value '' }
        $plainValue = $null
    }
    $clipboardText = $null
    exit 0
}

# Owner runs Copy actions locally and pastes only into the approved hosting env field.
# The helper never prints a value, writes plaintext, or sends it to a remote service.
$secureValue = if ($Action -eq 'CopyApiKey') { $stored.ApiKey } else { $stored.WebhookSecret }
$plainValue = [Net.NetworkCredential]::new('', $secureValue).Password
try { Set-Clipboard -Value $plainValue }
finally { $plainValue = $null }
