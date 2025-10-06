# Synchronisation Complete Windows -> DDEV (TOUT-EN-UN) - Version 2.2
# Usage: .\Sync-ToDDEV-Ameliore.ps1 [-ConfigFile] [-WhatIf] [-LogLevel] [-InstallDeps] [-Force] [-Verify] [-ClearCache] [-RestartDDEV] [-Quiet]
# 
# NOUVELLES FONCTIONNALITES v2.2 :
# - Configuration externe (.sync-config.json)
# - Mode Dry-Run (-WhatIf) pour simulation
# - Logging structuré avec niveaux (-LogLevel)
# - Vérification intelligente post-sync
# - Mode Quiet cohérent global
# 
# Ce script fait TOUT automatiquement :
# 1. Chargement de la configuration
# 2. Synchronisation complete du plugin
# 3. Verification intelligente des fichiers
# 4. Nettoyage des caches DDEV
# 5. Installation des dependances (optionnel)

param(
    [string]$ConfigFile = (Join-Path $PSScriptRoot ".sync-config.json"),  # Fichier de configuration
    [switch]$WhatIf,                            # Mode simulation (dry-run)
    [ValidateSet("Error", "Warning", "Info", "Debug", "Verbose")]
    [string]$LogLevel = "Info",                 # Niveau de logging
    [switch]$Force,                             # Force (surcharge config)
    [switch]$Verify,                            # Verify (surcharge config)
    [switch]$ClearCache,                        # ClearCache (surcharge config)
    [switch]$RestartDDEV,                       # RestartDDEV (surcharge config)
    [switch]$InstallDeps,                       # InstallDeps (surcharge config)
    [switch]$Quiet                              # Mode silencieux (surcharge config)
)

$ErrorActionPreference = "Stop"

# =============================================================================
# CONSTANTES
# =============================================================================

# Codes de sortie Robocopy
$ROBOCOPY_SUCCESS = @(0, 1)                    # Succès
$ROBOCOPY_EXTRA_FILES = @(2, 3)                # Fichiers supplémentaires
$ROBOCOPY_INCOMPATIBLE = @(4, 5)               # Fichiers incompatibles
$ROBOCOPY_ERROR = @(8..15)                     # Erreurs

# Niveaux de logging
$LOG_LEVELS = @{
    Error = 0
    Warning = 1
    Info = 2
    Debug = 3
    Verbose = 4
}

# =============================================================================
# FONCTIONS UTILITAIRES
# =============================================================================

# Fonction d'analyse des codes de sortie Robocopy
function Get-RobocopyStatus {
    param([int]$ExitCode)
    
    if ($ExitCode -in $ROBOCOPY_SUCCESS) {
        return @{
            Status = "Success"
            Message = if ($ExitCode -eq 0) { "Aucun fichier modifie detecte" } else { "Fichiers copies avec succes" }
            Level = "Info"
        }
    }
    elseif ($ExitCode -in $ROBOCOPY_EXTRA_FILES) {
        return @{
            Status = "ExtraFiles"
            Message = "Fichiers supplementaires trouves dans la cible"
            Level = "Warning"
        }
    }
    elseif ($ExitCode -in $ROBOCOPY_INCOMPATIBLE) {
        return @{
            Status = "Incompatible"
            Message = "Fichiers incompatibles detectes"
            Level = "Warning"
        }
    }
    elseif ($ExitCode -in $ROBOCOPY_ERROR) {
        return @{
            Status = "Error"
            Message = "Erreur de synchronisation (code: $ExitCode)"
            Level = "Error"
        }
    }
    else {
        return @{
            Status = "Unknown"
            Message = "Code de sortie inconnu : $ExitCode"
            Level = "Warning"
        }
    }
}

# =============================================================================
# FONCTIONS UTILITAIRES
# =============================================================================

