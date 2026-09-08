# Sprint SEO — `/climatisation-nice/`

Objectif : première page Google (Nice / Côte d'Azur) sur « climatisation Nice » et requêtes associées.
Plan complet jour par jour : voir l'artefact partagé (audit + 30 jours). Ce fichier regroupe **ce qui n'est pas du code** : mots-clés à suivre, mise en route Search Console, et contenu prêt-à-coller pour la fiche Google Business Profile.

Statut du 2026-09-09 — fait dans le dépôt ce jour :
- Nœud `HVACBusiness` ajouté au `@graph` de la page (geo 43.726332 / 7.286618, `openingHoursSpecification`, `areaServed`, `makesOffer`, `sameAs`).
  → **À faire manuellement :** remplacer l'URL `sameAs` Google Maps par l'URL réelle de la fiche GBP (lien « Partager » de la fiche, format `https://maps.app.goo.gl/…` ou `?cid=…`).
- « Climatisation à Nice » ajouté au menu déroulant Services (`components/header.html`) — s'applique à tout le site (lien interne sitewide vers la page pilier, voulu).
- Colonne « Zones d'intervention » ajoutée au footer (`components/footer.html`) : Nice, Antibes, Cannes, Menton, Cagnes-sur-Mer.
- Section « Prix Climatisation à Nice 2026 » (tableau + reste à charge) + paragraphe featured-snippet après l'intro.
- FAQ portée de 5 à 13 questions (visible `<details>` **et** `FAQPage` JSON-LD synchronisés).
- `sitemap.xml` : ajout des 4 pages ville manquantes, `lastmod` = 2026-09-09, `changefreq` `weekly` pour la page Nice.

Reste à faire hors dépôt : soumettre le sitemap dans GSC + Bing, optimiser la fiche GBP (contenu ci-dessous), remplacer le hero et les photos « Réalisations » par de vraies photos de chantiers niçois géotaggées, brancher le suivi de positions.

---

## 1. Mots-clés à suivre dès le J1 (25)

Suivi hebdomadaire, en géolocalisation **Nice (06)**, desktop + mobile, + suivi Local Pack.

### Primaires — transaction
| Mot-clé | Page cible |
|---|---|
| climatisation nice | /climatisation-nice/ |
| climatisation à nice | /climatisation-nice/ |
| installateur climatisation nice | /climatisation-nice/ |
| entreprise climatisation nice | /climatisation-nice/ |
| devis climatisation nice | /climatisation-nice/ |
| installation climatisation nice | /climatisation-nice/ |
| pose climatisation nice | /climatisation-nice/ |

### Réversible / PAC air-air
climatisation réversible nice · clim réversible nice · pompe à chaleur air air nice · climatisation gainable nice · multi split nice

### Qualité / réassurance
climatisation rge nice · meilleur installateur climatisation nice · climatisation nice avis · climatisation nice pas cher

### Service après installation
dépannage climatisation nice · entretien climatisation nice · réparation climatisation nice

### Longue traîne
prix climatisation nice · aide climatisation nice · climatisation appartement nice · climatisation copropriété nice

### Communes limitrophes
climatisation cagnes-sur-mer · climatisation saint-laurent-du-var · climatisation antibes · climatisation cannes · climatisation villeneuve-loubet

---

## 2. Search Console / Bing — mise en route (J1)

- [ ] **Google Search Console** — propriété **domaine** (`climanova-energie.fr`), validée par enregistrement DNS TXT.
- [ ] Soumettre `https://climanova-energie.fr/sitemap.xml` dans GSC → *Sitemaps*.
- [ ] **Bing Webmaster Tools** — importer depuis GSC, soumettre le même sitemap.
- [ ] GSC → *Inspection d'URL* sur `https://climanova-energie.fr/climatisation-nice/` → « Demander une indexation ».
- [ ] Vérifier `site:climanova-energie.fr/climatisation-nice/` dans Google (doit remonter la page).
- [ ] Créer le suivi de positions (SE Ranking / Ahrefs / Localo) sur les 25 mots-clés ci-dessus.
- [ ] **Capture datée** des positions de départ : « climatisation nice », « installateur climatisation nice », « climatisation réversible nice » (screenshot + tableur).
- [ ] GSC → *Performances* → filtre page = `/climatisation-nice/` : noter impressions / clics / position moyenne de référence.

**Terminé quand :** sitemap soumis (GSC + Bing), 25 mots-clés suivis, capture de référence archivée.

---

## 3. Google Business Profile — contenu prêt-à-coller

NAP strictement identique partout :
```
Nom      : ClimaNova Énergie
Adresse  : 218 Route de Turin, 06300 Nice
Tél      : +33 6 52 23 81 64
Site     : https://climanova-energie.fr
```

### Catégories
- **Principale :** Entreprise de climatisation
- **Secondaires :** Chauffagiste · Installateur de pompes à chaleur · Entreprise de chauffage · Service de réparation de climatiseurs · Électricien · Plombier · Entreprise de rénovation

### Zone desservie
Nice, Saint-Laurent-du-Var, Cagnes-sur-Mer, Villeneuve-Loubet, Antibes, Biot, Valbonne, Cannes, Le Cannet, Mougins, Mandelieu-la-Napoule, Grasse, Vence, Carros, Villefranche-sur-Mer, Beaulieu-sur-Mer, Èze, Cap-d'Ail, Roquebrune-Cap-Martin, Menton.
→ Garder l'adresse **visible** (établissement réel).

### Horaires
Lun–Ven 08:00–18:00 · Sam 09:00–17:00 · Dim fermé

### Attributs
Devis gratuit · Sur rendez-vous · Paiements : espèces, carte, virement, chèque, financement · Intervention d'urgence

### Description (≤ 750 caractères)
> Installation de climatisation à Nice et sur toute la Côte d'Azur par ClimaNova Énergie, installateur certifié RGE QualiPAC. Nous posons des climatiseurs réversibles mono-split, multi-split et gainables, dimensionnés pour le climat méditerranéen et le bâti niçois (copropriétés, secteurs Bâtiments de France, quartiers côtiers exposés aux embruns). Marques Mitsubishi Electric, Toshiba, Panasonic, Hisense, Atlantic Fujitsu. Nous gérons vos aides : CEE, TVA 5,5 %, MaPrimeRénov' selon éligibilité. Dépannage et entretien 6 j/7, contrats de maintenance annuels. Devis gratuit et détaillé sous 24 h. Intervention à Nice, Cagnes-sur-Mer, Saint-Laurent-du-Var, Antibes, Cannes, Menton et alentours. Appelez le 06 52 23 81 64 ou demandez votre devis en ligne.

### Services (nom + description ≤ 300 car., « Nice » dans chacune)
1. **Installation climatisation réversible Nice** — Pose de climatisation réversible mono et multi-split à Nice par techniciens RGE QualiPAC. Étude de dimensionnement, choix de la marque, gestion de l'autorisation de copropriété, mise en service et accompagnement aides (CEE, TVA 5,5 %). Devis gratuit sous 24 h.
2. **Climatisation gainable Nice** — Installation de climatisation gainable à diffusion invisible à Nice, idéale en rénovation avec faux plafonds ou en secteur Bâtiments de France. Groupe extérieur discret, bouches sur mesure, réseau de gaines isolées.
3. **Dépannage climatisation Nice** — Dépannage de climatisation à Nice 6 j/7 : panne de froid, fuite de fluide, unité qui givre, code défaut, bruit anormal. Diagnostic rapide, intervention prioritaire pendant les canicules.
4. **Entretien climatisation Nice** — Contrat d'entretien annuel de climatisation à Nice : nettoyage des unités, contrôle d'étanchéité du circuit frigorifique (obligatoire tous les 2 ans), vérification des performances et des filtres.
5. **Installation pompe à chaleur air/air Nice** — Installation de pompe à chaleur air/air à Nice pour chauffer et rafraîchir avec un seul équipement. SCOP jusqu'à 5, éligible CEE, dimensionnement selon l'exposition et l'isolation du logement.

Lien de prise de rendez-vous : `https://climanova-energie.fr/demande-devis/`

### Questions / Réponses à publier (12)
Poser depuis un autre compte Google, répondre depuis la fiche. Réponses ≤ 200 car., « Nice » quand c'est naturel.

1. **Q :** Quel est le prix d'une climatisation à Nice ? — **R :** Comptez 1 800–3 200 € pour un mono-split et 5 000–11 000 € pour un multi-split posé à Nice. Reste à charge réduit par les CEE et la TVA 5,5 %. Devis gratuit sous 24 h.
2. **Q :** Êtes-vous certifiés RGE ? — **R :** Oui, ClimaNova Énergie est certifié RGE QualiPAC, condition indispensable pour les aides CEE et MaPrimeRénov' sur vos travaux à Nice.
3. **Q :** Intervenez-vous en urgence pour une panne de clim ? — **R :** Oui, dépannage à Nice et dans l'agglomération 6 j/7, priorité pendant les fortes chaleurs. Appelez le 06 52 23 81 64.
4. **Q :** Quelles communes couvrez-vous autour de Nice ? — **R :** Nice, Saint-Laurent-du-Var, Cagnes-sur-Mer, Antibes, Cannes, Vence, Villefranche, Beaulieu, Menton et une vingtaine de communes des Alpes-Maritimes.
5. **Q :** Faut-il l'accord de la copropriété ? — **R :** Le plus souvent oui pour une unité extérieure visible. Nous préparons le dossier pour le syndic / l'assemblée générale et posons des groupes discrets et silencieux.
6. **Q :** Peut-on installer une clim dans le Vieux-Nice ? — **R :** Oui, avec une implantation non visible depuis la rue et une déclaration préalable en mairie. Le gainable ou une unité dissimulée répond aux exigences ABF.
7. **Q :** Quelles marques posez-vous ? — **R :** Mitsubishi Electric, Toshiba, Panasonic, Atlantic Fujitsu et Hisense. Le choix dépend du budget, du niveau sonore et de la configuration du logement.
8. **Q :** Combien de temps dure l'installation ? — **R :** Une demi-journée pour un mono-split, une journée pour un multi-split ou un gainable, selon l'accès à Nice.
9. **Q :** L'entretien est-il obligatoire ? — **R :** Oui, contrôle d'étanchéité tous les 2 ans par un professionnel avec attestation de capacité. Nous proposons des contrats annuels à Nice.
10. **Q :** Proposez-vous un devis gratuit ? — **R :** Oui, visite technique à domicile puis devis détaillé gratuit sous 24 à 48 h, avec le chiffrage des aides.
11. **Q :** La clim réversible chauffe-t-elle vraiment l'hiver à Nice ? — **R :** Oui, très efficacement sur le littoral : avec un SCOP de 4 à 5, elle restitue 4 à 5 kWh de chaleur pour 1 kWh consommé.
12. **Q :** Gérez-vous les démarches d'aides ? — **R :** Oui, nous montons les dossiers CEE et appliquons la TVA 5,5 %. MaPrimeRénov' est étudiée dans le cadre d'une rénovation d'ampleur.

### Posts GBP — 4 semaines
| Sem. | Type | Titre (≤ 58 car.) | CTA |
|---|---|---|---|
| 1 | Nouveauté | Installation climatisation à Nice — devis gratuit 24h | Obtenir un devis |
| 2 | Offre | Aides climatisation 2026 à Nice : CEE + TVA 5,5 % | En savoir plus |
| 3 | Nouveauté | Clim en copropriété à Nice : on gère le dossier syndic | Nous appeler |
| 4 | Nouveauté | Entretien climatisation Nice avant l'été : réservez | Réserver |

Tous les CTA pointent vers `https://climanova-energie.fr/demande-devis/`.

---

## 4. Moteur d'avis Google

- Générer le **lien court d'avis** (fiche GBP → « Demander des avis »).
- Routine : chaque fin de chantier → SMS d'avis sous 24 h. Objectif **2 à 4 avis/semaine**, réponse à **100 %** des avis sous 48 h.
- Ne **jamais** acheter d'avis, ni conditionner un avis à une remise, ni réintroduire `aggregateRating` / `review` en dur dans le JSON-LD du site (les notes viennent uniquement de la fiche GBP).

**SMS (≤ 160 car.)**
> Bonjour {Prénom}, merci d'avoir choisi ClimaNova Énergie pour votre climatisation à Nice. Votre avis Google nous aide beaucoup : {lien court} — Merci ! L'équipe ClimaNova

**E-mail — objet :** Votre avis compte pour nous — ClimaNova Énergie
> Bonjour {Prénom},
> Nous espérons que votre nouvelle climatisation vous apporte pleine satisfaction depuis notre intervention à {quartier}.
> Notre entreprise se développe surtout grâce au bouche-à-oreille : si vous avez 1 minute, un avis Google (accueil, devis, pose, propreté du chantier) aiderait d'autres habitants de Nice à nous trouver.
> 👉 {lien court avis Google}
> Merci pour votre confiance. L'équipe ClimaNova Énergie — +33 6 52 23 81 64

**Réponse à un avis 5★**
> Merci {Prénom} pour votre confiance ! Ravis que votre installation de climatisation à {quartier}, Nice, vous donne satisfaction. N'hésitez pas à nous solliciter pour l'entretien annuel ou un futur projet. — L'équipe ClimaNova Énergie

**Réponse à un avis 3–4★**
> Bonjour {Prénom}, merci pour ce retour. Nous notons les points à améliorer sur votre chantier à Nice et souhaitons les traiter avec vous : joignez-nous au +33 6 52 23 81 64. — ClimaNova Énergie

**Réponse à un avis 1–2★**
> Bonjour {Prénom}, nous sommes sincèrement désolés que notre prestation n'ait pas répondu à vos attentes ; ce n'est pas notre standard. Merci de nous contacter au +33 6 52 23 81 64 afin de trouver une solution concrète. — La direction, ClimaNova Énergie

---

## 5. Photos à fournir (remplacent le hero générique + « Réalisations »)

À prendre sur de vrais chantiers niçois, puis à géotagger (EXIF GPS = Nice) avant intégration :
- `climatisation-nice-installation-rge.webp` / `.jpg` — hero, 16:9, technicien RGE posant une unité murale, contexte niçois reconnaissable.
- 3 à 4 photos chantier : unité extérieure sur balcon (Cimiez / Riquier), multi-split séjour, groupe à traitement anticorrosion près du littoral, mise en service.
- Alt géolocalisés par quartier, ex. : « pose d'un groupe extérieur de climatisation à Cimiez, Nice, par ClimaNova Énergie ».
- Hero < 300 Ko, images < 150 Ko, format `<picture>` WebP + fallback JPG avec `width`/`height`.
