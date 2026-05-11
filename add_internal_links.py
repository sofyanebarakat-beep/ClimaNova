#!/usr/bin/env python3
"""
Step 7: Internal links + À lire aussi + French rewrites for 2 English posts.
"""
import re, os

# ── Helpers ────────────────────────────────────────────────────────────────────
IL = 'cn-inline-link'

def lire_aussi(articles):
    """articles: list of (href, title, date, img_slug)"""
    cards = ''
    for href, title, date, img in articles:
        cards += (
            f'<a href="{href}" class="cn-lire-aussi-card w-inline-block">'
            f'<div class="cn-lire-aussi-img-wrap">'
            f'<picture><source srcset="/images/{img}.webp" type="image/webp">'
            f'<img src="/images/{img}.jpg" loading="lazy" alt="{title}" '
            f'class="cn-lire-aussi-img" width="800" height="533"></picture></div>'
            f'<div class="cn-lire-aussi-body">'
            f'<span class="cn-lire-aussi-date">{date}</span>'
            f'<p class="cn-lire-aussi-title">{title}</p>'
            f'</div></a>'
        )
    return (
        f'<div class="cn-lire-aussi">'
        f'<h4 class="cn-lire-aussi-heading">À lire aussi</h4>'
        f'<div class="cn-lire-aussi-grid">{cards}</div>'
        f'</div>'
    )

TOP_TAG   = 'data-w-id="963b071e-977a-173b-a2d9-b052df66323e" class="blog-details-top-content-block w-richtext cn-anim-hidden"'
MID1_TAG  = 'data-w-id="f78ab632-a7ee-07ec-2a40-8e115c4599d7" class="blog-details-middle-content-block w-richtext cn-anim-hidden"'
MID2_TAG  = 'data-w-id="3eb6367e-f027-c8a5-9791-3d0db3d560f9" class="blog-details-middle-content-block w-richtext cn-anim-hidden"'

def replace_block(html, tag, new_content):
    pattern = rf'(<div {re.escape(tag)}>)(.*?)(</div>)'
    return re.sub(pattern, rf'\g<1>{new_content}\3', html, count=1, flags=re.DOTALL)

def add_inline_link(html, phrase, href, anchor):
    """Replace first occurrence of phrase, wrapping `anchor` inside it as a link."""
    linked = phrase.replace(anchor, f'<a href="{href}" class="{IL}">{anchor}</a>', 1)
    return html.replace(phrase, linked, 1)

# ── French content for rewritten posts ────────────────────────────────────────

CHOISIR_TOP = (
    '<p>Faire appel à un professionnel pour des travaux énergétiques — '
    'installation d\'une climatisation, rénovation thermique ou remplacement '
    'd\'un système de chauffage — engage plusieurs milliers d\'euros et '
    'conditionne votre confort pendant des années. À Nice et en région PACA, '
    'l\'offre est dense et la qualité variable. Ce guide vous aide à distinguer '
    'les professionnels sérieux, à poser les bonnes questions et à éviter les '
    'pièges les plus courants.</p><p>‍</p>'
)

