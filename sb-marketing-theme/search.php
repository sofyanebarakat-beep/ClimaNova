<?php
/**
 * Search results template.
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<div class="page-hero">
	<div class="container">
		<h1>
			<?php
			printf(
				/* translators: %s: search query */
				esc_html__( 'Résultats pour : « %s »', 'sb-marketing-theme' ),
				'<span>' . esc_html( get_search_query() ) . '</span>'
			);
			?>
		</h1>
		<?php
		$found = $wp_query->found_posts;
		printf(
			/* translators: %d: number of results */
			esc_html( _n( '%d résultat trouvé.', '%d résultats trouvés.', $found, 'sb-marketing-theme' ) ),
			esc_html( number_format_i18n( $found ) )
		);
		?>
	</div>
</div>

<div class="page-content-wrap">
	<div class="container">
		<div class="search-form-wrap" style="max-width:500px;margin-bottom:40px;">
			<?php get_search_form(); ?>
		</div>

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
