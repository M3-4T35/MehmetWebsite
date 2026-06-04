# MehmetAtes.fr | Portfolio Professionnel

Ce projet est un site web dynamique faisant office de **CV numérique et de portfolio professionnel**. Il est conçu pour mettre en valeur mes réalisations, mes compétences et mon parcours académique en tant qu'étudiant en Master Informatique.

## ✨ Fonctionnalités Majeures

### 🌐 Partie Publique (Showcase)

* **Bilingue Complet (FR/EN) :** Support intégral des deux langues avec bascule instantanée via la barre de navigation.
* **Portfolio Dynamique :** Galerie de projets avec miniatures, descriptions détaillées en Markdown et galerie média interactive.
* **Parcours Professionnel :** Section dédiée aux CVs avec prévisualisation PDF intégrée ("Consulter en ligne") et téléchargement.
* **Design Moderne :** Interface épurée avec effet "Glassmorphism" sur la barre de navigation, animations fluides et compatibilité mobile (Responsive Design).
* **Mode Sombre / Clair :** Bascule intelligente respectant les préférences système de l'utilisateur.

### 🔐 Espace Administration (Back-Office)

Un espace sécurisé réservé à l'administrateur permet de gérer l'intégralité du contenu :

* **Gestion des Projets :** CRUD complet avec saisie bilingue (titre, description courte et longue) sur la même interface.
* **Éditeur Markdown :** Prévisualisation en temps réel du rendu des descriptions de projets.
* **Gestion des Médias :** Téléchargement d'images et vidéos, association aux projets et gestion de l'ordre d'affichage.
* **Gestion des CV :** Upload et mise à jour simplifiée des fichiers PDF.
* **Sécurité Avancée :** Authentification robuste, protection CSRF sur toutes les actions sensibles (suppression) et système de réinitialisation de mot de passe par email.

## 🛠 Prérequis

* **PHP :** 8.2 ou supérieur
* **Serveur Web :** Apache (avec `mod_rewrite` activé) ou Nginx
* **Base de données :** PostgreSQL (recommandé) ou MySQL/MariaDB
* **Gestionnaire de paquets :** Composer
* **Outils :** Symfony CLI (optionnel mais recommandé)

## 🚀 Installation

1. **Clonage du projet :**

   ```bash
   git clone https://github.com/M3-4T35/MehmetWebsite.git
   cd MehmetWebsite
   ```

2. **Installation des dépendances :**

   ```bash
   composer install
   ```

3. **Configuration de l'environnement :**
   Copiez le fichier `.env` en `.env.local` et configurez vos accès à la base de données et votre serveur de mail :

   ```bash
   cp .env .env.local
   ```

4. **Création de la base de données et migrations :**

   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Création du compte Administrateur :**
   Comme il n'y a pas de page d'inscription publique, vous devez créer le premier utilisateur manuellement :

   a. Générez un mot de passe sécurisé :

   ```bash
   php bin/console security:hash-password
   ```

   (Saisissez votre mot de passe, copiez le hash généré).

   b. Insérez l'utilisateur en base de données (exemple SQL) :

   ```sql
   INSERT INTO "user" (email, roles, password) 
   VALUES ('votre@email.fr', '["ROLE_ADMIN"]', 'LE_HASH_GENERE_A_L_ETAPE_PRECEDENTE');
   ```

6. **Initialisation des assets :**

   ```bash
   php bin/console assets:install public
   ```

7. **Lancement du serveur :**

   ```bash
   symfony server:start
   # OU via un hôte virtuel Apache
   ```

## 🌐 Configuration Serveur Web (Apache)

Pour déployer le projet sur un serveur Apache, créez un fichier de configuration VirtualHost (ex: `/etc/apache2/sites-available/mehmet.conf`) :

```apache
<VirtualHost *:80>
    ServerName mehmetates.fr
    ServerAlias www.mehmetates.fr

    # Chemin vers le dossier public du projet
    DocumentRoot /var/www/MehmetWebsite/public
    <Directory /var/www/MehmetWebsite/public>
        AllowOverride None
        Order Allow,Deny
        Allow from All

        # Indispensable pour le fonctionnement du routeur Symfony
        FallbackResource /index.php
    </Directory>

    # Configuration des logs
    ErrorLog ${APACHE_LOG_DIR}/mehmet_error.log
    CustomLog ${APACHE_LOG_DIR}/mehmet_access.log combined

    # Optimisation (optionnel)
    <IfModule mod_deflate.c>
        AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
    </IfModule>
</VirtualHost>
```

N'oubliez pas d'activer le site et de recharger Apache :

```bash
sudo a2ensite mehmet.conf
sudo systemctl reload apache2
```

## ⚙️ Configuration Environnement (.env.local)

Les variables suivantes sont essentielles au bon fonctionnement :

* `DATABASE_URL` : Connexion à votre base de données (ex: `postgresql://db_user:db_password@127.0.0.1:5432/db_name`).
* `MAILER_DSN` : Configuration pour l'envoi des emails de réinitialisation (ex: `smtp://user:pass@smtp.example.com:587`).
* `APP_SECRET` : Une clé unique pour la sécurité de votre application.

## ⚙️ Commandes Utiles

### 🎻 Gestion du Projet (Composer)

* `composer install` : Installe les dépendances définies dans le lock.
* `composer update` : Met à jour les bibliothèques vers les dernières versions compatibles.

### 🛠️ Symfony CLI / Console

* `php bin/console make:migration` : Génère un nouveau fichier de migration après un changement d'entité.
* `php bin/console doctrine:migrations:migrate` : Applique les changements à la base de données.
* `php bin/console security:hash-password` : Génère un hash pour créer manuellement un utilisateur en base.
* `php bin/console cache:clear` : Vide le cache de l'application (utile après des changements de config/traduction).

### 🐘 Base de données

* `psql -U admin -d postgres -h 127.0.0.1` : Accès direct à PostgreSQL.

### 🧪 Validation & Qualité

* `php bin/phpunit` : Lance la suite de tests automatisés.

## 🔗 Stack Technique

* **Framework :** Symfony 7
* **Moteur de template :** Twig
* **ORM :** Doctrine (PostgreSQL)
* **Frontend :** Bootstrap 5.3, Bootstrap Icons
* **Contenu :** Markdown (rendu via marked.js)