# Fonction de logging optimisée avec flush périodique
function Write-Log {
    param(
        [string]$Message,
        [ValidateSet("Error", "Warning", "Info", "Debug", "Verbose")]
        [string]$Level = "Info"
    )
    
    # Vérifier si le niveau doit être affiché
    $currentLevel = $LOG_LEVELS[$script:LogLevel]
    $messageLevel = $LOG_LEVELS[$Level]
    
    if ($messageLevel -gt $currentLevel) {
        return
    }
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    
    # Affichage console
    if (-not $script:Quiet) {
        $color = switch ($Level) {
            "Error" { "Red" }
            "Warning" { "Yellow" }
            "Info" { "White" }
            "Debug" { "Gray" }
            "Verbose" { "Cyan" }
        }
        Write-Host $logEntry -ForegroundColor $color
    }
    
    # Ajout au buffer de logs avec flush périodique
    if ($script:LogEnabled) {
        $script:LogBuffer += $logEntry
        
        # Flush automatique si le buffer atteint 50 entrées ou si c'est une erreur
        if ($script:LogBuffer.Count -ge 50 -or $Level -eq "Error") {
            Flush-LogBuffer
        }
    }
}

# Fonction d'écriture des logs en batch
function Flush-LogBuffer {
    if ($script:LogBuffer.Count -gt 0 -and $script:LogFile) {
        try {
            $script:LogBuffer | Add-Content -Path $script:LogFile -Encoding UTF8
            $script:LogBuffer.Clear()
        } catch {
            # Fallback : afficher l'erreur en console si possible
            if (-not $script:Quiet) {
                Write-Host "ERREUR LOGGING : $($_.Exception.Message)" -ForegroundColor Red
            }
        }
    }
}

# Fonction de sortie d'erreur standardisée
function Exit-WithError {
    param(
        [string]$Message,
        [int]$ExitCode = 1
    )
    
    Write-Log $Message "Error"
    Remove-SyncLock
    Flush-LogBuffer
    exit $ExitCode
}

# Fonction de validation et sanitization des chemins
function Test-SafePath {
    param([string]$Path)
    
    # Vérifier que le chemin ne contient pas de séquences dangereuses
    if ($Path -match '\.\.' -or $Path -match '[\|\*\?\"<>]') {
        return $false
    }
    
    # Vérifier que le chemin est valide
    try {
        $null = [System.IO.Path]::GetFullPath($Path)
        return $true
    } catch {
        return $false
    }
}

# Fonction de validation stricte des chemins avec message d'erreur
function Assert-SafePath {
    param(
        [string]$Path,
        [string]$Context = "Chemin"
    )
    
    if (-not (Test-SafePath $Path)) {
        Exit-WithError "$Context non securise detecte : $Path"
    }
}

# Fonction de sanitization des paramètres WSL avec liste blanche
function Invoke-SafeWslCommand {
    param(
        [string]$Distribution,
        [string]$Command
    )
    
    # Liste blanche des commandes autorisées
    $allowedCommands = @('ddev', 'wp', 'cd', 'echo', 'kill', 'pwd', 'ls', 'test')
    
    # Validation des paramètres
    if (-not $Distribution -or $Distribution -match '[^a-zA-Z0-9_-]') {
        Exit-WithError "Distribution WSL invalide : $Distribution"
    }
    
    if (-not $Command -or $Command -match '[;&|`$]') {
        Exit-WithError "Commande WSL potentiellement dangereuse : $Command"
    }
    
    # Vérifier que la première commande est autorisée
    $commandParts = $Command -split '\s+'
    $firstCommand = $commandParts[0]
    
    if ($firstCommand -notin $allowedCommands) {
        Exit-WithError "Commande non autorisee : $firstCommand"
    }
    
    # Exécution sécurisée
    return wsl -d $Distribution -e sh -c $Command
}

# Fonction réutilisable pour les commandes DDEV
function Invoke-DDEVCommand {
    param(
        [string]$Command,
        [string]$Distribution = "Ubuntu",
        [string]$ProjectPath = "~/projects/tb-wp-dev"
    )
    
    $fullCommand = "cd $ProjectPath && ddev $Command"
    return Invoke-SafeWslCommand -Distribution $Distribution -Command $fullCommand
}

