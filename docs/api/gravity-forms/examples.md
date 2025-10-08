# 📘 Exemple d'intégration - CalculationRetriever

## 🎯 Objectif

Ce document montre comment utiliser `CalculationRetriever` pour récupérer la valeur calculée du score de positionnement lors du passage de la page 15 à la page 30 dans un formulaire Gravity Forms.

---

## 📋 Code d'intégration

### Option 1 : Hook dans le plugin principal

Ajouter ce code dans `src/Form/GravityForms/SubmissionHandler.php` ou créer une nouvelle classe dédiée.

```php
<?php
namespace WcQualiopiFormation\Form\GravityForms;

use WcQualiopiFormation\Helpers\LoggingHelper;

/**
 * Gestionnaire de passage de pages pour le tunnel Qualiopi
 */
class PageTransitionHandler {

	/**
	 * Instance CalculationRetriever
	 *
	 * @var CalculationRetriever
	 */
	private $calculation_retriever;

	/**
	 * Constructeur
	 *
	 * @param CalculationRetriever $calculation_retriever Instance du retriever.
	 */
	public function __construct( CalculationRetriever $calculation_retriever ) {
		$this->calculation_retriever = $calculation_retriever;
		$this->init_hooks();
	}

	/**
	 * Initialise les hooks Gravity Forms
	 */
	private function init_hooks() {
		// Hook avant le changement de page
		add_filter( 'gform_save_field_value', array( $this, 'on_field_save' ), 10, 5 );

		// Hook après validation de page
		add_action( 'gform_post_paging', array( $this, 'on_page_transition' ), 10, 3 );
	}

	/**
	 * Appelé lors du passage d'une page à l'autre
	 *
	 * @param array $form Formulaire GF.
	 * @param int   $source_page_number Page source.
	 * @param int   $current_page_number Page cible.
	 */
	public function on_page_transition( $form, $source_page_number, $current_page_number ) {
		LoggingHelper::info( '[PageTransition] Passage de page détecté', array(
			'form_id'      => $form['id'],
			'from_page'    => $source_page_number,
			'to_page'      => $current_page_number,
		) );

		// Vérifier si c'est la transition page 15 → page 30
		if ( $source_page_number === 15 && $current_page_number === 30 ) {
			$this->handle_test_completion( $form );
		}
	}

	/**
	 * Gère la complétion du test de positionnement
	 *
	 * @param array $form Formulaire GF.
	 */
	private function handle_test_completion( $form ) {
		// Récupérer l'entrée en cours (submission partielle)
		$submission_data = \GFFormsModel::get_current_lead();

		if ( ! $submission_data ) {
			LoggingHelper::error( '[PageTransition] Impossible de récupérer les données de soumission' );
			return;
		}

		// Récupérer la valeur calculée du score
		$score = $this->calculation_retriever->get_calculated_value(
			$form['id'],
			$submission_data,
			27  // ID du champ de calcul (score de positionnement)
		);

		if ( $score === false ) {
			LoggingHelper::error( '[PageTransition] Erreur lors de la récupération du score' );
			return;
		}

		LoggingHelper::info( '[PageTransition] Score de positionnement récupéré', array(
			'form_id' => $form['id'],
			'score'   => $score,
		) );

		// Utiliser le score pour déterminer le parcours
		$this->determine_training_path( $score, $submission_data );
	}

	/**
	 * Détermine le parcours de formation selon le score
	 *
	 * @param float $score Score de positionnement.
	 * @param array $submission_data Données de soumission.
	 */
	private function determine_training_path( $score, $submission_data ) {
		// Logique métier : déterminer le parcours selon le score
		// Par exemple :
		// - Score < 30 : Parcours débutant
		// - Score 30-60 : Parcours intermédiaire
		// - Score > 60 : Parcours avancé

		$path = '';
		if ( $score < 30 ) {
			$path = 'debutant';
		} elseif ( $score < 60 ) {
			$path = 'intermediaire';
		} else {
			$path = 'avance';
		}

		LoggingHelper::info( '[PageTransition] Parcours déterminé', array(
			'score' => $score,
			'path'  => $path,
		) );

		// Stocker le parcours dans les données de soumission
		// ou rediriger vers une page spécifique
		// ou afficher un message conditionnel

		// Exemple : Stocker dans une méta de l'entrée
		// (à implémenter selon les besoins)
	}
}
```

