<?php if (!$data['BlogContent']['status']): ?>
	<?php $class = ' class="unpublish disablerow"'; ?>
	<?php else: ?>
	<?php $class = ' class="publish"'; ?>
<?php endif; ?>
<tr <?php echo $class; ?>>
	<td class="row-tools">
		<?php $this->BcBaser->link($this->BcBaser->getImg('admin/icn_tool_permission.png', array('width' => 24, 'height' => 24, 'alt' => '設定', 'class' => 'btn')), array('action' => 'form', $data['BlogContent']['id']), array('title' => '設定')) ?>
		<?php $this->BcBaser->link($this->BcBaser->getImg('admin/icn_tool_delete.png', array('width' => 24, 'height' => 24, 'alt' => '初期化', 'class' => 'btn')), array('action' => 'delete', $data['BlogContent']['id']), array('title' => '初期化', 'class' => 'btn-delete', 'onclick' => "return confirm('本当に削除してもよろしいですか？');")) ?>
	</td>
	<td><?php echo $data['BlogContent']['id']; ?></td>
	<td><?php $this->BcBaser->link($data['BlogContent']['name'], array('action' => 'form', $data['BlogContent']['id'])) ?></td>
	<td><?php echo $data['BlogContent']['title'] ?></td>
	<td><?php echo $this->BcTime->format('Y-m-d', $data['BlogContent']['created']); ?><br />
		<?php echo $this->BcTime->format('Y-m-d', $data['BlogContent']['modified']); ?></td>
</tr>