# Fonction de chargement et validation de la configuration
function Load-Config {
    param([string]$ConfigPath)
    
    $defaultConfig = @{
        paths = @{
            source = $PSScriptRoot
            target = "\\wsl$\Ubuntu\home\gabrysh\projects\tb-wp-dev\web\wp-content\plugins\wc_qualiopi_formation"
        }
        robocopy = @{
            excludeDirectories = @("node_modules", ".git")
            excludeFiles = @("*.md", "*.bat", "*.ps1", "sync_*.py", "*.log")
            options = @("/E", "/NJH", "/NJS", "/NDL", "/NP")
        }
        verification = @{
            criticalFiles = @("wc_qualiopi_formation.php", "src/Core/Plugin.php", "assets/js/form-frontend.js")
            enableChecksums = $false
        }
        logging = @{
            enabled = $true
            logDirectory = "./logs"
            logFile = "sync-{date}.log"
            maxLogFiles = 10
            defaultLevel = "Info"
        }
        defaults = @{
            force = $true
            verify = $true
            clearCache = $true
            restartDDEV = $false
            installDeps = $false
            quiet = $false
        }
        ddev = @{
            wslDistribution = "Ubuntu"
            projectPath = "~/projects/tb-wp-dev"
            siteUrl = "https://tb-wp-dev.ddev.site"
        }
    }
    
    if (Test-Path $ConfigPath) {
        try {
            $configContent = Get-Content $ConfigPath -Raw -Encoding UTF8
            $config = $configContent | ConvertFrom-Json
            
            # Validation de la configuration
            if (-not $config.paths -or -not $config.paths.source -or -not $config.paths.target) {
                throw "Configuration invalide : sections paths manquantes"
            }
            
            # Validation des chemins
            if (-not (Test-SafePath $config.paths.source) -or -not (Test-SafePath $config.paths.target)) {
                throw "Configuration invalide : chemins dangereux detectes"
            }
            
            # Validation des paramètres DDEV
            if ($config.ddev -and $config.ddev.wslDistribution) {
                if ($config.ddev.wslDistribution -match '[^a-zA-Z0-9_-]') {
                    throw "Configuration invalide : distribution WSL invalide"
                }
            }
            
            Write-Log "Configuration chargee et validee depuis : $ConfigPath" "Debug"
            return $config
        } catch {
            Write-Log "Erreur lors du chargement de la configuration : $($_.Exception.Message)" "Warning"
            Write-Log "Utilisation de la configuration par defaut" "Info"
            return $defaultConfig
        }
    } else {
        Write-Log "Fichier de configuration non trouve : $ConfigPath" "Warning"
        Write-Log "Utilisation de la configuration par defaut" "Info"
        return $defaultConfig
    }
}

# Fonction de gestion du verrouillage
function New-SyncLock {
    $lockFile = Join-Path $PSScriptRoot ".sync.lock"
    
    if (Test-Path $lockFile) {
        $lockContent = Get-Content $lockFile -Raw
        $lockTime = [DateTime]::Parse($lockContent)
        
        # Vérifier si le verrou est ancien (plus de 10 minutes)
        if ((Get-Date) - $lockTime -gt [TimeSpan]::FromMinutes(10)) {
            Write-Log "Verrou ancien detecte, suppression..." "Warning"
            Remove-Item $lockFile -Force
        } else {
            throw "Script deja en cours d'execution (verrou cree le $lockTime)"
        }
    }
    
    # Créer le verrou
    (Get-Date).ToString("yyyy-MM-dd HH:mm:ss") | Out-File $lockFile -Encoding UTF8
    Write-Log "Verrou cree : $lockFile" "Debug"
}

function Remove-SyncLock {
    $lockFile = Join-Path $PSScriptRoot ".sync.lock"
    if (Test-Path $lockFile) {
        Remove-Item $lockFile -Force
        Write-Log "Verrou supprime" "Debug"
    }
}

# Fonction d'initialisation du logging optimisée
function Initialize-Logging {
    param(
        [object]$Config,
        [string]$RequestedLogLevel
    )
    
    $script:LogEnabled = $Config.logging.enabled
    $script:LogLevel = if ($RequestedLogLevel) { $RequestedLogLevel } else { $Config.logging.defaultLevel }
    $script:Quiet = if ($Quiet.IsPresent) { $true } else { $Config.defaults.quiet }
    $script:LogBuffer = @()
    
    if ($script:LogEnabled) {
        $logDir = $Config.logging.logDirectory
        if (-not (Test-Path $logDir)) {
            New-Item -Path $logDir -ItemType Directory -Force | Out-Null
        }
        
        $dateStr = Get-Date -Format "yyyyMMdd"
        $logFileName = $Config.logging.logFile -replace "{date}", $dateStr
        $script:LogFile = Join-Path $logDir $logFileName
        
        # Nettoyage des anciens logs (date + nombre de fichiers)
        $maxFiles = $Config.logging.maxLogFiles
        $cutoffDate = (Get-Date).AddDays(-30)
        
        # Suppression basée sur la date
        Get-ChildItem -Path $logDir -Filter "sync-*.log" | 
            Where-Object { $_.LastWriteTime -lt $cutoffDate } | 
            Remove-Item -Force
        
        # Suppression basée sur le nombre (garder les N plus récents)
        $logFiles = Get-ChildItem -Path $logDir -Filter "sync-*.log" | 
            Sort-Object LastWriteTime -Descending
        
        if ($logFiles.Count -gt $maxFiles) {
            $logFiles | Select-Object -Skip $maxFiles | Remove-Item -Force
        }
    }
}

