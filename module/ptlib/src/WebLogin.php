<?php

class WebLogin{
	
	public $message = array();
	public $defaultMessage = array(
		"USER_LOGIN_LOCK" => "アカウントが一時的にロックされています。しばらく時間をあけてからログインしなおしてください",
		"USER_LOGIN_LOCK_START" => "一定回数以上ログインに失敗したためアカウントを15分間、一時的にロックします。15分後ログインしなおしてください。",
		"USER_LOGIN_LOCK_START_NUMBER" => "一定回数以上操作に失敗したためアカウントを15分間、一時的にロックします。新しい4桁の確認番号をメールで送信しましたので15分後入力しなおしてください。",
		"USER_LOGIN_AUTH_NUMBER_FAILED" => "4桁の確認番号が異なります。新しい4桁の確認番号をメールで送信しましたのでご確認いただき入力してください",
		"USER_LOGIN_URL_AUTH_DUPLICATION" => "有効期限が切れているか無効なURLです。",
		"USER_LOGIN_ID_OR_PASS" => "ユーザIDまたはパスワードが違います",
		"USER_LOGIN_FAILED" => "ログインに失敗しました。",
		"USER_LOGIN_FAILED_GOOGLE" => "ログインに失敗しました。Googleアカウントで本サイトに登録していない場合は、先に新規登録をしてください",
		"USER_LOGIN_AUTH_FAILED" => "ログイン中にエラーが発生しました",
		"USER_LOGIN_EMAIL_DUPLICATION" => "すでに使用されています",
		"USER_LOGIN_SNS_EMAIL_DUPLICATION" => "指定されたアカウントのメールアドレスはすでに使用されています",
		"USER_LOGIN_ID_DUPLICATION" => "すでに使用されています",
		"USER_LOGIN_EMAIL_AUTH_DUPLICATION" => "既にメールアドレスが認証されているか、登録されていないメールアドレスです",
		"USER_LOGIN_DISABLE" => "このアカウントは利用できません",
		"USER_LOGIN_RESIST_FAILED" => "登録に失敗しました",
		"USER_LOGIN_DUPLICATION" => "すでに登録済みのユーザです",
		"USER_LOGIN_PLEASE_RETRY" => "ログイン中にエラーが発生しました。お手数ですがもう一度やり直してください",
		"USER_LOGIN_EMAIL_NOTEXIST" => "指定したメールアドレスは登録されていないか、仮登録のためパスワードの変更はできません（仮登録でパスワードをお忘れの方は新規登録をやり直してください）",
		"FORM_ERROR_RECAPTCHA" => "ログイン処理に失敗しました。もう一度やり直してください。",
	);
	public $lastErrorCode = false;
	public $lastErrorMessage = false;
	private $initMessageFlag = false;
	private $error_message = "";
	
	private $ctoken = false;
	
	private $controllerInfo = false;

	private $ALC_KEY = 'wdblk';
	private $ALC_TOKEN = 'wdbln';
	private $cookiePath = '/';
	
	private $LoginModel ="WebLoginModel";
	private $UserModel ="MemberModel";
	
	private $info = null;
	
	private function createModelObject($className){
		$reflClass = new ReflectionClass($className);
		$model = $reflClass->newInstance();
		return $model;
	}
	public function getLoginModel(){
		return $this->createModelObject($this->LoginModel);
	}
	public function getUserModel(){
		return $this->createModelObject($this->UserModel);
	}
	
	public function user(){
		return $this->userInfo;
	}
	public function isAuth(){
		return isset($this->userInfo);
	}
	
