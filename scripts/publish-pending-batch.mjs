import fs from "node:fs";
import path from "node:path";

const root = process.cwd();
const todayIso = "2026-07-09";
const todayFr = "9 juillet 2026";
const queuePath = path.join(root, "scripts/content-queue.json");
const blogIndexPath = path.join(root, "blog/index.html");
const sitemapPath = path.join(root, "sitemap.xml");
const blogCssPath = path.join(root, "css/pages/blog.css");

const cta = `<div class="cn-blog-cta-banner cn-blog-cta-banner--full"><h3 class="cn-blog-cta-title">Besoin d'un devis&nbsp;?</h3><p class="cn-blog-cta-banner-text">Recevez des devis gratuits pour vos projets de climatisation, chauffage, pompe à chaleur et rénovation énergétique.</p><ul class="cn-blog-cta-list"><li>Étude technique à domicile</li><li>Jusqu'à 3 devis détaillés</li><li>Installateurs qualifiés de votre secteur</li></ul><a href="/demande-devis/" class="cn-blog-cta-banner-btn">Demander des devis gratuits</a><p class="cn-blog-cta-note">Service gratuit &amp; sans engagement</p></div>`;

const recentPosts = [
  {
    href: "/blog/fuite-eau-urgence-que-faire/",
    title: "Fuite d'eau urgence : que faire ?",
    img: "/images/climanova-blog-fuite-eau-urgence-que-faire-featured-ai.webp",
  },
  {
    href: "/blog/climatisation-ne-refroidit-plus-causes-solutions/",
    title: "Climatisation qui ne refroidit plus",
    img: "/images/climanova-blog-clim-ne-refroidit-plus-ai.webp",
  },
  {
    href: "/blog/installateur-climatisation-nice/",
    title: "Installateur climatisation Nice",
    img: "/images/climanova-blog-04.webp",
  },
  {
    href: "/blog/entretien-climatisation-nice/",
    title: "Entretien climatisation Nice",
    img: "/images/blog-entretien-climatisation-nice.svg",
  },
];