CHOISIR_MID1 = (
    '<h4>Avant de chercher : définissez vos besoins</h4>'
    '<p>Clarifier vos besoins avant de contacter un prestataire évite les '
    'devis imprécis et les mauvaises surprises :</p>'
    '<ul role="list">'
    '<li><strong>Identifiez la nature des travaux –</strong> Installation neuve, '
    'remplacement ou dépannage ? Le type de prestation détermine le profil du '
    'professionnel à solliciter.</li>'
    '<li><strong>Fixez une enveloppe budgétaire –</strong> Même approximative, elle '
    'permet au prestataire de proposer des solutions adaptées.</li>'
    '<li><strong>Notez vos contraintes –</strong> Délais, copropriété, accès : ces '
    'éléments impactent directement le chiffrage.</li>'
    '<li><strong>Renseignez-vous sur les aides –</strong> MaPrimeRénov\', CEE, TVA '
    'à 5,5 % : certains travaux ouvrent droit à des subventions importantes que seuls '
    'des professionnels certifiés RGE peuvent déclencher.</li>'
    '</ul>'
    '<p>Cette préparation vous permettra d\'évaluer les prestataires sur des bases '
    'objectives et d\'éviter de vous laisser convaincre par un argumentaire sans rapport '
    'avec vos besoins réels.</p><p>‍</p>'
    '<h4><strong>Les 5 critères pour bien choisir</strong></h4>'
    '<p>Ces critères sont ceux que nos clients appliquent systématiquement avant de '
    'confier leurs travaux à un artisan. Ils vous permettront de comparer les offres '
    'sur des bases solides et de sécuriser votre investissement.</p><p>‍</p>'
    '<h5>1. Vérifiez les certifications et assurances</h5>'
    '<p>Un artisan sérieux vous présente sur demande sa garantie décennale, son '
    'assurance responsabilité civile professionnelle et, pour les travaux de '
    '<a href="/services/renovation-energetique/" class="cn-inline-link">rénovation '
    'énergétique</a>, la certification RGE. Ces documents vous protègent en cas de '
    'malfaçon et conditionnent votre accès aux aides publiques.</p><p>‍</p>'
    '<h5>2. Demandez des devis détaillés et comparables</h5>'
    '<p>Un devis professionnel détaille les fournitures, la main-d\'œuvre, les délais '
    'et les garanties. Méfiez-vous des devis vagues ou ne mentionnant qu\'un montant '
    'global. Demandez au moins deux à trois devis pour comparer sur des bases '
    'objectives.</p>'
)

CHOISIR_MID2 = (
    '<h5>3. Consultez les avis clients et réalisations locales</h5>'
    '<p>Les avis Google, les photos de chantiers terminés et les recommandations de '
    'particuliers de votre entourage sont des indicateurs fiables. Privilégiez les '
    'entreprises présentes localement depuis plusieurs années, qui connaissent les '
    'spécificités des logements niçois et le climat de la région PACA.</p><p>‍</p>'
    '<h5>4. Évaluez la réactivité et la clarté des échanges</h5>'
    '<p>Un prestataire qui répond tardivement ou ne prend pas le temps d\'expliquer '
    'sa proposition est rarement plus disponible une fois les travaux commencés. La '
    'qualité de la communication en amont préfigure souvent la qualité du suivi de '
    'chantier.</p><p>‍</p>'
    '<h5>5. Méfiez-vous des prix anormalement bas</h5>'
    '<p>Un devis très inférieur aux autres peut indiquer des matériaux de moindre '
    'qualité, une main-d\'œuvre non déclarée ou une sous-évaluation pour gagner le '
    'chantier puis facturer des suppléments. Les travaux énergétiques nécessitent du '
    'matériel certifié et une installation conforme aux normes.</p>'
    '<blockquote><em>« Un bon prestataire prend le temps de visiter votre logement, '
    'd\'écouter vos attentes et de vous expliquer ses solutions avant de chiffrer '
    'quoi que ce soit. »</em></blockquote>'
    '<h4>Conclusion</h4>'
    '<p>Choisir le bon professionnel pour vos travaux énergétiques à Nice, c\'est '
    'investir dans une réalisation durable et économiquement optimisée. Chez '
    '<a href="/services/climatisation/" class="cn-inline-link">ClimaNova Energie</a>, '
    'nous intervenons sur l\'ensemble de la région PACA avec des équipes certifiées, '
    'des devis transparents et un suivi personnalisé à chaque étape de votre '
    'projet.</p>'
)

PREPARER_TOP = (
    '<p>À Nice, les premières canicules arrivent souvent dès juin. Si votre '
    'climatiseur n\'a pas été vérifié depuis l\'automne, si votre installation '
    'électrique n\'est pas dimensionnée pour accueillir des appareils de '
    'rafraîchissement supplémentaires, ou si vos protections solaires sont '
    'défectueuses, l\'été peut vite devenir inconfortable — et coûteux. '
    'Chez ClimaNova Energie, nous accompagnons chaque printemps les habitants '
    'de Nice et de la région PACA pour anticiper les fortes chaleurs. '
    'Voici notre guide pratique.</p><p>‍</p>'
)

