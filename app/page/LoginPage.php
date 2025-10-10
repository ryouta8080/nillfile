<?php

require_once("BasePageClass.php");

use Patreon\API;
use Patreon\OAuth;

class LoginPage extends PTUserPage
{

	public function indexAction()
	{
		$get = $this->getGet(
			PCF::useParam()
			->setAllowEmpty("url", null, PCV::vEnable(), PCV::vMyDomainUrl())
		);
		$url = $get["url"];
		if($url){
			$this->view->url = $url;
		}else{
			$this->view->url = "";
		}
		if($this->member){
			if($url){
				$this->redirect($url);
			}else{
				$this->redirect("/account/mypage");
			}
			return;
		}
		
		$this->view->title = "ログイン";
		$this->display();
	}
	
	public function patreonAction()
	{
		$get = $this->getGet(
			PCF::useParam()
			->setAllowEmpty("url", "", PCV::vEnable(), PCV::vMyDomainUrl())
		);
		if($this->member){
			$this->redirect("/account/mypage");
			return;
			$url = $get["url"];
			if($url){
				$this->redirect($url);
			}else{
				$this->redirect("/account/mypage");
			}
			return;
		}
		
		$api = PCConfig::get()->o->work->api->patreon;
		$statusKey = $api->statusKey;
		
		if( ! isset($_SERVER["HTTP_HOST"])){
			$this->displayNotFound();
			return;
		}
		
		$redirectUri = $this->getPatreonRedirectUrl();
		
		$statePayload  = [
			'url' => $get["url"]
		];
		$state = $this->util->encrypt_state($statePayload, $statusKey);
		
		$auth_url = "https://www.patreon.com/oauth2/authorize?response_type=code"
			. "&client_id=" . $api->clientId
			. "&redirect_uri=" . urlencode($redirectUri)
			. "&scope=identity%20identity.memberships"
			. "&state=" . urlencode($state);
		header("Location: $auth_url");
		return;
	}
	
	public function getPatreonRedirectUrl(){
		return $this->util->getPatreonRedirectUrl();
	}
	
	public function patreoncallbackAction()
	{
		if (!isset($_GET['code'])) {
			//print("認証コードがありません");
			return $this->autherror();
		}
		$api = PCConfig::get()->o->work->api->patreon;
		$clientId = $api->clientId;
		$clientSecret = $api->clientSecret;
		$campaign_id = $api->campaignId;
		$statusKey = $api->statusKey;
		
		$returnUrl = null;
		// stateをデコード
		if (!empty($_GET['state'])) {
			$state = $this->util->decrypt_state($_GET['state'], $statusKey);
			$returnUrl = $state["url"];
		}
		
		$oauth_client = new OAuth($clientId, $clientSecret);

		// アクセストークンを取得
		$tokens = $oauth_client->get_tokens($_GET['code'], $this->getPatreonRedirectUrl());
		$obtained_at = time();
		$expires_at = isset($tokens['expires_in']) ? $obtained_at + (int)$tokens['expires_in'] : null;
		
		$tokens['obtained_at'] = $obtained_at;
		$tokens['expires_at'] = $expires_at;
		
		$patreonInfo = $this->util->getPatreonUserInfoByAPI($tokens);
		if (!isset($tokens['access_token'])) {
			//print("アクセストークン取得失敗");
			return $this->autherror();
		}
		
		$member = $this->util->getMemberInfoByPatreonId($patreonInfo["id"]);
		if($member === false){
			//print("システムエラー");
			return $this->autherror();
		}
		if(!$member){
			//新規ユーザ作成
			$memberModel = new MemberModel();
			
			$result = $memberModel->edit(
				function() use ($patreonInfo){
					$memberModel = new MemberModel();
					$data = array(
						'nickname' => $patreonInfo["name"],
						'uid' => $patreonInfo["id"],
					);
					$lastId = $memberModel->save($data);
					$memberPatreonModel = new MemberPatreonModel();
					$patreonInfo["member_id"] = $lastId;
					$memberPatreonModel->save($patreonInfo);
				}
			);
			if(!$result){
				//print("システムエラー");
				return $this->autherror();
			}
			$member = $this->util->getMemberInfoByPatreonId($patreonInfo["id"]);
			if($member === false){
				//print("システムエラー");
				return $this->autherror();
			}
		}else{
			//patreonInfo更新
			$this->util->updatePatreonInfo($member,$patreonInfo);
			$member = $this->util->getMemberInfoByPatreonId($patreonInfo["id"]);
		}
		
		//ログイン処理
		$this->login->setLoginData($member["member_id"], $member);
		
		/*
		print("<pre>");
		var_dump($returnUrl);
		
		var_dump($member);
		
		var_dump($patreonInfo);
		print("</pre>");
		*/
		if($returnUrl){
			$this->redirect($returnUrl);
			return;
		}
		$this->redirect("/account/mypage");
		
	}
	
	public function autherror($message=null)
	{
		$loginUrl = "/login";
		$this->view->message = $message ? $message : "認証に失敗しました";
		$this->setError("form_message","ERROR",$this->view->message);
		$this->redirect( $loginUrl, array( "errors"=> $this->view->errors, "formValues"=>[] ), true );
		return;
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}
	
}
