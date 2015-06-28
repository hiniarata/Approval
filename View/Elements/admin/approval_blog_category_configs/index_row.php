<tr>
	<td class="row-tools">
		<?php $this->BcBaser->link($this->BcBaser->getImg('admin/icn_tool_permission.png', array('width' => 24, 'height' => 24, 'alt' => '設定', 'class' => 'btn')), array('action' => 'form', $data['BlogCategory']['id']), array('title' => '設定')) ?>
		<?php $this->BcBaser->link($this->BcBaser->getImg('admin/icn_tool_delete.png', array('width' => 24, 'height' => 24, 'alt' => '初期化', 'class' => 'btn')), array('action' => 'delete', $data['BlogCategory']['id']), array('title' => '初期化', 'class' => 'btn-delete', 'onclick' => "return confirm('本当に削除してもよろしいですか？');")) ?>
	</td>
	<td><?php echo $data['BlogCategory']['id']; ?></td>
	<td><?php $this->BcBaser->link($data['BlogCategory']['title'], array('action' => 'form', $data['BlogCategory']['id'])) ?></td>
	<td><?php $this->Approval->blogContentTitle($data['BlogCategory']['blog_content_id']) ?></td>
	<td><?php echo $this->BcTime->format('Y-m-d', $data['BlogCategory']['created']); ?><br />
		<?php echo $this->BcTime->format('Y-m-d', $data['BlogCategory']['modified']); ?></td>
</tr>
