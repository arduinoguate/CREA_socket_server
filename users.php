<?php

include_once 'session.php';

class WebSocketUser{

  public $socket;
  public $id;
  public $headers = array();
  public $handshake = false;

  public $handlingPartialPacket = false;
  public $partialBuffer = "";

  public $sendingContinuous = false;
  public $partialMessage = "";

  public $hasSentClose = false;

  public $authenticated = false;
  public $subscribed = false;
  public $username = "";
  public $module = "";
  public $message = "";

  public $session;

  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
    $this->session = new SESSION();
  }

  public function authenticate($key){
    if ($this->session->validate_basic_token($key)){
      $this->authenticated = true;
      $this->username = $this->session->username;
      return true;
    }else
      return false;
  }

  public function subscribe($module){
    if ($this->session->validate_module($module, $this->username)){
      $this->subscribed = true;
      $this->module = $module;
      return true;
    }else{
      return false;
    }
  }

  public function process(){
    $this->session->api_what($this->module);
    return $this->session->response;
  }

  public function send($value){
    $this->session->api_what($this->module, $value);
    return $this->session->response;
  }
}
