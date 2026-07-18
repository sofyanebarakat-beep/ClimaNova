# Agent quotidien d'articles — ClimaNova Énergie

Ce dépôt contient un agent GitHub Actions qui crée **7 articles français chaque jour** : Climatisation, Chauffage, Électricien, Plombier, Rénovation énergétique, Entretien & dépannage, et ClimaNova Énergie. Tous ciblent Nice et la Côte d'Azur.

## Activation (une seule fois)

1. Pousser ces fichiers sur la branche `main`.
2. Aucun secret API externe n'est nécessaire : le workflow utilise le `GITHUB_TOKEN` temporaire fourni automatiquement par GitHub Actions.
3. Facultatif : dans **Settings → Secrets and variables → Actions → Variables**, créer `GITHUB_MODEL` pour remplacer le modèle GitHub Models par défaut `openai/gpt-4.1`.
4. Dans **Actions → Daily ClimaNova SEO articles**, lancer d'abord **Run workflow** avec `dry_run: true`.
5. Lancer ensuite sans dry run pour vérifier la première publication.

Le planning automatique s'exécute à **07:15, heure de Nice (`Europe/Paris`)**, été comme hiver. GitHub peut parfois démarrer un workflow planifié avec quelques minutes de retard.

## Fonctionnement

- `scripts/daily-article-config.json` définit la marque, la zone et les 7 axes éditoriaux.
- `scripts/daily-article-history.json` empêche de produire deux fois le même axe le même jour et fournit au modèle les sujets récents à éviter.
- `scripts/generate-daily-articles.mjs` appelle l'API GitHub Models avec le jeton éphémère du workflow et un schéma JSON strict, crée les pages, ajoute les cartes au blog et met à jour le sitemap.
- `scripts/validate-daily-articles.mjs` bloque la publication si un article n'a pas la structure SEO minimale.
- `.github/workflows/daily-seo-articles.yml` orchestre la génération, la validation, le commit et le push.

## Test local sans coût

```bash
node scripts/generate-daily-articles.mjs --dry-run
node scripts/validate-daily-articles.mjs
```

## Coût et cadence

Sept articles longs par jour représentent environ 210 articles par mois et peuvent atteindre les quotas ou limites de débit GitHub Models. Commencer par plusieurs exécutions manuelles et contrôler la qualité dans Google Search Console est recommandé. Pour réduire le rythme, diminuer `articles_per_run` dans `scripts/daily-article-config.json` ou modifier le cron.

## Sécurité éditoriale

L'agent refuse d'écraser un dossier d'article existant, conserve un historique, demande des sources publiques pour les informations évolutives et interdit les faux avis, certifications, prix garantis ou classements. Une relecture humaine reste conseillée pour les aides financières, normes, obligations et tarifs.