PREPARER_MID1 = (
    '<h4>Avant de commencer : anticipez les priorités</h4>'
    '<p>La préparation estivale suit une logique simple : traiter en priorité ce '
    'qui a le plus fort impact sur le confort et la sécurité :</p>'
    '<ul role="list">'
    '<li><strong>Commencez par la climatisation –</strong> C\'est l\'équipement le '
    'plus sollicité en été. Vérifiez son état avant les premières chaleurs, '
    'pas pendant.</li>'
    '<li><strong>Contrôlez l\'installation électrique –</strong> Climatiseurs et '
    'ventilateurs augmentent la consommation. Assurez-vous que votre tableau '
    'n\'est pas sous-dimensionné.</li>'
    '<li><strong>Évaluez vos protections solaires –</strong> En région '
    'méditerranéenne, bloquer le rayonnement avant qu\'il n\'entre est plus '
    'efficace que de climatiser une pièce surchauffée.</li>'
    '<li><strong>Vérifiez l\'étanchéité des ouvertures –</strong> Joints de '
    'fenêtres, portes-fenêtres : un logement bien étanche conserve mieux la '
    'fraîcheur nocturne.</li>'
    '</ul>'
    '<p>Ces vérifications peuvent être effectuées par vous-même pour les plus '
    'simples, ou confiées à nos techniciens pour les points nécessitant une '
    'expertise technique.</p><p>‍</p>'
    '<h4><strong>5 étapes pour préparer votre logement</strong></h4>'
    '<p>Voici les actions prioritaires que nos techniciens recommandent chaque '
    'printemps aux propriétaires et locataires de la région PACA.</p><p>‍</p>'
    '<h5>1. Faites réviser votre climatiseur avant juin</h5>'
    '<p>Un entretien de printemps permet de contrôler le niveau de fluide '
    'frigorigène, l\'état des filtres, le bon fonctionnement du drain de condensat '
    'et les performances générales de l\'appareil. Un climatiseur révisé consomme '
    'moins, refroidit mieux et tombe rarement en panne en plein été. Si votre '
    'appareil date de plus de 10 ans, un <a href="/services/climatisation/" '
    'class="cn-inline-link">remplacement anticipé</a> peut être plus économique '
    'qu\'une panne en juillet.</p><p>‍</p>'
    '<h5>2. Nettoyez les filtres de votre climatisation</h5>'
    '<p>Accumulés pendant l\'hiver, poussières et allergènes saturent les filtres '
    'et réduisent l\'efficacité de l\'appareil. Cette opération simple prend moins '
    'de 15 minutes et peut améliorer les performances de votre climatiseur de '
    '10 à 15 %. Faites-le dès mars ou avril, avant les premières chaleurs.</p>'
)

PREPARER_MID2 = (
    '<h5>3. Vérifiez l\'unité extérieure</h5>'
    '<p>Après un hiver exposé aux intempéries, l\'unité extérieure peut être '
    'encrassée de dépôts calcaires, de feuilles ou de végétation. Vérifiez que '
    'les ailettes du condenseur sont propres et que l\'espace autour est dégagé '
    'sur au moins 50 cm. Une unité obstruée peut réduire l\'efficacité du système '
    'de 20 à 30 %.</p><p>‍</p>'
    '<h5>4. Préparez votre installation électrique</h5>'
    '<p>Si vous prévoyez d\'ajouter un climatiseur ou un ventilateur de plafond, '
    'faites vérifier votre tableau électrique. Un circuit surchargé peut provoquer '
    'des disjonctions répétées ou présenter un risque incendie. Nos '
    '<a href="/services/electricite/" class="cn-inline-link">électriciens à Nice</a> '
    'interviennent pour l\'ajout de circuits dédiés ou le remplacement de tableaux '
    'obsolètes.</p><p>‍</p>'
    '<h5>5. Optimisez la gestion thermique au quotidien</h5>'
    '<p>Quelques gestes simples réduisent les besoins en climatisation : fermer '
    'volets et rideaux côté soleil pendant la journée, ouvrir les fenêtres la nuit '
    'pour ventiler naturellement, et régler le thermostat à 26 °C plutôt qu\'à '
    '22 °C. Ces habitudes peuvent réduire votre consommation électrique estivale '
    'de 20 à 30 %.</p>'
    '<blockquote><em>« La meilleure façon de passer un été confortable à Nice, '
    'c\'est de préparer son logement au printemps — pas d\'attendre la première '
    'canicule. »</em></blockquote>'
    '<h4>Conclusion</h4>'
    '<p>Préparer son logement pour l\'été ne demande pas de gros investissements. '
    'Un entretien préventif bien planifié fait souvent toute la différence. '
    'Chez ClimaNova Energie, nous sommes disponibles dès mars pour planifier la '
    '<a href="/services/entretien/" class="cn-inline-link">révision de votre '
    'climatisation</a>, contrôler votre installation et vous conseiller sur les '
    'meilleures solutions pour votre logement à Nice.</p>'
)

