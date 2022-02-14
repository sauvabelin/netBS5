# Exportation

Le NetBS offre plusieurs manières d'exporter de l'information, sous forme
de listes principalement, au format PDF et CSV par défaut.

## Créer un exporter

Il est tout à fait possible de créer de nouveaux exporters permettant 
d'exporter de l'information. Tous les exporters doivent implémenter l'interface
`NetBS\CoreBundle\Model\ExporterInterface`. Cependant, vous pouvez également utiliser une
des classes abstraites suivantes:
- `NetBS\CoreBundle\Exporter\CSVExporter` qui défini toute la base d'exportation en CSV et ne vous
demande plus que de configurer les colonnes
- `NetBS\CoreBundle\Exporter\PDFExporter` qui vous met directement à disposition
des instances de twig et snappy pour générer vos documents. Vous n'avez plus qu'à
générer un template twig.

Vous devez ensuite l'enregistrer comme un service avec le tag `netbs.exporter`.

Celui-ci est ensuite disponible dans chaque liste où l'on affiche des éléments
compatibles avec votre exporter (au sens des transformations de bridges, voir les bridges).

### Les exporters configurables

Il est possible de rendre votre exporter configurable par l'utilisateur, comme
par exemple les étiquettes où l'on peut changer plusieurs paramètres de rendu.
Pour cela, votre exporter doit implémenter l'interface `NetBS\CoreBundle\Model\ConfigurableExporterInterface`.

## Les previewers

Lorsque vous configurez votre exportation, vous avez parfois la possibilité d'avoir
un aperçu de celle-ci avant de la télécharger. Ceci se fait au moyen des previewers.

### Créer un previewer

Pour créer un previewer, il suffit de créer une classe implémentant l'interface
`NetBS\CoreBundle\Model\PreviewerInterface`, puis enregistrez là comme service
avec le tag `netbs.previewer`.

Vous pouvez ensuite l'utiliser dans les exporters configurables implémentant
l'interface `NetBS\CoreBundle\Model\ConfigurableExporterInterface` dans la méthode
`getPreviewer()` qui doit retourner la classe du previewer utilisé, ou null si aucun
aperçu ne sera disponible.