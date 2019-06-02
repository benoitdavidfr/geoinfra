# géoinfra - une infrastructure de données et de services géographiques
2 juin 2019 (en cours)

### introduction

L'infrastructure de données et de services géographiques définie par la
[directive Inspire](https://eur-lex.europa.eu/eli/dir/2007/2/oj?locale=fr) se révèle très complexe à mettre en oeuvre
et difficile à utiliser.
L'objectif de ce document est de proposer une **nouvelle infrastructure plus simple à utiliser** et prenant mieux en compte
les [recommandations W3C/OGC pour la publication de données géographiques sur le web](https://w3c.github.io/sdw/bp/).

Pour illustrer cette spécification un prototype a été développé et sera progressivement complété ;
il est référencé dans les exemples ci-dessous.

## spécifications de l'infrastructure

Dans cette géoinfra :

  - les données géographiques sont organisées en
      - données vecteur structurées au [format GeoJSON](https://tools.ietf.org/html/rfc7946)
      - données maillées structurées au [format NetCDF](https://fr.wikipedia.org/wiki/NetCDF)
      - données de consultation sous la forme d'images PNG ou JPEG géoréférencées
      - catalogues de données référençant et organisant les 3 éléments ci-dessus
  - les données sont exposées sur le web au travers de 5 types de web-services:
      - les [web-services GeoJSON](#GeoJSON)
      - les web-services de données maillées (à spécifier)
      - les [web-services de consultation tuilés](#tile) (standard de facto XYZ)
      - les services [OGC-WMS](#wms)
      - les [catalogues](#geocat) exposant une toile des web-services et des cartes
  - des [cartes](#map) peuvent être définies dans la géoinfra afin de faciliter la consultation des données,
  - des web-services de traitement de données peuvent aussi être référencés, tels que:
      - service de géocodage
      - service de transformation de coordonnées,
      - ...

### web-service GeoJSON<a id='GeoJSON'></a>

- expose une FeatureCollection
- est identifié par un URI de base (basepath)
- définit les points d'accès (endpoints) suivants:
  - / renvoie la description du service et les métadonnées de la collection
      - ce document a pour type `http://gi.geoapi.fr/types/GeoJSON`
  - `/api` renvoie la définition de l'API d'accès à la collection
  - `/schema` renvoie le schéma des propriétés des objets de la collection
  - `/items` renvoie les objets (Feature) de la collection
  - `/items?{criteria}` renvoie une sélection de la collection définie par `{criteria}`
  - `/items/{fid}` renvoie l'objet {fid} de la collection
  - `/count` renvoie le nombre d'objets de la collection
  - `/count?{criteria}` renvoie le nombre d'objets de la sélection de la collection définie par `{criteria}`

`{citeria}` est un ensemble de critères élémentaires de la forme:

  - `{property}={value}` spécifie que la propriété {property} doit contenir la valeur {value},
    exemple: `su_a3=FXX`
  - `{property}(={v1},{v2},...,{vn}` spécifie que la propriété {property} doit contenir une des valeurs {vi},
    exemple: nature(=Limite%20c%C3%B4ti%C3%A8re,Fronti%C3%A8re%20internationale
  - `bbox={lonmin},{latmin},{lonmax},{latmax}` spécifie que la géométrie de l'objet doit intersecter la fenêtre définie
    par les intervalles de longitude et de latitude exprimées en degrés.

#### exemples

- <http://gi.geoapi.fr/ne_110m/collections/coastline> - description de
  la collection des [limites côtières mondiales définies par la base Natural Earth 1/110 M](https://www.naturalearthdata.com/downloads/110m-physical-vectors/110m-coastline/)
- [http://gi.geoapi.fr/ne_110m/collections/coastline/items](http://gi.geoapi.fr/ne_110m/collections/coastline/items) - le contenu de cette collection
- <http://gi.geoapi.fr/ne_110m/collections/coastline/items?bbox=-6,41,10,51.1> - les limites intersectant le rectangle
  dont les longitudes sont comprises entre 6° Ouest et 10° Est et les latitudes entre 41° et 51.1 ° Nord
- <http://gi.geoapi.fr/ne_110m/collections/admin_0_map_units/items?su_a3=FXX> - l'unité administrative de Natural Earth 1/110M
  correspondant au code 'FXX'
- <http://gi.geoapi.fr/route500/collections/limite_administrative/items?nature(=Limite%20c%C3%B4ti%C3%A8re,Fronti%C3%A8re%20internationale> - les limites côtières et les frontières internationales de Route 500

### web-service de consultation tuilé<a id='tile'></a>

- expose une couche correspondant à une image géoréférencée PNG ou JPG
- est identifié par un URI de base (basepath)
- définit les points d'accès (endpoints) suivants:
  - / renvoie la description du service et de la couche
      - ce document a pour type http://gi.geoapi.fr/types/tile
  - `/{z}/{x}/{y}.(png|jpg)` renvoie l'image correspondant à la tuile
      - documenté dans <https://wiki.openstreetmap.org/wiki/Slippy_map_tilenames>

On reprend ici un format très utilisé sur le web et popularisé par OSM
en lui ajoutant cependant l'utilisation d'une URI correspondant à la description du service et les métadonnées de la couche.

#### exemples

- <http://igngp.geoapi.fr/tile.php/cartes> - cartes exposées sur le Géportail
- <http://igngp.geoapi.fr/tile.php/cartes/16/32945/22940.jpg> - une tuile

### web-service de consultation WMS<a id='wms'></a>

Les services OGC-WMS sont très utilisés et d'utilisation assez simple.
De plus, ils sont nécessaires pour réaliser des cartes dans une projection autre que WebMercator.
Ils peuvent donc être intégrés dans géoinfra en utilisant comme URI l'URL du service sans paramètre

### web-service de catalogue<a id='geocat'></a>
- est implémenté comme un web-service GeoJSON particulier
  - avec un type spécifique http://gi.geoapi.fr/types/geocat
- expose comme objets (Feature)
  - des web-services GeoJSON
  - des web-services de données maillées
  - des web-services de consultation tuilés
  - des services WMS (type=http://www.opengis.net/wms)
  - des cartes
  - des web-services de catalogue
  - des web-services de traitement définis par leur API
- les propriétés des objets sont:
  - identifier: URI de l'objet
  - title: titre en clair
  - type: type de l'objet
  - deleted: boolean
  - modified: date de dernière modification
- la géométrie des objets définit l'extension du service ou de la carte
- les objets détruits sont conservés avec deleted=true permettant ainsi de retrouver facilement les évolutions entre 2 dates

#### exemples

- <http://gi.geoapi.fr/ne_110m/items> - les collections de la base Natural Earth 1/110 M,
  chacune définie comme web-service GeoJSON par leur URI,
- <http://gi.geoapi.fr/ne_110m> - description de la base Natural Earth 1/110 M comme catalogue de web-services GeoJSON,
- <http://gi.geoapi.fr/> - racine du catalogue de démonstration de la géoinfra.

### carte<a id='map'></a>
est définie par:

  - des métadonnées (titre, ...)
  - son type http://gi.geoapi.fr/types/map
  - une extension géographique enregistrée dans la géométrie de l'objet
  - la projection de l'affichage définie par un code EPSG
  - une liste de couches, chacune soit
    - un web-service de consultation tuilé ou une couche d'un service WMS
        - associée à un copyright
    - un web-service GeoJSON associé à
      - d'éventuels critères de sélection
      - un éventuel style de représentation
