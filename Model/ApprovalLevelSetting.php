<?php
/*
* 承認プラグイン
* 承認段階 設定モデル
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
* 承認段階設定モデル
*
* @package baser.plugins.approval
*/
class ApprovalLevelSetting extends ApprovalApp {
  /**
  * クラス名
  *
  * @var string
  * @access public
  */
  public $name = 'ApprovalLevelSetting';

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
  * バリデーション
  *
  * @var array
  * @access public
  */
  public $validate = array ();

}
