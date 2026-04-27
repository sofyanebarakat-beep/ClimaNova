</main><!-- /#main-content -->

<!-- ══════════════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════════ -->
<footer class="footer-section" role="contentinfo">
	<div class="container">
		<div class="footer-content-wrapper">

			<!-- Column 1: Logo + description + newsletter -->
			<div class="footer-brand-col">

				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
				   class="footer-logo-link"
				   aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — <?php esc_attr_e( 'Accueil', 'sb-marketing-theme' ); ?>">
					<?php sbmt_the_footer_logo(); ?>
				</a>

				<p class="footer-description">
					<?php echo esc_html( sbmt_mod( 'sbmt_footer_description', 'ClimaNova Energie assure l\'installation et l\'entretien de vos équipements à Nice et en région PACA — climatisation, électricité, plomberie et chauffage.' ) ); ?>
				</p>

				<!-- Newsletter signup -->
				<div class="footer-newsletter-block">
					<p class="footer-newsletter-title">
						<?php echo esc_html( sbmt_mod( 'sbmt_footer_newsletter_title', 'Recevez nos conseils et offres exclusives !' ) ); ?>
					</p>
					<?php
					// If MailChimp for WordPress / WPForms shortcode is available, insert it here.
					// Otherwise, show a basic HTML form (replace with your plugin shortcode).
					?>
					<form class="footer-newsletter-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'sbmt_newsletter', 'sbmt_newsletter_nonce' ); ?>
						<input type="hidden" name="action" value="sbmt_newsletter_subscribe">
						<div class="newsletter-input-wrap">
							<input type="email"
							       name="sbmt_email"
							       class="footer-newsletter-input"
							       placeholder="<?php esc_attr_e( 'Votre adresse e-mail', 'sb-marketing-theme' ); ?>"
							       required
							       aria-label="<?php esc_attr_e( 'Adresse e-mail', 'sb-marketing-theme' ); ?>">
							<button type="submit" class="footer-newsletter-btn button-primary w-inline-block">
								<?php esc_html_e( 'S\'inscrire', 'sb-marketing-theme' ); ?>
							</button>
						</div>
					</form>
				</div>

			</div>
			<!-- /Column 1 -->

			<!-- Column 2: Services nav -->
			<div class="footer-nav-col">
				<h4 class="footer-col-title"><?php esc_html_e( 'Services', 'sb-marketing-theme' ); ?></h4>
				<?php if ( has_nav_menu( 'footer-services' ) ) : ?>
					<?php
					wp_nav_menu( [
						'theme_location' => 'footer-services',
						'container'      => false,
						'menu_class'     => 'footer-nav-list',
						'link_before'    => '',
						'link_after'     => '',
						'depth'          => 1,
						'walker'         => new SBMT_Footer_Walker(),
					] );
					?>
				<?php else : ?>
					<ul class="footer-nav-list">
						<?php
						$services = [
							[ 'Climatisation',          home_url( '/services/climatisation' ) ],
							[ 'Chauffage',              home_url( '/services/chauffage' ) ],
							[ 'Électricité',            home_url( '/services/electricite' ) ],
							[ 'Plomberie',              home_url( '/services/plomberie' ) ],
							[ 'Rénovation énergétique', home_url( '/services/renovation-energetique' ) ],
							[ 'Plomberie & Fuites',     home_url( '/services/plomberie-fuites' ) ],
						];
						foreach ( $services as [ $label, $url ] ) :
							?>
							<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<!-- /Column 2 -->

			<!-- Column 3: Explorer nav -->
			<div class="footer-nav-col">
				<h4 class="footer-col-title"><?php esc_html_e( 'Explorer', 'sb-marketing-theme' ); ?></h4>
				<?php if ( has_nav_menu( 'footer-explore' ) ) : ?>
					<?php
					wp_nav_menu( [
						'theme_location' => 'footer-explore',
						'container'      => false,
						'menu_class'     => 'footer-nav-list',
						'depth'          => 1,
						'walker'         => new SBMT_Footer_Walker(),
					] );
					?>
				<?php else : ?>
					<ul class="footer-nav-list">
						<?php
						$explore = [
							[ 'À propos',    home_url( '/a-propos' ) ],
							[ 'Réalisations', home_url( '/realisations' ) ],
							[ 'Blog',         home_url( '/blog' ) ],
							[ 'FAQ',          home_url( '/faq' ) ],
							[ 'Nous contacter', home_url( '/contact' ) ],
						];
						foreach ( $explore as [ $label, $url ] ) :
							?>
							<li><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $label ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
			<!-- /Column 3 -->

			<!-- Column 4: Contact info -->
			<div class="footer-contact-col">
				<h4 class="footer-col-title"><?php esc_html_e( 'Contact', 'sb-marketing-theme' ); ?></h4>
				<ul class="footer-contact-list">
					<?php
					$phone   = sbmt_mod( 'sbmt_phone', '+33 4 93 00 00 00' );
					$email   = sbmt_mod( 'sbmt_email', 'contact@climanova-energie.fr' );
					$address = sbmt_mod( 'sbmt_address', '12 Rue de la Paix, 06000 Nice' );
					?>
					<li>
						<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>">
							<?php echo esc_html( $phone ); ?>
						</a>
					</li>
					<li>
						<a href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>">
							<?php echo esc_html( antispambot( $email ) ); ?>
						</a>
					</li>
					<li><?php echo esc_html( $address ); ?></li>
				</ul>

				<!-- Social icons -->
				<?php
				$facebook  = get_theme_mod( 'sbmt_facebook_url', '' );
				$instagram = get_theme_mod( 'sbmt_instagram_url', '' );
				$linkedin  = get_theme_mod( 'sbmt_linkedin_url', '' );
				if ( $facebook || $instagram || $linkedin ) :
					?>
					<div class="footer-social-block">
						<p class="footer-col-title"><?php esc_html_e( 'Suivez-nous', 'sb-marketing-theme' ); ?></p>
						<div class="footer-social-links">
							<?php if ( $facebook ) : ?>
								<a href="<?php echo esc_url( $facebook ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
									<span class="material-icons-round">facebook</span>
								</a>
							<?php endif; ?>
							<?php if ( $instagram ) : ?>
								<a href="<?php echo esc_url( $instagram ); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
									<span class="material-icons-round">photo_camera</span>
								</a>
							<?php endif; ?>
							<?php if ( $linkedin ) : ?>
								<a href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
									<span class="material-icons-round">business</span>
								</a>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
			<!-- /Column 4 -->

		</div><!-- /footer-content-wrapper -->

		<!-- Bottom bar -->
		<div class="footer-bottom-bar">
			<div class="footer-bottom-left">
				<span class="footer-copyright">
					<?php echo esc_html( sbmt_mod( 'sbmt_footer_copyright', '© Copyright ' . gmdate( 'Y' ) . ', Tous droits réservés — ClimaNova Energie' ) ); ?>
				</span>
				<?php
				$made_by     = sbmt_mod( 'sbmt_footer_made_by', 'Made by SB Marketing' );
				$made_by_url = sbmt_mod( 'sbmt_footer_made_by_url', 'https://sbmarketing.fr' );
				?>
				<span class="footer-made-by">
					<a href="<?php echo esc_url( $made_by_url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php echo esc_html( $made_by ); ?>
					</a>
				</span>
			</div>
			<div class="footer-bottom-right">
				<?php
				$privacy_url = sbmt_mod( 'sbmt_footer_privacy_url', '/politique-de-confidentialite' );
				$terms_url   = sbmt_mod( 'sbmt_footer_terms_url', '/conditions-generales' );
				?>
				<a href="<?php echo esc_url( $privacy_url ); ?>" class="footer-legal-link">
					<?php esc_html_e( 'Politique de confidentialité', 'sb-marketing-theme' ); ?>
				</a>
				<a href="<?php echo esc_url( $terms_url ); ?>" class="footer-legal-link">
					<?php esc_html_e( 'Conditions générales', 'sb-marketing-theme' ); ?>
				</a>
			</div>
		</div>
		<!-- /Bottom bar -->

	</div>
</footer>
<!-- /FOOTER -->

<?php wp_footer(); ?>

<?php
// ── Footer nav walker ─────────────────────────────────────────────────────────
class SBMT_Footer_Walker extends Walker_Nav_Menu {
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}

	public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
		$item  = $data_object;
		$url   = $item->url;
		$title = apply_filters( 'the_title', $item->title, $item->ID );
		$output .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a></li>';
	}

	public function end_el( &$output, $data_object, $depth = 0, $args = null ) {}
}
?>

</body>
</html>
