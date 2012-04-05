# BayesClassifier
PHPによるベイジアンフィルタ。単純ベイズ分類器。

テキストドキュメントのカテゴライズなどができます。

## Requrement
* MeCab

* PHP

* php_mecab

* MySQL

##Usage

* MeCabとPHPとphp_mecabとMySQLをインストールする。

* どこか適当なディレクトリにBayesClassifierのファイル一式を置く。

* MySQLにbayes_classifierデータベースを作成する

* BayesClassifier/sql/bays_classifier_ddl.sqlでテーブルを作成する。

* 機械学習させるには、BayesClassifier/testディレクトリ配下に移動してコマンドラインから

    php bayes_classifier_train.php なんかテキスト カテゴリ文字列

* カテゴリ推定させるには、BayesClassifier/testディレクトリ配下に移動してコマンドラインから

    php bayes_classifier_train.php なんかテキスト

##License
Apache License 2.0

