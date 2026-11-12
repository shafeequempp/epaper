# Mathrubhumi ePaper: upload dashboard + reader website

InDesign pages are exported with a script, uploaded in a PHP dashboard, stored in MySQL, and shown on a reader site with three views (All pages, Page list, Full page), a separate article view, and search.

## How it works

1. **InDesign** — the designer runs `indesign/EpaperExport.jsx`. It saves one 200 dpi JPG per page and a `manifest.json` with each article's headline, body, captions and position on the page.
2. **Dashboard** (`/admin/`) — upload the manifest + JPGs for a date and one or more editions. PHP makes the thumbnail and page-list images, cuts one image per article, and saves the text. An editor reviews the article boxes and publishes.
3. **Reader** (`/`) — readers choose an edition and date, browse the pages, click a story to read it as printed or as text, and search.

Why export from InDesign instead of uploading the .indd: photos and ads are linked from network drives (G:, L:, S:) and are not inside the .indd. Only InDesign on a desk PC can see them.

## Install on WAMP

1. Copy this folder to `C:\wamp64\www\epaper`.
2. In phpMyAdmin, import `sql/schema.sql` (creates the `epaper` database), then import `sql/editions_seed.sql` (the 125 Mathrubhumi editions with their file-name codes).
   **Upgrading an existing install?** Import `sql/upgrade_editions.sql` once, then `sql/editions_seed.sql`. Earlier test editions without a code are hidden, not deleted.
3. Copy `config.sample.php` to `config.php` and set the database user and password. If the folder is not `/epaper`, change `base_url` and `storage_url`.
4. In `php.ini`, enable `extension=gd` and set:
   ```
   upload_max_filesize = 64M
   post_max_size = 128M
   max_file_uploads = 50
   memory_limit = 768M
   ```
   Restart WAMP.
5. Make sure `storage/` is writable by PHP.
6. Open `http://localhost/epaper/admin/`. The first visit asks you to create the admin account.

Requires PHP 7.4+ with GD, PDO MySQL and mbstring; MySQL 5.7+ or MariaDB 10.3+.

## Install the InDesign script

1. In InDesign open **Window > Utilities > Scripts**.
2. Right-click **User** > **Reveal in Explorer**, and copy `EpaperExport.jsx` into that folder.
3. With the page open, double-click **EpaperExport.jsx** and choose where to save.
4. Check the headline shown at the end reads as proper Malayalam. If it looks like English letters, that font is not Unicode and its text needs converting before search works.

**Best results:** group each story (headline, text, photos, caption) in **Window > Articles**. The script uses those groups exactly. Without them it guesses from grouped frames and threaded stories, which can mis-join stories in busy layouts.

Settings at the top of the script: page resolution, the ad folder pattern (`/ads/`), and the headline size threshold.

## One-button import from InDesign (batch)

### File names decide edition, date and page

```
9pg2e-LocalNews.indd     day 9,  page 2, edition code E  -> Kothamangalam-Muvattupuzha
16pg1cc-Front.indd       day 16, page 1, edition code CC -> Ernakulam
9pg12kt2-Kannur.indd     day 9,  page 12, edition code KT2 -> Kannur
```
The month and year come from the date chosen in the script's dialog (a date like `20261009` in the folder path fills it in). For day 30 with a chosen date of 1 November, the script picks 30 October. One folder may hold any mix of editions. Files whose names do not follow the pattern are skipped and listed.

### Common pages

Sub-editions show their main edition's page for any page number they have no file for. If the Muvattupuzha folder holds only `9pg2e` and `9pg3e`, its e-paper shows Ernakulam's page 1 with Muvattupuzha's pages 2 and 3. Mumbai and New Delhi take missing pages from Common-Outside-Kerala. Supplements never take pages from anywhere. Change this per edition on the **Editions** page (**Missing pages from**).

### Shared codes

In the edition list, `WKN` is both Kannur Weekend and Malappuram Weekend, and `MK` is both Kondotty and Thrissur Micro Supplement. The dashboard decides from the other files in the same export: an `mk` file exported with `mt` files goes to Kondotty; with `rt` files it goes to Thrissur. If it cannot tell, the upload stops with a message; give one of them a different code on the Editions page.

### One-time setup
1. In `config.php` set `'api_key'` to a long random value.
2. Open `EpaperExport.jsx` in Notepad and set `uploadUrl` (e.g. `http://localhost/epaper/api/import.php`) and `apiKey` (same as `api_key`).
3. Windows 10/11 includes `curl.exe`, which the script uses to upload. Nothing to install.

### Every day
1. Run **EpaperExport.jsx**, choose **All .indd files in a folder**, pick the folder, check the date.
2. Tick **Upload to the ePaper dashboard** and, if wanted, **Publish each edition when all its pages uploaded**. Press **Export**.

The script opens each file without showing it, exports it, closes it without saving, uploads each page to its edition, and shows a summary per edition. An edition is only published if all its pages uploaded. Running again replaces pages; nothing is duplicated. Details are in `_epaper_<date>\export_log.txt`; the server's reply for each page is in `_upload`.

