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
          'Page.beforeSave',
          );

  /**
  * ブログ投稿 beforeSave
  * 承認や差戻しの状態を確認し、データ操作を行う。
  *
  * [仕様]
  * 記事が属するカテゴリもしくはコンテンツ全体に承認設定があると処理に入る。
  * 記事の作成段階では「申請」を行う。申請があってはじめて承認段階に入る。
  * 「承認」は設定で決められた承認権限者が行う。ユーザーの場合とグループの場合がある。
  * 「承認」されるとpass_stageが1上がる。「差し戻し」を受けると1下がる。
  * 申請前も第１段階承認前もpass_stageは「0」。
  * 区別をつける必要がある時は「next_approver_id」を見る。申請前はまだ「0」になっている。
  *
  * カテゴリ毎に設定ができるため、途中でカテゴリが変更されたら、
  * 変更後のカテゴリの承認設定に従うため、一度リセット（申請前まで戻す）する必要がある。
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

      //承認・保留・拒否に関わらず、カテゴリが変更になった場合、
      //新しいカテゴリの承認フローを１から通過する必要がある。
      //---------------------------------
      // カテゴリ変更の検知
      //---------------------------------
      $approvalData = $approvalPost->find('first', array('conditions' => array(
        'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
      )));
      //新規作成の時などはデータがないので、ある場合のみ処理。
      if (!empty($approvalData)) {
        //カテゴリの変更を検知
        if ($approvalData['ApprovalPost']['blog_category_id'] != $BlogPost->data['BlogPost']['blog_category_id']) {
          //変更後のカテゴリに承認設定があるかどうかを確認する。
          $newCategorySetting = $approvalLevelSetting->find('first', array('conditions' => array(
            'ApprovalLevelSetting.publish' => 1,
            'ApprovalLevelSetting.type' => 'blog',
            'ApprovalLevelSetting.category_id' => $BlogPost->data['BlogPost']['blog_category_id']
          )));
          //カテゴリ個別の設定がない場合は、ブログ全体を確かめる。
          if (empty($newCategorySetting)) {
            $newCategorySetting = $approvalLevelSetting->find('first', array('conditions' => array(
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
            //新しい承認設定のもと、申請段階前まで戻す。
            $approvalData['ApprovalPost']['blog_category_id'] = $BlogPost->data['BlogPost']['blog_category_id'];
            $approvalData['ApprovalPost']['next_approver_id'] = 0;
            $approvalData['ApprovalPost']['pass_stage'] = 0;
            $BlogPost->data['BlogPost']['status'] = 0;
            //データの更新
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
        if (!empty($BlogPost->data['BlogPost']['id'])) {
          //承認情報データの取得
          $approvalData = $approvalPost->find('first', array('conditions' => array(
            'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
          )));
          //もしも最終段階まで来ていなければ強制非公開
          if ($settingData['ApprovalLevelSetting']['last_stage'] != $approvalData['ApprovalPost']['pass_stage']) {
            $BlogPost->data['BlogPost']['status'] = 0;
          }

        //新規作成の時
        } else {
          $BlogPost->data['BlogPost']['status'] = 0;
        }
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
                  if ($post['ApprovalPost']['pass_stage'] < $last_stage) {
                    $BlogPost->data['BlogPost']['status'] = 0;
                    $this->_sendApprovalMail($settingData, $approvalData, $BlogPost->data, 1);
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
                  if ($post['ApprovalPost']['pass_stage'] < $last_stage) {
                    $BlogPost->data['BlogPost']['status'] = 0;
                    $this->_sendApprovalMail($settingData, $approvalData, $BlogPost->data, 1);
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
          //0まで戻った場合（申請段階まで戻った場合）
          if ($prevStage == 0) {
            $approvalData['ApprovalPost']['next_approver_id'] = 0;
          } else {
            $approvalData['ApprovalPost']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$prevStage.'_approver_id'];
          }
          //通過ステージを１段階戻す。もしも最終段階まで承認が終わっていれば2つ戻す
          if ($approvalData['ApprovalPost']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
            $approvalData['ApprovalPost']['pass_stage'] = $approvalData['ApprovalPost']['pass_stage'] -2;
            if ($approvalData['ApprovalPost']['pass_stage'] < 0) {
              $approvalData['ApprovalPost']['pass_stage'] = 0;
            }
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
          $this->_sendApprovalMail($settingData, $approvalData, $BlogPost->data, 2);
          return true;
        }

      //------------------------------
      // 申請（新規登録or最初まで却下されてきた場合）
      //------------------------------
      } elseif ($BlogPost->data['Approval']['approval_flag'] == 3){
        //現在の承認情報データの取得
        //（一度却下されたあとの場合はデータが存在する。）
        if (!empty($BlogPost->data['BlogPost']['id'])) {
          $approvalData = $approvalPost->find('first', array('conditions' => array(
            'ApprovalPost.post_id' => $BlogPost->data['BlogPost']['id']
          )));
        }

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
            $this->_sendApprovalMail($settingData, $approvalData, $BlogPost->data, 3);
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
            $this->_sendApprovalMail($settingData, null, $BlogPost->data, 3);
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
    //---------------------------------
    // モデル・セッションの呼び出し
    //---------------------------------
    //承認待ちモデルを取得する。
    App::import('Model', 'Approval.ApprovalPage');
    $approvalPage = new ApprovalPage();
    //承認レベル設定モデルを取得する。
    App::import('Model', 'Approval.ApprovalLevelSetting');
    $approvalLevelSetting = new ApprovalLevelSetting();
    //セッション情報からログイン中のユーザー情報を取得する。
    App::uses('CakeSession', 'Model/Datasource');
    $Session = new CakeSession();
    $user = $Session->read('Auth.User');
    //呼び出し元のモデルを取得する
    $PagePost = $event->subject();

    //---------------------------------
    // 設定情報の確認
    //---------------------------------
    //カテゴリID（優先）、コンテンツIDの両方を確認する。
    $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
      'ApprovalLevelSetting.type' => 'page',
      'ApprovalLevelSetting.category_id' => $PagePost->data['Page']['page_category_id']
    )));
    //カテゴリの設定がなければ固定ページ全体で設定があるかどうかを確認する。
    if (empty($settingData)) {
      $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
        'ApprovalLevelSetting.type' => 'page',
        'ApprovalLevelSetting.category_id' => 0,
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
      if (empty($PagePost->data['Approval'])) {
        return false;
      }

      //承認・保留・拒否に関わらず、カテゴリが変更になった場合、新しいカテゴリの承認フローを１からへる必要がある。
      //現在のデータ取得
      if (!empty($PagePost->data['Page']['id'])) {
        $approvalData = $approvalPage->find('first', array('conditions' => array(
          'ApprovalPage.page_id' => $PagePost->data['Page']['id']
        )));
      }
      //新規作成の時などはデータがないので、ある場合のみ処理。
      if (!empty($approvalData)) {
        //カテゴリの変更を検知
        if ($approvalData['ApprovalPage']['page_category_id'] != $PagePost->data['Page']['page_category_id']) {
          //変更後のカテゴリに承認設定があるかどうかを確認する。
          $newCategorySetting = $approvalLevelSetting->find('first', array('conditions' => array(
            'ApprovalLevelSetting.publish' => 1,
            'ApprovalLevelSetting.type' => 'page',
            'ApprovalLevelSetting.category_id' => $PagePost->data['Page']['page_category_id']
          )));
          //カテゴリ個別の設定がない場合は、ブログ全体を確かめる。
          if (empty($newCategorySetting)) {
            $newCategorySetting = $approvalLevelSetting->find('first', array('conditions' => array(
              'ApprovalLevelSetting.publish' => 1,
              'ApprovalLevelSetting.type' => 'page'
            )));
          }
          //ブログ全体にも設定がない場合は、承認設定がないので現在の承認段階フローの記録を消して通過させる。
          if (empty($newCategorySetting)) {
            //削除実行
            $this->ApprovalPage->delete($approvalData['ApprovalPage']['id']);
            //通過させる。
            return true;

          //何かしらの承認設定があった場合
          } else {
            //新しい承認設定のもと、申請前段階まで戻す。
            $approvalData['ApprovalPage']['page_category_id'] = $PagePost->data['Page']['page_category_id'];
            $approvalData['ApprovalPage']['next_approver_id'] = 0;
            $approvalData['ApprovalPage']['pass_stage'] = 0;
            $PagePost->data['Page']['status'] = 0;
            if ($this->ApprovalPage->save($approvalData)) {
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
      if ($PagePost->data['Approval']['approval_flag'] == 0) {
        //承認情報データの取得
        if (!empty($PagePost->data['Page']['id'])) {
          //現状の確認
          $approvalData = $approvalPage->find('first', array('conditions' => array(
            'ApprovalPage.page_id' => $PagePost->data['Page']['id']
          )));
          //もしも最終段階まで来ていれば、承認状態。
          if ($settingData['ApprovalLevelSetting']['last_stage'] != $approvalData['ApprovalPage']['pass_stage']) {
            $PagePost->data['Page']['status'] = 0;
          }

      //新規作成時
      } else {
        $PagePost->data['Page']['status'] = 0;
      }
      return true; //スルーする。

      //------------------------------
      // 1:承認の時（承認申請後の場合）
      //------------------------------
      } elseif ($PagePost->data['Approval']['approval_flag'] == 1) {
        //すでに承認申請後なので、この段階では必ず既存データがある。
        if (!empty($PagePost->data['Page']['id'])){
          //承認情報データの取得
          $approvalData = $approvalPage->find('first', array('conditions' => array(
            'ApprovalPage.page_id' => $PagePost->data['Page']['id']
          )));

          /* 現状の確認を行う */
          //現在の承認済段階を確認する。
          if (!empty($approvalData['ApprovalPage']['pass_stage'])) {
            $pass_stage = $approvalData['ApprovalPage']['pass_stage'];
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
                $PagePost->data['Page']['status'] = 0;

              /* 権限あり */
              //承認権限を持っている場合
              } elseif ($approverId == $loginUserId) {
                /* データの整理を行う */
                //現在の承認ステージ
                $now = $pass_stage+1;
                //次の承認ステージ
                $next_stage = $now+1;
                //ブログ投稿記事の情報を取得する。
                $post['ApprovalPage'] = $PagePost->data['Page'];
                $post['ApprovalPage']['post_id'] = $PagePost->data['Page']['id'];
                $post['ApprovalPage']['id'] = $approvalData['ApprovalPage']['id'];
                $post['ApprovalPage']['pass_stage'] = $now;
                //もしもこれが最終権限でなければ、次のステージ権限者を保存しておく。
                if ($settingData['ApprovalLevelSetting']['last_stage'] != $now) {
                  $post['ApprovalPage']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$next_stage.'_approver_id'];
                }
                /* 承認の記録を保存する */
                //承認情報DBへ保存する。
                if ($approvalPage->save($post)) {
                  //最終承認まで行っていない場合は、強制非公開にする。
                  if ($post['ApprovalPage']['pass_stage'] < $last_stage) {
                    $PagePost->data['Page']['status'] = 0;
                    //メールの送信（最終までいけば送信は不要）
                    $this->_sendApprovalMail($settingData, $approvalData, $PagePost->data, 1);
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
                $PagePost->data['Page']['status'] = 0;

              /* 権限あり */
              //承認権限を持っている場合
              } else {
                /* データの整理を行う */
                //現在の承認ステージ
                $now = $pass_stage+1;
                //次の承認ステージ
                $next_stage = $now+1;
                //ブログ投稿記事の情報を取得する。
                $post['ApprovalPage'] = $PagePost->data['Page'];
                $post['ApprovalPage']['page_id'] = $PagePost->data['Page']['id'];
                $post['ApprovalPage']['id'] = $approvalData['ApprovalPage']['id'];
                $post['ApprovalPage']['pass_stage'] = $now;
                //もしもこれが最終権限でなければ、次のステージ権限者を保存しておく。
                if ($settingData['ApprovalLevelSetting']['last_stage'] != $now) {
                  $post['ApprovalPage']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$next_stage.'_approver_id'];
                }

                /* 承認の記録を保存する */
                //承認情報DBへ保存する。
                if ($approvalPage->save($post)) {
                  //pass_stageがlast_stageより大きくなければ（最終承認まで行っていない）、非公開は続行。
                  if ($post['ApprovalPage']['pass_stage'] < $last_stage) {
                    $PagePost->data['Page']['status'] = 0;
                    //メールの送信（最終までいけば送信は不要）
                    $this->_sendApprovalMail($settingData, $approvalData, $PagePost->data, 1);
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
      } elseif ($PagePost->data['Approval']['approval_flag'] == 2) {
        /* 現在の状況を取得 */
        $approvalData = $approvalPage->find('first', array('conditions' => array(
          'ApprovalPage.page_id' => $PagePost->data['Page']['id']
        )));

        /* 承認却下の処理実行 */
        //すでに承認を１段階以上受けている場合
        if ($approvalData['ApprovalPage']['pass_stage'] != 0) {
          //次の権限者を１段階前に戻す
          $prevStage = $approvalData['ApprovalPage']['pass_stage'] -1;
          if ($prevStage == 0) { //-1した結果、0なら申請まで戻る。
            $approvalData['ApprovalPage']['next_approver_id'] = 0;
          } else {
            $approvalData['ApprovalPage']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level'.$prevStage.'_approver_id'];
          }
          //通過ステージを１段階戻す。もしも最終段階まで承認が終わっていれば2つ戻す
          if ($approvalData['ApprovalPage']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
            $approvalData['ApprovalPage']['pass_stage'] = $approvalData['ApprovalPage']['pass_stage'] -2;
            if ($approvalData['ApprovalPage']['pass_stage'] < 0) {
              $approvalData['ApprovalPage']['pass_stage'] = 0;
            }
          } else {
            $approvalData['ApprovalPage']['pass_stage'] = $approvalData['ApprovalPage']['pass_stage'] -1;
          }

        //第１段階で却下された場合、次の権限者は0にする。
        } else {
          $approvalData['ApprovalPage']['next_approver_id'] = 0;
        }

        /* 却下の記録を保存する */
        //承認情報DBを更新する
        if ($approvalPage->save($approvalData)) {
          //常に非公開状態（承認が下るまでは非公開にする）
          $PagePost->data['Page']['status'] = 0;
          //メールの送信
          $this->_sendApprovalMail($settingData, $approvalData, $PagePost->data, 2);
          return true;
        }

      //------------------------------
      // 申請（新規登録or最初まで却下されてきた場合）
      //------------------------------
      } elseif ($PagePost->data['Approval']['approval_flag'] == 3){
        //現在の承認情報データの取得
        //（一度却下されたあとの場合はデータが存在する。）
        if (!empty($PagePost->data['Page']['id'])) {
          $approvalData = $approvalPage->find('first', array('conditions' => array(
            'ApprovalPage.page_id' => $PagePost->data['Page']['id']
          )));
        }
        //すでに承認情報データがある（却下されて最初の段階まで戻った場合）
        if (!empty($approvalData)) {
          //承認情報DBへデータを挿入する。
          $post['ApprovalPage'] = $PagePost->data['Page'];
          //IDだけは一旦捨てる。
          $post['ApprovalPage']['id'] = $approvalData['ApprovalPage']['id'];
          //申請段階のため通過ステージは0にする
          $post['ApprovalPage']['pass_stage'] = 0;
          //申請段階のため次のステージは必ず１
          $post['ApprovalPage']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level1_approver_id'];
          //承認申請を行う段階で選択されたカテゴリを取得する。
          if (!empty($PagePost->data['Page']['page_category_id'])) {
              $post['ApprovalPage']['next_approver_id'] = $PagePost->data['Page']['page_category_id'];
          }

          /* 申請の記録を保存する */
          if ($approvalPage->save($post)) {
            //常に非公開状態（承認が下るまでは非公開にする）
            $PagePost->data['Page']['status'] = 0;
            //メールの送信
            $this->_sendApprovalMail($settingData, $approvalData, $PagePost->data, 3);
            return true;
          }

        //承認情報データがない場合
        } else {
          //承認情報DBへデータを挿入する。
          $post['ApprovalPage'] = $PagePost->data['Page'];
          //idがある場合（一度保留を経て申請）。
          if (!empty($PagePost->data['Page']['id'])) {
            $post['ApprovalPage']['page_id'] = $PagePost->data['Page']['id'];
          //idがない場合（新規作成）
          } else {
            //ブログの最終IDを取得する。getLastInsertID();が効かない・・・
            App::import('Model', 'Page');
            $pageModel = new Page();
            $pageLastData = $pageModel->find('first', array('order' => array('Page.id DESC')));
            $post['ApprovalPage']['page_id'] = (int)$pageLastData['Page']['id'] +1;
          }
          $post['ApprovalPage']['id'] = '';
          unset($post['ApprovalPage']['id']);
          //申請段階のため通過ステージは0にする
          $post['ApprovalPage']['pass_stage'] = 0;
          //申請段階のため次のステージは必ず１
          $post['ApprovalPage']['next_approver_id'] = $settingData['ApprovalLevelSetting']['level1_approver_id'];

          /* 申請の記録を保存する */
          if ($approvalPage->save($post)) {
            //常に非公開状態（承認が下るまでは非公開にする）
            $PagePost->data['Page']['status'] = 0;
            //メールの送信
            $this->_sendApprovalMail($settingData, null, $PagePost->data, 3);
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
  * 申請・承認・差戻し連絡メール送信
  *
  * @param   array $setting
  * @param   array   $approvalData
  * @param   array   $postData
  * @param   array   $approvalType
  * @return  boolean
  * @access  public
  */
  private function _sendApprovalMail($setting, $approvalData = null, $postData, $approvalType){
    /* インポート */
    //コンポーネント
    /* TODO メール送信にBcEmailComponentを使用する
    App::import('Component', 'BcEmail');
    */
    //モデル
    App::import('Model', 'User');
    $userModel = new User();
    //サイト設定
    App::import('Model', 'SiteConfig');
    $siteConfigModel = new SiteConfig();
    $siteConfigs = $siteConfigModel->find('first', array('conditions' => array(
      'SiteConfig.name' => 'email'
    )));
    /* 情報の整理 */
    //タイプ
    $type = $setting['ApprovalLevelSetting']['type'];

    //------------------------------
    // 固定ページ
    //------------------------------
    if ($type == 'page') {
      //承認タイプによって内容を変更する
      switch ($approvalType) {
        case 1:
          $approvalTypeVal = '承認申請';
          $firstMes = '表題の固定ページについて、前段階の承認権限者より承認が下りました。' . "\n";
          $firstMes .= '内容をお確かめのうえ、承認処理をお願いします。' . "\n". "\n";
          break;
        case 2:
          $approvalTypeVal = '差戻通知';
          $firstMes = '表題の固定ページについて、次段階の承認権限者より差戻しがありました。' . "\n";
          $firstMes .= '内容をお確かめのうえ、承認処理をお願いします。' . "\n". "\n";
          break;
        case 3:
          $approvalTypeVal = '承認申請';
          $firstMes = '表題の固定ページについて、作成者より承認申請がありました。' . "\n";
          $firstMes .= '内容をお確かめのうえ、承認処理をお願いします。' . "\n". "\n";
          break;
        default:
          break;
      }

      //メールを送るべき相手の段階
      if (!empty($approvalData)) {
        //ただし差戻し後の申請段階だとpass_stageが0になっている。
        //申請時のメールは常に第１段階に権限者へ渡る。
        if ($approvalType == 3) {
          $passStage = 1;
        } else {
          $passStage = $approvalData['ApprovalPage']['pass_stage'];
        }

      //新規作成時は１段目の権限者に送る。
      } else {
        $passStage = 1;
      }

      //タイトルの確認
      $title = $postData['Page']['title'];
      //送信先のタイプ
      if ($passStage != 0) { //最初の段階でなければgroupかuserかを取得
        $approverType = $setting['ApprovalLevelSetting']['level'.$passStage.'_type'];
      } else {
        $approverType = 'user'; //最初の段階まで戻っていれば作成者（user）にメールする。
      }

      //最初の申請前の段階まで戻った時は作成者に送る
      //そうでなければ権限者に送る。
      if ($approvalData['ApprovalPage']['next_approver_id'] != 0) {
        //送信先のユーザーを特定する。
        if ($approverType == 'user') {
          //ユーザーID
          $userID = $setting['ApprovalLevelSetting']['level'.$passStage.'_approver_id'];
          //ユーザー情報の取得
          $userData = $userModel->findById($userID);
        } else {
          //グループID
          $groupID = $setting['ApprovalLevelSetting']['level'.$passStage.'_approver_id'];
          $userDatas = $userModel->find('all', array(
            'conditions' => array(
              'User.user_group_id' => $groupID
          )));
        }

      //申請の前まで戻ってしまった場合。
      } else {
        $userID = $postData['Page']['author_id'];
        $userData = $userModel->findById($userID);
      }

      //メッセージの取得
      $mailData['message'] = '';
      if (!empty($postData['Approval']['approval_comment'])) {
        $mailData['message'] = $postData['Approval']['approval_comment'];
      }

      /* メール送信設定 */
      // TODO BcEmailComponentを使って送信する。
      mb_language("japanese");
      mb_internal_encoding("UTF-8");
      $subject = "【".$approvalTypeVal."】" . $title;
      $from = $siteConfigs['SiteConfig']['value'];
      //メールの内容作成
      $body = $firstMes ."■申し送り事項". "\n" . $mailData['message'] . "\n" . "\n" . "以上";

      //送信処理
      if ($approverType == 'user') {
        if (!empty($userData['User']['email'])) {
          //メールの送信処理実行
          $to = $userData['User']['email'];
          mb_send_mail($to,$subject,$body,"From:".$from);
        }
      //グループだったら全員にループしつつメールする。
      } elseif ($approverType == 'group')  {
        if (!empty($userDatas)) {
          foreach ($userDatas as $data) {
            if (!empty($data['User']['email'])) {
              //メールの送信処理実行
              $to = $userData['User']['email'];
              mb_send_mail($to,$subject,$body,"From:".$from);
            }
          }
        }
      }

    //------------------------------
    // ブログ
    //------------------------------
    } else {
      //承認タイプによって内容を変更する
      switch ($approvalType) {
        case 1:
          $approvalTypeVal = '承認申請';
          $firstMes = '表題のブログ記事について、前段階の承認権限者より承認が下りました。' . "\n";
          $firstMes .= '内容をお確かめのうえ、承認処理をお願いします。' . "\n". "\n";
          break;
        case 2:
          $approvalTypeVal = '差戻通知';
          $firstMes = '表題のブログ記事について、次段階の承認権限者より差戻しがありました。' . "\n";
          $firstMes .= '内容をお確かめのうえ、承認処理をお願いします。' . "\n". "\n";
          break;
        case 3:
          $approvalTypeVal = '承認申請';
          $firstMes = '表題のブログ記事について、作成者より承認申請がありました。' . "\n";
          $firstMes .= '内容をお確かめのうえ、承認処理をお願いします。' . "\n". "\n";
          break;
        default:
          break;
      }

      //メールを送るべき相手の段階（0の時は作成者に送ることになる）
      if (!empty($approvalData)) {
        //ただし差戻し後の申請段階だとpass_stageが0になっている。
        //申請時のメールは常に第１段階に権限者へ渡る。
        if ($approvalType == 3) {
          $passStage = 1;
        } else {
          $passStage = $approvalData['ApprovalPost']['pass_stage'];
        }
      //新規作成時は１段目の権限者に送る。
      } else {
        $passStage = 1;
      }

      //タイトルの確認
      $title = $postData['BlogPost']['name'];
      //送信先のタイプ
      if ($passStage != 0) { //最初の段階でなければgroupかuserかを取得
        $approverType = $setting['ApprovalLevelSetting']['level'.$passStage.'_type'];
      } else {
        $approverType = 'user'; //最初の段階まで戻っていれば作成者（user）にメールする。
      }
//echo $passStage;exit();
      //申請の前段階まで戻った場合は作成者にメールする。
      //そうでなければ、権限者へメールする。
      if ($approvalData['ApprovalPost']['next_approver_id'] != 0) {
        //送信先のユーザーを特定する。
        if ($approverType == 'user') {
          //ユーザーID
          $userID = $setting['ApprovalLevelSetting']['level'.$passStage.'_approver_id'];
          //ユーザー情報の取得
          $userData = $userModel->findById($userID);
        } else {
          //グループID
          $groupID = $setting['ApprovalLevelSetting']['level'.$passStage.'_approver_id'];
          $userDatas = $userModel->find('all', array(
            'conditions' => array(
              'User.user_group_id' => $groupID
          )));
        }

      //申請の前段階まで戻ってしまった
      } else {
        $userID = $postData['BlogPost']['user_id'];
        $userData = $userModel->findById($userID);
      }

      //メッセージの取得
      $mailData['message'] = '';
      if (!empty($postData['Approval']['approval_comment'])) {
        $mailData['message'] = $postData['Approval']['approval_comment'];
      }

      /* メール送信設定 */
      // TODO BcEmailComponentを使って送信する。
      mb_language("japanese");
      mb_internal_encoding("UTF-8");
      $subject = "【".$approvalTypeVal."】" . $title;
      $from = $siteConfigs['SiteConfig']['value'];
      //メールの内容作成
      $body = $firstMes ."■申し送り事項". "\n" . $mailData['message'] . "\n" . "\n" . "以上";

      //送信処理
      if ($approverType == 'user') {
        if (!empty($userData['User']['email'])) {
          //メールの送信処理実行
          $to = $userData['User']['email'];
          mb_send_mail($to,$subject,$body,"From:".$from);
        }
      //グループだったら全員にループしつつメールする。
      } elseif ($approverType == 'group')  {
        if (!empty($userDatas)) {
          foreach ($userDatas as $data) {
            if (!empty($data['User']['email'])) {
              //メールの送信処理実行
              $to = $userData['User']['email'];
              mb_send_mail($to,$subject,$body,"From:".$from);
            }
          }
        }
      }
    }

  }

}
