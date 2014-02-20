<?php

App::uses('GalleryAppController', 'Gallery.Controller');
App::uses('Galleries', 'Gallery.Lib');

/**
 * Albums Controller
 *
 * PHP version 5
 *
 * @category Controller
 * @package  Croogo
 * @version  1.3
 * @author   Edinei L. Cipriani <phpedinei@gmail.com>
 * @license  http://www.opensource.org/licenses/mit-license.php The MIT License
 * @link     http://www.demoveo.com
 */
class AlbumsController extends GalleryAppController {

	public $components = array(
		'Search.Prg' => array(
			'presetForm' => array(
				'paramType' => 'querystring',
			),
			'commonProcess' => array(
				'paramType' => 'querystring',
			),
		),
	);

	public $presetVars = array(
		'title' => array('type' => 'like'),
		'description' => array('type' => 'like'),
	);

	public function beforeFilter() {
		$this->jslibs = Galleries::activeLibs();
		parent::beforeFilter();
		$this->Taxonomy = ClassRegistry::init('Taxonomy.Taxonomy');
		if ($this->action == 'admin_upload_photo' && $this->request->is('ajax')) {
			$this->Security->csrfCheck = false;
		}
	}

	public function admin_index() {
		$title_for_layout = __d('gallery','Albums');
		$searchFields = array(
			'title',
			'description' => array(
				'type' => 'text',
			),
		);

		$this->Prg->commonProcess();

		$this->Album->recursive = 0;
		$this->paginate = array(
			'limit' => Configure::read('Gallery.album_limit_pagination'),
			'order' => 'Album.position',
			'conditions' => $this->Album->parseCriteria($this->request->query),
		);
		$albums = $this->paginate();
		$this->set(compact('title_for_layout', 'albums', 'searchFields'));
	}

	public function admin_add() {
		$typeAlias = 'album';
		$type = $this->Taxonomy->Vocabulary->Type->findByAlias($typeAlias);
		if (!empty($this->request->data)) {
			$this->Album->create();
			if (empty($this->request->data['Album']['slug'])){
				$this->request->data['Album']['slug'] = Inflector::slug($this->request->data['Album']['title']);
			}

			$this->Album->recursive = -1;
			$position = $this->Album->find('all',array(
				'fields' => 'MAX(Album.position) as position'
			));

			$this->request->data['Album']['position'] = $position[0][0]['position'] + 1;

			if ($this->Album->saveAll($this->request->data)) {
				$this->Session->setFlash(__('Album is saved.'), 'default', array('class' => 'success'));
				$this->redirect(array('action' => 'index'));
			} else {
				$this->Session->setFlash(__d('gallery','Album could not be saved. Please try again.'));
			}
		}
		$this->_setCommonVariables($type);
		$this->set('types', $this->jslibs);
	}

	function admin_edit($id = null) {
		$typeAlias = 'album';
		$type = $this->Taxonomy->Vocabulary->Type->findByAlias($typeAlias);

		if (!$id) {
			$this->Session->setFlash(__('Invalid album.'));
			$this->redirect(array('action' => 'index'));
		}
		if (!empty($this->request->data)) {
			$this->Album->type = 'album';
			if ($this->Album->saveAll($this->request->data)) {
				$this->Session->setFlash(__('Album is saved.'), 'default', array('class' => 'success'));
				$this->Croogo->redirect(array('action' => 'edit', $id));
			} else {
				$this->Session->setFlash(__d('gallery','Album could not be saved. Please try again.'));
			}
		}
		$this->_setCommonVariables($type);

		$this->request->data = $this->Album->read(null, $id);
		$this->set('types', $this->jslibs);
	}

	protected function _setCommonVariables($type) {
		$typeAlias = $type['Type']['alias'];
		$vocabularies = Hash::combine($type['Vocabulary'], '{n}.id', '{n}');
		$taxonomy = array();
		foreach ($type['Vocabulary'] as $vocabulary) {
			$vocabularyId = $vocabulary['id'];
			$taxonomy[$vocabularyId] = $this->Taxonomy->getTree($vocabulary['alias'], array('taxonomyId' => true));
		}
		$this->set(compact('typeAlias', 'type', 'vocabularies', 'taxonomy'));
	}

