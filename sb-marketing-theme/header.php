<!DOCTYPE html>
<html <?php language_attributes(); ?> class="w-mod-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class( 'page-wrapper' ); ?>>
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
				$maps_url = sbmt_mod( 'sbmt_maps_url', '#' );
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
     ═══════════════════════════════════════════════ -->
<header class="header w-nav" role="banner" id="site-header">
	<div class="container">
		<div class="menu-wrapper">

			<!-- Logo -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
			   class="nav-brand w-nav-brand"
			   aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — <?php esc_attr_e( 'Accueil', 'sb-marketing-theme' ); ?>">
				<?php sbmt_the_logo( 'climanova-logo.png', 'nav-logo' ); ?>
			</a>

			<!-- Desktop navigation -->
			<nav role="navigation" class="nav-wrapper w-nav-menu" id="primary-nav" aria-label="<?php esc_attr_e( 'Navigation principale', 'sb-marketing-theme' ); ?>">
				<div class="nav-main-menu">

					<!-- Accueil -->
					<div class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"
						   class="nav-menu-link<?php echo is_front_page() ? ' w--current' : ''; ?>"
						   <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'Accueil', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

					<!-- À propos -->
					<div class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/a-propos' ) ); ?>"
						   class="nav-menu-link"
						   <?php echo is_page( 'a-propos' ) ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'À propos', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

					<!-- Services dropdown -->
					<div class="nav-services-dropdown w-dropdown" data-delay="400" data-hover="true">
						<div class="nav-service-dropdown-toggle w-dropdown-toggle" aria-haspopup="true" aria-expanded="false">
							<div class="nav-menu-item">
								<a href="<?php echo esc_url( home_url( '/services' ) ); ?>" class="nav-menu-link">
									<?php esc_html_e( 'Services', 'sb-marketing-theme' ); ?>
								</a>
								<div class="nav-border"></div>
							</div>
							<div class="navbar-service-dropdown-icon">
								<div class="service-dropdown-icon w-icon-dropdown-toggle"></div>
							</div>
						</div>

						<nav class="nav-service-dropdown-list w-dropdown-list" aria-label="<?php esc_attr_e( 'Sous-menu Services', 'sb-marketing-theme' ); ?>">
							<?php if ( has_nav_menu( 'services-menu' ) ) : ?>
								<?php
								wp_nav_menu( [
									'theme_location' => 'services-menu',
									'container'      => false,
									'items_wrap'     => '<div class="nav-service-list-wrapper"><div role="list" class="nav-service-list-wrap">%3$s</div></div>',
									'walker'         => new SBMT_Services_Walker(),
								] );
								?>
							<?php else : ?>
								<!-- Default services list if menu not assigned -->
								<div class="nav-service-list-wrapper">
									<div role="list" class="nav-service-list-wrap">
										<?php
										$default_services = [
											[ 'Climatisation',           home_url( '/services/climatisation' ) ],
											[ 'Chauffage',               home_url( '/services/chauffage' ) ],
											[ 'Électricité',             home_url( '/services/electricite' ) ],
											[ 'Plomberie',               home_url( '/services/plomberie' ) ],
											[ 'Rénovation énergétique',  home_url( '/services/renovation-energetique' ) ],
											[ 'Plomberie & Fuites',      home_url( '/services/plomberie-fuites' ) ],
										];
										foreach ( $default_services as [ $name, $url ] ) :
											?>
											<div role="listitem" class="w-dyn-item">
												<div class="nav-services-list-item">
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
					<div class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/realisations' ) ); ?>"
						   class="nav-menu-link"
						   <?php echo is_post_type_archive( 'climanova_project' ) ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'Réalisations', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

					<!-- Blog -->
					<div class="nav-menu-item">
						<a href="<?php echo esc_url( home_url( '/blog' ) ); ?>"
						   class="nav-menu-link"
						   <?php echo ( is_home() || is_category() || is_tag() ) ? 'aria-current="page"' : ''; ?>>
							<?php esc_html_e( 'Blog', 'sb-marketing-theme' ); ?>
						</a>
						<div class="nav-border"></div>
					</div>

				</div><!-- /nav-main-menu -->

				<!-- CTA button inside nav -->
				<div class="nav-cta-wrap">
					<?php sbmt_button(
						sbmt_mod( 'sbmt_hero_btn_text', 'Demander un devis' ),
						sbmt_mod( 'sbmt_hero_btn_url',  '/contact' ),
						'nav-cta-button'
					); ?>
				</div>

			</nav>
			<!-- /Desktop navigation -->

			<!-- Mobile hamburger button -->
			<button class="nav-hamburger" id="nav-hamburger" aria-controls="primary-nav" aria-expanded="false" aria-label="<?php esc_attr_e( 'Ouvrir le menu', 'sb-marketing-theme' ); ?>">
				<span></span>
				<span></span>
				<span></span>
			</button>

		</div><!-- /menu-wrapper -->
	</div>
</header>
<!-- /HEADER -->

<main id="main-content">
<?php
// ── Simple Walker for Services dropdown nav ───────────────────────────────────
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
