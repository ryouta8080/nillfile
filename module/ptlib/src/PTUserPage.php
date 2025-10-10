<?php

class PTUserPage extends PCMVCBasicPageBase
{
	public $util = null;
	public $login = null;
	public $member = null;
	
	public $gestPage = [
		
	];
	
	protected function init(){
		$this->util = new PTUtil();
		$this->login = new WebLogin();
		$this->member = null;
		$this->view->member = null;
	}
	
	protected function preAction(){
		$this->member = $this->login->auth();
		if($this->member){
			$isLogout = false;
			$patreonInfo = $this->util->getPatreonUserInfoByMember($this->member);
			if($patreonInfo){
				$this->member["patreon"] = $patreonInfo;
				$needUpdate = false;
				if (empty($patreonInfo['upd_datetime'])) {
					$needUpdate = true;
				} else {
					// 現在時刻とDB上の更新時刻を比較
					$lastUpdate = strtotime($patreonInfo['upd_datetime']);
					$now = time();
					$diff = $now - $lastUpdate;

					// 10秒以内ならスキップ
					$needUpdate = ($diff > 10);
				}

				// 判定結果
				if ($needUpdate) {
					//リフレッシュ＋最新情報の取得
					$member = $this->util->ensureFreshPatreonToken($this->member);
					if($member){
						$this->member = $member;
						$patreonInfo = $this->util->getPatreonUserInfoByAPI($member["patreon"]);
						if($patreonInfo){
							//patreonInfo更新
							$this->util->updatePatreonInfo($member,$patreonInfo);
							$this->member = $this->util->getMemberInfoByPatreonId($patreonInfo["id"]);
							
							//print("updated");
							if(!$this->member){
								$isLogout = true;
							}
						}else{
							//patreon情報の取得に失敗した場合はログアウト
							$isLogout = true;
						}
					}else{
						$isLogout = true;
					}
				} else {
					//print("skip");
				}
			}else{
				$isLogout = true;
			}
			if($isLogout){
				$this->login->logout();
				$this->member = null;
			}
		}
		$this->view->member = $this->member;
		$this->setValueErrorsToView();
		
		if( $this->util->isDebug() && ! in_array( $this->pageName,$this->gestPage) ){
			switch (true) {
				case !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']):
				case $_SERVER['PHP_AUTH_USER'] !== 'debug':
				case $_SERVER['PHP_AUTH_PW']   !== 'password':
					header('WWW-Authenticate: Basic realm="Enter username and password."');
					header('Content-Type: text/plain; charset=utf-8');
					die('');
			}
		}
	}
	protected function isDeckViewPage(){
		if($this->pageName == "deck" && $this->actionName == "view"){
			return true;
		}
		
		return false;
	}
	
	public function setError($key,$errorCode=false,$message=false){
		$con = PCMVCDispatcher::getCurrentController();
		if( ! isset($con->view->errors) ){
			$con->view->errors = array();
			$con->view->errors["desc"] = array();
		}
		
		$con->view->errors["desc"][] = array(
			"code"=>$errorCode,
			"name"=>".error_".$key,
			"message" => $message,
		);
	}
	public function setValueErrorsToView(){
		$con = PCMVCDispatcher::getCurrentController();
		$error = array();
		if(isset($con->sessionParam["errors"])){
			if( ! isset($con->sessionParam["errors"]["desc"])){
				$con->errors["errors"] = array(
					"desc" => $con->sessionParam["errors"],
				);
			}else{
				$con->view->errors = $con->sessionParam["errors"];
			}
			if(isset($con->sessionParam["formValues"])){
				$con->view->formValues = $con->sessionParam["formValues"];
			}
		}
	}
	
	public function checkLogin(){
		if(!$this->member){
			$url = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
			$this->redirect("/login", ["url"=>$url]);
			return false;
		}
		return true;
	}

}
