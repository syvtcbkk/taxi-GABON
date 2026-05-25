Stripe integration — tests locaux

Prerequisites
- Install Stripe CLI: https://stripe.com/docs/stripe-cli
- Add your Stripe keys to environment or .env:

  STRIPE_SECRET=sk_test_...
  STRIPE_WEBHOOK_SECRET=whsec_...

Testing locally with Stripe CLI
1. Start your local PHP server or ensure localhost is reachable by Stripe CLI. Example (from project root):

```bash
php -S localhost:8000 -t .
```

2. Forward webhook events to your local webhook endpoint:

```bash
stripe listen --forward-to "http://localhost:8000/api/stripe-webhook.php"
```

3. Create a Checkout session from the UI (use the app) and pay with Stripe test card `4242 4242 4242 4242`, any future expiry and CVC `123`.

4. Observe the webhook events in the CLI and verify a new ride is created in the database.

Notes
- Ensure `STRIPE_SECRET` is set for the app (used by server to call Stripe API).
- Use the `--events` flag to limit events, e.g. `stripe listen --events checkout.session.completed`.
- For production, register the webhook URL in the Stripe Dashboard and set `STRIPE_WEBHOOK_SECRET` accordingly.
