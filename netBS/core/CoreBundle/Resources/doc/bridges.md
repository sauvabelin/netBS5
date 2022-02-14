# Les bridges

L'ensemble de l'application utilise plusieurs entités pour représenter
l'information, comme les membres, groupes, attributions etc. Il existe
ensuite des fonctionnalités pour plusieurs de ces entités, comme exporter
des listes de groupes, ou générer des étiquettes pour des membres ou des
familles (car ils implémentent l'interface `AdressableInterface`). Afin de
rendre l'accès à ces fonctionnalités plus simple et plus générique, ce
serait pratique de pouvoir directement appliquer une fonctionnalité à un
ensemble donné si il est possible de passer de l'un à l'autre, par exemple
générer des étiquettes sur une liste de groupes car on peut récupérer les
membres d'un groupe, et générer ces étiquettes sur les membres. C'est
précisément ce que font les bridges.


## Fonctionnement

Plusieurs bridges existent par défaut dans l'application. Pour fonctionner,
le netBS les récupère tous et crée ensuite un graphe orienté où chaque
entité est un sommet, et le fait de pouvoir passer de l'une à l'autre devient
un arc. Ce graphe est pondéré par un entier purement théorique représentant
la complexité de l'opération de transformation.

A chaque fois que l'on cherche à passer d'un type de donnée à un autre, on
applique l'algorithme de dijkstra sur le graphe et regardons si une
transformation potentielle existe.


## Utiliser les bridges

L'utilisation des bridges passe par le BridgeManager et s'utilise comme
ceci.

```php
/** @var NetBS\CoreBundle\Service\ListBridgeManager $manager */
$manager = $this->get('netbs.core.bridge_manager');

if($manager->isValidTransformation(Attribution::class, Membre::class)
    $manager->convertItems($attributions, Membre::class);
```


## Créer un bridge

Tous les bridges doivent simplement implémenter l'interface 
`NetBS\CoreBundle\Model\BridgeInterface`. Enregistrez le ensuite comme
service avec le tag `{ name: netbs.bridge }`