---

## 🔗 Intégration dans FormManager

Ajouter dans `FormManager.php` :

```php
/**
 * Instance PageTransitionHandler
 *
 * @var PageTransitionHandler
 */
private $page_transition_handler;

// Dans init_components() :
$this->page_transition_handler = new PageTransitionHandler( $this->calculation_retriever );
```

---

## 📝 Utilisation directe (sans handler)

Si vous voulez utiliser `CalculationRetriever` directement dans un autre contexte :

```php
// Récupérer l'instance depuis FormManager
$form_manager = // ... récupérer l'instance de FormManager
$calculation_retriever = $form_manager->get_calculation_retriever();

// Récupérer la valeur calculée
$form_id = 1;
$entry = \GFAPI::get_entry( $entry_id );
$score = $calculation_retriever->get_calculated_value( $form_id, $entry, 27 );

if ( $score !== false ) {
    echo "Score de positionnement : " . esc_html( $score );
}
```

---

## 🧪 Test manuel

### 1. Accéder au formulaire

1. Se rendre sur la page du formulaire de test de positionnement
2. Remplir les pages 1 à 15
3. Observer les logs lors du clic sur "Suivant" (page 15 → 30)

### 2. Vérifier les logs

```bash
# Depuis DDEV
ddev exec tail -f web/wp-content/debug.log | grep "CalculationRetriever"

# Ou utiliser le script de logs
python Scripts/quick_logs.py
```

Vous devriez voir :

```json
{
  "message": "[CalculationRetriever] Début récupération valeur calculée",
  "form_id": 1,
  "field_id": 27,
  "level": "info"
}
{
  "message": "[CalculationRetriever] Valeur calculée récupérée avec succès",
  "form_id": 1,
  "field_id": 27,
  "value": 42.5,
  "level": "info"
}
```

### 3. Test unitaire (futur)

```php
<?php
namespace WcQualiopiFormation\Tests\Form\GravityForms;

use PHPUnit\Framework\TestCase;
use WcQualiopiFormation\Form\GravityForms\CalculationRetriever;
use WcQualiopiFormation\Form\GravityForms\FieldMapper;

class CalculationRetrieverTest extends TestCase {

	public function test_get_calculated_value_returns_float() {
		$field_mapper = new FieldMapper();
		$retriever = new CalculationRetriever( $field_mapper );

		$form_id = 1;
		$entry = array(
			'id'  => 123,
			'27'  => '42.5',  // Valeur calculée
		);

		$result = $retriever->get_calculated_value( $form_id, $entry, 27 );

		$this->assertIsFloat( $result );
		$this->assertEquals( 42.5, $result );
	}

	public function test_get_calculated_value_returns_false_on_invalid_form() {
		$field_mapper = new FieldMapper();
		$retriever = new CalculationRetriever( $field_mapper );

		$result = $retriever->get_calculated_value( 999999, array(), 27 );

		$this->assertFalse( $result );
	}
}
```

---

## 🎯 Prochaines étapes

1. **Implémenter PageTransitionHandler** dans le plugin si nécessaire
2. **Tester le flux complet** sur l'environnement de développement
3. **Ajouter la logique métier** pour déterminer le parcours de formation
4. **Créer des tests automatisés** pour valider le fonctionnement
5. **Documenter l'API** dans le README principal du plugin

---

## 📚 Références

- **Hook Gravity Forms** : `gform_post_paging` - [Documentation officielle](https://docs.gravityforms.com/gform_post_paging/)
- **Récupération données** : `GFFormsModel::get_current_lead()` - [Documentation](https://docs.gravityforms.com/gfformsmodel-get_current_lead/)
- **Cahier des charges** : `Dev/GF/cahier_des_charges_fonction_recuperation_valeur_calculee.md`
- **Architecture plugin** : `Plugins/wc_qualiopi_formation/README.md`

---

**Date de création** : 7 octobre 2025  
**Version** : 1.0.0
