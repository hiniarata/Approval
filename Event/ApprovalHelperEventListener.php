<?php
/*
* 承認プラグイン
* 承認フォーム生成 イベントリスナー
*
* PHP 5.4.x
*
* @copyright    Copyright (c) hiniarata co.ltd
* @link         https://hiniarata.jp
* @package      Approval Plugin Project
* @since        ver.0.9.0
*/

/**
* ヘルパー イベントリスナー
*
* @package baser.plugins.approval
*/
class ApprovalHelperEventListener extends BcHelperEventListener {

  /**
  * イベント
  *
  * @var array
  * @access public
  */
  public $events = array(
      'Form.afterCreate',
      'Form.afterForm'
  );

  /**
  * 承認確認メッセージ出力
  * 承認設定がある場合に必要なメッセージを
  * 細かく条件わけして出力する。
  *
  * @return  void
  * @access  public
  */
  public function formAfterCreate(CakeEvent $event){
    //---------------------------------
    // ブログの投稿フォーム
    //---------------------------------
    if($event->data['id'] == 'BlogPostForm') {
      //Viewのデータを取得する。
      $View = $event->subject();

      /* 新規作成でない場合（編集） */
      if ($View->view != 'admin_add') {
        /* データの整理 */
        //パラメータからブログのコンテンツIDを取得する。
        $blogContentId = $View->request->params['pass'][0];
        //承認レベル設定モデルを取得する。
        App::import('Model', 'Approval.ApprovalLevelSetting');
        $approvalLevelSetting = new ApprovalLevelSetting();
        //まずはカテゴリの設定を確認する
        $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.category_id' => $View->data['BlogPost']['blog_category_id'],
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1
        )));
        if (empty($settingData)) {
          $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
            'ApprovalLevelSetting.blog_content_id' => $blogContentId,
            'ApprovalLevelSetting.type' => 'blog',
            'ApprovalLevelSetting.publish' => 1
          )));
        }
        //承認設定の中に、特にカテゴリでの設定があれば別で取得する。
        $categorySettingData = $approvalLevelSetting->find('all', array('conditions' => array(
          'ApprovalLevelSetting.blog_content_id' => $blogContentId,
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1,
          'NOT' => array(
            'ApprovalLevelSetting.category_id' => 0
        ))));
        //承認設定の中に、特にこのブログコンテンツ全体への設定があれば別で取得する。
        $contentSettingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.blog_content_id' => $blogContentId,
          'ApprovalLevelSetting.category_id' => 0,
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1
        )));

        /* 設定がある場合は適時メッセージを表示する */
        if (!empty($settingData)) {
          /* データの取得 */
          //セッション情報からログイン中のユーザー情報を取得する。
          App::uses('CakeSession', 'Model/Datasource');
          $Session = new CakeSession();
          $user = $Session->read('Auth.User');
          //承認記事を確認する。
          App::import('Model', 'Approval.ApprovalPost');
          $approvalPost = new ApprovalPost();
          //承認情報・承認待ちモデルからデータを探す
          $approvalPostData = $approvalPost->find('first',array('conditions' => array(
            'ApprovalPost.post_id' => $View->request->params['pass'][1]
          )));

          /* 共通フォームボタン非表示スクリプト */
          //必要に応じて以下で出力するメッセージ部分に追加する。
          $hideButton = '<script type="text/javascript">
          $(function(){
            $("div.submit").css("display","none");
          });
          </script>';

          //データが存在すれば処理にはいる（基本的にはあるはず。）
          if (!empty($approvalPostData)) {
            $nowStage = $approvalPostData['ApprovalPost']['pass_stage']+1;
            $nowApprovalType = $settingData['ApprovalLevelSetting']['level'.$nowStage.'_type'];
            $nowApprovalId = $settingData['ApprovalLevelSetting']['level'.$nowStage.'_approver_id'];
            $last_approver_id = $settingData['ApprovalLevelSetting']['level'.$settingData['ApprovalLevelSetting']['last_stage'].'_approver_id'];

            //通過レベルと最終レベルが一致していれば、最後まで来ている。
            if ($approvalPostData['ApprovalPost']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
              //もしも自分が最終権限者でなければメッセージ
              if ($last_approver_id != $user['id']) {
                $message = $hideButton.'<div id="MessageBox"><div id="flashMessage" class="notice-message">この記事は最終権限者の承認を得ています。<br><span style="font-size:13px;color:#666;font-weight:normal;">変更するには権限者からの差し戻しが必要です。</spam></div></div>';
                $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

              } else {
                $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">この記事は最終承認済みです。<br><span style="font-size:13px;color:#666;font-weight:normal;">差し戻すことで、下位権限者でも編集が出来るようになります（その間、記事は非公開になります）。</span></div></div>';
                $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
              }
            }

            //承認者IDが0の時は、一度申請されて差し戻されたもの。
            if ($approvalPostData['ApprovalPost']['next_approver_id'] == 0){
              //カテゴリ設定がある場合
              if(!empty($categorySettingData)){
                //コンテンツ全体にも設定がある
                if(!empty($contentSettingData)){
                  $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このブログでは記事を公開するのに権限者の承認が必要です。ただし、承認権限者の設定はカテゴリによって異なります。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

                //カテゴリのみに設定がある
                } else{
                  $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このブログでは特定のカテゴリにおいて、記事を公開するのに権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
                }

              //カテゴリ設定がない場合。
              } else {
                $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このブログでは記事を公開するのに権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
                $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
              }
            }

            //承認権限者のタイプを確認する。
            switch($nowApprovalType){
              case 'user':
                //ログインユーザーと権限者の不一致
                if ($nowApprovalId != $user['id']) {
                  //権限者でない場合、警告メッセージと共にactionの先を変更しておく。
                  $message = $hideButton.'<div id="MessageBox"><div id="flashMessage" class="alert-message">現在、この記事は承認申請中です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（承認権限者のみ編集できます。）</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

                //承認権限がある。
                } else {
                  //権限者であることを表示する。
                  $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、この記事はあなたの承認を求めています。</div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
                }
                break;

              case 'group':
                //ログインユーザーと権限者の不一致
                if ($nowApprovalId != $user['UserGroup']['id']) {
                  //権限者でない場合、警告メッセージと共にactionの先を変更しておく。
                  $message = $hideButton.'<div id="MessageBox"><div id="flashMessage" class="alert-message">現在、この記事は承認申請中です。<br><span style="font-size:13px;color:#666;font-weight:normal;">申請が承認または拒否されるまで承認権限者のみ編集できます。</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

                //承認権限がある。
                } else {
                  //権限者であることを表示する。
                  $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、この記事はあなたが属するグループの承認を求めています。<br><span style="font-size:13px;color:#666;font-weight:normal;">この記事に対する承認は、当該グループに属するユーザーなら誰でも可能です。</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
                }
                break;
            }

          //承認情報DBにない場合は、途中で承認設定がついた場合。メッセージを出力する。
          } else {
            $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">この記事を公開するには権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
            $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
          }
        }
      //新規登録時にメッセージを出力しておく。
      } else {
        //承認レベル設定モデルを取得する。
        App::import('Model', 'Approval.ApprovalLevelSetting');
        $blogContentId = $View->request->params['pass'][0];
        $approvalLevelSetting = new ApprovalLevelSetting();
        //ブログ全体、またはカテゴリの設定があるか確認する。
        //承認設定の中に、特にカテゴリでの設定があれば取得する。
        $categorySettingData = $approvalLevelSetting->find('all', array('conditions' => array(
          'ApprovalLevelSetting.blog_content_id' => $blogContentId,
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1,
          'NOT' => array(
            'ApprovalLevelSetting.category_id' => 0
        ))));
        //承認設定の中に、特にこのブログコンテンツ全体への設定があれば取得する。
        $contentSettingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.blog_content_id' => $blogContentId,
          'ApprovalLevelSetting.category_id' => 0,
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1
        )));
        if (!empty($categorySettingData)) {
          if (!empty($contentSettingData)) {
            $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このブログでは記事を公開するのに権限者の承認が必要です<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
            $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
          } else {
            $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このブログでは、特定のカテゴリの記事を公開するのに権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
            $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
          }
        } else {
          if (!empty($contentSettingData)) {
            $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このブログでは記事を公開するのに権限者の承認が必要です<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
            $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
          }
        }
      }


  //---------------------------------
  // 固定ページの投稿フォーム
  //---------------------------------
  } elseif ($event->data['id'] == 'PageForm'){
    //Viewのデータを取得する。
    $View = $event->subject();
    /* 新規作成でない場合（編集） */
    if ($View->view != 'admin_add') {
      /* データの整理 */
      //承認レベル設定モデルを取得する。
      App::import('Model', 'Approval.ApprovalLevelSetting');
      $approvalLevelSetting = new ApprovalLevelSetting();
      //まずはカテゴリの設定を確認する
      $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
        'ApprovalLevelSetting.category_id' => $View->data['Page']['page_category_id'],
        'ApprovalLevelSetting.type' => 'page',
        'ApprovalLevelSetting.publish' => 1
      )));
      if (empty($settingData)) {
        $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.type' => 'page',
          'ApprovalLevelSetting.publish' => 1
        )));
      }
      //承認設定の中に、特にカテゴリでの設定があれば別で取得する。
      $categorySettingData = $approvalLevelSetting->find('all', array('conditions' => array(
        'ApprovalLevelSetting.type' => 'page',
        'ApprovalLevelSetting.publish' => 1,
        'NOT' => array(
          'ApprovalLevelSetting.category_id' => 0
      ))));

      /* 設定がある場合は適時メッセージを表示する */
      if (!empty($settingData)) {
        /* データの取得 */
        //セッション情報からログイン中のユーザー情報を取得する。
        App::uses('CakeSession', 'Model/Datasource');
        $Session = new CakeSession();
        $user = $Session->read('Auth.User');
        //承認記事を確認する。
        App::import('Model', 'Approval.ApprovalPage');
        $approvalPage = new ApprovalPage();
        //承認情報・承認待ちモデルからデータを探す
        $approvalPageData = $approvalPage->find('first',array('conditions' => array(
          'ApprovalPage.page_id' => $View->request->params['pass'][0]
        )));

        /* 共通フォームボタン非表示スクリプト */
        //必要に応じて以下で出力するメッセージ部分に追加する。
        $hideButton = '<script type="text/javascript">
        $(function(){
          $("div.submit").css("display","none");
        });
        </script>';

        //データが存在すれば処理にはいる（基本的にはあるはず。）
        if (!empty($approvalPageData)) {
          $nowStage = $approvalPageData['ApprovalPage']['pass_stage']+1;
          $nowApprovalType = $settingData['ApprovalLevelSetting']['level'.$nowStage.'_type'];
          $nowApprovalId = $settingData['ApprovalLevelSetting']['level'.$nowStage.'_approver_id'];
          $last_approver_id = $settingData['ApprovalLevelSetting']['level'.$settingData['ApprovalLevelSetting']['last_stage'].'_approver_id'];

          //通過レベルと最終レベルが一致していれば、最後まで来ている。
          if ($approvalPageData['ApprovalPage']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
            //もしも自分が最終権限者でなければメッセージ
            if ($last_approver_id != $user['id']) {
              $message = $hideButton.'<div id="MessageBox"><div id="flashMessage" class="notice-message">このページは最終権限者の承認を得ています。<br><span style="font-size:13px;color:#666;font-weight:normal;">変更するには権限者からの差し戻しが必要です。</spam></div></div>';
              $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

            } else {
              $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このページは最終承認済みです。<br><span style="font-size:13px;color:#666;font-weight:normal;">差し戻すことで、下位権限者でも編集が出来るようになります（その間、記事は非公開になります）。</span></div></div>';
              $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
            }
          }

          //承認者IDが0の時は、一度申請されて差し戻されたもの。
          if ($approvalPageData['ApprovalPage']['next_approver_id'] == 0){
            //カテゴリ設定がある場合
            if(!empty($categorySettingData)){
                $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、特定のカテゴリにおいて記事を公開するのに権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
                $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

            //カテゴリ設定がない場合。
            } else {
              $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、ページを公開するのに権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
              $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
            }
          }

          //もしも差し戻しを受けるなど、データはあるが申請段階のものであれば、
          //IDが承認権限者と一致しても承認段階にないので、メッセージを出力しない。
          if ($approvalPageData['ApprovalPage']['pass_stage'] != 0 && $approvalPageData['ApprovalPage']['next_approver_id'] != 0) {
            //承認権限者のタイプを確認してメッセージを出す。
            switch($nowApprovalType){
              case 'user':
                //ログインユーザーと権限者の不一致
                if ($nowApprovalId != $user['id']) {
                  //権限者でない場合、警告メッセージと共にactionの先を変更しておく。
                  $message = $hideButton.'<div id="MessageBox"><div id="flashMessage" class="alert-message">現在、このページは承認申請中です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（承認権限者のみ編集できます。）</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

                //承認権限がある。
                } else {
                  //権限者であることを表示する。
                  $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、このページはあなたの承認を求めています。</div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
                }
                break;

              case 'group':
                //ログインユーザーと権限者の不一致
                if ($nowApprovalId != $user['UserGroup']['id']) {
                  //権限者でない場合、警告メッセージと共にactionの先を変更しておく。
                  $message = $hideButton.'<div id="MessageBox"><div id="flashMessage" class="alert-message">現在、このページは承認申請中です。<br><span style="font-size:13px;color:#666;font-weight:normal;">申請が承認または拒否されるまで承認権限者のみ編集できます。</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);

                //承認権限がある。
                } else {
                  //権限者であることを表示する。
                  $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、このページはあなたが属するグループの承認を求めています。<br><span style="font-size:13px;color:#666;font-weight:normal;">この記事に対する承認は、当該グループに属するユーザーなら誰でも可能です。</span></div></div>';
                  $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
                }
                break;
            }
          }

        //承認情報DBにない場合は、途中で承認設定がついた場合。メッセージを出力する。
        } else {
          $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">このページを公開するには権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
          $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
        }
      }

    //新規登録時にメッセージを出力しておく。
    } else {
      //承認レベル設定モデルを取得する。
      App::import('Model', 'Approval.ApprovalLevelSetting');
      $approvalLevelSetting = new ApprovalLevelSetting();
      //承認設定の中に、特にカテゴリでの設定があれば取得する。
      $categorySettingData = $approvalLevelSetting->find('all', array('conditions' => array(
        'ApprovalLevelSetting.type' => 'page',
        'ApprovalLevelSetting.publish' => 1,
        'NOT' => array(
          'ApprovalLevelSetting.category_id' => 0
      ))));
      //承認設定の中に、特に固定ページ全体への設定があれば取得する。
      $contentSettingData = $approvalLevelSetting->find('first', array('conditions' => array(
        'ApprovalLevelSetting.category_id' => 0,
        'ApprovalLevelSetting.type' => 'page',
        'ApprovalLevelSetting.publish' => 1
      )));
      if (!empty($categorySettingData)) {
        if (!empty($contentSettingData)) {
          $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、ページを公開するのに権限者の承認が必要です<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
          $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
        } else {
          $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、特定のカテゴリのページを公開するのに権限者の承認が必要です。<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
          $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
        }
      } else {
        if (!empty($contentSettingData)) {
          $message = '<div id="MessageBox"><div id="flashMessage" class="notice-message">現在、ページを公開するのに権限者の承認が必要です<br><span style="font-size:13px;color:#666;font-weight:normal;">（まだ承認申請されていません。誰でも編集できますが「公開」は出来ません。）</span></div></div>';
          $event->data['out'] = str_replace ('<form', $message.'<form', $event->data['out']);
        }
      }
    }
  }
    //値を返す
    return $event->data['out'];
  }




  /**
  * 承認選択フォーム出力
  * 承認設定がある場合に必要なメッセージを出力する。
  *
  * @return  void
  * @access  public
  */
  public function formAfterForm(CakeEvent $event) {
    //ブログ＆固定ページ共通で、申し送り事項の表示処理を差し込む。
    //差し戻し、承認の時のみ、申し送り入力欄を表示する。
    $approval_comment_toggle = '
    <div id="ApprovalCommentNoUse">
    使用しません。
    </div>
    <script type="text/javascript">
    $(function(){
      //初期表示
      $("#ApprovalApprovalComment").css("display","none");
      //値の変化を取得して表示を切り返える。
      $("#ApprovalApprovalFlag").change(function(){
        var flag = $("#ApprovalApprovalFlag").val();
        // 保留以外で表示する
        if (flag > 0) {
          $("#ApprovalApprovalComment").css("display","inline");
          $("#ApprovalCommentNoUse").css("display","none");
        } else {
          $("#ApprovalApprovalComment").css("display","none");
          $("#ApprovalCommentNoUse").css("display","block");
        }
      });
    });
    </script>
    ';

    //処理を実行するのは、ブログと固定ページ
    //---------------------------------
    // ブログ投稿フォーム
    //---------------------------------
    if($event->data['id'] == 'BlogPostForm') {
      // BcFormHelperを利用する為、Viewを取得
      $View = $event->subject();
      //ブログのコンテンツIDを取得する。
      $blogContentId = $View->request->params['pass'][0];

      //承認レベル設定モデルを取得する。
      App::import('Model', 'Approval.ApprovalLevelSetting');
      $approvalLevelSetting = new ApprovalLevelSetting();
      //カテゴリの設定を優先する
      if(!empty($View->data['BlogPost']['blog_category_id'])){
        $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.category_id' => $View->data['BlogPost']['blog_category_id'],
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1
        )));
      }
      if (empty($settingData)) {
        $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.blog_content_id' => $blogContentId,
          'ApprovalLevelSetting.type' => 'blog',
          'ApprovalLevelSetting.publish' => 1
        )));
      }

      //セッション情報からログイン中のユーザー情報を取得する。
      App::uses('CakeSession', 'Model/Datasource');
      $Session = new CakeSession();
      $user = $Session->read('Auth.User');

      //設定情報を確認する。
      if (!empty($settingData)) {
        //新規作成の場合は、保留か承認申請かに内容を変更する。
        if ($View->request->params['action'] == 'admin_add') {
          $options = array(0 => '保留', 3 => '承認を申請する');
          //承認設定のある新規作成は誰でも書けるので、常に表示する。
          $event->data['fields'][] = array(
              'title'    => '承認設定',
              'input'    =>
              $View->BcForm->input('Approval.approval_flag',
                array(
                  'type' => 'select',
                  'options' => $options
              ))
          );
          //申送入力欄
          $event->data['fields'][] = array(
              'title'    => '申し送り事項',
              'input'    =>
              $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
          );

        //編集（edit）の場合
        } else {
          //現在の承認済段階を確認する。
          //承認情報・承認待ちモデルを取得する。
          App::import('Model', 'Approval.ApprovalPost');
          $approvalPost = new ApprovalPost();
          //承認情報・承認待ちモデルからデータを探す
          $approvalPostData = $approvalPost->find('first',array('conditions' => array(
            'ApprovalPost.post_id' => $View->request->params['pass'][1]
          )));

          //データがある場合
          if (!empty($approvalPostData)) {
            //通過済みの承認ステージ
            $pass_stage = $approvalPostData['ApprovalPost']['pass_stage'];
            if(empty($pass_stage)){
              $pass_stage = 0;
            }
            //現在のステージを確認する（最終承認後はそれ以上ない）
            if ($approvalPostData['ApprovalPost']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
              $nowStage = $approvalPostData['ApprovalPost']['pass_stage'];
            } else {
              $nowStage = $approvalPostData['ApprovalPost']['pass_stage']+1;
            }
            //現在の承認権限者タイプ
            $nowApprovalType = $settingData['ApprovalLevelSetting']['level'.$nowStage.'_type'];

            //編集モードの場合でも、next_approver_idが0の時は、却下で戻ってきたもの。
            if ($pass_stage == 0) {
              //権限者の確認
              switch ($nowApprovalType) {
                case 'user':
                  if ($approvalPostData['ApprovalPost']['next_approver_id'] == $user['id']) {
                    //承認権限があるかどうかを判定する。
                    $now_stage = $pass_stage+1;
                    //ユーザー情報を確認する。
                    $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                    $event->data['fields'][] = array(
                        'title'    => '承認設定',
                        'input'    =>
                        $View->BcForm->input('Approval.approval_flag',
                          array(
                            'type' => 'select',
                            'options' => $options
                        ))
                    );
                    //申送入力欄
                    $event->data['fields'][] = array(
                        'title'    => '申し送り事項',
                        'input'    =>
                        $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                    );

                  } else {
                    //自分が権限者でない場合
                    //却下で戻ってきたものの場合は、改めて申請できるので表示する。
                    if ($approvalPostData['ApprovalPost']['next_approver_id'] == 0) {
                      $options = array(0 => '保留', 3 => '承認を申請する');
                      //最初の段階まで戻っていれば、誰もが申請できる。
                      $event->data['fields'][] = array(
                          'title'    => '承認設定',
                          'input'    =>
                          $View->BcForm->input('Approval.approval_flag',
                            array(
                              'type' => 'select',
                              'options' => $options
                          ))
                      );
                      //申送入力欄
                      $event->data['fields'][] = array(
                          'title'    => '申し送り事項',
                          'input'    =>
                          $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                      );

                    }
                  }
                break;

                case 'group':
                    if ($approvalPostData['ApprovalPost']['next_approver_id'] == $user['UserGroup']['id']) {
                      //承認権限があるかどうかを判定する。
                      $now_stage = $pass_stage+1;
                      //ユーザー情報を確認する。
                      $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                      $event->data['fields'][] = array(
                          'title'    => '承認設定',
                          'input'    =>
                          $View->BcForm->input('Approval.approval_flag',
                            array(
                              'type' => 'select',
                              'options' => $options
                          ))
                      );
                      //申送入力欄
                      $event->data['fields'][] = array(
                          'title'    => '申し送り事項',
                          'input'    =>
                          $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                      );

                    } else {
                      //自分が権限者でない場合
                      //却下で戻ってきたものの場合は、改めて申請できるので表示する。
                      if ($approvalPostData['ApprovalPost']['next_approver_id'] == 0) {
                        $options = array(0 => '保留', 3 => '承認を申請する');
                        //最初の段階まで戻っていれば、誰もが申請できる。
                        $event->data['fields'][] = array(
                            'title'    => '承認設定',
                            'input'    =>
                            $View->BcForm->input('Approval.approval_flag',
                              array(
                                'type' => 'select',
                                'options' => $options
                            ))
                        );
                        //申送入力欄
                        $event->data['fields'][] = array(
                            'title'    => '申し送り事項',
                            'input'    =>
                            $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                        );

                      }
                    }
                  break;
                default:
                  break;
              }

            //却下または第１段階ではなく、承認待ちの状態の場合
            } else {
              $now_stage = $pass_stage+1;
              //権限者の確認
              switch ($nowApprovalType) {
                case 'user':
                  //権限者の確認
                  if ($approvalPostData['ApprovalPost']['next_approver_id']  == $user['id']) {
                    //もしも最終段階まで来ていれば「承認する」はいらない。
                    if ($approvalPostData['ApprovalPost']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
                      $options = array(0 => '承認状態を維持', 2 => '差し戻す');
                    } else {
                      $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                    }
                    $event->data['fields'][] = array(
                        'title'    => '承認設定',
                        'input'    =>
                        $View->BcForm->input('Approval.approval_flag',
                          array(
                            'type' => 'select',
                            'options' => $options
                        ))
                    );
                    //申送入力欄
                    $event->data['fields'][] = array(
                        'title'    => '申し送り事項',
                        'input'    =>
                        $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                    );

                  }
                break;
                case 'group':
                  //権限者の確認
                  if ($approvalPostData['ApprovalPost']['next_approver_id']  == $user['UserGroup']['id']) {
                    //もしも最終段階まで来ていれば「承認する」はいらない。
                    if ($approvalPostData['ApprovalPost']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
                      $options = array(0 => '承認状態を維持', 2 => '差し戻す');
                    } else {
                      $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                    }
                    $event->data['fields'][] = array(
                        'title'    => '承認設定',
                        'input'    =>
                        $View->BcForm->input('Approval.approval_flag',
                          array(
                            'type' => 'select',
                            'options' => $options
                        ))
                    );
                    //申送入力欄
                    $event->data['fields'][] = array(
                        'title'    => '申し送り事項',
                        'input'    =>
                        $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                    );
                  }
                break;
              }
            }

          //データがない場合は、新規作成後に承認機能を付与した場合。
          } else {
            $options = array(0 => '保留', 3 => '承認を申請する');
            //データがないため新規作成と同じく、だれにでも表示する。
            $event->data['fields'][] = array(
                'title'    => '承認設定',
                'input'    =>
                $View->BcForm->input('Approval.approval_flag',
                  array(
                    'type' => 'select',
                    'options' => $options
                ))
            );
            //申送入力欄
            $event->data['fields'][] = array(
                'title'    => '申し送り事項',
                'input'    =>
                $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
            );
          }
        }
      }

    //---------------------------------
    // 固定ページ投稿フォーム
    //---------------------------------
    } elseif($event->data['id'] == 'PageForm') {
      // BcFormHelperを利用する為、Viewを取得
      $View = $event->subject();
      //承認レベル設定モデルを取得する。
      App::import('Model', 'Approval.ApprovalLevelSetting');
      $approvalLevelSetting = new ApprovalLevelSetting();
      //カテゴリの設定を優先する
      if(!empty($View->data['Page']['page_category_id'])){
        $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.category_id' => $View->data['Page']['page_category_id'],
          'ApprovalLevelSetting.type' => 'page',
          'ApprovalLevelSetting.publish' => 1
        )));
      }
      if (empty($settingData)) {
        $settingData = $approvalLevelSetting->find('first', array('conditions' => array(
          'ApprovalLevelSetting.category_id' => 0,
          'ApprovalLevelSetting.type' => 'page',
          'ApprovalLevelSetting.publish' => 1
        )));
      }

      //セッション情報からログイン中のユーザー情報を取得する。
      App::uses('CakeSession', 'Model/Datasource');
      $Session = new CakeSession();
      $user = $Session->read('Auth.User');

      //設定情報を確認する。
      if (!empty($settingData)) {
        //新規作成の場合は、保留か承認申請かに内容を変更する。
        if ($View->request->params['action'] == 'admin_add') {
          $options = array(0 => '保留', 3 => '承認を申請する');
          //承認設定のある新規作成は誰でも書けるので、常に表示する。
          $event->data['fields'][] = array(
              'title'    => '承認設定',
              'input'    =>
              $View->BcForm->input('Approval.approval_flag',
                array(
                  'type' => 'select',
                  'options' => $options
              ))
          );
          //申送入力欄
          $event->data['fields'][] = array(
              'title'    => '申し送り事項',
              'id' => 'test',
              'input'    =>
              $View->BcForm->textarea('Approval.approval_comment') . $approval_comment_toggle
          );

        //編集（edit）の場合
        } else {
          //現在の承認済段階を確認する。
          //承認情報・承認待ちモデルを取得する。
          App::import('Model', 'Approval.ApprovalPage');
          $approvalPage = new ApprovalPage();
          //承認情報・承認待ちモデルからデータを探す
          $approvalPageData = $approvalPage->find('first',array('conditions' => array(
            'ApprovalPage.page_id' => $View->request->params['pass'][0] //ページid
          )));

          //データがある場合
          if (!empty($approvalPageData)) {
            //通過済みの承認ステージ
            $pass_stage = $approvalPageData['ApprovalPage']['pass_stage'];
            if(empty($pass_stage)){
              $pass_stage = 0;
            }
            //現在のステージを確認する（最終承認後はそれ以上ない）
            if ($approvalPageData['ApprovalPage']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
              $nowStage = $approvalPageData['ApprovalPage']['pass_stage'];
            } else {
              $nowStage = $approvalPageData['ApprovalPage']['pass_stage']+1;
            }
            //現在の承認権限者タイプ
            $nowApprovalType = $settingData['ApprovalLevelSetting']['level'.$nowStage.'_type'];

            //編集モードの場合でも、next_approver_idが0の時は、却下で戻ってきたもの。
            if ($pass_stage == 0) {
              //権限者の確認
              switch ($nowApprovalType) {
                case 'user':
                  if ($approvalPageData['ApprovalPage']['next_approver_id'] == $user['id']) {
                    //承認権限があるかどうかを判定する。
                    $now_stage = $pass_stage+1;
                    //ユーザー情報を確認する。
                    $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                    $event->data['fields'][] = array(
                        'title'    => '承認設定',
                        'input'    =>
                        $View->BcForm->input('Approval.approval_flag',
                          array(
                            'type' => 'select',
                            'options' => $options
                        ))
                    );
                    //申送入力欄
                    $event->data['fields'][] = array(
                        'title'    => '申し送り事項',
                        'input'    =>
                        $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                    );

                  } else {
                    //自分が権限者でない場合
                    //却下で戻ってきたものの場合は、改めて申請できるので表示する。
                    if ($approvalPageData['ApprovalPage']['next_approver_id'] == 0) {
                      $options = array(0 => '保留', 3 => '承認を申請する');
                      //最初の段階まで戻っていれば、誰もが申請できる。
                      $event->data['fields'][] = array(
                          'title'    => '承認設定',
                          'input'    =>
                          $View->BcForm->input('Approval.approval_flag',
                            array(
                              'type' => 'select',
                              'options' => $options
                          ))
                      );
                      //申送入力欄
                      $event->data['fields'][] = array(
                          'title'    => '申し送り事項',
                          'input'    =>
                          $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                      );
                    }
                  }
                break;

                case 'group':
                    if ($approvalPageData['ApprovalPage']['next_approver_id'] == $user['UserGroup']['id']) {
                      //承認権限があるかどうかを判定する。
                      $now_stage = $pass_stage+1;
                      //ユーザー情報を確認する。
                      $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                      $event->data['fields'][] = array(
                          'title'    => '承認設定',
                          'input'    =>
                          $View->BcForm->input('Approval.approval_flag',
                            array(
                              'type' => 'select',
                              'options' => $options
                          ))
                      );
                      //申送入力欄
                      $event->data['fields'][] = array(
                          'title'    => '申し送り事項',
                          'input'    =>
                          $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                      );

                    } else {
                      //自分が権限者でない場合
                      //却下で戻ってきたものの場合は、改めて申請できるので表示する。
                      if ($approvalPageData['ApprovalPage']['next_approver_id'] == 0) {
                        $options = array(0 => '保留', 3 => '承認を申請する');
                        //最初の段階まで戻っていれば、誰もが申請できる。
                        $event->data['fields'][] = array(
                            'title'    => '承認設定',
                            'input'    =>
                            $View->BcForm->input('Approval.approval_flag',
                              array(
                                'type' => 'select',
                                'options' => $options
                            ))
                        );
                        //申送入力欄
                        $event->data['fields'][] = array(
                            'title'    => '申し送り事項',
                            'input'    =>
                            $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                        );

                      }
                    }
                  break;
                default:
                  break;
              }

            //却下または第１段階ではなく、承認待ちの状態の場合
            } else {
              $now_stage = $pass_stage+1;
              //権限者の確認
              switch ($nowApprovalType) {
                case 'user':
                  //権限者の確認
                  if ($approvalPageData['ApprovalPage']['next_approver_id']  == $user['id']) {
                    //もしも最終段階まで来ていれば「承認する」はいらない。
                    if ($approvalPageData['ApprovalPage']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
                      $options = array(0 => '承認状態を維持', 2 => '差し戻す');
                    } else {
                      $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                    }
                    $event->data['fields'][] = array(
                        'title'    => '承認設定',
                        'input'    =>
                        $View->BcForm->input('Approval.approval_flag',
                          array(
                            'type' => 'select',
                            'options' => $options
                        ))
                    );
                    //申送入力欄
                    $event->data['fields'][] = array(
                        'title'    => '申し送り事項',
                        'input'    =>
                        $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                    );

                  }
                break;
                case 'group':
                  //権限者の確認
                  if ($approvalPageData['ApprovalPage']['next_approver_id']  == $user['UserGroup']['id']) {
                    //もしも最終段階まで来ていれば「承認する」はいらない。
                    if ($approvalPageData['ApprovalPage']['pass_stage'] == $settingData['ApprovalLevelSetting']['last_stage']) {
                      $options = array(0 => '承認状態を維持', 2 => '差し戻す');
                    } else {
                      $options = array(0 => '保留', 1 => '承認する', 2 => '差し戻す');
                    }
                    $event->data['fields'][] = array(
                        'title'    => '承認設定',
                        'input'    =>
                        $View->BcForm->input('Approval.approval_flag',
                          array(
                            'type' => 'select',
                            'options' => $options
                        ))
                    );
                    //申送入力欄
                    $event->data['fields'][] = array(
                        'title'    => '申し送り事項',
                        'input'    =>
                        $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
                    );

                  }
                break;
              }
            }

          //データがない場合は、新規作成後に承認機能を付与した場合。
          } else {
            $options = array(0 => '保留', 3 => '承認を申請する');
            //データがないため新規作成と同じく、だれにでも表示する。
            $event->data['fields'][] = array(
                'title'    => '承認設定',
                'input'    =>
                $View->BcForm->input('Approval.approval_flag',
                  array(
                    'type' => 'select',
                    'options' => $options
                ))
            );
            //申送入力欄
            $event->data['fields'][] = array(
                'title'    => '申し送り事項',
                'input'    =>
                $View->BcForm->textarea('Approval.approval_comment'). $approval_comment_toggle
            );
          }
        }
      }
    }
    return true;
  }

}
