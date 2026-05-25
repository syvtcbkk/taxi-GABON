# 🚀 GUIDE CONFIGURATION RAPIDE

## 1️⃣ Configuration Minimale (5 min)

### Étape 1: Copier .env.example
```bash
cp .env.example .env
```

### Étape 2: Éditer .env
Ouvrir `.env` et remplacer:
```env
# Développement local
DEV_MODE=false          # Mettre à true UNIQUEMENT pour localhost

# Stripe (obtenir clés depuis https://dashboard.stripe.com)
STRIPE_SECRET=sk_test_your_test_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# Email (Gmail exemple)
MAIL_USER=votre-email@gmail.com
MAIL_PASS=votre-app-password
```

### Étape 3: Vérifier l'installation
```bash
# Les fichiers doivent exister
ls includes/auth.php          # ✓ Nouveau
ls includes/validators.php    # ✓ Nouveau
ls SECURITY_IMPROVEMENTS.md   # ✓ Nouveau
```

---

## 2️⃣ Test Rapide (2 min)

### Via navigateur:
```
1. Aller à: http://localhost/register.php
2. Essayer s'inscrire avec:
   - Email invalide: "invalid" → Erreur "invalide"
   - Téléphone invalide: "123" → Erreur "invalide"
   - Mot de passe court: "pass" → Erreur "au moins 8"
3. S'inscrire correctement → Code de vérification
```

### Via API (cURL):
```bash
# Test 1: Sans authentification
curl -X POST http://localhost/api/book-ride.php
# Résultat: {"success":false,"error":"Non authentifié"}

# Test 2: Données invalides
curl -X POST http://localhost/api/book-ride.php \
  -H "Content-Type: application/json" \
  -d '{"origin_lat": 91}'
# Résultat: {"success":false,"error":"invalide"}

# Test 3: Rate limiting (faire 6+ requêtes rapides)
for i in {1..7}; do curl -X POST http://localhost/api/book-ride.php; done
# 7ème requête retourne: 429 Too Many Requests
```

---

## 3️⃣ Déploiement Production (30 min)

### ✅ Checklist avant déploiement:

```
SÉCURITÉ:
  [ ] DEV_MODE=false dans .env
  [ ] STRIPE_SECRET = clé PRODUCTION (sk_live_...)
  [ ] MAIL_PASS = mot de passe sécurisé
  [ ] .env dans .gitignore (ne pas commiter)
  [ ] HTTPS activé sur le serveur
  [ ] Headers de sécurité configurés (CORS, CSP)

DATABASE:
  [ ] Backups en place
  [ ] Indexes créés (users email, phone, rides status)
  [ ] Permissions DB restrictives (read-only pour web app sauf INSERT/UPDATE)

TESTS:
  [ ] Inscription avec validation email/phone
  [ ] Login avec 2 comptes (passager + driver)
  [ ] Book ride API fonctionne
  [ ] Respond ride API fonctionne
  [ ] Webhook Stripe reçu et traitment correctement
  [ ] Rate limiting active
  [ ] Logs d'audit générés

MONITORING:
  [ ] Logs configurés et lisibles
  [ ] Alertes email pour erreurs critiques
  [ ] Dashboard monitoring Stripe
  [ ] Backup logs quotidiens
```

---

## 4️⃣ Variables d'Environnement Complètes

```env
# ===== SÉCURITÉ =====
DEV_MODE=false                        # IMPORTANT: Toujours false en prod

# ===== STRIPE =====
STRIPE_SECRET=sk_live_...             # Clé PRODUCTION
STRIPE_WEBHOOK_SECRET=whsec_...       # Webhook secret

# ===== DATABASE =====
DB_HOST=localhost
DB_NAME=taxi_gabon
DB_USER=root
DB_PASSWORD=votre_mot_de_passe

# ===== EMAIL =====
MAIL_HOST=smtp.gmail.com              # Ou votre serveur SMTP
MAIL_PORT=587
MAIL_USER=noreply@taxigabon.ga
MAIL_PASS=votre_app_password
MAIL_FROM=noreply@taxigabon.ga
MAIL_FROM_NAME=Taxi Gabon

# ===== APP =====
APP_ENV=production                    # local ou production
APP_DEBUG=false                       # TOUJOURS false en prod
LOG_LEVEL=WARNING                     # ERROR, WARNING, INFO
```

---

## 5️⃣ Commandes Utiles

### Lister les fichiers modifiés:
```bash
git status
```

### Voir les changements:
```bash
git diff includes/auth.php
git diff api/book-ride.php
```

### Lancer les tests:
```bash
bash test-security.sh
```

### Vérifier la configuration:
```php
<?php
// check-config.php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/validators.php';
require_once 'includes/validators.php';

echo "✓ Tous les fichiers de sécurité chargés\n";
?>
```

---

## 6️⃣ Dépannage Courant

### ❌ Erreur: "Non authentifié" sur API
**Solution**: Vérifier que la session est bien démarrée
```php
// Au début de chaque fichier API
session_start();
```

### ❌ Erreur: "Fichier auth.php introuvable"
**Solution**: Vérifier que `includes/auth.php` existe
```bash
ls includes/auth.php    # Doit exister
```

### ❌ Validation email toujours échoue
**Solution**: Vérifier que `validators.php` est chargé
```php
require_once __DIR__ . '/../includes/validators.php';
```

### ❌ Rate limiting trop restrictif
**Solution**: Modifier les paramètres dans le code
```php
// Dans api/book-ride.php, ligne ~16
if (!checkRateLimit('book_ride', 5, 60)) {  // 5 tentatives/60 sec
    // Augmenter le 5 à 10 si nécessaire
}
```

---

## 7️⃣ Architecture Sécurité

```
┌─────────────────┐
│   Utilisateur   │
└────────┬────────┘
         │
    ┌────▼─────────┐
    │   CSRF Check  │ includes/csrf.php
    └────┬─────────┘
         │
    ┌────▼─────────────┐
    │  Authentication   │ includes/auth.php
    │  Rate Limiting    │
    └────┬─────────────┘
         │
    ┌────▼──────────────┐
    │   Validation      │ includes/validators.php
    │   GPS/Email/etc   │
    └────┬──────────────┘
         │
    ┌────▼──────────────┐
    │  Sanitization     │ htmlspecialchars()
    │  Prepared Stmts   │
    └────┬──────────────┘
         │
    ┌────▼──────────────┐
    │   Database        │
    │   (Protected)     │
    └───────────────────┘
```

---

## ✅ Succès = Vous pouvez maintenant:

- ✓ Inscrire des utilisateurs avec validation stricte
- ✓ Authentifier les API calls
- ✓ Valider les coordonnées GPS
- ✓ Protéger contre les abus (rate limit)
- ✓ Audit trail des actions sensibles
- ✓ Gérer les montants Stripe en sécurité

---

**Besoin d'aide?** Voir `SECURITY_IMPROVEMENTS.md` pour détails complets.
