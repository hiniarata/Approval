<?php
// 担当者切り替えのJS（１〜５）
for($i=1; $i<6; $i++){
  $this->Js->get('.userType'.$i)->event(
      'change',
      $this->Js->request(
          array('controller'=>'approval_blog_configs','action'=>'admin_ajax_get_users', 'plugin' => 'approval'),
          array(
            //'before'=>$this->Js->get('#AjaxLoader')->effect('hide'),
            //'success'=>$this->Js->get('#AjaxLoader')->effect('show'),
            'update' => '#ApprovalLevelSettingLevel'.$i.'ApproverId',
            'dataExpression' => true,
            'data' => '$(".userType'.$i.'").serialize()'
          )
      )
  );
  echo $this->Js->writeBuffer();
}
?>
<script type="text/javascript">
  $(document).ready(function () {
    <?php
    if(!empty($approvalSetting['ApprovalLevelSetting']['last_stage'])){
      echo 'var lastStage = '.$approvalSetting['ApprovalLevelSetting']['last_stage'].' +1;';
    } else {
      echo 'var lastStage = 2;';
    }
    ?>
    //初期表示（１以外を非表示にする）
    for (i=lastStage; i<6; i++) {
      $('#box-level'+i).css('display','none');
    }
    //編集・バリデーションエラーの際は値が入っている。
    var defaultLastStage = $("#ApprovalLevelSettingLastStage").val();
    for (i=2; i<6; i++) {
      if(i <= defaultLastStage){
        $('#box-level'+i).css('display','table-row');
      } else {
        $('#box-level'+i).css('display','none');
      }
    }
    //承認回数の選択が変化した場合、それによって表示を変化させる。
    $('#ApprovalLevelSettingLastStage').change(function(){
      var lastStage = $("#ApprovalLevelSettingLastStage").val();
      for (i=2; i<6; i++) {
        if(i <= lastStage){
          $('#box-level'+i).css('display','table-row');
        } else {
          $('#box-level'+i).css('display','none');
        }
      }
    });
  });
</script>


