<?php
/*
* 承認プラグイン
* 固定ページ カテゴリ設定コントローラー
*
* PHP 5.4.x
*
* @copyright    Copyright (c) hiniarata co.ltd
* @link         https://hiniarata.jp
* @package      Approval Plugin Project
* @since        ver.0.9.0
*/

/**
* 固定ページ カテゴリ設定コントローラー
*
* @package  baser.plugins.approval
*/
class ApprovalPageCategoryConfigsController extends ApprovalAppController {

  /**
  * クラス名
  *
  * @var string
  * @access public
  */
  public $name = 'ApprovalPageCategoryConfigs';

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
  public $helper = array('Js'=> array('Jquery'), 'Approval');

  /**
  * モデル
  *
  * @var array
  * @access public
  */
  public $uses = array('Approval.ApprovalLevelSetting','Approval.ApprovalPost', 'PageCategory', 'User', 'UserGroup');

  /**
  * ぱんくずナビ
  *
  * @var string
  * @access public
  */
  public $crumbs = array(
  array('name' => 'プラグイン管理', 'url' => array('plugin' => '', 'controller' => 'plugins', 'action' => 'index')),
  array('name' => '固定ページカテゴリ承認管理', 'url' => array('controller' => 'approval_page_category_configs', 'action' => 'index'))
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
  public function admin_form($pageCategoryId = null){
    /* 除外処理 */
    if (empty($pageCategoryId)) {
      $this->setMessage('無効なIDです。', true);
      $this->redirect(array('action' => 'index'));
    }

    //ブログ情報
    $pageData = $this->PageCategory->find('first', array('conditions' => array(
      'PageCategory.id' => $pageCategoryId
    )));

    //更新ボタン押下後
    if (!empty($this->request->data)) {
      //カテゴリIDの指定
      $this->request->data['ApprovalLevelSetting']['category_id'] = $pageCategoryId;
      //設定タイプ
      $this->request->data['ApprovalLevelSetting']['type'] = 'page';

      //バリデーションの動的追加
      //設定する承認段階数によって、空欄の許可・不許可を変更する。
      $lastStage = $this->request->data['ApprovalLevelSetting']['last_stage'];
      for ($i=1; $i <= $lastStage; $i++) {
        $this->ApprovalLevelSetting->validate['level'.$i.'_approver_id'] = array(
            'numeric'=> array(
                'rule' => 'numeric',
                'message' => '承認権限者を設定してください。',
                'allowEmpty' => false,//不許可
            )
        );
      }

      //バリデーションエラーが、オプション選択肢を作る。（ヘルパーで出していないので自作する。）
      $this->ApprovalLevelSetting->set($this->request->data);
      if (!$this->ApprovalLevelSetting->validates()) {
        //データがあるかどうか。あればUPDATE用のidをセットする。
        $approvalSetting = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.category_id' => $pageCategoryId,
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

      //保存処理
      if($this->ApprovalLevelSetting->save($this->request->data)){
        $this->setMessage('カテゴリ「'.$pageData['PageCategory']['title'].'」の段階承認設定を変更しました。', false, true);
        $this->redirect(array('action' => 'index'));
      } else {
        $this->setMessage('入力エラーです。内容を修正してください。', true);
      }

    //初期表示
    } else {
      //データがあるかどうか。あればUPDATE用のidをセットする。
      $approvalSetting = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
        'ApprovalLevelSetting.category_id' => $pageCategoryId,
        'ApprovalLevelSetting.type' => 'page'
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
    $this->set('pageData', $pageData);
    $this->pageTitle = '承認の設定';
  }

  /**
  * [ADMIN] 一覧表示
  *
  * @return void
  */
  public function admin_index() {
    // 画面表示情報設定
    $default = array(
      'named' => array(
        'num' => $this->siteConfigs['admin_list_num']
      ),
    );
    $this->setViewConditions('PageCategory', array('default' => $default));

    //データの取得
    $conditions = array();
    $this->paginate = array('conditions' => $conditions,
      'order' => 'PageCategory.id DESC',
      'limit' => $this->passedArgs['num'],
    );

    /* 表示設定 */
    $this->set('pageCategory', $this->paginate('PageCategory'));
    $this->pageTitle = '固定ページカテゴリ一覧';
  }

  /**
  * [ADMIN] 初期化する
  *
  * @return void
  */
  public function admin_delete($blogContentId = null){
    /* 除外処理 */
    if (empty($blogContentId)) {
      $this->setMessage('無効なIDです。', true);
      $this->redirect(array('action' => 'index'));
    }
    // ブログ情報の取得
    $blogContent = $this->BlogContent->findById($blogContentId);
    // 設定データの取得
    $settingData = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
      'ApprovalLevelSetting.blog_content_id' => $blogContentId
    )));
    /* 除外処理 */
    if (empty($settingData)) {
      $this->setMessage('承認設定が未設定です。', true);
      $this->redirect(array('action' => 'index'));
    }
    // 削除実行
    if ($this->ApprovalLevelSetting->delete($settingData['ApprovalLevelSetting']['id'])) {
      $this->setMessage('ブログ「'.$blogContent['BlogContent']['title'].'」の承認設定を初期化しました。', false, true);
      $this->redirect(array('action' => 'index'));
    } else {
      $this->setMessage('入力エラーです。初期化に失敗しました。', true);
    }
  }
}
