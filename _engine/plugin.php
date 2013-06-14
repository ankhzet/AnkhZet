<?php

  class Plugin {
    private $data = null;
    
    function __construct() {
    
    }
    
    function installed() {
      return isset($this->data['installed']) && (!!$this->data['installed']);
    }
    
    function install() {
    
    }
    
    function init() {
    
    }
    
    function configure($config) {
    
    }
  }
?>