# =============================================================================
# INITIALISATION
# =============================================================================

# Création du verrou pour éviter l'exécution concurrente
try {
    New-SyncLock
} catch {
    Write-Host "ERREUR : $($_.Exception.Message)" -ForegroundColor Red
    exit 1
}

# Chargement de la configuration
$Config = Load-Config $ConfigFile

# Initialisation du logging
Initialize-Logging -Config $Config -RequestedLogLevel $LogLevel

# Application des paramètres (surcharge de la config)
$script:Force = if ($Force.IsPresent) { $true } elseif ($Force -eq $false) { $false } else { $Config.defaults.force }
$script:Verify = if ($Verify.IsPresent) { $true } elseif ($Verify -eq $false) { $false } else { $Config.defaults.verify }
$script:ClearCache = if ($ClearCache.IsPresent) { $true } elseif ($ClearCache -eq $false) { $false } else { $Config.defaults.clearCache }
$script:RestartDDEV = if ($RestartDDEV.IsPresent) { $true } elseif ($RestartDDEV -eq $false) { $false } else { $Config.defaults.restartDDEV }
$script:InstallDeps = if ($InstallDeps.IsPresent) { $true } elseif ($InstallDeps -eq $false) { $false } else { $Config.defaults.installDeps }

Write-Log "=== DEBUT SYNCHRONISATION DDEV v2.2 ===" "Info"

# Gestion du nettoyage en cas d'arrêt du script
$script:Cleanup = {
    Remove-SyncLock
    Flush-LogBuffer
}

# Enregistrer le nettoyage pour l'arrêt du script
Register-EngineEvent -SourceIdentifier PowerShell.Exiting -Action $script:Cleanup

# Affichage des paramètres actifs
Write-Log "Parametres actifs :" "Info"
Write-Log "  - Force: $script:Force" "Debug"
Write-Log "  - Verify: $script:Verify" "Debug"
Write-Log "  - ClearCache: $script:ClearCache" "Debug"
Write-Log "  - RestartDDEV: $script:RestartDDEV" "Debug"
Write-Log "  - InstallDeps: $script:InstallDeps" "Debug"
Write-Log "  - Quiet: $script:Quiet" "Debug"
Write-Log "  - LogLevel: $LogLevel" "Debug"

# =============================================================================
# ETAPE 1 : INSTALLATION DES DEPENDANCES
# =============================================================================

