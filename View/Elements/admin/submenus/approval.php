<tr>
  <th>固定ページ承認設定 メニュー</th>
  <td>
    <ul class="cleafix">
      <li><?php $this->BcBaser->link('固定ページ設定', array('controller' => 'approval_page_configs', 'action' => 'form')) ?></li>
      <li><?php $this->BcBaser->link('固定ページカテゴリ一覧', array('controller' => 'approval_page_category_configs', 'action' => 'index')) ?></li>
    </ul>
  </td>
</tr>
<tr>
  <th>ブログ承認設定 メニュー</th>
  <td>
    <ul class="cleafix">
      <li><?php $this->BcBaser->link('ブログ一覧', array('controller' => 'approval_blog_configs', 'action' => 'index')) ?></li>
      <li><?php $this->BcBaser->link('ブログカテゴリ一覧', array('controller' => 'approval_blog_category_configs', 'action' => 'index')) ?></li>
    </ul>
  </td>
</tr>
