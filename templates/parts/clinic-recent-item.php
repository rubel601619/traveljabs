<?php
/**
 * Clinic recent-item template part.
 *
 * Rendered inside the Traveljabs clinic single sidebar.
 *
 * @package Traveljabs
 */

defined( 'ABSPATH' ) || exit;
?>

<a class="tjb-cs__recent-item" href="<?php the_permalink(); ?>">

	<?php if ( has_post_thumbnail() ) : ?>
		<span class="tjb-cs__recent-image">
			<?php the_post_thumbnail( 'thumbnail', array( 'class' => 'tjb-cs__recent-thumb' ) ); ?>
		</span>
	<?php else : ?>
		<span class="tjb-cs__recent-fallback" aria-hidden="true"></span>
	<?php endif; ?>

	<span class="tjb-cs__recent-body">
		<span class="tjb-cs__recent-title"><?php the_title(); ?></span>
		<span class="tjb-cs__recent-link">
			<?php esc_html_e( 'View Details', 'traveljabs' ); ?>
		</span>
	</span>

</a>