if ($script:InstallDeps) {
    Write-Log "[ETAPE 1/5] INSTALLATION DES DEPENDANCES" "Info"
    
    # Vérifier que composer.json existe
    $composerPath = Join-Path $PSScriptRoot "composer.json"
    if (-not (Test-Path $composerPath)) {
        Write-Log "composer.json non trouve - Installation ignoree" "Warning"
    } else {
        # Déterminer le contexte d'exécution
        $composerContext = if ($Config.dependencies -and $Config.dependencies.context) { 
            $Config.dependencies.context 
        } else { 
            "windows" 
        }
        
        $composerOptions = if ($Config.dependencies -and $Config.dependencies.composerOptions) {
            $Config.dependencies.composerOptions -join " "
        } else {
            "--no-dev --optimize-autoloader"
        }
        
        if ($WhatIf) {
            if ($composerContext -eq "ddev") {
                Write-Log "[SIMULATION] Executerait dans DDEV : ddev composer install $composerOptions" "Info"
            } else {
                Write-Log "[SIMULATION] Executerait en local : composer install $composerOptions" "Info"
            }
        } else {
            if ($composerContext -eq "ddev") {
                Write-Log "Installation des dependances via DDEV Composer..." "Info"
                try {
                    $pluginPath = "web/wp-content/plugins/wc_qualiopi_formation"
                    $composerCmd = "cd $pluginPath && composer install $composerOptions"
                    $ComposerOutput = Invoke-DDEVCommand -Command "exec '$composerCmd'" -Distribution $Config.ddev.wslDistribution -ProjectPath $Config.ddev.projectPath 2>&1
                    $ComposerExitCode = $LASTEXITCODE
                    
                    if ($ComposerExitCode -eq 0) {
                        Write-Log "Dependances installees avec succes (DDEV)" "Info"
                    } else {
                        Write-Log "Probleme lors de l'installation des dependances dans DDEV (code: $ComposerExitCode)" "Warning"
                    }
                } catch {
                    Write-Log "Erreur lors de l'installation via DDEV : $($_.Exception.Message)" "Error"
                }
            } else {
                Write-Log "Installation des dependances Composer (Windows)..." "Info"
                Push-Location $PSScriptRoot
                
                try {
                    $ComposerOutput = & "composer" "install" $composerOptions.Split(' ') 2>&1
                    $ComposerExitCode = $LASTEXITCODE
                    
                    if ($ComposerExitCode -eq 0) {
                        Write-Log "Dependances installees avec succes (Windows)" "Info"
                    } else {
                        Write-Log "Probleme lors de l'installation des dependances (code: $ComposerExitCode)" "Warning"
                    }
                } catch {
                    Write-Log "Impossible d'installer les dependances : $($_.Exception.Message)" "Error"
                } finally {
                    Pop-Location
                }
            }
        }
    }
}

# =============================================================================
# ETAPE 2 : VERIFICATION DES CHEMINS
# =============================================================================

Write-Log "[ETAPE 2/5] VERIFICATION DES CHEMINS" "Info"

# Utilisation des chemins de la configuration (résolution des chemins relatifs)
$Source = if ($Config.paths.source -eq ".") { $PSScriptRoot } else { $Config.paths.source }
$Target = $Config.paths.target

Write-Log "Source : $Source" "Debug"
Write-Log "Cible : $Target" "Debug"

# Validation stricte des chemins
Assert-SafePath $Source "Repertoire source"
Assert-SafePath $Target "Repertoire cible"

if (-not (Test-Path $Source)) {
    Exit-WithError "Repertoire source introuvable : $Source"
}

if (-not (Test-Path $Target)) {
    Write-Log "Repertoire cible WSL introuvable" "Warning"
    
    if ($WhatIf) {
        Write-Log "[SIMULATION] Creerait le repertoire cible : $Target" "Info"
    } else {
        Write-Log "Tentative de creation..." "Info"
        try {
            New-Item -Path $Target -ItemType Directory -Force | Out-Null
            Write-Log "Repertoire cible cree avec succes" "Info"
        } catch {
            Exit-WithError "Impossible de creer le repertoire cible : $($_.Exception.Message)"
        }
    }
}

Write-Log "Chemins valides" "Info"

# =============================================================================
# ETAPE 3 : SYNCHRONISATION
# =============================================================================

Write-Log "[ETAPE 3/5] SYNCHRONISATION" "Info"

# Préparation des arguments Robocopy basés sur la configuration
$RobocopyArgs = @($Source, $Target)

# Ajout des options de base depuis la configuration
foreach ($option in $Config.robocopy.options) {
    $RobocopyArgs += $option
}

# Ajout des exclusions de dossiers
if ($Config.robocopy.excludeDirectories.Count -gt 0) {
    $RobocopyArgs += "/XD"
    $RobocopyArgs += $Config.robocopy.excludeDirectories
}

# Ajout des exclusions de fichiers
if ($Config.robocopy.excludeFiles.Count -gt 0) {
    $RobocopyArgs += "/XF"
    $RobocopyArgs += $Config.robocopy.excludeFiles
}

# Mode Synchronisation (Mirror vs Standard)
$syncMode = if ($Config.sync -and $Config.sync.mode) { $Config.sync.mode } else { "standard" }

