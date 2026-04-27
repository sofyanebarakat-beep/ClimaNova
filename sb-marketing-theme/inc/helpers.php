<?php
/**
 * SB Marketing Theme — Helper functions
 *
 * @package SBMarketingTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns the URL for a theme asset image, falling back to a placeholder if missing.
 *
 * @param string $filename   Filename relative to assets/images/.
 * @param string $fallback   Optional URL used when file does not exist.
 * @return string
 */
function sbmt_asset_img( string $filename, string $fallback = '' ): string {
	$path = SBMT_DIR . '/assets/images/' . $filename;
	if ( file_exists( $path ) ) {
		return SBMT_URI . '/assets/images/' . esc_attr( $filename );
	}
	return $fallback ?: SBMT_URI . '/assets/images/placeholder.png';
}

/**
 * Outputs the site logo:
 * — custom_logo if set in the Customizer
 * — else falls back to the theme asset logo
 *
 * @param string $fallback_file  Filename inside assets/images/.
 * @param string $css_class      Additional CSS class on the <img> tag.
 */
function sbmt_the_logo( string $fallback_file = 'climanova-logo.png', string $css_class = 'nav-logo' ): void {
	if ( has_custom_logo() ) {
		$logo_id  = get_theme_mod( 'custom_logo' );
		$logo_img = wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => $css_class, 'alt' => get_bloginfo( 'name' ) ] );
		echo wp_kses_post( $logo_img );
	} else {
		$src = sbmt_asset_img( $fallback_file );
		printf(
			'<img src="%s" alt="%s" class="%s" loading="lazy">',
			esc_url( $src ),
			esc_attr( get_bloginfo( 'name' ) ),
			esc_attr( $css_class )
		);
	}
}

/**
 * Outputs the footer logo (separate Customizer setting or falls back to sbmt_the_logo).
 */
function sbmt_the_footer_logo(): void {
	$footer_logo_url = get_theme_mod( 'sbmt_footer_logo', '' );
	if ( $footer_logo_url ) {
		printf(
			'<img src="%s" alt="%s" class="footer-logo" loading="lazy">',
			esc_url( $footer_logo_url ),
			esc_attr( get_bloginfo( 'name' ) )
		);
	} else {
		sbmt_the_logo( 'climanova-logo-footer.png', 'footer-logo' );
	}
}

/**
 * Outputs star rating HTML (filled + empty stars).
 *
 * @param int $rating  Rating out of 5.
 * @param int $max     Maximum stars (default 5).
 */
function sbmt_star_rating( int $rating, int $max = 5 ): void {
	$rating = max( 0, min( $max, $rating ) );
	echo '<div class="star-rating" aria-label="' . esc_attr( sprintf( __( 'Note : %d sur %d', 'sb-marketing-theme' ), $rating, $max ) ) . '">';
	for ( $i = 1; $i <= $max; $i++ ) {
		$filled = $i <= $rating ? 'filled' : '';
		echo '<span class="star ' . esc_attr( $filled ) . '">&#9733;</span>';
	}
	echo '</div>';
}

/**
 * Returns a sanitised customizer value with a fallback.
 *
 * @param string $key      Customizer setting ID.
 * @param string $fallback Default value if setting is empty.
 * @return string
 */
function sbmt_mod( string $key, string $fallback = '' ): string {
	return get_theme_mod( $key, $fallback ) ?: $fallback;
}

/**
 * Renders a primary button using brand classes.
 *
 * @param string $text   Button label.
 * @param string $url    Button href.
 * @param string $class  Additional CSS classes.
 */
function sbmt_button( string $text, string $url, string $class = '' ): void {
	printf(
		'<a href="%s" class="button-primary w-inline-block %s">
			<div class="button-text-wrapper">
				<div class="button-text">%s</div>
				<div class="button-text absolute">%s</div>
			</div>
			<div class="button-arrow-wrap">
				<div class="button-arrow-block">
					<span class="material-icons-round button-right-arrow" aria-hidden="true">arrow_outward</span>
				</div>
			</div>
		</a>',
		esc_url( $url ),
		esc_attr( $class ),
		esc_html( $text ),
		esc_html( $text )
	);
}

/**
 * Returns the SVG arrow icon used in nav dropdowns.
 */
function sbmt_arrow_svg(): string {
	return '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
		<path d="M5 12H19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M12 5L19 12L12 19" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>';
}

/**
 * Outputs the location pin SVG used in the top bar.
 */
function sbmt_icon_location(): void {
	echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
		<path d="M15.7597 15C16.5212 16.14 16.8434 16.8358 16.5727 17.4166C16.5395 17.4878 16.5006 17.5566 16.4564 17.6222C15.9777 18.3333 14.7402 18.3333 12.2655 18.3333H7.73584C5.26108 18.3333 4.02369 18.3333 3.54491 17.6222C3.50069 17.5566 3.46181 17.4878 3.42861 17.4166C3.15789 16.8358 3.48004 16.14 4.24161 15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
		<path d="M12.5 7.91406C12.5 9.29481 11.3807 10.4141 10 10.4141C8.61925 10.4141 7.5 9.29481 7.5 7.91406C7.5 6.53335 8.61925 5.41406 10 5.41406C11.3807 5.41406 12.5 6.53335 12.5 7.91406Z" stroke="currentColor" stroke-width="1.5"/>
		<path d="M10 1.66406C13.3823 1.66406 16.25 4.52075 16.25 7.98648C16.25 11.5074 13.3357 13.9782 10.6437 15.6584C10.4476 15.7713 10.2257 15.8307 10 15.8307C9.77425 15.8307 9.55242 15.7713 9.35625 15.6584C6.66937 13.9618 3.75 11.5196 3.75 7.98648C3.75 4.52075 6.61767 1.66406 10 1.66406Z" stroke="currentColor" stroke-width="1.5"/>
	</svg>';
}

