<?php
/**
 * Homepage — Marquee / Ticker section
 * Scrolling strip of images with CSS animation.
 *
 * @package SBMarketingTheme
 */

// Collect up to 6 marquee images from Customizer; fallback to theme assets
$images = [];
for ( $i = 1; $i <= 6; $i++ ) {
	$url = get_theme_mod( "sbmt_marquee_image_{$i}", '' );
	if ( ! $url ) {
		// Try theme asset fallback (e.g., climanova-ticker-01.png through -05.png)
		$asset = "climanova-ticker-0{$i}.png";
		$path  = SBMT_DIR . '/assets/images/' . $asset;
		if ( file_exists( $path ) ) {
			$url = sbmt_asset_img( $asset );
		}
	}
	if ( $url ) {
		$images[] = $url;
	}
}

// Also check the widget area
$has_widget = is_active_sidebar( 'home-marquee' );

if ( ! $images && ! $has_widget ) {
	return; // Nothing to show
}
?>

<section class="marquee-section" aria-label="<?php esc_attr_e( 'Galerie défilante', 'sb-marketing-theme' ); ?>" aria-hidden="true">
	<div class="marquee-wrapper">
		<div class="marquee-track" role="presentation">

			<?php if ( $has_widget ) : ?>
				<?php dynamic_sidebar( 'home-marquee' ); ?>
			<?php elseif ( $images ) : ?>
				<!-- Duplicate items for infinite scroll effect -->
				<?php foreach ( [ $images, $images ] as $set ) : ?>
					<?php foreach ( $set as $img_url ) : ?>
						<div class="marquee-item">
							<img src="<?php echo esc_url( $img_url ); ?>"
							     alt=""
							     class="marquee-image"
							     loading="lazy"
							     width="320" height="240"
							     aria-hidden="true">
						</div>
					<?php endforeach; ?>
				<?php endforeach; ?>
			<?php endif; ?>

		</div>
	</div>
</section>
