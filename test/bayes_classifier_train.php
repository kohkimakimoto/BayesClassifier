#!/usr/bin/env php
<?php

require_once(dirname(__FILE__).'/../src/BayesClassifier.php');

$bayesClassifier = BayesClassifier::getInstance(array(
  'db' => array(
    'dsn' => 'mysql:dbname=bayes_classifier;host=127.0.0.1',
    'username' => 'username',
    'password' => 'password')
));

if ($argc != 3) {
  echo "You need to pass text and category date on command line argument.\n";
  exit;
}

// text, cat
$bayesClassifier->train($argv[1], $argv[2]);

