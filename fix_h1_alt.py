#!/usr/bin/env python3
"""Step 6 SEO fixes: H1 hierarchy + alt text improvements."""
import re, glob, os

# ── Alt text mapping ──────────────────────────────────────────────────────────
ALT_MAP = {
    'climanova-blog-01':      "Article de blog ClimaNova Energie – conseils climatisation Nice",
    'climanova-blog-02':      "Conseils énergie ClimaNova – rénovation et confort thermique",
    'climanova-blog-03':      "Blog ClimaNova Energie – experts en climatisation à Nice",
    'climanova-service-01':   "Technicien ClimaNova en intervention climatisation à Nice",
    'climanova-service-02':   "Entretien climatisation ClimaNova Energie",
    'climanova-service-03':   "Service électricité ClimaNova Energie Nice",
    'climanova-service-04':   "Intervention plomberie ClimaNova Energie Nice",
    'climanova-service-05':   "Rénovation énergétique par ClimaNova Energie",
    'climanova-service-06':   "Service ClimaNova Energie – dépannage et entretien",
    'climanova-why-choose':   "Équipe ClimaNova Energie – experts en énergie à Nice",
    'climanova-hero-image':   "Techniciens ClimaNova Energie en intervention à Nice",
    'climanova-about-01':     "L'équipe ClimaNova Energie – spécialistes climatisation et énergie",
    'climanova-about-02':     "Intervention ClimaNova Energie chez un client à Nice",
    'climanova-about-banner': "À propos de ClimaNova Energie – votre expert énergie à Nice",
    'climanova-project-00':   "Projet ClimaNova Energie – installation à Nice",
    'climanova-project-01':   "Réalisation ClimaNova Energie – travaux énergétiques",
    'climanova-project-02':   "Réalisation ClimaNova Energie – projet énergie Nice",
    'climanova-project-03':   "Chantier ClimaNova Energie – rénovation à Nice",
    'climanova-project-04':   "Projet énergétique réalisé par ClimaNova Energie",
    'climanova-project-05':   "Installation ClimaNova Energie – résultat final",
    'climanova-project-slide':"Portfolio ClimaNova Energie – réalisations à Nice",
    'climanova-testimonial':  "Client satisfait de ClimaNova Energie à Nice",
    'climanova-avatar-01':    "Avis client ClimaNova Energie",
    'climanova-avatar-02':    "Avis client ClimaNova Energie",
    'climanova-avatar-03':    "Avis client ClimaNova Energie",
    'climanova-avatar-04':    "Avis client ClimaNova Energie",
    'climanova-marquee-06':   "Logo partenaire ClimaNova Energie",
    'climanova-services-banner': "Services ClimaNova Energie – climatisation chauffage électricité plomberie",
}

# ── H1 fixes per file ─────────────────────────────────────────────────────────
# Format: (file, old_tag_pattern, new_h1_tag)
# For blog posts, we change the first h2 with specific data-w-id to h1
# For service/legal pages, we change a specific h2 to h1

H1_SIMPLE = [
    # (file, exact_old, exact_new)

    # FAQ – sidebar h2 at bottom of page
    ('faq/index.html',
     '<h2 class="content-titile">Questions fréquentes</h2>',
     '<h1 class="content-titile">Questions fréquentes</h1>'),

    # Terms & conditions
    ('terms-conditions/index.html',
     '<h2 class="h2-style">Conditions générales</h2>',
     '<h1 class="h2-style">Conditions générales</h1>'),

    # French service pages with real H2 banner
    ('services/entretien/index.html',
     '<h2 class="h2-style">Entretien &amp; dépannage</h2>',
     '<h1 class="h2-style">Entretien &amp; dépannage</h1>'),

    # Legacy English service pages – rename + translate
    ('services/furniture-repair/index.html',
     '<h2 class="h2-style">Furniture Repair</h2>',
     '<h1 class="h2-style">Réparation de mobilier</h1>'),

    ('services/home-remodeling/index.html',
     '<h2 class="h2-style">Home remodeling</h2>',
     '<h1 class="h2-style">Rénovation intérieure</h1>'),

    ('services/kitchen-upgrade/index.html',
     '<h2 class="h2-style">Kitchen Upgrade</h2>',
     '<h1 class="h2-style">Rénovation de cuisine</h1>'),

    ('services/appliance-installation/index.html',
     '<h2 class="h2-style">Appliance installation</h2>',
     '<h1 class="h2-style">Installation d\'équipements</h1>'),

    ('services/plumbing-leak-repairs/index.html',
     '<h2 class="h2-style">Plumbing &amp; Leak Repairs</h2>',
     '<h1 class="h2-style">Plomberie &amp; Réparation de fuites</h1>'),

    ('services/roof-and-wall-repair/index.html',
     '<h2 class="h2-style">Roof and wall repair</h2>',
     '<h1 class="h2-style">Réparation toiture et murs</h1>'),
]

