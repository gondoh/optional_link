<?php
/**
 * [Controller] OptionalLink
 *
 * @link			http://www.materializing.net/
 * @author			arata
 * @license			MIT
 */
/**
 * Include files
 */
App::import('Controller', 'OptionalLink.OptionalLinkApp');
class OptionalLinksController extends OptionalLinkAppController {
/**
 * ControllerName
 * 
 * @var string
 */
	public $name = 'OptionalLinks';
	
/**
 * Model
 * 
 * @var array
 */
	public $uses = array('OptionalLink.OptionalLink', 'OptionalLink.OptionalLinkConfig');
	
/**
 * ぱんくずナビ
 *
 * @var string
 */
	public $crumbs = array(
		array('name' => 'プラグイン管理', 'url' => array('plugin' => '', 'controller' => 'plugins', 'action' => 'index')),
		array('name' => 'オプショナルリンク管理', 'url' => array('plugin' => 'optional_link', 'controller' => 'optional_links', 'action' => 'index'))
	);
	
/**
 * 管理画面タイトル
 *
 * @var string
 */
	public $adminTitle = 'オプショナルリンク';
	
/**
 * beforeFilter
 *
 * @return	void
 */
	public function beforeFilter() {
		parent::beforeFilter();
	}
	
/**
 * [ADMIN] 一覧
 * 
 * @return void
 */
	public function admin_index() {
		$this->pageTitle = $this->adminTitle . '一覧';
		$this->search = 'optional_links_index';
		$this->help = 'optional_links_index';
		parent::admin_index();
	}
	
/**
 * [ADMIN] 編集
 * 
 * @param int $id
 * @return void
 */
	public function admin_edit($id = null) {
		$this->pageTitle = $this->adminTitle . '編集';
		parent::admin_edit($id);
	}
	
/**
 * [ADMIN] 削除
 *
 * @param int $id
 * @return void
 */
	public function admin_delete($id = null) {
		parent::admin_delete($id);
	}
	
/**
 * [ADMIN] ブログ記事のオプショナルリンクを、ブログ別に一括で登録する
 *   ・オプショナルリンクの登録がないブログ記事に登録する
 * 
 * @return void
 */
	public function admin_batch() {
		
		if ($this->request->data) {
			// 既にオプショナルリンク登録のあるブログ記事は除外する
			// 登録済のオプショナルリンクを取得する
			$optionalLinks = $this->{$this->modelClass}->find('list', array(
				'conditions' => array($this->modelClass .'.blog_content_id' => $this->request->data[$this->modelClass]['blog_content_id']),
				'fields' => 'blog_post_id',
				'recursive' => -1));
			// オプショナルリンクの登録がないブログ記事を取得する
			$BlogPostModel = ClassRegistry::init('Blog.BlogPost');
			if ($optionalLinks) {
				$datas = $BlogPostModel->find('all', array(
					'conditions' => array(
						'NOT' => array('BlogPost.id' => $optionalLinks),
						'BlogPost.blog_content_id' => $this->request->data[$this->modelClass]['blog_content_id']),
					'fields' => array('id', 'no', 'name'),
					'recursive' => -1));
			} else {
				$datas = $BlogPostModel->find('all', array(
					'conditions' => array(
						'BlogPost.blog_content_id' => $this->request->data[$this->modelClass]['blog_content_id']),
					'fields' => array('id', 'no', 'name'),
					'recursive' => -1));
			}
			
			// オプショナルリンクを保存した数を初期化
			$count = 0;
			if ($datas) {
				foreach ($datas as $data) {
					$this->request->data[$this->modelClass]['blog_post_id'] = $data['BlogPost']['id'];
					$this->{$this->modelClass}->create($this->request->data);
					if ($this->{$this->modelClass}->save($this->request->data, false)) {
						$count++;
					} else {
						$this->log('ID:'. $data['BlogPost']['id'] .'のブログ記事の'. $this->adminTitle .'登録に失敗');
					}
				}
			}
			
			$this->setMessage($count .'件の'. $this->adminTitle .'を登録しました。', false, true);
		}
		unset($optionalLinks);
		unset($datas);
		unset($data);
		
		$registerd = array();
		foreach ($this->blogContentDatas as $key => $blog) {
			// $key : blog_content_id
			// 登録済のオプショナルリンクを取得する
			$optionalLinks = $this->{$this->modelClass}->find('list', array(
				'conditions' => array($this->modelClass .'.blog_content_id' => $key),
				'fields' => 'blog_post_id',
				'recursive' => -1));
			// オプショナルリンクの登録がないブログ記事を取得する
			$BlogPostModel = ClassRegistry::init('Blog.BlogPost');
			if ($optionalLinks) {
				$datas = $BlogPostModel->find('all', array(
					'conditions' => array(
						'NOT' => array('BlogPost.id' => $optionalLinks),
						'BlogPost.blog_content_id' => $key),
					'fields' => array('id', 'no', 'name'),
					'recursive' => -1));
			} else {
				$datas = $BlogPostModel->find('all', array(
					'conditions' => array(
						'BlogPost.blog_content_id' => $key),
					'fields' => array('id', 'no', 'name'),
					'recursive' => -1));
			}
			
			$registerd[] = array(
				'name' => $blog,
				'count' => count($datas)
			);
		}
		
		$this->set('registerd', $registerd);
		$this->set('blogContentDatas', $this->blogContentDatas);
		
		$this->pageTitle = $this->adminTitle . '一括設定';
		
	}
/**
 * 一覧用の検索条件を生成する
 *
 * @param array $data
 * @return array $conditions
 */
	public function _createAdminIndexConditions($data) {
		
		$conditions = array();
		$name = '';
		$blogContentId = '';
		
		if (isset($data[$this->modelClass]['name'])) {
			$name = $data[$this->modelClass]['name'];
		}
		if (isset($data[$this->modelClass]['blog_content_id'])) {
			$blogContentId = $data[$this->modelClass]['blog_content_id'];
		}
		if (isset($data[$this->modelClass]['status']) && $data[$this->modelClass]['status'] === '') {
			unset($data[$this->modelClass]['status']);
		}
		if (isset($data[$this->modelClass]['nolink']) && $data[$this->modelClass]['nolink'] === '') {
			unset($data[$this->modelClass]['nolink']);
		}
		
		unset($data['_Token']);
		unset($data[$this->modelClass]['name']);
		unset($data[$this->modelClass]['blog_content_id']);
		
		// 条件指定のないフィールドを解除
		foreach($data[$this->modelClass] as $key => $value) {
			if ($value === '') {
				unset($data[$this->modelClass][$key]);
			}
		}
		
		if ($data[$this->modelClass]) {
			$conditions = $this->postConditions($data);
		}
		
		// １つの入力指定から複数フィールド検索指定
		if ($name) {
			$conditions[] = array(
				$this->modelClass .'.name LIKE' => '%'.$name.'%'
			);
		}
		if ($blogContentId) {
			$conditions['and'] = array(
				$this->modelClass .'.blog_content_id' => $blogContentId
			);
		}
		
		if ($conditions) {
			return $conditions;
		} else {
			return array();
		}
		
	}
	
}
