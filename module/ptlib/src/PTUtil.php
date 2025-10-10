<?php

use Patreon\API;
use Patreon\OAuth;

class PTUtil{
	
	public $config = null;
	
	public function isDebug(){
		if( ! isset($_SERVER["HTTP_HOST"])){
			//batch
			return true;
		}
		if( $_SERVER["HTTP_HOST"] == "dev-file.nilwork.net"){
			return true;
		}
		return false;
	}
	public function ua(){
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
	}
	/**
	* スマホか判定
	* @param  string $ua ユーザエージェント
	* @return boolean スマートフォンからのアクセスか否か
	*/
	public function isSmartphone($ua = null)
	{
		if ( is_null($ua) ) {
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}

		if ( preg_match('/iPhone|iPod|iPad|Android/ui', $ua) ) {
			return true;
		} else {
			return false;
		}
	}
	/**
	* デバイス判定
	* @param  string $ua ユーザエージェント
	* @return string pc|android|ios
	*/
	public function getDeviceType($ua = null)
	{
		if ( is_null($ua) ) {
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}

		if ( preg_match('/iPhone|iPod|iPad/ui', $ua) ) {
			return 'ios';
		} else if ( preg_match('/Android/ui', $ua) ) {
			return 'android';
		} else {
			return 'pc';
		}
	}
	
	function __construct() {
		$this->loadConfig();
	}
	
	public function loadConfig(){
		$this->config = PCConfig::get();
	}
	//oauth直後に実行する
	public function getPatreonUserInfoByAPI($tokens){
		$now = time();
		if(!$tokens){
			return null;
		}
		if(! isset($tokens['access_token'])){
			return null;
		}
		$access_token = $tokens['access_token'];
		
		$api = PCConfig::get()->o->work->api->patreon;
		$clientId = $api->clientId;
		$clientSecret = $api->clientSecret;
		$campaign_id = $api->campaignId;

		// APIクライアントを作成
		$api_client = new API($access_token);

		// ユーザー情報を取得
		$params = "include=memberships.currently_entitled_tiers,memberships.campaign"
		        . "&fields[user]=email,first_name,full_name,image_url,last_name,thumb_url,url,vanity,is_email_verified"
		        . "&fields[member]=currently_entitled_amount_cents,lifetime_support_cents,campaign_lifetime_support_cents,last_charge_status,patron_status,last_charge_date,pledge_relationship_start,pledge_cadence";

		$identity = $api_client->get_data("identity?{$params}");

		if (!$identity) {
			//print("ユーザー情報取得失敗");
			return null;
		}
		
		if ( !isset( $identity['data'] ) ) {
			//print("ユーザー情報取得失敗");
			return null;
		}
		
		//メンバー情報取り出し
		$membership = null;
		if ( isset( $identity['included'][0] ) && is_array( $identity['included'][0] ) ) {
			
			foreach ($identity['included'] as $key => $value) {

				if ( $identity['included'][$key]['type'] == 'member' && ( isset( $identity['included'][$key]['relationships']['campaign'] ) && $campaign_id && $identity['included'][$key]['relationships']['campaign']['data']['id'] == $campaign_id ) ) {
					
					$membership = $identity['included'][$key];
					
					break;
				}
			}
		}
		
		// 支援ステータスを確認
		$patron_status = $membership && isset($membership['attributes']) && isset($membership['attributes']['patron_status']) ? $membership['attributes']['patron_status'] : null;
		
		$current_tier_id = $this->getCurrentTierId($membership);
		
		$patreonInfo = [
			"id"     => $identity['data']['id'],
			"name"   => $identity['data']['attributes']['full_name'],
			"status" => $patron_status,
			"current_tier_id" => $current_tier_id,
			
			"token_type" => $tokens["token_type"],
			"access_token" => $tokens["access_token"],
			"expires_in" => $tokens["expires_in"],
			"refresh_token" => $tokens["refresh_token"],
			"scope" => $tokens["scope"],
			
			"obtained_at" => $tokens["obtained_at"],
			"expires_at" => $tokens["expires_at"],
		];
		/*
		print("<pre>");
		var_dump($membership);
		print_r($identity);
		print_r($tokens);
		var_dump($patreonInfo);
		print("</pre>");
		*/
		return $patreonInfo;
	}
	
	public function getPatreonUserInfoByMember($member){
		if(!$member){
			return null;
		}
		$memberPatreonModel = new MemberPatreonModel();
		$data = $memberPatreonModel->where("member_id=?",[$member["member_id"]])->select();
		if(!$data){
			return false;
		}
		if($data && $data->total > 0){
			$patreonInfo = $data->data[0];
			return $patreonInfo;
		}
		return null;
	}
	
