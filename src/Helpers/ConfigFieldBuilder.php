<?php
/**
 * ConfigFieldBuilder - Builder pour champs de configuration admin WordPress
 *
 * Simplifie la création de champs de configuration dans l'admin WordPress
 * avec sanitization automatique et personnalisable.
 *
 * @package WcQualiopiFormation\Helpers
 * @since 1.0.0
 */

namespace WcQualiopiFormation\Helpers;

use WcQualiopiFormation\Core\Constants;
use WcQualiopiFormation\Helpers\LoggingHelper;
use WcQualiopiFormation\Admin\AdminUi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConfigFieldBuilder
 * Builder pour champs de configuration admin
 * RESPONSABILITÉ UNIQUE : Faciliter la création de champs admin
 */
class ConfigFieldBuilder {

/**
	 * Sections enregistrées
	 *
	 * @var array
	 */
	private $sections = array();

	/**
	 * Champs enregistrés
	 *
	 * @var array
	 */
	private $fields = array();

	/**
	 * Constructeur
	 *
	 * @param Logger $logger Instance du logger.
	 */
	public function __construct() {
				LoggingHelper::debug( '[ConfigFieldBuilder] Initialized' );
	}

	/**
	 * Ajoute une section de configuration
	 *
	 * @param string $id ID unique de la section.
	 * @param string $title Titre de la section.
	 * @param string $description Description optionnelle.
	 * @return self Pour chaînage.
	 */
	public function add_section( $id, $title, $description = '' ) {
		$this->sections[ $id ] = array(
			'id' => $id,
			'title' => $title,
			'description' => $description,
		);

		LoggingHelper::debug( '[ConfigFieldBuilder] Section ajoutee', array(
			'section_id' => $id,
			'title' => $title,
		) );

		return $this;
	}

	/**
	 * Ajoute un champ à une section
	 *
	 * @param string $section_id ID de la section.
	 * @param string $field_id ID unique du champ.
	 * @param string $type Type de champ (text, textarea, select, checkbox, etc.).
	 * @param string $label Label du champ.
	 * @param array  $args Arguments supplémentaires (description, options, etc.).
	 * @return self Pour chaînage.
	 */
	public function add_field( $section_id, $field_id, $type, $label, $args = array() ) {
		if ( ! isset( $this->sections[ $section_id ] ) ) {
			LoggingHelper::warning( '[ConfigFieldBuilder] Section inexistante pour champ', array(
				'section_id' => $section_id,
				'field_id' => $field_id,
			) );
			return $this;
		}

		$default_args = array(
			'id' => $field_id,
			'type' => $type,
			'label' => $label,
			'description' => '',
			'placeholder' => '',
			'default' => '',
			'sanitize_callback' => null, // Callback personnalisé
			'options' => array(), // Pour select, radio, etc.
			'class' => 'regular-text',
		);

		$field_data = \array_merge( $default_args, $args );
		$field_data['section_id'] = $section_id;

		$this->fields[ $field_id ] = $field_data;

		LoggingHelper::debug( '[ConfigFieldBuilder] Champ ajoute', array(
			'section_id' => $section_id,
			'field_id' => $field_id,
			'type' => $type,
		) );

		return $this;
	}

	/**
	 * Affiche une section avec ses champs
	 *
	 * @param string $section_id ID de la section.
	 * @param array  $values Valeurs actuelles des champs.
	 * @return void
	 */
	public function render_section( $section_id, $values = array() ) {
		if ( ! isset( $this->sections[ $section_id ] ) ) {
			LoggingHelper::warning( '[ConfigFieldBuilder] Tentative render section inexistante', array(
				'section_id' => $section_id,
			) );
			return;
		}

		$section = $this->sections[ $section_id ];

		// Utiliser AdminUi pour le rendu unifié
		echo AdminUi::section_start( $section['title'] );
		
		if ( ! empty( $section['description'] ) ) {
			echo '<p class="wcqf-help">' . esc_html( $section['description'] ) . '</p>';
		}

		foreach ( $this->fields as $field ) {
			if ( $field['section_id'] === $section_id ) {
				$value = $values[ $field['id'] ] ?? $field['default'];
				$input_html = $this->render_field_input( $field, $value );
				echo AdminUi::field_row( $field['label'], $input_html, $field['description'] ?? null );
			}
		}

		echo AdminUi::section_end();

		LoggingHelper::debug( '[ConfigFieldBuilder] Section rendue avec AdminUi', array(
			'section_id' => $section_id,
			'fields_count' => count( array_filter( $this->fields, function( $f ) use ( $section_id ) {
				return $f['section_id'] === $section_id;
			} ) ),
		) );
	}

