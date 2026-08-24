<?php
/**
 * Abstract custom field group (meta box) definition.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Meta;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Shared registration logic for Traveljabs custom field groups.
 *
 * Handles meta box registration, post meta registration, rendering of the
 * supported field types, and sanitized persistence. Concrete field groups
 * only declare their fields and any extra UI, such as repeaters.
 */
abstract class AbstractFieldGroup {

	/**
	 * Namespace wrapping every submitted input of all field groups.
	 */
	public const INPUT_NAMESPACE = 'traveljabs_meta';

	/**
	 * Nonce action prefix; the group key is appended at runtime.
	 */
	public const NONCE_ACTION_PREFIX = 'traveljabs_save_meta_';

	/**
	 * Nonce field name prefix; the group key is appended at runtime.
	 */
	public const NONCE_FIELD_PREFIX = 'traveljabs_meta_nonce_';

	/**
	 * Constructor. Hooks the field group into WordPress.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_meta' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . $this->get_post_type(), array( $this, 'save' ), 10 );
		add_action( 'admin_enqueue_scripts', array( $this, 'maybe_enqueue_assets' ) );
	}

	/**
	 * Returns the unique field group key, e.g. group_clinic_details.
	 *
	 * @return string
	 */
	abstract public function get_group_key(): string;

	/**
	 * Returns the internal, stable post type key this group attaches to.
	 *
	 * @return string
	 */
	abstract public function get_post_type(): string;

	/**
	 * Returns the meta box title.
	 *
	 * @return string
	 */
	abstract public function get_title(): string;

	/**
	 * Returns the scalar field definitions of this group.
	 *
	 * Each field supports: key, label, name, type (text|textarea|email|
	 * number), rows, required, instructions, placeholder, min and max.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	abstract public function get_fields(): array;

	/**
	 * Registers the meta box on its post type edit screen.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box(
			$this->get_group_key(),
			esc_html( $this->get_title() ),
			array( $this, 'render' ),
			$this->get_post_type(),
			'normal',
			'high'
		);
	}

	/**
	 * Registers every field as single post meta with sanitization and a
	 * capability-aware auth callback.
	 *
	 * @return void
	 */
	public function register_meta(): void {
		foreach ( $this->get_fields() as $field ) {
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';
			$type = isset( $field['type'] ) ? (string) $field['type'] : 'text';

			if ( '' === $name ) {
				continue;
			}

			register_post_meta(
				$this->get_post_type(),
				$name,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => function ( $value ) use ( $type ): string {
						return $this->sanitize_value( $type, $value );
					},
					'auth_callback'     => static function ( $allowed, $meta_key, $object_id ): bool {
						return current_user_can( 'edit_post', (int) $object_id );
					},
				)
			);
		}

