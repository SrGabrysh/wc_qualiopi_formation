# API Gravity Forms - Documentation

Documentation technique des composants d'intégration avec Gravity Forms.

## 📚 Guides disponibles

### [PageTransitionHandler](page-transition-handler.md)

Gestion automatique des transitions de pages dans les formulaires Gravity Forms.

**Fonctionnalités** :

- Interception du passage page 15 → 30
- Récupération automatique du score de positionnement
- Détermination du parcours (refused, reinforced, admitted)
- Déclenchement action WordPress `wcqf_test_completed`

**Utilisation** : Déjà intégré et actif, aucune configuration nécessaire.

---

### [CalculationRetriever](calculation-retriever.md)

Récupération de valeurs calculées dans les champs Gravity Forms.

**Fonctionnalités** :

- Extraction de valeurs de champs calculés
- Validation et sanitization automatique
- Support du système de mapping
- Gestion d'erreurs complète

**Utilisation** : Composant réutilisable pour récupérer des valeurs calculées.

---

### [Exemples d'intégration](examples.md)

Exemples de code et cas d'usage pratiques.

**Contenu** :

- Utilisation de `CalculationRetriever`
- Extension de `PageTransitionHandler`
- Classe `PageTransitionHandler` complète
- Cas d'usage réels

---

## 🎯 Action WordPress

```php
/**
 * Action déclenchée lors de la complétion du test de positionnement
 *
 * @param float $score Score obtenu (0-20)
 * @param string $path Parcours déterminé ('refused', 'reinforced', 'admitted')
 * @param array $submission_data Données de soumission Gravity Forms
 * @param array $form Formulaire Gravity Forms complet
 */
add_action( 'wcqf_test_completed', function( $score, $path, $submission_data, $form ) {
    // Votre logique personnalisée
}, 10, 4 );
```

---

## 📖 Références

- [Documentation Gravity Forms officielle](https://docs.gravityforms.com/)
- [Hook gform_post_paging](https://docs.gravityforms.com/gform_post_paging/)
- [GFAPI Documentation](https://docs.gravityforms.com/api-functions/)

---

**Plugin** : wc_qualiopi_formation  
**Version** : 1.0.0-dev.0  
**Dernière mise à jour** : 8 octobre 2025
