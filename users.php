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
  public $username = "";
  public $module = "";
  public $message = "";

  function __construct($id, $socket) {
    $this->id = $id;
    $this->socket = $socket;
  }

  public function authenticate($key){
    $session = new SESSION();
    if ($session->validate_basic_token($key, $_POST, 'GET')){
      $this->authenticated = true;
      $this->username = $session->username;
      return true;
    }else
      return false;
  }
}
