<?php
/**
 * SB Marketing Theme — header.php
 * Pixel-perfect WordPress conversion of the Webflow header.
 *
 * @package SBMarketingTheme
 */

// ── Walker: must be declared before wp_nav_menu() is called ──────────────────
if ( ! class_exists( 'SBMT_Services_Walker' ) ) {
	class SBMT_Services_Walker extends Walker_Nav_Menu {
		public function start_lvl( &$output, $depth = 0, $args = null ) {}
		public function end_lvl( &$output, $depth = 0, $args = null ) {}

		public function start_el( &$output, $data_object, $depth = 0, $args = null, $current_object_id = 0 ) {
			$item   = $data_object;
			$url    = $item->url;
			$title  = apply_filters( 'the_title', $item->title, $item->ID );
			$output .= '<div role="listitem" class="w-dyn-item">
				<div class="nav-services-list-item">
					<a href="' . esc_url( $url ) . '" class="nav-list-item-link w-dropdown-link">' . esc_html( $title ) . '</a>
					<div class="nav-list-item-icon-wrap">
						<div class="nav-list-service-icon w-embed">' . sbmt_arrow_svg() . '</div>
					</div>
				</div>
			</div>';
		}

		public function end_el( &$output, $data_object, $depth = 0, $args = null ) {}
	}
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="w-mod-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div class="page-wrapper">
<?php wp_body_open(); ?>

<a class="skip-link sr-only" href="#main-content"><?php esc_html_e( 'Aller au contenu principal', 'sb-marketing-theme' ); ?></a>

<!-- ══════════════════════════════════════════════════
     TOP BAR
     ═══════════════════════════════════════════════ -->
<div class="top-bar">
	<div class="container">
		<div class="top-bar-content-wrapper">

			<!-- Left: address + phone -->
			<div class="top-bar-text-block-wrap">

				<?php
				$maps_url = sbmt_mod( 'sbmt_maps_url', 'https://www.google.com/maps/search/12+Rue+de+la+Paix,+06000+Nice,+France' );
				$address  = sbmt_mod( 'sbmt_address', '12 Rue de la Paix, 06000 Nice, France' );
				?>
				<a href="<?php echo esc_url( $maps_url ); ?>"
				   class="top-bar-text-block w-inline-block"
				   target="_blank"
				   rel="noopener noreferrer">
					<div class="top-bar-icon w-embed"><?php sbmt_icon_location(); ?></div>
					<div class="text-sm text-light"><?php echo esc_html( $address ); ?></div>
				</a>

				<?php $phone = sbmt_mod( 'sbmt_phone', '+33 4 93 00 00 00' ); ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"
				   class="top-bar-text-block w-inline-block">
					<div class="top-bar-icon w-embed"><?php sbmt_icon_phone(); ?></div>
					<div class="text-sm text-light"><?php echo esc_html( $phone ); ?></div>
				</a>

			</div>

			<!-- Right: certification badge -->
			<div class="top-bar-text-block">
				<div class="top-bar-icon w-embed"><?php sbmt_icon_badge(); ?></div>
				<div class="text-sm text-light">
					<?php echo esc_html( sbmt_mod( 'sbmt_certification', 'Certifié RGE – Expert énergie & confort depuis 2010' ) ); ?>
				</div>
			</div>

		</div>
	</div>
</div>
<!-- /TOP BAR -->

<!-- ══════════════════════════════════════════════════
     HEADER / NAVIGATION
     Exact structure matches Webflow output.
     data-collapse="medium" triggers mobile nav at ≤991px.
     ═══════════════════════════════════════════════ -->
<div data-animation="default"
     data-collapse="medium"
     data-duration="400"
     data-easing="ease"
     data-easing2="ease"
     role="banner"
     class="header w-nav"
     id="site-header">

	<div class="container">
		<div class="menu-wrapper">

			<!-- Logo -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
			   <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>
			   class="nav-brand w-nav-brand<?php echo is_front_page() ? ' w--current' : ''; ?>">
				<?php sbmt_the_logo( 'climanova-logo.png', 'nav-logo' ); ?>
			</a>

			<!-- Desktop navigation -->
			<nav role="navigation"
			     class="nav-wrapper w-nav-menu"
			     id="primary-nav"
			     aria-label="<?php esc_attr_e( 'Navigation principale', 'sb-marketing-theme' ); ?>">

				<div class="nav-main-menu">

					<!-- Accueil -->
					<div data-w-id="nav-item-home" class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
						   class="nav-menu-link<?php echo is_front_page() ? ' w--current' : ''; ?>"
						   <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'Accueil', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

					<!-- À propos -->
					<div data-w-id="nav-item-about" class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/a-propos' ) ); ?>"
						   class="nav-menu-link<?php echo is_page( 'a-propos' ) ? ' w--current' : ''; ?>"
						   <?php echo is_page( 'a-propos' ) ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'À propos', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

					<!-- Services dropdown -->
					<div data-delay="800"
					     data-hover="true"
					     data-w-id="nav-item-services"
					     class="nav-services-dropdown w-dropdown">

						<div class="nav-service-dropdown-toggle w-dropdown-toggle"
						     aria-haspopup="true"
						     aria-expanded="false">
							<div class="nav-menu-item">
								<a href="<?php echo esc_url( home_url( '/services' ) ); ?>"
								   class="nav-menu-link<?php echo is_page( 'services' ) ? ' w--current' : ''; ?>">
									<?php esc_html_e( 'Services', 'sb-marketing-theme' ); ?>
								</a>
								<div class="nav-border"></div>
							</div>
							<div class="navbar-service-dropdown-icon">
								<div class="service-dropdown-icon w-icon-dropdown-toggle"></div>
							</div>
						</div>

						<nav class="nav-service-dropdown-list w-dropdown-list"
						     aria-label="<?php esc_attr_e( 'Sous-menu Services', 'sb-marketing-theme' ); ?>">

							<?php if ( has_nav_menu( 'services-menu' ) ) : ?>
								<?php
								wp_nav_menu( [
									'theme_location' => 'services-menu',
									'container'      => false,
									'items_wrap'     => '<div class="nav-service-list-wrapper w-dyn-list"><div role="list" class="nav-service-list-wrap w-dyn-items">%3$s</div></div>',
									'walker'         => new SBMT_Services_Walker(),
								] );
								?>
							<?php else : ?>
								<div class="nav-service-list-wrapper w-dyn-list">
									<div role="list" class="nav-service-list-wrap w-dyn-items">
										<?php
										$default_services = [
											[ 'Climatisation',          home_url( '/services/climatisation' ) ],
											[ 'Chauffage',              home_url( '/services/chauffage' ) ],
											[ 'Électricité',            home_url( '/services/electricite' ) ],
											[ 'Plomberie',              home_url( '/services/plomberie' ) ],
											[ 'Rénovation énergétique', home_url( '/services/renovation-energetique' ) ],
											[ 'Plomberie & Fuites',     home_url( '/services/plomberie-fuites' ) ],
										];
										foreach ( $default_services as [ $name, $url ] ) :
											?>
											<div role="listitem" class="w-dyn-item">
												<div data-w-id="nav-services-list-item" class="nav-services-list-item">
													<a href="<?php echo esc_url( $url ); ?>" class="nav-list-item-link w-dropdown-link">
														<?php echo esc_html( $name ); ?>
													</a>
													<div class="nav-list-item-icon-wrap">
														<div class="nav-list-service-icon w-embed">
															<?php echo wp_kses_post( sbmt_arrow_svg() ); ?>
														</div>
													</div>
												</div>
											</div>
										<?php endforeach; ?>
									</div>
								</div>
							<?php endif; ?>

						</nav>
					</div>
					<!-- /Services dropdown -->

					<!-- Réalisations -->
					<div data-w-id="nav-item-projects" class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/realisations' ) ); ?>"
						   class="nav-menu-link<?php echo is_post_type_archive( 'climanova_project' ) ? ' w--current' : ''; ?>"
						   <?php echo is_post_type_archive( 'climanova_project' ) ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'Réalisations', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

					<!-- Blog -->
					<div data-w-id="nav-item-blog" class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/blog' ) ); ?>"
						   class="nav-menu-link<?php echo ( is_home() || is_category() || is_tag() || is_singular( 'post' ) ) ? ' w--current' : ''; ?>"
						   <?php echo ( is_home() || is_category() || is_tag() || is_singular( 'post' ) ) ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'Blog', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

				</div><!-- /nav-main-menu -->

				<!-- CTA button -->
				<div class="nav-cta-wrap">
					<?php
					sbmt_button(
						sbmt_mod( 'sbmt_hero_btn_text', 'Demander un devis' ),
						sbmt_mod( 'sbmt_hero_btn_url', home_url( '/contact' ) ),
						'nav-cta-button'
					);
					?>
				</div>

			</nav>
			<!-- /Desktop navigation -->

			<!-- Mobile nav panel — class "nav-modile" matches the original Webflow typo -->
			<div class="nav-modile">
				<div class="nav-mobile-content-wrapper">

					<div class="nav-mobile-content-inner">
						<h6 class="h5-style"><?php esc_html_e( 'Aperçu des pages', 'sb-marketing-theme' ); ?></h6>
						<div class="nav-mobile-description">
							<p class="text-md"><?php esc_html_e( 'Simple à utiliser, il met en avant vos services, présente vos réalisations et construit une présence en ligne fiable.', 'sb-marketing-theme' ); ?></p>
						</div>
						<?php
						sbmt_button(
							sbmt_mod( 'sbmt_hero_btn_text', 'Nous contacter' ),
							sbmt_mod( 'sbmt_hero_btn_url', home_url( '/contact' ) ),
							''
						);
						?>
					</div>

					<div class="nav-mobile-list-wrapper">

						<!-- List 01: main pages -->
						<div id="w-node-nav-mobile-list-01" class="nav-mobile-list-01">

							<!-- Accueil -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M18.3346 8.74967L10.7367 2.3514C10.5309 2.17807 10.2704 2.08301 10.0013 2.08301C9.73222 2.08301 9.47172 2.17807 9.26589 2.3514L1.66797 8.74967" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M17.0846 7.91699V13.3337C17.0846 15.2883 17.0846 16.2657 16.5681 16.9389C16.4351 17.1122 16.2799 17.2674 16.1066 17.4004C15.4333 17.917 14.456 17.917 12.5013 17.917V14.167C12.5013 12.9885 12.5013 12.3992 12.1352 12.0331C11.7691 11.667 11.1798 11.667 10.0013 11.667C8.8228 11.667 8.23354 11.667 7.86742 12.0331C7.5013 12.3992 7.5013 12.9885 7.5013 14.167V17.917C5.54665 17.917 4.56934 17.917 3.89607 17.4004C3.72274 17.2674 3.56759 17.1122 3.43459 16.9389C2.91797 16.2657 2.91797 15.2883 2.91797 13.3337V7.91699" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
									   class="nav-list-item-link<?php echo is_front_page() ? ' w--current' : ''; ?>"
									   <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>>
										<?php esc_html_e( 'Accueil', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

							<!-- À propos -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M14.1654 7.08366C14.1654 4.78248 12.2999 2.91699 9.9987 2.91699C7.69751 2.91699 5.83203 4.78248 5.83203 7.08366C5.83203 9.38483 7.69751 11.2503 9.9987 11.2503C12.2999 11.2503 14.1654 9.38483 14.1654 7.08366Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M15.8346 17.0833C15.8346 13.8617 13.223 11.25 10.0013 11.25C6.77964 11.25 4.16797 13.8617 4.16797 17.0833" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/a-propos' ) ); ?>"
									   class="nav-list-item-link<?php echo is_page( 'a-propos' ) ? ' w--current' : ''; ?>">
										<?php esc_html_e( 'À propos', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

							<!-- Services -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M16.6654 18.333V15.833C16.6654 13.476 16.6654 12.2975 15.9331 11.5653C15.2009 10.833 14.0224 10.833 11.6654 10.833L9.9987 12.4997L8.33203 10.833C5.97501 10.833 4.7965 10.833 4.06426 11.5653C3.33203 12.2975 3.33203 13.476 3.33203 15.833V18.333" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M13.332 10.833V15.4163" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M7.08464 10.833V14.1663M7.08464 14.1663C8.00511 14.1663 8.7513 14.9125 8.7513 15.833V16.6663M7.08464 14.1663C6.16416 14.1663 5.41797 14.9125 5.41797 15.833V16.6663" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M12.9154 5.41699V4.58366C12.9154 2.97283 11.6095 1.66699 9.9987 1.66699C8.38786 1.66699 7.08203 2.97283 7.08203 4.58366V5.41699C7.08203 7.02783 8.38786 8.33366 9.9987 8.33366C11.6095 8.33366 12.9154 7.02783 12.9154 5.41699Z" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M13.957 16.042C13.957 16.3872 13.6772 16.667 13.332 16.667C12.9869 16.667 12.707 16.3872 12.707 16.042C12.707 15.6968 12.9869 15.417 13.332 15.417C13.6772 15.417 13.957 15.6968 13.957 16.042Z" stroke="currentColor" stroke-width="1.25"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/services' ) ); ?>"
									   class="nav-list-item-link<?php echo is_page( 'services' ) ? ' w--current' : ''; ?>">
										<?php esc_html_e( 'Services', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

							<!-- Blog -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M16.4596 9.16699V8.33366C16.4596 5.19096 16.4596 3.61962 15.4833 2.6433C14.5069 1.66699 12.9356 1.66699 9.79294 1.66699H8.95969C5.81699 1.66699 4.24565 1.66699 3.26934 2.64329C2.29304 3.61959 2.29302 5.19093 2.29299 8.3336L2.29297 11.667C2.29294 14.8097 2.29293 16.381 3.2692 17.3573C4.24551 18.3336 5.81691 18.3337 8.9596 18.3337" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M6.04297 5.83301H12.7096M6.04297 9.99967H12.7096" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
											<path d="M11.043 17.356V18.3337H12.0208C12.362 18.3337 12.5326 18.3337 12.6859 18.2702C12.8393 18.2066 12.9599 18.086 13.2011 17.8448L17.2208 13.8248C17.4483 13.5973 17.5621 13.4836 17.6229 13.3609C17.7386 13.1274 17.7386 12.8533 17.6229 12.6198C17.5621 12.4971 17.4483 12.3833 17.2208 12.1558C16.9932 11.9283 16.8795 11.8146 16.7567 11.7537C16.5232 11.6381 16.2491 11.6381 16.0156 11.7537C15.8929 11.8146 15.7791 11.9283 15.5516 12.1558L11.5319 16.1758C11.2906 16.417 11.1701 16.5376 11.1066 16.6909C11.043 16.8443 11.043 17.0148 11.043 17.356Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/blog' ) ); ?>"
									   class="nav-list-item-link<?php echo ( is_home() || is_category() || is_tag() || is_singular( 'post' ) ) ? ' w--current' : ''; ?>">
										<?php esc_html_e( 'Blog', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

							<!-- Contact -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M11.668 2.5V5M15.8346 4.16667L14.168 5.83333M17.5013 8.33333H15.0013" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M7.63188 4.76019L7.29633 4.00521C7.07693 3.51157 6.96723 3.26473 6.80317 3.07584C6.59756 2.83912 6.32956 2.66495 6.02973 2.57321C5.79048 2.5 5.52038 2.5 4.98018 2.5C4.18993 2.5 3.79479 2.5 3.46311 2.65191C3.07239 2.83085 2.71953 3.2194 2.57894 3.6255C2.45959 3.97024 2.49378 4.32453 2.56215 5.03308C3.28993 12.5752 7.42484 16.7101 14.9669 17.4378C15.6755 17.5062 16.0298 17.5404 16.3745 17.4211C16.7806 17.2805 17.1692 16.9276 17.3481 16.5369C17.5 16.2052 17.5 15.8101 17.5 15.0198C17.5 14.4796 17.5 14.2095 17.4268 13.9702C17.3351 13.6704 17.1609 13.4024 16.9242 13.1968C16.7352 13.0327 16.4884 12.9231 15.9948 12.7037L15.2398 12.3681C14.7052 12.1305 14.4379 12.0117 14.1663 11.9859C13.9063 11.9612 13.6443 11.9977 13.4009 12.0924C13.1467 12.1914 12.9219 12.3787 12.4725 12.7532C12.0251 13.126 11.8014 13.3124 11.5281 13.4122C11.2858 13.5007 10.9655 13.5336 10.7103 13.4959C10.4224 13.4535 10.2019 13.3358 9.76108 13.1001C8.38933 12.3671 7.63294 11.6107 6.89988 10.2389C6.66428 9.79808 6.54648 9.57758 6.50406 9.28975C6.46645 9.0345 6.49923 8.71417 6.58775 8.47192C6.6876 8.19857 6.87401 7.97488 7.24683 7.5275C7.62135 7.07807 7.80862 6.85335 7.90763 6.59909C8.00238 6.35578 8.03884 6.09367 8.01412 5.83373C7.98828 5.5621 7.86948 5.2948 7.63188 4.76019Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/contact' ) ); ?>"
									   class="nav-list-item-link<?php echo is_page( 'contact' ) ? ' w--current' : ''; ?>">
										<?php esc_html_e( 'Contact', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

						</div><!-- /nav-mobile-list-01 -->

						<!-- List 02: secondary pages -->
						<div class="nav-mobile-list-02">

							<!-- Réalisations -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M16.4596 9.16699V8.33366C16.4596 5.19096 16.4596 3.61962 15.4833 2.6433C14.5069 1.66699 12.9356 1.66699 9.79294 1.66699H8.95969C5.81699 1.66699 4.24565 1.66699 3.26934 2.64329C2.29304 3.61959 2.29302 5.19093 2.29299 8.3336L2.29297 11.667C2.29294 14.8097 2.29293 16.381 3.2692 17.3573C4.24551 18.3336 5.81691 18.3337 8.9596 18.3337" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M6.04297 5.83301H12.7096M6.04297 9.99967H12.7096" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
											<path d="M11.043 17.356V18.3337H12.0208C12.362 18.3337 12.5326 18.3337 12.6859 18.2702C12.8393 18.2066 12.9599 18.086 13.2011 17.8448L17.2208 13.8248C17.4483 13.5973 17.5621 13.4836 17.6229 13.3609C17.7386 13.1274 17.7386 12.8533 17.6229 12.6198C17.5621 12.4971 17.4483 12.3833 17.2208 12.1558C16.9932 11.9283 16.8795 11.8146 16.7567 11.7537C16.5232 11.6381 16.2491 11.6381 16.0156 11.7537C15.8929 11.8146 15.7791 11.9283 15.5516 12.1558L11.5319 16.1758C11.2906 16.417 11.1701 16.5376 11.1066 16.6909C11.043 16.8443 11.043 17.0148 11.043 17.356Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/realisations' ) ); ?>"
									   class="nav-list-item-link<?php echo is_post_type_archive( 'climanova_project' ) ? ' w--current' : ''; ?>">
										<?php esc_html_e( 'Réalisations', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

							<!-- FAQ -->
							<div class="nav-list-item">
								<div class="nav-list-item-icon-wrap">
									<div class="nav-list-item-icon w-embed">
										<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none">
											<path d="M1.66797 8.74968C1.66797 8.10735 1.67919 7.48033 1.70054 6.87493C1.77032 4.89703 1.80521 3.90808 2.60969 3.09755C3.41418 2.28702 4.43105 2.24351 6.4648 2.1565C7.58061 2.10876 8.76872 2.08301 10.0013 2.08301C11.2339 2.08301 12.422 2.10876 13.5378 2.1565C15.5715 2.24351 16.5885 2.28702 17.3929 3.09755C18.1974 3.90808 18.2323 4.89703 18.302 6.87493C18.3234 7.48033 18.3346 8.10735 18.3346 8.74968C18.3346 9.39201 18.3234 10.019 18.302 10.6244C18.2323 12.6023 18.1974 13.5913 17.3929 14.4018C16.5885 15.2123 15.5715 15.2558 13.5377 15.3428C12.9261 15.369 12.2928 15.3886 11.6424 15.4009C11.0248 15.4126 10.716 15.4185 10.4446 15.5218C10.1733 15.6252 9.94505 15.8209 9.48838 16.2124L7.67216 17.7698C7.56191 17.8643 7.42146 17.9163 7.27623 17.9163C6.94029 17.9163 6.66797 17.644 6.66797 17.3081V15.3513C6.59998 15.3485 6.53226 15.3458 6.46479 15.3428C4.43104 15.2558 3.41418 15.2123 2.60969 14.4018C1.80521 13.5913 1.77032 12.6023 1.70054 10.6244C1.67919 10.019 1.66797 9.39201 1.66797 8.74968Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M9.99219 12.5H10.0012" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
											<path d="M7.91797 7.08333C7.91797 5.93274 8.85072 5 10.0013 5C11.1519 5 12.0846 5.93274 12.0846 7.08333C12.0846 7.94607 11.5602 8.68633 10.8127 9.00275C10.3889 9.18217 10.0013 9.53975 10.0013 10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</div>
								</div>
								<div class="nav-list-item-link-wrap">
									<a href="<?php echo esc_url( home_url( '/faq' ) ); ?>"
									   class="nav-list-item-link<?php echo is_page( 'faq' ) ? ' w--current' : ''; ?>">
										<?php esc_html_e( 'FAQ', 'sb-marketing-theme' ); ?>
									</a>
									<div class="nav-list-border"></div>
								</div>
							</div>

						</div><!-- /nav-mobile-list-02 -->

					</div><!-- /nav-mobile-list-wrapper -->

				</div><!-- /nav-mobile-content-wrapper -->
			</div>
			<!-- /nav-modile -->

			<!-- Mobile hamburger button (custom, for JS hook) -->
			<button class="nav-hamburger"
			        id="nav-hamburger"
			        aria-controls="primary-nav"
			        aria-expanded="false"
			        aria-label="<?php esc_attr_e( 'Ouvrir le menu', 'sb-marketing-theme' ); ?>">
				<span></span>
				<span></span>
				<span></span>
			</button>

		</div><!-- /menu-wrapper -->
	</div><!-- /container -->
</div><!-- /header .w-nav -->
<!-- /HEADER -->

<main id="main-content">
