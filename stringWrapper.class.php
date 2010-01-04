<?php
class stringWrapper {
    private $pre;
    private $post;
    public function __construct($pre = '', $post = '') {
	$this->pre = $pre;
	$this->post = $post;
    }
    public function wrap_string($string) {
	return $this->pre . $string . $this->post;
    }
}
