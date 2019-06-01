<?php
//die("MODIF en cours");
/*PhpDoc:
name: wfsjson.inc.php
title: wfsjson.inc.php - document correspondant à un serveur WFS capable de générer du GeoJSON
functions:
doc: <a href='/yamldoc/?action=version&name=wfsjson.inc.php'>doc intégrée en Php</a>
*/
{
$phpDocs[basename(__FILE__)]['file'] = <<<EOT
name: wfsjson.inc.php
title: wfsjson.inc.php - document correspondant à un serveur WFS capable de générer du GeoJSON
doc: |
  La classe WfsServerJson expose différentes méthodes utilisant un serveur WFS capable de générer du GeoJSON.  
journal: |
  1/6/2019:
    - fork et adaptation à geoinfra
  4/11/2018:
    - réécriture de WfsServerJson::printAllFeatures() pour être plus stable
  3/11/2018:
    - prise en compte du defaultCrs du typename dans getFeature()
  9/10/2018:
    - création à partir de wfsserver.inc.php
    - ajout de la classe WfsServerJsonAugmented permettant de modifier les feature à la volée
EOT;
}

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

abstract class WfsServer {
  static $log = __DIR__.'/wfsserver.log.yaml'; // nom du fichier de log ou false pour pas de log
  static $capCache = __DIR__.'/wfscapcache'; // nom du répertoire dans lequel sont stockés les fichiers XML
                                           // de capacités ainsi que les DescribeFeatureType en json
  protected $_c; // contient les champs
  
  // crée un nouveau doc, $yaml est le contenu Yaml externe issu de l'analyseur Yaml
  function __construct($yaml) {
    $this->_c = $yaml;
    if (!$this->wfsUrl)
      throw new Exception("Erreur dans WfsServer::__construct(): champ wfsUrl obligatoire");
  }

  // lit les champs
  function __get(string $name) { return isset($this->_c[$name]) ? $this->_c[$name] : null; }
  
  // renvoi l'URL de la requête
  function url(array $params): string {
    if (self::$log) { // log
      file_put_contents(
          self::$log,
          Yaml::dump([
            'date'=> date(DateTime::ATOM),
            'appel'=> 'WfsServer::url',
            'params'=> $params,
          ]),
          FILE_APPEND
      );
    }
    $url = $this->wfsUrl;
    $url .= ((strpos($url, '?') === false) ? '?' : '&').'SERVICE=WFS';
    foreach($params as $key => $value)
      $url .= "&$key=$value";
    if (self::$log) { // log
      file_put_contents(self::$log, Yaml::dump(['url'=> $url]), FILE_APPEND);
    }
    return $url;
  }
  
  // envoi une requête et récupère la réponse sous la forme d'un texte
  function query(array $params): string {
    $url = $this->url($params);
    $context = null;
    if ($this->wfsOptions && isset($this->wfsOptions['referer'])) {
      $referer = $this->wfsOptions['referer'];
      if (self::$log) { // log
        file_put_contents(
            self::$log,
            Yaml::dump([
              'appel'=> 'WfsServer::query',
              'referer'=> $referer,
            ]),
            FILE_APPEND
        );
      }
      $context = stream_context_create(['http'=> ['header'=> "referer: $referer\r\n"]]);
    }
    if (($result = @file_get_contents($url, false, $context)) === false) {
      if (isset($http_response_header)) {
        echo "http_response_header="; var_dump($http_response_header);
      }
      throw new Exception("Erreur dans WfsServer::query() : sur url=$url");
    }
    //die($result);
    //if (substr($result, 0, 17) == '<ExceptionReport>') {
    if (preg_match('!ExceptionReport!', $result)) {
      if (preg_match('!<ExceptionReport><[^>]*>([^<]*)!', $result, $matches)) {
        throw new Exception ("Erreur dans WfsServer::query() : $matches[1]");
      }
      if (preg_match('!<ows:ExceptionText>([^<]*)!', $result, $matches)) {
        throw new Exception ("Erreur dans WfsServer::query() : $matches[1]");
      }
      echo $result;
      throw new Exception("Erreur dans WfsServer::query() : message d'erreur non détecté");
    }
    return $result;
  }
  
};

class WfsServerJson extends WfsServer {
  private $wfsUrl;
  
