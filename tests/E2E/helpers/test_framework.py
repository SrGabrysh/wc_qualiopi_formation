#!/usr/bin/env python3
"""
Framework de base pour tests E2E interactifs WC Qualiopi Formation
Basé sur la stratégie PTI_001_2.py
"""

import subprocess
import time
import json
from datetime import datetime
from typing import List, Dict, Optional


class E2ETestFramework:
    """
    Framework de base pour tests E2E avec SSH/WP-CLI + observations utilisateur
    """

    def __init__(self, test_id: str, test_name: str, description: str):
        self.test_id = test_id
        self.test_name = test_name
        self.description = description
        self.start_time = time.time()
        self.phases = []
        self.observations = []
        self.logs = []
        self.debug_mode = False

    def print_phase(self, phase_name: str):
        """Affiche un titre de phase"""
        print(f"\n{'=' * 60}")
        print(f"  {phase_name}")
        print(f"{'=' * 60}\n")
        self.phases.append({"name": phase_name, "start": time.time()})

    def print_instruction(self, *instructions):
        """Affiche des instructions utilisateur"""
        print("📋 INSTRUCTIONS :")
        for instruction in instructions:
            print(f"   {instruction}")
        print()

    def print_javascript_test(self, js_code: str):
        """Affiche du code JavaScript à exécuter dans la console"""
        print("🔧 JAVASCRIPT À EXÉCUTER (Console navigateur) :")
        print("```javascript")
        print(js_code)
        print("```\n")

    def verify_ssh(self, description: str, command: str) -> bool:
        """Exécute une vérification SSH"""
        print(f"🔍 Vérification : {description}")
        result = self.execute_ssh_command(description, command)
        return result["success"]

    def execute_ssh_command(self, description: str, command: str) -> Dict:
        """Exécute une commande SSH/DDEV et retourne le résultat"""
        try:
            result = subprocess.run(
                f'wsl -d Ubuntu bash -c "{command}"',
                shell=True,
                capture_output=True,
                text=True,
                timeout=30,
            )

            success = result.returncode == 0

            if success:
                self.log_success(f"{description} → OK")
            else:
                self.log_error(f"{description} → ERREUR: {result.stderr}")

            return {
                "success": success,
                "output": result.stdout.strip(),
                "error": result.stderr.strip(),
            }
        except subprocess.TimeoutExpired:
            self.log_error(f"{description} → TIMEOUT")
            return {"success": False, "error": "Timeout"}
        except Exception as e:
            self.log_error(f"{description} → EXCEPTION: {str(e)}")
            return {"success": False, "error": str(e)}

    def get_wp_option(self, option_name: str) -> Optional[str]:
        """Récupère une option WordPress via WP-CLI"""
        cmd = f"cd ~/projects/tb-wp-dev && ddev wp option get {option_name} --format=json"
        result = self.execute_ssh_command(f"Get option {option_name}", cmd)
        
        if result["success"]:
            try:
                return json.loads(result["output"])
            except json.JSONDecodeError:
                return result["output"]
        return None

    def collect_observations(self, questions: List[str]) -> List[Dict]:
        """Collecte les observations utilisateur"""
        observations = []
        print("💭 OBSERVATIONS UTILISATEUR :")
        for question in questions:
            print(f"\n❓ {question}")
            response = input("   Réponse (oui/non/commentaire) : ")
            observations.append(
                {
                    "question": question,
                    "response": response,
                    "timestamp": datetime.now().isoformat(),
                }
            )
        self.observations.extend(observations)
        return observations

    def wait_user_confirmation(self, message: str):
        """Attend confirmation utilisateur"""
        print(f"\n⏸️  {message}")
        input("   Appuyez sur Entrée pour continuer...")

    def log_success(self, message: str):
        """Log un succès"""
        print(f"✅ {message}")
        self.logs.append({"type": "success", "message": message, "time": time.time()})

    def log_error(self, message: str):
        """Log une erreur"""
        print(f"❌ {message}")
        self.logs.append({"type": "error", "message": message, "time": time.time()})

    def log_info(self, message: str):
        """Log une info"""
        print(f"ℹ️  {message}")
        self.logs.append({"type": "info", "message": message, "time": time.time()})

    def log_warning(self, message: str):
        """Log un avertissement"""
        print(f"⚠️  {message}")
        self.logs.append({"type": "warning", "message": message, "time": time.time()})

    def get_duration(self) -> float:
        """Retourne la durée du test en secondes"""
        return time.time() - self.start_time

    def get_phases_summary(self) -> List[Dict]:
        """Retourne le résumé des phases"""
        return self.phases

    def get_all_observations(self) -> List[Dict]:
        """Retourne toutes les observations"""
        return self.observations

    def calculate_success_rate(self) -> float:
        """Calcule le taux de succès basé sur les logs"""
        success_logs = [log for log in self.logs if log["type"] == "success"]
        error_logs = [log for log in self.logs if log["type"] == "error"]
        total = len(success_logs) + len(error_logs)
        return (len(success_logs) / total * 100) if total > 0 else 0

    def save_debug_snapshot(self, data: Dict):
        """Sauvegarde un snapshot de debug"""
        if self.debug_mode:
            filename = f"tests/E2E/reports/debug_{self.test_id}_{int(time.time())}.json"
            with open(filename, "w", encoding="utf-8") as f:
                json.dump(data, f, indent=2)
            self.log_info(f"Debug snapshot sauvegardé : {filename}")

    def save_markdown_report(self, report: Dict):
        """Sauvegarde le rapport final en Markdown"""
        filename = f"tests/E2E/reports/{self.test_id}_{datetime.now().strftime('%Y%m%d_%H%M%S')}.md"

        content = f"""# {self.test_name}

**Test ID** : {self.test_id}  
**Date** : {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}  
**Durée** : {report['duration']:.2f}s  
**Taux de succès** : {report['success_rate']:.1f}%

## Description

{self.description}

## Phases Exécutées

"""
        for phase in report["phases"]:
            content += f"- {phase['name']}\n"

        content += "\n## Observations\n\n"
        for obs in report["observations"]:
            content += f"- **Q**: {obs['question']}\n"
            content += f"  **R**: {obs['response']}\n\n"

        content += "\n## Logs\n\n```\n"
        for log in self.logs:
            timestamp = datetime.fromtimestamp(log["time"]).strftime("%H:%M:%S")
            content += f"[{timestamp}] [{log['type'].upper()}] {log['message']}\n"
        content += "```\n"

        # Recommendations
        success_rate = report["success_rate"]
        content += "\n## Recommandations\n\n"
        if success_rate >= 90:
            content += "✅ **Test réussi** - Le workflow fonctionne comme prévu.\n"
        elif success_rate >= 70:
            content += "⚠️ **Test partiellement réussi** - Quelques problèmes mineurs à corriger.\n"
        else:
            content += "❌ **Test échoué** - Des problèmes critiques nécessitent une attention immédiate.\n"

        with open(filename, "w", encoding="utf-8") as f:
            f.write(content)

        print(f"\n📄 Rapport sauvegardé : {filename}")

    def print_summary(self):
        """Affiche le résumé final"""
        duration = self.get_duration()
        success_rate = self.calculate_success_rate()

        print(f"\n{'=' * 60}")
        print(f"  RÉSUMÉ : {self.test_name}")
        print(f"{'=' * 60}")
        print(f"Durée : {duration:.2f}s ({duration / 60:.1f} min)")
        print(f"Taux de succès : {success_rate:.1f}%")
        print(f"Phases : {len(self.phases)}")
        print(f"Observations : {len(self.observations)}")
        print(f"Succès : {len([l for l in self.logs if l['type'] == 'success'])}")
        print(f"Erreurs : {len([l for l in self.logs if l['type'] == 'error'])}")
        print(f"{'=' * 60}\n")

    def run(self):
        """Méthode abstraite à surcharger pour exécuter le test"""
        raise NotImplementedError(
            "La méthode run() doit être implémentée dans la classe enfant"
        )