	/**
	 * Génère le HTML d'un champ pour AdminUi
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @return string HTML du champ.
	 */
	public function render_field_input( $field, $value ) {
		$field_id = $field['id'];
		$field_name = 'wcqf_settings[' . $field_id . ']';

		ob_start();
		switch ( $field['type'] ) {
			case 'text':
				$this->render_text_field( $field, $value, $field_name );
				break;
			case 'password':
				$this->render_password_field( $field, $value, $field_name );
				break;
			case 'textarea':
				$this->render_textarea_field( $field, $value, $field_name );
				break;
			case 'select':
				$this->render_select_field( $field, $value, $field_name );
				break;
			case 'checkbox':
				$this->render_checkbox_field( $field, $value, $field_name );
				break;
			default:
				$this->render_text_field( $field, $value, $field_name );
		}
		return ob_get_clean();
	}

	/**
	 * Affiche un champ individuel (méthode legacy pour compatibilité)
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @return void
	 */
	public function render_field( $field, $value ) {
		$field_id = $field['id'];
		$field_name = 'wcqf_settings[' . $field_id . ']';

		?>
		<tr>
			<th scope="row">
				<label for="<?php echo \esc_attr( $field_id ); ?>">
					<?php echo \esc_html( $field['label'] ); ?>
				</label>
			</th>
			<td>
				<?php
				switch ( $field['type'] ) {
					case 'text':
						$this->render_text_field( $field, $value, $field_name );
						break;
					case 'password':
						$this->render_password_field( $field, $value, $field_name );
						break;
					case 'textarea':
						$this->render_textarea_field( $field, $value, $field_name );
						break;
					case 'select':
						$this->render_select_field( $field, $value, $field_name );
						break;
					case 'checkbox':
						$this->render_checkbox_field( $field, $value, $field_name );
						break;
					default:
						$this->render_text_field( $field, $value, $field_name );
				}

				if ( ! empty( $field['description'] ) ) {
					echo '<p class="description">' . \esc_html( $field['description'] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Affiche un champ texte
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @param string $name Nom du champ.
	 * @return void
	 */
	private function render_text_field( $field, $value, $name ) {
		?>
		<input 
			type="text" 
			id="<?php echo \esc_attr( $field['id'] ); ?>" 
			name="<?php echo \esc_attr( $name ); ?>" 
			value="<?php echo \esc_attr( $value ); ?>" 
			placeholder="<?php echo \esc_attr( $field['placeholder'] ); ?>"
			class="<?php echo \esc_attr( $field['class'] ); ?>"
		/>
		<?php
	}

	/**
	 * Affiche un champ password
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @param string $name Nom du champ.
	 * @return void
	 */
	private function render_password_field( $field, $value, $name ) {
		?>
		<input 
			type="password" 
			id="<?php echo \esc_attr( $field['id'] ); ?>" 
			name="<?php echo \esc_attr( $name ); ?>" 
			value="<?php echo \esc_attr( $value ); ?>" 
			placeholder="<?php echo \esc_attr( $field['placeholder'] ); ?>"
			class="<?php echo \esc_attr( $field['class'] ); ?>"
			autocomplete="off"
		/>
		<?php
	}

	/**
	 * Affiche un champ textarea
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @param string $name Nom du champ.
	 * @return void
	 */
	private function render_textarea_field( $field, $value, $name ) {
		?>
		<textarea 
			id="<?php echo \esc_attr( $field['id'] ); ?>" 
			name="<?php echo \esc_attr( $name ); ?>" 
			placeholder="<?php echo \esc_attr( $field['placeholder'] ); ?>"
			class="<?php echo \esc_attr( $field['class'] ); ?>"
			rows="5"
		><?php echo \esc_textarea( $value ); ?></textarea>
		<?php
	}

	/**
	 * Affiche un champ select
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @param string $name Nom du champ.
	 * @return void
	 */
	private function render_select_field( $field, $value, $name ) {
		?>
		<select 
			id="<?php echo \esc_attr( $field['id'] ); ?>" 
			name="<?php echo \esc_attr( $name ); ?>"
			class="<?php echo \esc_attr( $field['class'] ); ?>"
		>
			<?php foreach ( $field['options'] as $option_value => $option_label ) : ?>
				<option 
					value="<?php echo \esc_attr( $option_value ); ?>"
					<?php \selected( $value, $option_value ); ?>
				>
					<?php echo \esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Affiche un champ checkbox
	 *
	 * @param array  $field Configuration du champ.
	 * @param mixed  $value Valeur actuelle.
	 * @param string $name Nom du champ.
	 * @return void
	 */
	private function render_checkbox_field( $field, $value, $name ) {
		?>
		<label>
			<input 
				type="checkbox" 
				id="<?php echo \esc_attr( $field['id'] ); ?>" 
				name="<?php echo \esc_attr( $name ); ?>" 
				value="1"
				<?php \checked( $value, 1 ); ?>
			/>
			<?php if ( ! empty( $field['checkbox_label'] ) ) : ?>
				<span><?php echo \esc_html( $field['checkbox_label'] ); ?></span>
			<?php endif; ?>
		</label>
		<?php
	}

	/**
	 * Sanitize une valeur selon le type de champ
	 *
	 * @param string $field_id ID du champ.
	 * @param mixed  $value Valeur à sanitizer.
	 * @return mixed Valeur sanitizée.
	 */
	public function sanitize_field( $field_id, $value ) {
		if ( ! isset( $this->fields[ $field_id ] ) ) {
			LoggingHelper::warning( '[ConfigFieldBuilder] Champ inexistant pour sanitization', array(
				'field_id' => $field_id,
			) );
			return '';
		}

		$field = $this->fields[ $field_id ];

		// Callback personnalisé prioritaire
		if ( ! empty( $field['sanitize_callback'] ) && \is_callable( $field['sanitize_callback'] ) ) {
			$sanitized = \call_user_func( $field['sanitize_callback'], $value );
			
			LoggingHelper::debug( '[ConfigFieldBuilder] Sanitization personnalisee', array(
				'field_id' => $field_id,
			) );
			
			return $sanitized;
		}

		// Sanitization automatique par type
		switch ( $field['type'] ) {
			case 'text':
			case 'password':
				$sanitized = \sanitize_text_field( $value );
				break;
			case 'textarea':
				$sanitized = \sanitize_textarea_field( $value );
				break;
			case 'url':
				$sanitized = \esc_url_raw( $value );
				break;
			case 'email':
				$sanitized = \sanitize_email( $value );
				break;
			case 'checkbox':
				$sanitized = ! empty( $value ) ? 1 : 0;
				break;
			default:
				$sanitized = \sanitize_text_field( $value );
		}

		LoggingHelper::debug( '[ConfigFieldBuilder] Sanitization automatique', array(
			'field_id' => $field_id,
			'type' => $field['type'],
		) );

		return $sanitized;
	}

	/**
	 * Récupère tous les champs d'une section
	 *
	 * @param string $section_id ID de la section.
	 * @return array Liste des champs.
	 */
	public function get_section_fields( $section_id ) {
		return \array_filter( $this->fields, function( $field ) use ( $section_id ) {
			return $field['section_id'] === $section_id;
		} );
	}
}

