<?php
/**
 * Homepage — Blog preview section
 * Shows the latest N blog posts (N configurable in Customizer).
 *
 * @package SBMarketingTheme
 */

$title     = sbmt_mod( 'sbmt_blog_title',    'Conseils et guides pratiques' );
$btn_text  = sbmt_mod( 'sbmt_blog_btn_text', 'Voir tous les articles' );
$btn_url   = sbmt_mod( 'sbmt_blog_btn_url',  get_permalink( get_option( 'page_for_posts' ) ) ?: home_url( '/blog' ) );
$num_posts = absint( get_theme_mod( 'sbmt_blog_posts_count', 4 ) );
$num_posts = max( 1, min( 6, $num_posts ) );

$blog_query = new WP_Query( [
	'post_type'      => 'post',
	'posts_per_page' => $num_posts,
	'post_status'    => 'publish',
	'ignore_sticky_posts' => true,
] );

if ( ! $blog_query->have_posts() ) {
	return;
}
?>

<section class="blog-section" aria-label="<?php esc_attr_e( 'Blog & conseils', 'sb-marketing-theme' ); ?>">
	<div class="container">

		<!-- Section header -->
		<div class="section-header-wrap">
			<div class="section-title-wrap">
				<div class="section-subtitle-wrap">
					<div class="section-subtitle-icon w-embed">
						<span class="material-icons-round" aria-hidden="true">article</span>
					</div>
					<div class="section-subtitle text-sm"><?php esc_html_e( 'Notre blog', 'sb-marketing-theme' ); ?></div>
				</div>
				<h2 class="heading-style-h2"><?php echo wp_kses_post( $title ); ?></h2>
			</div>
			<div class="section-header-cta">
				<?php sbmt_button( $btn_text, $btn_url ); ?>
			</div>
		</div>

		<!-- Blog cards grid -->
		<div class="blog-cards-grid" role="list">

			<?php while ( $blog_query->have_posts() ) : $blog_query->the_post(); ?>
				<?php
				$thumb    = get_the_post_thumbnail_url( get_the_ID(), 'sbmt-blog-thumb' );
				$category = get_the_category();
				$cat_name = ! empty( $category ) ? $category[0]->name : '';
				$cat_url  = ! empty( $category ) ? get_category_link( $category[0]->term_id ) : '';
				$read_time= sbmt_reading_time( get_the_ID() );
				?>
				<article class="blog-card" role="listitem">
					<a href="<?php the_permalink(); ?>" class="blog-card-image-link" tabindex="-1" aria-hidden="true">
						<?php if ( $thumb ) : ?>
							<img src="<?php echo esc_url( $thumb ); ?>"
							     alt="<?php the_title_attribute(); ?>"
							     class="blog-card-image"
							     loading="lazy"
							     width="480" height="320">
						<?php else : ?>
							<div class="blog-card-image-placeholder">
								<span class="material-icons-round" aria-hidden="true">image</span>
							</div>
						<?php endif; ?>
					</a>

					<div class="blog-card-content">
						<div class="blog-card-meta">
							<?php if ( $cat_name ) : ?>
								<a href="<?php echo esc_url( $cat_url ); ?>" class="blog-card-category text-sm">
									<?php echo esc_html( $cat_name ); ?>
								</a>
							<?php endif; ?>
							<time class="blog-card-date text-sm" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
								<?php echo esc_html( get_the_date() ); ?>
							</time>
							<span class="blog-card-read-time text-sm"><?php echo esc_html( $read_time ); ?></span>
						</div>

						<h3 class="blog-card-title">
							<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
						</h3>

						<p class="blog-card-excerpt text-sm">
							<?php echo esc_html( get_the_excerpt() ); ?>
						</p>

						<a href="<?php the_permalink(); ?>" class="blog-card-read-more text-sm" aria-label="<?php printf( esc_attr__( 'Lire : %s', 'sb-marketing-theme' ), get_the_title() ); ?>">
							<?php esc_html_e( 'Lire l\'article', 'sb-marketing-theme' ); ?>
							<span class="material-icons-round" aria-hidden="true" style="font-size:16px;vertical-align:middle;">arrow_forward</span>
						</a>
					</div>
				</article>

			<?php endwhile; ?>
			<?php wp_reset_postdata(); ?>

		</div>
		<!-- /Blog cards grid -->

	</div>
</section>
