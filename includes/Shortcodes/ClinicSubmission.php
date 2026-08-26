<?php
/**
 * Frontend clinic submission shortcode.
 *
 * @package Traveljabs
 */

namespace Traveljabs\Shortcodes;

use Traveljabs\Commerce\PackageService;
use Traveljabs\Meta\ClinicDetails;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and securely processes frontend clinic submissions.
 */
final class ClinicSubmission {

	/**
	 * Shortcode tag.
	 */
	private const SHORTCODE = 'clinic_submission';

	/**
	 * Form action name.
	 */
	private const ACTION = 'traveljabs_submit_clinic';

	/**
	 * Submission token lifetime in seconds.
	 */
	private const TOKEN_TTL = HOUR_IN_SECONDS;

	/**
	 * Validation errors from the current request.
	 *
	 * @var array<int, string>
	 */
	private array $errors = array();

	/**
	 * Constructor. Registers the shortcode and POST handler.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'process_submission' ) );
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
	}

	/**
	 * Processes the form before page output begins.
	 *
	 * @return void
	 */
	public function process_submission(): void {
		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Action is checked before nonce verification.
		if ( ! isset( $_POST['traveljabs_clinic_action'] ) || self::ACTION !== sanitize_key( wp_unslash( $_POST['traveljabs_clinic_action'] ) ) ) {
			return;
		}

		$redirect_to = isset( $_POST['traveljabs_redirect'] ) ? wp_validate_redirect( wp_unslash( $_POST['traveljabs_redirect'] ), home_url( '/' ) ) : home_url( '/' );
		$redirect_to = add_query_arg( 'traveljabs_clinic_status', 'error', $redirect_to );

		if ( ! is_user_logged_in() ) {
			$this->errors[] = __( 'You must be logged in to add a clinic.', 'traveljabs' );
			return;
		}

		if ( ! isset( $_POST['traveljabs_clinic_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['traveljabs_clinic_nonce'] ) ), self::ACTION ) ) {
			$this->errors[] = __( 'Your session expired. Please refresh the page and try again.', 'traveljabs' );
			return;
		}

		$user_id = get_current_user_id();
		$package = PackageService::get_active_package( $user_id );

		if ( null === $package ) {
			$this->errors[] = __( 'You need an active package before adding a clinic.', 'traveljabs' );
			return;
		}

		$token = isset( $_POST['traveljabs_clinic_token'] ) ? sanitize_key( wp_unslash( $_POST['traveljabs_clinic_token'] ) ) : '';

		if ( '' === $token || false === get_transient( 'traveljabs_clinic_submission_' . $token ) ) {
			$this->errors[] = __( 'This form has already been submitted or has expired. Please refresh the page.', 'traveljabs' );
			return;
		}

		$lock_key = 'traveljabs_clinic_lock_' . $user_id;
		$existing_lock = get_option( $lock_key, false );

		if ( false !== $existing_lock && ( time() - (int) $existing_lock ) > 5 * MINUTE_IN_SECONDS ) {
			delete_option( $lock_key );
		}

		if ( ! add_option( $lock_key, (string) time(), '', 'no' ) ) {
			$this->errors[] = __( 'Another clinic submission is already being processed. Please try again.', 'traveljabs' );
			return;
		}

		try {
			$count = $this->count_user_clinics( $user_id );

			if ( $count >= (int) $package['limit'] ) {
				$this->errors[] = sprintf( __( 'You have reached your %s clinic limit.', 'traveljabs' ), $package['label'] );
				return;
			}

			$values = $this->validate_input();

			if ( ! empty( $this->errors ) ) {
				return;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => 'clinic',
					'post_status'  => 'pending',
					'post_title'   => $values['title'],
					'post_content' => $values['content'],
					'post_author'  => $user_id,
				),
				true
			);

			if ( is_wp_error( $post_id ) ) {
				$this->errors[] = __( 'The clinic could not be added. Please try again.', 'traveljabs' );
				return;
			}

			$this->save_meta( (int) $post_id, $values );
			$this->set_featured_image( (int) $post_id, $values['featured_image'] );
			delete_transient( 'traveljabs_clinic_submission_' . $token );
			wp_safe_redirect( add_query_arg( 'traveljabs_clinic_status', 'success', $redirect_to ) );
			exit;
		} finally {
			delete_option( $lock_key );
		}
	}

	/**
	 * Renders the submission form and owned clinic list.
	 *
	 * @return string
	 */
	public function render(): string {
		if ( ! is_user_logged_in() ) {
			return $this->render_message( __( 'You must be logged in to add a clinic.', 'traveljabs' ), 'warning', wp_login_url( $this->current_url() ) );
		}

		$user_id = get_current_user_id();
		$package = PackageService::get_active_package( $user_id );

		if ( null === $package ) {
			return $this->render_message( __( 'You need an active package before adding a clinic.', 'traveljabs' ), 'warning', PackageService::get_purchase_url() );
		}

		$count = $this->count_user_clinics( $user_id );
		$output = '<div class="traveljabs-clinic-submission">';
		$output .= '<div class="traveljabs-clinic-submission__summary"><strong>' . esc_html__( 'Your Package:', 'traveljabs' ) . '</strong> ' . esc_html( $package['label'] ) . '<br><strong>' . esc_html__( 'Clinic Usage:', 'traveljabs' ) . '</strong> ' . esc_html( $count . ' / ' . $package['limit'] ) . '<br><strong>' . esc_html__( 'Remaining Clinics:', 'traveljabs' ) . '</strong> ' . esc_html( max( 0, (int) $package['limit'] - $count ) ) . '</div>';
		$output .= $this->render_status();
		$output .= $this->render_owned_clinics( $user_id );

		if ( $count >= (int) $package['limit'] ) {
			return $output . $this->render_message( sprintf( __( 'You have reached your %s clinic limit. Please upgrade your package to add another clinic.', 'traveljabs' ), $package['label'] ), 'warning', PackageService::get_purchase_url() ) . '</div>';
		}

		$token = wp_generate_uuid4();
		set_transient( 'traveljabs_clinic_submission_' . $token, 1, self::TOKEN_TTL );
		wp_enqueue_media();
		wp_enqueue_editor();
		wp_enqueue_style( 'traveljabs-clinic-submission', TRAVELJABS_URL . 'assets/css/clinic-submission.css', array(), TRAVELJABS_VERSION );
		wp_enqueue_style( 'traveljabs-clinic-details', TRAVELJABS_URL . 'assets/css/clinic-details.css', array(), TRAVELJABS_VERSION );
		wp_enqueue_script( 'traveljabs-clinic-submission', TRAVELJABS_URL . 'assets/js/clinic-submission.js', array( 'jquery' ), TRAVELJABS_VERSION, true );
		wp_enqueue_script( 'traveljabs-clinic-details', TRAVELJABS_URL . 'assets/js/clinic-details.js', array(), TRAVELJABS_VERSION, true );

		ob_start();
		?>
		<div class="traveljabs-clinic-submission__form-wrap">
			<h2><?php echo esc_html__( 'Add New Clinic', 'traveljabs' ); ?></h2>
			<?php if ( ! empty( $this->errors ) ) : ?>
				<div class="traveljabs-clinic-submission__errors" role="alert"><ul><?php foreach ( $this->errors as $error ) : ?><li><?php echo esc_html( $error ); ?></li><?php endforeach; ?></ul></div>
			<?php endif; ?>
			<form method="post" class="traveljabs-clinic-submission__form">
				<input type="hidden" name="traveljabs_clinic_action" value="<?php echo esc_attr( self::ACTION ); ?>">
				<input type="hidden" name="traveljabs_clinic_nonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION ) ); ?>">
				<input type="hidden" name="traveljabs_clinic_token" value="<?php echo esc_attr( $token ); ?>">
				<input type="hidden" name="traveljabs_redirect" value="<?php echo esc_url( $this->current_url() ); ?>">
				<p><label for="traveljabs-clinic-title"><?php echo esc_html__( 'Clinic Name', 'traveljabs' ); ?></label><input required type="text" id="traveljabs-clinic-title" name="clinic_title" value="<?php echo esc_attr( $this->posted_value( 'clinic_title' ) ); ?>"></p>
				<p><label for="traveljabs-clinic-content"><?php echo esc_html__( 'Description', 'traveljabs' ); ?></label><?php wp_editor( $this->posted_value( 'clinic_content' ), 'traveljabs_clinic_content', array( 'textarea_name' => 'clinic_content', 'media_buttons' => false, 'textarea_rows' => 8 ) ); ?></p>
				<p><label><?php echo esc_html__( 'Featured Image', 'traveljabs' ); ?></label><input type="hidden" name="clinic_featured_image" id="traveljabs-clinic-featured-image" value="<?php echo esc_attr( $this->posted_value( 'clinic_featured_image' ) ); ?>"><button type="button" class="button traveljabs-select-image"><?php echo esc_html__( 'Select Image', 'traveljabs' ); ?></button><span class="traveljabs-selected-image"></span></p>
				<?php $this->render_field( 'clinic_address', __( 'Address', 'traveljabs' ), 'text', true ); ?>
				<?php $this->render_field( 'clinic_postcode', __( 'Postcode', 'traveljabs' ), 'text', true ); ?>
				<?php $this->render_field( 'clinic_phone', __( 'Phone', 'traveljabs' ), 'text' ); ?>
				<?php $this->render_field( 'clinic_email', __( 'Email', 'traveljabs' ), 'email' ); ?>
				<?php $this->render_field( 'clinic_website', __( 'Website', 'traveljabs' ), 'url' ); ?>
				<?php $this->render_field( 'clinic_latitude', __( 'Latitude', 'traveljabs' ), 'number', true, '-90', '90', 'any' ); ?>
				<?php $this->render_field( 'clinic_longitude', __( 'Longitude', 'traveljabs' ), 'number', true, '-180', '180', 'any' ); ?>
				<?php $this->render_opening_hours_field(); ?>
				<p><button type="submit" class="button button-primary traveljabs-submit-clinic"><?php echo esc_html__( 'Add Clinic', 'traveljabs' ); ?></button></p>
			</form>
		</div>
		<?php
		return $output . ob_get_clean() . '</div>';
	}

	/**
	 * Validates and sanitizes submitted fields.
	 *
	 * @return array<string, mixed>
	 */
	private function validate_input(): array {
		$title = $this->posted_value( 'clinic_title' );
		$content = isset( $_POST['clinic_content'] ) ? wp_kses_post( wp_unslash( $_POST['clinic_content'] ) ) : '';
		$values = array( 'title' => sanitize_text_field( $title ), 'content' => $content, 'featured_image' => absint( $this->posted_value( 'clinic_featured_image' ) ) );

		if ( '' === $values['title'] ) $this->errors[] = __( 'Clinic name is required.', 'traveljabs' );
		$values['clinic_address'] = $this->required_text( 'clinic_address', __( 'Address', 'traveljabs' ) );
		$values['clinic_postcode'] = $this->required_text( 'clinic_postcode', __( 'Postcode', 'traveljabs' ) );
		$values['clinic_phone'] = sanitize_text_field( $this->posted_value( 'clinic_phone' ) );
		$values['clinic_email'] = sanitize_email( $this->posted_value( 'clinic_email' ) );
		$values['clinic_website'] = esc_url_raw( $this->posted_value( 'clinic_website' ) );
		$values['clinic_latitude'] = $this->validated_coordinate( 'clinic_latitude', -90, 90 );
		$values['clinic_longitude'] = $this->validated_coordinate( 'clinic_longitude', -180, 180 );
		$values['clinic_opening_hours'] = $this->sanitize_opening_hours( $this->posted_opening_hours() );

		if ( '' !== $this->posted_value( 'clinic_email' ) && ! is_email( $values['clinic_email'] ) ) $this->errors[] = __( 'Please enter a valid email address.', 'traveljabs' );
		if ( '' !== $this->posted_value( 'clinic_website' ) && '' === $values['clinic_website'] ) $this->errors[] = __( 'Please enter a valid website URL.', 'traveljabs' );

		return $values;
	}

	/**
	 * Renders the same day/time repeater used in the clinic admin field group.
	 *
	 * @return void
	 */
	private function render_opening_hours_field(): void {
		$rows = $this->posted_opening_hours();
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
					<?php foreach ( $rows as $row ) : ?>
						<?php $this->render_opening_hours_row( $row['day'], $row['time'] ); ?>
					<?php endforeach; ?>
					<?php $this->render_opening_hours_row( '', '', true ); ?>
				</tbody>
			</table>
			<p class="traveljabs-repeater__footer">
				<button type="button" class="button button-secondary traveljabs-hours-add" aria-describedby="traveljabs-opening-hours-label">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
					<?php echo esc_html__( 'Add', 'traveljabs' ); ?>
				</button>
			</p>
			<p class="description"><?php echo esc_html__( 'Add each day and its opening time. Leave both fields empty to skip a row.', 'traveljabs' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Renders one opening-hours day/time row.
	 *
	 * @param string $day        Day value.
	 * @param string $time       Time value.
	 * @param bool   $is_template Whether this is the hidden JS template.
	 * @return void
	 */
	private function render_opening_hours_row( string $day, string $time, bool $is_template = false ): void {
		$base_name = 'clinic_opening_hours';

		printf(
			'<tr class="traveljabs-hours-row%1$s"%2$s>',
			$is_template ? ' is-template' : '',
			$is_template ? ' hidden' : ''
		);
		printf(
			'<td><input type="text" name="%1$s[day][]" class="widefat" aria-label="%2$s" placeholder="%3$s" value="%4$s" /></td>',
			esc_attr( $base_name ),
			esc_attr__( 'Day', 'traveljabs' ),
			esc_attr__( 'Monday', 'traveljabs' ),
			esc_attr( $day )
		);
		printf(
			'<td><input type="text" name="%1$s[time][]" class="widefat" aria-label="%2$s" placeholder="%3$s" value="%4$s" /></td>',
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
		</tr>
		<?php
	}

	/**
	 * Reads submitted opening-hours rows.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function posted_opening_hours(): array {
		$raw = isset( $_POST['clinic_opening_hours'] ) && is_array( $_POST['clinic_opening_hours'] )
			? wp_unslash( $_POST['clinic_opening_hours'] )
			: array();
		$days  = isset( $raw['day'] ) && is_array( $raw['day'] ) ? $raw['day'] : array();
		$times = isset( $raw['time'] ) && is_array( $raw['time'] ) ? $raw['time'] : array();
		$rows  = array();

		foreach ( $days as $index => $day ) {
			$rows[] = array(
				'day'  => is_scalar( $day ) ? sanitize_text_field( (string) $day ) : '',
				'time' => isset( $times[ $index ] ) && is_scalar( $times[ $index ] ) ? sanitize_text_field( (string) $times[ $index ] ) : '',
			);
		}

		return $rows;
	}

	/**
	 * Removes completely empty opening-hours rows.
	 *
	 * @param array<int, array<string, string>> $rows Opening-hours rows.
	 * @return array<int, array<string, string>>
	 */
	private function sanitize_opening_hours( array $rows ): array {
		$clean = array();

		foreach ( $rows as $row ) {
			if ( '' === $row['day'] && '' === $row['time'] ) {
				continue;
			}

			$clean[] = $row;
		}

		return $clean;
	}

	/**
	 * Saves the existing clinic meta format.
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string, mixed> $values  Validated values.
	 * @return void
	 */
	private function save_meta( int $post_id, array $values ): void {
		foreach ( array( 'clinic_address', 'clinic_postcode', 'clinic_phone', 'clinic_email', 'clinic_website', 'clinic_latitude', 'clinic_longitude' ) as $key ) update_post_meta( $post_id, $key, $values[ $key ] );
		update_post_meta( $post_id, ClinicDetails::OPENING_HOURS_META, $values['clinic_opening_hours'] );
	}

	/**
	 * Sets a selected image as featured image when the user can edit it.
	 *
	 * @param int $post_id  Clinic ID.
	 * @param int $image_id Attachment ID.
	 * @return void
	 */
	private function set_featured_image( int $post_id, int $image_id ): void {
		if ( $image_id > 0 && wp_attachment_is_image( $image_id ) && current_user_can( 'edit_post', $image_id ) ) set_post_thumbnail( $post_id, $image_id );
	}

	/** @param int $user_id User ID. @return int */
	private function count_user_clinics( int $user_id ): int { return count( get_posts( array( 'post_type' => 'clinic', 'post_status' => 'any', 'author' => $user_id, 'numberposts' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) ); }

	/** @param string $key Field key. @param string $label Field label. @return string */
	private function required_text( string $key, string $label ): string { $value = sanitize_text_field( $this->posted_value( $key ) ); if ( '' === $value ) $this->errors[] = sprintf( __( '%s is required.', 'traveljabs' ), $label ); return $value; }

	/** @param string $key Field key. @param float $min Minimum. @param float $max Maximum. @return string */
	private function validated_coordinate( string $key, float $min, float $max ): string { $raw = trim( $this->posted_value( $key ) ); if ( '' === $raw || ! is_numeric( $raw ) || (float) $raw < $min || (float) $raw > $max ) { $this->errors[] = sprintf( __( 'Please enter a valid %s.', 'traveljabs' ), str_replace( 'clinic_', '', $key ) ); return ''; } return (string) (float) $raw; }

	/** @param string $key Field key. @param string $label Label. @param string $type Input type. @param bool $required Required. @param string $min Minimum. @param string $max Maximum. @param string $step Step. @return void */
	private function render_field( string $key, string $label, string $type, bool $required = false, string $min = '', string $max = '', string $step = '1' ): void { printf( '<p><label for="traveljabs-%1$s">%2$s</label><input type="%3$s" id="traveljabs-%1$s" name="%1$s" value="%4$s"%5$s%6$s%7$s%8$s></p>', esc_attr( $key ), esc_html( $label ), esc_attr( $type ), esc_attr( $this->posted_value( $key ) ), $required ? ' required' : '', '' !== $min ? ' min="' . esc_attr( $min ) . '"' : '', '' !== $max ? ' max="' . esc_attr( $max ) . '"' : '', 'number' === $type ? ' step="' . esc_attr( $step ) . '"' : '' ); }

	/** @param int $user_id User ID. @return string */
	private function render_owned_clinics( int $user_id ): string { $posts = get_posts( array( 'post_type' => 'clinic', 'post_status' => 'any', 'author' => $user_id, 'numberposts' => -1, 'orderby' => 'date', 'order' => 'DESC' ) ); if ( empty( $posts ) ) return ''; $html = '<div class="traveljabs-clinic-submission__owned"><h2>' . esc_html__( 'My Clinics', 'traveljabs' ) . '</h2><ul>'; foreach ( $posts as $post ) $html .= '<li>' . esc_html( get_the_title( $post ) ) . '</li>'; return $html . '</ul></div>'; }

	/** @param string $message Message. @param string $type Notice class. @param string $url Link URL. @return string */
	private function render_message( string $message, string $type, string $url ): string { return '<div class="traveljabs-clinic-submission__message ' . esc_attr( $type ) . '"><p>' . esc_html( $message ) . '</p><p><a href="' . esc_url( $url ) . '">' . esc_html__( 'Continue', 'traveljabs' ) . '</a></p></div>'; }

	/** @return string */
	private function render_status(): string { $status = isset( $_GET['traveljabs_clinic_status'] ) ? sanitize_key( wp_unslash( $_GET['traveljabs_clinic_status'] ) ) : ''; if ( 'success' !== $status ) return ''; return '<div class="traveljabs-clinic-submission__success" role="status">' . esc_html__( 'Clinic added successfully.', 'traveljabs' ) . '</div>'; }

	/** @param string $key Field key. @return string */
	private function posted_value( string $key ): string { return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : ''; }

	/** @return string */
	private function current_url(): string { return ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ?? '' ) ) . sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ); }
}