		$this->register_extra_meta();
	}

	/**
	 * Renders the meta box: nonce, all fields, then any extra UI.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	public function render( WP_Post $post ): void {
		wp_nonce_field(
			self::NONCE_ACTION_PREFIX . $this->get_group_key(),
			self::NONCE_FIELD_PREFIX . $this->get_group_key()
		);

		echo '<div class="traveljabs-field-group">';

		foreach ( $this->get_fields() as $field ) {
			$this->render_field( $post->ID, $field );
		}

		$this->render_extras( $post );

		echo '</div>';
	}

	/**
	 * Persists submitted values after nonce, autosave, and capability checks.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in can_save().
		$raw   = isset( $_POST[ self::INPUT_NAMESPACE ] ) ? $_POST[ self::INPUT_NAMESPACE ] : array();
		$raw   = is_array( $raw ) ? wp_unslash( $raw ) : array();

		foreach ( $this->get_fields() as $field ) {
			$name = isset( $field['name'] ) ? (string) $field['name'] : '';

			if ( '' === $name || ! array_key_exists( $name, $raw ) ) {
				continue;
			}

			update_post_meta(
				$post_id,
				$name,
				$this->sanitize_value( (string) ( $field['type'] ?? 'text' ), $raw[ $name ] )
			);
		}

		$this->save_extras( $post_id, $raw );
	}

	/**
	 * Enqueues group assets when editing this group's post type.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function maybe_enqueue_assets( string $hook_suffix ): void {
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( null === $screen || $screen->post_type !== $this->get_post_type() ) {
			return;
		}

		$this->enqueue_assets();
	}

	/**
	 * Sanitizes a raw value according to its field type.
	 *
	 * @param string $type  Field type.
	 * @param mixed  $value Raw value.
	 * @return string
	 */
	protected function sanitize_value( string $type, $value ): string {
		$value = is_scalar( $value ) ? (string) $value : '';

		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $value );
			case 'email':
				return sanitize_email( $value );
			case 'number':
				return is_numeric( $value ) ? $value : '';
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Renders a single field row based on its type definition.
	 *
	 * @param int                   $post_id Post ID used to load the stored value.
	 * @param array<string, mixed>  $field   Field definition.
	 * @return void
	 */
	protected function render_field( int $post_id, array $field ): void {
		$name  = isset( $field['name'] ) ? (string) $field['name'] : '';
		$label = isset( $field['label'] ) ? (string) $field['label'] : '';
		$type  = isset( $field['type'] ) ? (string) $field['type'] : 'text';

		if ( '' === $name ) {
			return;
		}

		$value    = get_post_meta( $post_id, $name, true );
		$value    = is_scalar( $value ) ? (string) $value : '';
		$input_id = self::INPUT_NAMESPACE . '_' . $name;
		$required = ! empty( $field['required'] );

		printf(
			'<div class="traveljabs-field traveljabs-field--%1$s">',
			esc_attr( $type )
		);

		printf(
			'<label class="traveljabs-field__label" for="%1$s">%2$s%3$s</label>',
			esc_attr( $input_id ),
			esc_html( $label ),
			$required ? ' <span class="required" aria-hidden="true">*</span>' : ''
		);

		switch ( $type ) {
			case 'textarea':
				printf(
					'<textarea id="%1$s" name="%2$s[%3$s]" rows="%4$d"%5$s>%6$s</textarea>',
					esc_attr( $input_id ),
					esc_attr( self::INPUT_NAMESPACE ),
					esc_attr( $name ),
					max( 2, isset( $field['rows'] ) ? (int) $field['rows'] : 4 ),
					$required ? ' required' : '',
					esc_textarea( $value )
				);
				break;
			default:
				printf(
					'<input type="%1$s" id="%2$s" name="%3$s[%4$s]" value="%5$s" class="regular-text"%6$s%7$s%8$s%9$s />',
					esc_attr( $type ),
					esc_attr( $input_id ),
					esc_attr( self::INPUT_NAMESPACE ),
					esc_attr( $name ),
					esc_attr( $value ),
					$required ? ' required' : '',
					isset( $field['placeholder'] ) && '' !== (string) $field['placeholder']
						? ' placeholder="' . esc_attr( (string) $field['placeholder'] ) . '"'
						: '',
					isset( $field['min'] ) ? sprintf( ' min="%s"', esc_attr( (string) $field['min'] ) ) : '',
					isset( $field['max'] ) ? sprintf( ' max="%s"', esc_attr( (string) $field['max'] ) ) : ''
				);
				break;
		}

		if ( ! empty( $field['instructions'] ) ) {
			printf(
				'<p class="description">%s</p>',
				esc_html( (string) $field['instructions'] )
			);
		}

		echo '</div>';
	}

	/**
	 * Extension point for additional UI below the declared fields.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	protected function render_extras( WP_Post $post ): void {}

	/**
	 * Extension point for persisting data from render_extras().
	 *
	 * @param int                  $post_id Post ID being saved.
	 * @param array<string, mixed> $input   Unslashed, namespaced POST input.
	 * @return void
	 */
	protected function save_extras( int $post_id, array $input ): void {}

	/**
	 * Extension point for registering meta outside get_fields().
	 *
	 * @return void
	 */
	protected function register_extra_meta(): void {}

	/**
	 * Enqueues the group-specific assets on the edit screen.
	 *
	 * @return void
	 */
	protected function enqueue_assets(): void {}

	/**
	 * Runs all security checks required before persisting meta.
	 *
	 * @param int $post_id Post ID being saved.
	 * @return bool
	 */
	private function can_save( int $post_id ): bool {
		$nonce_field = self::NONCE_FIELD_PREFIX . $this->get_group_key();
		$nonce       = isset( $_POST[ $nonce_field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ) : '';

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION_PREFIX . $this->get_group_key() ) ) {
			return false;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		return current_user_can( 'edit_post', $post_id );
	}
}
