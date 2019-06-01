<?php
/*PhpDoc:
name: index.php
title: index.php - accès aux données de la geoinfra
doc: |
  Points d'accès:
  - / -> description du catalogue de la géoinfra
  - /api -> API du catalogue
  - /schema -> schema du catalogue
  - /items -> éléments de premier niveau du catalogue
    - {path}/ne
    - {path}/ign
  - /ne -> catalogue des bases NE
  - /ne/schema
  - /ne/items
    - {path}/ne_110m
    - {path}/ne_10m
  - /ne_110m -> catalogue des collections de NE 1/110 M
  - /ne_110m/schema
  - /ne_110m/items
    - {path}/ne_110m/collections/coastline
  - /ne_110m/collections/coastline -> descr de la FeatureCollection
  - /ne_110m/collections/coastline/schema -> schema de la FeatureCollection
  - /ne_110m/collections/coastline/items -> contenu de la FeatureCollection   
  
  synchro par:
    http://localhost/synchro.php?remote=http://prod.geoapi.fr/   
  
  AFaire:
    - pas que ne ! renommer en gidb ? gimy ?
    - gérer les bases ou les tables inexistantes, renvoyer une erreur 404
    - spécs des bases ?
    - sur Alwaysdata supprimer le prefix bdavid_ dans le nom de la table

journal:
  31/5/2019:
    - restructuration pour exploiter le catalogue défini dans geoinfra.geocat
  30/5/2019:
    - première version
*/
//require_once __DIR__.'/../../phplib/mysql.inc.php';
require_once __DIR__.'/../../geovect/fcoll/database.inc.php';

use Symfony\Component\Yaml\Yaml;

