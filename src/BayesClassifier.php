<?php

/**
 * Bayes Classifier
 * 単純ベイズ分類器のPHP実装
 * 
 * @author Kohki Makimoto <kohki.makimoto@gmail.com>
 */
class BayesClassifier
{
  /*
  pos_id
  ------
  その他,間投,*,* 0
  フィラー,*,*,* 1
  感動詞,*,*,* 2
  記号,アルファベット,*,* 3
  記号,一般,*,* 4
  記号,括弧開,*,* 5
  記号,括弧閉,*,* 6
  記号,句点,*,* 7
  記号,空白,*,* 8
  記号,読点,*,* 9
  形容詞,自立,*,* 10
  形容詞,接尾,*,* 11
  形容詞,非自立,*,* 12
  助詞,格助詞,一般,* 13
  助詞,格助詞,引用,* 14
  助詞,格助詞,連語,* 15
  助詞,係助詞,*,* 16
  助詞,終助詞,*,* 17
  助詞,接続助詞,*,* 18
  助詞,特殊,*,* 19
  助詞,副詞化,*,* 20
  助詞,副助詞,*,* 21
  助詞,副助詞／並立助詞／終助詞,*,* 22
  助詞,並立助詞,*,* 23
  助詞,連体化,*,* 24
  助動詞,*,*,* 25
  接続詞,*,*,* 26
  接頭詞,形容詞接続,*,* 27
  接頭詞,数接続,*,* 28
  接頭詞,動詞接続,*,* 29
  接頭詞,名詞接続,*,* 30
  動詞,自立,*,* 31
  動詞,接尾,*,* 32
  動詞,非自立,*,* 33
  副詞,一般,*,* 34
  副詞,助詞類接続,*,* 35
  名詞,サ変接続,*,* 36
  名詞,ナイ形容詞語幹,*,* 37
  名詞,一般,*,* 38
  名詞,引用文字列,*,* 39
  名詞,形容動詞語幹,*,* 40
  名詞,固有名詞,一般,* 41
  名詞,固有名詞,人名,一般 42
  名詞,固有名詞,人名,姓 43
  名詞,固有名詞,人名,名 44
  名詞,固有名詞,組織,* 45
  名詞,固有名詞,地域,一般 46
  名詞,固有名詞,地域,国 47
  名詞,数,*,* 48
  名詞,接続詞的,*,* 49
  名詞,接尾,サ変接続,* 50
  名詞,接尾,一般,* 51
  名詞,接尾,形容動詞語幹,* 52
  名詞,接尾,助数詞,* 53
  名詞,接尾,助動詞語幹,* 54
  名詞,接尾,人名,* 55
  名詞,接尾,地域,* 56
  名詞,接尾,特殊,* 57
  名詞,接尾,副詞可能,* 58
  名詞,代名詞,一般,* 59
  名詞,代名詞,縮約,* 60
  名詞,動詞非自立的,*,* 61
  名詞,特殊,助動詞語幹,* 62
  名詞,非自立,一般,* 63
  名詞,非自立,形容動詞語幹,* 64
  名詞,非自立,助動詞語幹,* 65
  名詞,非自立,副詞可能,* 66
  名詞,副詞可能,*,* 67
  連体詞,*,*,* 68
  */
  protected $posids;
  
  /** PDOデータベースコネクション */
  protected $conn;
  
  /** オプションパラメータ */
  protected $options;
  
  protected $sm_param;
  
  private static $instance;
  
  public static function getInstance($options)
  {
    if (!self::$instance) {
      self::$instance = new BayesClassifier($options);
    }
    return self::$instance;
  }
  
  private function __construct($options)
  {
    $this->options = $options;
    
    $this->termCount = array();
    $this->categoryCount = array();
    
    $this->initDefaultEnablePosids();
  }
  
  /**
   * 学習
   *
   * @param  string $text テキストデータ
   * @param  string $category_name テキストデータのカテゴリ
   */
  public function train($text, $category_name)
  {
    // テキストを単語ベクトルに変換
    $vec = $this->text2vec($text);
    
    // カテゴリを保存
    $category_id = $this->saveCategory($category_name);
    foreach ($vec as $word => $count) {
      // 単語を保存
      $this->saveTerm($word, $category_id, $count);
    }
  }
  
