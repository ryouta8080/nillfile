<?php

require_once("BasePageClass.php");

class DataPage extends PTUserPage
{

	public function indexAction()
	{
		$this->displayNotFound();
	}
	
	public function embedAction()
	{
		$post = $this->getGet(
			PCF::useParam()
			->set("f",null, PCV::vString(),PCV::vMaxLength(255))
			->set("k",null, PCV::vString(),PCV::vMaxLength(255))
			->set("m","play", PCV::vInArray(["play","download"]))
		);
		$file = $post["f"];
		$key = $post["k"];
		$mode = $post["m"];
		
		$host = "file.nilwork.net";
		if(isset($_SERVER["HTTP_HOST"])){
			$host = $_SERVER["HTTP_HOST"];
		}
		
		$url = "https://".$host."/data/player/?f=".$file."&k=".$key."&m=".$mode;
		
		$content = [
			"version" => "1.0",
			"title"=> "Display the Video",
			"width"=> "100%",
			"height"=> 600,
			"type"=> "rich",
			"provider_name"=> "Nill Video",
			"provider_url"=> "https://".$host,
			"html"=> "<iframe id='nill-frame' src='".$url."' allowfullscreen='true' style='width:100%;height:600px;border:1px #ccc solid;border-radius:10px;'></iframe>",
			"url"=> $url
		];
		
		if( ! $file || ! $key){
			$this->displayNotFound();
			return;
		}
		
		header('Content-Type: application/json');
		header("Access-Control-Allow-Origin: *");
		echo json_encode($content);
	}
	
	public function playerAction()
	{
		if(!$this->member){
			$this->redirect($this->util->getLoginUrl());
			return;
		}
		$post = $this->getGet(
			PCF::useParam()
			->set("f",null, PCV::vString(),PCV::vMaxLength(255))
			->set("k",null, PCV::vString(),PCV::vMaxLength(255))
			->set("m","play", PCV::vInArray(["play","download"]))
		);
		$file = $post["f"];
		$key = $post["k"];
		$mode = $post["m"];
		
		$host = "file.nilwork.net";
		if(isset($_SERVER["HTTP_HOST"])){
			$host = $_SERVER["HTTP_HOST"];
		}
		
		if( ! $file || ! $key){
			$this->displayNotFound();
			return;
		}
		
		$fileConfig = $this->loadFileConfig($file,$key,$mode);
		if($fileConfig === false){
			$this->configLoadErrorAction();
			return;
		}
		if(!$fileConfig){
			$this->displayNotFound();
			return;
		}
		if( ! $this->checkPermision($fileConfig)){
			$this->displayNoPermision();
			return;
		}
		
		$embedUrl = "https://".$host."/data/embed/?f=".$file."&k=".$key."&m=".$mode;
		$this->view->embed = $embedUrl;
		
		$videoUrl = "https://".$host."/data/file/?f=".$file."&k=".$key."&m=".$mode;
		$this->view->video = $videoUrl;
		
		$downloadUrl = "https://".$host."/data/file/?f=".$file."&k=".$key."&m=download";
		$this->view->download = $downloadUrl;
		
		$this->view->title = "";
		$this->view->description = "";
		
		//$this->setTemplatePath("index/index.phtml");
		$this->display();
	}
	
	public function smAction()
	{
		if(!$this->member){
			$this->redirect($this->util->getLoginUrl());
			return;
		}
		$post = $this->getGet(
			PCF::useParam()
			->set("f",null, PCV::vString(),PCV::vMaxLength(255))
			->set("k",null, PCV::vString(),PCV::vMaxLength(255))
			->set("m","play", PCV::vInArray(["play","download"]))
		);
		$file = $post["f"];
		$key = $post["k"];
		$mode = $post["m"];
		
		$host = "file.nilwork.net";
		if(isset($_SERVER["HTTP_HOST"])){
			$host = $_SERVER["HTTP_HOST"];
		}
		
		if( ! $file || ! $key){
			$this->displayNotFound();
			return;
		}
		
		$fileConfig = $this->loadFileConfig($file,$key,$mode);
		if($fileConfig === false){
			$this->configLoadErrorAction();
			return;
		}
		if(!$fileConfig){
			$this->displayNotFound();
			return;
		}
		if( ! $this->checkPermision($fileConfig)){
			$this->displayNoPermision();
			return;
		}
		
		$videoUrl = "https://".$host."/data/file/?f=".$file."&k=".$key."&m=sm";
		$this->view->video = $videoUrl;
		
		$this->view->title = "";
		$this->view->description = "";
		
		//$this->setTemplatePath("index/index.phtml");
		$this->display();
	}
	
