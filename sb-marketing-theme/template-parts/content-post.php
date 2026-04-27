<?php
/**
 * Blog post card — used in index.php, archive.php, search.php.
 *
 * @package SBMarketingTheme
 */

$thumb     = get_the_post_thumbnail_url( get_the_ID(), 'sbmt-blog-thumb' );
$cats      = get_the_category();
$cat       = ! empty( $cats ) ? $cats[0] : null;
$read_time = sbmt_reading_time( get_the_ID() );
?>

<article id="post-<?php the_ID(); ?>" <?php post_class( 'blog-card' ); ?> role="listitem">

	<!-- Thumbnail -->
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

	<!-- Card content -->
	<div class="blog-card-content">
		<div class="blog-card-meta">
			<?php if ( $cat ) : ?>
				<a href="<?php echo esc_url( get_category_link( $cat ) ); ?>" class="blog-card-category text-sm">
					<?php echo esc_html( $cat->name ); ?>
				</a>
			<?php endif; ?>
			<time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>" class="blog-card-date text-sm">
				<?php echo esc_html( get_the_date() ); ?>
			</time>
			<span class="blog-card-read-time text-sm"><?php echo esc_html( $read_time ); ?></span>
		</div>

		<h2 class="blog-card-title">
			<a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
		</h2>

		<p class="blog-card-excerpt text-sm">
			<?php echo esc_html( get_the_excerpt() ); ?>
		</p>

		<a href="<?php the_permalink(); ?>"
		   class="blog-card-read-more text-sm"
		   aria-label="<?php printf( esc_attr__( 'Lire : %s', 'sb-marketing-theme' ), get_the_title() ); ?>">
			<?php esc_html_e( 'Lire l\'article', 'sb-marketing-theme' ); ?>
			<span class="material-icons-round" aria-hidden="true" style="font-size:16px;vertical-align:middle;">arrow_forward</span>
		</a>
	</div>

</article>
