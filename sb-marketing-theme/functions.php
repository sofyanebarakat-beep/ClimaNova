<?php
/**
 * SB Marketing Theme — functions.php
 *
 * @package SBMarketingTheme
 * @author  SB Marketing <contact@sbmarketing.fr>
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Constants ────────────────────────────────────────────────────────────────
define( 'SBMT_VERSION', '1.0.0' );
define( 'SBMT_DIR',     get_template_directory() );
define( 'SBMT_URI',     get_template_directory_uri() );

// ── Theme Setup ──────────────────────────────────────────────────────────────
function sbmt_setup() {
	load_theme_textdomain( 'sb-marketing-theme', SBMT_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ] );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );

	// Custom logo
	add_theme_support( 'custom-logo', [
		'height'      => 80,
		'width'       => 220,
		'flex-width'  => true,
		'flex-height' => true,
		'header-text' => [ 'site-title', 'site-description' ],
	] );

	// Custom image sizes
	add_image_size( 'sbmt-service-card',      400,  520, true );
	add_image_size( 'sbmt-project-thumb',     600,  440, true );
	add_image_size( 'sbmt-blog-thumb',        480,  320, true );
	add_image_size( 'sbmt-blog-thumb-large',  800,  540, true );
	add_image_size( 'sbmt-testimonial-avatar', 80,   80, true );
	add_image_size( 'sbmt-hero',             1440,  900, false );
	add_image_size( 'sbmt-marquee',           400,  300, true );

	// Navigation menus
	register_nav_menus( [
		'primary'         => __( 'Menu principal',      'sb-marketing-theme' ),
		'services-menu'   => __( 'Menu services (nav)', 'sb-marketing-theme' ),
		'footer-services' => __( 'Footer — Services',   'sb-marketing-theme' ),
		'footer-explore'  => __( 'Footer — Explorer',   'sb-marketing-theme' ),
	] );
}
add_action( 'after_setup_theme', 'sbmt_setup' );

// ── Content Width ────────────────────────────────────────────────────────────
function sbmt_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'sbmt_content_width', 1200 );
}
add_action( 'after_setup_theme', 'sbmt_content_width', 0 );

// ── Widget Areas ─────────────────────────────────────────────────────────────
function sbmt_widgets_init() {
	$args_base = [
		'before_widget' => '<div id="%1$s" class="widget %2$s">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4 class="widget-title">',
		'after_title'   => '</h4>',
	];

	register_sidebar( array_merge( $args_base, [
		'name'        => __( 'Barre latérale', 'sb-marketing-theme' ),
		'id'          => 'sidebar-1',
		'description' => __( 'Widgets de la barre latérale.', 'sb-marketing-theme' ),
	] ) );

	register_sidebar( array_merge( $args_base, [
		'name'        => __( 'Footer — Colonne widgets 1', 'sb-marketing-theme' ),
		'id'          => 'footer-col-1',
		'description' => __( 'Zone de widgets dans le footer, colonne 1.', 'sb-marketing-theme' ),
	] ) );

	register_sidebar( array_merge( $args_base, [
		'name'        => __( 'Footer — Colonne widgets 2', 'sb-marketing-theme' ),
		'id'          => 'footer-col-2',
		'description' => __( 'Zone de widgets dans le footer, colonne 2.', 'sb-marketing-theme' ),
	] ) );

	register_sidebar( array_merge( $args_base, [
		'name'        => __( 'Accueil — Marquee', 'sb-marketing-theme' ),
		'id'          => 'home-marquee',
		'description' => __( 'Zone de widgets pour le bandeau défilant (page d\'accueil).', 'sb-marketing-theme' ),
	] ) );
}
add_action( 'widgets_init', 'sbmt_widgets_init' );

// ── Enqueue Scripts & Styles ──────────────────────────────────────────────────
function sbmt_enqueue_scripts() {
	// ---- Styles ----
	wp_enqueue_style(
		'repairly-webflow',
		SBMT_URI . '/assets/css/repairly.webflow.shared.c9f4e2b89.css',
		[],
		SBMT_VERSION
	);
	wp_enqueue_style(
		'material-icons-round',
		'https://fonts.googleapis.com/icon?family=Material+Icons+Round',
		[],
		null
	);
	wp_enqueue_style(
		'sbmt-brand',
		SBMT_URI . '/assets/css/brand.css',
		[ 'repairly-webflow' ],
		SBMT_VERSION
	);
	// Main theme stylesheet (style.css)
	wp_enqueue_style(
		'sbmt-style',
		get_stylesheet_uri(),
		[ 'repairly-webflow', 'sbmt-brand' ],
		SBMT_VERSION
	);
	// WP-specific overrides
	wp_enqueue_style(
		'sbmt-theme',
		SBMT_URI . '/assets/css/theme.css',
		[ 'sbmt-style' ],
		SBMT_VERSION
	);

	// ---- Scripts ----
	// Use the bundled jQuery from the original project so Webflow scripts work correctly
	wp_deregister_script( 'jquery' );
	wp_register_script(
		'jquery',
		SBMT_URI . '/assets/js/jquery-3.5.1.min.dc5e7f18c8.js',
		[],
		'3.5.1',
		false // Load in <head> before Webflow scripts need it
	);
	wp_enqueue_script( 'jquery' );

	// GSAP core
	wp_enqueue_script(
		'gsap',
		SBMT_URI . '/assets/js/gsap.min.js',
		[],
		SBMT_VERSION,
		true
	);

	// GSAP plugins
	wp_enqueue_script(
		'gsap-scroll-trigger',
		SBMT_URI . '/assets/js/ScrollTrigger.min.js',
		[ 'gsap' ],
		SBMT_VERSION,
		true
	);
	wp_enqueue_script(
		'gsap-split-text',
		SBMT_URI . '/assets/js/SplitText.min.js',
		[ 'gsap' ],
		SBMT_VERSION,
		true
	);

	// Webflow runtime (handles nav, dropdowns, sliders, animations)
	wp_enqueue_script(
		'webflow-chunk-1',
		SBMT_URI . '/assets/js/webflow.schunk.36b8fb49256177c8.js',
		[ 'jquery' ],
		SBMT_VERSION,
		true
	);
	wp_enqueue_script(
		'webflow-chunk-2',
		SBMT_URI . '/assets/js/webflow.schunk.51ea5130a0308f5f.js',
		[ 'jquery' ],
		SBMT_VERSION,
		true
	);
	wp_enqueue_script(
		'webflow-main',
		SBMT_URI . '/assets/js/webflow.610a0b52.d373f04e52caade1.js',
		[ 'jquery', 'webflow-chunk-1', 'webflow-chunk-2' ],
		SBMT_VERSION,
		true
	);

	// Custom theme JS (WP-native: mobile menu, dropdowns, marquee, etc.)
	wp_enqueue_script(
		'sbmt-main',
		SBMT_URI . '/assets/js/main.js',
		[ 'jquery' ],
		SBMT_VERSION,
		true
	);

	// Pass dynamic data to JS
	wp_localize_script( 'sbmt-main', 'sbmtData', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'sbmt_nonce' ),
		'homeUrl' => home_url( '/' ),
	] );

	// Comment reply
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'sbmt_enqueue_scripts' );

// ── Web Font Loader (Google Fonts — Instrument Sans) ─────────────────────────
function sbmt_webfont_loader() {
	?>
	<script type="text/javascript">
	(function(){
		var wf = document.createElement('script');
		wf.src = '<?php echo esc_url( SBMT_URI . '/assets/js/webfont.js' ); ?>';
		wf.type = 'text/javascript';
		wf.async = true;
		wf.onload = function(){
			WebFont.load({ google: { families: ['Instrument Sans:300,400,500,600,700'] } });
		};
		document.head.appendChild(wf);
	})();
	</script>
	<?php
}
add_action( 'wp_head', 'sbmt_webfont_loader', 1 );

// ── Local Business Schema (JSON-LD) ──────────────────────────────────────────
function sbmt_schema_markup() {
	if ( ! is_front_page() ) {
		return;
	}
	$phone   = get_theme_mod( 'sbmt_phone',   '+33 4 93 00 00 00' );
	$email   = get_theme_mod( 'sbmt_email',   'contact@climanova-energie.fr' );
	$address = get_theme_mod( 'sbmt_address', '12 Rue de la Paix, 06000 Nice' );
	$schema  = [
		'@context'    => 'https://schema.org',
		'@type'       => 'LocalBusiness',
		'name'        => get_bloginfo( 'name' ),
		'description' => get_bloginfo( 'description' ),
		'url'         => home_url( '/' ),
		'telephone'   => $phone,
		'email'       => $email,
		'address'     => [
			'@type'           => 'PostalAddress',
			'streetAddress'   => '12 Rue de la Paix',
			'addressLocality' => 'Nice',
			'addressRegion'   => 'Provence-Alpes-Côte d\'Azur',
			'postalCode'      => '06000',
			'addressCountry'  => 'FR',
		],
		'priceRange'      => '60€–800€',
		'aggregateRating' => [
			'@type'       => 'AggregateRating',
			'ratingValue' => '4.9',
			'reviewCount' => '25000',
		],
	];
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>' . "\n";
}
add_action( 'wp_head', 'sbmt_schema_markup' );

// ── Excerpt Length & More ────────────────────────────────────────────────────
function sbmt_excerpt_length( $length ) {
	return 20;
}
add_filter( 'excerpt_length', 'sbmt_excerpt_length', 999 );

function sbmt_excerpt_more( $more ) {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'sbmt_excerpt_more' );

// ── Reading Time Helper ───────────────────────────────────────────────────────
function sbmt_reading_time( $post_id = null ) {
	$content    = get_post_field( 'post_content', $post_id );
	$word_count = str_word_count( wp_strip_all_tags( $content ) );
	$minutes    = max( 1, (int) ceil( $word_count / 200 ) );
	/* translators: %d: number of minutes */
	return sprintf( _n( '%d min de lecture', '%d min de lecture', $minutes, 'sb-marketing-theme' ), $minutes );
}

