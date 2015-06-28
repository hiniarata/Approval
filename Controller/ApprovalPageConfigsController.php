<?php
/*
* 承認プラグイン
* 固定ページ設定コントローラー
*
* PHP 5.4.x
*
* @copyright    Copyright (c) hiniarata co.ltd
* @link         https://hiniarata.jp
* @package      Approval Plugin Project
* @since        ver.0.9.0
*/

/**
* 固定ページ設定コントローラー
*
* @package  baser.plugins.approval
*/
class ApprovalPageConfigsController extends ApprovalAppController {

  /**
  * クラス名
  *
  * @var string
  * @access public
  */
  public $name = 'ApprovalPageConfigs';

  /**
  * コンポーネント
  *
  * @var array
  * @access public
  */
  public $components = array('BcAuth', 'Cookie', 'BcAuthConfigure', 'RequestHandler');

  /**
  * ヘルパー
  *
  * @var array
  * @access public
  */
  public $helper = array('Js'=> array('Jquery'));

  /**
  * モデル
  *
  * @var array
  * @access public
  */
  public $uses = array('Approval.ApprovalLevelSetting','Approval.ApprovalPost', 'User', 'UserGroup');

  /**
  * ぱんくずナビ
  *
  * @var string
  * @access public
  */
  public $crumbs = array(
  array('name' => 'プラグイン管理', 'url' => array('plugin' => '', 'controller' => 'plugins', 'action' => 'index')),
  array('name' => '固定ページ承認管理', 'url' => array('controller' => 'approval_page_configs', 'action' => 'form'))
  );

  /**
  * サブメニューエレメント
  *
  * @var array
  * @access public
  */
  public $subMenuElements = array('approval');

  /**
  * beforeFilter
  *
  * @return	void
  * @access 	public
  */
  public function beforeFilter() {
    parent::beforeFilter();
  }

