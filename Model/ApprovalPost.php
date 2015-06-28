<?php
/*
* 承認プラグイン
* ブログ記事 承認情報モデル
*
* PHP 5.4.x
*
* @copyright    Copyright (c) hiniarata co.ltd
* @link         https://hiniarata.jp
* @package      Approval Plugin Project
* @since        ver.0.9.0
*/

/**
* Include files
*/
App::uses('ApprovalApp', 'Approval.Model');

/**
* ブログ記事 承認情報モデル
*
* @package baser.plugins.approval
*/
class ApprovalPost extends ApprovalApp {
  /**
  * クラス名
  *
  * @var string
  * @access public
  */
  public $name = 'ApprovalPost';

  /**
  * プラグイン名
  *
  * @var string
  * @access public
  */
  public $plugin = 'Approval';

  /**
  * DB接続時の設定
  *
  * @var string
  * @access public
  */
  public $useDbConfig = 'plugin';

  /**
   * ビヘイビア
   *
   * @var array
   * @access public
   */
  	public $actsAs = array(
  		//'BcContentsManager',
  		//'BcCache',
  		'BcUpload' => array(
  			'subdirDateFormat' => 'Y/m/',
  			'fields' => array(
  				'eye_catch' => array(
  					'type' => 'image',
  					'namefield' => 'no',
  					'nameformat' => '%08d'
  				)
  			)
  		)
  	);
}
