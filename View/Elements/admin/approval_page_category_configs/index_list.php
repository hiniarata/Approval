<p>
  承認設定を行うカテゴリを選択してください。
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
			<th>カテゴリ名</th>
      <th>カテゴリ タイトル</th>
			<th>登録日<br />更新日</th>
		</tr>
	</thead>
	<tbody>
		<?php if (!empty($pageCategory)): ?>
			<?php $count = 1; ?>
			<?php foreach ($pageCategory as $data): ?>
				<?php $this->BcBaser->element('approval_page_category_configs/index_row', array('data' => $data, 'count' => $count)) ?>
				<?php $count++; ?>
			<?php endforeach; ?>
		<?php else: ?>
		<tr>
			<td colspan="6"><p class="no-data">データが見つかりませんでした。</p></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>
