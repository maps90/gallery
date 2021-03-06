<?php

App::uses('AppHelper', 'View/Helper');

class DdsliderHelper extends AppHelper {

	public $helpers = array(
		'Html',
		'Js',
		'Gallery.Gallery',
	);

	public function assets($options = array()) {
		$options = Set::merge(array('inline' => false), $options);
		$this->Html->script('/gallery/js/jquery.DDSlider', false, $options);
		$this->Html->css('/gallery/css/DDSlider', false, $options);
	}

	public function album($album, $photos) {
		return $this->Html->tag('ul', $photos, array(
			'id' => 'gallery-' . $album['Album']['id'],
		));
	}

	public function photo($album, $photo) {
		$urlLarge = $this->Html->url('/' . $photo['large']);
		$urlSmall = $this->Html->url('/' . $photo['small']);
		$title = empty($photo['title']) ? false : $photo['title'];
		$options = array('rel' => $urlSmall);
		if ($title) {
			$options = Set::merge(array('title' => $title), $options);
		}
		return $this->Html->tag('li', $this->Html->image($urlLarge, $options));
	}

	public function initialize($album) {
		$config = $this->Gallery->getAlbumJsParams($album);
		$js = sprintf('$(function(){ $(\'#%s\').DDSlider(%s); })',
			'gallery-' . $album['Album']['id'],
			$config
		);
		$this->Js->buffer($js);
	}

}
