#!/usr/bin/env python3
"""
Script de synchronisation des fichiers de tests vers DDEV
Copie les fichiers depuis Windows vers le container DDEV via WSL
"""

import os
import subprocess
from pathlib import Path

# Configuration
BASE_DIR = Path(__file__).parent
DDEV_PATH = "~/projects/tb-wp-dev/web/wp-content/plugins/wc_qualiopi_formation/tests"

# Couleurs pour output
class Colors:
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    RED = '\033[91m'
    BLUE = '\033[94m'
    END = '\033[0m'

def print_success(msg):
    print(f"{Colors.GREEN}✅ {msg}{Colors.END}")

def print_info(msg):
    print(f"{Colors.BLUE}ℹ️  {msg}{Colors.END}")

def print_error(msg):
    print(f"{Colors.RED}❌ {msg}{Colors.END}")

def print_warning(msg):
    print(f"{Colors.YELLOW}⚠️  {msg}{Colors.END}")

def run_wsl_command(cmd):
    """Exécute une commande dans WSL"""
    full_cmd = f'wsl -d Ubuntu bash -c "{cmd}"'
    result = subprocess.run(
        full_cmd,
        shell=True,
        capture_output=True,
        text=True
    )
    return result.returncode == 0, result.stdout, result.stderr

def create_directory_in_ddev(rel_path):
    """Crée un répertoire dans DDEV"""
    ddev_dir = f"{DDEV_PATH}/{rel_path}"
    cmd = f"mkdir -p {ddev_dir}"
    success, stdout, stderr = run_wsl_command(cmd)
    return success

def copy_file_to_ddev(local_file, rel_path):
    """Copie un fichier vers DDEV en utilisant un fichier temporaire"""
    try:
        # Convertir le chemin Windows en chemin WSL et échapper les espaces
        wsl_source = str(local_file).replace('\\', '/').replace('E:/', '/mnt/e/').replace(' ', '\\ ')
        
        # Chemin destination dans DDEV
        ddev_file = f"{DDEV_PATH}/{rel_path.replace(chr(92), '/')}"  # Remplacer \ par /
        
        # Créer le répertoire parent si nécessaire
        parent_dir = os.path.dirname(rel_path.replace('\\', '/'))
        if parent_dir:
            create_directory_in_ddev(parent_dir)
        
        # Copier le fichier directement (sans guillemets car espaces déjà échappés)
        cmd = f'cp {wsl_source} {ddev_file}'
        success, stdout, stderr = run_wsl_command(cmd)
        
        if not success and stderr:
            print(f"\n  Erreur: {stderr}")
        
        return success
    
    except Exception as e:
        print(f"\n  Exception: {str(e)}")
        return False

def sync_tests():
    """Synchronise tous les fichiers de tests vers DDEV"""
    print_info("🚀 Début de la synchronisation des tests vers DDEV...")
    print()
    
    files_to_sync = []
    
    # Parcourir tous les fichiers dans BASE_DIR
    for root, dirs, files in os.walk(BASE_DIR):
        # Ignorer les dossiers __pycache__ et .git
        dirs[:] = [d for d in dirs if d not in ['__pycache__', '.git', '.gitkeep']]
        
        for file in files:
            # Ignorer ce script lui-même et les fichiers temporaires
            if file == 'sync_tests_to_ddev.py' or file.endswith('.pyc') or file == 'INSTALL_TESTS.md':
                continue
            
            full_path = Path(root) / file
            rel_path = full_path.relative_to(BASE_DIR)
            files_to_sync.append((full_path, str(rel_path)))
    
    if not files_to_sync:
        print_warning("Aucun fichier à synchroniser trouvé.")
        return
    
    print_info(f"Fichiers à synchroniser : {len(files_to_sync)}")
    print()
    
    success_count = 0
    failed_count = 0
    
    for local_file, rel_path in files_to_sync:
        print(f"📁 {rel_path}...", end=" ")
        
        if copy_file_to_ddev(local_file, rel_path):
            print_success("OK")
            success_count += 1
        else:
            print_error("ÉCHEC")
            failed_count += 1
    
    print()
    print("=" * 60)
    print(f"{Colors.GREEN}✅ Réussis : {success_count}{Colors.END}")
    if failed_count > 0:
        print(f"{Colors.RED}❌ Échoués : {failed_count}{Colors.END}")
    print("=" * 60)
    print()
    
    if success_count > 0:
        print_success("Synchronisation terminée !")
        print()
        print_info("Vous pouvez maintenant tester avec :")
        print('  wsl -d Ubuntu bash -c "cd ~/projects/tb-wp-dev && ddev exec \'cd web/wp-content/plugins/wc_qualiopi_formation && ./vendor/bin/pest\'"')
    
    return success_count > 0 and failed_count == 0

def verify_sync():
    """Vérifie que les fichiers sont bien présents dans DDEV"""
    print()
    print_info("🔍 Vérification des fichiers dans DDEV...")
    
    cmd = f"find {DDEV_PATH} -type f -name '*.php' -o -name '*.py' | grep -v __pycache__ | sort"
    success, stdout, stderr = run_wsl_command(cmd)
    
    if success and stdout:
        files = stdout.strip().split('\n')
        print_success(f"{len(files)} fichiers trouvés dans DDEV :")
        for file in files:
            # Afficher juste le nom relatif
            rel = file.replace(DDEV_PATH + "/", "")
            print(f"  - {rel}")
    else:
        print_warning("Aucun fichier trouvé dans DDEV")
    
    print()

if __name__ == "__main__":
    print()
    print("=" * 60)
    print("  🧪 SYNCHRONISATION TESTS → DDEV")
    print("=" * 60)
    print()
    
    try:
        # Synchronisation
        success = sync_tests()
        
        # Vérification
        if success:
            verify_sync()
        
        print()
        if success:
            print_success("✨ Tout est prêt ! Vous pouvez lancer les tests.")
        else:
            print_error("⚠️  Certains fichiers n'ont pas pu être copiés.")
        print()
    
    except KeyboardInterrupt:
        print()
        print_warning("Synchronisation interrompue par l'utilisateur")
        print()
    except Exception as e:
        print()
        print_error(f"Erreur inattendue : {str(e)}")
        print()

