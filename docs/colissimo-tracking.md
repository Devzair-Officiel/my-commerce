# Suivi Colissimo — Documentation technique

## Vue d'ensemble

Le système de suivi des colis Colissimo repose sur quatre couches :

1. **`ColissimoClient::getTracking()`** — appel HTTP à l'API Colissimo
2. **`ColissimoTrackingService`** — synchronisation et persistance en BDD
3. **`SyncShipmentTrackingCommand`** — commande CLI à planifier en cron
4. **Page client + admin EasyAdmin** — visualisation du suivi

---

## Architecture des données

```
Shipment
├── trackingNumber       string       Numéro de colis Colissimo (ex: 6X123456789FR)
├── statusCode           string|null  Dernier code statut (ex: LIVCFM)
├── syncedAt             datetime     Dernière synchronisation avec l'API
├── shippedAt            datetime     Date d'expédition (null = pas encore expédié)
├── deliveredAt          datetime     Date de livraison finale (null = en cours)
└── shipmentStatuses     Collection   Historique des événements

ShipmentStatus
├── statusCode    string        Code brut Colissimo (ex: PCHCFM)
├── label         string        Libellé en français
├── occuredAt     datetime      Date/heure de l'événement
└── rawData       json          Réponse brute de l'API (inclut le champ "site")
```

### Codes statut Colissimo courants

| Code       | Signification                    | Badge admin  | Déclenche `deliveredAt` |
|------------|----------------------------------|--------------|------------------------|
| `PCHCFM`   | Prise en charge confirmée        | gris         |                        |
| `ENCOURS`  | En cours d'acheminement          | bleu clair   |                        |
| `INTRANS`  | En transit                       | bleu clair   |                        |
| `TRANSIT`  | En transit                       | bleu clair   |                        |
| `DISTRI`   | En cours de distribution         | bleu         |                        |
| `OUTDELIV` | En cours de livraison            | bleu         |                        |
| `LIVCFM`   | Livré — confirmation client      | vert         | ✓                      |
| `LIVGAR`   | Livré en gardiennage             | vert         | ✓                      |
| `LIVDOM`   | Livré à domicile                 | vert         | ✓                      |
| `AVISAGE`  | Avis de passage déposé           | orange       |                        |
| `RETOUR`   | Retour à l'expéditeur            | rouge        |                        |
| `ANOMAL`   | Anomalie de traitement           | rouge        |                        |
| `RETN`     | Retourné                         | rouge        |                        |

---

## Fichiers concernés

| Fichier | Rôle |
|---|---|
| `src/Service/Carrier/ColissimoClient.php` | Client HTTP Colissimo — `generateLabel`, `generateLabelPickupPoint`, `getWidgetToken`, **`getTracking`** |
| `src/Service/Carrier/ColissimoTrackingService.php` | Synchronise un `Shipment` : appelle `getTracking`, déduplique les événements, met à jour les statuts |
| `src/Command/SyncShipmentTrackingCommand.php` | Commande CLI `app:sync-shipment-tracking` |
| `src/Repository/ShipmentRepository.php` | `findActiveShipments()` — colis expédiés non livrés |
| `src/Entity/Shipment.php` | Entité colis avec suivi |
| `src/Entity/ShipmentStatus.php` | Entité événement de suivi |
| `src/Controller/AccountController.php` | Route `app_account_order_tracking` — page suivi client |
| `src/Controller/Admin/ShipmentCrudController.php` | Badges statut + action "Sync suivi" dans EasyAdmin |
| `templates/account/order_tracking.html.twig` | Page suivi client : barre de progression + timeline |

---

## Utilisation

### Page suivi client

Accessible depuis la page de détail commande via le bouton **"Suivre mon colis"**.

```
/account/order/{orderId}/tracking/{shipmentId}
```

La page affiche :
- Un en-tête avec le numéro de suivi, les dates d'expédition et de livraison
- Le point relais si applicable
- Une **barre de progression** 4 étapes (pris en charge → transit → en livraison → livré)
- Une **timeline chronologique** de tous les événements `ShipmentStatus`

### Synchronisation depuis l'admin EasyAdmin

Dans la liste ou la fiche d'une expédition, le bouton **"Sync suivi"** déclenche une synchronisation immédiate avec l'API Colissimo. Il est visible uniquement si le colis a un numéro de suivi et n'est pas encore marqué livré.

### Lancer la synchronisation en CLI

```bash
# Tous les colis actifs (expédiés, non livrés)
php bin/console app:sync-shipment-tracking

# Un seul colis (debug / relance manuelle)
php bin/console app:sync-shipment-tracking -t 6X123456789FR

# Mode verbeux
php bin/console app:sync-shipment-tracking -v
```

### Planification cron (recommandé : toutes les heures)

```cron
0 * * * * /usr/bin/php /var/www/html/bin/console app:sync-shipment-tracking >> /var/log/colissimo-tracking.log 2>&1
```

> En production, préférer **Symfony Scheduler** ou **Messenger** pour éviter les problèmes de lock et bénéficier du monitoring intégré.

---

## API Colissimo utilisée

### Endpoint tracking

```
GET https://ws.colissimo.fr/tracking-cxf/rest/v2/consolidated/search
    ?login=XXXXX
    &password=XXXXX
    &skybillNumber=6X123456789FR
    &lang=FR
```

**Réponse (structure simplifiée) :**

```json
{
  "errorCode": "000",
  "shipment": {
    "statusCode": "LIVCFM",
    "status": "Livré",
    "eventDetailList": [
      {
        "code": "PCHCFM",
        "label": "Pris en charge",
        "date": "15/04/2026 09:12:00",
        "site": "Paris 15"
      }
    ]
  }
}
```

**Code d'erreur `000`** = succès. Tout autre code lève une `RuntimeException` dans `ColissimoClient`.

### Credentials

Configurés dans `config/services.yaml` via les paramètres `colissimo.login` et `colissimo.password`  
(variables d'environnement `COLISSIMO_LOGIN` / `COLISSIMO_PASSWORD`).

---

## Déduplication des événements

Chaque événement est identifié par la clé composite `statusCode|date` (format `Y-m-d H:i`).  
Si la clé existe déjà en BDD, l'événement est ignoré. La commande peut donc tourner aussi souvent que nécessaire sans créer de doublons.

---

## Gestion des erreurs

- Si l'API est inaccessible ou retourne une erreur, `ColissimoTrackingService` log un warning, enregistre le message dans `Shipment::errorMessage` et met à jour `syncedAt`.
- La commande CLI termine avec `Command::FAILURE` si au moins un colis a échoué — utile pour les alertes cron.
- En admin, un flash `danger` s'affiche si la synchro échoue.

---

## Prochaines étapes

- [ ] Notification email au client lors d'un changement de statut clé (`DISTRI` → "votre colis est en cours de livraison", `LIVCFM` → "votre colis a été livré")
- [ ] Intégration Symfony Scheduler pour remplacer le cron