/**
 * Outputs the phone SVG used in the top bar.
 */
function sbmt_icon_phone(): void {
	echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
		<path d="M7.63188 4.76019L7.29633 4.00521C7.07693 3.51157 6.96723 3.26473 6.80317 3.07584C6.59756 2.83912 6.32956 2.66495 6.02973 2.57321C5.79049 2.5 5.52038 2.5 4.98017 2.5C4.18992 2.5 3.7948 2.5 3.46311 2.65191C3.07239 2.83085 2.71953 3.2194 2.57894 3.6255C2.45959 3.97024 2.49378 4.32453 2.56215 5.03308C3.28992 12.5752 7.42485 16.7101 14.9669 17.4378C15.6755 17.5063 16.0298 17.5404 16.3745 17.4211C16.7806 17.2805 17.1691 16.9276 17.3481 16.5369C17.5 16.2052 17.5 15.8101 17.5 15.0198C17.5 14.4796 17.5 14.2095 17.4268 13.9703C17.335 13.6704 17.1609 13.4024 16.9241 13.1968C16.7353 13.0328 16.4885 12.9231 15.9948 12.7037L15.2398 12.3681C14.7052 12.1305 14.4379 12.0118 14.1663 11.9859C13.9063 11.9612 13.6442 11.9977 13.4009 12.0924C13.1466 12.1914 12.922 12.3787 12.4725 12.7532C12.0251 13.126 11.8015 13.3124 11.5281 13.4123C11.2858 13.5008 10.9655 13.5336 10.7103 13.4959C10.4224 13.4535 10.202 13.3358 9.76105 13.1001C8.38938 12.3671 7.63294 11.6107 6.89989 10.2389C6.66428 9.79808 6.54648 9.57758 6.50406 9.28975C6.46645 9.0345 6.49923 8.71417 6.58775 8.47192C6.6876 8.19857 6.87401 7.97488 7.24682 7.5275C7.62135 7.07807 7.80861 6.85335 7.90762 6.59909C8.00237 6.35578 8.03885 6.09367 8.01412 5.83373C7.98828 5.5621 7.86948 5.2948 7.63188 4.76019Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
	</svg>';
}

/**
 * Outputs the badge / shield SVG used in the top bar certification block.
 */
function sbmt_icon_badge(): void {
	echo '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
		<path d="M7.82814 2.20055C8.88964 1.64237 9.42045 1.36328 10.0009 1.36328C10.5813 1.36328 11.1121 1.64237 12.1737 2.20055L13.6509 2.97734C14.7528 3.55674 15.3038 3.84645 15.6068 4.33094C15.91 4.81543 15.91 5.40634 15.91 6.58816V7.95657C15.91 9.13838 15.91 9.72929 15.6068 10.2138C15.3038 10.6983 14.7528 10.988 13.6509 11.5674L12.1737 12.3442C11.1121 12.9024 10.5813 13.1815 10.0009 13.1815C9.42045 13.1815 8.88964 12.9024 7.82814 12.3442L6.35087 11.5674C5.24897 10.988 4.69803 10.6983 4.39492 10.2138C4.0918 9.72929 4.0918 9.13838 4.0918 7.95657V6.58816C4.0918 5.40634 4.0918 4.81543 4.39492 4.33094C4.69803 3.84645 5.24897 3.55674 6.35087 2.97734L7.82814 2.20055Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/>
		<path d="M7.72656 7.94448C7.72656 7.94448 8.35156 7.94447 8.97656 9.16667C8.97656 9.16667 10.9619 6.11111 12.7266 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
	</svg>';
}

/**
 * Outputs the quote icon SVG used in testimonials.
 */
function sbmt_icon_quote(): void {
	echo '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true">
		<path d="M4 19.333c0-4.596 3.104-8.667 9.333-12L15 9.667c-3.896 2-6.333 4.333-6.333 7.333h3.666V22H4v-2.667zm14.667 0c0-4.596 3.103-8.667 9.333-12l1.667 2.334c-3.896 2-6.333 4.333-6.333 7.333h3.666V22H18.667v-2.667z" fill="currentColor" opacity=".2"/>
	</svg>';
}

/**
 * Returns the pagination HTML for any WP_Query.
 *
 * @param WP_Query $query The current query.
 */
function sbmt_pagination( WP_Query $query ): void {
	$total   = $query->max_num_pages;
	$current = max( 1, get_query_var( 'paged' ) );
	if ( $total <= 1 ) {
		return;
	}
	$links = paginate_links( [
		'base'      => str_replace( 999999999, '%#%', esc_url( get_pagenum_link( 999999999 ) ) ),
		'format'    => '?paged=%#%',
		'current'   => $current,
		'total'     => $total,
		'type'      => 'array',
		'prev_text' => '&larr;',
		'next_text' => '&rarr;',
	] );
	if ( $links ) {
		echo '<nav class="pagination" aria-label="' . esc_attr__( 'Pagination', 'sb-marketing-theme' ) . '">';
		foreach ( $links as $link ) {
			echo wp_kses_post( $link );
		}
		echo '</nav>';
	}
}
