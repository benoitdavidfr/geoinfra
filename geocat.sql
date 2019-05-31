/*PhpDoc:
name: geocat.sql
title: geocat.sql - structure et contenu du catalogue racine de la géoinfra
doc: |
  Les éléments du catalogue sont organisés hiérarchiquement au travers du champ parent qui référence l'id de l'élément parent
  sauf pour les éléments de premier niveau pour lesquels parent est null.
  Un élément peut être:
    - un noeud intermédiaire contenant des éléments (node)
    - un schema MySql contenant des tables, le nom du schema est défini par l'URI
    - un schema PgSql (à faire)
    - un WFS (à faire)
journal: |
  31/5/2019:
    création
*/
drop table if exists geocat;
create table if not exists geocat (
  id varchar(255) not null PRIMARY KEY comment "id court",
  parent varchar(255) comment "id du parent ou null",
  type enum('node','schemaMySql','schemaPgSql','wfs') not null comment "type de l'élément",
  title varchar(255) not null comment "titre de l'élément",
  deleted enum('true','false') not null default 'false' comment "'true' ssi l'élément a été supprimé",
  modified datetime not null comment "date et heure de dernière modification de l'élément",
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
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
),
(
  'bdcarto', 'ign', 'wfs',
  'Base IGN BD Carto (en cours)', now(),
  ST_GeomFromText('POLYGON((-180 -90,-180 90,180 90,180 -90,-180 -90))')
);
