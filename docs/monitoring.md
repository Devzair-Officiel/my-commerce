# Monitoring — Sentry + UptimeRobot

## Vue d'ensemble

| Outil | Surveille | Alerte si |
|-------|-----------|-----------|
| **Sentry** | Erreurs internes (500, exceptions) | Une exception non gérée se produit |
| **UptimeRobot** | Disponibilité du site | Le site ne répond plus |

Les deux sont complémentaires : UptimeRobot détecte que le serveur est mort, Sentry détecte les bugs dans le code.

---

## Sentry — Erreurs applicatives

### Configuration

Le bundle est installé (`sentry/sentry-symfony`) et configuré dans `config/packages/sentry.yaml`.

**Variables d'environnement** (à définir dans `.env.prod.local`) :

```env
SENTRY_DSN=https://xxxxx@oxxxxxx.ingest.de.sentry.io/xxxxxxx
SENTRY_TRACES_SAMPLE_RATE=0.1
```

- `SENTRY_DSN` → URL fournie par Sentry lors de la création du projet (disponible dans Sentry → Settings → Projects → Client Keys)
- `SENTRY_TRACES_SAMPLE_RATE` → pourcentage de requêtes tracées pour les performances. `0.1` = 10% (suffisant, évite de saturer le quota gratuit)

### Ce qui est ignoré (trop de bruit)

- Erreurs 404 (`NotFoundHttpException`)
- Accès refusés (`AccessDeniedException`)

### Tester l'intégration

```bash
# Déclencher une erreur test vers Sentry
php bin/console sentry:test
```

Tu devrais recevoir un email de Sentry dans les secondes qui suivent.

### Tableau de bord

Connecte-toi sur [sentry.io](https://sentry.io) pour voir :
- Toutes les erreurs en temps réel
- Stack traces complètes
- URL et navigateur de l'utilisateur concerné
- Fréquence des erreurs

---

## UptimeRobot — Disponibilité

### Créer un compte

1. Va sur [uptimerobot.com](https://uptimerobot.com)
2. Crée un compte gratuit
3. Vérifie ton email

### Moniteurs à créer

Dans UptimeRobot → **Add New Monitor** :

| Moniteur | Type | URL | Intervalle |
|----------|------|-----|------------|
| Site principal | HTTPS | `https://nidemiel.com` | 5 min |
| Page produit | HTTPS | `https://nidemiel.com/boutique` | 5 min |
| Sitemap (Symfony alive) | HTTPS | `https://nidemiel.com/sitemap.xml` | 5 min |

**Paramètres pour chaque moniteur :**
- Type : **HTTPS**
- Friendly Name : nom lisible (ex: "nidemiel.com — Site principal")
- URL : l'URL à surveiller
- Monitoring Interval : **5 minutes**
- Alert Contacts : ton adresse email

### Alertes

UptimeRobot envoie un email dès que :
- Le site ne répond plus (timeout)
- Le serveur retourne une erreur HTTP (5xx)
- Le certificat SSL expire dans moins de 7 jours

### Page de statut publique (optionnel)

UptimeRobot permet de créer une page de statut publique (ex: `status.nidemiel.com`) que tu peux partager avec tes clients en cas d'incident. Disponible dans l'onglet **Status Pages**.

---

## En cas d'alerte

### Sentry envoie une alerte

1. Ouvre l'email → clique sur le lien vers l'erreur
2. Lis la stack trace → identifie le fichier et la ligne
3. Reproduis l'erreur en local
4. Corrige et déploie

### UptimeRobot envoie une alerte

1. Essaie d'accéder au site manuellement
2. Connecte-toi au serveur via SSH
3. Vérifie les containers Docker :
   ```bash
   docker compose ps
   docker compose logs php --tail=50
   ```
4. Redémarre si nécessaire :
   ```bash
   docker compose restart php
   # ou en dernier recours
   docker compose down && docker compose up -d
   ```
