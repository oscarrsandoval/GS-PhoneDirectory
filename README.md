# GS Phone Directory

A tiny, dependency-free PHP web app for managing a contact list and publishing
it as a **Grandstream-compatible `phonebook.xml`** that your IP phones can
auto-download.

- **Admin UI** (login-protected): add / edit / delete contacts.
- **Public file**: `phonebook.xml` — the only thing that is publicly reachable,
  regenerated automatically on every change. Point your phones at it.
- **Storage**: plain JSON files (`data/contacts.json`, `data/users.json`). No
  database server, no Composer, no framework.

Each contact has a first name, last name, one phone number, and a type
(**Work / Home / Mobile**).

### Users & roles

The app supports multiple accounts with two roles:

- **Admin** — manage contacts **and** manage users (add/remove accounts).
- **Editor** — add / edit / delete contacts only.

The first account you create in `setup.php` is an **admin**. Admins get a
**Users** link in the top nav to add more accounts (no file editing required).
Guards prevent deleting your own account or the last remaining admin.

---

## Requirements

- PHP 7.4+ (tested on PHP 8.x) with the `xmlwriter` extension (bundled by
  default).
- Apache with `.htaccess` (`AllowOverride All`) — or any web server where you
  replicate the access rules below.

## Install

1. Copy all files into your web root (or a subdirectory of it).
2. Make the `data/` directory writable by the web server, e.g.
   ```bash
   chmod 0775 data
   chown www-data:www-data data      # adjust to your server's user
   ```
3. Browse to the app. On first visit you'll be redirected to **`setup.php`** to
   create the first admin account. After that, `setup.php` disables itself.
4. Sign in and start adding contacts. `phonebook.xml` appears in the app folder
   and updates on every add/edit/delete (there's also a **Rebuild XML** button).

### Adding more users

Sign in as an admin and use the **Users** page (link in the top-right nav) to
add accounts — choose **Admin** or **Editor** for each. To remove someone, hit
**Remove** on their row.

If you ever need to add a user by hand (e.g. you've locked yourself out),
generate a bcrypt hash and append an entry to `data/users.json`:

```bash
php -r 'echo password_hash("the-password", PASSWORD_DEFAULT), "\n";'
```

```json
[
  { "username": "admin", "password_hash": "$2y$...", "role": "admin",  "created": "2026-06-05" },
  { "username": "alice", "password_hash": "$2y$...", "role": "editor", "created": "2026-06-05" }
]
```

> Accounts with no `role` field are treated as **admin** for backwards
> compatibility.

---

## Point your Grandstream phones at it

The phones download a file literally named **`phonebook.xml`**. The admin
dashboard shows the exact public URL to use. In the phone's web UI:

1. Go to **Phonebook → Phonebook Management**.
2. Enable **Download Phonebook** and set the mode to **HTTP** (or **HTTPS**).
3. Set **Phonebook XML Server Path** to the *directory* that contains
   `phonebook.xml` (do **not** include the filename — the phone always appends
   `phonebook.xml`). Example: `http://your-server/gs-phonedirectory`
4. Set a **Download Interval** (e.g. every few hours), or use **Download Now**.
5. Save & apply, then check **Phonebook** on the phone.

The generated XML looks like:

```xml
<?xml version="1.0" encoding="utf-8"?>
<AddressBook>
  <version>1</version>
  <Contact>
    <id>1</id>
    <FirstName>John</FirstName>
    <LastName>Doe</LastName>
    <Phone type="Work">
      <phonenumber>1001</phonenumber>
      <accountindex>1</accountindex>
    </Phone>
  </Contact>
</AddressBook>
```

> The `<accountindex>` value (the SIP line the phones dial out on) is set once
> via `PHONE_ACCOUNT_INDEX` in `includes/config.php` and applied to every
> contact when the phonebook is rebuilt.

---

## Security notes

- Passwords are stored only as bcrypt hashes (`password_hash`) and checked with
  `password_verify`.
- Sessions use HttpOnly + SameSite cookies and rotate the id on login.
- Every state-changing form is CSRF-protected.
- `.htaccess` files block web access to `data/` and `includes/`; only
  `phonebook.xml` and the PHP entry pages are public.
- **Hardening (recommended for production):** move the `data/` directory above
  the web root and update `DATA_DIR` in `includes/config.php`, so the JSON files
  are unreachable over HTTP regardless of `.htaccess` support. Serve the admin
  side over HTTPS.

## Local development

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/>. Validate the generated file with:

```bash
xmllint --noout phonebook.xml && echo "well-formed"
```

## File layout

```
index.php            Dashboard: list / search contacts, rebuild XML
login.php / logout.php
setup.php            First-run admin creation (self-disables)
users.php            Admin-only: add / remove users and set roles
contact_edit.php     Add / edit a contact
contact_delete.php   Delete a contact (with confirmation)
phonebook.xml        Generated — what the phones download (git-ignored)
assets/app.css       Admin styling
includes/            config, storage (JSON), auth, csrf, xml, layout
data/                contacts.json + users.json (git-ignored, not web-served)
```