  function describeFeatureType(string $typeName): array {
    $filepath = self::$capCache.'/wfs'.md5($this->wfsUrl."/$typeName").'-ft.json';
    if (is_file($filepath)) {
      $featureType = file_get_contents($filepath);
    }
    else {
      $featureType = $this->query([
        'VERSION'=> '2.0.0',
        'REQUEST'=> 'DescribeFeatureType',
        'OUTPUTFORMAT'=> 'application/json',
        //'OUTPUTFORMAT'=> rawurlencode('text/xml; subtype=gml/3.2'),
        'TYPENAME'=> $typeName,
      ]);
      file_put_contents($filepath, $featureType);
    }
    $featureType = json_decode($featureType, true);
    return $featureType;
  }
  
  // nom de la propriété géométrique du featureType
  function geomPropertyName(string $typeName): ?string {
    $featureType = $this->describeFeatureType($typeName);
    //var_dump($featureType);
    foreach($featureType['featureTypes'] as $featureType) {
      foreach ($featureType['properties'] as $property) {
        if (preg_match('!^gml:!', $property['type']))
          return $property['name'];
      }
    }
    return null;
  }
    
  // retourne le nbre d'objets correspondant au résultat de la requête
  function getNumberMatched(string $typename, array $bbox=[], string $where=''): int {
    $geomPropertyName = $this->geomPropertyName($typename);
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'RESULTTYPE'=> 'hits',
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktCrs($bbox, $this->defaultCrs($typename));
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    $result = $this->query($request);
    if (!preg_match('! numberMatched="(\d+)" !', $result, $matches)) {
      //echo "result=",$result,"\n";
      throw new Exception("Erreur dans WfsServerJson::getNumberMatched() : no match on result $result");
    }
    return (int)$matches[1];
  }
  
  // retourne le résultat de la requête en GeoJSON
  function getFeature(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): string {
    $geomPropertyName = $this->geomPropertyName($typename);
    $request = [
      'VERSION'=> '2.0.0',
      'REQUEST'=> 'GetFeature',
      'TYPENAMES'=> $typename,
      'OUTPUTFORMAT'=> 'application/json',
      'SRSNAME'=> 'CRS:84', // système de coordonnées nécessaire pour du GeoJSON
      'COUNT'=> $count,
      'STARTINDEX'=> $startindex,
    ];
    $cql_filter = '';
    if ($bbox) {
      $bboxwkt = self::bboxWktCrs($bbox, $this->defaultCrs($typename));
      $cql_filter = "Intersects($geomPropertyName,$bboxwkt)";
    }
    if ($where) {
      $where = utf8_decode($where); // expérimentalement les requêtes doivent être encodées en ISO-8859-1
      $cql_filter .= ($cql_filter ? ' AND ':'').$where;
    }
    if ($cql_filter)
      $request['CQL_FILTER'] = urlencode($cql_filter);
    return $this->query($request);
  }
  
  // retourne le résultat de la requête en GeoJSON encodé en array Php
  function getFeatureAsArray(string $typename, array $bbox=[], int $zoom=-1, string $where='', int $count=100, int $startindex=0): array {
    $result = $this->getFeature($typename, $bbox, $zoom, $where, $count, $startindex);
    return json_decode($result, true);
  }
  
  // affiche le résultat de la requête en GeoJSON
  function printAllFeatures(string $typename, array $bbox=[], int $zoom=-1, string $where=''): void {
    //echo "WfsServerJson::printAllFeatures()<br>\n";
    $numberMatched = $this->getNumberMatched($typename, $bbox, $where);
    if ($numberMatched <= 100) {
      echo $this->getFeature($typename, $bbox, $zoom, $where);
      return;
    }
    //$numberMatched = 12; POUR TESTS
    echo '{"type":"FeatureCollection","numberMatched":'.$numberMatched.',"features":[',"\n";
    $startindex = 0;
    $count = 100;
    while ($startindex < $numberMatched) {
      $fc = $this->getFeature($typename, $bbox, $zoom, $where, $count, $startindex);
      $fc = json_decode($fc, true);
      foreach ($fc['features'] as $nof => $feature) {
        if (($startindex <> 0) || ($nof <> 0))
          echo ",\n";
        echo json_encode($feature, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
      }
      $startindex += $count;
    }
    echo "\n]}\n";
  }
};
