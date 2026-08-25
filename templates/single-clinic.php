<?php
/**
 * Clinic single post template (Traveljabs).
 *
 * Renders the plugin single layout inside the active theme's header and
 * footer. Hero section + two-column content/sidebar.
 *
 * @package Traveljabs
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>



<main class="archive-clinic-single">
	<div class="feature-section"></div>
</main>

<main class="tjb-cs">

	<article class="tjb-cs__post">

		<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

		<section class="tjb-cs__hero">
			<div class="tjb-cs__container">

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="tjb-cs__featured-image">
						<?php the_post_thumbnail( 'large', array( 'class' => 'tjb-cs__image' ) ); ?>
					</div>
				<?php endif; ?>

				<header class="tjb-cs__header">
					<h1 class="tjb-cs__title"><?php the_title(); ?></h1>
				</header>

			</div>
		</section>

		<section class="tjb-cs__content-section">
			<div class="tjb-cs__container">

				<div class="tjb-cs__layout">

					<article class="tjb-cs__main">
						<div class="tjb-cs__entry-content">
							<?php the_content(); ?>
						</div>
					</article>

					<aside class="tjb-cs__sidebar">

						<?php
						$tjb_phone     = trim( (string) get_post_meta( get_the_ID(), 'clinic_phone', true ) );
						$tjb_email     = trim( (string) get_post_meta( get_the_ID(), 'clinic_email', true ) );
						$tjb_website   = esc_url( trim( (string) get_post_meta( get_the_ID(), 'clinic_website', true ) ) );
						$tjb_address   = trim( (string) get_post_meta( get_the_ID(), 'clinic_address', true ) );
						$tjb_postcode  = trim( (string) get_post_meta( get_the_ID(), 'clinic_postcode', true ) );
						$tjb_latitude  = (string) get_post_meta( get_the_ID(), 'clinic_latitude', true );
						$tjb_longitude = (string) get_post_meta( get_the_ID(), 'clinic_longitude', true );
						$tjb_hours     = get_post_meta( get_the_ID(), 'clinic_opening_hours', true );
						$tjb_hours     = is_array( $tjb_hours ) ? array_filter(
							$tjb_hours,
							static function ( $row ): bool {
								return is_array( $row ) && ( ! empty( $row['day'] ) || ! empty( $row['time'] ) );
							}
						) : array();
						$tjb_has_map   = is_numeric( $tjb_latitude ) && is_numeric( $tjb_longitude )
							&& (float) $tjb_latitude >= -90 && (float) $tjb_latitude <= 90
							&& (float) $tjb_longitude >= -180 && (float) $tjb_longitude <= 180;
						?>

						<section class="tjb-cs__details" aria-labelledby="tjb-cs-details-title">
							<h2 id="tjb-cs-details-title" class="tjb-cs__sidebar-title">
								<?php esc_html_e( 'Clinic Details', 'traveljabs' ); ?>
							</h2>

							<dl class="tjb-cs__contact-list">
								<?php if ( '' !== $tjb_phone ) : ?>
									<div class="tjb-cs__contact-row">
										<dt><?php esc_html_e( 'Phone', 'traveljabs' ); ?></dt>
										<dd><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $tjb_phone ) ); ?>"><?php echo esc_html( $tjb_phone ); ?></a></dd>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $tjb_email && is_email( $tjb_email ) ) : ?>
									<div class="tjb-cs__contact-row">
										<dt><?php esc_html_e( 'Email', 'traveljabs' ); ?></dt>
										<dd><a href="<?php echo esc_url( 'mailto:' . $tjb_email ); ?>"><?php echo esc_html( $tjb_email ); ?></a></dd>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $tjb_website ) : ?>
									<div class="tjb-cs__contact-row">
										<dt><?php esc_html_e( 'Website', 'traveljabs' ); ?></dt>
										<dd><a href="<?php echo esc_url( $tjb_website ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $tjb_website ); ?></a></dd>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $tjb_address ) : ?>
									<div class="tjb-cs__contact-row">
										<dt><?php esc_html_e( 'Address', 'traveljabs' ); ?></dt>
										<dd><?php echo nl2br( esc_html( $tjb_address ) ); ?></dd>
									</div>
								<?php endif; ?>

								<?php if ( '' !== $tjb_postcode ) : ?>
									<div class="tjb-cs__contact-row">
										<dt><?php esc_html_e( 'Postcode', 'traveljabs' ); ?></dt>
										<dd><?php echo esc_html( $tjb_postcode ); ?></dd>
									</div>
								<?php endif; ?>
							</dl>

							<?php if ( ! empty( $tjb_hours ) ) : ?>
								<div class="tjb-cs__hours">
									<h3 class="tjb-cs__subheading"><?php esc_html_e( 'Opening Hours', 'traveljabs' ); ?></h3>
									<dl class="tjb-cs__hours-list">
										<?php foreach ( $tjb_hours as $tjb_hour ) : ?>
											<div class="tjb-cs__hours-row">
												<dt><?php echo esc_html( (string) ( $tjb_hour['day'] ?? '' ) ); ?></dt>
												<dd><?php echo esc_html( (string) ( $tjb_hour['time'] ?? '' ) ); ?></dd>
											</div>
										<?php endforeach; ?>
									</dl>
								</div>
							<?php endif; ?>

							<?php if ( $tjb_has_map ) : ?>
								<div class="tjb-cs__map">
									<iframe
										title="<?php esc_attr_e( 'Clinic location map', 'traveljabs' ); ?>"
										src="<?php echo esc_url( 'https://maps.google.com/maps?q=' . rawurlencode( $tjb_latitude . ',' . $tjb_longitude ) . '&z=15&output=embed' ); ?>"
										loading="lazy"
										referrerpolicy="no-referrer-when-downgrade"></iframe>
								</div>
							<?php endif; ?>
						</section>

						<h2 class="tjb-cs__sidebar-title"><?php esc_html_e( 'Recent Clinics', 'traveljabs' ); ?></h2>

						<?php
						$tjb_recent_query = new \WP_Query(
							array(
								'post_type'      => 'clinic',
								'posts_per_page' => 10,
								'post_status'    => 'publish',
								'orderby'        => 'date',
								'order'          => 'DESC',
								'post__not_in'   => array( get_the_ID() ),
								'no_found_rows'  => true,
							)
						);

						if ( $tjb_recent_query->have_posts() ) :
							while ( $tjb_recent_query->have_posts() ) :
								$tjb_recent_query->the_post();

								$tjb_recent_template = apply_filters(
									'traveljabs_clinic_recent_item_template',
									TRAVELJABS_PATH . 'templates/parts/clinic-recent-item.php'
								);

								if ( $tjb_recent_template && is_readable( $tjb_recent_template ) ) {
									include $tjb_recent_template;
								}
							endwhile;

							wp_reset_postdata();
						else :
						?>
							<p class="tjb-cs__sidebar-empty"><?php esc_html_e( 'No other clinics available.', 'traveljabs' ); ?></p>
						<?php endif; ?>

					</aside>

				</div>

			</div>
		</section>

		<?php endwhile; endif; ?>

	</article>

</main>

<?php
get_footer();
