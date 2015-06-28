<p>
  承認設定を行うブログカテゴリを選択してください。
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
			<th>所属先ブログ</th>
			<th>登録日<br />更新日</th>
		</tr>
	</thead>
	<tbody>
		<?php if (!empty($blogCategory)): ?>
			<?php $count = 1; ?>
			<?php foreach ($blogCategory as $data): ?>
				<?php $this->BcBaser->element('approval_blog_category_configs/index_row', array('data' => $data, 'count' => $count)) ?>
				<?php $count++; ?>
			<?php endforeach; ?>
		<?php else: ?>
		<tr>
			<td colspan="6"><p class="no-data">データが見つかりませんでした。</p></td>
		</tr>
		<?php endif; ?>
	</tbody>
</table>
