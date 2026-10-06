# Sue — share your interests

A hand-drawn-notebook personal site. Multi-user PHP + MySQL. Designed to run on plain shared hosting like InfinityFree.

## What's in here

```
sue/
├── index.php           public landing page
├── signup.php          create account
├── login.php           log in
├── logout.php
├── dashboard.php       owner: manage profile, pages, suggested pages
├── edit-section.php    owner: add / delete items in a page, rename page
├── import.php          owner: CSV / XLSX import for movies/shows/books/music/custom
├── u.php               public profile + per-section pages
├── schema.sql          database schema
├── .htaccess           pretty URLs (/u/username/section)
├── assets/
│   ├── style.css       hand-drawn notebook stylesheet
│   └── logo.png        the Sue logo
└── includes/
    ├── config.php      ← edit DB credentials and BASE_URL here
    ├── db.php
    ├── auth.php
    ├── helpers.php
    ├── header.php
    └── footer.php
```

## Sections every user starts with

Cool things about me · Movies · Shows · Books · Music · Hot takes · Skills & party tricks · Quotes · Now · Places · Stats · Ask me anything · Guestbook.

Users can also **+ add a page** of any type at any time, or pick from **Suggested pages** (only shows types the user hasn't added yet). Built so new types can be plugged in later from `helpers.php` (`SECTION_TYPES`).

## CSV / XLSX import

Movies, Shows, Books, Music, and any custom-list page can import a spreadsheet. Upload → preview → map columns → commit. XLSX is parsed natively (no Composer needed) — only `.xlsx` (Office Open XML) is supported, not legacy `.xls`.

Tested mappings:
- **Letterboxd** → Title, Year, Rating
- **Goodreads** → Title, Author, My Rating, Bookshelves (status)
- Any spreadsheet with at least a "title" column

## Deploying to InfinityFree

1. **Create a MySQL database** in the InfinityFree control panel → "MySQL Databases". Note the host, db name, user, password.
2. **Edit `sue/includes/config.php`**: fill in `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`. Set `BASE_URL` to `''` if uploaded to the domain root, or `'/sue'` if uploaded to a subfolder.
3. **Upload everything in `sue/`** to your `htdocs/` folder (or `htdocs/sue/`) via the InfinityFree file manager or FTP.
4. **Open phpMyAdmin** → select your database → **SQL** tab → paste the contents of `schema.sql` → run.
5. Visit your domain. Sign up. Done.

The footer prints:

> Copyright © 2026 Sue. Made by [Dima Alhajajlah](https://dimaalhajajlah-portfolio.my.canva.site).

The year auto-updates each year, and the site title comes from `$SITE_TITLE` in `config.php`. Note: `[hfe_current_year]` and `[hfe_site_title]` are WordPress shortcodes that don't work outside WordPress — this is the equivalent in plain PHP.

## Run locally to test (optional)

```bash
cd sue
php -S localhost:8000
```

You'll need a local MySQL/MariaDB; create a database, edit `includes/config.php` to point at it, import `schema.sql`, and visit `http://localhost:8000`.

## Customising

- **Site title / tagline** — `includes/config.php`
- **Logo** — replace `assets/logo.png`
- **Look & feel** — `assets/style.css` (paper colors, marker red `--red`, marker blue `--blue`)
- **Add a new section type** — add an entry to `SECTION_TYPES` in `includes/helpers.php`, then add a render block in `u.php` and an input block in `edit-section.php`.

## Known limits

- No password reset flow yet (no email server assumed on free hosting).
- AMA / guestbook have no spam protection beyond CSRF — add a captcha if you go viral.
- XLSX reader handles the first sheet only and skips formula recalculation.
