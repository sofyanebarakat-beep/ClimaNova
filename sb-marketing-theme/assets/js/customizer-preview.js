/**
 * SB Marketing Theme — Customizer live preview (postMessage bindings).
 * Binds Customizer settings with transport:'postMessage' to DOM updates.
 */
( function ( $ ) {
	'use strict';

	// ── Contact info ─────────────────────────────────────────────────────────
	wp.customize( 'sbmt_phone', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.top-bar-text-block a[href^="tel"]' ).find( '.text-sm' ).text( newVal );
		} );
	} );

	wp.customize( 'sbmt_address', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.top-bar-text-block a[href*="maps"]' ).find( '.text-sm' ).text( newVal );
		} );
	} );

	// ── Hero section ──────────────────────────────────────────────────────────
	wp.customize( 'sbmt_hero_badge_text', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.hero-badge-text' ).contents().filter( function () {
				return this.nodeType === 3; // text node
			} ).last().replaceWith( newVal );
		} );
	} );

	wp.customize( 'sbmt_hero_title', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.hero-title' ).html( newVal );
		} );
	} );

	wp.customize( 'sbmt_hero_subtitle', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.hero-subtitle' ).text( newVal );
		} );
	} );

	wp.customize( 'sbmt_hero_btn_text', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.hero-cta-btn .button-text' ).text( newVal );
		} );
	} );

	// ── About section ─────────────────────────────────────────────────────────
	wp.customize( 'sbmt_about_title', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.about-section h2' ).html( newVal );
		} );
	} );

	wp.customize( 'sbmt_about_subtitle', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.about-description' ).text( newVal );
		} );
	} );

	// ── Services section ──────────────────────────────────────────────────────
	wp.customize( 'sbmt_services_title', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.service-section h2' ).html( newVal );
		} );
	} );

	// ── CTA section ───────────────────────────────────────────────────────────
	wp.customize( 'sbmt_cta_title', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.cta-title' ).html( newVal );
		} );
	} );

	wp.customize( 'sbmt_cta_subtitle', function ( value ) {
		value.bind( function ( newVal ) {
			$( '.cta-subtitle' ).text( newVal );
		} );
	} );

} )( jQuery );
