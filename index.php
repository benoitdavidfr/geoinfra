<?php
/*PhpDoc:
name: index.php
title: index.php - accès aux données de Natural Earth stockées dans MySql selon les protocoles geoinfra
doc: |
  Points d'accès:
  - / -> description du catalogue des bases
  - /schema -> schema du catalogue
  - /items -> contenu du catalogue
    - {path}/ne_110m
    - {path}/ne_10m
  - /ne_110m -> catalogue des collections
  - /ne_110m/schema
  - /ne_110m/items
    - {path}/ne_110m/collections/coastline
  - /ne_110m/collections/coastline -> descr de la FeatureCollection
  - /ne_110m/collections/coastline/schema -> schema de la FeatureCollection
  - /ne_110m/collections/coastline/items -> contenu de la FeatureCollection   
  
  synchro par:
    http://localhost/synchro.php?remote=http://prod.geoapi.fr/   
journal:
  30/5/2019:
    - première version
*/
//require_once __DIR__.'/../../phplib/mysql.inc.php';
require_once __DIR__.'/../../geovect/fcoll/database.inc.php';

use Symfony\Component\Yaml\Yaml;

function main(string $dbParams, string $path, string $path_info) {
  if (1) { // log
    file_put_contents(__DIR__.'/log.yaml',Yaml::dump([[
      'date'=> date( DATE_ATOM),
      'path'=> $path.($_SERVER['QUERY_STRING'] ? "?$_SERVER[QUERY_STRING]"  : ''),
      //'$_SERVER'=> $_SERVER,
    ]]), FILE_APPEND);
  }

  MySql::open($dbParams);

  header('Content-type: application/json');

  //$path = "http://$_SERVER[HTTP_HOST]".dirname($_SERVER['SCRIPT_NAME']);
  //die($path);

  // racine = descriptif du catalogue des bases + exemples pour tests
  if (!$path_info || ($path_info=='/')) {
    echo json_encode([
      'type'=> 'http://id.georef.eu/geoinfra/geocat',
      'title'=> "Catalogue des bases de données Natural Earth",
      'self'=> $path,
      'api'=> ['title'=> "documentation de l'API", 'href'=> "$path/api"],
      'schema'=> ['title'=> "schema de description des bases", 'href'=> "$path/schema"],
      'items'=> ['title'=> "liste des bases", 'href'=> "$path/items"],
      'examples'=> [
        'coastline'=> [
          'title'=> "coastline",
          'href'=> "$path/ne_110m/collections/coastline"],
        'coastline+bbox'=> [
          'title'=> "coastline avec bbox",
          'href'=> "$path/ne_110m/collections/coastline/items?bbox=[0,0,180,90]"],
        'ne_110m/admin_0_map_units?su_a3=FXX'=> [
          'title'=> "ne_110m admin_0_map_units / su_a3=FXX",
          'href'=> "$path/ne_110m/collections/admin_0_map_units/items?su_a3=FXX"
        ],
        'ne_110m/admin_0_map_units?adm0_a3=FRA'=> [
          'title'=> "ne_110m admin_0_map_units / adm0_a3=FRA",
          'href'=> "$path/ne_110m/collections/admin_0_map_units/items?adm0_a3=FRA"
        ],
        'ne_10m/admin_0_map_units?adm0_a3=FRA'=> [
          'title'=> "ne_10m admin_0_map_units / adm0_a3=FRA",
          'href'=> "$path/ne_10m/collections/admin_0_map_units/items?adm0_a3=FRA"
        ],
      ],
    ]);
    die();
  }

  // "/api" - doc API à faire, utilité ?
  if ($path_info == '/api') {
    header("HTTP/1.1 501 Not Implemented");
    die(json_encode(['error'=> "API definition to be done"]));
  }

  // "/schema" - schema à faire
  if ($path_info == '/schema') {
    $properties = [
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
    ];
    die(json_encode([
      '$schema'=> 'http://json-schema.org/draft-07/schema#',
      'id'=> "$path/schema",
      'title'=> "Schema des propriétés des objets du catalogue des bases",
      'type'=> 'object',
      'required'=> array_keys($properties),
      'properties'=> $properties,
    ]));
  }

  // "/items" : liste des bases
  if ($path_info == '/items') {
    $items = [];
    $query = "select distinct table_schema from information_schema.columns where data_type='geometry'";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $items[] = [
        'properties'=> [
          'type'=> 'http://id.georef.eu/geoinfra/geocat',
          'title'=> $tuple['table_schema'],
          'identifier'=> "$path/$tuple[table_schema]",
          'deleted'=> false,
          'modified'=> '2019-05-30T12:00:00+00:00',
        ],
      ];
    }
    MySql::close();
    echo json_encode([
      'type'=> 'FeatureCollection',
      'features'=> $items,
    ]);
    die();
  }

  // "/{base}" - base = catalogue des tables
  if (preg_match('!^/([^/]+)$!', $path_info, $matches)) {
    $basename = $matches[1];
    echo json_encode([
      'type'=> 'http://id.georef.eu/geoinfra/geocat',
      'title'=> "Catalogue des collections de $basename",
      'self'=> "$path/$basename",
      'api'=> ['title'=> "documentation de l'API", 'href'=> "$path/$basename/api"],
      'schema'=> ['title'=> "schema de description des collections", 'href'=> "$path/$basename/schema"],
      'items'=> ['title'=> "liste des collections", 'href'=> "$path/$basename/items"],
    ]);
    die();
  }

  // "/{base}/items" - liste des collections d'une base
  if (preg_match('!^/([^/]+)/items$!', $path_info, $matches)) {
    $basename = $matches[1];
    $collections = [];
    $query = "select distinct table_name from information_schema.columns "
      ."where table_schema='$basename' and data_type='geometry'";
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $collections[] = [
        'properties'=> [
          'type'=> 'http://id.georef.eu/geoinfra/GeoJSON',
          'title'=> "$tuple[table_name]",
          'identifier'=> "$path/$basename/collections/$tuple[table_name]",
          'deleted'=> false,
          'modified'=> '2019-05-30T12:00:00+00:00',
        ],
        'geometry'=> [
          'type'=> 'Polygon',
          'coordinates'=> [[[-180,-90],[-180,90],[180,90],[180,-90],[-180,-90]]],
        ]
      ];
    }
    MySql::close();
    die(json_encode([
      'type'=> 'FeatureCollection',
      'features'=> $collections,
    ]));
  }

  // "/{base}/collections/{collname}"
  if (preg_match('!^/([^/]+)/collections/([^/]+)$!', $path_info, $matches)) {
    $basename = $matches[1];
    $collname = $matches[2];
    echo json_encode([
      'title'=> $collname,
      'self'=> "$path/$basename/collections/$collname",
      'schema'=> "$path/$basename/collections/$collname/schema",
      'items'=> "$path/$basename/collections/$collname/items",
    ]);
    die();
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

  // "/{schema}/collections/{collname}/schema"
  if (preg_match('!^/([^/]+)/collections/([^/]+)/schema$!', $path_info, $matches)) {
    $basename = $matches[1];
    $collname = $matches[2];
    $query = "select ordinal_position, column_name, data_type from information_schema.columns "
      ."where table_schema='$basename' and table_name='$collname' and data_type<>'geometry' "
      ."order by ordinal_position";
    $properties = [];
    foreach(MySql::query($query) as $tuple) {
      //echo "<pre>tuple="; print_r($tuple); echo "</pre>\n";
      $properties[$tuple['column_name']] = [
        'type'=> mysql2jsonDataType($tuple['data_type']),
      ];
    }
    MySql::close();
    die(json_encode([
      '$schema'=> 'http://json-schema.org/draft-04/schema#',
      'id'=> "$path/$basename/collections/$collname/schema",
      'title'=> "Schema des propriétés des objets de la collection $basename.$collname déduit du schema de la table dans MySql",
      'type'=> 'object',
      'required'=> array_keys($properties),
      'properties'=> $properties,
    ]));
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
        $criteria['bbox'] = json_decode($criteria['bbox']);
    }
    $table = new \fcoll\Table('', $dbParams, "$schemaname.$collname");
    echo '{"type":"FeatureCollection",',"\n";
    $query = [
      'schema'=> "$path/$schemaname",
      'collection'=> "$path/$schemaname/collections/$collname",
    ];
    if ($criteria)
      $query['criteria'] = $criteria;
    echo '"query":',json_encode($query),",\n";
    echo '"features":[',"\n";
    $first = true;
    foreach ($table->features($criteria) as $feature) {
      echo ($first ? '':",\n"),json_encode($feature);
      $first = false;
    }
    die("\n]}\n");
  }
}

