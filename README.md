# MehmetWebSite

TODO README

## 📝 Description

Ce projet est un site web dynamique faisant office de **CV numérique et de portfolio**. Son objectif est de centraliser et de mettre en valeur mes différentes réalisations (projets académiques réalisés au cours de ma formation, projets personnels et expériences professionnelles).

### 🌐 Fonctionnalités Publiques
* **Page d'accueil :** Présentation personnelle détaillée et mise à disposition du CV pour consultation et téléchargement.
* **Page "Travaux" :** Galerie et liste de l'ensemble des projets. Chaque projet possède sa propre page dédiée comprenant :
    * Un titre et une description bilingues (**Français / Anglais**).
    * Des liens externes vers les dépôts de code (GitHub/GitLab) ou les versions de production.
    * Une galerie d'images d'illustration.

### 🔐 Interface Administration (Back-Office)
Un espace sécurisé (authentification par e-mail et mot de passe) permet de gérer l'intégralité du site :
* **CRUD Projets :** Ajout, modification et suppression des fiches projets.
* **Éditeur Markdown :** Rédaction des descriptions de projets via un éditeur de texte au format Markdown pour une mise en page riche.
* **Gestion du CV :** Possibilité de téléverser et de remplacer directement le fichier PDF du CV depuis l'interface.
* **Réinitialisation de mot de passe :** Système de récupération par e-mail. *Note : Cette fonctionnalité dépend des variables SMTP du fichier `.env` ; elle se désactive automatiquement si ces dernières ne sont pas configurées.*

### ✨ Fonctionnalités Bonus
* **Traduction :** Support complet des langues Française et Anglaise.
* **Accessibilité :** Bascule d'affichage entre Mode Sombre (Dark Mode) et Mode Clair (Light Mode).

## 🛠 Prérequis

TODO

##  🚀 Installation

TODO

## ⚙️ Configuration Environnement (.env)

TODO

## ⚙️ Commandes utiles

### 🎻 Composer

```bash
#Composer
composer install            # Installer les dépendances
composer update             # Mettre à jour les dépendances
composer require package    # Ajouter une dépendance
composer remove package     # Supprimer une dépendance
sudo composer self-update   # Mettre à jour Composer
composer validate           # Vérifier le fichier composer.json
```

### 🛠️ Symfony

```bash
#Symfonie
symfony server:start    # Démarrer le serveur de développement
symfony console about   # Afficher les infos Symfony

# Debug
symfony console debug:router            #Afficher toute les routes
php bin/console debug:router
symfony console debug:router route_name # Détails d'une route spécifique
symfony console debug:config # Afficher la configuration
symfony console debug:dotenv # Afficher les variables .env chargées
symfony console debug:container # Lister tous les services
php bin/console security:hash-password # Génere un mot de passe utilisable dans la base de donnée (utile pour faire un nouveau utilisateur)
```

### 🐘 PostgreSQL

```bash
#Postgres
psql -U admin -d postgres -h 127.0.0.1
sudo -u postgres psql
```

### 🎛️ Apache

```bash
#Apache
sudo apache2ctl -S            # Affiche les hôtes virtuels actifs 
ls /etc/apache2/sites-enabled/  # Liste les sites activés

#Gestion des sites
sudo a2dissite site.conf        # Désactiver un site
sudo systemctl reload apache2   # Activer un site
sudo a2ensite site.conf         # Recharger la configuration

sudo a2enmod rewrite # nécessaire pour le fonctionnement Symfony
```

### 🧪 Tests

```bash
#Lancer les tests unitaires
./vendor/bin/phpunit
php bin/phpunit
```

## 🔗 Ressources utiles

- [Documentation PHP](https://www.php.net/docs.php)
- [Documentation Symfony](https://symfony.com/doc/current/index.html)
- [Best Practices Symfony](https://symfony.com/doc/current/best_practices.html)  
- [Conventions Symfony](https://symfony.com/doc/current/contributing/code/standards.html)
- [Documentation Twig](https://twig.symfony.com/doc/3.x/)
- [Documentation Bootstrap](https://getbootstrap.com/docs/4.1/getting-started/introduction/)
- [Documentation PostgreSQL](https://www.postgresql.org/docs/)
- [Documentation Composer](https://getcomposer.org/doc/)
- [Documentation Apache](https://httpd.apache.org/docs/)