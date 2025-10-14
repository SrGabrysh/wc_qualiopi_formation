<?php
/**
 * AdminUi - Composants de rendu réutilisables pour l'admin
 *
 * @package WcQualiopiFormation\Admin
 */

namespace WcQualiopiFormation\Admin;

use WcQualiopiFormation\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Classe utilitaire pour unifier le rendu UI dans l'admin du plugin
 */
final class AdminUi {

	public static function section_start( string $title, ?string $id = null ): string {
		$id_attr = $id ? ' id="' . esc_attr( $id ) . '"' : '';
		return '<div class="wcqf-section"' . $id_attr . '><h2 class="wcqf-section__title">' . esc_html( $title ) . '</h2>';
	}

	public static function section_end(): string {
		return '</div>';
	}

	public static function field_row( string $label, string $input_html, ?string $help = null ): string {
		$help_html = $help ? '<p class="wcqf-help">' . esc_html( $help ) . '</p>' : '';
		return '<div class="wcqf-field">'
			. '<label class="wcqf-field__label">' . esc_html( $label ) . '</label>'
			. '<div class="wcqf-field__control">' . $input_html . $help_html . '</div>'
		. '</div>';
	}

	public static function button( string $label, string $variant = 'primary', array $attrs = array() ): string {
		$classes = array( 'wcqf-btn' );
		$classes[] = ( 'secondary' === $variant ) ? 'wcqf-btn--secondary' : 'wcqf-btn--primary';
		
		// Définir type="button" par défaut si non spécifié
		if ( ! isset( $attrs['type'] ) ) {
			$attrs['type'] = 'button';
		}
		
		$attr_str = self::attrs( $attrs );
		return '<button class="' . esc_attr( implode( ' ', $classes ) ) . '" ' . $attr_str . '>' . esc_html( $label ) . '</button>';
	}

	public static function button_primary( string $label, string $name = '', array $extra_attrs = array() ): string {
		$attrs = array( 'type' => 'submit' );
		if ( ! empty( $name ) ) {
			$attrs['name'] = $name;
		}
		// Fusionner les attributs supplémentaires
		$attrs = array_merge( $attrs, $extra_attrs );
		return self::button( $label, 'primary', $attrs );
	}

	public static function notice( string $message, string $type = 'success' ): string {
		$type = in_array( $type, array( 'success', 'info', 'warning', 'error' ), true ) ? $type : 'info';
		return '<div class="wcqf-notice wcqf-notice--' . esc_attr( $type ) . '">' . esc_html( $message ) . '</div>';
	}

	public static function select( string $name, array $options, $selected = '', array $attrs = array() ): string {
		$attr_str = self::attrs( $attrs );
		$html = '<select name="' . esc_attr( $name ) . '" ' . $attr_str . '>';
		
		foreach ( $options as $value => $label ) {
			// Comparaison non-stricte pour gérer int et string (fix: dropdown GF se réinitialise)
			$is_selected = ( (string) $value === (string) $selected ) ? ' selected="selected"' : '';
			$html .= '<option value="' . esc_attr( $value ) . '"' . $is_selected . '>' . esc_html( $label ) . '</option>';
		}
		
		$html .= '</select>';
		return $html;
	}

	public static function table_start( array $headers = array() ): string {
		$html = '<table class="wcqf-table"><thead><tr>';
		foreach ( $headers as $header ) {
			$html .= '<th>' . esc_html( $header ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		return $html;
	}

	public static function table_end(): string {
		return '</tbody></table>';
	}

	public static function table_row( array $cells ): string {
		$html = '<tr>';
		foreach ( $cells as $cell ) {
			$html .= '<td>' . $cell . '</td>';
		}
		$html .= '</tr>';
		return $html;
	}

	/**
	 * Génère une icône tooltip avec bulle d'aide
	 *
	 * @param string $content Contenu du tooltip (HTML autorisé).
	 * @return string HTML du tooltip.
	 */
	public static function tooltip( string $content ): string {
		return '<span class="wcqf-tooltip">
			<span class="wcqf-tooltip__icon">?</span>
			<span class="wcqf-tooltip__content">' . $content . '</span>
		</span>';
	}

	/**
	 * Génère un champ avec label ET tooltip
	 *
	 * @param string      $label      Label du champ.
	 * @param string      $input_html HTML de l'input.
	 * @param string|null $help       Texte d'aide en dessous du champ.
	 * @param string|null $tooltip    Contenu du tooltip (à côté du label).
	 * @return string HTML complet.
	 */
	public static function field_row_with_tooltip( string $label, string $input_html, ?string $help = null, ?string $tooltip = null ): string {
		$help_html = $help ? '<p class="wcqf-help">' . esc_html( $help ) . '</p>' : '';
		$tooltip_html = $tooltip ? ' ' . self::tooltip( $tooltip ) : '';
		
		return '<div class="wcqf-field">'
			. '<label class="wcqf-field__label">' . esc_html( $label ) . $tooltip_html . '</label>'
			. '<div class="wcqf-field__control">' . $input_html . $help_html . '</div>'
		. '</div>';
	}

	private static function attrs( array $attrs ): string {
		$out = array();
		foreach ( $attrs as $key => $value ) {
			$out[] = esc_attr( (string) $key ) . '="' . esc_attr( (string) $value ) . '"';
		}
		return implode( ' ', $out );
	}
}