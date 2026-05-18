# RVM Recording Authorization Portal

A dealer-facing recording compliance portal. Dealers visit a unique public link, record audio for each campaign slot, check attestations, sign, and submit — all evidence is stored with a full audit trail. Admins manage campaigns and download evidence packages (ZIP with audio + manifest).

---

## Local Setup (XAMPP / MAMP)

### 1. Copy files

Place the project folder inside your web root:

- **XAMPP (Windows):** `C:\xampp\htdocs\bdcvmd\`
- **MAMP (Mac):** `/Applications/MAMP/htdocs/bdcvmd/`

### 2. Import the database

1. Start Apache and MySQL in XAMPP Control Panel.
2. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
3. Click **New** and create a database named `rvm_portal` with charset `utf8mb4_unicode_ci`.
4. Select the new database, click **Import**, choose `database.sql`, and click **Go**.

### 3. Configure database credentials

Edit `/config/database.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rvm_portal');
define('DB_USER', 'root');   // your MySQL username
define('DB_PASS', '');       // your MySQL password
```

### 4. Set the base URL

Edit `/config/app.php`:

```php
define('BASE_URL', 'http://localhost/bdcvmd');
```

Adjust the path to match your folder name and server.

### 5. Storage folder permissions

The `/storage/recordings/` folder must be **writable by PHP**. On Linux/Mac:

```bash
chmod -R 775 storage/recordings/
chown -R www-data:www-data storage/recordings/   # Apache user
```

On XAMPP for Windows this is automatic.

---

## Default Admin Login

| Field    | Value                |
|----------|----------------------|
| URL      | `/admin/login.php`   |
| Email    | `admin@example.com`  |
| Password | `password123`        |

**Change this password immediately after first login** (update the row in the `users` table via phpMyAdmin or add a profile page).

---

## How to Create and Activate a Campaign

1. Log in at `/admin/login.php`.
2. Go to **Campaigns → + New Campaign**.
3. Fill in the campaign name, intro copy, and signature prompt.
4. Add **Recording Slots** — each slot has a label, optional script, optional max seconds, and required flag.
5. Add **Attestations** — checkboxes the dealer must agree to before submitting.
6. Set Status to **Active** and click **Create Campaign**.
7. On the campaign view page, copy the **Public Submission URL** and share it with the dealer.

---

## How Dealers Use the Submission Link

1. Dealer opens the URL (e.g. `http://localhost/bdcvmd/public/submit.php?token=abc123…`).
2. Fills in their identity (name, dealership, email, phone, title).
3. For each recording slot: clicks **Record**, speaks the script, clicks **Stop**, previews playback, then clicks **Accept** (or **Re-record**).
4. Checks all required attestation boxes.
5. Types their full legal name in the Signature field.
6. Clicks **Submit Recording**.
7. A confirmation page with a reference number is shown.

---

## Viewing Submissions

- Go to **Submissions** in the admin nav.
- Filter by campaign if needed.
- Click **View** on any submission to see full identity info, audio players, attestation snapshots, signature, IP, user agent, and the full audit trail.
- Click **Download Evidence Package** to get a ZIP containing:
  - All audio recordings
  - `manifest.json` — machine-readable full metadata
  - `manifest.txt` — human-readable summary

---

## Browser Support for Recording

| Browser | Support |
|---------|---------|
| Chrome / Edge | Full (`audio/webm`) |
| Firefox | Full (`audio/ogg`) |
| Safari | Partial — Safari's `MediaRecorder` support improved in v14.1+; recommend Chrome for best results |

---

## File Structure

```
/config         DB connection and app constants
/includes       Shared PHP: auth, functions, header, footer
/admin          Admin-only pages (session-protected)
/public         Dealer-facing pages (token-protected)
/assets         CSS and JavaScript
/storage/recordings   Audio files organised by submission ID
database.sql    Full schema + seed admin user
```
