<?php
/**
 * Clinic Details custom field group.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Meta;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the Clinic Details field group on the clinic post type,
 * including the opening hours repeater (day + time rows with an Add button).
 */
final class ClinicDetails extends AbstractFieldGroup {

	/**
	 * Meta key storing the opening hours repeater rows.
	 */
	public const OPENING_HOURS_META = 'clinic_opening_hours';

	/**
	 * Input sub-key used by the opening hours repeater fields.
	 */
	private const OPENING_HOURS_INPUT = 'opening_hours';

	/**
	 * Returns the unique field group key.
	 *
	 * @return string
	 */
	public function get_group_key(): string {
		return 'group_clinic_details';
	}

	/**
	 * Returns the internal, stable post type key this group attaches to.
	 *
	 * @return string
	 */
	public function get_post_type(): string {
		return 'clinic';
	}

	/**
	 * Returns the meta box title.
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Clinic Details', 'traveljabs' );
	}

	/**
	 * Returns the scalar field definitions of this group.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_fields(): array {
		return array(
			array(
				'key'      => 'field_clinic_address',
				'label'    => __( 'Address', 'traveljabs' ),
				'name'     => 'clinic_address',
				'type'     => 'textarea',
				'rows'     => 2,
				'required' => 1,
			),
			array(
				'key'      => 'field_clinic_postcode',
				'label'    => __( 'Postcode', 'traveljabs' ),
				'name'     => 'clinic_postcode',
				'type'     => 'text',
				'required' => 1,
			),
			array(
				'key'   => 'field_clinic_phone',
				'label' => __( 'Phone', 'traveljabs' ),
				'name'  => 'clinic_phone',
				'type'  => 'text',
			),
			array(
				'key'          => 'field_clinic_email',
				'label'        => __( 'Email', 'traveljabs' ),
				'name'         => 'clinic_email',
				'type'         => 'email',
				'instructions' => __( 'Public contact email address.', 'traveljabs' ),
			),
			array(
				'key'          => 'field_clinic_latitude',
				'label'        => __( 'Latitude', 'traveljabs' ),
				'name'         => 'clinic_latitude',
				'type'         => 'number',
				'instructions' => __( 'e.g. 51.5074', 'traveljabs' ),
			),
			array(
				'key'          => 'field_clinic_longitude',
				'label'        => __( 'Longitude', 'traveljabs' ),
				'name'         => 'clinic_longitude',
				'type'         => 'number',
				'instructions' => __( 'e.g. -0.1278', 'traveljabs' ),
			),
		);
	}

	/**
	 * Renders the opening hours repeater below the declared fields.
	 *
	 * @param WP_Post $post Current post object.
	 * @return void
	 */
	protected function render_extras( WP_Post $post ): void {
		$stored = get_post_meta( $post->ID, self::OPENING_HOURS_META, true );
		$rows   = is_array( $stored ) ? $stored : array();

		?>
		<div class="traveljabs-field traveljabs-repeater" id="traveljabs-clinic-opening-hours">
			<span class="traveljabs-field__label" id="traveljabs-opening-hours-label">
				<?php echo esc_html__( 'Opening Hours', 'traveljabs' ); ?>
			</span>

			<table class="widefat striped traveljabs-repeater__table">
				<thead>
					<tr>
						<th scope="col"><?php echo esc_html__( 'Day', 'traveljabs' ); ?></th>
						<th scope="col"><?php echo esc_html__( 'Time', 'traveljabs' ); ?></th>
						<th scope="col" class="traveljabs-repeater__actions"><span class="screen-reader-text"><?php echo esc_html__( 'Actions', 'traveljabs' ); ?></span></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $rows as $row ) {
						$this->render_repeater_row(
							isset( $row['day'] ) ? (string) $row['day'] : '',
							isset( $row['time'] ) ? (string) $row['time'] : ''
						);
					}

					$this->render_repeater_row( '', '', true );
					?>
				</tbody>
			</table>

			<p class="traveljabs-repeater__footer">
				<button type="button" class="button button-secondary traveljabs-hours-add" aria-describedby="traveljabs-opening-hours-label">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php echo esc_html__( 'Add', 'traveljabs' ); ?>
				</button>
			</p>