// ── Admin: Show custom columns in CPT list tables ─────────────────────────────
function sbmt_service_columns( $cols ) {
	return [
		'cb'             => $cols['cb'],
		'title'          => __( 'Titre', 'sb-marketing-theme' ),
		'service_price'  => __( 'Prix', 'sb-marketing-theme' ),
		'service_rating' => __( 'Note', 'sb-marketing-theme' ),
		'thumbnail'      => __( 'Image', 'sb-marketing-theme' ),
		'date'           => $cols['date'],
	];
}
add_filter( 'manage_climanova_service_posts_columns', 'sbmt_service_columns' );

function sbmt_service_column_content( $col, $post_id ) {
	switch ( $col ) {
		case 'service_price':
			echo esc_html( get_post_meta( $post_id, '_service_price', true ) ?: '—' );
			break;
		case 'service_rating':
			echo esc_html( get_post_meta( $post_id, '_service_rating', true ) ?: '—' );
			break;
		case 'thumbnail':
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, [ 60, 60 ] );
			}
			break;
	}
}
add_action( 'manage_climanova_service_posts_custom_column', 'sbmt_service_column_content', 10, 2 );

// ── Include core files ───────────────────────────────────────────────────────
require_once SBMT_DIR . '/inc/customizer.php';
require_once SBMT_DIR . '/inc/custom-post-types.php';
require_once SBMT_DIR . '/inc/helpers.php';