const posts = [
  {
    slug: "prix-climatisation-appartement-2026",
    keyword: "prix climatisation appartement",
    title: "Prix climatisation appartement 2026 : budget, devis et aides",
    h1: "Prix climatisation appartement 2026 : combien prevoir pour un logement confortable ?",
    description:
      "Prix climatisation appartement 2026 : monosplit, multisplit, gainable, pose, entretien, aides possibles et conseils pour comparer un devis fiable.",
    excerpt:
      "Studio, T2, T3 ou grand appartement : les prix 2026 pour climatiser sans surpayer, avec les postes de devis a controler avant signature.",
    image: "/images/climanova-blog-04.webp",
    imageFallback: "/images/climanova-blog-04.jpg",
    bannerClass: "cn-banner-blog-prix-appartement",
    readTime: "13 min de lecture",
    sections: [
      {
        h2: "Quel prix pour climatiser un appartement en 2026 ?",
        body: [
          "Le prix climatisation appartement varie surtout selon la surface, le nombre de pieces a traiter, la distance entre l'unite interieure et l'unite exterieure, les contraintes de copropriete et le niveau de gamme choisi. Pour un studio ou un T2, un monosplit bien dimensionne suffit souvent. Pour un T3 ou un T4, un multisplit devient plus coherent si plusieurs chambres doivent rester confortables la nuit.",
          "En 2026, un budget prudent se situe souvent entre 1 500 et 3 500 euros pour une piece principale, entre 3 500 et 7 500 euros pour deux a trois pieces, et au-dela de 8 000 euros lorsque l'appartement impose un reseau gainable, plusieurs liaisons frigorifiques ou des travaux d'integration plus lourds.",
        ],
      },
      {
        h2: "Tableau des budgets par configuration",
        body: [
          `<table><thead><tr><th>Appartement</th><th>Solution courante</th><th>Budget pose comprise</th><th>Cas ideal</th></tr></thead><tbody><tr><td>Studio / T1</td><td>Monosplit mural</td><td>1 500 a 3 000 euros</td><td>Piece principale unique</td></tr><tr><td>T2</td><td>Monosplit ou bi-split</td><td>2 000 a 4 800 euros</td><td>Salon + chambre</td></tr><tr><td>T3</td><td>Bi-split / tri-split</td><td>4 000 a 7 500 euros</td><td>Confort nocturne</td></tr><tr><td>T4 et plus</td><td>Multisplit ou gainable</td><td>6 500 a 12 000 euros</td><td>Projet global</td></tr></tbody></table>`,
          "Ces fourchettes restent indicatives. Un devis serieux doit preciser la puissance en kW, le niveau sonore, le rendement saisonnier, les longueurs de liaisons, la protection electrique, les percements, l'evacuation des condensats et la mise en service.",
        ],
      },
      {
        h2: "Les postes qui font monter le devis",
        body: [
          "La plus grosse difference de prix ne vient pas toujours de la marque. Elle vient souvent de la difficulte de pose : acces a la facade, autorisation de copropriete, passage des goulottes, evacuation des condensats, hauteur de pose, besoin d'une nacelle ou cheminement discret dans un faux plafond.",
          "A Nice et dans les Alpes-Maritimes, les appartements anciens, les immeubles de centre-ville et les residences avec reglement strict demandent une etude plus fine. Une unite exterieure visible depuis la rue ou posee sur balcon peut exiger une validation prealable.",
        ],
      },
      {
        h2: "Comment comparer deux devis de climatisation",
        body: [
          "Comparez a prestation egale : puissance installee, nombre d'unites, marque et reference exacte, garantie, travaux electriques inclus ou non, attestation de capacite fluide frigorigene, delai de pose et entretien propose. Un devis moins cher qui oublie la protection electrique ou la mise en service peut devenir plus couteux apres coup.",
          "Demandez aussi le niveau sonore de l'unite interieure en mode nuit et celui de l'unite exterieure. En appartement, ce point compte autant que le rendement : une climatisation performante mais bruyante peut devenir un probleme de voisinage.",
        ],
      },
      {
        h2: "Aides, TVA et rentabilite",
        body: [
          "La climatisation air-air reversible est principalement eligible aux primes CEE selon les operations et les conditions du moment. MaPrimeRenov finance surtout les travaux d'efficacite energetique et les parcours de renovation ; l'eligibilite exacte d'une climatisation reversible doit etre verifiee avant devis sur France Renov ou avec un conseiller.",
          "La rentabilite depend de l'usage : si l'appareil remplace des radiateurs electriques anciens en mi-saison, l'economie peut etre sensible. Si l'usage est uniquement estival, le gain est surtout du confort et de la valeur d'usage du logement.",
        ],
      },
    ],
    faqs: [
      ["Quel est le prix moyen d'une climatisation pour appartement ?", "Le prix moyen se situe souvent entre 1 500 et 7 500 euros pose comprise selon le nombre de pieces. Un studio coute moins cher qu'un T3 avec plusieurs unites interieures."],
      ["Faut-il l'accord de la copropriete ?", "Oui des qu'une unite exterieure modifie la facade, le balcon ou les parties communes. Il faut verifier le reglement et obtenir l'accord avant les travaux."],
      ["Un monosplit suffit-il pour un appartement ?", "Oui pour une piece principale ou un petit logement ouvert. Pour plusieurs chambres, un multisplit apporte un confort plus stable."],
      ["Quel entretien prevoir ?", "Nettoyez les filtres plusieurs fois par an et prevoyez un controle professionnel regulier pour conserver performance, silence et duree de vie."],
    ],
  },
  {
    slug: "prix-climatisation-maison-2026",
    keyword: "prix climatisation maison",
    title: "Prix climatisation maison 2026 : cout complet par surface",
    h1: "Prix climatisation maison 2026 : budget complet par surface et solution",
    description:
      "Prix climatisation maison 2026 : monosplit, multisplit, gainable, pompe a chaleur air-air, cout de pose, entretien, aides et devis fiable.",
    excerpt:
      "Maison de 80, 120 ou 160 m2 : les budgets realistes pour installer une climatisation reversible confortable et durable en 2026.",
    image: "/images/climanova-service-02.webp",
    imageFallback: "/images/climanova-service-02.jpg",
    bannerClass: "cn-banner-blog-prix-maison",
    readTime: "14 min de lecture",
    sections: [
      {
        h2: "Combien coute une climatisation de maison en 2026 ?",
        body: [
          "Le prix climatisation maison depend de trois decisions : climatiser seulement les pieces de vie, couvrir aussi les chambres, ou traiter toute la maison avec une solution gainable. Pour une maison, le dimensionnement est plus sensible qu'en appartement car les volumes, l'exposition, l'isolation, les baies vitrees et les combles changent fortement le besoin.",
          "En 2026, une installation simple pour une piece de vie demarre souvent autour de 1 800 a 3 500 euros. Une maison avec trois ou quatre zones peut atteindre 6 000 a 12 000 euros. Un gainable confortable et discret peut aller de 9 000 a 18 000 euros selon les combles, les bouches, les reprises d'air et la regulation par zone.",
        ],
      },
      {
        h2: "Budget par surface",
        body: [
          `<table><thead><tr><th>Surface</th><th>Solution frequente</th><th>Budget indicatif</th><th>Point de vigilance</th></tr></thead><tbody><tr><td>60 a 80 m2</td><td>1 a 2 splits</td><td>2 000 a 5 500 euros</td><td>Piece principale + chambre</td></tr><tr><td>90 a 120 m2</td><td>Multisplit</td><td>5 500 a 11 000 euros</td><td>Longueur des liaisons</td></tr><tr><td>120 a 160 m2</td><td>Multisplit ou gainable</td><td>8 000 a 16 000 euros</td><td>Regulation par zone</td></tr><tr><td>Maison etagee</td><td>Mix split + gainable</td><td>10 000 a 20 000 euros</td><td>Confort nuit / jour</td></tr></tbody></table>`,
          "Ces budgets incluent generalement materiel et pose, mais pas toujours les adaptations electriques importantes, reprises de placo, faux plafonds ou travaux de peinture. Le devis doit les separer clairement.",
        ],
      },
      {
        h2: "Multisplit ou gainable : que choisir ?",
        body: [
          "Le multisplit est souple : chaque piece dispose de son unite interieure et sa temperature. Il convient aux renovations ou l'on veut limiter les travaux. Le gainable est plus discret : les bouches sont integrees au plafond, mais il suppose des combles accessibles ou des faux plafonds.",
          "Pour une maison familiale, le meilleur choix n'est pas toujours le plus puissant. Une installation trop forte coute plus cher, s'use plus vite et peut donner une sensation d'air froid. Une etude thermique simple permet d'ajuster la puissance et de placer les unites au bon endroit.",
        ],
      },
      {
        h2: "Pourquoi les devis varient autant",
        body: [
          "La marque, le rendement SCOP/SEER, le niveau sonore, le nombre de percements, la longueur des liaisons frigorifiques et l'acces chantier expliquent les ecarts. Une maison exposee plein sud avec grandes baies vitrees demandera une approche differente d'une maison mitoyenne bien isolee.",
          "Dans les Alpes-Maritimes, les fortes chaleurs d'ete poussent beaucoup de foyers a demander une climatisation en urgence. Anticiper hors pic saisonnier facilite le planning et donne plus de temps pour comparer les solutions.",
        ],
      },
      {
        h2: "Cout d'usage et entretien",
        body: [
          "Une climatisation reversible bien dimensionnee peut aussi chauffer en mi-saison avec un bon rendement. Le cout d'usage depend du reglage : viser 25 a 26 degres en ete consomme beaucoup moins que descendre brutalement a 20 degres.",
          "L'entretien protege la performance : filtres propres, unite exterieure degagee, controle d'etancheite si necessaire, verification des condensats et mesure des temperatures. Prevoir un contrat annuel est souvent pertinent pour une maison avec plusieurs unites.",
        ],
      },
    ],
    faqs: [
      ["Quel budget pour climatiser une maison de 100 m2 ?", "Le budget courant se situe entre 6 000 et 12 000 euros selon le nombre de zones, la gamme choisie et les contraintes de pose."],
      ["Le gainable coute-t-il plus cher ?", "Oui, mais il est plus discret et plus confortable quand les combles ou faux plafonds permettent une installation propre."],
      ["Une climatisation reversible peut-elle chauffer toute la maison ?", "Oui dans beaucoup de cas, surtout en climat doux. Il faut toutefois verifier l'isolation et conserver un appoint si la maison est mal isolee."],
      ["Quand demander un devis ?", "Idealement avant l'ete, pour eviter les delais de haute saison et laisser le temps a l'etude technique."],
    ],
  },
  {
    slug: "climatisation-aides-maprimerenov-cee-2026",
    keyword: "aides climatisation MaPrimeRenov",
    title: "Aides climatisation MaPrimeRenov et CEE 2026 : ce qui est possible",
    h1: "Aides climatisation MaPrimeRenov et CEE 2026 : comprendre les options avant devis",
    description:
      "Aides climatisation MaPrimeRenov et CEE 2026 : eligibilite, primes energie, TVA, conditions RGE, documents a demander et pieges a eviter.",
    excerpt:
      "MaPrimeRenov, CEE, TVA et devis RGE : le point clair pour financer une climatisation reversible sans promettre de fausses aides.",
    image: "/images/climanova-blog-02.webp",
    imageFallback: "/images/climanova-blog-02.jpg",
    bannerClass: "cn-banner-blog-aides-clim",
    readTime: "12 min de lecture",
    sections: [
      {
        h2: "Peut-on obtenir des aides pour une climatisation en 2026 ?",
        body: [
          "Les aides climatisation MaPrimeRenov et CEE doivent etre distinguees. MaPrimeRenov vise la renovation energetique et ses regles changent regulierement. Les Certificats d'Economies d'Energie, ou CEE, peuvent soutenir certaines pompes a chaleur air-air selon les fiches d'operation, les performances de l'appareil et les offres des obliges.",
          "La bonne methode consiste a verifier l'eligibilite avant signature, pas apres. Un installateur serieux indique la reference exacte du materiel, son rendement, son statut RGE si necessaire, les documents a fournir et le montant de prime estime sans le presenter comme acquis tant que le dossier n'est pas valide.",
        ],
      },
      {
        h2: "MaPrimeRenov : attention aux confusions",
        body: [
          "MaPrimeRenov est pilotee par l'ANAH et le service public France Renov. Elle finance prioritairement des travaux de performance energetique, avec des parcours et plafonds selon les revenus, le logement et le gain energetique. Une climatisation reversible air-air seule n'est pas a traiter comme une prime automatique.",
          "Les informations publiques evoluent. En 2026, il faut controler le cas exact sur France Renov, surtout si le projet s'inscrit dans une renovation plus large avec isolation, ventilation, remplacement de chauffage ou audit energetique.",
        ],
      },
      {
        h2: "CEE : la piste la plus frequente pour l'air-air",
        body: [
          "Les CEE sont verses par des fournisseurs d'energie ou leurs partenaires. Leur montant depend de la zone climatique, de la surface chauffee, des performances de l'equipement et de l'offre commerciale disponible au moment du dossier. Le montant peut varier fortement d'un acteur a l'autre.",
          `<table><thead><tr><th>Aide</th><th>Organisme</th><th>Pour clim reversible ?</th><th>A verifier</th></tr></thead><tbody><tr><td>MaPrimeRenov</td><td>ANAH / France Renov</td><td>Selon parcours et travaux</td><td>Eligibilite officielle avant devis</td></tr><tr><td>CEE</td><td>Fournisseurs d'energie</td><td>Souvent la piste principale</td><td>Fiche operation, rendement, dossier avant travaux</td></tr><tr><td>TVA reduite</td><td>Etat</td><td>Selon nature des travaux</td><td>Logement, age, type de prestation</td></tr><tr><td>Aides locales</td><td>Commune / region</td><td>Cas par cas</td><td>Reglement local</td></tr></tbody></table>`,
        ],
      },
      {
        h2: "Documents a demander avant de signer",
        body: [
          "Demandez la marque, le modele, la puissance, les rendements saisonniers, l'attestation de capacite pour les fluides frigorigenes, les conditions de garantie, la mention de mise en service, le montant de prime CEE estime et la procedure de depot. Le dossier d'aide doit etre engage avant le debut des travaux lorsque le dispositif l'exige.",
          "Refusez les promesses de climatisation a un euro, les demarchages insistants et les montants d'aide non justifies. Les aides serieuses laissent toujours une trace : fiche technique, devis detaille, conditions d'eligibilite et calendrier.",
        ],
      },
      {
        h2: "Comment ClimaNova securise votre demande",
        body: [
          "ClimaNova Energie commence par une visite ou un echange technique : surface, exposition, isolation, tableau electrique, emplacement des unites, contraintes de copropriete et objectifs de chauffage ou rafraichissement. Le devis se construit ensuite avec une solution dimensionnee et des options lisibles.",
          "Pour les projets a Nice, Cannes, Antibes, Menton et dans les Alpes-Maritimes, cette verification evite les erreurs les plus couteuses : puissance inadaptee, aide promise mais non recevable, unite exterieure mal acceptee par la copropriete ou consommation plus forte que prevu.",
        ],
      },
    ],
    faqs: [
      ["MaPrimeRenov finance-t-elle une climatisation reversible ?", "Ce n'est pas une aide automatique. L'eligibilite depend du parcours, du logement, des travaux associes et des regles en vigueur. Verifiez toujours sur France Renov avant signature."],
      ["Les CEE sont-ils cumulables ?", "Les CEE peuvent parfois se cumuler avec d'autres dispositifs, mais chaque dossier a ses conditions. Le cumul doit etre valide avant le demarrage des travaux."],
      ["Faut-il un artisan RGE ?", "Pour de nombreuses aides, une qualification reconnue et des attestations techniques sont indispensables. Le devis doit afficher les qualifications pertinentes."],
      ["Quand deposer le dossier d'aide ?", "Toujours avant les travaux lorsque le dispositif l'impose. Un dossier lance trop tard peut rendre la prime irrecevable."],
    ],
  },
];