if ($syncMode -eq "mirror" -or $script:Force) {
    $RobocopyArgs += "/MIR"  # Mirror (supprime fichiers en trop dans cible)
    Write-Log "Mode MIRROR active - Synchronisation complete avec suppression" "Warning"
    if (-not $WhatIf) {
        Write-Log "ATTENTION : Les fichiers absents de la source seront supprimes de la cible" "Warning"
    }
} else {
    $RobocopyArgs += "/IS"   # Include Same (copie meme si identique)
    Write-Log "Mode STANDARD - Copie incrementale uniquement" "Debug"
}

# Mode WhatIf (Dry-Run)
if ($WhatIf) {
    $RobocopyArgs += "/L"    # List only - no copying, deleting or timestamp
    Write-Log "MODE SIMULATION (DRY-RUN) - Aucune modification ne sera effectuee" "Warning"
}

# Exécution de Robocopy
Write-Log "Execution de Robocopy..." "Debug"
$RobocopyOutput = & robocopy @RobocopyArgs 2>&1
$ExitCode = $LASTEXITCODE

Write-Log "Code de sortie Robocopy : $ExitCode" "Debug"

# Analyse des résultats avec la nouvelle fonction
$robocopyStatus = Get-RobocopyStatus $ExitCode

# Mode normal - utiliser la fonction d'analyse
Write-Log $robocopyStatus.Message $robocopyStatus.Level

if ($robocopyStatus.Status -eq "Error") {
    Write-Log "Causes possibles :" "Error"
    Write-Log "  - Fichier verrouille par un processus" "Error"
    Write-Log "  - Permissions insuffisantes" "Error"
    Write-Log "  - Espace disque insuffisant" "Error"
    Write-Log "  - WSL non demarre" "Error"
    exit 1
}

# =============================================================================
# ETAPE 4 : VERIFICATION INTELLIGENTE POST-SYNC
# =============================================================================

if ($script:Verify) {
    Write-Log "[ETAPE 4/5] VERIFICATION POST-SYNC" "Info"
    
    # Vérification des fichiers critiques depuis la configuration
    $criticalFiles = $Config.verification.criticalFiles
    $allOK = $true
    $verifiedFiles = @()
    
    foreach ($file in $criticalFiles) {
        $targetFile = Join-Path $Target $file
        if (Test-Path $targetFile) {
            Write-Log "  [OK] $file" "Info"
            $verifiedFiles += $file
            
            # Vérification des checksums si activée
            if ($Config.verification.enableChecksums) {
                $sourceFile = Join-Path $Source $file
                if (Test-Path $sourceFile) {
                    $sourceHash = Get-FileHash $sourceFile -Algorithm MD5
                    $targetHash = Get-FileHash $targetFile -Algorithm MD5
                    
                    if ($sourceHash.Hash -eq $targetHash.Hash) {
                        Write-Log "    [CHECKSUM OK] $file" "Debug"
                    } else {
                        Write-Log "    [CHECKSUM FAIL] $file" "Warning"
                        $allOK = $false
                    }
                }
            }
        } else {
            Write-Log "  [MANQUANT] $file" "Error"
            $allOK = $false
        }
    }
    
    # Vérification basée sur les fichiers réellement synchronisés (si disponible)
    if ($ExitCode -eq 1 -or $ExitCode -eq 3) {
        Write-Log "Verification des fichiers synchronises..." "Debug"
        # Ici on pourrait parser la sortie Robocopy pour vérifier les fichiers copiés
        # Pour l'instant, on se contente des fichiers critiques
    }
    
    if ($allOK) {
        Write-Log "VERIFICATION REUSSIE - $($verifiedFiles.Count) fichiers critiques OK" "Info"
    } else {
        Write-Log "VERIFICATION ECHOUEE - Certains fichiers critiques manquent" "Error"
    }
}

# =============================================================================
# ETAPE 5 : NETTOYAGE DES CACHES DDEV
# =============================================================================

