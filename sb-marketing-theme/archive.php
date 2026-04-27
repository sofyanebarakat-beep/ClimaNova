<?php
/**
 * Archive template — used for categories, tags, dates, author, and CPT archives.
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<div class="page-hero">
	<div class="container">
		<?php the_archive_title( '<h1>', '</h1>' ); ?>
		<?php the_archive_description( '<p class="page-hero p">', '</p>' ); ?>
	</div>
</div>

<div class="page-content-wrap">
	<div class="container">

		<?php if ( have_posts() ) : ?>
			<div class="blog-grid">
				<?php while ( have_posts() ) : the_post(); ?>
					<?php
					// Use project card template for project archives
					if ( 'climanova_project' === get_post_type() ) {
						get_template_part( 'template-parts/content', 'project' );
					} elseif ( 'climanova_service' === get_post_type() ) {
						get_template_part( 'template-parts/content', 'service' );
					} else {
						get_template_part( 'template-parts/content', 'post' );
					}
					?>
				<?php endwhile; ?>
			</div>
			<?php sbmt_pagination( $wp_query ); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content', 'none' ); ?>
		<?php endif; ?>

	</div>
</div>

<?php get_footer(); ?>
