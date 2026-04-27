/**
 * SB Marketing Theme — main.js
 * Handles: mobile nav toggle, service dropdowns, marquee pause, smooth scroll.
 */
( function ( $ ) {
	'use strict';

	// ── Mobile navigation ────────────────────────────────────────────────────
	const hamburger  = document.getElementById( 'nav-hamburger' );
	const primaryNav = document.getElementById( 'primary-nav' );

	if ( hamburger && primaryNav ) {
		hamburger.addEventListener( 'click', function () {
			const isOpen = this.classList.toggle( 'is-open' );
			primaryNav.classList.toggle( 'is-open', isOpen );
			this.setAttribute( 'aria-expanded', String( isOpen ) );
			this.setAttribute( 'aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu' );
			// Prevent page scroll when menu open
			document.body.style.overflow = isOpen ? 'hidden' : '';
		} );

		// Close nav when a link is clicked
		primaryNav.querySelectorAll( 'a' ).forEach( function ( link ) {
			link.addEventListener( 'click', function () {
				hamburger.classList.remove( 'is-open' );
				primaryNav.classList.remove( 'is-open' );
				hamburger.setAttribute( 'aria-expanded', 'false' );
				hamburger.setAttribute( 'aria-label', 'Ouvrir le menu' );
				document.body.style.overflow = '';
			} );
		} );

		// Close on Escape key
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key && primaryNav.classList.contains( 'is-open' ) ) {
				hamburger.click();
			}
		} );
	}

	// ── Desktop dropdown toggles (touch / keyboard) ──────────────────────────
	document.querySelectorAll( '.nav-services-dropdown, .nav-dropdown' ).forEach( function ( dropdown ) {
		const toggle = dropdown.querySelector( '.w-dropdown-toggle' );
		const list   = dropdown.querySelector( '.w-dropdown-list' );
		if ( ! toggle || ! list ) return;

		// Mouse hover (CSS handles this; JS adds aria)
		dropdown.addEventListener( 'mouseenter', function () {
			toggle.setAttribute( 'aria-expanded', 'true' );
		} );
		dropdown.addEventListener( 'mouseleave', function () {
			toggle.setAttribute( 'aria-expanded', 'false' );
		} );

		// Keyboard / touch toggle
		toggle.addEventListener( 'click', function ( e ) {
			// Only on mobile (nav is full-screen open state)
			if ( window.innerWidth <= 991 ) {
				e.preventDefault();
				const expanded = list.classList.toggle( 'is-open' );
				toggle.setAttribute( 'aria-expanded', String( expanded ) );
			}
		} );
	} );

	// ── Sticky header ────────────────────────────────────────────────────────
	const header = document.getElementById( 'site-header' );
	if ( header ) {
		let lastScroll = 0;
		const onScroll = function () {
			const current = window.scrollY;
			if ( current > 80 ) {
				header.classList.add( 'header--scrolled' );
			} else {
				header.classList.remove( 'header--scrolled' );
			}
			// Optional: hide on scroll down, show on scroll up
			if ( current > lastScroll && current > 200 ) {
				header.classList.add( 'header--hidden' );
			} else {
				header.classList.remove( 'header--hidden' );
			}
			lastScroll = Math.max( current, 0 );
		};
		window.addEventListener( 'scroll', onScroll, { passive: true } );
	}

	// ── Smooth scroll for anchor links ───────────────────────────────────────
	document.querySelectorAll( 'a[href^="#"]' ).forEach( function ( anchor ) {
		anchor.addEventListener( 'click', function ( e ) {
			const target = document.querySelector( this.getAttribute( 'href' ) );
			if ( target ) {
				e.preventDefault();
				const offset = header ? header.offsetHeight + 16 : 80;
				const top    = target.getBoundingClientRect().top + window.scrollY - offset;
				window.scrollTo( { top: top, behavior: 'smooth' } );
			}
		} );
	} );

	// ── Newsletter form feedback ──────────────────────────────────────────────
	const newsletterForm = document.querySelector( '.footer-newsletter-form' );
	if ( newsletterForm ) {
		newsletterForm.addEventListener( 'submit', function ( e ) {
			// Basic client-side UX: disable button while submitting
			const btn = this.querySelector( '[type=submit]' );
			if ( btn ) {
				btn.disabled  = true;
				btn.textContent = '…';
			}
		} );
	}

	// ── Back-to-top button (create dynamically) ───────────────────────────────
	const btt = document.createElement( 'button' );
	btt.className   = 'sbmt-back-to-top';
	btt.innerHTML   = '<span class="material-icons-round">arrow_upward</span>';
	btt.setAttribute( 'aria-label', 'Retour en haut' );
	btt.style.cssText = [
		'position:fixed', 'bottom:32px', 'right:32px', 'z-index:999',
		'width:48px', 'height:48px', 'border-radius:50%',
		'background:#1664BF', 'color:#fff', 'border:none',
		'display:flex', 'align-items:center', 'justify-content:center',
		'cursor:pointer', 'opacity:0', 'transform:translateY(16px)',
		'transition:opacity 0.3s,transform 0.3s', 'box-shadow:0 4px 16px rgba(22,100,191,0.35)',
	].join( ';' );
	document.body.appendChild( btt );

	const toggleBTT = function () {
		const show = window.scrollY > 400;
		btt.style.opacity   = show ? '1' : '0';
		btt.style.transform = show ? 'translateY(0)' : 'translateY(16px)';
		btt.style.pointerEvents = show ? 'auto' : 'none';
	};
	window.addEventListener( 'scroll', toggleBTT, { passive: true } );
	btt.addEventListener( 'click', function () {
		window.scrollTo( { top: 0, behavior: 'smooth' } );
	} );

} )( jQuery );