if ($script:ClearCache) {
    Write-Log "[ETAPE 5/5] NETTOYAGE DES CACHES DDEV" "Info"
    
    if ($WhatIf) {
        Write-Log "[SIMULATION] Operations DDEV qui seraient executees :" "Info"
        if ($script:RestartDDEV) {
            Write-Log "[SIMULATION] - ddev restart" "Info"
        }
        Write-Log "[SIMULATION] - ddev wp cache flush" "Info"
        Write-Log "[SIMULATION] - Rechargement PHP-FPM" "Info"
        Write-Log "[SIMULATION] - Test HTTP du site" "Info"
    } else {
        # Vérifier que WSL fonctionne
        Write-Log "Verification WSL..." "Debug"
        $wslDistribution = $Config.ddev.wslDistribution
        $wslTest = wsl -d $wslDistribution -e echo "OK" 2>$null
        
        if (-not $wslTest) {
            Write-Log "WSL ne repond pas - Verifiez que WSL est demarre" "Error"
            Write-Log "Nettoyage des caches DDEV impossible sans WSL" "Warning"
        } else {
            Write-Log "WSL operationnel" "Info"
            
            # 1. Redémarrer DDEV (seulement si activé)
            if ($script:RestartDDEV) {
            Write-Log "Redemarrage DDEV..." "Info"
            try {
                $restartOutput = Invoke-DDEVCommand -Command "restart" -Distribution $wslDistribution -ProjectPath $Config.ddev.projectPath 2>&1
                if ($LASTEXITCODE -eq 0) {
                    Write-Log "DDEV redemarre avec succes" "Info"
                } else {
                    Write-Log "Probleme lors du redemarrage DDEV" "Warning"
                }
            } catch {
                Write-Log "Erreur lors du redemarrage DDEV : $($_.Exception.Message)" "Warning"
            }
        } else {
            Write-Log "Redemarrage DDEV ignore (trop lent pour developpement)" "Debug"
        }
        
        # 2. Vider le cache WordPress
        Write-Log "Vidage cache WordPress..." "Info"
        try {
            $cacheOutput = Invoke-DDEVCommand -Command "wp cache flush" -Distribution $wslDistribution -ProjectPath $Config.ddev.projectPath 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Log "Cache WordPress vide" "Info"
            } else {
                Write-Log "Impossible de vider le cache WordPress (normal si WP-CLI non configure)" "Warning"
            }
        } catch {
            Write-Log "Erreur lors du vidage cache WordPress : $($_.Exception.Message)" "Warning"
        }
        
        # 3. Vider l'OPcache PHP
        Write-Log "Redemarrage PHP-FPM (vide OPcache)..." "Info"
        try {
            $phpOutput = Invoke-DDEVCommand -Command "exec 'kill -USR2 1'" -Distribution $wslDistribution -ProjectPath $Config.ddev.projectPath 2>&1
            if ($LASTEXITCODE -eq 0) {
                Write-Log "PHP-FPM recharge" "Info"
            } else {
                Write-Log "Impossible de recharger PHP-FPM - OPcache vide au redemarrage DDEV" "Warning"
            }
        } catch {
            Write-Log "Erreur lors du rechargement PHP-FPM : $($_.Exception.Message)" "Warning"
        }
        
        # 4. Vérifier que le site répond
        Write-Log "Verification du site..." "Info"
        Start-Sleep -Seconds 3
        
        try {
            $siteUrl = $Config.ddev.siteUrl
            $response = Invoke-WebRequest -Uri $siteUrl -UseBasicParsing -TimeoutSec 10
            if ($response.StatusCode -eq 200) {
                Write-Log "Site accessible (HTTP 200)" "Info"
            } else {
                Write-Log "Site repond avec code : $($response.StatusCode)" "Warning"
            }
        } catch {
            Write-Log "Site non accessible : $($_.Exception.Message)" "Warning"
        }
        }
    }
}

# =============================================================================
# FINALISATION
# =============================================================================

Write-Log "=== SYNCHRONISATION TERMINEE ! ===" "Info"

Write-Log "PROCHAINES ETAPES :" "Info"
Write-Log "  1. Rafraichir le navigateur : Ctrl+Shift+R" "Info"
Write-Log "  2. Tester votre fonctionnalite" "Info"
Write-Log "  3. Si probleme persiste : ddev logs -f" "Info"

Write-Log "FONCTIONNALITES v2.2 :" "Info"
Write-Log "  - Configuration externe (.sync-config.json)" "Info"
Write-Log "  - Logging structure avec niveaux" "Info"
Write-Log "  - Verification intelligente post-sync" "Info"
Write-Log "  - Mode Quiet coherent global" "Info"

Write-Log "Logs disponibles dans : $script:LogFile" "Debug"
Write-Log "=== FIN DU SCRIPT ===" "Info"

# Nettoyage final
Remove-SyncLock
Flush-LogBuffer