	function auth() {
		if($this->isAuth()){
			return true;
		}
		if (isset($_COOKIE[$this->ALC_KEY]) && isset($_COOKIE[$this->ALC_TOKEN]) ){
			
			$token = $_COOKIE[$this->ALC_TOKEN];
			$key = $_COOKIE[$this->ALC_KEY];
			
			$login = $this->getLoginModel();
			$d = $login->where('l_token=? and l_key=?', array( $token, $key ) )
						->select();
			if($d===false){
				$this->setError("USER_LOGIN_DB_ERROR","U-A-DE-1");
				return false;
			}

			if($d->total > 0){
			
				$loginData = $d->data[0];
				$user_id = $loginData['id'];
				
				$reg = $loginData['reg_datetime'];
				$change = PCDate::addDay($reg, 1);//カラムが1日前の場合は新しいトークンを発行する
				if(new DateTime($change) < new DateTime()){
					$this->clearAuthInfo();
					$r = $this->setupAutoLogin($user_id);
				}else{
					$r=true;
				}
				
				$user = $this->getUserModel();
				$d = $user->where('member_id=?', array($user_id) )
							->select();
				if($d===false){
					$this->setError("USER_LOGIN_DB_ERROR","U-A-DE-2");
					return false;
				}
				if($d->total > 0){
					$userData = $d->data[0];
					$this->info = $userData;
					return $userData;
				}
			}
		}
		
		$this->setError("USER_LOGIN_FAILED");
		$this->clearAuthInfo();
		return false;
		
	}
	
	public function setLoginData($user_id, $userData){
		$this->setupAutoLogin($user_id);
		$this->info = $userData;
	}
	
	private function setupAutoLogin($user_id) {
		// 認証が完了し、自動ログインを設定
		$auto_login_key = sha1(uniqid().mt_rand(1,999999999)); // keyを生成
		$auto_login_token = sha1($user_id . uniqid() ).time(); // keyを生成
		
		$login = $this->getLoginModel();
		$result = $login->insert(
			array(
				'id' => $user_id,
				'l_token' => $auto_login_token,
				'l_key' => $auto_login_key,
				'l_expire' => date("Y-m-d H:i:s", time()+3600*24*7),
			)
		);
		if( ! $result){
			$this->setError("USER_LOGIN_PLEASE_RETRY");
			return false;
		}
		
		$this->deleteCookie($this->ALC_KEY);
		$this->deleteCookie($this->ALC_TOKEN);
		
		setcookie($this->ALC_KEY, $auto_login_key, time() +3600*24*7, $this->cookiePath, "", true, true ); //有効期限7日の自動ログインクッキーを送信
		setcookie($this->ALC_TOKEN, $auto_login_token, time() +3600*24*7, $this->cookiePath, "", true, true );
		
		return true;
	}
	
	private function clearAuthInfo() {
		if (isset($_COOKIE[$this->ALC_KEY]) && isset($_COOKIE[$this->ALC_TOKEN]) ){
			$token = $_COOKIE[$this->ALC_TOKEN];
			$key = $_COOKIE[$this->ALC_KEY];
			
			// 古い自動ログインkeyを削除
			$login = $this->getLoginModel();
			$result = $login->delete(
				array(),
				"l_token=? and l_key=?",
				array( $token, $key )
			);
		}
		
		// 自動ログイン用のクッキーを削除
		$this->deleteCookie($this->ALC_KEY);
		$this->deleteCookie($this->ALC_TOKEN);
		$this->info = null;
		return true;
	}
	function deleteCookie($key){
		setcookie($key, "", time() - 3600, $this->cookiePath, "", true, true );
	}
	
	function logout() {
		return $this->clearAuthInfo();
	}
	
	public function clearError(){
		$this->lastErrorCode = false;
		$this->lastErrorMessage = false;
	}
	public function setError($code, $symbol="", $param=false){
		if( ! $this->initMessageFlag){
			$this->initMesage();
		}
		$this->lastErrorCode = $code;
		if(isset($this->message[$code])){
			$em = new PCErrorMessage($code, $this->message[$code], $param);
			$this->lastErrorMessage = $em->getMessage();
		}else{
			$this->lastErrorMessage = "予期せぬエラーが発生しました[".$code."-".$symbol."]";
		}
	}
	public function initMesage(){
		$this->message = array_merge($this->defaultMessage, $this->message);
		$this->initMessageFlag = true;
	}
	public function getErrorCode(){
		return $this->lastErrorCode;
	}
	public function getErrorMessage(){
		return $this->lastErrorMessage;
	}
	
}