**If upload fails**, the summary says why: wrong API key, unknown edition code, a shared code it could not settle, a file too large (raise `upload_max_filesize` / `post_max_size`), or the dashboard could not be reached (check `uploadUrl`). The exported `manifest.json` and JPGs stay in the folder; uploading them on the **Upload pages** screen uses the same codes and dates.

## Daily use (manual upload)

1. Export each page from InDesign.
2. Dashboard > **Upload pages**: pick the date, tick the edition(s), drop in `manifest.json` and the JPGs. Tick several editions for a common page.
3. **Review** the issue: open each page, check the numbered boxes, fix text with **Edit text**, **Remove** wrong boxes.
4. **Publish**. To change a page later, upload it again with the same page number; it replaces the old one.

Roles: uploader (upload), editor (review, edit, publish, delete pages), admin (everything, editions, users, delete issues).

**Register users:** sign in as an admin, open **Users**, fill in username, password and role, and press **Add user**. The same page changes roles, resets passwords and deletes accounts. Changes apply immediately, even to people already signed in.

## Reader URLs

| View | URL |
|---|---|
| All pages | `index.php?e=kothamangalam-muvattupuzha&d=2026-10-09` |
| Page list | `list.php?e=kothamangalam-muvattupuzha&d=2026-10-09` |
| Full page | `page.php?e=kothamangalam-muvattupuzha&d=2026-10-09&p=2` |
| Article | `article.php?id=123` (add `&mode=text` for text) |
| Search | `search.php?q=പാലം&e=ernakulam` |

Editors can add `&preview=1` to see unpublished issues while signed in.

## Files

```
indesign/EpaperExport.jsx   InDesign export script
sql/schema.sql              database tables
sql/editions_seed.sql       the 125 editions, codes, groups, common-page links (safe to re-run)
sql/upgrade_editions.sql    one-time upgrade for databases made with the earlier schema
inc/editions.php            edition codes and common pages
config.sample.php           settings (copy to config.php)
inc/importer.php            upload processing: images, crops, DB
inc/reader.php              reader queries and search
admin/                      dashboard
index.php list.php page.php article.php search.php   reader
api/article.php             article JSON for the Full page panel
api/import.php              receives pages uploaded by EpaperExport.jsx (needs api_key)
assets/                     CSS and JS
storage/                    generated images (keep writable)
```

## Reader subscriptions (paywall + payments)

Readers get their own accounts, separate from staff. **Today's paper is free for everyone;** older/archived editions, article text and page downloads need an active subscription. Billing and auto-renewals run through **Razorpay**.

### Set up
1. In phpMyAdmin, import `sql/subscriptions.sql` (adds the subscription tables and two starter plans — Monthly and Annual). Safe to re-run.
2. In `config.php` fill in the `razorpay` block:
   - `key_id` / `key_secret` — Razorpay Dashboard → **Settings → API Keys** (use `rzp_test_…` keys while testing).
   - `webhook_secret` — a secret you choose; set the same value in the webhook (next step).
   Also set the `mail` block (`from_email`, `from_name`) for verification / reset e-mails.
3. In Razorpay Dashboard → **Settings → Webhooks**, add a webhook pointing at
   `https://your-domain/epaper/api/razorpay_webhook.php`, with the same secret, subscribed to:
   `subscription.activated`, `subscription.charged`, `subscription.pending`,
   `subscription.halted`, `subscription.cancelled`, `subscription.completed`, `payment.failed`.
   The webhook is what keeps renewals and cancellations correct, so it must be reachable publicly.
   On local WAMP, expose it with a tunnel (e.g. ngrok) or test on a public host.
4. Sign in to the dashboard as an **admin** → **Plans** to set prices/durations. Each plan is created in
   Razorpay automatically the first time someone subscribes (or press **Provision now**).

Until Razorpay keys are set, accounts and the admin all work; the Subscribe button shows a "not configured" notice instead of taking payment.

### How it behaves
- The newest published date of each edition is free. Open a past date and non-subscribers see a paywall.
- Turn the whole paywall off, or stop downloads being free on the latest issue, in `config.php` → `paywall`.
- Readers manage their plan, renewal date, payment history and auto-renewal at `/account/`.
- Admins see and manage accounts under **Subscribers**: search, block, or grant complimentary access.

### Subscription files
```
sql/subscriptions.sql          subscriber, plan, subscription, payment & webhook tables
inc/subscriber.php             reader accounts, access checks, the paywall rule
inc/razorpay.php               Razorpay REST client + signature verification
inc/mailer.php                 verification / reset e-mail (PHP mail(); logs to storage/_mail)
inc/account_layout.php         layout for the reader account pages
account/                       register, login, verify, forgot/reset, dashboard, cancel
subscribe.php                  plan selection + Razorpay Checkout
api/checkout.php               creates a subscription for Checkout
api/razorpay_webhook.php       renewals / cancellations (source of truth)
admin/plans.php                manage plans
admin/subscribers.php          manage subscriber accounts & access
```

## Known limits and next steps

- A shared page uploaded to several editions is stored once but its articles appear once per edition in search (search is limited to the chosen edition by default).
- Replaced pages leave their old image folder in `storage/`; clean up old folders periodically.
- The article boxes can be removed but not redrawn in the dashboard; fix them in the InDesign Articles panel and re-upload.
- For large archives, move images to S3 + CloudFront and search to OpenSearch.
