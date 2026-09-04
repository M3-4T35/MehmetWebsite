# 🚀 Guide de déploiement — MehmetAtes.fr

Check-list complète pour déployer et mettre à jour le site sur le serveur de production (Debian/Ubuntu, Apache + mod_php, PostgreSQL).

---

## 1. Prérequis serveur

* PHP **8.2+** avec extensions : `php-intl`, `php-fileinfo`, `php-pgsql`, `php-mbstring`, `php-xml`, `php-curl`
* Apache 2.4 (`mod_rewrite` actif par défaut via `FallbackResource`)
* PostgreSQL 14+
* Composer 2 et Git

```bash
sudo apt install php-intl php-fileinfo php-pgsql php-mbstring php-xml php-curl
```

---

## 2. Premier déploiement (installation complète)

```bash
cd /var/www/MehmetWebsite
git clone https://github.com/M3-4T35/MehmetWebsite.git .
composer install --no-dev --optimize-autoloader
```

**Configuration locale (ne jamais commiter) :**

```bash
cp .env .env.local
```

Éditer `.env.local` :

```ini
APP_ENV=prod          # ⚠️ jamais "dev" en production
APP_DEBUG=0           # ⚠️ jamais "1" en production
APP_SECRET=<chaîne-aléatoire-longue>   # ex: openssl rand -hex 32
DATABASE_URL="postgresql://user:motdepasse@127.0.0.1:5432/app?serverVersion=16&charset=utf8"
MAILER_DSN="smtp://user:pass@ssl0.ovh.net:465?encryption=ssl&auth_mode=login"
```

**Base de données + administrateur :**

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction

# Méthode 1 (recommandée) : Commande interactive Symfony
php bin/console app:create-user
# Ou directement : php bin/console app:create-user votre@email.fr mon_mot_de_passe --admin

# Méthode 2 : Hachage manuel + insertion SQL directe
php bin/console security:hash-password    # copier le hash généré
psql -h 127.0.0.1 -U <user> -d <nom_base> -c "INSERT INTO \"user\" (email, roles, password) VALUES ('votre@email.fr', '[\"ROLE_ADMIN\"]', 'LE_HASH');"
```

**Cache, assets et permissions :**

```bash
php bin/console cache:warmup --env=prod
sudo chown -R www-data:www-data public/uploads var/cache var/log
```

Puis configurer Apache et HTTPS (sections 4 et 5).

---

## 3. Mise à jour (déploiements suivants)

```bash
cd /var/www/MehmetWebsite
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction   # si migrations présentes
php bin/console cache:warmup --env=prod                        # vide aussi l'ancien cache
sudo systemctl reload apache2
```

> 💡 Les fichiers uploadés (médias/CV) vivent dans `public/uploads/` : ignorés par git, ils survivent aux déploiements.

---

## 4. Configuration Apache (VirtualHost)

Fichier : `/etc/apache2/sites-available/mehmetates.conf`

```apache
<VirtualHost *:80>
    ServerName mehmetates.fr
    ServerAlias www.mehmetates.fr

    DocumentRoot /var/www/MehmetWebsite/public

    <Directory /var/www/MehmetWebsite/public>
        AllowOverride None
        Require all granted
        FallbackResource /index.php
    </Directory>

    # 🔒 Sécurité : ne jamais exécuter ni lister les fichiers téléversés
    <Directory /var/www/MehmetWebsite/public/uploads>
        Options -Indexes -ExecCGI
        SetHandler none
        <FilesMatch "\.(?i:php|phtml|phar|php3|php4|php5|php7|phps|cgi|pl|py|sh)$">
            Require all denied
        </FilesMatch>
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mehmet_error.log
    CustomLog ${APACHE_LOG_DIR}/mehmet_access.log combined

    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
    </IfModule>
</VirtualHost>
```

```bash
sudo a2ensite mehmetates.conf && sudo systemctl reload apache2
```

---

## 5. HTTPS (obligatoire avant mise en ligne)

Les liens de réinitialisation de mot de passe et la session admin circulent sinon en clair.

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d mehmetates.fr -d www.mehmetates.fr
```

Certbot ajoute automatiquement le vhost 443 et la redirection 80→443. Renouvellement automatique inclus (timer systemd).

---

## 6. Limites d'upload (250 Mo)

Le projet utilise **mod_php**, qui ignore `public/.user.ini` : il faut éditer `/etc/php/8.5/apache2/php.ini` :

```ini
upload_max_filesize = 260M
post_max_size = 260M
```

```bash
sudo systemctl restart apache2
php -i | grep -E 'upload_max_filesize|post_max_size'   # vérification
```

---

## 7. Vérifications post-déploiement

```bash
# Pages publiques
for p in /fr/ /fr/travaux /fr/cv/public /fr/login; do
  curl -s -o /dev/null -w "%{http_code} $p\n" https://mehmetates.fr$p
done
```

* [ ] Accueil, travaux, CV publics en FR **et** EN → `200`
* [ ] Bascule de langue FR ⇄ EN fonctionne
* [ ] Connexion admin OK, tableau de bord accessible
* [ ] Création d'un projet → dates remplies automatiquement
* [ ] Upload image/vidéo lourde (~200 Mo) → OK (valide les limites PHP)
* [ ] Suppression d'un média → le fichier disparaît bien du disque
* [ ] Mode sombre : formulaire de connexion lisible
* [ ] Réinitialisation de mot de passe → email reçu (valide le SMTP)

---

## 8. Dépannage rapide

| Symptôme | Cause probable | Solution |
|---|---|---|
| « Impossible de déplacer le fichier » | `public/uploads/*` non accessible en écriture pour www-data | `sudo chown -R www-data:www-data public/uploads` |
| Upload refusé au-delà de ~2 Mo | Limites PHP par défaut (mod_php) | Section 6 |
| Erreur SQL `null ... created_at` | Cache prod obsolète | `cache:warmup --env=prod` |
| Page blanche / 500 après déploiement | Cache mélangé dev/prod | `rm -rf var/cache/prod && cache:warmup --env=prod` |
| Emails non envoyés | `MAILER_DSN` invalide | Tester : `php bin/console mailer:test <email>` |

Logs applicatifs : `var/log/prod.log` · Logs Apache : `/var/log/apache2/mehmet_*.log`