<!-- form -->
<?php echo $this->BcForm->create('ApprovalLevelSetting') ?>
<div class="section">
  <h2>承認基本設定</h2>
  <table cellpadding="0" cellspacing="0" id="FormTable" class="form-table">
    <tr>
      <th class="col-head">設定対象</th>
      <td class="col-input">
        カテゴリ：<?php echo $pageData['PageCategory']['title'] ?>
      </td>
    </tr>
    <tr>
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.last_stage', '承認段階数') ?></th>
      <td class="col-input">
        <?php
        $options = array(1=>'第１段階', 2=>'第２段階', 3=>'第３段階', 4=>'第４段階', 5=>'第５段階');
        echo $this->BcForm->input('ApprovalLevelSetting.last_stage', array(
          'type' => 'select',
          'options' => $options
        )) ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>承認させたい段階数を設定します。</li>
          </ul>
        </div>
      </td>
    </tr>
  </table>


  <h2>承認権限者の選択</h2>
  <table cellpadding="0" cellspacing="0" id="FormTable" class="form-table">
    <tr id="box-level1">
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.level1_type', '第１段階') ?></th>
      <td class="col-input">タイプ：
        <?php $options = array('user' => 'ユーザー', 'group' => 'グループ') ?>
        <?php echo $this->BcForm->input('ApprovalLevelSetting.level1_type', array(
          'type' => 'radio',
          'class' => 'userType1',
          'options' => $options
        )) ?>
        <?php echo $this->BcForm->error('ApprovalLevelSetting.level1_type') ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>第１段階の承認者タイプを選択します。</li>
            <li>「ユーザー」を選択すると特定のユーザーのみに承認権限が付与されます。「グループ」を選択すると特定のグループに属するユーザー全員に承認権限が付与されます。</li>
          </ul>
        </div>
        <div>
          <select id="ApprovalLevelSettingLevel1ApproverId" name="data[ApprovalLevelSetting][level1_approver_id]">
            <?php echo $defaultOption[1] ?>
          </select>
          <?php echo $this->BcForm->error('ApprovalLevelSetting.level1_approver_id') ?>
        </div>
      </td>
    </tr>


    <tr id="box-level2">
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.level2_type', '第２段階') ?></th>
      <td class="col-input">タイプ：
        <?php $options = array('user' => 'ユーザー', 'group' => 'グループ') ?>
        <?php echo $this->BcForm->input('ApprovalLevelSetting.level2_type', array(
          'type' => 'radio',
          'class' => 'userType2',
          'options' => $options
        )) ?>
        <?php echo $this->BcForm->error('ApprovalLevelSetting.level2_type') ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>第２段階の承認者タイプを選択します。</li>
            <li>「ユーザー」を選択すると特定のユーザーのみに承認権限が付与されます。「グループ」を選択すると特定のグループに属するユーザー全員に承認権限が付与されます。</li>
          </ul>
        </div>
        <div>
          <select id="ApprovalLevelSettingLevel2ApproverId" name="data[ApprovalLevelSetting][level2_approver_id]">
            <?php echo $defaultOption[2] ?>
          </select>
          <?php echo $this->BcForm->error('ApprovalLevelSetting.level2_approver_id') ?>
        </div>
      </td>
    </tr>


    <tr id="box-level3">
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.level3_type', '第３段階') ?></th>
      <td class="col-input">タイプ：
        <?php $options = array('user' => 'ユーザー', 'group' => 'グループ') ?>
        <?php echo $this->BcForm->input('ApprovalLevelSetting.level3_type', array(
          'type' => 'radio',
          'class' => 'userType3',
          'options' => $options
        )) ?>
        <?php echo $this->BcForm->error('ApprovalLevelSetting.level3_type') ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>第３段階の承認者タイプを選択します。</li>
            <li>「ユーザー」を選択すると特定のユーザーのみに承認権限が付与されます。「グループ」を選択すると特定のグループに属するユーザー全員に承認権限が付与されます。</li>
          </ul>
        </div>
        <div>
          <select id="ApprovalLevelSettingLevel3ApproverId" name="data[ApprovalLevelSetting][level3_approver_id]">
            <?php echo $defaultOption[3] ?>
          </select>
          <?php echo $this->BcForm->error('ApprovalLevelSetting.level3_approver_id') ?>
        </div>
      </td>
    </tr>


    <tr id="box-level4">
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.level1_type', '第４段階') ?></th>
      <td class="col-input">タイプ：
        <?php $options = array('user' => 'ユーザー', 'group' => 'グループ') ?>
        <?php echo $this->BcForm->input('ApprovalLevelSetting.level4_type', array(
          'type' => 'radio',
          'class' => 'userType4',
          'options' => $options
        )) ?>
        <?php echo $this->BcForm->error('ApprovalLevelSetting.level4_type') ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>第４段階の承認者タイプを選択します。</li>
            <li>「ユーザー」を選択すると特定のユーザーのみに承認権限が付与されます。「グループ」を選択すると特定のグループに属するユーザー全員に承認権限が付与されます。</li>
          </ul>
        </div>
        <div>
          <select id="ApprovalLevelSettingLevel4ApproverId" name="data[ApprovalLevelSetting][level4_approver_id]">
            <?php echo $defaultOption[4] ?>
          </select>
          <?php echo $this->BcForm->error('ApprovalLevelSetting.level4_approver_id') ?>
        </div>
      </td>
    </tr>


    <tr id="box-level5">
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.level4_type', '第５段階') ?></th>
      <td class="col-input">タイプ：
        <?php $options = array('user' => 'ユーザー', 'group' => 'グループ') ?>
        <?php echo $this->BcForm->input('ApprovalLevelSetting.level5_type', array(
          'type' => 'radio',
          'class' => 'userType5',
          'options' => $options
        )) ?>
        <?php echo $this->BcForm->error('ApprovalLevelSetting.level5_type') ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>第５段階の承認者タイプを選択します。</li>
            <li>「ユーザー」を選択すると特定のユーザーのみに承認権限が付与されます。「グループ」を選択すると特定のグループに属するユーザー全員に承認権限が付与されます。</li>
          </ul>
        </div>
        <div>
          <select id="ApprovalLevelSettingLevel5ApproverId" name="data[ApprovalLevelSetting][level5_approver_id]">
            <?php echo $defaultOption[5] ?>
          </select>
          <?php echo $this->BcForm->error('ApprovalLevelSetting.level5_approver_id') ?>
        </div>
      </td>
    </tr>
  </table>


  <h2>利用設定</h2>
  <table cellpadding="0" cellspacing="0" id="FormTable" class="form-table">
    <tr>
      <th class="col-head"><?php echo $this->BcForm->label('ApprovalLevelSetting.publish', '利用設定') ?>&nbsp;<span class="required">*</span></th>
      <td class="col-input">
        <?php $options = array(0 => '使わない', 1 => '使う') ?>
        <?php echo $this->BcForm->input('ApprovalLevelSetting.publish', array(
          'type' => 'select',
          'options' => $options
        )) ?>
        <?php echo $this->BcForm->error('ApprovalLevelSetting.publish') ?>
        <?php echo $this->BcHtml->image('admin/icn_help.png', array('id' => 'helpName', 'class' => 'btn help', 'alt' => 'ヘルプ')) ?>
        <div id="helptextName" class="helptext">
          <ul>
            <li>この設定を使用するかどうかを設定できます。</li>
          </ul>
        </div>
      </td>
    </tr>
    </table>
  </div>
  <?php echo $this->BcForm->hidden('ApprovalLevelSetting.category_id', array('value' => $pageData['PageCategory']['id'])); ?>
  <?php
    if (!empty($approvalId)) {
      echo $this->BcForm->hidden('ApprovalLevelSetting.id', array('value' => $approvalId));
    }
  ?>
  <!-- button -->
  <div class="submit">
    <?php echo $this->BcForm->submit('保存', array('div' => false, 'class' => 'button', 'id' => 'BtnSave')) ?>
      <?php
      $this->BcBaser->link('初期化', array('action' => 'delete',$pageData['PageCategory']['id']), array('class' => 'button'), sprintf('本当に初期化してもいいですか？'), false);
      ?>
  </div>

  <?php echo $this->BcForm->end() ?>
