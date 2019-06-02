/*PhpDoc:
name: geocat.sql
title: geocat.sql - structure et contenu du catalogue racine de la géoinfra
doc: |
  Les éléments du catalogue sont organisés hiérarchiquement au travers du champ parent qui référence l'id de l'élément parent
  sauf pour les éléments de premier niveau pour lesquels parent est null.
  Un élément peut être:
    - un noeud intermédiaire contenant d'autres éléments (node)
    - un schema MySql contenant des tables, le nom du schema est défini par l'id
    - un schema PgSql (à faire)
    - un service WFS
    - un sous-catalogue écrit par un fichier Yaml (nodeInYaml)

  Questions:
    - pourquoi stocker ces infos dans MySql ?
    - pourquoi ne pas les stocker dans un fichier Yaml ?
    - comment la mettre à jour ?
journal: |
  1/6/2019:
    ajout de tile
  1/6/2019:
    ajout wfsService et nodeInYaml
  31/5/2019:
    création
*/
drop table if exists geocat;
create table if not exists geocat (
  id varchar(255) not null PRIMARY KEY comment "id court",
  parent varchar(255) comment "id du parent ou null",
  type enum('node','schemaMySql','schemaPgSql','tile','wfsService','nodeInYaml') not null comment "type de l'élément",
  title varchar(255) not null comment "titre de l'élément",
  deleted enum('true','false') not null default 'false' comment "'true' ssi l'élément a été supprimé",
  modified datetime not null comment "date et heure de dernière modification de l'élément",
  extra varchar(255) comment "info complémentaire éventuelle",
  geom geometry not null comment "extension géographique de l'élément"
)
comment "catalogue de la géoinfra"
ENGINE=MyISAM DEFAULT CHARSET=utf8;

truncate geocat;
INSERT INTO geocat(id, parent, type, title, modified, geom) VALUES
(
  'ne', null, 'node',
  'Bases Natural Earth', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'ne_110m', 'ne', 'schemaMySql',
  'Base Natural Earth 1/110 M', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'ne_10m', 'ne', 'schemaMySql',
  'Base Natural Earth 1/10 M', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'ign', null, 'node',
  'Bases IGN', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'route500', 'ign', 'schemaMySql',
  'Base IGN Route 500', now(),
  ST_GeomFromText('POLYGON((-5.15 41.33,-5.15 51.09,9.56 51.09,9.56 41.33,-5.15 41.33))')
),
(
  'bdcarto', 'ign', 'nodeInYaml',
  'Base IGN BD Carto', now(),
  ST_GeomFromText('POLYGON((-5.15 41.33,-5.15 51.09,9.56 51.09,9.56 41.33,-5.15 41.33))')
),
(
  'igngp', null, 'node',
  'Ressources de consultation IGN', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'http://igngp.geoapi.fr/tile.php/cartes', 'igngp', 'tile',
  'Cartes', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'http://igngp.geoapi.fr/tile.php/orthos', 'igngp', 'tile',
  'Ortho-images', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
);
INSERT INTO geocat(id, parent, type, title, modified, extra, geom) VALUES
(
  'igngpwfs', 'ign', 'wfsService',
  'Service WFS du Géoportail', now(),
  '{url: https://wxs.ign.fr/3j980d2491vfvr7pigjqdwqw/geoportail/wfs, referer: http://gexplor.fr/}',
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
);
