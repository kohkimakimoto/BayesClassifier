-- 
-- 単語
-- 
CREATE TABLE IF NOT EXISTS `term` (
  `id`   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `word` VARCHAR(255)    NOT NULL COMMENT '単語',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `term_unique1` (`word` ASC)
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COMMENT = '単語';


-- 
-- カテゴリ
-- 
CREATE TABLE IF NOT EXISTS `category` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `name`      VARCHAR(100) NOT NULL COMMENT 'カテゴリ',
  `name_kana` VARCHAR(100) NOT NULL COMMENT 'カテゴリの名称(かな)',
  `count`     INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'このカテゴリの登場回数',
  PRIMARY KEY (`id`),
  INDEX `catogory_index1` (`count` ASC),
  UNIQUE INDEX `category_unique1` (`name` ASC)
  )
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COMMENT = 'カテゴリ';


-- 
-- 単語とカテゴリの関連
-- 
CREATE TABLE IF NOT EXISTS `term_category` (
  `term_id`     BIGINT UNSIGNED NOT NULL COMMENT '単語',
  `category_id` INT UNSIGNED NOT NULL COMMENT 'カテゴリ',
  `count`       BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'この単語がこのカテゴリに分類された回数',
  PRIMARY KEY (`term_id`, `category_id`),
  CONSTRAINT `term_category_fk1`
    FOREIGN KEY (`term_id` )
    REFERENCES `term` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `term_category_fk2`
    FOREIGN KEY (`category_id` )
    REFERENCES `category` (`id` )
    ON DELETE CASCADE
    ON UPDATE CASCADE
)
ENGINE = InnoDB
DEFAULT CHARACTER SET = utf8
COMMENT = '単語カテゴリ関連';