	public function getCurrentTierId($member){
		if (
			isset($member['relationships']['currently_entitled_tiers']['data']) &&
			is_array($member['relationships']['currently_entitled_tiers']['data']) &&
			count($member['relationships']['currently_entitled_tiers']['data']) > 0
		) {
			$tier = $member['relationships']['currently_entitled_tiers']['data'][0];
			if (isset($tier['id'])) {
				return $tier['id'];
			}
		}
		return null;
	}
	
	public function getPatreonRedirectUrl(){
		if( ! isset($_SERVER["HTTP_HOST"])){
			return "";
		}
		$redirectUri = "https://".$_SERVER["HTTP_HOST"]."/login/patreoncallback";
		return $redirectUri;
	}
	
	/*
	必要に応じてトークンをリフレッシュ
	*/
	function ensureFreshPatreonToken($member)
	{
		if(!$member || !isset($member["patreon"]) ){
			return null;
		}
		$auth = $member["patreon"];
		$api = PCConfig::get()->o->work->api->patreon;
		$clientId = $api->clientId;
		$clientSecret = $api->clientSecret;
		$campaign_id = $api->campaignId;
		
		// 余裕時間（秒）
		$leeway = 120;
		$now = time();

		// expires_at を計算
		$expiresAt = isset($auth['obtained_at'], $auth['expires_in'])
			? ((int)$auth['obtained_at'] + (int)$auth['expires_in'])
			: null;

		$needsRefresh = $expiresAt !== null ? (($expiresAt - $now) <= $leeway) : true;
		
		if (!$needsRefresh) {
			return $member; // まだ有効
		}

		// patreon-php の OAuth クライアントでリフレッシュ
		$oauth = new OAuth($clientId, $clientSecret);

		// ※ refresh_token は毎回更新される（シングルユース）
		$tokens = $oauth->refresh_token($auth['refresh_token'], $this->getPatreonRedirectUrl() );

		if (!is_array($tokens) || empty($tokens['access_token'])) {
			// 失敗時は再認可が必要なケース
			//throw new RuntimeException('Patreon token refresh failed: ' . json_encode($tokens));
			return null;
		}

		// 配列を更新
		$auth['access_token']  = $tokens['access_token'];
		$auth['refresh_token'] = $tokens['refresh_token'] ?? $auth['refresh_token'];
		$auth['token_type']    = $tokens['token_type']    ?? ($auth['token_type'] ?? 'Bearer');
		$auth['expires_in']    = isset($tokens['expires_in']) ? (int)$tokens['expires_in'] : (int)($auth['expires_in'] ?? 0);
		$auth['obtained_at']   = $now;

		//トークンをDBに保存
	    return $this->updatePatreonInfo($member,$auth);
	}
	
	public function getMemberInfoByPatreonId($id){
		$memberPatreonModel = new MemberPatreonModel();
		$data = $memberPatreonModel->where("id=?",[$id])->select();
		if(!$data){
			return false;
		}
		if($data && $data->total > 0){
			$patreonInfo = $data->data[0];
			$memberModel = new MemberModel();
			$data = $memberModel->where("member_id=?",[$patreonInfo["member_id"]])->select();
			if(!$data){
				return false;
			}
			if($data && $data->total > 0){
				$member = $data->data[0];
				$member["patreon"] = $patreonInfo;
				return $member;
			}
		}
		return null;
	}
	//apiから取得したpatreonInfoを更新する
	public function updatePatreonInfo($member,$patreonInfo){
		if(!$patreonInfo){
			return false;
		}
		$member["patreon"] = $patreonInfo;
		$member["patreon"]["member_id"] = $member["member_id"];
		
		$memberPatreonModel = new MemberPatreonModel();
		$data = $member["patreon"];
		$data["upd_datetime"] = "now()";
		$memberPatreonModel->save($data,["upd_datetime"=>"no_escape"]);
		return $this->getMemberInfoByPatreonId($member["patreon"]["id"]);
	}
	
	public $STATE_CIPHER      = 'aes-256-gcm';
	public $STATE_IV_BYTES    = 12; // 推奨
	public $STATE_TAG_BYTES   = 16; // 推奨 (128bit)
	public function urlsafe_b64encode(string $bin): string {
		return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
	}
	public function urlsafe_b64decode(string $txt): string {
		$remainder = strlen($txt) % 4;
		if ($remainder) {
			$txt .= str_repeat('=', 4 - $remainder);
		}
		return base64_decode(strtr($txt, '-_', '+/'));
	}

	/**
	 * $payload を JSON にして暗号化し、URLパラメータに載せられるトークンを返す
	 * $b64Key: Base64エンコードされた32バイト鍵（configから）
	 */
	public function encrypt_state(array $payload, string $plainKey){
		$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
		$iv = random_bytes($this->STATE_IV_BYTES);
		$tag = '';

		$cipher = openssl_encrypt(
			$json,
			$this->STATE_CIPHER,
			$plainKey,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			$this->STATE_TAG_BYTES
		);
		return $this->urlsafe_b64encode($iv . $tag . $cipher);
	}

