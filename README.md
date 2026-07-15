# Pazar Press — PHP CMS

Lokalni news portal (fork Sandžak.net `web/` CMS), rebrandovan u Pazar Press.

## Zahtevi

- PHP 8.0+
- SQLite (lokalno) ili MySQL (produkcija)

## Pokretanje

```bash
cd c:\Projekti\passpress
php database\install.php
php database\seed.php
php -S localhost:8080 -t public public\router.php
```

Otvori: http://localhost:8080

**Admin:** http://localhost:8080/admin/login  
Email: `admin@pazarpress.local` / Lozinka: `admin123`

## Struktura

- `public/` — web root
- `app/` — PHP logika, rute, CMS
- `views/` — šabloni
- `database/` — schema + seed
- `mockup/` — HTML dizajn prototip (referenca)
- `storage/` — settings + cache

## Brend

- Boje: crvena `#C8102E` (CSS `--pine`)
- Logo: `public/assets/img/pazar-press-logo.png`
- Ime: `app/config.php` → `site_name`
