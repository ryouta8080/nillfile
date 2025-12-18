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
		
		list($fileType, $virtualPath) = $this->parseFileType($file);
		if ($fileType === 'zip' && $mode !== 'download') {
			$mode = 'download';
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
		
		$this->view->fileType = $fileType;
		
		$baseEmbedUrl    = "https://" . $host . "/data/embed/?f=" . rawurlencode($file) . "&k=" . rawurlencode($key) . "&m=" . rawurlencode($mode);
		$baseFileUrl     = "https://" . $host . "/data/file/?f=" . rawurlencode($file) . "&k=" . rawurlencode($key) . "&m=" . rawurlencode($mode);
		$baseDownloadUrl = "https://" . $host . "/data/file/?f=" . rawurlencode($file) . "&k=" . rawurlencode($key) . "&m=download";
		
		switch ($fileType) {
			case 'movie':
				// 動画プレイヤー用
				$this->view->embed    = $baseEmbedUrl;
				$this->view->video    = $baseFileUrl;
				$this->view->download = $baseDownloadUrl;
				break;

			case 'image':
				// 画像ビューア用
				$this->view->fileType    = 'image';
				$this->view->imageUrl    = $baseFileUrl;       // inline表示（m=play）
				$this->view->downloadUrl = $baseDownloadUrl;   // attachment（m=download）
				$this->setTemplatePath("data/player_image.phtml");
				break;
			case 'file':
			case 'zip':
				$this->view->fileType    = $fileType;          // 'file' or 'zip'
				$this->view->downloadUrl = $baseDownloadUrl;
				$this->view->fileUrl     = $baseFileUrl;       // inlineで開く用途（使わなくてもOK）
				$this->setTemplatePath("data/player_download.phtml");
				break;

			case 'bookmarklet':
				$this->view->bookmarkletUrl = $baseFileUrl;
				$this->setTemplatePath("/data/player_bookmarklet.phtml");
				$this->view->scriptTitle = $fileConfig["title"];
				$this->view->scriptDesc = $fileConfig["desc"];
				break;
		}
		
		$this->view->title = "";
		$this->view->description = "";
		
		$this->display();
	}
	protected function parseFileType($file)
	{
		$file = (string)$file;
		$parts = explode(':', $file, 2);

		if (count($parts) === 2) {
			$type = $parts[0];
			$path = $parts[1];
		} else {
			// タイプ指定が無い場合は既存互換のため file とみなす
			$type = 'file';
			$path = $parts[0];
		}

		return [$type, $path];
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
		if( ! $this->util->isAdmin($this->member)){
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

	public function gifAction()
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
		if( ! $this->util->isAdmin($this->member)){
			$this->displayNoPermision();
			return;
		}
		
		$videoUrl = "https://".$host."/data/file/?f=".$file."&k=".$key."&m=sm";
		$this->view->video = $videoUrl;
		
		$this->view->title = "";
		$this->view->description = "";
		
		//$this->setTemplatePath("index/index.phtml");
		
		header("Cross-Origin-Opener-Policy: same-origin");
		header("Cross-Origin-Embedder-Policy: require-corp");

		// キャッシュを無効化（推奨）
		header("Cache-Control: no-cache, no-store, must-revalidate");
		header("Pragma: no-cache");
		header("Expires: 0");

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
			$this->util->addPlayHistory($fileName, $file, $this->member);
		}else if($mode == "download"){
			$this->util->addDownloadHistory($fileName, $file, $this->member);
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
		if($type != "movie" && $type != "image" && $type != "zip" && $type != "file" && $type != "bookmarklet" ){
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
		
		if ($type === 'zip' && (!file_exists($realPath) || !is_file($realPath))) {
			if (isset($node['files']) && is_array($node['files']) && !empty($node['files'])) {
				$ok = $this->generateZipIfNeeded($realPathBase, $realPath, $node['files']);
				if (!$ok) {
					// 生成失敗はサーバ側の問題なので false（CONFIG_ERROR 扱い）にする
					return false;
				}
			}
		}
		
		if( ! file_exists($realPath) || !is_file($realPath) ){
			return null;
		}
		$filePath = $realPath;
		$node['file_path'] = $filePath;
		$node['path'] = $path;
		return $node;
	}
	
	/**
	 * files 設定から zip を生成（無ければ作る）
	 * @param string $realPathBase 例: app/res/content/
	 * @param string $zipRealPath  例: app/res/content/zip/kasumi_1.zip
	 * @param array  $filesConfig  例: ["kasumi_*.mp4"=>["path"=>"movie/kasumi_*.mp4"], ...]
	 * @return bool
	 */
	private function generateZipIfNeeded(string $realPathBase, string $zipRealPath, array $filesConfig): bool
	{
		// zip 出力先ディレクトリを用意
		$dir = dirname($zipRealPath);
		if (!is_dir($dir)) {
			if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
				return false;
			}
		}

		// 念のためベース外を書けないようにチェック
		$baseReal = realpath($realPathBase);
		if ($baseReal === false) return false;

		// 既に存在していてファイルなら何もしない
		if (file_exists($zipRealPath) && is_file($zipRealPath)) {
			return true;
		}

		// 一時ファイルに作ってから置き換える（途中失敗で壊れたzipが残らない）
		$tmp = $zipRealPath . '.tmp_' . bin2hex(random_bytes(4));

		$zip = new ZipArchive();
		if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			return false;
		}

		$added = 0;
		$addedNames = [];

		foreach ($filesConfig as $zipEntryName => $info) {
			if (!is_array($info)) continue;
			$virtual = (string)($info['path'] ?? '');
			$virtual = trim($virtual);

			if ($virtual === '') continue;

			// パス正規化（危険なもの排除）
			$virtual = ltrim($virtual, '/');
			$virtual = str_replace(['../', '..\\'], '', $virtual);
			$virtual = preg_replace('#/+#', '/', $virtual);

			// 例: movie/kasumi_*.mp4 → 実パスパターンへ
			$pattern = rtrim($realPathBase, '/') . '/' . $virtual;

			// glob（ワイルドカード対応）
			$matches = glob($pattern, GLOB_NOSORT);
			if (!$matches) continue;

			$isZipNameWildcard = (strpos($zipEntryName, '*') !== false) || (strpos($zipEntryName, '?') !== false);

			foreach ($matches as $filePath) {
				// 実在チェック
				if (!is_file($filePath)) continue;

				// ベース配下かチェック（zipスリップ等を防ぐ）
				$real = realpath($filePath);
				if ($real === false) continue;
				if (strpos($real, $baseReal . DIRECTORY_SEPARATOR) !== 0 && $real !== $baseReal) {
					continue;
				}

				// zip内のファイル名
				// - zipEntryName がワイルドカードなら、実ファイルの basename を採用
				// - そうでなければ、zipEntryName を採用
				$entryName = $isZipNameWildcard ? basename($real) : (string)$zipEntryName;

	            // zip内に危険なパスを入れない
				$entryName = str_replace(['\\', "\0"], ['/', ''], $entryName);
				$entryName = ltrim($entryName, '/');
				if ($entryName === '' || strpos($entryName, '../') !== false) continue;

				// 同名重複を避ける（後勝ちにしたいならここを変えてください）
				if (isset($addedNames[$entryName])) continue;

				if ($zip->addFile($real, $entryName)) {
					$added++;
					$addedNames[$entryName] = true;
				}
			}
		}

		$zip->close();

		// 1件も入らなかったら失敗扱い（空zipを作りたくない場合）
		if ($added <= 0) {
			@unlink($tmp);
			return false;
		}

		// アトミックに差し替え
		if (!rename($tmp, $zipRealPath)) {
			@unlink($tmp);
			return false;
		}

		return true;
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
		
		if($patreonInfo["status"] || $patreonInfo["status"] == "active_patron"){
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