	/**
	 * encrypt_state() で作ったトークンを復号し、配列を返す
	 */
	public function decrypt_state(string $token, string $plainKey): array {
		$raw = $this->urlsafe_b64decode($token);
		$iv     = substr($raw, 0, $this->STATE_IV_BYTES);
		$tag    = substr($raw, $this->STATE_IV_BYTES, $this->STATE_TAG_BYTES);
		$cipher = substr($raw, $this->STATE_IV_BYTES + $this->STATE_TAG_BYTES);

		$json = openssl_decrypt(
			$cipher,
			$this->STATE_CIPHER,
			$plainKey,
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			''
		);
		if ($json === false) throw new RuntimeException('Decryption failed.');

		$arr = json_decode($json, true);
		if (!is_array($arr)) throw new RuntimeException('Invalid JSON');
		return $arr;
	}
	
	public function getLoginUrl(){
		// 現在のURLを取得（クエリ付きで）
		$current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
		$current_url .= "://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$login_url = "/login/patreon?url=" . urlencode($current_url);
		return $login_url;
	}
	
	public function isAdmin($member){
		$api = PCConfig::get()->o->work->api->patreon;
		$adminId = $api->adminId;

		// member情報が有効かチェック
		if (
		    !$member ||
		    !isset($member["patreon"]["id"]) ||
		    empty($member["patreon"]["id"])
		) {
			return false;
		}

		$patreonId = $member["patreon"]["id"];

		// adminId が配列なら in_array で判定
		if (is_array($adminId)) {
			return in_array($patreonId, $adminId);
		}

		// 単一値なら直接比較
		return $patreonId == $adminId;
	}
	
	public function addPlayHistory($fileName, $path, $member=[]){
		$param = [
			"code" => $path,
			"name" => $fileName,
		];
		$action = "play";
		$this->addHistory($action, $param, $member);
	}
	public function addDownloadHistory($fileName, $path, $member=[]){
		$param = [
			"code" => $path,
			"name" => $fileName,
		];
		$action = "download";
		$this->addHistory($action, $param, $member);
	}
	
	public function addHistory($action,$param=[],$member=[], $lang="ja"){
		if($this->isDebug()){
			return true;
		}
		$model = new ActionHistoryModel();
		$targetId = isset($member["patreon"]) && isset($member["patreon"]["id"]) ? $member["patreon"]["id"] : null;
		
		$p = [
			"action" => $action,
			"target_id" => $targetId,
			"title_id" => isset($param["title_id"]) ? $param["title_id"] : null,
			"code" => isset($param["code"]) ? $param["code"] : null,
			"name" => isset($param["name"]) ? $param["name"] : null,
			"keyword" => isset($param["keyword"]) ? $param["keyword"] : null,
			"rule_type" => isset($param["rule_type"]) ? $param["rule_type"] : null,
			"policy" => isset($param["policy"]) ? $param["policy"] : null,
			"card_number" => isset($param["card_number"]) ? $param["card_number"] : null,
			"card_name" => isset($param["card_name"]) ? $param["card_name"] : null,
			"page" => isset($param["page"]) ? $param["page"] : null,
			"page_order" => isset($param["order"]) ? $param["order"] : null,
			"os" => isset($member["device"]) && isset($member["device"]["os"]) ? $member["device"]["os"] : null,
			"mid" => isset($member["member_id"]) ? $member["member_id"] : null,
			"device_id" => isset($member["device"]) && isset($member["device"]["device_id"]) ? $member["device"]["device_id"] : null,
			"lang" => isset($param["lang"]) ? $param["lang"] : null,
			"ver" => isset($param["ver"]) ? $param["ver"] : null,
			"param" => $param ? json_encode($param) : null,
		];
		
		$model->save($p);
		
		return true;
	}
	
	
	/**
	 * JSONを再帰的に探索してファイル情報を列挙
	 */
	function listFilesRecursive(array $node, string $basePath = ''): array {
		$files = [];

		foreach ($node as $name => $value) {
			$currentPath = ltrim($basePath . '/' . $name, '/');

			if (is_array($value)) {
				// 配列内がファイル情報か、さらにフォルダを含むか判定
				$isFile = isset($value['plan']) || isset($value['key']) || isset($value['suffix']);

				if ($isFile) {
					// ファイル情報を格納
					$files[] = [
						'path' => $basePath,   // 上位パス
						'name' => $name,       // ファイル名
						'config' => $value     // 設定内容
					];
				} else {
					// 再帰的にフォルダを探索
					$files = array_merge($files, listFilesRecursive($value, $currentPath));
				}
			}
		}

		return $files;
	}
	
}