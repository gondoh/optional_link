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
 * ブログ記事多重保存の判定
 * 
 * @var boolean
 */
	public $throwBlogPost = false;
	
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
		// 2周目では保存処理に渡らないようにしている
		if (!$this->throwBlogPost) {
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
		
		// ブログ記事コピー保存時、アイキャッチが入っていると処理が2重に行われるため、1周目で処理通過を判定し、
		// 2周目では保存処理に渡らないようにしている
		$this->throwBlogPost = true;
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
	
/**
 * 保存するデータの生成
 * 
 * @param Object $Model
 * @param int $contentId
 * @return array
 */
	private function _generateSaveData($Model, $contentId = '') {
		$params = Router::getParams();
		$data = array();
		$modelId = $oldModelId = null;
		if ($Model->alias == 'BlogPost') {
			$modelId = $contentId;
			if(!empty($params['pass'][1])) {
				$oldModelId = $params['pass'][1];
			}
		}
		
		if ($contentId) {
			$data = $this->OptionalLink->find('first', array(
				'conditions' => array('OptionalLink.blog_post_id' => $contentId),
				'recursive' => -1
			));
		}
		
		if ($params['action'] != 'admin_ajax_copy') {
			if ($data) {
				// 編集時
				$data['OptionalLink'] = array_merge($data['OptionalLink'], $Model->data['OptionalLink']);
			} else {
				// 追加時
				$data['OptionalLink'] = $Model->data['OptionalLink'];
				$data['OptionalLink']['blog_post_id'] = $contentId;
				$data['OptionalLink']['blog_content_id'] = $Model->BlogContent->id;
			}
		} else {
			// Ajaxコピー処理時に実行
			// ブログコピー保存時にエラーがなければ保存処理を実行
			if (empty($Model->validationErrors)) {
				$_data = array();
				if ($oldModelId) {
					$_data = $this->OptionalLink->find('first', array(
						'conditions' => array('OptionalLink.blog_post_id' => $oldModelId),
						'recursive' => -1
					));
				}
				// もしオプショナルリンク設定の初期データ作成を行ってない事を考慮して判定している
				if ($_data) {
					// コピー元データがある時
					$data['OptionalLink'] = $_data['OptionalLink'];
					$data['OptionalLink']['blog_post_id'] = $contentId;
					unset($data['OptionalLink']['id']);
				} else {
					// コピー元データがない時
					$data['OptionalLink']['blog_post_id'] = $modelId;
					$data['OptionalLink']['blog_content_id'] = $params['pass'][0];
				}
			}
		}
		
		return $data;
	}
	
/**
 * 保存するデータの生成
 * 
 * @param Object $Model
 * @param int $contentId
 * @return array
 */
	private function _generateContentSaveData($Model, $contentId = '') {
		$params = Router::getParams();
		$data = array();
		if ($Model->alias == 'BlogContent') {
			$modelId = $contentId;
			if (isset($params['pass'][0])) {
				$oldModelId = $params['pass'][0];
			}
		}
		
		if ($contentId) {
			$data = $this->OptionalLinkConfig->find('first', array(
				'conditions' => array('OptionalLinkConfig.blog_content_id' => $contentId)
			));
		}
		
		if ($params['action'] != 'admin_ajax_copy') {
			if ($data) {
				// 編集時
				$data['OptionalLinkConfig'] = array_merge($data['OptionalLinkConfig'], $Model->data['OptionalLinkConfig']);
			} else {
				// 追加時
				if (!empty($Model->data['OptionalLinkConfig'])) {
					$data['OptionalLinkConfig'] = $Model->data['OptionalLinkConfig'];
				}
				$data['OptionalLinkConfig']['blog_content_id'] = $contentId;
			}
		} else {
			// Ajaxコピー処理時に実行
			// ブログコピー保存時にエラーがなければ保存処理を実行
			if (empty($Model->validationErrors)) {
				$_data = $this->OptionalLinkConfig->find('first', array(
					'conditions' => array('OptionalLinkConfig.blog_content_id' => $oldModelId),
					'recursive' => -1
				));
				// もしオプショナルリンク設定の初期データ作成を行ってない事を考慮して判定している
				if ($_data) {
					// コピー元データがある時
					$_data['OptionalLink']['id'] = null;
					$data = Hash::merge($data, $_data);
					$data['OptionalLinkConfig']['blog_content_id'] = $contentId;
				} else {
					// コピー元データがない時
					$data['OptionalLinkConfig']['blog_content_id'] = $modelId;
					$data['OptionalLinkConfig']['status'] = true;
				}
			}
		}
		
		return $data;
	}
	
}
