# Audit complet du code — 7 juillet 2026

Périmètre : sécurité, robustesse, performance, structure/qualité. ~21 500 lignes PHP, Symfony 8.0 / PHP 8.4, PostgreSQL 16, FrankenPHP.

Chaque point est noté ✅ corrigé dans cette passe, 📋 recommandé (non appliqué), ℹ️ information.

---

## 1. CRITIQUE — bugs métier

### C1. Réservations de stock jamais libérées ✅
`CheckoutController` réserve le stock (décrément immédiat) à chaque `GET /checkout`. Si le client abandonne, **le stock reste bloqué indéfiniment** : aucune commande cron/console ne libère les réservations expirées → rupture de stock fantôme.
**Fix appliqué** : commande `app:stock:release-expired-reservations` (libère les brouillons `stockReservedAt` > 30 min, verrou pessimiste), à planifier en cron (voir §7).

### C2. Remboursement admin : stock jamais restauré ✅
`RefundService::refundOrder()` passe la commande à `Rembourse` **avant** l'arrivée du webhook `charge.refunded`. Le webhook voit `wasAlreadyRefunded === true` et **ne restaure pas le stock**. Résultat : tout remboursement initié depuis l'admin laisse le stock décrémenté.
**Fix appliqué** : `RefundService` restaure le stock lui-même (via `StockAllocator::restoreStockForCancelledOrder` + passage fulfillment à `Annule`), le webhook reste le filet idempotent.

### C3. PATCH /api/order/{id} sans vérification de statut ✅
`ApiOrderController::update()` permettait de modifier adresses, transporteur **et total TTC** d'une commande **déjà payée ou expédiée** (n'importe laquelle de ses commandes). Fausse la comptabilité et peut bloquer la réconciliation webhook (mismatch de montant → 500 en boucle).
**Fix appliqué** : refus (409) si `paymentStatus !== Attente` ou `fulfillmentStatus !== Brouillon`.

### C4. Remboursement partiel Stripe traité comme total ✅
Stripe envoie `charge.refunded` aussi pour un remboursement **partiel**. Le webhook marquait la commande entière `Rembourse` + `Annule` + restaurait **tout** le stock.
**Fix appliqué** : comparaison `amount_refunded` vs total ; un remboursement partiel logge et notifie sans changer le statut ni le stock.

