# Agent quotidien d'articles — ClimaNova Énergie

Ce dépôt contient un agent GitHub Actions qui crée **7 articles français chaque jour** : Climatisation, Chauffage, Électricien, Plombier, Rénovation énergétique, Entretien & dépannage, et ClimaNova Énergie. Tous ciblent Nice et la Côte d'Azur.

## Activation (une seule fois)

1. Pousser ces fichiers sur la branche `main`.
2. Dans GitHub, ouvrir **Settings → Secrets and variables → Actions**.
3. Créer le secret de dépôt `OPENAI_API_KEY` avec une clé API OpenAI. Ne jamais placer la clé dans un fichier du dépôt.
4. Facultatif : créer la variable `OPENAI_MODEL` pour remplacer le modèle par défaut `gpt-5-mini`.
5. Dans **Actions → Daily ClimaNova SEO articles**, lancer d'abord **Run workflow** avec `dry_run: true`.
6. Lancer ensuite sans dry run pour vérifier la première publication.

Le planning automatique s'exécute à **07:15, heure de Nice (`Europe/Paris`)**, été comme hiver. GitHub peut parfois démarrer un workflow planifié avec quelques minutes de retard.

## Fonctionnement

- `scripts/daily-article-config.json` définit la marque, la zone et les 7 axes éditoriaux.
- `scripts/daily-article-history.json` empêche de produire deux fois le même axe le même jour et fournit au modèle les sujets récents à éviter.
- `scripts/generate-daily-articles.mjs` appelle l'API Responses avec un schéma JSON strict, crée les pages, ajoute les cartes au blog et met à jour le sitemap.
- `scripts/validate-daily-articles.mjs` bloque la publication si un article n'a pas la structure SEO minimale.
- `.github/workflows/daily-seo-articles.yml` orchestre la génération, la validation, le commit et le push.

## Test local sans coût

```bash
node scripts/generate-daily-articles.mjs --dry-run
node scripts/validate-daily-articles.mjs
```

## Coût et cadence

Sept articles longs par jour représentent environ 210 articles par mois et peuvent consommer un budget API important. Commencer par plusieurs exécutions manuelles et contrôler la qualité dans Google Search Console est recommandé. Pour réduire le rythme, diminuer `articles_per_run` dans `scripts/daily-article-config.json` ou modifier le cron.

## Sécurité éditoriale

L'agent refuse d'écraser un dossier d'article existant, conserve un historique, demande des sources publiques pour les informations évolutives et interdit les faux avis, certifications, prix garantis ou classements. Une relecture humaine reste conseillée pour les aides financières, normes, obligations et tarifs.
