# Guide SEO — Règles & Checklist pour tout projet web

> Document de référence applicable à tout projet e-commerce ou site vitrine.
> Rédigé à partir des bonnes pratiques Google, des Core Web Vitals et de l'expérience terrain.

---

## Sommaire

1. [Les fondamentaux — ce que Google évalue](#1-les-fondamentaux)
2. [Architecture technique](#2-architecture-technique)
3. [Balises HTML & métadonnées](#3-balises-html--métadonnées)
4. [Performance & Core Web Vitals](#4-performance--core-web-vitals)
5. [Images](#5-images)
6. [Contenu & mots-clés](#6-contenu--mots-clés)
7. [Données structurées JSON-LD](#7-données-structurées-json-ld)
8. [Maillage interne](#8-maillage-interne)
9. [Sitemap & robots.txt](#9-sitemap--robotstxt)
10. [Suivi & outils](#10-suivi--outils)
11. [Checklist par type de page](#11-checklist-par-type-de-page)
12. [Règles à ne jamais enfreindre](#12-règles-à-ne-jamais-enfreindre)

---

## 1. Les fondamentaux

Google classe les pages selon **3 piliers** :

```
┌─────────────────────────────────────────────────────┐
│                   RANKING GOOGLE                    │
│                                                     │
│   TECHNIQUE          CONTENU         AUTORITÉ       │
│   ─────────          ───────         ─────────      │
│   Vitesse            Pertinence      Backlinks       │
│   Crawlabilité       Qualité         Réputation      │
│   Données struct.    Mots-clés       Mentions        │
│   Mobile-first       Fraîcheur       Partages        │
│                                                     │
│   Sans technique → pages non indexées               │
│   Sans contenu  → pages sans trafic                 │
│   Sans autorité → pages en page 5+                  │
└─────────────────────────────────────────────────────┘
```

**Règle d'or** : un bon SEO, c'est une page utile, rapide, et bien décrite.

---

## 2. Architecture technique

### Structure des URLs

```
✅ BON                          ❌ MAUVAIS
/produits/miel-de-thym          /product?id=42
/category/miels-naturels        /cat/3
/blog/bienfaits-miel-thym       /blog?post=12&lang=fr
```

**Règles :**
- URLs en minuscules, avec tirets (pas underscores)
- Pas de paramètres visibles si possible
- Maximum 3 niveaux de profondeur (`/categorie/sous-cat/produit`)
- Un seul slug par ressource → pas de duplicates

### Canonical

Toujours définir `<link rel="canonical">` pour éviter le contenu dupliqué :

```html
<!-- Sur la fiche produit /produits/miel-thym -->
<link rel="canonical" href="https://votresite.fr/produits/miel-thym">
```

**Cas où le canonical est critique :**
- Pages de résultats de recherche → canonical = URL sans paramètre
- Produits accessibles depuis plusieurs catégories
- Versions HTTP et HTTPS
- Avec et sans `/` final

### Pages à mettre en `noindex`

```
noindex,follow  → pages de recherche, filtres, panier, compte, login
noindex,nofollow → admin, API, pages techniques
index,follow    → toutes les pages de contenu
```

---

## 3. Balises HTML & métadonnées

### Hiérarchie obligatoire

```html
<head>
  <!-- 1. Charset et viewport EN PREMIER -->
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <!-- 2. Title — le plus important -->
  <title>Mot-clé Principal | Nom du Site</title>

  <!-- 3. Meta description -->
  <meta name="description" content="...">

  <!-- 4. Canonical -->
  <link rel="canonical" href="https://...">

  <!-- 5. Robots -->
  <meta name="robots" content="index,follow">

  <!-- 6. Open Graph (réseaux sociaux) -->
  <meta property="og:title" content="...">
  <meta property="og:description" content="...">
  <meta property="og:image" content="https://...">
  <meta property="og:url" content="https://...">
  <meta property="og:type" content="product">

  <!-- 7. JSON-LD (données structurées) -->
  <script type="application/ld+json">{ ... }</script>
</head>
```

### Règles pour le `<title>`

```
Format :   Mot-clé Principal — Contexte | Marque
Longueur : 50-60 caractères maximum
Exemples :
  ✅ Miel de Thym Naturel | Nidemiel
  ✅ Acheter du Miel de Jujubier en France | Nidemiel
  ❌ Produit n°42
  ❌ Nidemiel - Miel de thym naturel pur artisanal bio premium qualité (trop long)
```

### Règles pour la `<meta description>`

```
Longueur : 150-160 caractères
Contient : le mot-clé principal + un appel à l'action
Exemples :
  ✅ Miel de thym naturel récolté à la main. 100% pur, sans additifs.
     Livraison rapide en France. Commandez sur Nidemiel.
  ❌ Découvrez nos produits. (trop vague)
  ❌ (absent) → Google génère automatiquement, souvent mal
```

### Balises Hn (titres)

```html
<!-- Une seule H1 par page, contient le mot-clé principal -->
<h1>Miel de Thym Naturel</h1>

<!-- H2 pour les sections principales -->
<h2>Bienfaits du miel de thym</h2>
<h2>Comment l'utiliser ?</h2>

<!-- H3 pour les sous-sections -->
<h3>Propriétés antibactériennes</h3>
```

**Règles :**
- **1 seul H1** par page
- Le H1 doit contenir le mot-clé cible
- Ne pas sauter de niveau (pas de H1 → H3)
- Ne pas utiliser les Hn pour le style visuel

---

## 4. Performance & Core Web Vitals

Google mesure 3 métriques clés :

```
┌──────────────────────────────────────────────────┐
│  LCP  — Largest Contentful Paint                 │
│  Temps avant que l'image/texte principal charge  │
│  Objectif : < 2.5 secondes                       │
├──────────────────────────────────────────────────┤
│  FID / INP — Interaction to Next Paint           │
│  Réactivité aux clics                            │
│  Objectif : < 200ms                              │
├──────────────────────────────────────────────────┤
│  CLS — Cumulative Layout Shift                   │
│  Stabilité visuelle (pas de sauts de mise en     │
│  page au chargement)                             │
│  Objectif : < 0.1                                │
└──────────────────────────────────────────────────┘
```

### Checklist performance

- [ ] Minifier CSS et JS en production
- [ ] Activer gzip/brotli sur le serveur
- [ ] Mettre en cache les assets statiques (1 an)
- [ ] Charger les scripts en `defer` ou `type="module"`
- [ ] Ne charger que les scripts nécessaires par page
- [ ] Supprimer le CSS/JS inutilisé

### Éviter le CLS

Toujours définir `width` et `height` sur les images :

```html
<!-- Évite le Layout Shift -->
<img src="miel.webp" width="600" height="600" alt="Miel de thym">
```

---

## 5. Images

### Format recommandé

```
WebP   → format idéal, 30-50% plus léger que JPG, supporté par tous les navigateurs
AVIF   → encore plus léger, support progressif
JPG    → fallback acceptable
PNG    → uniquement pour les logos et images avec transparence
```

### Taille à l'upload

```
Contexte              Résolution recommandée
────────────────────────────────────────────
Listing / card        600×600 px minimum
Page produit          1200×1200 px minimum (ratio carré)
Hero / bannière       1920×600 px
Blog thumbnail        800×450 px (ratio 16:9)
```

### Variantes à générer automatiquement

```
Original   → zoom, download
-medium    → 800×800 max, ratio conservé (page produit)
-thumb     → 600×600 cover (listing, cards)
```

### Règles pour les attributs

```html
<!-- Image principale d'une page → charger en priorité -->
<img
  src="miel-thym-medium.webp"
  alt="Miel de thym naturel de qualité supérieure"
  width="800" height="800"
  fetchpriority="high"
  decoding="async"
>

<!-- Images secondaires → charger en différé -->
<img
  src="miel-thym-thumb.webp"
  alt="Miel de thym — vue de côté"
  width="600" height="600"
  loading="lazy"
  decoding="async"
>
```

### Règles pour l'attribut `alt`

```
✅ alt="Miel de thym naturel artisanal — Nidemiel"
✅ alt="Pot de 500g de miel de jujubier"
❌ alt="image001.jpg"
❌ alt=""  (sauf si image décorative)
❌ alt="miel miel miel thym thym bio naturel"  (keyword stuffing)
```

---

## 6. Contenu & mots-clés

### Stratégie de mots-clés

```
Mots-clés principaux (fort volume, forte concurrence)
  → "acheter miel naturel", "miel en ligne France"
  → Ciblez avec la page d'accueil et les catégories

Mots-clés longue traîne (volume modéré, faible concurrence)
  → "miel de thym naturel livraison France"
  → "miel de jujubier bienfaits"
  → "où acheter vrai miel de thym"
  → Ciblez avec les fiches produit et les articles de blog

Mots-clés questions (featured snippets)
  → "qu'est-ce que le miel de jujubier"
  → "comment reconnaître un miel pur"
  → Ciblez avec des articles de blog en FAQ
```

### Règles pour les fiches produit

**Longueur minimale :** 300 mots dans la description.

**Structure recommandée :**

```
1. Introduction (50 mots)
   → Présenter le produit avec le mot-clé principal

2. Origine & récolte (100 mots)
   → D'où vient-il ? Comment est-il récolté ?
   → Différenciateur concurrentiel

3. Goût & utilisation (100 mots)
   → Comment l'utiliser ? Avec quoi ?
   → Niche + mots-clés secondaires

4. Bienfaits (100 mots)
   → Propriétés naturelles
   → Mots-clés "bienfaits du miel de thym"

5. Informations pratiques
   → Poids, conservation, origine
```

### Règles pour les articles de blog

**Longueur minimale :** 800 mots pour un article standard, 1500+ pour un article pilier.

**Structure recommandée :**

```
Titre H1 : contient le mot-clé principal
├── Introduction : pose la problématique
├── H2 : première section
│   └── H3 : sous-section si nécessaire
├── H2 : deuxième section
├── H2 : FAQ (questions fréquentes)
│   ├── H3 : Question 1 ?
│   └── H3 : Question 2 ?
└── Conclusion : appel à l'action + lien interne
```

**Dans chaque article :**
- 1 à 2 liens vers des fiches produit liées
- 1 lien vers un autre article de blog
- Des images avec `alt` descriptifs
- Un appel à l'action ("Découvrez notre miel de thym →")

---

## 7. Données structurées JSON-LD

### Types à implémenter selon le contexte

```
Page d'accueil      → Organization + WebSite + SearchAction
Fiche produit       → Product + Offer + BreadcrumbList
Catégorie           → CollectionPage + BreadcrumbList
Article de blog     → Article + BreadcrumbList
Page contact        → LocalBusiness (si boutique physique)
FAQ                 → FAQPage
```

### Schéma Product complet

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Miel de Thym Naturel",
  "description": "Miel de thym 100% naturel, récolté à la main...",
  "image": ["https://nidemiel.fr/images/miel-thym.webp"],
  "brand": { "@type": "Brand", "name": "Nidemiel" },
  "offers": {
    "@type": "Offer",
    "priceCurrency": "EUR",
    "price": "12.90",
    "availability": "https://schema.org/InStock",
    "itemCondition": "https://schema.org/NewCondition"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "47"
  }
}
```

### Schéma Organization (page d'accueil)

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Nidemiel",
  "url": "https://nidemiel.fr",
  "logo": "https://nidemiel.fr/logo.png",
  "contactPoint": {
    "@type": "ContactPoint",
    "contactType": "customer service",
    "availableLanguage": "French"
  },
  "sameAs": [
    "https://www.instagram.com/nidemiel",
    "https://www.facebook.com/nidemiel"
  ]
}
```

---

## 8. Maillage interne

```
Règle principale : chaque page importante doit être accessible
en 3 clics maximum depuis la page d'accueil.

┌─────────────────────────────────────────────────────┐
│  Accueil                                            │
│    ├── Catégorie "Miels naturels"                   │
│    │     ├── Fiche "Miel de thym"    ← prioritaire  │
│    │     └── Fiche "Miel de jujubier"               │
│    └── Blog                                         │
│          └── "Bienfaits du miel de thym"            │
│                └── lien → Fiche "Miel de thym"      │
└─────────────────────────────────────────────────────┘
```

**Règles :**
- Les textes de liens (ancres) doivent être descriptifs : `Découvrez le miel de thym` et non `cliquez ici`
- Les pages importantes (produits phares) reçoivent plus de liens internes
- Ne pas créer de "pages orphelines" (inaccessibles depuis le menu ou un autre lien)
- Relier les articles de blog aux fiches produit correspondantes

---

## 9. Sitemap & robots.txt

### Sitemap XML

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url>
    <loc>https://nidemiel.fr/produits/miel-de-thym</loc>
    <lastmod>2024-01-15</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
  </url>
</urlset>
```

**Priorités recommandées :**

```
1.0  → Page d'accueil
0.9  → Fiches produit
0.8  → Pages catégorie, Blog index
0.7  → Articles de blog
0.5  → Pages statiques (contact, CGV)
```

**À exclure du sitemap :**
- Pages `noindex`
- Pages de compte utilisateur
- Panier, checkout
- Pages d'administration

### robots.txt

```
User-agent: *
Allow: /

Disallow: /admin
Disallow: /account
Disallow: /cart
Disallow: /checkout
Disallow: /login
Disallow: /register
Disallow: /api/
Disallow: /product/search

Sitemap: https://nidemiel.fr/sitemap.xml
```

---

## 10. Suivi & outils

### Outils gratuits indispensables

```
Google Search Console  → indexation, erreurs, clics, positions
  → https://search.google.com/search-console

Google PageSpeed       → score performance, Core Web Vitals
  → https://pagespeed.web.dev

Google Rich Results    → valider les données structurées
  → https://search.google.com/test/rich-results

Bing Webmaster Tools   → 10-15% du trafic recherche
  → https://www.bing.com/webmasters
```

### Actions après mise en ligne

```
Jour 1    Soumettre sitemap.xml dans Google Search Console
Semaine 1 Demander l'indexation des pages prioritaires (produits)
Mois 1    Analyser les premiers clics et positions
Mois 3    Ajuster les descriptions des pages en positions 5-15
En continu Publier des articles de blog (1 par mois minimum)
```

---

## 11. Checklist par type de page

### ✅ Page d'accueil

- [ ] H1 avec mot-clé principal de la marque
- [ ] Meta title 50-60 chars
- [ ] Meta description 150-160 chars avec appel à l'action
- [ ] Schema.org `Organization` + `WebSite`
- [ ] Image hero avec `fetchpriority="high"` + alt
- [ ] Liens vers les catégories principales
- [ ] Canonical = URL racine

### ✅ Fiche produit

- [ ] H1 = nom du produit + variante
- [ ] Meta title = `[Produit] | [Marque]`
- [ ] Meta description avec prix / livraison
- [ ] Description 300+ mots avec mots-clés naturellement intégrés
- [ ] Schema.org `Product` + `Offer`
- [ ] Images : `alt` descriptifs, `width/height`, image principale en `fetchpriority="high"`
- [ ] Fil d'Ariane visible + Schema.org `BreadcrumbList`
- [ ] Produits similaires en maillage interne
- [ ] Canonical unique

### ✅ Page catégorie

- [ ] H1 = nom de la catégorie
- [ ] Texte introductif 100+ mots en haut ou bas de page
- [ ] Meta title + description
- [ ] Schema.org `CollectionPage` + `BreadcrumbList`
- [ ] Images produit en `loading="lazy"`
- [ ] Pagination avec `rel="next"` / `rel="prev"` si applicable

### ✅ Article de blog

- [ ] H1 avec le mot-clé de la question / requête cible
- [ ] Introduction avec le mot-clé dans les 100 premiers mots
- [ ] Minimum 800 mots
- [ ] Schema.org `Article` avec `datePublished` et `dateModified`
- [ ] Images avec `alt` descriptifs
- [ ] Liens internes vers produits liés
- [ ] Meta title + description
- [ ] Canonical

---

## 12. Règles à ne jamais enfreindre

```
❌  Keyword stuffing
    Répéter 10 fois le même mot-clé dans un texte.
    Pénalité Google garantie.

❌  Contenu dupliqué
    La même page accessible via plusieurs URLs sans canonical.
    Google choisit laquelle indexer (souvent la mauvaise).

❌  Balises title ou description dupliquées
    Chaque page doit avoir son propre title et sa description.

❌  Liens achetés
    Les "backlinks payants" ou les fermes de liens.
    Pénalité manuelle possible.

❌  Texte caché
    Texte blanc sur fond blanc, font-size 0.
    Pénalité immédiate.

❌  Cloaking
    Afficher un contenu différent à Google et aux utilisateurs.
    Pénalité immédiate.

❌  Pages lentes
    LCP > 4s = chute de classement garantie.
    Optimiser les images est la première action.

❌  Site non mobile
    Google indexe en mobile-first depuis 2019.
    Un site non responsive = pénalité de classement.
```

---

*Document créé en avril 2026 — mis à jour selon les évolutions des guidelines Google.*
