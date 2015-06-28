<?php
/*
カテゴリmobile,smartphoneについてはデフォルトで使用するもの。
承認設定の考え方として、他のカテゴリと同等に扱えないので、ここでは無視する。
*/
if ($data['PageCategory']['name'] != 'mobile' && $data['PageCategory']['name'] != 'smartphone'):
?>
<tr>
	<td class="row-tools">
		<?php $this->BcBaser->link($this->BcBaser->getImg('admin/icn_tool_permission.png', array('width' => 24, 'height' => 24, 'alt' => '設定', 'class' => 'btn')), array('action' => 'form', $data['PageCategory']['id']), array('title' => '設定')) ?>
		<?php $this->BcBaser->link($this->BcBaser->getImg('admin/icn_tool_delete.png', array('width' => 24, 'height' => 24, 'alt' => '初期化', 'class' => 'btn')), array('action' => 'delete', $data['PageCategory']['id']), array('title' => '初期化', 'class' => 'btn-delete', 'onclick' => "return confirm('本当に削除してもよろしいですか？');")) ?>
	</td>
	<td><?php echo $data['PageCategory']['id']; ?></td>
  <td><?php $this->BcBaser->link($data['PageCategory']['name'], array('action' => 'form', $data['PageCategory']['id'])) ?></td>
	<td><?php $this->BcBaser->link($data['PageCategory']['title'], array('action' => 'form', $data['PageCategory']['id'])) ?></td>
	<td><?php echo $this->BcTime->format('Y-m-d', $data['PageCategory']['created']); ?><br />
		<?php echo $this->BcTime->format('Y-m-d', $data['PageCategory']['modified']); ?></td>
</tr>
<?php endif; ?>
