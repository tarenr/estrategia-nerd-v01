<#
Orquestra o backup diario do Estrategia Nerd (sistemico local/stage/producao +
editorial), enviando tudo para o Google Drive via os comandos upload-cloud dos
scripts CLI. Pensado para rodar via Tarefa Agendada do Windows, sem depender
do admin. Cada etapa roda mesmo se uma anterior falhar - erros ficam no log,
sem travar o restante da rotina.

Backup sistemico do ambiente local: de segunda a sabado roda com --no-uploads (so banco e
arquivos do sistema); aos domingos roda completo, com uploads.zip. Os uploads ja ficam
cobertos todo dia pelo backup geral (restic). O dia vem de [DayOfWeek] numerico (domingo = 0),
sem depender do idioma do Windows. Stage e producao continuam sempre completos.
#>
param(
    # So para teste: 0 = domingo ... 6 = sabado. Padrao: o dia de hoje.
    [int]$DiaDaSemana = -1,
    # So para teste: mostra os comandos que rodariam, sem executar nada nem enviar ao Drive.
    [switch]$SomenteMostrarComandos
)
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
    if ($SomenteMostrarComandos) {
        Write-Host ('[simulacao] ' + $Description + ' -> php ' + ($CliArgs -join ' '))
        return $true
    }
    Write-Log ('INICIO: ' + $Description)
    try {
        $scriptPath = Join-Path $root $CliArgs[0]
        $extraArgs = $CliArgs[1..($CliArgs.Length - 1)]
        $output = & $php $scriptPath @extraArgs 2>&1
        $output | ForEach-Object { Write-Log ('  ' + $_) }
        if ($LASTEXITCODE -ne 0) {
            Write-Log ('FALHA: ' + $Description + ' (codigo ' + $LASTEXITCODE + ')')
            return $false
        }
        Write-Log ('OK: ' + $Description)
        return $true
    } catch {
        Write-Log ('ERRO: ' + $Description + ' - ' + $_.Exception.Message)
        return $false
    }
}

$hoje = if ($DiaDaSemana -ge 0) { $DiaDaSemana } else { [int](Get-Date).DayOfWeek }
$localCompleto = ($hoje -eq 0)

if ($SomenteMostrarComandos) {
    Write-Output ('[simulacao] dia da semana = ' + $hoje + ' (0 = domingo); backup local ' + $(if ($localCompleto) { 'COMPLETO, com uploads' } else { 'SEM uploads' }))
} else {
    Write-Log '=== Rotina diaria de backup iniciada ==='
    Write-Log ('Backup local: ' + $(if ($localCompleto) { 'COMPLETO, com uploads (domingo)' } else { 'sem uploads (segunda a sabado)' }))
}

foreach ($profile in @('local', 'stage', 'production')) {
    $runArgs = @('scripts\backup.php', 'run', $profile)
    $semUploads = ($profile -eq 'local' -and -not $localCompleto)
    if ($semUploads) { $runArgs += '--no-uploads' }
    $ok = Invoke-Step ("Backup sistemico ($profile)" + $(if ($semUploads) { ' sem uploads' } else { '' })) $runArgs
    if (-not $ok -and $profile -eq 'local' -and $localCompleto) {
        Write-Log 'ALERTA: a rodada completa de domingo (com uploads) do local falhou; o pacote local fica sem uploads.zip ate a proxima rodada completa.'
    }
    $null = Invoke-Step "Upload sistemico ($profile) para Google Drive" @('scripts\backup.php', 'upload-cloud', 'latest')
}

foreach ($profile in @('local', 'production')) {
    $null = Invoke-Step "Exportacao editorial ($profile)" @('scripts\content-sync.php', 'export', $profile)
    $null = Invoke-Step "Upload editorial ($profile) para Google Drive" @('scripts\content-sync.php', 'upload-cloud', $profile)
}

if (-not $SomenteMostrarComandos) { Write-Log '=== Rotina diaria de backup concluida ===' }