  /**
  * [ADMIN] 編集フォーム
  *
  * @return void
  */
  public function admin_form(){
    //更新ボタン押下後
    if (!empty($this->request->data)) {
      //設定タイプ
      $this->request->data['ApprovalLevelSetting']['type'] = 'page';
      //バリデーションの追加
      //設定する承認段階数によって、空欄の許可不許可を変更する。
      $lastStage = $this->request->data['ApprovalLevelSetting']['last_stage'];
      for ($i=1; $i <= $lastStage; $i++) {
        $this->ApprovalLevelSetting->validate['level'.$i.'_approver_id'] = array(
            'numeric'=> array(
                'rule' => 'numeric',
                'message' => '承認権限者を設定してください。',
                'allowEmpty' => false,//不許可
        ));
      }
      //バリデーションエラーが、オプション選択肢を作る。（ヘルパーで出していないので自作する。）
      $this->ApprovalLevelSetting->set($this->request->data);
      if (!$this->ApprovalLevelSetting->validates()) {
        //データがあるかどうか。あればUPDATE用のidをセットする。
        $approvalSetting = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.category_id' => 0,
          'ApprovalLevelSetting.type' => 'page'
        )));
        //View側でヘルパーで出力していないため、自前でselectedを入れる必要がある。
        $defaultOption = array();
        for($i=1; $i<6; $i++){
          if(!empty($approvalSetting['ApprovalLevelSetting']['level'.$i.'_type'])){
            switch ($approvalSetting['ApprovalLevelSetting']['level'.$i.'_type']) {
              //ユーザー
              case 'user':
                $userList = $this->User->find('all');
                $data = '';
                foreach($userList as $val){
                  if ($val['User']['id'] == $approvalSetting['ApprovalLevelSetting']['level'.$i.'_approver_id']) {
                    $selected = ' selected';
                  } else {
                    $selected = '';
                  }
                  $data .= '<option value="' .$val['User']['id']. '" '.$selected.'>' .$val['User']['real_name_1']. $val['User']['real_name_2'] . '</option>';
                }
                break;
              //グループ
              case 'group':
              $userList = $this->UserGroup->find('all');
              $data = '';
              foreach($userList as $val){
                if ($val['UserGroup']['id'] == $approvalSetting['ApprovalLevelSetting']['level'.$i.'_approver_id']) {
                  $selected = ' selected';
                } else {
                  $selected = '';
                }
                $data .= '<option value="' .$val['UserGroup']['id']. '" '.$selected.'>' .$val['UserGroup']['title']. '</option>';
              }
                break;
            }
          //設定がないもの
          } else {
            $data = '<option>タイプを選択してください。</option>';
          }
          $defaultOption[$i] = $data;
        }
        //表示セット
        $this->set('defaultOption', $defaultOption);
        if (!empty($approvalSetting['ApprovalLevelSetting']['id'])) {
          $this->set('approvalId', $approvalSetting['ApprovalLevelSetting']['id']);
        }
        $this->set('approvalSetting', $approvalSetting);
      }

      //カテゴリIDは常に0
      $this->request->data['ApprovalLevelSetting']['category_id'] = 0;
      //保存処理
      if($this->ApprovalLevelSetting->save($this->request->data)){
        $this->setMessage('固定ページの段階承認設定を変更しました。', false, true);
        $this->redirect(array('action' => 'form'));
      } else {
        $this->setMessage('入力エラーです。内容を修正してください。', true);
      }

    //初期表示
    } else {
      //データがあるかどうか。あればUPDATE用のidをセットする。
      $approvalSetting = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
        'ApprovalLevelSetting.type' => 'page',
        'ApprovalLevelSetting.category_id' => 0
      )));
      //セッティング内容を確かめる。
      //View側でヘルパーで出力していないため、自前でselectedを入れる必要がある。
      $defaultOption = array();
      for($i=1; $i<6; $i++){
        if(!empty($approvalSetting['ApprovalLevelSetting']['level'.$i.'_type'])){
          switch ($approvalSetting['ApprovalLevelSetting']['level'.$i.'_type']) {
            //ユーザー
            case 'user':
              $userList = $this->User->find('all');
              $data = '';
              foreach($userList as $val){
                if ($val['User']['id'] == $approvalSetting['ApprovalLevelSetting']['level'.$i.'_approver_id']) {
                  $selected = ' selected';
                } else {
                  $selected = '';
                }
                $data .= '<option value="' .$val['User']['id']. '" '.$selected.'>' .$val['User']['real_name_1']. $val['User']['real_name_2'] . '</option>';
              }
              break;
            //グループ
            case 'group':
            $userList = $this->UserGroup->find('all');
            $data = '';
            foreach($userList as $val){
              if ($val['UserGroup']['id'] == $approvalSetting['ApprovalLevelSetting']['level'.$i.'_approver_id']) {
                $selected = ' selected';
              } else {
                $selected = '';
              }
              $data .= '<option value="' .$val['UserGroup']['id']. '" '.$selected.'>' .$val['UserGroup']['title']. '</option>';
            }
              break;
          }
        //設定がないもの
        } else {
          $data = '<option>タイプを選択してください。</option>';
        }
        $defaultOption[$i] = $data;
      }
      //表示セット
      $this->set('defaultOption', $defaultOption);
      if (!empty($approvalSetting['ApprovalLevelSetting']['id'])) {
        $this->set('approvalId', $approvalSetting['ApprovalLevelSetting']['id']);
      }
      $this->set('approvalSetting', $approvalSetting);
      $this->request->data = $approvalSetting;
    }
    //タイトル
    $this->pageTitle = '固定ページ 承認の設定';
  }

  /**
  * [ADMIN] 初期化する
  *
  * @return void
  */
  public function admin_delete($id = null){
    /* 除外処理 */
    if (empty($id)) {
      $this->setMessage('無効なIDです。', true);
      $this->redirect(array('action' => 'index'));
    }
    // 削除実行
    if ($this->ApprovalLevelSetting->delete($id)) {
      $this->setMessage('固定ページ（全体）の承認設定を初期化しました。', false, true);
      $this->redirect(array('action' => 'index'));
    } else {
      $this->setMessage('入力エラーです。初期化に失敗しました。', true);
    }
  }

}