function escapeJson(value) {
  return value.replace(/\\/g, "\\\\").replace(/"/g, '\\"');
}

function layout(post) {
  const faqJson = post.faqs
    .map(([q, a]) => `{"@type":"Question","name":"${escapeJson(q)}","acceptedAnswer":{"@type":"Answer","text":"${escapeJson(a)}"}}`)
    .join(",");
  const sectionHtml = post.sections
    .map(
      (section, index) => `
              <h2>${section.h2}</h2>
              ${index === 0 ? `<div class="cn-direct-answer"><strong>Reponse courte :</strong> ${post.excerpt}</div>` : ""}
              ${section.body.map((p) => (p.startsWith("<table") ? p : `<p>${p}</p>`)).join("\n              ")}
              ${index === 1 ? cta : ""}`,
    )
    .join("\n");

  const faqHtml = post.faqs.map(([q, a]) => `<h3>${q}</h3><p>${a}</p>`).join("\n              ");
  const recentHtml = recentPosts
    .map(
      (item) => `<a href="${item.href}" class="blog-details-sidebar-post-link w-inline-block"><img src="${item.img}" loading="lazy" alt="${item.title}" class="blog-details-sidebar-post-image"><div class="blog-details-sidebar-post-title">${item.title}</div></a>`,
    )
    .join("");

  return `<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<title>${post.title} | ClimaNova</title>
<meta name="description" content="${post.description}">
<meta property="og:title" content="${post.title}">
<meta property="og:description" content="${post.description}">
<meta property="og:type" content="article">
<meta property="og:url" content="https://climanova-energie.fr/blog/${post.slug}/">
<meta property="og:image" content="https://climanova-energie.fr${post.image}">
<meta name="twitter:image" content="https://climanova-energie.fr${post.image}">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="${post.title}">
<meta name="twitter:description" content="${post.description}">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="canonical" href="https://climanova-energie.fr/blog/${post.slug}/">
<link href="/css/global.css" rel="stylesheet" type="text/css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<script src="/js/webfont.js"></script>
<script>WebFont.load({google:{families:["Instrument Sans:300,400,500,600,700"]}});</script>
<script>!function(o,c){var n=c.documentElement,t=" w-mod-";n.className+=t+"js",("ontouchstart"in o||o.DocumentTouch&&c instanceof DocumentTouch)&&(n.className+=t+"touch")}(window,document);</script>
<link rel="shortcut icon" href="/images/69787fd4f36671416d6263df_ClimaNova Energie-favicon.png" type="image/x-icon">
<link rel="apple-touch-icon" href="/images/6978808c50851a02e3fc681e_ClimaNova Energie-favicon.png">
<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"BlogPosting","headline":"${escapeJson(post.h1)}","description":"${escapeJson(post.description)}","url":"https://climanova-energie.fr/blog/${post.slug}/","datePublished":"${todayIso}","dateModified":"${todayIso}","inLanguage":"fr","image":{"@type":"ImageObject","url":"https://climanova-energie.fr${post.image}"},"author":{"@id":"https://climanova-energie.fr/#organization"},"publisher":{"@id":"https://climanova-energie.fr/#organization"},"mainEntityOfPage":{"@type":"WebPage","@id":"https://climanova-energie.fr/blog/${post.slug}/"},"keywords":"${escapeJson(post.keyword)}, climatisation Nice, ClimaNova Energie"},{"@type":"FAQPage","mainEntity":[${faqJson}]},{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Accueil","item":"https://climanova-energie.fr/"},{"@type":"ListItem","position":2,"name":"Blog","item":"https://climanova-energie.fr/blog/"},{"@type":"ListItem","position":3,"name":"${escapeJson(post.title)}"}]},{"@type":"Service","name":"${escapeJson(post.keyword)}","provider":{"@id":"https://climanova-energie.fr/#organization"},"areaServed":{"@type":"AdministrativeArea","name":"Alpes-Maritimes"},"url":"https://climanova-energie.fr/services/climatisation/"},{"@type":"LocalBusiness","name":"ClimaNova Energie","url":"https://climanova-energie.fr","telephone":"+33652238164","priceRange":"€€","areaServed":[{"@type":"City","name":"Nice"},{"@type":"City","name":"Antibes"},{"@type":"City","name":"Cannes"},{"@type":"AdministrativeArea","name":"Alpes-Maritimes"}],"openingHoursSpecification":[{"@type":"OpeningHoursSpecification","dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],"opens":"08:00","closes":"19:00"}],"aggregateRating":{"@type":"AggregateRating","ratingValue":"4.9","reviewCount":"127"}}]}</script>
<link rel="alternate" hreflang="fr" href="https://climanova-energie.fr/blog/${post.slug}/">
<link rel="alternate" hreflang="x-default" href="https://climanova-energie.fr/blog/${post.slug}/">
</head>
<body><div class="page-wrapper"><div data-component="site-header"></div><main class="main-wrapper">
<section class="blog-details-banner-section ${post.bannerClass}"><div class="blog-details-banner-gradient"></div><div class="container"><div class="blog-details-banner-content-wrapper"><div class="blog-details-banner-text-block"><h1 class="h2-style text-light cn-anim-visible">${post.h1}</h1><div class="blog-details-banner-meta-block cn-anim-visible"><div class="text-lg neutral-color-08">Par l'equipe ClimaNova Energie</div><div class="blog-details-banner-meta-divider"></div><div class="text-lg neutral-color-08">${todayFr}</div><div class="blog-details-banner-meta-divider"></div><div class="text-lg neutral-color-08">${post.readTime}</div></div></div></div></div></section>
<section class="blog-details-section"><div class="container"><div class="blog-details-content-wrapper"><div class="blog-details-content-block"><div class="blog-details-top-content-block w-richtext cn-anim-visible">
              <p>${post.description}</p>
              <div class="cn-key-takeaway"><span class="cn-key-takeaway-icon">i</span><div><strong>Points cles :</strong> demandez toujours un devis detaille, verifiez les contraintes du logement, comparez les performances saisonnieres et confirmez les aides avant de signer.</div></div>
              <picture><source srcset="${post.image}" type="image/webp"><img src="${post.imageFallback}" loading="lazy" alt="${post.title}" width="1400" height="787"></picture>
${sectionHtml}
              <h2>Questions frequentes</h2>
              ${faqHtml}
              ${cta}
            </div></div><aside class="cn-blog-sidebar"><div class="blog-details-sidebar-post-block"><h3 class="h5-style">Vous aimerez aussi</h3>${recentHtml}</div>${cta}</aside></div></div></section>
</main><div data-component="site-footer"></div></div><script src="/js/jquery-3.5.1.min.dc5e7f18c8.js"></script><script src="/js/components/site-header.js"></script><script src="/js/components/site-footer.js"></script><script src="/js/core/main.js"></script></body></html>`;
}

function card(post) {
  return `<div role="listitem" class="w-dyn-item"><a data-w-id="d488816c-c85f-a3a9-acf9-317585844aa9" href="/blog/${post.slug}/" class="blog-widget w-inline-block cn-anim-hidden"><div class="blog-widget-image-block"><picture><source srcset="${post.image}" type="image/webp"><img src="${post.imageFallback}" loading="lazy" alt="${post.title}" class="blog-widget-image cn-anim-in-origin" width="1400" height="787" style="object-fit:cover;width:100%;height:100%;"/></picture></div><div class="blog-widget-content-block"><div class="blog-widget-content-block-inner"><div class="blog-slide-meta-block"><div class="text-md">${todayFr}</div><div class="blog-slide-meta-divider"></div><div class="text-md">${post.readTime}</div></div><div class="blog-widget-text-block"><h2 class="h4-style">${post.title}</h2><p class="text-md">${post.excerpt}</p></div></div><div class="blog-widget-button"><div data-w-id="7f6adf3b-e806-8832-6c3a-5cabd19e1b02" class="blog-button-link cn-text-dark"><div class="button-text">Lire la suite</div></div></div></div></a><div data-w-id="93bc52a5-594e-de17-9c28-8a9e0f5e0ddf" class="blog-widget-divider cn-anim-hidden"></div></div>`;
}

for (const post of posts) {
  const dir = path.join(root, "blog", post.slug);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, "index.html"), layout(post));
}

