<#
Orquestra o backup diario do Estrategia Nerd (sistemico local/stage/producao +
editorial), enviando tudo para o Google Drive via os comandos upload-cloud dos
scripts CLI. Pensado para rodar via Tarefa Agendada do Windows, sem depender
do admin. Cada etapa roda mesmo se uma anterior falhar - erros ficam no log,
sem travar o restante da rotina.
#>
$ErrorActionPreference = 'Continue'

$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$php = 'C:\xampp\php\php.exe'
$logDir = Join-Path $root 'storage\logs'
if (!(Test-Path $logDir)) { New-Item -ItemType Directory -Force -Path $logDir | Out-Null }
$logFile = Join-Path $logDir ('daily-backup-' + (Get-Date -Format 'yyyy-MM') + '.log')

function Write-Log {
    param([string]$Message)
    $line = (Get-Date -Format 'yyyy-MM-dd HH:mm:ss') + ' - ' + $Message
    Add-Content -LiteralPath $logFile -Value $line -Encoding utf8
}

function Invoke-Step {
    param([string]$Description, [string[]]$CliArgs)
    Write-Log ('INICIO: ' + $Description)
    try {
        $scriptPath = Join-Path $root $CliArgs[0]
        $extraArgs = $CliArgs[1..($CliArgs.Length - 1)]
        $output = & $php $scriptPath @extraArgs 2>&1
        $output | ForEach-Object { Write-Log ('  ' + $_) }
        if ($LASTEXITCODE -ne 0) {
            Write-Log ('FALHA: ' + $Description + ' (codigo ' + $LASTEXITCODE + ')')
        } else {
            Write-Log ('OK: ' + $Description)
        }
    } catch {
        Write-Log ('ERRO: ' + $Description + ' - ' + $_.Exception.Message)
    }
}

Write-Log '=== Rotina diaria de backup iniciada ==='

foreach ($profile in @('local', 'stage', 'production')) {
    Invoke-Step "Backup sistemico ($profile)" @('scripts\backup.php', 'run', $profile)
    Invoke-Step "Upload sistemico ($profile) para Google Drive" @('scripts\backup.php', 'upload-cloud', 'latest')
}

foreach ($profile in @('local', 'production')) {
    Invoke-Step "Exportacao editorial ($profile)" @('scripts\content-sync.php', 'export', $profile)
    Invoke-Step "Upload editorial ($profile) para Google Drive" @('scripts\content-sync.php', 'upload-cloud', 'latest')
}

Write-Log '=== Rotina diaria de backup concluida ==='
