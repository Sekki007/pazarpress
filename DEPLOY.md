# PazarPress.net — deploy (SSH)

Pretpostavka: Ubuntu/Debian VPS + SSH. Domain: **pazarpress.net**

## 1) DNS (kod registrara domena)

| Tip | Ime | Vrednost |
|-----|-----|----------|
| A | `@` | IP adresa servera |
| A | `www` | IP adresa servera |

Sačekaj 5–60 min da se proširi.

## 2) Server paketi

```bash
sudo apt update
sudo apt install -y nginx mysql-server php-fpm php-mysql php-sqlite3 php-mbstring php-xml php-curl php-gd php-zip git unzip certbot python3-certbot-nginx
```

Proveri PHP verziju (treba 8.0+):

```bash
php -v
```

## 3) Folder sajta + Git

```bash
sudo mkdir -p /var/www/pazarpress
sudo chown -R $USER:www-data /var/www/pazarpress
cd /var/www/pazarpress

# Opcija A — sa GitHub-a (preporučeno)
git clone https://github.com/TVOJ_USER/pazarpress.git .

# Opcija B — prvi put bez Gita (scp sa Windowsa)
# scp -r c:\Projekti\passpress\* user@IP:/var/www/pazarpress/
```

Ne uploaduj lokalni `.env` i `*.sqlite` ako već postoje na serveru.

## 4) Dozvole

```bash
cd /var/www/pazarpress
mkdir -p storage/cache public/uploads
sudo chown -R www-data:www-data storage public/uploads
sudo chmod -R 775 storage public/uploads
```

## 5) `.env` na serveru

```bash
cp .env.example .env
nano .env
```

Primer produkcije:

```env
SITE_URL=https://pazarpress.net
APP_DEBUG=0
MAIL_FROM=noreply@pazarpress.net
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pazarpress
DB_USERNAME=pazarpress
DB_PASSWORD=JAKA_LOZINKA
```

## 6) MySQL baza

```bash
sudo mysql
```

```sql
CREATE DATABASE pazarpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pazarpress'@'localhost' IDENTIFIED BY 'JAKA_LOZINKA';
GRANT ALL PRIVILEGES ON pazarpress.* TO 'pazarpress'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Schema + admin:

```bash
cd /var/www/pazarpress
# Ako imaš mysql schema:
mysql -u pazarpress -p pazarpress < database/schema.mysql.sql
# ili SQLite schema prilagođena — proveri da li postoji schema.mysql.sql

php database/seed.php
```

Promeni admin lozinku odmah posle prvog logina.

## 7) Nginx (document root = `public/`)

```bash
sudo nano /etc/nginx/sites-available/pazarpress.net
```

```nginx
server {
    listen 80;
    server_name pazarpress.net www.pazarpress.net;
    root /var/www/pazarpress/public;
    index index.php;

    client_max_body_size 12M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;  # prilagodi: php -v / ls /run/php/
    }

    location ~* /\.(env|git) {
        deny all;
    }

    location ~* ^/(app|database|storage|views)/ {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/pazarpress.net /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

> `fastcgi_pass` sock mora da odgovara tvojoj PHP verziji (`ls /run/php/`).

## 8) SSL (HTTPS)

```bash
sudo certbot --nginx -d pazarpress.net -d www.pazarpress.net
```

Proveri da je u `.env`: `SITE_URL=https://pazarpress.net`

## 9) Ažuriranje posle izmena (Git)

Na PC-u:

```bash
git add .
git commit -m "..."
git push
```

Na serveru:

```bash
cd /var/www/pazarpress
git pull
sudo systemctl reload php8.1-fpm   # ako treba
```

## 10) Provera

- https://pazarpress.net  
- https://pazarpress.net/admin/login  
- Upload slike u članku  
- HTTPS katanac u browseru  

---

## Ako je cPanel + SSH (ne čist VPS)

1. DNS A → hosting IP  
2. Document root domena stavi na `.../pazarpress/public`  
3. PHP 8.0+  
4. MySQL baza iz cPanel-a → upiši u `.env`  
5. SSL: AutoSSL / Let's Encrypt u cPanelu  
6. Kod: `git clone` u home folder, ili upload pa `git` kasnije  

---

## Bezbednost (obavezno)

- `APP_DEBUG=0`  
- Jaka MySQL + admin lozinka  
- Nemoj committovati `.env`  
- `storage/` i `uploads/` ne smeju biti browsable van aplikacije  