let blogIndex = fs.readFileSync(blogIndexPath, "utf8");
const marker = '<div role="list" class="w-dyn-items">';
if (!blogIndex.includes(`/blog/${posts[0].slug}/`)) {
  blogIndex = blogIndex.replace(marker, `${marker}${posts.map(card).join("")}`);
}
fs.writeFileSync(blogIndexPath, blogIndex);

let sitemap = fs.readFileSync(sitemapPath, "utf8");
sitemap = sitemap.replace(/<loc>https:\/\/climanova-energie\.fr\/blog\/<\/loc><lastmod>[^<]+<\/lastmod>/, `<loc>https://climanova-energie.fr/blog/</loc><lastmod>${todayIso}</lastmod>`);
for (const post of posts) {
  const entry = `  <url><loc>https://climanova-energie.fr/blog/${post.slug}/</loc><lastmod>${todayIso}</lastmod><priority>0.8</priority><changefreq>monthly</changefreq></url>\n`;
  if (!sitemap.includes(`/blog/${post.slug}/`)) {
    sitemap = sitemap.replace("</urlset>", `${entry}</urlset>`);
  }
}
fs.writeFileSync(sitemapPath, sitemap);

let css = fs.readFileSync(blogCssPath, "utf8");
const cssAdd = posts
  .map((post) => `\n.${post.bannerClass} {\n  background-image: url('../..${post.image}');\n  background-position: center 42%;\n}\n`)
  .join("");
if (!css.includes(posts[0].bannerClass)) {
  fs.writeFileSync(blogCssPath, `${css}\n${cssAdd}`);
}

const queue = JSON.parse(fs.readFileSync(queuePath, "utf8"));
for (const row of queue) {
  if (posts.some((post) => post.slug === row.slug)) {
    row.status = "done";
    row.published = todayIso;
  }
}
fs.writeFileSync(queuePath, `${JSON.stringify(queue, null, 2)}\n`);
