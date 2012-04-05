#!/usr/bin/env php
<?php
require_once(dirname(__FILE__).'/../src/BayesClassifier.php');

$bayesClassifier = BayesClassifier::getInstance(array(
  'db' => array(
    'dsn' => 'mysql:dbname=bayes_classifier;host=127.0.0.1',
    'username' => 'username',
    'password' => 'password')
));

if ($argc != 2) {
  echo "You need to pass text date on command line argument.\n";
  exit;
}

$predicted_category = $bayesClassifier->predict($argv[1]);
print_r($predicted_category);