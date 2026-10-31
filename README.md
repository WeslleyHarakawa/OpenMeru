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
| A server | Any Linux VPS with **PHP 8.5**, **MySQL 8 / MariaDB 10.6+**, **Composer** and nginx or Apache. [CloudPanel](https://www.cloudpanel.io) makes this easy. |
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

Create a MySQL database and user first (CloudPanel: **Databases → Add Database**) and keep the name, user and password at hand.

Then sign in to your server over SSH **as the site user** (not root) and run:

```bash
curl -fsSL https://openmeru.com/install | bash
```

The installer:

1. checks PHP 8.5, the extensions, Composer and git;
2. clones the app into `~/openmeru-app` (`--dir` to change it) and runs `composer install`;
3. starts `php artisan openmeru:install`, which asks for your domain, the public URL, the Gmail for the sign-in codes, the database and the first admin. Passwords are typed without echo and never passed on the command line;
4. generates `APP_KEY`, `INBOUND_SECRET` and the push keys, creates the tables, saves the Brevo SMTP relay if you have the key at hand, builds the caches, and prints what is left to do in Cloudflare.

The same thing by hand (a fresh clone has no writable folders yet, so create them first):

```bash
git clone https://github.com/WeslleyHarakawa/OpenMeru.git openmeru-app
cd openmeru-app
mkdir -p storage/app/private storage/app/public storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache
chmod -R u+rwX,g+rwX storage bootstrap/cache   # and chown them to the web server user if it is not you
composer install --no-dev --optimize-autoloader
php artisan openmeru:install
```

Without those folders the app answers 500 ("Please provide a valid cache path").

`openmeru:install` can be run again at any time: it keeps what is already set and only asks for what is missing. Every answer can also be given as an option (`--domain`, `--url`, `--otp-email`, `--db-name`, `--db-user`, `--admin-email`, …; `php artisan openmeru:install --help`), except passwords.

### Point the web server at `public_root`

The public files live in `public_root/` (not `public/`). Set the site's document root to `~/openmeru-app/public_root`.

On CloudPanel the app is usually inside the site folder (`~/htdocs/yourdomain.com/openmeru-app`): go to the site's **Settings → Root Directory** and set it to `yourdomain.com/openmeru-app/public_root`. Nothing needs to be copied, now or after updates.

nginx needs the usual Laravel rule, `try_files $uri $uri/ /index.php?$query_string;`, and HTTPS. CloudPanel's free Let's Encrypt certificate works, and so does Cloudflare's proxy.

Open `https://yourdomain.com/` and you should see the sign-in page: on an install the webmail **is** the site root (`/mail`, `/webmail` and `/email` redirect there). Only openmeru.com itself keeps its project page at the root.

**Do Step 3 (Brevo) before opening `/admin`.** Signing in to `/admin` sends a 6-digit code by e-mail, and e-mail only works once the Brevo relay is saved. A mailbox can sign in at the root with just e-mail and password at any time.

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

You have two ways to route mail to the Worker; both keep a copy of everything in Gmail.

- **Catch-all → Worker** (simplest): every address on the domain reaches OpenMeru, new mailboxes work the moment you create them. Use it when nothing else receives mail on this domain.
- **One rule per mailbox** (safest when the domain already forwards mail elsewhere): leave the catch-all as it is and add a routing rule `name@yourdomain.com → Send to a Worker → openmeru-mail-inbound` for each mailbox you create in `/admin`. Addresses without a rule keep going wherever they went before.


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

Every install asks `https://openmeru.com/version` once a day. When a newer version is out, `/admin` shows a banner with **Update now** (on git checkouts: database backup, `git pull`, `composer install`, migrations, caches, with the log on screen) and the one-line command for everything else.

The same line that installs also updates. Run it as the site user:

```bash
curl -fsSL https://openmeru.com/install | bash
```

It pulls the latest code, runs `composer install`, migrates the database and rebuilds the caches. `.env` and your settings are kept. By hand:

```bash
cd ~/openmeru-app
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

## Commands

| Command | What it does |
|---|---|
| `php artisan openmeru:install` | Interactive setup: `.env`, keys, database, domain, admin, SMTP, caches. Safe to run again |
| `php artisan openmeru:domain example.com` | Sets the mailbox domain (`.env` and texts) |
| `php artisan openmeru:admin email "Name"` | Creates or resets an admin mailbox; asks for the password without echo (it can also be given as a third argument) |
| `php artisan openmeru:smtp host port login - from` | Saves the SMTP relay (Brevo); `-` asks for the key without echo |

## Security notes

- `/hooks/inbound` rejects any request without the correct `X-OpenMeru-Secret` header.
- Admin sign-in needs the password **and** a one-time code sent to `ADMIN_OTP_EMAIL`.
- SMTP and API keys are encrypted with `APP_KEY`. Back up your `.env`.
- Never commit `.env`.

## License

MIT © Weslley Harakawa
