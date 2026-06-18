param(
    [string]$ProgId = "UnityObjects.UnityApplication",
    [string]$LogoPath = "",
    [int]$CompanyNo = 2,
    [int]$PeriodNo = 1,
    [string[]]$Users = @("LOGO", "BURAK.KARACA", "FARUK.CELIK", "ERHAN.KOSAR"),
    [string[]]$Passwords = @("2525", ""),
    [switch]$ForWebUse
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Get-ComValue {
    param(
        [Parameter(Mandatory = $true)] $ComObject,
        [Parameter(Mandatory = $true)] [string] $Name,
        $DefaultValue = $null
    )

    try {
        return $ComObject.$Name
    } catch {
        return $DefaultValue
    }
}

function Invoke-ComCleanup {
    param([Parameter(Mandatory = $true)] $ComObject)

    try {
        if (Get-ComValue -ComObject $ComObject -Name "CompanyLoggedIn" -DefaultValue $false) {
            $ComObject.CompanyLogout() | Out-Null
        }
    } catch {}

    try {
        if (Get-ComValue -ComObject $ComObject -Name "LoggedIn" -DefaultValue $false) {
            $ComObject.UserLogout() | Out-Null
        }
    } catch {}

    try {
        if (Get-ComValue -ComObject $ComObject -Name "Connected" -DefaultValue $false) {
            $ComObject.Disconnect() | Out-Null
        }
    } catch {}

    try {
        [System.Runtime.InteropServices.Marshal]::ReleaseComObject($ComObject) | Out-Null
    } catch {}
}

function New-LogoApplication {
    param([Parameter(Mandatory = $true)] [type] $ComType)

    $app = [Activator]::CreateInstance($ComType)

    if ($LogoPath.Trim() -ne "") {
        $app.LGSetup($LogoPath) | Out-Null
    }

    if ($ForWebUse.IsPresent) {
        $app.ForWebUse = $true
    }

    return $app
}

function Get-LogoState {
    param(
        [Parameter(Mandatory = $true)] $ComObject,
        [Parameter(Mandatory = $true)] [string] $Flow,
        [Parameter(Mandatory = $true)] [string] $User,
        [Parameter(Mandatory = $true)] [string] $Password,
        [Parameter(Mandatory = $true)] [int] $PasswordIndex,
        $CallResult = $null,
        $ConnectResult = $null,
        $UserLoginResult = $null,
        $CompanyLoginResult = $null
    )

    $lastError = $null
    $lastErrorString = ""

    try { $lastError = $ComObject.GetLastError() } catch {}
    try { $lastErrorString = $ComObject.GetLastErrorString() } catch {}

    [pscustomobject]@{
        Flow = $Flow
        User = $User
        PasswordIndex = $PasswordIndex
        PasswordHint = if ($Password.Length -eq 0) { "<blank>" } else { "len=$($Password.Length)" }
        CallResult = $CallResult
        ConnectResult = $ConnectResult
        UserLoginResult = $UserLoginResult
        CompanyLoginResult = $CompanyLoginResult
        Connected = Get-ComValue -ComObject $ComObject -Name "Connected" -DefaultValue $null
        LoggedIn = Get-ComValue -ComObject $ComObject -Name "LoggedIn" -DefaultValue $null
        CompanyLoggedIn = Get-ComValue -ComObject $ComObject -Name "CompanyLoggedIn" -DefaultValue $null
        CurrentFirm = Get-ComValue -ComObject $ComObject -Name "CurrentFirm" -DefaultValue $null
        ActivePeriod = Get-ComValue -ComObject $ComObject -Name "ActivePeriod" -DefaultValue $null
        LastError = $lastError
        LastErrorString = $lastErrorString
    }
}

function Test-LogoLogin {
    param(
        [Parameter(Mandatory = $true)] [type] $ComType,
        [Parameter(Mandatory = $true)] [string] $Flow,
        [Parameter(Mandatory = $true)] [string] $User,
        [Parameter(Mandatory = $true)] [string] $Password,
        [Parameter(Mandatory = $true)] [int] $PasswordIndex
    )

    $app = $null

    try {
        $app = New-LogoApplication -ComType $ComType
        $callResult = $null
        $connectResult = $null
        $userLoginResult = $null
        $companyLoginResult = $null

        switch ($Flow) {
            "UserLoginOnly" {
                $userLoginResult = $app.UserLogin($User, $Password)
                $callResult = $userLoginResult
            }
            "ConnectUserCompany" {
                $connectResult = $app.Connect()
                $userLoginResult = $app.UserLogin($User, $Password)
                if ($userLoginResult) {
                    $companyLoginResult = $app.CompanyLogin($CompanyNo, $PeriodNo)
                }

                $callResult = $userLoginResult -and ($companyLoginResult -eq $true)
            }
            "CombinedLogin" {
                $callResult = $app.Login($User, $Password, $CompanyNo, $PeriodNo)
            }
            default {
                throw "Unsupported flow: $Flow"
            }
        }

        Get-LogoState `
            -ComObject $app `
            -Flow $Flow `
            -User $User `
            -Password $Password `
            -PasswordIndex $PasswordIndex `
            -CallResult $callResult `
            -ConnectResult $connectResult `
            -UserLoginResult $userLoginResult `
            -CompanyLoginResult $companyLoginResult
    } catch {
        [pscustomobject]@{
            Flow = $Flow
            User = $User
            PasswordIndex = $PasswordIndex
            PasswordHint = if ($Password.Length -eq 0) { "<blank>" } else { "len=$($Password.Length)" }
            CallResult = $false
            ConnectResult = $null
            UserLoginResult = $null
            CompanyLoginResult = $null
            Connected = $null
            LoggedIn = $null
            CompanyLoggedIn = $null
            CurrentFirm = $null
            ActivePeriod = $null
            LastError = $null
            LastErrorString = $_.Exception.Message
        }
    } finally {
        if ($null -ne $app) {
            Invoke-ComCleanup -ComObject $app
        }
    }
}

$type = [type]::GetTypeFromProgID($ProgId)
if ($null -eq $type) {
    throw "COM ProgID not found: $ProgId"
}

$flows = @("UserLoginOnly", "ConnectUserCompany", "CombinedLogin")
$results = foreach ($user in $Users) {
    for ($passwordIndex = 0; $passwordIndex -lt $Passwords.Count; $passwordIndex++) {
        $password = $Passwords[$passwordIndex]
        foreach ($flow in $flows) {
            Test-LogoLogin -ComType $type -Flow $flow -User $user -Password $password -PasswordIndex ($passwordIndex + 1)
        }
    }
}

$results | Sort-Object User, PasswordIndex, Flow | Format-Table -AutoSize

$success = $results | Where-Object { $_.CallResult -eq $true -or $_.LoggedIn -eq $true -or $_.CompanyLoggedIn -eq $true }
if ($success) {
    Write-Host ""
    Write-Host "Successful login candidates:"
    $success | Format-Table -AutoSize
} else {
    Write-Host ""
    Write-Host "No successful login found. Verify the real Logo application username/password, then rerun with -Passwords."
}
