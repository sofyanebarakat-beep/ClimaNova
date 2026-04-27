<?php
/**
 * Homepage — CTA (Call To Action) section
 *
 * @package SBMarketingTheme
 */

$title    = sbmt_mod( 'sbmt_cta_title',    'Votre logement mérite le meilleur — faites confiance à ClimaNova Energie' );
$subtitle = sbmt_mod( 'sbmt_cta_subtitle', 'Climatisation, électricité, plomberie et chauffage — une seule équipe pour tous vos besoins.' );
$btn_text = sbmt_mod( 'sbmt_cta_btn_text', 'Demander un devis gratuit' );
$btn_url  = sbmt_mod( 'sbmt_cta_btn_url',  home_url( '/contact' ) );
$img_url  = get_theme_mod( 'sbmt_cta_image', '' ) ?: sbmt_asset_img( 'climanova-cta.png' );
?>

<section class="cta-section" aria-label="<?php esc_attr_e( 'Contactez-nous', 'sb-marketing-theme' ); ?>">
	<div class="cta-gradient">
		<div class="container">
			<div class="cta-content-wrapper">

				<!-- Left: text -->
				<div class="cta-text-block">
					<h2 class="cta-title heading-style-h2" style="color:#fff;">
						<?php echo wp_kses_post( $title ); ?>
					</h2>
					<?php if ( $subtitle ) : ?>
						<p class="cta-subtitle text-size-medium" style="color:rgba(255,255,255,0.85);">
							<?php echo esc_html( $subtitle ); ?>
						</p>
					<?php endif; ?>
					<div class="cta-btn-wrap">
						<a href="<?php echo esc_url( $btn_url ); ?>"
						   class="button-primary w-inline-block cta-btn cta-btn--white">
							<div class="button-text-wrapper">
								<div class="button-text"><?php echo esc_html( $btn_text ); ?></div>
								<div class="button-text absolute"><?php echo esc_html( $btn_text ); ?></div>
							</div>
							<div class="button-arrow-wrap">
								<div class="button-arrow-block">
									<span class="material-icons-round button-right-arrow" aria-hidden="true">arrow_outward</span>
								</div>
							</div>
						</a>
					</div>
				</div>

				<!-- Right: image -->
				<div class="cta-image-block">
					<img src="<?php echo esc_url( $img_url ); ?>"
					     alt="<?php esc_attr_e( 'Technicien ClimaNova Energie', 'sb-marketing-theme' ); ?>"
					     class="cta-image"
					     loading="lazy"
					     width="480" height="400">
				</div>

			</div>
		</div>
	</div>
</section>