function geoinfra(string $dbParams, string $script_path, string $path_info, callable $errorCallback): array {
  if (1) { // log
    
    file_put_contents(__DIR__.'/log.yaml',Yaml::dump([[
      'date'=> date( DATE_ATOM),
      'path'=> $script_path.$path_info.($_SERVER['QUERY_STRING'] ? "?$_SERVER[QUERY_STRING]"  : ''),
      '$_GET'=> $_GET,
      '$_POST'=> $_POST,
      //'$_SERVER'=> $_SERVER,
    ]]), FILE_APPEND);
  }

  MySql::open($dbParams);

  // racine = descriptif du catalogue des bases + exemples pour tests
  if (!$path_info || ($path_info=='/')) {
    return [
      'type'=> 'http://gi.geoapi.fr/types/geocat',
      'title'=> "Catalogue des données et web-services de la géoinfra",
      'self'=> $script_path,
      'api'=> ['title'=> "documentation de l'API", 'href'=> "$script_path/api"],
      'schema'=> ['title'=> "schema de description des éléments", 'href'=> "$script_path/schema"],
      'items'=> ['title'=> "liste des éléments", 'href'=> "$script_path/items"],
      'examples'=> [
        'ne'=> [
          'title'=> "les bases NE",
          'href'=> "$script_path/ne/items"
        ],
        'ne_110m'=> [
          'title'=> "les collections de la base NE 1/110 M",
          'href'=> "$script_path/ne_110m/items"
        ],
        'coastline'=> [
          'title'=> "la collection coastline de NE 1/110 M",
          'href'=> "$script_path/ne_110m/collections/coastline"
        ],
        'coastline+bbox'=> [
          'title'=> "coastline avec bbox",
          'href'=> "$script_path/ne_110m/collections/coastline/items?bbox=[0,0,180,90]"
        ],
        'ne_110m/admin_0_map_units?su_a3=FXX'=> [
          'title'=> "ne_110m admin_0_map_units / su_a3=FXX",
          'href'=> "$script_path/ne_110m/collections/admin_0_map_units/items?su_a3=FXX"
        ],
        'ne_110m/admin_0_map_units?adm0_a3=FRA'=> [
          'title'=> "ne_110m admin_0_map_units / adm0_a3=FRA",
          'href'=> "$script_path/ne_110m/collections/admin_0_map_units/items?adm0_a3=FRA"
        ],
        'ne_10m/admin_0_map_units?adm0_a3=FRA'=> [
          'title'=> "ne_10m admin_0_map_units / adm0_a3=FRA",
          'href'=> "$script_path/ne_10m/collections/admin_0_map_units/items?adm0_a3=FRA"
        ],
      ],
    ];
  }

  // "/api" - doc API à faire, utilité ?
  if ($path_info == '/api') {
    $errorCallback(501, ['error'=> "API definition to be done"]);
  }

  // caractéristiques réutilisées
  $common = [
    'catalog_schema' => [
      'type'=> [
        'description'=> "type de l'élément",
        'type'=> 'string',
      ],
      'title'=> [
        'description'=> "titre de l'élément",
        'type'=> 'string',
      ],
      'identifier'=> [
        'description'=> "URI de l'élément",
        'type'=> 'string',
      ],
      'deleted'=> [
        'description'=> "vrai ssi l'élément a été supprimé",
        'type'=> 'boolean',
      ],
      'modified'=> [
        'description'=> "date et heure de dernière modification de l'élément",
        'type'=> 'string',
        'format'=> 'date-time',
      ],
    ],
  ];
  $mysqlSchemaNamePrefix = ($_SERVER['HTTP_HOST'] == 'localhost') ? '' : 'bdavid_';
  
  // "/schema" - schema à faire
  if ($path_info == '/schema') {
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'id'=> "$script_path/schema",
      'title'=> "Schema des propriétés des objets du catalogue des bases",
      'type'=> 'object',
      'required'=> array_keys($common['catalog_schema']),
      'properties'=> $common['catalog_schema'],
    ];
  }

  // "/items" : liste des éléments de premier niveau du catalogue
  if ($path_info == '/items') {
    $items = [];
    $query = "select * from ${mysqlSchemaNamePrefix}geoinfra.geocat where parent is null";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $items[] = [
        'properties'=> [
          'type'=> 'http://gi.geoapi.fr/types/geocat',
          'title'=> $tuple['title'],
          'identifier'=> "$script_path/$tuple[id]",
          'deleted'=> $tuple['deleted']<>'false',
          //'modified'=> '2019-05-30T12:00:00+00:00',
          'modified'=> $tuple['modified'],
        ],
      ];
    }
    MySql::close();
    return [
      'type'=> 'FeatureCollection',
      'features'=> $items,
    ];
  }

  // "/{id}" - description soit d'un noeud intermédiaire du catalogue soit d'une base MySql composée de tables
  if (preg_match('!^/([^/]+)$!', $path_info, $matches)) {
    $id = $matches[1];
    $query = "select type, title from ${mysqlSchemaNamePrefix}geoinfra.geocat where id='$id'";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
    }
    MySql::close();
    if ($tuple['type']=='node') {
      return [
        'type'=> 'http://gi.geoapi.fr/types/geocat',
        'title'=> $tuple['title'],
        'self'=> "$script_path/$id",
        'api'=> ['title'=> "documentation de l'API", 'href'=> "$script_path/$id/api"],
        'schema'=> ['title'=> "schema de description des éléments", 'href'=> "$script_path/$id/schema"],
        'items'=> ['title'=> "liste des éléments", 'href'=> "$script_path/$id/items"],
      ];
    }
    elseif ($tuple['type']=='schemaMySql') {
      return [
        'type'=> 'http://gi.geoapi.fr/types/geocat',
        'title'=> $tuple['title'],
        'self'=> "$script_path/$id",
        'api'=> ['title'=> "documentation de l'API", 'href'=> "$script_path/$id/api"],
        'schema'=> ['title'=> "schema de description des collections", 'href'=> "$script_path/$id/schema"],
        'items'=> ['title'=> "liste des collections", 'href'=> "$script_path/$id/items"],
      ];
    }
    else
      $errorCallback(501, ['error'=> "fonctionnalité non implémentée pour type=$tuple[type]"]);
  }

  // "/{base}/api" - API des collections de la base
  if ($path_info == '/api') {
    $errorCallback(501, ['error'=> "API definition to be done"]);
  }

  // "/{base}/schema" - schema des collections de la base
  if (preg_match('!^/([^/]+)/schema$!', $path_info, $matches)) {
    $basename = $matches[1];
    return [
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'id'=> "$script_path/schema/$basename",
      'title'=> "Schema des propriétés des objets du catalogue des collections de la base $basename",
      'type'=> 'object',
      'required'=> array_keys($common['catalog_schema']),
      'properties'=> $common['catalog_schema'],
    ];
  }
  
  // "/{id}/items" - soit liste des sous-elts du catalogue soit liste des collections de la base
  if (preg_match('!^/([^/]+)/items$!', $path_info, $matches)) {
    $id = $matches[1];
    $query = "select type from ${mysqlSchemaNamePrefix}geoinfra.geocat where id='$id'";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
    }
    if ($tuple['type']=='node') { // l'objet courant est un noeud intermédiaire du catalogue
      $query = "select * from ${mysqlSchemaNamePrefix}geoinfra.geocat where parent='$id'";
      foreach(MySql::query($query) as $tuple) {
        //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
        $items[] = [
          'properties'=> [
            'type'=> 'http://gi.geoapi.fr/types/geocat',
            'title'=> $tuple['title'],
            'identifier'=> "$script_path/$tuple[id]",
            'deleted'=> $tuple['deleted']<>'false',
            'modified'=> $tuple['modified'],
          ],
          'geometry'=> [
            'type'=> 'Polygon',
            'coordinates'=> [[[-180,-90],[-180,90],[180,90],[180,-90],[-180,-90]]],
          ],
        ];
      }
    }
    elseif ($tuple['type']=='schemaMySql') { // l'objet courant est un schema MySql
      $items = [];
      $query = "select distinct table_name from information_schema.columns "
        ."where table_schema='${mysqlSchemaNamePrefix}$id' and data_type='geometry'";
      foreach(MySql::query($query) as $tuple) {
        //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
        $items[] = [
          'properties'=> [
            'type'=> 'http://gi.geoapi.fr/types/GeoJSON',
            'title'=> "$tuple[table_name]",
            'identifier'=> "$script_path/$id/collections/$tuple[table_name]",
            'deleted'=> false,
            'modified'=> '2019-05-30T12:00:00+00:00',
          ],
          'geometry'=> [
            'type'=> 'Polygon',
            'coordinates'=> [[[-180,-90],[-180,90],[180,90],[180,-90],[-180,-90]]],
          ],
        ];
      }
    }
    else
      $errorCallback(501, ['error'=> "fonctionnalité non implémentée pour type=$tuple[type]"]);
    
    MySql::close();
    return [
      'type'=> 'FeatureCollection',
      'features'=> $items,
    ];
  }

  // "/{base}/collections/{collname}"
  if (preg_match('!^/([^/]+)/collections/([^/]+)$!', $path_info, $matches)) {
    $basename = $matches[1];
    $collname = $matches[2];
    $table = new \fcoll\Table('', $dbParams, "${mysqlSchemaNamePrefix}$basename.$collname");
    return [
      'type'=> 'http://gi.geoapi.fr/types/GeoJSON',
      'title'=> $collname,
      'self'=> "$script_path/$basename/collections/$collname",
      'schema'=> "$script_path/$basename/collections/$collname/schema",
      'items'=> "$script_path/$basename/collections/$collname/items",
      'bbox'=> $table->bbox([])->asArray(),
    ];
  }

  // conversion de type MySql -> JSON Schema
  function mysql2jsonDataType(string $mysqlDatatype) {
    switch ($mysqlDatatype) {
      case 'varchar': return 'string';
      case 'decimal': return 'number';
      case 'bigint': return 'integer';
      case 'geometry': return ['$ref'=> 'http://json-schema.org/geojson/geometry.json#'];
      default : return $mysqlDatatype;
    }
  }

  // "/{base}/collections/{collname}/schema"
  if (preg_match('!^/([^/]+)/collections/([^/]+)/schema$!', $path_info, $matches)) {
    $basename = $matches[1];
    $collname = $matches[2];
    $query = "select ordinal_position, column_name, data_type from information_schema.columns "
      ."where table_schema='${mysqlSchemaNamePrefix}$basename' and table_name='$collname' and data_type<>'geometry' "
      ."order by ordinal_position";
    $properties = [];
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $properties[$tuple['column_name']] = [
        'type'=> mysql2jsonDataType($tuple['data_type']),
      ];
    }
    MySql::close();
    return [
      '$schema'=> 'http://json-schema.org/draft-04/schema#',
      'id'=> "$script_path/$basename/collections/$collname/schema",
      'title'=> "Schema des propriétés des objets de la collection $basename.$collname déduit du schema de la table dans MySql",
      'type'=> 'object',
      'required'=> array_keys($properties),
      'properties'=> $properties,
    ];
  }

  // "/{base}/{table}/items"
  if (preg_match('!^/([^/]+)/collections/([^/]+)/items$!', $path_info, $matches)) {
    $schemaname = $matches[1];
    $collname = $matches[2];
    $criteria = $_GET;
    if (isset($_POST) && $_POST)
      $criteria = array_merge($criteria, $_POST);
    foreach ($criteria as $name => $value) {
      if ($name == 'bbox')
        $criteria['bbox'] = explode(',', $criteria['bbox']);
    }
    $table = new \fcoll\Table('', $dbParams, "${mysqlSchemaNamePrefix}$schemaname.$collname");
    header('Content-type: application/json');
    echo '{"type":"FeatureCollection",',"\n";
    $query = [
      'schema'=> "$script_path/$schemaname",
      'collection'=> "$script_path/$schemaname/collections/$collname",
    ];
    if ($criteria)
      $query['criteria'] = $criteria;
    //echo '"query":',json_encode($query),",\n";
    echo '"features":[',"\n";
    $first = true;
    foreach ($table->features($criteria) as $feature) {
      echo ($first ? '':",\n"),json_encode($feature);
      $first = false;
    }
    die("\n]}\n");
  }

  return [];
}


