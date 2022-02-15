# NetBS

Bienvenue sur le système de gestion d'organisation de la brigade de Sauvabelin. Cette application a pour but de rendre
plus facile la gestion des membres dans tout type de structure organisée de manière hierarchique.

## Important
Initialement le projet a été développé avec Symfony 3.4 comme dernière version. Un effort pour le passer
en version 5 a été réalisé (au prix de nombreuses heures et soirées), la codebase a donc subit quelques
mises à jour mais le code ne reflête absolument pas les dernières bonnes pratiques apportées par les versions
successives de Symfony, l'effort s'étant concentré à supporter Symfony 5 par manque de temps.

La version originale est disponible ici: https://github.com/sauvabelin/netBS

## Ce qui est cool

- Prêt à l'usage directement
- Hautement customizable pour s'adapter au plus de besoins possibles tout en restant performant et agréable à utiliser
- Exportation en Excel, PDF et génération d'étiquettes
- Génération de listes dynamiques de membres par utilisateur

## Concrètement

Le projet est codé en PHP, basé sur Symfony et utilisant Doctrine ORM pour l'abstraction base de données.
Nous avons séparé les fonctionnalités dans 4 bundles différents

- NetBSCoreBundle, qui fournis des fonctionnalités de base à l'application
- NetBSFichierBundle qui s'occupe de la gestion des membres, groupes etc. à proprement parlé
- NetBSSecureBundle qui offre une couche de sécurité hautement customizable pour accéder à l'application
- NetBSListBundle qui s'occupe de générer et afficher des listes en tout genre un peu partout

## Qu'est-ce qui est en développement
- Les envois massifs d'email automatiques, avec la possibilité pour les utilisateurs de s'abonner/désabonner de certaines listes de publication

## Comment ça marche
Initialement, le projet était développé pour gérer l'effectif d'un groupe scout, mais peut facilement être adapté pour d'autres usages.
L'application est construite autour de la notion de groupe, entité qui réunit des membres autour de quelque chose.
Dans le cas des scouts, c'est toute la structure de l'organisation qui est représentée sous forme de groupes, avec les
patrouilles, troupes, sizaines, branches etc. Chaque groupe possède des propriétés qui lui donnent ses particularités.

Les membres font ensuite partie de ces groupes au travers d'attributions, qui leur associent une date de début et de fin
potentielle, ainsi qu'une fonction qu'ils exercent dans ce groupe. Les fonctions leurs donnent ensuite les autorisations
d'exécuter certaines actions ou d'accéder à certaines parties de l'application par exemple.

## installation
- faites un git clone de ce repository
- Configurez votre fichier d'environnement `.env.local`
- Ouvrez un terminal à l'intérieur du dossier ainsi cloné, et utilisez composer pour faire un composer install
- Une fois l'installation terminée, assurez-vous que les paramètres de base de donnée soient corrects.
- Exécutez `php bin/console doctrine:fixtures:load --group=fill` pour charger des données d'exemple
- Générez les clés pour les JWT:
    - créez le dossier des clés `mkdir`
    - Générez les clés `openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096`
    - Puis `openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout`
    - Et mettez la clé utilisée dans votre fichier `.env.local`
- Connectez vous avec `admin` et `password`