# ── À lire aussi data per post ─────────────────────────────────────────────────
LIRE_AUSSI = {
    'blog/5-signes-climatisation-entretien-professionnel/index.html': [
        ('/blog/climatisation-verifications-avant-technicien/',
         'Climatisation : 5 choses à vérifier avant d\'appeler un technicien',
         'Mars 2026', 'climanova-blog-01'),
        ('/blog/prolonger-duree-vie-climatisation-conseils/',
         'Comment prolonger la durée de vie de votre climatiseur',
         'Avril 2026', 'climanova-blog-02'),
    ],
    'blog/choisir-prestataire-travaux-energetiques-nice/index.html': [
        ('/blog/5-signes-climatisation-entretien-professionnel/',
         '5 signes que votre climatisation a besoin d\'entretien professionnel',
         'Avril 2026', 'climanova-service-01'),
        ('/blog/solutions-economiques-confort-thermique-logement/',
         'Des solutions économiques pour améliorer le confort thermique',
         'Mars 2026', 'climanova-blog-03'),
    ],
    'blog/climatisation-verifications-avant-technicien/index.html': [
        ('/blog/5-signes-climatisation-entretien-professionnel/',
         '5 signes que votre climatisation a besoin d\'entretien professionnel',
         'Avril 2026', 'climanova-service-01'),
        ('/blog/prolonger-duree-vie-climatisation-conseils/',
         'Comment prolonger la durée de vie de votre climatiseur',
         'Avril 2026', 'climanova-blog-02'),
    ],
    'blog/prolonger-duree-vie-climatisation-conseils/index.html': [
        ('/blog/5-signes-climatisation-entretien-professionnel/',
         '5 signes que votre climatisation a besoin d\'entretien professionnel',
         'Avril 2026', 'climanova-service-01'),
        ('/blog/climatisation-verifications-avant-technicien/',
         'Climatisation : 5 choses à vérifier avant d\'appeler un technicien',
         'Mars 2026', 'climanova-blog-01'),
    ],
    'blog/solutions-economiques-confort-thermique-logement/index.html': [
        ('/blog/preparer-logement-ete-climanova/',
         'Préparer son logement pour l\'été : le guide ClimaNova',
         'Février 2026', 'climanova-blog-03'),
        ('/blog/choisir-prestataire-travaux-energetiques-nice/',
         'Choisir son prestataire de travaux énergétiques à Nice',
         'Avril 2026', 'climanova-blog-01'),
    ],
    'blog/preparer-logement-ete-climanova/index.html': [
        ('/blog/5-signes-climatisation-entretien-professionnel/',
         '5 signes que votre climatisation a besoin d\'entretien professionnel',
         'Avril 2026', 'climanova-service-01'),
        ('/blog/solutions-economiques-confort-thermique-logement/',
         'Des solutions économiques pour améliorer le confort thermique',
         'Mars 2026', 'climanova-blog-02'),
    ],
}