if (__FILE__ == $_SERVER['SCRIPT_FILENAME']) {
  // paramètres de BD / host
  $dbParamsByHost = [
    'localhost'=> 'mysql://root@172.17.0.3/',
    //'localhost'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'bdavid.alwaysdata.net'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'geoapi.fr'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    'ne.geoapi.fr'=> 'mysql://bdavid@mysql-bdavid.alwaysdata.net/',
    //'bdavid.alwaysdata.net'=> 'pgsql://bdavid@postgresql-bdavid.alwaysdata.net/',
  ];
  //die(json_encode($_SERVER));

  if (null == $dbParams = $dbParamsByHost[$_SERVER['HTTP_HOST']] ?? null) {
    header("HTTP/1.1 500 Internal Server Error");
    header('Content-type: application/json');
    die(json_encode(['error'=> "Erreur aucun serveur de BD paramétré pour le host $_SERVER[HTTP_HOST]"]));
  }
  
  // "/server" pour visualiser la variable $_SERVER
  if (isset($_SERVER['PATH_INFO']) && ($_SERVER['PATH_INFO'] == '/server'))
    die(json_encode($_SERVER));
  
  main($dbParams, "http://$_SERVER[HTTP_HOST]$_SERVER[SCRIPT_NAME]", isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '');
}

header("HTTP/1.1 404 Not Found");
header('Content-type: application/json');
die(json_encode([
  '__FILE__'=> __FILE__,
  '$_SERVER'=> $_SERVER,
  '$_GET'=> $_GET,
  '$_POST'=> $_POST,
  'error'=> "No match",
]));  
