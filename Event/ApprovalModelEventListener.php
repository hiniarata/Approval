<?php
/*
* 承認プラグイン
* モデル イベントリスナー
*
* PHP 5.4.x
*
* @copyright    Copyright (c) hiniarata co.ltd
* @link         https://hiniarata.jp
* @package      Approval Plugin Project
* @since        ver.0.9.0
*/

/**
* モデル イベントリスナー
*
* @package baser.plugins.approval
*/
class ApprovalModelEventListener extends BcModelEventListener {

  /**
  * イベント
  *
  * @var array
  * @access public
  */
  public $events = array(
          'Blog.BlogPost.beforeSave',
          //'Page.beforeSave',
          );

  /**
  * ブログ投稿 beforeSave
  * 承認や差戻しの状態を確認し、データ操作を行う。
  *
  * @return  void
  * @access  public
  */
  public function blogBlogPostBeforeSave(CakeEvent $event) {
    //---------------------------------
    // モデル・セッションの呼び出し
    //---------------------------------
    //承認待ちモデルを取得する。
    App::import('Model', 'Approval.ApprovalPost');
    $approvalPost = new ApprovalPost();
    //承認レベル設定モデルを取得する。
    App::import('Model', 'Approval.ApprovalLevelSetting');
    $approvalLevelSetting = new ApprovalLevelSetting();
    //セッション情報からログイン中のユーザー情報を取得する。
    App::uses('CakeSession', 'Model/Datasource');
    $Session = new CakeSession();
    $user = $Session->read('Auth.User');
    //呼び出し元のモデルを取得する
    $BlogPost = $event->subject();

    //---------------------------------
    // 設定情報の確認
    //---------------------------------
    //ブログの場合はカテゴリID（優先）、コンテンツIDの両方を確認する。
    $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
      'ApprovalLevelSetting.type' => 'blog',
      'ApprovalLevelSetting.category_id' => $BlogPost->data['BlogPost']['blog_category_id']
    )));
    //カテゴリの設定がなければブログコンテンツ単位であるかどうかを確認する。
    if (empty($settingData)) {
      $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
        'ApprovalLevelSetting.type' => 'blog',
        'ApprovalLevelSetting.category_id' => 0,
        'ApprovalLevelSetting.blog_content_id' => $BlogPost->data['BlogPost']['blog_content_id']
      )));
    }

    //---------------------------------
    // 承認処理開始
    //---------------------------------
    //設定がされてない場合はスルーする
    if (empty($settingData)) {
      return true;
    //設定はあるが、利用しない:0の場合もスルーする
    } elseif ($settingData['ApprovalLevelSetting']['publish'] == 0){
      return true;
    //設定があり、かつ利用する:1場合のみ処理に入る。
    } else {
      /* 除外処理（承認処理を行うにも関わらずフラグが届いていない。）*/
      if (empty($BlogPost->data['Approval'])) {
        return false;
      }

      //承認・保留・拒否に関わらず、カテゴリが変更になった場合、新しいカテゴリの承認フローを１からへる必要がある。
      //現在のデータ取得
      $approvalData = $approvalPost->find('first', array('conditions' => array(
        'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
      )));
      //新規作成の時などはデータがないので、ある場合のみ処理。
      if (!empty($approvalData)) {
        //カテゴリの変更を検知
        if ($approvalData['ApprovalPost']['blog_category_id'] !== $BlogPost->data['BlogPost']['blog_category_id']) {
          //変更後のカテゴリに承認設定があるかどうかを確認する。
          $newCategorySetting = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
            'ApprovalLevelSetting.publish' => 1,
            'ApprovalLevelSetting.type' => 'blog',
            'ApprovalLevelSetting.category_id' => $BlogPost->data['BlogPost']['blog_category_id']
          )));
          //カテゴリ個別の設定がない場合は、ブログ全体を確かめる。
          if (empty($newCategorySetting)) {
            $newCategorySetting = $this->ApprovalLevelSetting->find('first', array('conditions' => array(
              'ApprovalLevelSetting.publish' => 1,
              'ApprovalLevelSetting.type' => 'blog',
              'ApprovalLevelSetting.blog_content_id' => $BlogPost->data['BlogPost']['blog_content_id']
            )));
          }
          //ブログ全体にも設定がない場合は、承認設定がないので現在の承認段階フローの記録を消して通過させる。
          if (empty($newCategorySetting)) {
            //削除実行
            $this->ApprovalPost->delete($approvalData['ApprovalPost']['id']);
            //通過させる。
            return true;

          //何かしらの承認設定があった場合
          } else {
            //新しい承認設定のもと、第１段階まで戻す。
            $approvalData['ApprovalPost']['blog_category_id'] = $BlogPost->data['BlogPost']['blog_category_id'];
            $approvalData['ApprovalPost']['next_approver_id'] = $newCategorySetting['ApprovalLevelSetting']['level1_approver_id'];
            $approvalData['ApprovalPost']['pass_stage'] = 0;
            $BlogPost->data['BlogPost']['status'] = 0;
            //TODO メール送信
            if ($this->ApprovalPost->save($approvalData)) {
              return true;
            } else {
              return false;
            }
          }
        }
      }

      /* 承認フラグを確認する。*/
      // 0:保留
      // 1:承認または承認申請（次の段階へ）
      // 2:拒否（１つ戻す）
      //------------------------------
      // 0:保留の時
      //------------------------------
      if ($BlogPost->data['Approval']['approval_flag'] == 0) {
        $BlogPost->data['BlogPost']['status'] = 0;
        return true; //スルーする。

      //------------------------------
      // 1:承認の時（承認申請後の場合）
      //------------------------------
      } elseif ($BlogPost->data['Approval']['approval_flag'] == 1) {
        //すでに承認申請後なので、この段階では必ず既存データがある。
        if (!empty($BlogPost->data['BlogPost']['id'])){
          //承認情報データの取得
          $approvalData = $approvalPost->find('first', array('conditions' => array(
            'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
          )));

          /* 現状の確認を行う */
          //現在の承認済段階を確認する。
          if (!empty($approvalData['ApprovalPost']['pass_stage'])) {
            $pass_stage = $approvalData['ApprovalPost']['pass_stage'];
          }
          if(empty($pass_stage)){ //null
            $pass_stage = 0;
          }
          //現在の承認ステージ（通過ステージに１足したもの）
          $now_stage = $pass_stage+1;
          //最終的に必要な承認ステージ数
          $last_stage = $settingData['ApprovalLevelSetting']['last_stage'];
          //現在のステージの権限者ID（ユーザーもしくはグループのIDが入っている）
          $approverId = $settingData['ApprovalLevelSetting']['level'.$now_stage.'_approver_id'];
          //ログイン中のユーザーのIDとグループID
          $loginUserId = $user['id'];
          $loginUserGroupId = $user['UserGroup']['id'];

          /* 取得した情報から承認権限の確認を行う */
          //権限者タイプ（type:user or group）の確認と権限チェック
          switch ($settingData['ApprovalLevelSetting']['level'.$now_stage.'_type']) {
            //承認権限者がユーザー単位で設定されている場合
            case 'user':
              /* 権限なし */
              //現在のユーザーが権限者ではない場合
              if ($approverId != $loginUserId) {
                //強制的に非公開設定
                $BlogPost->data['BlogPost']['status'] = 0;

              /* 権限あり */
              //承認権限を持っている場合
              } elseif ($approverId == $loginUserId) {
                /* データの整理を行う */
                //現在の承認ステージ
                $now = $pass_stage+1;
                //次の承認ステージ
                $next_stage = $now+1;
                //ブログ投稿記事の情報を取得する。
                $post['ApprovalPost'] = $BlogPost->data['BlogPost'];
                $post['ApprovalPost']['post_id'] = $BlogPost->data['BlogPost']['id'];
                $post['ApprovalPost']['id'] = $approvalData['ApprovalPost']['id'];
                $post['ApprovalPost']['pass_stage'] = $now;
                //もしもこれが最終権限でなければ、次のステージ権限者を保存しておく。
                if ($settingData['ApprovalLevelSetting']['last_stage'] != $now) {
                  $post['ApprovalPost']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$next_stage.'_approver_id'];
                }
                /* 承認の記録を保存する */
                //承認情報DBへ保存する。
                if ($approvalPost->save($post)) {
                  //最終承認まで行っていない場合は、強制非公開にする。
                  if ($pass_stage < $last_stage) {
                    $BlogPost->data['BlogPost']['status'] = 0;
                  }
                  return true;
                }
              }
              break;

            //承認権限者がグループ単位で設定されている場合
            case 'group':
              /* 権限なし */
              //承認権限がない場合、常に非公開
              if ($approverId != $loginUserGroupId) {
                $BlogPost->data['BlogPost']['status'] = 0;

              /* 権限あり */
              //承認権限を持っている場合
              } else {
                /* データの整理を行う */
                //現在の承認ステージ
                $now = $pass_stage+1;
                //次の承認ステージ
                $next_stage = $now+1;
                //ブログ投稿記事の情報を取得する。
                $post['ApprovalPost'] = $BlogPost->data['BlogPost'];
                $post['ApprovalPost']['post_id'] = $BlogPost->data['BlogPost']['id'];
                $post['ApprovalPost']['id'] = $approvalData['ApprovalPost']['id'];
                $post['ApprovalPost']['pass_stage'] = $now;
                //もしもこれが最終権限でなければ、次のステージ権限者を保存しておく。
                if ($settingData['ApprovalLevelSetting']['last_stage'] != $now) {
                  $post['ApprovalPost']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$next_stage.'_approver_id'];
                }

                /* 承認の記録を保存する */
                //承認情報DBへ保存する。
                if ($approvalPost->save($post)) {
                  //pass_stageがlast_stageより大きくなければ（最終承認まで行っていない）、非公開は続行。
                  if ($pass_stage < $last_stage) {
                    $BlogPost->data['BlogPost']['status'] = 0;
                  }
                  return true;
                }
              }
              break;
            default:
              break;
          }
        }

      //------------------------------
      // 2:却下の時
      //------------------------------
      } elseif ($BlogPost->data['Approval']['approval_flag'] == 2) {
        /* 現在の状況を取得 */
        $approvalData = $approvalPost->find('first', array('conditions' => array(
          'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
        )));

        /* 承認却下の処理実行 */
        //すでに承認を１段階以上受けている場合
        if ($approvalData['ApprovalPost']['pass_stage'] != 0) {
          //次の権限者を１段階前に戻す
          $prevStage = $approvalData['ApprovalPost']['pass_stage'] -1;
          $approvalData['ApprovalPost']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$prevStage.'_approver_id'];
          //通過ステージを１段階戻す。もしも最終段階まで承認が終わっていれば2つ戻す
          if ($approvalData['ApprovalPost']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
            $approvalData['ApprovalPost']['pass_stage'] = $approvalData['ApprovalPost']['pass_stage'] -2;
          } else {
            $approvalData['ApprovalPost']['pass_stage'] = $approvalData['ApprovalPost']['pass_stage'] -1;
          }

        //第１段階で却下された場合、次の権限者は0にする。
        } else {
          $approvalData['ApprovalPost']['next_approver_id'] = 0;
        }

        /* 却下の記録を保存する */
        //承認情報DBを更新する
        if ($approvalPost->save($approvalData)) {
          //常に非公開状態（承認が下るまでは非公開にする）
          $BlogPost->data['BlogPost']['status'] = 0;
          return true;
        }

      //------------------------------
      // 申請（新規登録or最初まで却下されてきた場合）
      //------------------------------
      } elseif ($BlogPost->data['Approval']['approval_flag'] == 3){
        //現在の承認情報データの取得
        //（一度却下されたあとの場合はデータが存在する。）
        $approvalData = $approvalPost->find('first', array('conditions' => array(
          'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
        )));

        //すでに承認情報データがある（却下されて最初の段階まで戻った場合）
        if (!empty($approvalData)) {
          //承認情報DBへデータを挿入する。
          $post['ApprovalPost'] = $BlogPost->data['BlogPost'];
          //IDだけは一旦捨てる。
          $post['ApprovalPost']['id'] = $approvalData['ApprovalPost']['id'];
          //申請段階のため通過ステージは0にする
          $post['ApprovalPost']['pass_stage'] = 0;
          //申請段階のため次のステージは必ず１
          $post['ApprovalPost']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level1_approver_id'];
          //承認申請を行う段階で選択されたカテゴリを取得する。
          if (!empty($BlogPost->data['BlogPost']['blog_category_id'])) {
              $post['ApprovalPost']['next_approver_id'] = $BlogPost->data['BlogPost']['blog_category_id'];
          }

          /* 申請の記録を保存する */
          if ($approvalPost->save($post)) {
            //常に非公開状態（承認が下るまでは非公開にする）
            $BlogPost->data['BlogPost']['status'] = 0;
            return true;
          }

        //承認情報データがない場合
        } else {
          //承認情報DBへデータを挿入する。
          $post['ApprovalPost'] = $BlogPost->data['BlogPost'];
          //idがある場合（一度保留を経て申請）。
          if (!empty($BlogPost->data['BlogPost']['id'])) {
            $post['ApprovalPost']['post_id'] = $BlogPost->data['BlogPost']['id'];
          //idがない場合（新規作成）
          } else {
            //ブログの最終IDを取得する。getLastInsertID();が効かない・・・
            App::import('Model', 'Blog.BlogPost');
            $blogModel = new BlogPost();
            $blogLastData = $blogModel->find('first', array('order' => array('BlogPost.id DESC')));
            $post['ApprovalPost']['post_id'] = (int)$blogLastData['BlogPost']['id'] +1;
          }
          $post['ApprovalPost']['id'] = '';
          unset($post['ApprovalPost']['id']);
          //申請段階のため通過ステージは0にする
          $post['ApprovalPost']['pass_stage'] = 0;
          //申請段階のため次のステージは必ず１
          $post['ApprovalPost']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level1_approver_id'];

          /* 申請の記録を保存する */
          if ($approvalPost->save($post)) {
            //常に非公開状態（承認が下るまでは非公開にする）
            $BlogPost->data['BlogPost']['status'] = 0;
            return true;
          }
        }

      //------------------------------
      // それ以外
      //------------------------------
      } else {
        return false;
      }
    }
  }


  /**
  * 固定ページ保存前
  *
  * @return  void
  * @access  public
  */
  public function pageBeforeSave(CakeEvent $event) {
    //$event->data['data']['BlogPost']
  }

/*
  public function loadComponent($componentClass, $settings = array()) {
	if (!isset($this->{$componentClass})) {
		if (!isset($this->Components)) {
			$this->Components = new ComponentCollection();
		}
		App::uses($componentClass, 'Controller/Component');
		$this->{$componentClass} = $this->Components->load($componentClass, $settings);
	}
}
*/

}
