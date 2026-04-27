<?php
/**
 * Homepage — Hero section
 *
 * @package SBMarketingTheme
 */

$badge_text   = sbmt_mod( 'sbmt_hero_badge_text', '5 000+ clients satisfaits' );
$title        = sbmt_mod( 'sbmt_hero_title',    'Votre partenaire Énergie & Confort à Nice et en région PACA' );
$subtitle     = sbmt_mod( 'sbmt_hero_subtitle', 'Climatisation, électricité, plomberie et chauffage — nous assurons le bon fonctionnement de vos installations avec sérieux, réactivité et savoir-faire.' );
$btn_text     = sbmt_mod( 'sbmt_hero_btn_text', 'Demander un devis' );
$btn_url      = sbmt_mod( 'sbmt_hero_btn_url',  home_url( '/contact' ) );
$hero_img     = get_theme_mod( 'sbmt_hero_image',        '' );
$client_img_1 = get_theme_mod( 'sbmt_hero_client_img_1', '' );
$client_img_2 = get_theme_mod( 'sbmt_hero_client_img_2', '' );

// Fallback to theme asset images
if ( ! $hero_img )     $hero_img     = sbmt_asset_img( 'climanova-hero-image.png' );
if ( ! $client_img_1 ) $client_img_1 = sbmt_asset_img( 'climanova-client-01.png' );
if ( ! $client_img_2 ) $client_img_2 = sbmt_asset_img( 'climanova-client-02.png' );
?>

<section class="hero-section" aria-label="<?php esc_attr_e( 'Section principale', 'sb-marketing-theme' ); ?>">
	<div class="container">
		<div class="hero-content-wrapper">

			<!-- Left: text content -->
			<div class="hero-text-block">

				<!-- Badge -->
				<div class="hero-badge-wrap">
					<?php if ( $client_img_1 || $client_img_2 ) : ?>
						<div class="hero-client-images">
							<?php if ( $client_img_1 ) : ?>
								<img src="<?php echo esc_url( $client_img_1 ); ?>"
								     alt="<?php esc_attr_e( 'Clients satisfaits', 'sb-marketing-theme' ); ?>"
								     class="hero-client-img"
								     loading="lazy" width="40" height="40">
							<?php endif; ?>
							<?php if ( $client_img_2 ) : ?>
								<img src="<?php echo esc_url( $client_img_2 ); ?>"
								     alt="<?php esc_attr_e( 'Clients satisfaits', 'sb-marketing-theme' ); ?>"
								     class="hero-client-img"
								     loading="lazy" width="40" height="40">
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $badge_text ) : ?>
						<div class="hero-badge-text">
							<span class="material-icons-round hero-badge-icon" aria-hidden="true">verified</span>
							<?php echo esc_html( $badge_text ); ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Title -->
				<h1 class="hero-title heading-style-h1">
					<?php echo wp_kses_post( $title ); ?>
				</h1>

				<!-- Subtitle -->
				<?php if ( $subtitle ) : ?>
					<p class="hero-subtitle text-size-medium">
						<?php echo esc_html( $subtitle ); ?>
					</p>
				<?php endif; ?>

				<!-- CTA button -->
				<?php sbmt_button( $btn_text, $btn_url, 'hero-cta-btn' ); ?>

			</div>
			<!-- /Left -->

			<!-- Right: hero image -->
			<div class="hero-image-block">
				<img src="<?php echo esc_url( $hero_img ); ?>"
				     alt="<?php esc_attr_e( 'ClimaNova Energie — interventions à Nice', 'sb-marketing-theme' ); ?>"
				     class="hero-image"
				     loading="eager"
				     width="600" height="600">
			</div>
			<!-- /Right -->

		</div>
	</div>
</section>