	function admin_delete($id = null) {
		if (!$id) {
			$this->Session->setFlash(__d('gallery','Invalid ID for album.'));
			$this->redirect(array('action' => 'index'));
		} else {
			$ssluga = $this->Album->findById($id);
			$sslug = $ssluga['Album']['slug'];

			$dir  = WWW_ROOT . 'img' . DS . $sslug;
		}
		if ($this->Album->delete($id, true)) {
			$this->Session->setFlash(__d('gallery','Album is deleted, and whole directory with images.'));
			$this->redirect(array('action' => 'index'));
		}
		$this->render(false);
	}

	public function index() {
		$this->set('title_for_layout',__d('gallery',"Albums"));

		$this->Album->recursive = -1;
		$this->Album->Behaviors->attach('Containable');
		$this->paginate = array(
			'conditions' => array('Album.status' => 1),
			'contain' => array('Photo' => array('limit' => 1)),
			'limit' => Configure::read('Gallery.album_limit_pagination'),
			'order' => 'Album.position ASC',
		);

		$this->set('albums', $this->paginate());
	}

	public function view($slug = null) {
		if (!$slug) {
			$this->Session->setFlash(__d('gallery','Invalid album. Please try again.'));
			$this->redirect(array('action' => 'index'));
		}

		$album = $this->Album->find('photos', array(
			'slug' => $slug
		));

		if (isset($this->params['requested'])) {
			return $album;
		}

		if (!count($album)) {
			$this->Session->setFlash(__d('gallery','Invalid album. Please try again.'));
			$this->redirect(array('action' => 'index'));
		}

		$this->set('title_for_layout', __d('gallery', 'Album %s', $album['Album']['title']));
		$this->set(compact('album'));
	}

	public function admin_upload($id = null) {
		if (!$id) {
			$this->Session->setFlash(__d('gallery','Invalid album. Please try again.'));
			$this->redirect(array('action' => 'index'));
		}
		$this->set('title_for_layout',__d('gallery',"Manage your photos in album"));

		$album = $this->Album->find('first', array(
			'contain' => array(
				'Photo' => array(
					'OriginalAsset', 'LargeAsset', 'ThumbnailAsset',
				),
			),
			'conditions' => array(
				'Album.id' => $id
			),
			'recursive' => -1,
		));
		if (isset($album['Photo'])) {
			$photos = Hash::sort($album['Photo'], '{n}.AlbumsPhoto.weight', 'asc');
			$album['Photo'] = $photos;
		}

		$this->set('album', $album);
	}

	public function admin_upload_photo($id = null) {
		set_time_limit ( 240 ) ;

		$this->layout = 'ajax';
		$this->render(false);
		Configure::write('debug', 0);

		$this->request->data['Photo']['status'] = true;
		$this->request->data['Album'][] = array('album_id' => $id, 'master' => true);

		$slug = $this->Album->field('slug', array('Album.id' => $id));
		$this->Album->Photo->setTargetDirectory($slug);
		$data = $this->Album->Photo->create();
		$this->Album->Photo->save($this->request->data);

		echo json_encode($this->Album->Photo->findById($this->Album->Photo->id));
	}

	public function admin_reset_weight($id = null) {
		$this->Album->id = $id;
		$this->Album->AlbumsPhoto->resetWeights();
		$this->redirect($this->referer());
	}

	public function admin_delete_photo($id = null) {
		$this->layout = 'ajax';
		$this->autoRender = false;

		if (!$id) {
			echo json_encode(array('status' => 0, 'msg' => __d('gallery','Invalid photo. Please try again.'))); exit();
		}

		if ($this->Album->Photo->delete($id)) {
			echo json_encode(array('status' => 1)); exit();
		} else {
			echo json_encode(array('status' => 0,  'msg' => __d('gallery','Problem to remove photo. Please try again.'))); exit();
		}
	}

}
