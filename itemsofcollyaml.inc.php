<?php
/*PhpDoc:
name: itemsofcollyaml.inc.php
title: itemsofcollyaml.inc.php - définition de itemsOfCollectionDefYaml()
doc: |
  Lecture du contenu d'une collection définie par le fichier Yaml
  Nombreuses améliorations à faire
    - pagination
    - prise en compte des criteres
journal:
  1/6/2019:
    - première version rapide de l'utilisation d'un WFS server JSON
*/
require_once __DIR__.'/wfsjson.inc.php';

use Symfony\Component\Yaml\Yaml;

function itemsOfCollectionDefYaml(string $id, string $collname, array $criteria) {
  $yaml = Yaml::parseFile(__DIR__."/$id.yaml");
  //echo "<pre>yaml="; print_r($yaml);
  if (($yaml['$schema'] == 'http://ydclasses.georef.eu/FeatureDataset/schema') && isset($yaml['wfsUrl'])) {
    $server = new WfsServerJson($yaml);
    $typename = null;
    foreach ($yaml['layersByTheme'] as $theme) {
      if (isset($theme[$collname])) {
        $typename = $theme[$collname]['typename'];
      }
    }
    if (!$typename)
      throw new Exception("Erreur typename de $collname non trouvé");
    header('Content-type: application/json');
    $server->printAllFeatures($typename);
    die();
  }
  else
    die("Erreur dans ".__FILE__." ligne ".__LINE__);
}
