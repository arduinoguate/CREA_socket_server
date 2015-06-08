<?php

include_once 'config/config.php';

class SESSION extends GCConfig
{
  protected $token;
	protected $_scopes;
	public $client_id;
	public $username;
	public $email;
	public $err;
	public $response;

	public function __construct() {
		parent::__construct();
		$this->response = '';
		$this->username = '';
	}


	function base64_url_encode($input) {
	   return strtr(base64_encode($input), '+/', '-_');
	}

	function base64_url_decode($input) {
	   return base64_decode(strtr($input, '-_', '+/'));
	}

  /**
   * Returns an encrypted & utf8-encoded
   */
  function encrypt($pure_string, $encryption_key) {
    $iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $encrypted_string = mcrypt_encrypt(MCRYPT_BLOWFISH, $encryption_key, utf8_encode($pure_string), MCRYPT_MODE_ECB, $iv);
    return $encrypted_string;
  }

  /**
   * Returns decrypted original string
   */
  function decrypt($encrypted_string, $encryption_key) {
    $iv_size = mcrypt_get_iv_size(MCRYPT_BLOWFISH, MCRYPT_MODE_ECB);
    $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
    $decrypted_string = mcrypt_decrypt(MCRYPT_BLOWFISH, $encryption_key, $encrypted_string, MCRYPT_MODE_ECB, $iv);
    return $decrypted_string;
  }

	private function validate_login($params=array()){
		if ($this->validate_fields($params, 'login')){
			$result = array();
			$pass = md5($params['password']);
			if ($this->user->fetch_id(array('idusuario' => $params['username']),null,true," password = '$pass' AND enabled is TRUE ")){
				if ($this->api_user_asoc->fetch_id(array('client_id'=>$this->client_id,'id_usuario'=>$this->user->columns['idusuario']))){
					$this->username = $this->user->columns['idusuario'];
					return true;
				}else{
					$this->err = 'Usuario no asociado';
					return false;
				}
			}else{
        $this->err = 'Invalid Credentials';
				return false;
			}
		}else{
			$this->err = $this->response;
			return false;
		}
	}

	private function validate_basic($params = array()){
		$token64 = base64_decode($this->token);
		$result = $this->api_client->fetch("CONCAT(client_id,':',client_secret) = '$token64' AND enabled is true");
		if (count($result) == 1){
			$this->client_id = $result[0]->columns['client_id'];
			$this->email = $result[0]->columns['email'];
      $this->username = $result[0]->columns['user_id']['idusuario'];
			if ($result[0]->columns['asoc'] == 1){
				return $this->validate_login($params);
			}
			return true;
		}else{
      $this->err = 'Token not found';
			return false;
		}
	}

	public function validate_basic_token($token, $params = array()){
		try{
      $this->token = $token;
  		if ($this->validate_basic($params)){
				return true;
			}else{
				$this->response = $this->err;
				return false;
			}
		}catch(Exception $e){
			$this->response = $this->err;
			return false;
		}
	}

  public function validate_module($eid=null, $token=''){
    if ($this->modulo_asoc->fetch_id(array('idusuario'=>$token, 'modulo_id'=>$eid)))
      if ($this->modulo->fetch_id(array("id" => $eid))) {
        $this->modulo->columns['estado'] = "ONLINE";
        $this->modulo->columns['tipo_modulo'] = $this->modulo->columns['tipo_modulo']['idtipo_modulo'];
        $this->modulo->columns['updated_at'] = date("Y-m-d H:i:s");
        if (!$this->modulo->update()) {
          return false;
        }else{
          return true;
        }
      }else
        return false;
    else
      return false;
  }

  public function disconnect_module($eid=null){
    if ($this->modulo->fetch_id(array("id" => $eid))) {
      $this->modulo->columns['estado'] = "OFFLINE";
      $this->modulo->columns['tipo_modulo'] = $this->modulo->columns['tipo_modulo']['idtipo_modulo'];
      $this->modulo->columns['updated_at'] = date("Y-m-d H:i:s");
      if (!$this->modulo->update()) {
        return false;
      }else{
        return true;
      }
    }else
      return false;
  }

  public function api_what($mid, $reply = '') {
    if ($this->validate_module_id_only($mid)){
      if ($this->modulo->fetch_id(array("id" => $mid))) {
        $status = 'IDLE';
        $this->actions->set_pagination(true);
        $this->actions->set_ipp(1);
        $acciones = $this->actions->fetch("modulo_id = '$mid' AND TRIM(ultimo_valor) <> '' ");

        $this->response = "NA";

        foreach ($acciones as $accion) {
          $this->response = "".$accion->columns['comando']."|".$accion->columns['ultimo_valor']."";
          if ($this->actions->fetch_id(array("id" => $accion->columns['id']))) {
            $status = 'OPERATED';

            $this->actions->columns['ultimo_valor'] = "";
            $this->actions->columns['modulo_id'] = $this->actions->columns['modulo_id']['id'];
            $this->actions->columns['tipo_accion'] = $this->actions->columns['tipo_accion']['idtipo_action'];
            $this->actions->columns['updated_at'] = date("Y-m-d H:i:s");

            if (!$this->actions->update()) {
              $this->response = 'UPD_ERR';
            }
          }else{
            $this->response = 'ERR';
          }
        }

        if (isset($reply) && $reply != ''){
          $this->modulo->columns['estado'] = "REPLIED";
          $this->modulo->columns['last_response'] = $reply;
          $this->response = "ACK";
        }else{
          $this->modulo->columns['estado'] = $status;
        }

        $this->modulo->columns['tipo_modulo'] = $this->modulo->columns['tipo_modulo']['idtipo_modulo'];
        $this->modulo->columns['updated_at'] = date("Y-m-d H:i:s");

        if (!$this->modulo->update()) {
          $this->response = 'UPD_M_ERR';
        }
      }else{
        $this->response = 'MOD_ERR';
      }
    }
  }

  //PRIVATE METHODS

  private function validate_module_id_only($id) {

    $validation = false;

    $result = $this->modulo->fetch("id = '$id'");
    if (count($result) <= 0) {
      $this->response = 'El dispositivo no existe';
    } else {
      $validation = true;
    }

    return $validation;
  }

}
?>
