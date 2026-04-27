<?php
/**
 * Single post template.
 *
 * @package SBMarketingTheme
 */

get_header();
?>

<?php while ( have_posts() ) : the_post(); ?>

	<!-- Post hero -->
	<div class="page-hero">
		<div class="container">
			<div class="post-meta-top">
				<?php
				$cats = get_the_category();
				if ( $cats ) :
					foreach ( $cats as $cat ) :
						?>
						<a href="<?php echo esc_url( get_category_link( $cat ) ); ?>" class="post-category-badge">
							<?php echo esc_html( $cat->name ); ?>
						</a>
					<?php endforeach; ?>
				<?php endif; ?>
				<span class="post-read-time"><?php echo esc_html( sbmt_reading_time() ); ?></span>
			</div>
			<h1><?php the_title(); ?></h1>
			<div class="post-meta-row">
				<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="post-date">
					<?php echo esc_html( get_the_date() ); ?>
				</time>
				<span class="post-author">
					<?php
					printf(
						/* translators: %s: author name */
						esc_html__( 'Par %s', 'sb-marketing-theme' ),
						'<a href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( get_the_author() ) . '</a>'
					);
					?>
				</span>
			</div>
		</div>
	</div>

	<!-- Featured image -->
	<?php if ( has_post_thumbnail() ) : ?>
		<div class="single-featured-image-wrap">
			<div class="container">
				<?php the_post_thumbnail( 'sbmt-blog-thumb-large', [ 'class' => 'single-featured-image', 'alt' => get_the_title() ] ); ?>
			</div>
		</div>
	<?php endif; ?>

	<!-- Post content -->
	<div class="page-content-wrap">
		<div class="container single-content-container">
			<div class="single-content-wrap">

				<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-content' ); ?>>
					<?php the_content(); ?>
					<?php
					wp_link_pages( [
						'before' => '<div class="page-links">' . esc_html__( 'Pages :', 'sb-marketing-theme' ),
						'after'  => '</div>',
					] );
					?>
				</article>

				<!-- Tags -->
				<?php
				$tags = get_the_tags();
				if ( $tags ) :
					?>
					<div class="post-tags">
						<span class="post-tags-label"><?php esc_html_e( 'Tags :', 'sb-marketing-theme' ); ?></span>
						<?php foreach ( $tags as $tag ) : ?>
							<a href="<?php echo esc_url( get_tag_link( $tag ) ); ?>" class="post-tag">
								<?php echo esc_html( $tag->name ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>

				<!-- Post navigation -->
				<nav class="post-navigation" aria-label="<?php esc_attr_e( 'Navigation entre articles', 'sb-marketing-theme' ); ?>">
					<?php
					the_post_navigation( [
						'prev_text' => '<span class="nav-label">' . esc_html__( '← Article précédent', 'sb-marketing-theme' ) . '</span><span class="nav-title">%title</span>',
						'next_text' => '<span class="nav-label">' . esc_html__( 'Article suivant →', 'sb-marketing-theme' ) . '</span><span class="nav-title">%title</span>',
					] );
					?>
				</nav>

			</div><!-- /single-content-wrap -->
		</div>
	</div>

	<!-- Comments -->
	<?php
	if ( comments_open() || get_comments_number() ) {
		comments_template();
	}
	?>

<?php endwhile; ?>

<!-- Related posts (same category) -->
<?php
$cats = get_the_category( get_the_ID() );
if ( $cats ) {
	$related = new WP_Query( [
		'category__in'   => [ $cats[0]->term_id ],
		'post__not_in'   => [ get_the_ID() ],
		'posts_per_page' => 3,
		'post_status'    => 'publish',
	] );
	if ( $related->have_posts() ) :
		?>
		<div class="related-posts-section">
			<div class="container">
				<h3 class="related-posts-title"><?php esc_html_e( 'Articles similaires', 'sb-marketing-theme' ); ?></h3>
				<div class="blog-grid">
					<?php while ( $related->have_posts() ) : $related->the_post(); ?>
						<?php get_template_part( 'template-parts/content', 'post' ); ?>
					<?php endwhile; ?>
				</div>
			</div>
		</div>
		<?php
		wp_reset_postdata();
	endif;
}
?>

<?php get_footer(); ?>
