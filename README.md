# OpenMeru Mail

Open-source webmail for your own domain. Create `you@yourdomain.com` mailboxes from an admin panel, send through **Brevo**, receive through **Cloudflare Email Routing**, and keep a backup copy of every message in **Gmail**.

- Admin panel at `/admin` (create, search, open and delete mailboxes, with a 6-digit sign-in code sent by email)
- Webmail at `/mail`, where each mailbox sees only its own mail
- Rich-text editor, attachments, Cc/Bcc, drafts, stars, spam, trash and search
- Push notifications, profile photo, signature and auto-forwarding
- 10 languages: English, Português (BR and PT), Español, Français, Deutsch, Italiano, Nederlands, Русский and 中文
- Light and dark themes, built for mobile

Website: <https://openmeru.com>. Live demo (read-only): [mailbox](https://openmeru.com/demo) and [admin](https://openmeru.com/demo/admin).

---

## How it works

```
               sending                                    receiving
 Webmail ──SMTP──▶ Brevo ──▶ the world      the world ──▶ Cloudflare Email Routing (catch-all)
                                                              │
                                                              ▼
                                                    Email Worker ──▶ Gmail (backup copy)
                                                              │
                                              POST /hooks/inbound (shared secret)
                                                              ▼
                                                      OpenMeru Mail ──▶ mailbox
```

You don't run a mail server (no Postfix, no IMAP, no port 25). Brevo delivers outgoing mail. Cloudflare accepts incoming mail and hands it to a small Worker. The Worker sends a copy to your Gmail and posts the raw message to your server.

## What you need

