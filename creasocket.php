#!/usr/bin/env php
<?php

require_once('./websockets.php');

class echoServer extends WebSocketServer {
  //protected $maxBufferSize = 1048576; //1MB... overkill for an echo server, but potentially plausible for other applications.

  protected function process ($user, $message) {
    $this->send($user,$message);
  }

  protected function connected ($user) {
    // Do nothing: This is just an echo server, there's no need to track the user.
    // However, if we did care about the users, we would probably have a cookie to
    // parse at this step, would be looking them up in permanent storage, etc.
  }

  protected function closed ($user) {
    // Do nothing: This is where cleanup would go, in case the user had any sort of
    // open files or other objects associated with them.  This runs after the socket
    // has been closed, so there is no need to clean up the socket itself here.
  }

  protected function send($user,$message) {
    if (!$user->authenticated){
      $command = explode("|",$message);
      if (count($command)>1 && $command[0] == 'CRAUTH'){
        if ($user->authenticate($command[1])){
          $this->stdout("> $command[1]");
          $message = $this->frame('ACK',$user);
          $result = @socket_write($user->socket, $message, strlen($message));
          $message = $this->frame('USR'.$user->username,$user);
          $result = @socket_write($user->socket, $message, strlen($message));
        }else{
          $message = $this->frame('ERR-001',$user);
          $result = @socket_write($user->socket, $message, strlen($message));
        }
      }
    }else{
      $this->stdout("> $message");
      $message = $this->frame($message,$user);
      $result = @socket_write($user->socket, $message, strlen($message));
    }
  }
}

$echo = new echoServer("0.0.0.0","9000");

try {
  $echo->run();
}
catch (Exception $e) {
  $echo->stdout($e->getMessage());
}
