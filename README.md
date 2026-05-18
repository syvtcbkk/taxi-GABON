# taxi-GABON
# 🚕 Taxi Gabon — Guide d'installation

## Stack technique
- **Backend** : PHP 8.1+, PDO MySQL
- **Frontend** : Bootstrap 5, Font Awesome, Chart.js, Leaflet.js
- **Cartes / Routing** : OpenStreetMap + OSRM (100 % gratuit, pas de clé API requise)
- **E-mails** : PHPMailer (SMTP)

---

## 1. Cloner et installer les dépendances

```bash
git clone https://github.com/ton-user/taxi-gabon.git
cd taxi-gabon
composer require phpmailer/phpmailer
```

---

## 2. Base de données

```bash
mysql -u root -p < schema.sql
```

---

## 3. Configuration `.env`

Copiez `.env.example` en `.env` et remplissez vos valeurs :

```
DB_HOST=localhost
DB_NAME=taxi_gabon
DB_USER=root
DB_PASSWORD=votre_mdp

MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USER=votre-email@gmail.com
MAIL_PASS=votre-mot-de-passe-application   # Google App Password
MAIL_FROM=noreply@taxigabon.ga
MAIL_FROM_NAME=Taxi Gabon
```

> **Gmail** : activez la validation en 2 étapes puis générez un "Mot de passe d'application"
> dans Compte Google → Sécurité → Mots de passe des applications.

---

## 4. Structure des fichiers

```
taxi-gabon/
├── .env
├── schema.sql
├── index.php
├── login.php
├── register.php
├── verify-email.php          ← Lien de vérification reçu par e-mail
├── forgot-password.php       ← Saisie de l'e-mail pour le code
├── reset-password.php        ← Saisie du code + nouveau mot de passe
├── dashboard-driver.php      ← Dashboard chauffeur (graphiques + carte)
├── dashboard-passenger.php   ← Dashboard passager (réservation + suivi)
├── includes/
│   ├── db.php                ← Connexion PDO
│   ├── mailer.php            ← PHPMailer + templates e-mail
│   ├── header.php            ← (votre fichier existant)
│   └── footer.php            ← (votre fichier existant)
├── actions/
│   ├── auth-register.php     ← Inscription
│   ├── auth-login.php        ← Connexion
│   ├── auth-forgot.php       ← Envoi du code
│   └── auth-reset.php        ← Réinitialisation du mot de passe
└── api/
    ├── update-position.php   ← Chauffeur envoie sa position GPS
    ├── driver-position.php   ← Passager récupère la position du chauffeur
    ├── update-status.php     ← Disponible / Hors ligne
    ├── book-ride.php         ← Passager crée une course
    ├── respond-ride.php      ← Chauffeur accepte / refuse
    └── pending-rides.php     ← Liste des courses en attente (polling)
```

---

## 5. Flux d'authentification

### Inscription
1. Formulaire `register.php` → `actions/auth-register.php`
2. Mot de passe haché (bcrypt), token de vérification généré
3. E-mail envoyé avec lien → `verify-email.php?token=...`
4. Compte activé → redirection vers `login.php`

### Connexion
1. Formulaire `login.php` → `actions/auth-login.php`
2. Vérifie `is_verified = 1` sinon bloqué
3. Session créée, redirection selon le rôle

### Mot de passe oublié
1. `forgot-password.php` → `actions/auth-forgot.php`
2. Code à 6 chiffres généré, valable **15 minutes**, envoyé par e-mail
3. `reset-password.php` : saisie des 6 cases + nouveau mot de passe
4. `actions/auth-reset.php` valide et met à jour

---

## 6. Cartes & Géolocalisation

| Service | Usage | Coût |
|---------|-------|------|
| **OpenStreetMap** (Leaflet) | Affichage des cartes | Gratuit |
| **Nominatim** | Géocodage (adresse → coords) | Gratuit (1 req/s) |
| **OSRM** | Calcul d'itinéraire + ETA | Gratuit |

Pour la production, il est recommandé d'héberger votre propre instance OSRM
ou de passer à l'API Google Maps / Mapbox pour des limites plus élevées.

---

## 7. Calcul du prix

```
Prix (FCFA) = 500 (prise en charge) + distance_km × 400
```

Modifiez les constantes `TARIF_BASE` et `TARIF_KM` dans `dashboard-passenger.php`
selon votre grille tarifaire réelle.

---

## 8. Sécurité à renforcer en production

- [ ] Passer `.env` hors de la racine web
- [ ] Ajouter CSRF tokens sur tous les formulaires
- [ ] Rate-limiting sur `/actions/auth-*.php` et `/api/`
- [ ] HTTPS obligatoire (Let's Encrypt)
- [ ] Valider et sanitiser toutes les entrées côté serveur
- [ ] Remplacer le polling par des WebSockets (Ratchet, Soketi…) pour le temps réel