### C5. Suite de tests cassée + CI inopérante ✅
46 tests sur 72 en erreur : les tests référencent les anciens noms d'enum (`PaymentStatus::Paid/Pending/…`) renommés en français (`Paye/Attente/…`). La CI GitHub n'exécute **jamais** PHPUnit : toutes les étapes utiles sont désactivées (`if: false`, template Symfony jamais complété).
**Fix appliqué** : tests réparés. 📋 Réactiver PHPUnit dans `.github/workflows/ci.yaml` (nécessite un choix d'infra CI — étape fournie en commentaire).

---

## 2. SÉCURITÉ

### S1. Actions admin sensibles en GET sans CSRF ✅
`processRefund`, `shipOrder`, `sendRefundEmail` (OrderCrudController) sont des liens GET EasyAdmin sans token. Cookies en `SameSite=Lax` ⇒ un lien piégé ouvert par un admin connecté déclenche un **remboursement réel**.
**Fix appliqué** : token CSRF ajouté aux URLs des 3 actions et vérifié côté contrôleur.

### S2. Sanitisation HTML incomplète (XSS stocké) ✅
Seule la description de **catégorie** passe par le HtmlSanitizer. Les contenus produit (`description`, `more_description`, `additional_infos`, `wholesaleDescription`), blog (`content`, `description`) et page (`content`) sont rendus avec `|normalize_editor_html|raw` **sans aucune sanitisation** — le commentaire de `EditorHtmlNormalizer` prétendait le contraire. Le passage récent à `CodeEditorField` (HTML brut) accentue le risque : un compte admin compromis ⇒ XSS sur tous les clients.
**Fix appliqué** : sanitizer `app.editor_content_sanitizer` (balises éditoriales sûres, `max_input_length` 100k) appliqué dans le filtre Twig `normalize_editor_html` — couvre aussi le contenu existant en base.

### S3. Check `Origin` bypassable ✅
`ApiAddressController::validateCsrf()` : `str_starts_with($origin, $schemeAndHost)` ⇒ `https://site.com.evil.com` passe. Le token CSRF protégeait déjà, mais le check était inefficace.
**Fix appliqué** : comparaison stricte (`===` sur l'origine exacte).

### S4. Page succès paiement sans vérification du statut ✅
`/stripe/payment/success?payment_intent=…` affichait la page « succès », renseignait `paidAt` et vidait le panier **sans vérifier le statut réel** — URL forgeable avec un paiement échoué.
**Fix appliqué** : la page s'appuie sur `paymentStatus` (webhook = source de vérité) : Payé → succès ; Attente → page d'attente auto-refresh ; Échec → retour panier avec message.

### S5. Mutation par GET ✅
`GET /api/cart/update/carrier/{id}` modifie l'état de session (aucune protection CSRF possible en GET).
**Fix appliqué** : passage en POST (le front appelait déjà via fetch — appel mis à jour).

### S6. Divers 📋
- `X-XSS-Protection "1; mode=block"` est déprécié (à retirer, le header peut *introduire* des failles XS-Leaks sur vieux navigateurs) ; **CSP toujours absente** (commentée dans le Caddyfile) — à construire après inventaire des domaines tiers (Stripe, Colissimo, Google).
- `#[Cache(smaxage: 3600)]` sur la home : si un CDN/proxy cache est un jour placé devant, risque de servir une page avec état utilisateur. Inoffensif aujourd'hui (pas de proxy cache), à garder en tête.
- Clés Stripe **de test** stockées en base (`PaymentMethod.test_*_api_key`), lisibles en admin. Acceptable (test only), mais ne jamais y mettre des clés live.
- `StockAlertController` : pas de rate-limit sur subscribe (spam d'emails arbitraires possible) ; unsubscribe d'un email tiers possible. Impact faible — rate limiter recommandé.
- GoogleAuthenticator : vérifier `email_verified` de Google serait une défense supplémentaire (marginal).
- Caddyfile : uploads inoffensifs (seul `index.php` est exécuté, `file_server hide *.php`) ✅ par design.

---

## 3. PERFORMANCE

### P1. Une requête SQL par requête HTTP (utilisateurs connectés) ✅
`ClearCartAfterPaymentSubscriber` interroge la table `order` à **chaque** requête de chaque utilisateur connecté.
**Fix appliqué** : early-return si le panier de session est vide (cas ~100 % des requêtes), la requête SQL ne part plus que si un panier existe.

### P2. Index manquants ✅ (migration à exécuter au déploiement)
Vérifié sur la base réelle :
- `order.payment_reference` — **aucun index**, requêté par le webhook Stripe à chaque événement ;
- `product.slug` — **aucun index ni unicité**, requêté sur chaque page produit (deux produits peuvent partager un slug → page aléatoire).
**Fix appliqué** : migration `Version20260707000000` (index `order.payment_reference`, **unique** `product.slug`). ⚠️ Non exécutée (base de prod live) : vérifier l'absence de doublons de slug avant `doctrine:migrations:migrate` (requête fournie dans la migration).

### P3. `flush()` global à chaque lecture du panier ✅
`CartService::getCartDetails()` → `saveCart()` → `persistCartForCurrentUser()` → `em->flush()` **à chaque affichage** du panier, même sans changement + pollution de l'audit (`savedCart` non exclu → une ligne d'audit par modification de panier).
**Fix appliqué** : persistance uniquement si le contenu du panier a réellement changé ; `savedCart` exclu de l'audit.

### P4. `setMaxResults` + fetch-join de collection ✅
`ProductRepository::search()` joint `medias` **et** limite : Doctrine tronque en lignes SQL, pas en entités → l'autocomplete peut renvoyer 2 produits au lieu de 6, avec des galeries incomplètes.
**Fix appliqué** : requête en deux temps (ids limités puis chargement avec médias).

### P5. Pas de timeout HTTP sur Colissimo ✅
`ColissimoClient` sans `timeout`/`max_duration` : si l'API Colissimo rame, la page **checkout** attend (jusqu'au timeout socket par défaut, ~60 s).
**Fix appliqué** : `timeout: 5, max_duration: 10` sur les appels widget/tracking/point relais.

### P6. Divers 📋
- Home : `findFeaturedWithMedias('isAvailable')` charge **tous** les produits disponibles — OK pour un petit catalogue, à paginer si le catalogue grossit.
- `Sliders::findAll()` sans join médias (N+1 léger, volume faible).
- Audit : 1 INSERT par entité modifiée à chaque flush — volume OK aujourd'hui.

---

## 4. ROBUSTESSE / QUALITÉ

### R1. Soft-404 ✅
Produit inconnu → `render('page/not-fount.html.twig')` en **HTTP 200** (idem `/error`). Mauvais pour le SEO (soft-404) + typo « fount ».
**Fix appliqué** : vrai statut 404.

### R2. Code mort / incohérences ✅
- `CheckoutController` : condition morte sur le brouillon (déjà filtrée par `findOneBy`).
- `CartService::isStockSufficient()` : `(int) null = 0` → produit « stock non géré » considéré épuisé, incohérent avec `addToCart` (null = illimité). Corrigé.
- 8 erreurs PHPStan niveau 5 (PHPDoc/propriétés mortes) corrigées.

### R3. Édition libre des statuts en admin 📋
`OrderCrudController` PAGE_EDIT permet de passer n'importe quelle commande à n'importe quel statut **sans** passer par la logique stock/Stripe (aucun décrément/restauration/remboursement). Source d'incohérences garantie si utilisé. Recommandation : restreindre l'édit au `fulfillmentStatus` (retirer `paymentStatus`, piloté par Stripe uniquement) ou brancher une machine à états. **Décision métier à valider avant d'appliquer.**

### R4. AuditSubscriber : `pending` non purgé sur rollback 📋
Si un flush échoue après `onFlush`, les lignes d'audit collectées partent au flush suivant (faux positifs). Rare, surtout en worker long-running. Fix simple si besoin : purger dans un listener d'exception/rollback.

### R5. Structure ℹ️
- `SeoResolver` (816 l.), `Product` (701 l.), `Order` (600 l.) : volumineux mais cohérents ; découpage possible (SeoResolver → un builder par type de page) mais non urgent.
- Caddyfile : le bloc `mizan-commerce.com { reverse_proxy … }` (autre projet) vit dans le repo my-commerce — à extraire vers l'infra du serveur pour ne pas coupler les deux projets.
- Deux configs PHPUnit à la racine (`phpunit.dist.xml` + `phpunit.xml.dist`) — la seconde fait foi pour PHPUnit ≥ 9.3 ? Non : PHPUnit lit `phpunit.xml` puis `phpunit.xml.dist` puis `phpunit.dist.xml`. Supprimer le doublon obsolète.
- Tests : 8 fichiers bien ciblés (webhook, stock, cart) mais couverture globale faible. Priorité aux flux d'argent : checkout, refund, réservation.

---

## 5. Points solides (à conserver)

- Webhook Stripe : signature vérifiée, idempotence par contrainte unique DB (`stripe_webhook_event`), verrou pessimiste sur la commande, contrôle du montant reçu, remboursement auto si stock insuffisant. Très bon.
- Montants **en centimes entiers** partout, TVA sans flottants (`splitTtcIntoHtAndTax`).
- `ApiStripeController` : montant lu en base (jamais du client), idempotency key, réutilisation d'intent.
- Ownership systématique (`AccountController`, voter d'adresses), CSRF sur login et API adresses, login throttling, remember-me sécurisé.
- Uploads : allowlist MIME + 5 Mo, noms aléatoires, Caddy n'exécute que `index.php`.
- `StorefrontGlobalsProvider` : cache tagué proprement invalidé.
- Secrets hors git (`.env.prod.local` non suivi), Postgres bindé sur 127.0.0.1.

---

## 6. Erreurs PHPStan corrigées

| Fichier | Erreur |
|---|---|
| `CreateUserCommand:83` | `@var` incompatible avec le type natif |
| `ShipmentCrudController:52` | `$em` jamais lu |
| `AuditSubscriber:240` | type retour `?string` jamais null |
| `SendNewReviewNotificationMessageHandler:24` | `$em` jamais lu |
| `SeoResolver:272,276,610` | clés array-shape manquantes |
| `CartService:436` | `@param` orphelin |

## 7. Actions à faire au déploiement

1. `doctrine:migrations:migrate` (index + unicité slug) — vérifier d'abord :
   `SELECT slug, COUNT(*) FROM product GROUP BY slug HAVING COUNT(*) > 1;`
2. Planifier le cron de libération des réservations (toutes les 10 min) :
   `*/10 * * * * docker compose exec -T php bin/console app:stock:release-expired-reservations`
3. Réactiver PHPUnit dans la CI (retirer les `if: false` pertinents).
4. Décider du sort de l'édition des statuts en admin (R3).
