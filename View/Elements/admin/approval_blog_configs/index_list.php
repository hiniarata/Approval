<p>
  承認設定を行うブログを選択してください。
</p>
<table cellpadding="0" cellspacing="0" class="list-table" id="ListTable">
	<thead>
		<tr>
			<th class="list-tool">
				<div>
          操作
				</div>
			</th>
			<th>NO</th>
			<th>ブログアカウント</th>
			<th>ブログタイトル</th>
			<th>登録日<br />更新日</th>
		</tr>
	</thead>
	<tbody>
		<?php if (!empty($blogContents)): ?>
			<?php $count = 1; ?>
			<?php foreach ($blogContents as $data): ?>
				<?php $this->BcBaser->element('approval_blog_configs/index_row', array('data' => $data, 'count' => $count)) ?>
				<?php $count++; ?>
			<?php endforeach; ?>
		<?php else: ?>
		<tr>
			<td colspan="6"><p class="no-data">データが見つかりませんでした。</p></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>