if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
  // paramètres de BD / host
  $dbParamsByHost = [
    'localhost'=> 'mysql://root@172.17.0.3/',
    //'localhost'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'bdavid.alwaysdata.net'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'geoapi.fr'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'ne.geoapi.fr'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'gi.geoapi.fr'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    //'bdavid.alwaysdata.net'=> 'pgsql://bdavid@postgresql-bdavid.alwaysdata.net/',
  ];
  //die(json_encode($_SERVER));

  if (null == $dbParams = $dbParamsByHost[$_SERVER['HTTP_HOST']] ?? null) {
    header("HTTP/1.1 500 Internal Server Error");
    header('Content-type: application/json');
    die(json_encode(['error'=> "Erreur aucun serveur de BD paramétré pour le host $_SERVER[HTTP_HOST]"]));
  }
  
  $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
  
    // "/server" pour visualiser la variable $_SERVER
  if ($path_info == '/server') die(json_encode($_SERVER));
  
  if ($result = geoinfra($dbParams, "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]", $path_info, 
      function(int $code, array $message) {
        $headers = [
          404 => "Not Found",
          501 => "Not Implemented",
        ];
        header(sprintf('HTTP/1.1 %d %s', $code, $headers[$code] ?? 'header not defined'));
        die(json_encode($message));
      })) {
    header('Content-type: application/json');
    die(json_encode($result));
  }
}

header("HTTP/1.1 404 Not Found");
header('Content-type: application/json');
die(json_encode([
  '__FILE__'=> __FILE__,
  '$_SERVER'=> $_SERVER,
  '$_GET'=> $_GET,
  '$_POST'=> $_POST,
  'error'=> "No match on path_info='$path_info'",
]));  
