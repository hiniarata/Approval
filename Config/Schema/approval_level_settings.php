<?php 
class ApprovalLevelSettingsSchema extends CakeSchema {

	public $file = 'approval_level_settings.php';

	public $connection = 'plugin';

	public function before($event = array()) {
		return true;
	}

	public function after($event = array()) {
	}

	public $approval_level_settings = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'unsigned' => false, 'key' => 'primary'),
		'type' => array('type' => 'string', 'null' => false, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'blog_content_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'category_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'level1_type' => array('type' => 'string', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'level1_approver_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'level2_type' => array('type' => 'string', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'level2_approver_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'level3_type' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 11, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'level3_approver_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'level4_type' => array('type' => 'string', 'null' => true, 'default' => null, 'length' => 11, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'level4_approver_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'level5_type' => array('type' => 'string', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'level5_approver_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'last_stage' => array('type' => 'integer', 'null' => true, 'default' => null, 'unsigned' => false),
		'publish' => array('type' => 'integer', 'null' => true, 'default' => null, 'length' => 2, 'unsigned' => false),
		'created' => array('type' => 'datetime', 'null' => false, 'default' => null),
		'modified' => array('type' => 'datetime', 'null' => false, 'default' => null),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);

}
