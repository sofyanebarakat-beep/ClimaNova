<?php
/**
 * Generic page template.
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<?php while ( have_posts() ) : the_post(); ?>

	<!-- Page hero -->
	<div class="page-hero">
		<div class="container">
			<h1><?php the_title(); ?></h1>
			<?php if ( has_excerpt() ) : ?>
				<p><?php the_excerpt(); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<!-- Page content -->
	<div class="page-content-wrap">
		<div class="container">
			<?php if ( has_post_thumbnail() ) : ?>
				<div class="page-featured-image">
					<?php the_post_thumbnail( 'sbmt-hero', [ 'alt' => get_the_title() ] ); ?>
				</div>
			<?php endif; ?>

			<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content' ); ?>>
				<?php the_content(); ?>
				<?php
				wp_link_pages( [
					'before' => '<div class="page-links">' . esc_html__( 'Pages :', 'sb-marketing-theme' ),
					'after'  => '</div>',
				] );
				?>
			</article>
		</div>
	</div>

	<?php
	// Allow comments on pages if enabled
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
	?>

<?php endwhile; ?>

<?php get_footer(); ?>