  /**
   * カテゴリ推定
   *
   * @param  string $text テキストデータ
   * @return array カテゴリレコードの連想配列
   */
  public function predict($text, $isDebug = false)
  {
    // テキストを単語ベクトルに変換
    $vec = $this->text2vec($text);
    
    $scores = array();
    $best_score = null;
    $best_score_category = null;
    
    // カテゴリを全件取得
    $conn = $this->getConnection();
    $stmt = $conn->prepare("SELECT * FROM category;");
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    $categorys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categorys as $category) {
      // カテゴリごとにスコアを算出
      $scores[$category['id']] = $this->score($vec, $category['id'], $isDebug);
      if ($best_score === null || $scores[$category['id']] > $best_score ) {
        $best_score = $scores[$category['id']];
        $best_score_category = $category;
      }
    }
    
    
    return $best_score_category;
  }
  
  /**
   * カテゴリごとのスコアを算出する
   */
  protected function score($vec, $category_id, $isDebug = false)
  {
    if ($isDebug) {
      echo "[Debug] scoring details \n";
    }
    
    // カテゴリが生起する確率を求める - P(C)
    $score = log($this->category_prob($category_id));

    foreach ($vec as $word => $count) {
      // P(w1|C)P(w2|C)P(w3|C)...  P(C)
      $term_prob = $this->term_prob($word, $category_id);
      // 未知の単語は確率0になるので非常に小さい値を設定してスムージングする
      if ($term_prob == 0) {
        $term_prob = $this->getSmParam();
      }

      $score +=  log($term_prob) * $count;

      if ($isDebug) {
        echo "[Debug] cat:".$category_id." word:".$word." term_prob:".$term_prob." score:".$term_prob."\n";
      }
    }
      
    return $score;
  }
  
  /**
   * P(C)を求める(あるカテゴリが生起する確率を求める)
   */
  protected function category_prob($category_id)
  {
    // DBに登録済みのカテゴリを全件取得
    $conn = $this->getConnection();
    $stmt = $conn->prepare("SELECT * FROM category;");
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    $total = 0;
    $category_count = 0;
    
    $categorys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($categorys as $category) {
      // 全カテゴリの登場回数を合算
      $total = $total + $category['count'];
      
      if ($category_id == $category['id']) {
        $category_count = $category['count'];
      }
    }
    
    return $category_count / $total;
  }
  
  /**
   * P(W|C)を求める(あるカテゴリにある単語が生起する条件付き確率を求める)
   * TODO: ちとアルゴリズムがあやしい
   */
  protected function term_prob($word, $category_id)
  {
    // DBに登録済みの単語を取得
    $conn = $this->getConnection();
    $stmt = $conn->prepare("SELECT id, word FROM term WHERE word = :word;");
    $stmt->bindParam(':word', $word, PDO::PARAM_STR);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    // fetchOne
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$term) {
      // 登録されていない単語
      return 0;
    }
    
    $term_id = $term['id'];
    
    // DBに登録済みの単語カテゴリ関連を取得
    $conn = $this->getConnection();
    $stmt = $conn->prepare("SELECT * FROM term_category WHERE term_id = :term_id and category_id = :category_id;");
    $stmt->bindParam(':term_id', $term_id, PDO::PARAM_INT);
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    // fetchOne
    $term_category_count = 0;
    $term_category = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($term_category) {
      // ある単語があるカテゴリに分類された回数
      $term_category_count = $term_category['count'];
    }

    $stmt = $conn->prepare("SELECT * FROM term_category WHERE category_id = :category_id;");
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    $terms_count = 0;
    $term_categorys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($term_categorys) {
      // あるカテゴリに分類された単語の数
      // TODO:データが多いと計算量が膨大になるか？ categoryにterms_countをあらかじめもっておく？
      foreach ($term_categorys as $term_category) {
        $terms_count += $term_category['count'];
      }
    }
    
    if ($terms_count === 0) {
      // 一つも分類単語がないカテゴリ(学習データのないカテゴリ)
      return 0;
      
    }
    
    // カテゴリの中に単語が現れた回数をカテゴリに出現した単語の総数で割る
    return $term_category_count / $terms_count;
  }
  
  protected function getConnection()
  {
    if (!$this->conn) {
      $this->conn = new PDO(
        $this->options['db']['dsn'], 
        $this->options['db']['username'], 
        $this->options['db']['password']);
    }
    return $this->conn;
  }
  
  protected function saveCategory($category_name)
  {
    // DBに登録済みのカテゴリを取得
    $conn = $this->getConnection();
    $stmt = $conn->prepare("SELECT * FROM category WHERE name = :name;");
    $stmt->bindParam(':name', $category_name, PDO::PARAM_STR);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    // fetchOne
    $category_id = null;
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$category) {
      // カテゴリがまだ登録されていない
      
      // カテゴリを登録する
      $stmt = $conn->prepare("INSERT INTO category (name, name_kana, count) values (:name, :name_kana, 0);");
      $stmt->bindParam(':name', $category_name, PDO::PARAM_STR);
      $stmt->bindParam(':name_kana', $category_name, PDO::PARAM_STR);
      if (!$stmt->execute()) {
        throw new Exception("Database Errors.");
      }
      
      $category_id = $conn->lastInsertId();
    } else {
      $category_id = $category['id'];
    }
    
    // カテゴリの登場回数を+1
    $stmt = $conn->prepare("UPDATE category set count = count + 1 where id = :id");
    $stmt->bindParam(':id', $category_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    return $category_id;
  }
  
  protected function saveTerm($word, $category_id, $count)
  {
    // DBに登録済みの単語を取得
    $conn = $this->getConnection();
    $stmt = $conn->prepare("SELECT id, word FROM term WHERE word = :word;");
    $stmt->bindParam(':word', $word, PDO::PARAM_STR);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    // fetchOne
    $term_id = null;
    $term = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$term) {
      // 単語がまだ登録されていない
      
      // 単語を登録する
      $stmt = $conn->prepare("INSERT INTO term (word) values (:word);");
      $stmt->bindParam(':word', $word, PDO::PARAM_STR);
      if (!$stmt->execute()) {
        throw new Exception("Database Errors.");
      }
      
      $term_id = $conn->lastInsertId();
    } else {
      $term_id = $term['id'];
    }
    
    // DBに登録済みの単語カテゴリ関連を取得
    $stmt = $conn->prepare("SELECT * FROM term_category WHERE term_id = :term_id and category_id = :category_id;");
    $stmt->bindParam(':term_id', $term_id, PDO::PARAM_INT);
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
    // fetchOne
    $term_category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$term_category) {
      // 単語カテゴリ関連がまだ登録されていない
      
      // 単語カテゴリ関連を登録する
      $stmt = $conn->prepare("INSERT INTO term_category (term_id, category_id, count) values (:term_id, :category_id, 0);");
      $stmt->bindParam(':term_id', $term_id, PDO::PARAM_INT);
      $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
      if (!$stmt->execute()) {
        throw new Exception("Database Errors.");
      }
    }
    
    // 単語カテゴリ関連の分類回数を単語ベクトルのカウント分プラス
    $stmt = $conn->prepare("UPDATE term_category set count = count + :plus_count where term_id = :term_id and category_id = :category_id");
    $stmt->bindParam(':plus_count', $count, PDO::PARAM_INT);
    $stmt->bindParam(':term_id', $term_id, PDO::PARAM_INT);
    $stmt->bindParam(':category_id', $category_id, PDO::PARAM_INT);
    if (!$stmt->execute()) {
      throw new Exception("Database Errors.");
    }
    
  }
  
  /**
   * テキストを単語ベクトル配列に変換する
   *
   * @param  string $text テキストデータ
   * 
   * @return array 単語ベクトル
   */
  public function text2vec($text)
  {
    $vec = array();
    
    $mecab = new MeCab_Tagger();
    $node = $mecab->parseToNode($text);
    while ($node) {
      $nodeArray = $node->toArray();
      if($this->checkPosid($nodeArray['posid'])) {
        // 解析対象の品詞かチェック
        $surface = $nodeArray['surface'];
        
        if (array_key_exists($surface, $vec)) {
          $vec[$surface] = $vec[$surface] + 1;
        } else {
          $vec[$surface] = 1;
        }
      }
      
      $node = $node->getNext();
    }
    
    return $vec;
  }
  
  /**
   * デフォルトで利用する品詞を設定する
   */
  protected function initDefaultEnablePosids()
  {
    $this->posids = array(
      10,11,12,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60,61,62,63,64,65,66,67
    );
  }
  
  /**
   * 品詞チェック。処理に利用する品詞の場合trueを戻す。
   * 
   * @param $posid MeCabのposid
   * 
   * @return boolean 利用する品詞の場合true
   */
  protected function checkPosid($posid)
  {
    if(in_array($posid, $this->posids)) {
      return true;
    } else {
      return false;
    }
  }
  
  /*
   * スムージングパラメータ
   */
  protected function getSmParam()
  {
    if (!$this->sm_param) {
      // DBに登録済みの単語を取得
      $conn = $this->getConnection();
      $conn = $this->getConnection();
      $stmt = $conn->prepare("SELECT sum(count) as total_count FROM term_category;");
      if (!$stmt->execute()) {
        throw new Exception("Database Errors.");
      }
      
      $ret = $stmt->fetch(PDO::FETCH_ASSOC);
      
      // 全単語数
      $total_count = $ret['total_count'];
      
      // 1/全単語数の10倍をスムージング用パラメータとして使用する
      $this->sm_param = 1 / $total_count;
    }
    
    return $this->sm_param;
  }
  
}
