<?php
/**
 * Fallback template — used when no other template matches.
 * WordPress always needs this file to exist.
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<div class="page-hero">
	<div class="container">
		<?php
		if ( is_home() && ! is_front_page() ) {
			echo '<h1>' . esc_html__( 'Blog', 'sb-marketing-theme' ) . '</h1>';
		} elseif ( is_archive() ) {
			the_archive_title( '<h1>', '</h1>' );
			the_archive_description( '<p class="archive-desc">', '</p>' );
		} elseif ( is_search() ) {
			printf(
				'<h1>' . esc_html__( 'Résultats pour : %s', 'sb-marketing-theme' ) . '</h1>',
				'<span>' . esc_html( get_search_query() ) . '</span>'
			);
		} else {
			echo '<h1>' . esc_html( get_bloginfo( 'name' ) ) . '</h1>';
		}
		?>
	</div>
</div>

<div class="page-content-wrap">
	<div class="container">

		<?php if ( have_posts() ) : ?>
			<div class="blog-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php get_template_part( 'template-parts/content', 'post' ); ?>
				<?php endwhile; ?>
			</div>
			<?php sbmt_pagination( $wp_query ); ?>

		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>

	</div>
</div>

<?php get_footer(); ?>