# ── Inline link patches for existing French posts ─────────────────────────────
# (file, phrase_to_find, anchor_to_wrap, href)
INLINE_LINKS = [
    # 5-signes
    ('blog/5-signes-climatisation-entretien-professionnel/index.html',
     'ne peut être réalisée que par un professionnel accrédité.',
     'professionnel accrédité', '/services/entretien/'),

    ('blog/5-signes-climatisation-entretien-professionnel/index.html',
     'Un entretien réalisé par ClimaNova peut réduire',
     'entretien réalisé par ClimaNova', '/services/entretien/'),

    ('blog/5-signes-climatisation-entretien-professionnel/index.html',
     'peut signaler un problème électrique qui nécessite',
     'problème électrique', '/services/electricite/'),

    # verifications
    ('blog/climatisation-verifications-avant-technicien/index.html',
     'dans l\'entretien de leurs installations.',
     'entretien de leurs installations', '/services/entretien/'),

    ('blog/climatisation-verifications-avant-technicien/index.html',
     'réservée aux professionnels certifiés.',
     'professionnels certifiés', '/services/entretien/'),

    ('blog/climatisation-verifications-avant-technicien/index.html',
     'pour l\'entretien, la vérification annuelle et le dépannage',
     'entretien, la vérification annuelle et le dépannage', '/services/entretien/'),

    # prolonger
    ('blog/prolonger-duree-vie-climatisation-conseils/index.html',
     'avec quelques gestes simples et un entretien régulier',
     'entretien régulier', '/services/entretien/'),

    ('blog/prolonger-duree-vie-climatisation-conseils/index.html',
     'l\'entretien, le dépannage et l\'installation de systèmes performants.',
     'installation de systèmes performants', '/services/climatisation/'),

    ('blog/prolonger-duree-vie-climatisation-conseils/index.html',
     'Un technicien contrôle l\'étanchéité',
     'Un technicien', '/services/entretien/'),

    # solutions
    ('blog/solutions-economiques-confort-thermique-logement/index.html',
     'ClimaNova propose la fourniture et l\'installation de systèmes mono-split et multi-split',
     'l\'installation de systèmes mono-split et multi-split', '/services/climatisation/'),

    ('blog/solutions-economiques-confort-thermique-logement/index.html',
     'éligibles aux aides à la rénovation énergétique.',
     'rénovation énergétique', '/services/renovation-energetique/'),

    ('blog/solutions-economiques-confort-thermique-logement/index.html',
     'Il rafraîchit en été et chauffe en hiver',
     'chauffe en hiver', '/services/chauffage/'),
]

# ── Main ───────────────────────────────────────────────────────────────────────
def process(path, top=None, mid1=None, mid2=None):
    with open(path, encoding='utf-8') as f:
        html = f.read()

    # 1. Content rewrites
    if top:
        html = replace_block(html, TOP_TAG, top)
    if mid1:
        html = replace_block(html, MID1_TAG, mid1)
    if mid2:
        html = replace_block(html, MID2_TAG, mid2)

    # 2. Inline links
    for fpath, phrase, anchor, href in INLINE_LINKS:
        if fpath == path and phrase in html:
            html = add_inline_link(html, phrase, href, anchor)

    # 3. À lire aussi block
    block = lire_aussi(LIRE_AUSSI[path])
    if '<div class="cn-blog-cta-banner">' in html:
        html = html.replace(
            '<div class="cn-blog-cta-banner">',
            block + '<div class="cn-blog-cta-banner">',
            1
        )

    with open(path, 'w', encoding='utf-8') as f:
        f.write(html)
    print(f'  done: {path}')


def main():
    print('=== Rewriting 2 English posts ===')
    process(
        'blog/choisir-prestataire-travaux-energetiques-nice/index.html',
        top=CHOISIR_TOP, mid1=CHOISIR_MID1, mid2=CHOISIR_MID2
    )
    process(
        'blog/preparer-logement-ete-climanova/index.html',
        top=PREPARER_TOP, mid1=PREPARER_MID1, mid2=PREPARER_MID2
    )

    print('\n=== Adding links to 4 French posts ===')
    for path in [
        'blog/5-signes-climatisation-entretien-professionnel/index.html',
        'blog/climatisation-verifications-avant-technicien/index.html',
        'blog/prolonger-duree-vie-climatisation-conseils/index.html',
        'blog/solutions-economiques-confort-thermique-logement/index.html',
    ]:
        process(path)

    print('\nDone.')


if __name__ == '__main__':
    main()
