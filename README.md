# NetBS 5

Système de gestion des membres de la Brigade de Sauvabelin, construit avec Symfony 5.4 et Doctrine ORM.

## Installation

```bash
composer install
```

## Environnement de développement (Docker)

```bash
docker compose up
```

Services exposés :
- Application web : http://localhost
- phpMyAdmin : http://localhost:8080
- MariaDB : localhost:3306

## Commandes utiles

```bash
# Vider le cache
php bin/console cache:clear

# Charger les fixtures
php bin/console doctrine:fixtures:load --group=fill

# Lancer les tests
php bin/phpunit
```

## Migrations de base de données

Les migrations se trouvent dans `migrations/` et suivent la convention de nommage `Version<NNNN>_<description>.php`.

```bash
# Voir l'état actuel des migrations
php bin/console doctrine:migrations:status

# Appliquer les migrations en attente
php bin/console doctrine:migrations:migrate

# Générer une migration après modification d'une entité
php bin/console doctrine:migrations:diff
```

Après avoir généré une migration avec `diff`, pensez à renommer le fichier et la classe selon la convention (`Version<NNNN>_<description>`) avant de commiter.
