<?php
/*
* 承認プラグイン
* 固定ページ草稿モデル
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
* 固定ページ草稿モデル
*
* @package baser.plugins.approval
*/
class ApprovalPage extends ApprovalApp {
  /**
  * クラス名
  *
  * @var string
  * @access public
  */
  public $name = 'ApprovalPage';

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


}
