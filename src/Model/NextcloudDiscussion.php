<?php

namespace App\Model;

class NextcloudDiscussion {

    private $data;

    public function __construct($content) {

        $this->data = $content;
    }

    public function getId() {
        return $this->data['id'];
    }

    public function getToken() {
        return $this->data['token'];
    }

    public function getType() {
        return $this->data['type'];
    }

    public function getName() {
        return $this->data['name'];
    }

    public function isReadOnly() {
        return $this->data['readOnly'] === 1;
    }
}