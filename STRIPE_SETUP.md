# Configuration de Stripe pour Taxi Gabon

## ⚠️ Configuration requise

Le système de paiement Stripe **n'est pas configuré correctement**. Vous devez fournir vos vraies clés Stripe pour que le paiement fonctionne.

## 📋 Étapes à suivre

### 1. Créer un compte Stripe (si vous n'en avez pas)
- Allez sur [https://stripe.com](https://stripe.com)
- Créez un compte business
- Vérifiez votre identité

### 2. Récupérer vos clés API
1. Connectez-vous à votre [tableau de bord Stripe](https://dashboard.stripe.com)
2. Allez dans **Developers** → **API Keys**
3. Vous verrez deux clés:
   - **Publishable key** (commence par `pk_test_` ou `pk_live_`)
   - **Secret key** (commence par `sk_test_` ou `sk_live_`)

### 3. Configurer le fichier `.env`

Ouvrez le fichier `.env` à la racine du projet et remplacez:

```env
# AVANT (placeholder - NE FONCTIONNE PAS):
STRIPE_SECRET=sk_test_your_test_key_here
STRIPE_WEBHOOK_SECRET=whsec_your_webhook_secret_here

# APRÈS (avec vos vraies clés):
STRIPE_SECRET=sk_test_xxxxxxxxxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxx
```

### 4. Configurer les Webhooks Stripe (optionnel mais recommandé)

Les webhooks permettent à Stripe de notifier votre application en cas de paiement:

1. Dans le tableau de bord Stripe → **Developers** → **Webhooks**
2. Cliquez sur **Add endpoint**
3. URL à configurer:
   ```
   https://votre-domaine.com/api/stripe-webhook.php
   ```
4. Sélectionnez les événements:
   - `checkout.session.completed`
5. Copiez le **Signing secret** dans `.env` sous `STRIPE_WEBHOOK_SECRET`

## 🧪 Mode test vs production

### Mode TEST (recommandé pour le développement)
- Utilisez les clés commençant par `pk_test_` et `sk_test_`
- Utilisez les [numéros de carte de test Stripe](https://stripe.com/docs/testing)
- Exemples:
  - `4242 4242 4242 4242` (carte valide)
  - `4000 0000 0000 0002` (carte déclinée)

### Mode PRODUCTION (réel)
- Utilisez les clés commençant par `pk_live_` et `sk_live_`
- Les vrais paiements seront traités
- ⚠️ Assurez-vous que tout est bien testé en mode TEST avant de passer en production

## 🔐 Sécurité

- ✅ Jamais committer les vraies clés dans Git (le `.env` est dans `.gitignore`)
- ✅ Garder vos clés secrètes confidentielles
- ✅ Utiliser les variables d'environnement pour différents environnements

## ✅ Vérification

Une fois configuré, testez en:
1. Créant une nouvelle course (bouton "Nouvelle Course")
2. Estimant le prix
3. En cliquant "Confirmer & Commander"
4. Vous devriez être redirigé vers Stripe Checkout

## 🆘 Dépannage

### Erreur: "STRIPE_SECRET n'est pas configuré"
→ Vous utilisez toujours le placeholder. Remplacez par votre vraie clé `sk_test_...`

### Erreur: "Paiement non confirmé"
→ La session Stripe n'a pas pu être récupérée. Vérifiez:
- Votre clé secrète est correcte
- Votre connexion Internet
- Les logs d'erreur dans `storage/logs/app.log`

### Erreur: "Une erreur cURL"
→ Problème de connexion à l'API Stripe. Vérifiez:
- Votre firewall n'est pas bloquant
- La connexion Internet fonctionne
- Vous pouvez accéder à `https://api.stripe.com`

## 📞 Support

- [Documentation Stripe](https://stripe.com/docs)
- [Support Stripe](https://support.stripe.com)
