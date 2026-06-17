<?php
/**
 * Shared meta-field rendering and persistence.
 *
 * Used by both the bike and repair-job meta boxes so field handling lives in
 * one place. Fields are declared as arrays ( key, label, type, options, … );
 * see Bike_Fields / Repair_Job_Fields. All output is escaped and all input is
 * sanitized by type.
 *
 * @package TossaWorkshop
 */

namespace TossaWorkshop;

defined( 'ABSPATH' ) || exit;

/**
 * Stateless helpers for declarative meta fields.
 */
class Field_Kit {

	/** Base POST key all fields nest under. */
	const POST_KEY = 'tcw_field';

	/**
	 * Render a field as a form-table row.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $field   Field definition.
	 * @return void
	 */
	public static function render_row( $post_id, array $field ) {
		$key   = $field['key'];
		$id    = self::POST_KEY . '_' . $key;
		$name  = self::POST_KEY . '[' . $key . ']';
		$value = get_post_meta( $post_id, $key, true );
		if ( '' === $value && isset( $field['default'] ) ) {
			$value = $field['default'];
		}
		$req = ! empty( $field['required'] ) ? ' <span class="description">' . esc_html__( '(required)', 'tossa-workshop' ) . '</span>' : '';

		echo '<tr>';
		echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . wp_kses_post( $req ) . '</label></th>';
		echo '<td>';
		self::render_input( $id, $name, $value, $field );
		if ( ! empty( $field['help'] ) ) {
			echo '<p class="description">' . esc_html( $field['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Render just the input control for a field.
	 *
	 * @param string $id    Field id.
	 * @param string $name  Field name.
	 * @param mixed  $value Stored value.
	 * @param array  $field Field definition.
	 * @return void
	 */
	public static function render_input( $id, $name, $value, array $field ) {
		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s" rows="4" class="large-text">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_textarea( (string) $value )
				);
				break;

			case 'select':
				$class = ! empty( $field['class'] ) ? ' class="' . esc_attr( $field['class'] ) . '"' : '';
				printf( '<select id="%1$s" name="%2$s"%3$s>', esc_attr( $id ), esc_attr( $name ), $class ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — $class pre-escaped.
				if ( empty( $field['required'] ) ) {
					echo '<option value="">' . esc_html__( '— Select —', 'tossa-workshop' ) . '</option>';
				}
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					printf(
						'<option value="%1$s"%2$s>%3$s</option>',
						esc_attr( $opt_val ),
						selected( $value, $opt_val, false ),
						esc_html( $opt_label )
					);
				}
				echo '</select>';
				break;

			case 'checkbox':
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( $value, '1', false ),
					esc_html__( 'Yes', 'tossa-workshop' )
				);
				break;

			case 'multicheck':
				$selected = is_array( $value ) ? $value : array();
				echo '<fieldset>';
				foreach ( $field['options'] as $opt_val => $opt_label ) {
					printf(
						'<label style="display:inline-block;margin:0 12px 4px 0;"><input type="checkbox" name="%1$s[]" value="%2$s"%3$s /> %4$s</label>',
						esc_attr( $name ),
						esc_attr( $opt_val ),
						checked( in_array( (string) $opt_val, array_map( 'strval', $selected ), true ), true, false ),
						esc_html( $opt_label )
					);
				}
				echo '</fieldset>';
				break;

			case 'number':
				printf(
					'<input type="number" step="any" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'decimal':
				printf(
					'<input type="number" step="0.01" min="0" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'date':
				printf(
					'<input type="date" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'datetime':
				printf(
					'<input type="datetime-local" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'email':
				printf(
					'<input type="email" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'url':
				printf(
					'<input type="url" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			case 'user':
				wp_dropdown_users(
					array(
						'name'              => $name,
						'id'                => $id,
						'selected'          => (int) $value,
						'show_option_none'  => __( '— None —', 'tossa-workshop' ),
						'option_none_value' => 0,
					)
				);
				break;

			case 'attachment':
				self::render_attachment( $id, $name, (int) $value );
				break;

			case 'gallery':
				self::render_gallery( $name, $value );
				break;

			case 'text':
			default:
				printf(
					'<input type="text" id="%1$s" name="%2$s" value="%3$s" class="regular-text" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;
		}
	}

	/**
	 * Render a single-attachment picker.
	 *
	 * @param string $id    Field id.
	 * @param string $name  Field name.
	 * @param int    $value Attachment ID.
	 * @return void
	 */
	public static function render_attachment( $id, $name, $value ) {
		$src = $value ? wp_get_attachment_image_url( $value, 'thumbnail' ) : '';
		echo '<div class="tcw-attachment" data-target="' . esc_attr( $id ) . '">';
		echo '<input type="hidden" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
		echo '<span class="tcw-attachment-preview">';
		if ( $src ) {
			echo '<img src="' . esc_url( $src ) . '" alt="" style="max-width:120px;height:auto;border:1px solid #ddd;" />';
		}
		echo '</span> ';
		echo '<button type="button" class="button tcw-attachment-select">' . esc_html__( 'Select image', 'tossa-workshop' ) . '</button> ';
		echo '<button type="button" class="button tcw-attachment-remove"' . ( $value ? '' : ' style="display:none;"' ) . '>' . esc_html__( 'Remove', 'tossa-workshop' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Render the repeatable photo gallery.
	 *
	 * @param string $name  Base field name.
	 * @param mixed  $value Stored array of { id, label }.
	 * @return void
	 */
	public static function render_gallery( $name, $value ) {
		$items = is_array( $value ) ? $value : array();

		// Keep only valid items so rendered indices are contiguous 0..n-1 and
		// data-next-index (= n) never collides with an existing index.
		$items = array_values(
			array_filter(
				$items,
				static function ( $item ) {
					return ! empty( $item['id'] );
				}
			)
		);

		echo '<div class="tcw-gallery">';
		echo '<ul class="tcw-gallery-items" data-next-index="' . esc_attr( (string) count( $items ) ) . '">';
		$i = 0;
		foreach ( $items as $item ) {
			$att_id = (int) $item['id'];
			$label  = isset( $item['label'] ) ? $item['label'] : '';
			$src    = wp_get_attachment_image_url( $att_id, 'thumbnail' );
			echo '<li class="tcw-gallery-item">';
			echo '<input type="hidden" name="' . esc_attr( $name ) . '[' . $i . '][id]" value="' . esc_attr( $att_id ) . '" />';
			if ( $src ) {
				echo '<img src="' . esc_url( $src ) . '" alt="" style="max-width:90px;height:auto;border:1px solid #ddd;" />';
			}
			echo '<input type="text" name="' . esc_attr( $name ) . '[' . $i . '][label]" value="' . esc_attr( $label ) . '" placeholder="' . esc_attr__( 'Label', 'tossa-workshop' ) . '" />';
			echo '<button type="button" class="button-link tcw-gallery-remove">' . esc_html__( 'Remove', 'tossa-workshop' ) . '</button>';
			echo '</li>';
			$i++;
		}
		echo '</ul>';
		echo '<button type="button" class="button tcw-gallery-add" data-name="' . esc_attr( $name ) . '">' . esc_html__( 'Add photos', 'tossa-workshop' ) . '</button>';
		echo '</div>';
	}

	/**
	 * Persist a single field from a pre-unslashed POST array.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $field   Field definition.
	 * @param array $posted  Unslashed POST data under POST_KEY.
	 * @return mixed The stored value (bool for checkbox, array for gallery/multicheck).
	 */
	public static function persist_field( $post_id, array $field, array $posted ) {
		$key = $field['key'];

		switch ( $field['type'] ) {
			case 'gallery':
				$raw = ( isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ) ? $posted[ $key ] : array();
				return self::save_gallery( $post_id, $key, $raw );

			case 'multicheck':
				$raw     = ( isset( $posted[ $key ] ) && is_array( $posted[ $key ] ) ) ? $posted[ $key ] : array();
				$allowed = isset( $field['options'] ) ? array_keys( $field['options'] ) : array();
				$clean   = array();
				foreach ( $raw as $v ) {
					$v = sanitize_text_field( $v );
					if ( in_array( $v, array_map( 'strval', $allowed ), true ) ) {
						$clean[] = $v;
					}
				}
				if ( $clean ) {
					update_post_meta( $post_id, $key, $clean );
				} else {
					delete_post_meta( $post_id, $key );
				}
				return $clean;

			case 'checkbox':
				$checked = ! empty( $posted[ $key ] );
				update_post_meta( $post_id, $key, $checked ? '1' : '' );
				return $checked;

			default:
				$clean = self::sanitize( $field, isset( $posted[ $key ] ) ? $posted[ $key ] : '' );
				update_post_meta( $post_id, $key, $clean );
				return $clean;
		}
	}

	/**
	 * Save the gallery array for a field.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param array  $raw     Raw (unslashed) gallery items.
	 * @return array Cleaned items.
	 */
	public static function save_gallery( $post_id, $key, array $raw ) {
		$clean = array();
		foreach ( $raw as $item ) {
			$id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
			if ( ! $id ) {
				continue;
			}
			$clean[] = array(
				'id'    => $id,
				'label' => isset( $item['label'] ) ? sanitize_text_field( $item['label'] ) : '',
			);
		}

		if ( $clean ) {
			update_post_meta( $post_id, $key, $clean );
		} else {
			delete_post_meta( $post_id, $key );
		}
		return $clean;
	}

	/**
	 * Sanitize a scalar field value by type.
	 *
	 * @param array $field Field definition.
	 * @param mixed $raw   Raw posted value.
	 * @return mixed
	 */
	public static function sanitize( array $field, $raw ) {
		switch ( $field['type'] ) {
			case 'textarea':
				return sanitize_textarea_field( $raw );

			case 'email':
				return sanitize_email( $raw );

			case 'url':
				return esc_url_raw( trim( (string) $raw ) );

			case 'number':
			case 'decimal':
				$raw = str_replace( ',', '.', trim( (string) $raw ) );
				return is_numeric( $raw ) ? (string) ( $raw + 0 ) : '';

			case 'date':
				$raw = sanitize_text_field( $raw );
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $raw ) ? $raw : '';

			case 'datetime':
				$raw = sanitize_text_field( $raw );
				return preg_match( '/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}$/', $raw ) ? $raw : '';

			case 'select':
				$raw = sanitize_text_field( $raw );
				return isset( $field['options'][ $raw ] ) ? $raw : '';

			case 'user':
			case 'attachment':
				return absint( $raw );

			case 'text':
			default:
				return sanitize_text_field( $raw );
		}
	}
}
