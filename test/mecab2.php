<?php
$mecab = new MeCab_Tagger();
$node = $mecab->parseToNode("すもももももももものうち");

while ($node) {
  $nodeArray = $node->toArray();
  print_r($nodeArray);
  $node = $node->getNext();
}