	public function fileAction()
	{
		if(!$this->member){
			return $this->notfoundAction();
			$this->redirect($this->util->getLoginUrl());
			return;
		}
		
		$host = "";
		if(isset($_SERVER["HTTP_HOST"])){
			$host = $_SERVER["HTTP_HOST"];
		}
		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : null;
		if( !$referer || (
			parse_url( $referer )['host'] !== $host
			&& parse_url( $referer )['host'] !== "www.patreon.com"
			&& parse_url( $referer )['host'] !== "patreon.com"
			)
		) {
			if($this->isDebug()){
				$post = $this->getGet(
					PCF::useParam()
					->set("debug",null, PCV::vInArray(["1"]))
				);
				$debug = $post["debug"];
				if($debug != "1"){
					$this->displayNotFound();
					return;
				}
			}else{
				$this->displayNotFound();
				return;
			}
		}
		
		$post = $this->getGet(
			PCF::useParam()
			->set("f",null, PCV::vString(),PCV::vMaxLength(255))
			->set("k",null, PCV::vString(),PCV::vMaxLength(255))
			->set("m","play", PCV::vInArray(["play","download","sm"]))
		);
		$file = $post["f"];
		$key = $post["k"];
		$mode = $post["m"];
		
		if($mode == "sm"){
			if( ! $this->util->isAdmin($this->member) ){
				$this->displayNotFound();
				return;
			}
		}
		
		$fileConfig = $this->loadFileConfig($file,$key,$mode);
		if($fileConfig === false){
			$this->configLoadErrorAction();
			return;
		}
		if(!$fileConfig){
			$this->displayNotFound();
			return;
		}
		
		if( ! $this->checkPermision($fileConfig)){
			$this->displayNoPermision();
			return;
		}
		
		$node = $fileConfig;
		$filePath = $fileConfig["file_path"];
		
		// ファイル名（ダウンロード時の名前）
		$fileName = basename($filePath);
		$originalFileName = $fileName;
		// ファイル名に suffix を付与（拡張子の前に追加）
		if (isset($node['suffix']) && $node['suffix'] !== '') {
			$dotPos = strrpos($originalFileName, '.');
			if ($dotPos !== false) {
				$fileName = substr($originalFileName, 0, $dotPos) ."_". $node['suffix'] . substr($originalFileName, $dotPos);
			} else {
				$fileName = $originalFileName . $node['suffix'];
			}
		}
		
		//ログ
		if($mode == "play"){
			$this->util->addPlayHistory($fileName, $fileConfig["path"], $this->member);
		}else if($mode == "download"){
			$this->util->addDownloadHistory($fileName, $fileConfig["path"], $this->member);
		}

		// MIMEタイプを自動判別（動画や画像など再生可能なものはブラウザで再生される）
		$mimeType = mime_content_type($filePath);

		// ヘッダを出力
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $mimeType);
		if($mode == "download"){
			header('Content-Disposition: attachment; filename="' . $fileName . '"');
		}else{
			header('Content-Disposition: inline; filename="' . $fileName . '"'); 
		}

		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . filesize($filePath));

		// バッファを消してから出力
		ob_clean();
		flush();
		readfile($filePath);
		
		return;
	}
	
	public function loadFileConfig($file,$key,$mode){
		
		if( ! $file || ! $key){
			return null;
		}
		
		$d = explode(":",$file);
		if( count($d) != 2){
			return null;
		}
		
		$type = $d[0];
		$path = $d[1];
		if($type != "movie" && $type != "image" && $type != "zip" && $type != "file"){
			return null;
		}
		
		$path = trim( trim( trim($path), "./"));
		$path = str_replace('../', '', $path);
		$path = str_replace('//', '/', $path);
		$path = preg_replace('/\/\/+/', '/', $path);
		
		$path = $type . "/" . $path;
		
		$pathList = explode("/",$path);
		if(count($pathList) <= 1 && count($pathList) > 4){
			return null;
		}
		
		$realPathBase = PCPath::systemRoot() . "app/res/content/";
		
		//設定ファイルロード
		$configPath = $realPathBase . "file.json";
		
		// 設定ファイル存在チェック
		if (!file_exists($configPath) || !is_file($configPath)) {
			return false;
		}

		// 設定ファイルを読み込み
		$configContent = file_get_contents($configPath);
		$config = json_decode($configContent, true);

		// JSONエラー判定
		if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
			return false;
		}

		// 階層をたどる
		$node = $config;
		foreach ($pathList as $part) {
			if (!isset($node[$part])) {
				return null;
			}
			$node = $node[$part];
		}

		// 最後のノードに key があるか確認
		if (!isset($node['key'])) {
			return false;
		}

		// key 照合
		if ($node['key'] !== $key) {
			return null;
		}
		
		$path = implode("/",$pathList);
		$realPath = $realPathBase . $path;
		if( ! file_exists($realPath) || !is_file($realPath) ){
			return null;
		}
		$filePath = $realPath;
		$node['file_path'] = $filePath;
		$node['path'] = $path;
		return $node;
	}
	public function checkPermision($fileConfig){
		$isFree = false;
		if($fileConfig["plan"] == "free"){
			$isFree = true;
		}
		
		if(!$this->member){
			return false;
		}
		
		if($this->util->isAdmin($this->member)){
			return true;
		}
		
		$patreonInfo = $this->member["patreon"];
		//そもそも登録していない
		if(! $patreonInfo["current_tier_id"]){
			return false;
		}

		if($isFree){
			return true;
		}
		
		if($patreonInfo["status"]){
			return true;
		}
		return false;
	}
	
	public function configLoadErrorAction()
	{ 
		echo "サーバーエラーが発生しました。クリエイターに連絡してください。[Error Code : CONFIG_ERROR]";
		return;
	}
	public function displayNoPermision()
	{ 
		echo "現在ログインされているアカウントではこのページを観覧する権限がありません。 <a href=\"/account/logout\">[ログアウト]</a>";
		return;
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}
	
}