# Blog posts: change banner h2 (data-w-id="9375cd72...") to h1
# Also fix the two that still have English title/author/date
BLOG_BANNER_OLD = 'data-w-id="9375cd72-c9eb-7e19-b4c2-3c7e06fce92c" class="h2-style text-light cn-anim-hidden"'

# For choisir-prestataire: fix English content in banner
CHOISIR_FIXES = [
    ('How to Choose the Right Home Repair Service Quickly and Safely',
     'Choisir son prestataire de travaux énergétiques à Nice'),
    ('By Darrell Steward', "Par l'équipe ClimaNova"),
    ('December 20, 2025', '11 avril 2026'),
    ('>10 min read<', '>8 min de lecture<'),
]

# For preparer-logement: fix English content in banner
PREPARER_FIXES = [
    ('Preparing Your Home for Essential Seasonal Maintenance Guide',
     'Préparer son logement pour l\'été : le guide ClimaNova'),
    ('By John Mark', "Par l'équipe ClimaNova"),
    ('February 22, 2026', '22 février 2026'),
    ('>10 min read<', '>5 min de lecture<'),
]


def fix_alt_text(html):
    """Replace alt="" on climanova images with descriptive text."""
    def replacer(m):
        full = m.group(0)
        for key, alt in ALT_MAP.items():
            if key in full:
                return full.replace('alt=""', f'alt="{alt}"')
        return full

    # Match <img ... alt="" ... > or <source ... > containing climanova filename
    pattern = r'<(?:img|source)\s[^>]*climanova-[^>]*>'
    return re.sub(pattern, replacer, html, flags=re.DOTALL)


def fix_blog_banner_h2_to_h1(html):
    """Change h2 with the specific blog banner data-w-id to h1."""
    old = f'<h2 {BLOG_BANNER_OLD}>'
    new = f'<h1 {BLOG_BANNER_OLD}>'
    html = html.replace(old, new)
    # Also fix closing tag – find the matching </h2> after the opening
    # Since titles don't contain nested tags, simple replace is safe
    # But we only want to replace the one that follows the banner h1 open
    # Use a targeted pattern
    html = re.sub(
        rf'(<h1 {re.escape(BLOG_BANNER_OLD)}>)(.*?)(</h2>)',
        r'\1\2</h1>',
        html, count=1, flags=re.DOTALL
    )
    return html


def process_file(path, fixes=None, blog_banner=False, extra_fixes=None):
    with open(path, encoding='utf-8') as f:
        html = f.read()

    changed = False

    # H1 simple replacements
    if fixes:
        for old, new in fixes:
            if old in html:
                html = html.replace(old, new, 1)
                changed = True

    # Blog banner h2→h1
    if blog_banner:
        new_html = fix_blog_banner_h2_to_h1(html)
        if new_html != html:
            html = new_html
            changed = True

    # Extra text fixes (English→French in banner)
    if extra_fixes:
        for old, new in extra_fixes:
            if old in html:
                html = html.replace(old, new, 1)
                changed = True

    # Alt text
    new_html = fix_alt_text(html)
    if new_html != html:
        html = new_html
        changed = True

    if changed:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(html)
        print(f'  fixed: {path}')
    else:
        print(f'  skip:  {path}')

    return changed


def main():
    print('=== H1 simple fixes ===')
    for path, old, new in H1_SIMPLE:
        if os.path.exists(path):
            process_file(path, fixes=[(old, new)])
        else:
            print(f'  missing: {path}')

    print('\n=== Blog banner H2→H1 ===')
    fr_blogs = [
        'blog/climatisation-verifications-avant-technicien/index.html',
        'blog/prolonger-duree-vie-climatisation-conseils/index.html',
        'blog/solutions-economiques-confort-thermique-logement/index.html',
        'blog/choisir-prestataire-travaux-energetiques-nice/index.html',
        'blog/preparer-logement-ete-climanova/index.html',
    ]
    for p in fr_blogs:
        extra = None
        if 'choisir-prestataire' in p:
            extra = CHOISIR_FIXES
        elif 'preparer-logement' in p:
            extra = PREPARER_FIXES
        if os.path.exists(p):
            process_file(p, blog_banner=True, extra_fixes=extra)
        else:
            print(f'  missing: {p}')

    print('\n=== Alt text on remaining pages ===')
    all_pages = glob.glob('**/index.html', recursive=True)
    all_pages = [p for p in all_pages
                 if not p.startswith('innerpages/')
                 and not p.startswith('ClimaNovaEnergie')
                 and not p.startswith('climavovaall')
                 and not p.startswith('template-pages/')]
    already = set(p for _, p, _ in H1_SIMPLE) | set(fr_blogs)
    for p in sorted(all_pages):
        if p not in already:
            process_file(p)

    print('\nDone.')


if __name__ == '__main__':
    main()
