<?php 
class OptionalLinksSchema extends CakeSchema {

	public $file = 'optional_links.php';

	public $connection = 'plugin';

	public function before($event = array()) {
		return true;
	}

	public function after($event = array()) {
	}

	public $optional_links = array(
		'id' => array('type' => 'integer', 'null' => false, 'default' => null, 'key' => 'primary'),
		'blog_post_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'length' => 8),
		'blog_content_id' => array('type' => 'integer', 'null' => true, 'default' => null, 'length' => 8),
		'name' => array('type' => 'string', 'null' => true, 'default' => null, 'collate' => 'utf8_general_ci', 'charset' => 'utf8'),
		'blank' => array('type' => 'boolean', 'null' => true, 'default' => '0'),
		'status' => array('type' => 'boolean', 'null' => true, 'default' => '0'),
		'nolink' => array('type' => 'boolean', 'null' => true, 'default' => '0'),
		'created' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'modified' => array('type' => 'datetime', 'null' => true, 'default' => null),
		'indexes' => array(
			'PRIMARY' => array('column' => 'id', 'unique' => 1),
			'blog_post_id' => array('column' => 'blog_post_id', 'unique' => 0),
			'blog_content_id' => array('column' => 'blog_content_id', 'unique' => 0)
		),
		'tableParameters' => array('charset' => 'utf8', 'collate' => 'utf8_general_ci', 'engine' => 'InnoDB')
	);

}
