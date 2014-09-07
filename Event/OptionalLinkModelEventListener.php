<?php
/**
 * [ModelEventListener] OptionalLink
 *
 * @link			http://www.materializing.net/
 * @author			arata
 * @license			MIT
 */
class OptionalLinkModelEventListener extends BcModelEventListener {
/**
 * 登録イベント
 *
 * @var array
 */
	public $events = array(
		'Blog.BlogPost.beforeValidate',
		'Blog.BlogPost.afterSave',
		'Blog.BlogPost.afterDelete',
		'Blog.BlogPost.beforeFind',
		'Blog.BlogContent.afterSave',
		'Blog.BlogContent.afterDelete',
		'Blog.BlogContent.beforeFind'
	);
	
/**
 * オプショナルリンクモデル
 * 
 * @var Object
 */
	public $OptionalLink = null;
	
/**
 * オプショナルリンク設定モデル
 * 
 * @var Object
 */
	public $OptionalLinkConfig = null;
	
/**
 * Construct
 * 
 */
	function __construct() {
		parent::__construct();
		if (ClassRegistry::isKeySet('OptionalLink.OptionalLink')) {
			$this->OptionalLink = ClassRegistry::getObject('OptionalLink.OptionalLink');
		} else {
			$this->OptionalLink = ClassRegistry::init('OptionalLink.OptionalLink');
		}
		if (ClassRegistry::isKeySet('OptionalLink.OptionalLinkConfig')) {
			$this->OptionalLinkConfig = ClassRegistry::getObject('OptionalLink.OptionalLinkConfig');
		} else {
			$this->OptionalLinkConfig = ClassRegistry::init('OptionalLink.OptionalLinkConfig');
		}
	}
	
/**
 * blogBlogPostBeforeFind
 * 
 * @param CakeEvent $event
 */
	public function blogBlogPostBeforeFind(CakeEvent $event) {
		$Model = $event->subject();
		// ブログ記事取得の際にオプショナルリンク情報も併せて取得する
		$association = array(
			'OptionalLink' => array(
				'className' => 'OptionalLink.OptionalLink',
				'foreignKey' => 'blog_post_id'
			)
		);
		$Model->bindModel(array('hasOne' => $association));
	}
	
/**
 * blogBlogContentBeforeFind
 * 
 * @param CakeEvent $event
 * @return array
 */
	public function blogBlogContentBeforeFind(CakeEvent $event) {
		$Model = $event->subject();
		// ブログ設定取得の際にオプショナルリンク設定情報も併せて取得する
		$association = array(
			'OptionalLinkConfig' => array(
				'className' => 'OptionalLink.OptionalLinkConfig',
				'foreignKey' => 'blog_content_id'
			)
		);
		$Model->bindModel(array('hasOne' => $association));
	}
	
/**
 * blogBlogPostBeforeValidate
 * 
 * @param CakeEvent $event
 * @return boolean
 */
	public function blogBlogPostBeforeValidate(CakeEvent $event) {
		$Model = $event->subject();
		// ブログ記事保存の手前で OptionalLink モデルのデータに対して validation を行う
		// TODO saveAll() ではbeforeValidateが効かない？
		$this->OptionalLink->set($Model->data);
		return $this->OptionalLink->validates();
	}
	
/**
 * blogBlogPostAfterSave
 * 
 * @param CakeEvent $event
 */
	public function blogBlogPostAfterSave(CakeEvent $event) {
		$Model = $event->subject();
		$created = $event->data[0];
		if ($created) {
			$contentId = $Model->getLastInsertId();
		} else {
			$contentId = $Model->data[$Model->alias]['id'];
		}
		$saveData = $this->_generateSaveData($Model, $contentId);
		if (isset($saveData['OptionalLink']['id'])) {
			// ブログ記事編集保存時に設定情報を保存する
			$this->OptionalLink->set($saveData);
		} else {
			// ブログ記事追加時に設定情報を保存する
			$this->OptionalLink->create($saveData);
		}
		if (!$this->OptionalLink->save()) {
			$this->log(sprintf('ID：%s のオプショナルリンクの保存に失敗しました。', $Model->data['OptionalLink']['id']));
		}
	}
	
/**
 * blogBlogContentAfterSave
 * 
 * @param CakeEvent $event
 */
	public function blogBlogContentAfterSave(CakeEvent $event) {
		$Model = $event->subject();
		$created = $event->data[0];
		if ($created) {
			$contentId = $Model->getLastInsertId();
		} else {
			$contentId = $Model->data[$Model->alias]['id'];
		}
		$saveData = $this->_generateContentSaveData($Model, $contentId);
		if (isset($saveData['OptionalLinkConfig']['id'])) {
			// ブログ設定編集保存時に設定情報を保存する
			$this->OptionalLinkConfig->set($saveData);
		} else {
			// ブログ追加時に設定情報を保存する
			$this->OptionalLinkConfig->create($saveData);
		}
		if (!$this->OptionalLinkConfig->save()) {
			$this->log(sprintf('ID：%s のオプショナルリンク設定の保存に失敗しました。', $Model->data['OptionalLinkConfig']['id']));
		}
		
	}
	
/**
 * 保存するデータの生成
 * 
 * @param Object $Model
 * @param int $contentId
 * @return array
 */
	private function _generateSaveData($Model, $contentId = '') {
		
		if ($Model->alias == 'BlogPost') {
			$params = Router::getParams();
			$data = array();
			
			if ($contentId) {
				$data = $this->OptionalLink->find('first', array('conditions' => array(
					'OptionalLink.blog_post_id' => $contentId
				)));
			}
			if ($params['action'] != 'admin_ajax_copy') {
				if(!empty($Model->data['OptionalLink'])) {
					$data['OptionalLink'] = $Model->data['OptionalLink'];
					$data['OptionalLink']['blog_post_id'] = $contentId;
				} else {
					// ブログ記事追加の場合
					$data['OptionalLink']['blog_post_id'] = $contentId;
					$data['OptionalLink']['blog_content_id'] = $Model->BlogContent->id;
				}
			} else {
				// Ajaxコピー処理時に実行
				// ブログコピー保存時にエラーがなければ保存処理を実行
				if (empty($Model->validationErrors)) {
					$_data = $this->OptionalLink->find('first', array(
						'conditions' => array(
							'OptionalLink.blog_post_id' => $params['pass'][1]
						),
						'recursive' => -1
					));
					// もしオプショナルリンク設定の初期データ作成を行ってない事を考慮して判定している
					if ($_data) {
						$data['OptionalLink'] = $_data['OptionalLink'];
						$data['OptionalLink']['blog_post_id'] = $contentId;
					} else {
						$data['OptionalLink']['blog_post_id'] = $contentId;
						$data['OptionalLink']['blog_content_id'] = $params['pass'][0];
					}
				}
			}
		}
		
		if ($model->alias == 'BlogContent') {
			$params = Router::getParams();
			$data = array();
			
			if ($contentId) {
				$data = $this->OptionalLinkConfig->find('first', array('conditions' => array(
					'OptionalLinkConfig.blog_content_id' => $contentId
				)));
			}
			if ($params['action'] != 'admin_ajax_copy') {
				$data['OptionalLinkConfig'] = $model->data['OptionalLinkConfig'];
				$data['OptionalLinkConfig']['blog_content_id'] = $contentId;
			} else {
				// Ajaxコピー処理時に実行
				// ブログコピー保存時にエラーがなければ保存処理を実行
				if (empty($model->validationErrors)) {
					$_data = $this->OptionalLinkConfig->find('first', array(
						'conditions' => array(
							'OptionalLinkConfig.blog_content_id' => $params['pass']['0']
						),
						'recursive' => -1
					));
					// もしオプショナルリンク設定の初期データ作成を行ってない事を考慮して判定している
					if ($_data) {
						$data['OptionalLinkConfig'] = $_data['OptionalLinkConfig'];
						$data['OptionalLinkConfig']['blog_content_id'] = $contentId;
					} else {
						$data['OptionalLinkConfig']['blog_content_id'] = $contentId;
						$data['OptionalLinkConfig']['status'] = true;
					}
				}
			}
		}
		
		return $data;
	}
	
/**
 * blogBlogPostAfterDelete
 * 
 * @param CakeEvent $event
 */
	public function blogBlogPostAfterDelete(CakeEvent $event) {
		$Model = $event->subject();
		// ブログ記事削除時、そのブログ記事が持つOptionalLinkを削除する
		$data = $this->OptionalLink->find('first', array(
			'conditions' => array('OptionalLink.blog_post_id' => $Model->id),
			'recursive' => -1
		));
		if ($data) {
			if (!$this->OptionalLink->delete($data['OptionalLink']['id'])) {
				$this->log('ID:' . $data['OptionalLink']['id'] . 'のOptionalLinkの削除に失敗しました。');
			}
		}
	}
	
/**
 * blogBlogContentAfterDelete
 * 
 * @param CakeEvent $event
 */
	public function blogBlogContentAfterDelete(CakeEvent $event) {
		$Model = $event->subject();
		// ブログ削除時、そのブログが持つOptionalLink設定を削除する
		$data = $this->OptionalLinkConfig->find('first', array(
			'conditions' => array('OptionalLinkConfig.blog_content_id' => $Model->id),
			'recursive' => -1
		));
		if ($data) {
			if (!$this->OptionalLinkConfig->delete($data['OptionalLinkConfig']['id'])) {
				$this->log('ID:' . $data['OptionalLinkConfig']['id'] . 'のOptionalLink設定の削除に失敗しました。');
			}
		}
	}
	
}
