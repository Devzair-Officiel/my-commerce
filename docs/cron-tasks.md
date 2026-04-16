# Tâches cron — Configuration serveur

Ce document répertorie toutes les commandes Symfony à planifier sur le serveur en production.

---

## Commandes disponibles

### 1. `app:sync-shipment-tracking`

**Description :** Synchronise les statuts de suivi des colis Colissimo en cours d'acheminement.  
**Fréquence recommandée :** toutes les 15 minutes  
**Priorité prod :** haute

```cron
*/15 * * * * cd /chemin/vers/app && php bin/console app:sync-shipment-tracking --no-interaction -q >> /var/log/colissimo-sync.log 2>&1
```

---

### 2. `app:regenerate-image-variants`

**Description :** Régénère les variantes `-thumb` et `-medium` pour toutes les images uploadées.  
**Fréquence recommandée :** à la demande uniquement (commande one-shot)  
**Priorité prod :** basse — à lancer manuellement après import de produits en masse ou changement de format d'image

```bash
php bin/console app:regenerate-image-variants
```

> Cette commande n'a pas vocation à tourner en cron régulier.

---

### 3. Messenger worker — `messenger:consume`

**Description :** Consomme la queue de messages async (emails, notifications…) et la dead-letter queue `failed`.  
**Mode :** service long-running (pas un cron), géré par Docker ou Supervisor en prod.

```bash
php bin/console messenger:consume async failed --time-limit=3600 --memory-limit=128M -vv
```

---

## Configuration sur le serveur (production)

### Option A — crontab utilisateur

```bash
crontab -e
```

```cron
# Suivi Colissimo — toutes les 15 minutes
*/15 * * * * cd /var/www/my-commerce && php bin/console app:sync-shipment-tracking -q >> /var/log/colissimo-sync.log 2>&1
```

### Option B — fichier dans `/etc/cron.d/`

```bash
# /etc/cron.d/my-commerce
*/15 * * * * www-data cd /var/www/my-commerce && php bin/console app:sync-shipment-tracking -q >> /var/log/colissimo-sync.log 2>&1
```

### Option C — dans le container Docker (image de prod)

Ajouter dans le `Dockerfile` :

```dockerfile
RUN echo "*/15 * * * * root cd /app && php bin/console app:sync-shipment-tracking -q >> /proc/1/fd/1 2>&1" \
    > /etc/cron.d/my-commerce && chmod 0644 /etc/cron.d/my-commerce
```

---

## Vérification des logs

```bash
# Voir les dernières exécutions
tail -f /var/log/colissimo-sync.log

# Vérifier que le cron tourne
grep colissimo /var/log/syslog
```
