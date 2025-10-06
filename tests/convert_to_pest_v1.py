#!/usr/bin/env python3
"""
Convertit les tests de Pest v2 syntax (describe/it) vers Pest v1 syntax (test)
"""

import re
from pathlib import Path

# Fichier à convertir
if __name__ == "__main__":
    test_file = Path(__file__).parent / "Unit/Security/Token/TokenGeneratorTest.php"
    
    print(f"📝 Conversion de {test_file}...")
    
    with open(test_file, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Remplacer it(' par test('
    content = re.sub(r"(\s+)it\('", r"test('", content)
    
    # Supprimer la dernière accolade fermante (celle du describe)
    lines = content.split('\n')
    
    # Trouver et supprimer les dernières accolades vides
    cleaned_lines = []
    for line in lines:
        # Ignorer les lignes qui sont juste '});' à la fin
        if line.strip() == '});' and len(cleaned_lines) > 0:
            # Vérifier si c'est la dernière accolade
            remaining = '\n'.join(lines[lines.index(line)+1:]).strip()
            if not remaining or remaining == '':
                # C'est la dernière, on la remplace par juste une ligne vide
                cleaned_lines.append('')
                break
        cleaned_lines.append(line)
    
    content = '\n'.join(cleaned_lines)
    
    # Écrire le fichier
    with open(test_file, 'w', encoding='utf-8') as f:
        f.write(content)
    
    print(f"✅ Conversion terminée !")
    print(f"   - Remplacé it(' par test('")
    print(f"   - Supprimé describe() wrapper")