			<p class="description">
				<?php echo esc_html__( 'e.g. Monday / 9:00 AM - 5:00 PM. Leave both fields empty to skip a row.', 'traveljabs' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Persists the opening hours repeater rows.
	 *
	 * @param int                  $post_id Post ID being saved.
	 * @param array<string, mixed> $input   Unslashed, namespaced POST input.
	 * @return void
	 */
	protected function save_extras( int $post_id, array $input ): void {
		$days  = isset( $input[ self::OPENING_HOURS_INPUT ]['day'] ) && is_array( $input[ self::OPENING_HOURS_INPUT ]['day'] )
			? $input[ self::OPENING_HOURS_INPUT ]['day']
			: array();
		$times = isset( $input[ self::OPENING_HOURS_INPUT ]['time'] ) && is_array( $input[ self::OPENING_HOURS_INPUT ]['time'] )
			? $input[ self::OPENING_HOURS_INPUT ]['time']
			: array();

		$rows = array();

		foreach ( array_keys( $days ) as $index ) {
			$rows[] = array(
				'day'  => sanitize_text_field( (string) $days[ $index ] ),
				'time' => isset( $times[ $index ] ) ? sanitize_text_field( (string) $times[ $index ] ) : '',
			);
		}

		update_post_meta( $post_id, self::OPENING_HOURS_META, $rows );
	}

	/**
	 * Registers the opening hours repeater meta with a REST schema.
	 *
	 * @return void
	 */
	protected function register_extra_meta(): void {
		register_post_meta(
			$this->get_post_type(),
			self::OPENING_HOURS_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => array(
					'name'   => self::OPENING_HOURS_META,
					'schema' => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'day'  => array(
									'type' => 'string',
								),
								'time' => array(
									'type' => 'string',
								),
							),
						),
					),
				),
				'sanitize_callback' => array( $this, 'sanitize_opening_hours' ),
				'auth_callback'     => static function ( $allowed, $meta_key, $object_id ): bool {
					return current_user_can( 'edit_post', (int) $object_id );
				},
			)
		);
	}

	/**
	 * Enqueues the clinic details admin assets on clinic edit screens.
	 *
	 * @return void
	 */
	protected function enqueue_assets(): void {
		wp_enqueue_style(
			'traveljabs-clinic-details',
			plugins_url( 'assets/css/clinic-details.css', TRAVELJABS_FILE ),
			array(),
			TRAVELJABS_VERSION
		);

		wp_enqueue_script(
			'traveljabs-clinic-details',
			plugins_url( 'assets/js/clinic-details.js', TRAVELJABS_FILE ),
			array(),
			TRAVELJABS_VERSION,
			true
		);
	}

	/**
	 * Sanitizes an opening hours row set; empty day and time rows are dropped.
	 *
	 * @param mixed $value Raw repeater value.
	 * @return array<int, array<string, string>>
	 */
	public function sanitize_opening_hours( $value ): array {
		$rows = array();

		foreach ( (array) $value as $row ) {
			$row  = is_array( $row ) ? $row : array();
			$day  = isset( $row['day'] ) ? sanitize_text_field( (string) $row['day'] ) : '';
			$time = isset( $row['time'] ) ? sanitize_text_field( (string) $row['time'] ) : '';

			if ( '' === $day && '' === $time ) {
				continue;
			}

			$rows[] = array(
				'day'  => $day,
				'time' => $time,
			);
		}

		return $rows;
	}

	/**
	 * Renders one opening hours table row.
	 *
	 * @param string $day        Day cell value.
	 * @param string $time       Time cell value.
	 * @param bool   $is_template Whether this row is the hidden JS template.
	 * @return void
	 */
	private function render_repeater_row( string $day, string $time, bool $is_template = false ): void {
		$base_name = self::INPUT_NAMESPACE . '[' . self::OPENING_HOURS_INPUT . ']';

		printf(
			'<tr class="traveljabs-hours-row%1$s"%2$s>',
			$is_template ? ' is-template' : '',
			$is_template ? ' hidden' : ''
		);

		printf(
			'<td><input type="text" name="%1$s[day][]" class="regular-text" aria-label="%2$s" placeholder="%3$s" value="%4$s" /></td>',
			esc_attr( $base_name ),
			esc_attr__( 'Day', 'traveljabs' ),
			esc_attr__( 'Monday', 'traveljabs' ),
			esc_attr( $day )
		);

		printf(
			'<td><input type="text" name="%1$s[time][]" class="regular-text" aria-label="%2$s" placeholder="%3$s" value="%4$s" /></td>',
			esc_attr( $base_name ),
			esc_attr__( 'Time', 'traveljabs' ),
			esc_attr__( '9:00 AM - 5:00 PM', 'traveljabs' ),
			esc_attr( $time )
		);

		?>
		<td class="traveljabs-repeater__actions">
			<button type="button" class="button-link traveljabs-hours-remove">
				<span class="dashicons dashicons-trash" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php echo esc_html__( 'Remove opening hour entry', 'traveljabs' ); ?></span>
			</button>
		</td>
		<?php

		echo '</tr>';
	}
}
