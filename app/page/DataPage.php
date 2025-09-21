<?php

require_once("BasePageClass.php");

class DataPage extends BasePageClass
{

	public function indexAction()
	{
		$this->displayNotFound();
	}
	
	public function playerAction()
	{
		$this->view->title = "";
		$this->view->description = "";
		
		//$this->setTemplatePath("index/index.phtml");
		$this->display();
	}
	
	public function fileAction()
	{
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
			->set("m","play", PCV::vInArray(["play","download"]))
		);
		$file = $post["f"];
		$key = $post["k"];
		$mode = $post["m"];
		
		if( ! $file || ! $key){
			$this->displayNotFound();
			return;
		}
		
		$d = explode(":",$file);
		if( count($d) != 2){
			$this->displayNotFound();
			return;
		}
		
		$type = $d[0];
		$path = $d[1];
		if($type != "movie" && $type != "image" && $type != "zip" && $type != "file"){
			$this->displayNotFound();
			return;
		}
		
		$path = trim( trim( trim($path), "./"));
		$path = str_replace('../', '', $path);
		$path = str_replace('//', '/', $path);
		$path = preg_replace('/\/\/+/', '/', $path);
		
		$path = $type . "/" . $path;
		
		$pathList = explode("/",$path);
		if(count($pathList) <= 1 && count($pathList) > 4){
			$this->displayNotFound();
			return;
		}
		
		$realPathBase = PCPath::systemRoot() . "app/res/content/";
		
		//設定ファイルロード
		$configPath = $realPathBase . "file.json";
		
		// 設定ファイル存在チェック
		if (!file_exists($configPath) || !is_file($configPath)) {
			$this->configLoadErrorAction();
			return;
		}

		// 設定ファイルを読み込み
		$configContent = file_get_contents($configPath);
		$config = json_decode($configContent, true);

		// JSONエラー判定
		if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
			$this->configLoadErrorAction();
			return;
		}

		// 階層をたどる
		$node = $config;
		foreach ($pathList as $part) {
			if (!isset($node[$part])) {
				$this->displayNotFound();
				return;
			}
			$node = $node[$part];
		}

		// 最後のノードに key があるか確認
		if (!isset($node['key'])) {
			$this->configLoadErrorAction();
			return;
		}

		// key 照合
		if ($node['key'] !== $key) {
			$this->displayNotFound();
			return;
		}
		
		$path = implode("/",$pathList);
		$realPath = $realPathBase . $path;
		if( ! file_exists($realPath) || !is_file($realPath) ){
			$this->displayNotFound();
			return;
		}
		$filePath = $realPath;
		
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
	
	public function configLoadErrorAction()
	{ 
		echo "サーバーエラーが発生しました。クリエイターに連絡してください。[Error Code : CONFIG_ERROR]";
		return;
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}
	
}
