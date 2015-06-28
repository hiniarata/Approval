<?php
/*
* 承認プラグイン
* 専用ヘルパー
*
* PHP 5.4.x
*
* @copyright    Copyright (c) hiniarata co.ltd
* @link         https://hiniarata.jp
* @package      Approval Plugin Project
* @since        ver.0.9.0
*/
/* Model */
App::import('Model', 'Blog.BlogCategory');
App::uses('AppHelper', 'View/Helper');

class ApprovalHelper extends AppHelper {

  /**
  * ヘルパー
  *
  * @var array
  */
  public $helpers = array('Html', 'BcBaser');

  /**
  * ブログコンテンツ名を出力する
  *
  * @var int $blogContentId
  * @access public
  */
  public function blogContentTitle($blogContentId){
    echo $this->getBlogContentTitle($blogContentId);
  }

  /**
  * ブログコンテンツ名を取得する
  *
  * @var int $blogContentId
  * @access public
  */
  public function getBlogContentTitle($blogContentId){
    $blogCategoryModel = new BlogCategory;
    $category = $blogCategoryModel->findById($blogContentId);
    if (!empty($category)) {
      return $category['BlogCategory']['title'];
    } else {
      return '';
    }
  }
}
