# Délivrabilité des emails — SPF, DKIM, DMARC

## Pourquoi c'est important ?

Quand ton site envoie un email (confirmation de commande, livraison, facture…),
les serveurs de messagerie de tes clients (Gmail, Outlook, Orange, Free…) vérifient
si cet email est légitime **avant** de le livrer en boîte de réception.

Sans configuration, tes emails risquent de :
- Atterrir directement dans les **spams**
- Être **rejetés** silencieusement sans jamais arriver
- Être marqués comme **phishing** (usurpation d'identité)

SPF, DKIM et DMARC sont trois enregistrements DNS qui prouvent aux serveurs de messagerie
que **c'est bien toi** qui envoies depuis `nidemiel.com`.

---

## 1. SPF — Sender Policy Framework

### C'est quoi ?
SPF déclare la liste des serveurs autorisés à envoyer des emails pour ton domaine.

> Analogie : c'est comme une liste d'invités à l'entrée d'une boîte de nuit.
> Si ton serveur n'est pas sur la liste, l'email est suspect.

### Comment ça marche ?
Quand Gmail reçoit un email de `contact@nidemiel.com`, il regarde dans le DNS de
`nidemiel.com` s'il existe un enregistrement SPF, et vérifie si le serveur expéditeur
est dans la liste autorisée.

### Configuration sur OVH

**OVH → Zone DNS → Ajouter un enregistrement → TXT**

| Champ | Valeur |
|-------|--------|
| Sous-domaine | *(laisser vide)* |
| TTL | 3600 |
| Valeur | `v=spf1 include:mx.ovh.com ~all` |

**Explication de la valeur :**
- `v=spf1` → version SPF
- `include:mx.ovh.com` → autorise les serveurs mail OVH
- `~all` → les autres serveurs sont suspects (soft fail — recommandé au départ)
- `~all` peut devenir `-all` une fois que tout est stabilisé (hard fail — rejet strict)

> ⚠️ Il ne peut y avoir **qu'un seul** enregistrement SPF par domaine.
> Si tu envoies aussi depuis un autre service (Mailchimp, SendGrid…), ajoute-le :
> `v=spf1 include:mx.ovh.com include:sendgrid.net ~all`

---

## 2. DKIM — DomainKeys Identified Mail

### C'est quoi ?
DKIM ajoute une **signature numérique** à chaque email envoyé.
Le destinataire peut vérifier que l'email n'a pas été modifié en transit
et qu'il vient bien de ton serveur.

> Analogie : c'est comme un cachet de cire sur une lettre.
> Si le cachet est intact et correspond à ta clé publique, la lettre est authentique.

### Comment ça marche ?
- Ton serveur mail **signe** chaque email avec une clé privée (secrète)
- Ta clé publique est publiée dans le DNS de `nidemiel.com`
- Le serveur du destinataire vérifie la signature avec la clé publique

### Configuration sur OVH

OVH peut gérer DKIM automatiquement :

**OVH → Hébergement → Emails → Sécurité → DKIM → Activer**

OVH crée automatiquement l'enregistrement DNS suivant :

```
ovhmo._domainkey.nidemiel.com  TXT  "v=DKIM1; k=rsa; p=MIGfMA0GCSq..."
```

> Si tu gères DKIM manuellement (ex: serveur externe), il faut :
> 1. Générer une paire de clés RSA (2048 bits minimum)
> 2. Configurer ton serveur mail avec la clé privée
> 3. Publier la clé publique dans le DNS

---

## 3. DMARC — Domain-based Message Authentication

### C'est quoi ?
DMARC indique aux serveurs de messagerie **quoi faire** quand SPF ou DKIM échoue :
laisser passer, mettre en quarantaine (spam) ou rejeter l'email.

C'est aussi un système de **rapports** : tu reçois des emails récapitulatifs
qui te montrent qui essaie d'envoyer des emails en usurpant ton domaine.

> Analogie : SPF et DKIM sont les contrôles de sécurité. DMARC est le règlement
> qui dit ce qu'on fait si quelqu'un échoue aux contrôles.

### Configuration sur OVH

**OVH → Zone DNS → Ajouter un enregistrement → TXT**

| Champ | Valeur |
|-------|--------|
| Sous-domaine | `_dmarc` |
| TTL | 3600 |
| Valeur | `v=DMARC1; p=none; rua=mailto:contact@nidemiel.com; pct=100` |

**Explication de la valeur :**
- `v=DMARC1` → version DMARC
- `p=none` → mode surveillance (aucune action, juste des rapports) — **commencer par là**
- `p=quarantine` → mettre en spam si échec (activer après quelques semaines)
- `p=reject` → rejeter si échec (activer quand tout est stable)
- `rua=mailto:contact@nidemiel.com` → envoyer les rapports à cette adresse
- `pct=100` → appliquer à 100% des emails

### Évolution recommandée

```
Semaine 1-2  →  p=none      (surveillance, tu reçois les rapports)
Semaine 3-4  →  p=quarantine (les faux emails vont en spam)
Mois 2+      →  p=reject     (les faux emails sont rejetés)
```

---

## 4. Récapitulatif des enregistrements DNS à créer

| Type | Sous-domaine | Valeur |
|------|-------------|--------|
| TXT | *(vide)* | `v=spf1 include:mx.ovh.com ~all` |
| TXT | `_dmarc` | `v=DMARC1; p=none; rua=mailto:contact@nidemiel.com; pct=100` |
| TXT | `ovhmo._domainkey` | *(généré automatiquement par OVH)* |

---

## 5. Configuration Symfony (production)

Dans `.env.prod.local` (ne jamais commiter ce fichier) :

```env
# SMTP OVH avec SSL port 465
MAILER_DSN=smtp://contact@nidemiel.com:TON_MOT_DE_PASSE@ssl0.ovh.net:465?encryption=ssl

# Ou avec STARTTLS port 587
MAILER_DSN=smtp://contact@nidemiel.com:TON_MOT_DE_PASSE@ssl0.ovh.net:587?encryption=tls
```

---

## 6. Vérification

### Tester la délivrabilité
1. Va sur [mail-tester.com](https://www.mail-tester.com)
2. Copie l'adresse email générée (ex: `test-abc123@mail-tester.com`)
3. Envoie un email depuis ton site vers cette adresse (via le formulaire de contact ou une commande test)
4. Clique "Vérifier le score"
5. Objectif : **score ≥ 9/10**

### Vérifier SPF
```bash
# Depuis un terminal
nslookup -type=TXT nidemiel.com
# Doit afficher : v=spf1 include:mx.ovh.com ~all
```

### Vérifier DKIM
```bash
nslookup -type=TXT ovhmo._domainkey.nidemiel.com
# Doit afficher la clé publique
```

### Vérifier DMARC
```bash
nslookup -type=TXT _dmarc.nidemiel.com
# Doit afficher : v=DMARC1; p=none; ...
```

---

## 7. Délai de propagation

Après ajout des enregistrements DNS, compte **24 à 48 heures** avant que
les changements soient visibles partout dans le monde (propagation DNS mondiale).

Tu peux vérifier la propagation en temps réel sur [dnschecker.org](https://dnschecker.org).