| | |
|---|---|
| A server | Any Linux VPS with **PHP 8.3+** (8.5 recommended), **MySQL 8 / MariaDB 10.6+**, **Composer** and nginx or Apache. [CloudPanel](https://www.cloudpanel.io) makes this easy. |
| A domain | Its DNS must be managed by **Cloudflare** (the free plan is enough). |
| A Brevo account | Free plan: 300 emails per day. <https://www.brevo.com> |
| A Gmail account | Receives a backup copy of every incoming message (the catch-all copy) and the admin sign-in codes. Create a new one just for this, for example `yourproject@gmail.com`. |

PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `sodium`, `gmp` or `bcmath`, `dom`, `fileinfo`, `curl`, `intl`.

---

## Step 1: Gmail for the catch-all

1. Create a Gmail account for the project, for example `yourproject@gmail.com`.
2. You'll use it twice:
   - as the **Cloudflare destination address**, which receives a copy of every message sent to any address on your domain;
   - as **`ADMIN_OTP_EMAIL`**, which receives the 6-digit code each time someone signs in to `/admin`.

Even if your server is down, no mail is lost: it's all in Gmail.

## Step 2: Install the app on your server

The example uses a CloudPanel site user `openmeru` and the domain `yourdomain.com`.

```bash
cd ~
git clone https://github.com/<your-user>/OpenMeru.git openmeru-app
cd openmeru-app
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Create a MySQL database and user. In CloudPanel: **Databases → Add Database**. Then edit `.env`:

```ini
APP_URL=https://yourdomain.com
MAIL_DOMAIN=yourdomain.com
INBOUND_SECRET=   # paste the output of: openssl rand -hex 24
ADMIN_OTP_EMAIL=yourproject@gmail.com
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
```

Set up the database, your domain, the first admin and the push-notification keys:

```bash
php artisan migrate --force
php artisan openmeru:domain yourdomain.com
php artisan openmeru:admin admin@yourdomain.com "Administrator" 'a-strong-password'
php artisan tinker --execute='App\Support\Push::generateKeys();'
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Point the web server at `public_root`

The public files live in `public_root/` (not `public/`). Pick one of these:

- **Option A, any server:** set the site's document root to `/home/openmeru/openmeru-app/public_root`.
- **Option B, CloudPanel default:** keep the root at `~/htdocs/yourdomain.com` and copy the files there. Repeat this after every update:

  ```bash
  cp -a ~/openmeru-app/public_root/. ~/htdocs/yourdomain.com/
  ```

  `public_root/index.php` finds the app in `~/openmeru-app` on its own.

nginx needs the usual Laravel rule, `try_files $uri $uri/ /index.php?$query_string;`, and HTTPS. CloudPanel's free Let's Encrypt certificate works, and so does Cloudflare's proxy.

Make `storage/` and `bootstrap/cache/` writable by the site user.

Open `https://yourdomain.com/mail` and you should see the sign-in page.

## Step 3: Brevo, for sending

1. Create an account at <https://www.brevo.com>.
2. **Senders, Domains & Dedicated IPs → Domains → Add a domain**, then enter `yourdomain.com`.
3. Brevo shows DNS records: a Brevo code (TXT), DKIM (TXT or CNAME) and DMARC (TXT). Add each one in **Cloudflare → DNS → Records**. Choose **DNS only** (grey cloud) for CNAMEs. Click **Verify** in Brevo.
4. **SPF:** your domain may have only **one** SPF record. Merge Cloudflare's and Brevo's into one:

   ```
   v=spf1 include:_spf.mx.cloudflare.net include:spf.brevo.com ~all
   ```

5. **Senders → Add a sender:** `hello@yourdomain.com`.
6. **SMTP & API → SMTP → Generate a new SMTP key.** Copy the **login** (something like `xxxx@smtp-brevo.com`) and the **key**.
7. Save them in OpenMeru, either in **`/admin` → Settings → SMTP** or on the command line:

   ```bash
   php artisan openmeru:smtp smtp-relay.brevo.com 587 'your-login@smtp-brevo.com' 'your-smtp-key' hello@yourdomain.com
   ```

   The SMTP password is stored encrypted in the database. It never goes in `.env` or in git.

Brevo accepts only senders on a verified domain, so every mailbox sends as `user@yourdomain.com`.

## Step 4: Cloudflare, for receiving

### 4.1 Turn on Email Routing

1. In Cloudflare, open your domain, then **Email → Email Routing → Get started / Enable**.
2. Let Cloudflare add its **MX** records (`route1/2/3.mx.cloudflare.net`) and its SPF record. Merge the SPF as shown in step 3.4.
3. **Destination addresses → Add destination address:** your Gmail (`yourproject@gmail.com`). Open the verification email in Gmail and confirm.

### 4.2 Create the Email Worker

1. **Workers & Pages → Create → Worker**, then name it `openmeru-mail-inbound`, then **Deploy**.
2. **Edit code:** replace everything with the contents of [`cloudflare-email-worker.js`](cloudflare-email-worker.js), then **Deploy**.
3. **Settings → Variables and Secrets:**

   | Type | Name | Value |
   |---|---|---|
   | Text | `GMAIL` | `yourproject@gmail.com` |
   | Text | `INBOUND_URL` | `https://yourdomain.com/hooks/inbound` |
   | **Secret** | `INBOUND_SECRET` | the same value as `INBOUND_SECRET` in `.env` |

### 4.3 Send the catch-all to the Worker

**Email Routing → Routing rules → Catch-all address → Edit**, then set:

- Action: **Send to a Worker**
- Destination: `openmeru-mail-inbound`
- **Enabled**: on

Now mail sent to *any* address `@yourdomain.com` goes to the Worker. The Worker copies it to Gmail and delivers it to the matching mailbox. Mail to an address that has no mailbox stays in Gmail only.

## Step 5: Test it

1. Open `https://yourdomain.com/admin` and sign in with the admin you created. A code arrives in your Gmail.
2. Create a mailbox, for example `test@yourdomain.com`.
3. Open `https://yourdomain.com/mail`, sign in as that mailbox, and send a message to your Gmail and to yourself.
4. Reply from Gmail. The reply should appear in the inbox within seconds, with a copy in Gmail.

If receiving fails, check:

- **Workers → openmeru-mail-inbound → Logs**: a `403` means the secrets don't match.
- **Email Routing → Activity log.**
- `storage/logs/laravel.log` on your server.

---

## Updating

```bash
cd ~/openmeru-app
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
cp -a public_root/. ~/htdocs/yourdomain.com/   # only for option B
```

## Commands

| Command | What it does |
|---|---|
| `php artisan openmeru:domain example.com` | Sets the mailbox domain (`.env` and texts) |
| `php artisan openmeru:admin email "Name" 'password'` | Creates or resets an admin mailbox |
| `php artisan openmeru:smtp host port login key from` | Saves the SMTP relay (Brevo) |

## Security notes

- `/hooks/inbound` rejects any request without the correct `X-OpenMeru-Secret` header.
- Admin sign-in needs the password **and** a one-time code sent to `ADMIN_OTP_EMAIL`.
- SMTP and API keys are encrypted with `APP_KEY`. Back up your `.env`.
- Never commit `.env`.

## License

MIT © Weslley Harakawa
