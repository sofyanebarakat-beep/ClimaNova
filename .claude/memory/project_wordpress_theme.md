---
name: WordPress theme conversion — ClimaNova
description: Complete WordPress theme built from the ClimaNova static HTML/Webflow site, located at sb-marketing-theme/
type: project
---

A full WordPress theme was generated from the static HTML Webflow site at /Users/sof/ClimaNova/.

Theme folder: `sb-marketing-theme/` (to be installed as a WordPress theme at `wp-content/themes/sb-marketing-theme/`).

**Why:** Client requested a dynamic, admin-manageable WordPress theme based on the ClimaNova Energie brand.

**How to apply:** When working on the theme, refer to the file list below for context on what exists and where.

## Key files
- `style.css` — Theme header (name: "SB Marketing Theme", author: SB Marketing)
- `functions.php` — Theme setup, enqueue, sidebars, menus, includes
- `inc/customizer.php` — All customizer panels/settings (contact, hero, about, services, process, CTA, footer, etc.)
- `inc/custom-post-types.php` — CPTs: climanova_service, climanova_project, climanova_testimonial
- `inc/helpers.php` — Utility functions (sbmt_mod, sbmt_button, sbmt_the_logo, sbmt_pagination, etc.)
- `header.php` — Top bar + nav with dynamic logo (wp_nav_menu + walker)
- `footer.php` — Footer with 4 columns, newsletter form, copyright
- `front-page.php` — Homepage assembles 10 template parts
- `template-parts/home/` — hero, marquee, about, why-choose, services, process, projects, testimonials, cta, blog-preview
- `assets/css/theme.css` — Layout/component styles
- `assets/js/main.js` — Mobile nav, sticky header, back-to-top, smooth scroll

## CPT structure
- `climanova_service`: meta = _service_price, _service_rating, _service_review_count, _service_icon
- `climanova_project`: meta = _project_location, _project_service; taxonomy = project_category
- `climanova_testimonial`: meta = _testimonial_author, _testimonial_role, _testimonial_rating

## Menus to register in WP admin
- primary, services-menu, footer-services, footer-explore

## Installation steps
1. Copy `sb-marketing-theme/` to `wp-content/themes/`
2. Activate in WP Admin → Appearance → Themes
3. Go to Appearance → Menus and create the 4 nav menus
4. Go to Appearance → Customize → ClimaNova Paramètres to fill in contact info, images, text
5. Add Services, Réalisations, Témoignages via their WP Admin menu items
6. Set a static front page in Settings → Reading
