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
			->setAllowEmpty("debug", 0, PCV::vInArray([0,1,'0','1']))
		);
		$file = $post["f"];
		$key = $post["k"];
		$mode = $post["m"];
		$debug = (string)$post["debug"] === '1';
		
		$host = "file.nilwork.net";
		if(isset($_SERVER["HTTP_HOST"])){
			$host = $_SERVER["HTTP_HOST"];
		}
		
		if( ! $file || ! $key){
			$this->displayNotFound();
			return;
		}
		
		list($fileType, $virtualPath) = $this->parseFileType($file);
		
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
		if(!empty($fileConfig["v2"]) && $this->util->isAdmin($this->member) && !$this->isContentV2Open($fileConfig)){
			$this->view->adminNotice = $this->getContentV2Notice($fileConfig);
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
			case 'vr':
				$vrImageItems = isset($fileConfig['image']) ? $fileConfig['image'] : [];
				if (!is_array($vrImageItems)) {
					$vrImageItems = [$vrImageItems];
				}
				if (!$vrImageItems) {
					$this->configLoadErrorAction();
					return;
				}
				$vrImagePresets = [];
				foreach ($vrImageItems as $presetLabel => $vrImageItem) {
					$label = is_string($presetLabel) ? trim($presetLabel) : '';
					$yawOffset = 0.0;
					$preload = null;
					$audioItems = [];
					$vrImageCode = $vrImageItem;

					if (is_array($vrImageItem)) {
						if (isset($vrImageItem['label']) && is_string($vrImageItem['label'])) {
							$label = trim($vrImageItem['label']);
						}
						if (isset($vrImageItem['yaw']) && is_numeric($vrImageItem['yaw'])) {
							$yawOffset = (float)$vrImageItem['yaw'];
						} else if (isset($vrImageItem['yawOffset']) && is_numeric($vrImageItem['yawOffset'])) {
							$yawOffset = (float)$vrImageItem['yawOffset'];
						} else if (isset($vrImageItem['yOffset']) && is_numeric($vrImageItem['yOffset'])) {
							$yawOffset = (float)$vrImageItem['yOffset'];
						}
						if (array_key_exists('preload', $vrImageItem)) {
							$preload = (bool)$vrImageItem['preload'];
						}
						if (isset($vrImageItem['audio']) && is_array($vrImageItem['audio'])) {
							$audioItems = $this->buildVrAudioPresets($vrImageItem['audio'], $host);
						}

						$vrImageCode = $vrImageItem['file'] ?? ($vrImageItem['image'] ?? ($vrImageItem['src'] ?? null));
					}

					if (!is_string($vrImageCode) || $vrImageCode === '') {
						$this->configLoadErrorAction();
						return;
					}
					list($vrMediaType) = $this->parseFileType($vrImageCode);
					if ($vrMediaType !== 'image' && $vrMediaType !== 'movie') {
						$this->configLoadErrorAction();
						return;
					}
					$vrImageConfig = $this->loadFileConfigByCode($vrImageCode);
					if ($vrImageConfig === false || !$vrImageConfig) {
						$this->configLoadErrorAction();
						return;
					}
					if (!$this->checkPermision($vrImageConfig)) {
						$this->displayNoPermision();
						return;
					}

					$preset = [
						'label' => $label,
						'image' => "https://" . $host . "/data/file/?f=" . rawurlencode($vrImageCode) . "&k=" . rawurlencode($vrImageConfig['key']) . "&m=play",
						'type' => $vrMediaType === 'movie' ? 'video' : 'image',
						'yaw' => $yawOffset,
					];
					if ($preload !== null) {
						$preset['preload'] = $preload;
					}
					if ($audioItems) {
						$preset['audio'] = $audioItems;
					}

					$vrImagePresets[] = $preset;
				}

				$this->view->vrImagePresets = $vrImagePresets;
				$this->view->vrDebug = $debug;
				$this->view->vrTitle = isset($fileConfig['title']) ? (string)$fileConfig['title'] : '';
				$this->view->fileName = $this->buildTrackedFileName($fileConfig);
				$this->setTemplatePath("data/player_vr.phtml");

				$this->util->addPlayHistory($this->buildTrackedFileName($fileConfig), $file, $this->member);
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
	
	protected function buildVrAudioPresets(array $audioMap, string $host): array
	{
		$items = [];
		foreach ($audioMap as $audioItem) {
			if (is_string($audioItem)) {
				$audioItem = ['file' => $audioItem];
			}
			if (!is_array($audioItem)) {
				continue;
			}

			$item = [
				'loop' => isset($audioItem['loop']) ? (bool)$audioItem['loop'] : true,
			];
			$audioCode = $audioItem['file'] ?? ($audioItem['audio'] ?? ($audioItem['src'] ?? null));
			if (is_string($audioCode) && trim($audioCode) !== '') {
				$audioCode = trim($audioCode);
				list($audioType) = $this->parseFileType($audioCode);
				if ($audioType !== 'audio' && $audioType !== 'file' && $audioType !== 'movie') {
					continue;
				}

				$audioConfig = $this->loadFileConfigByCode($audioCode);
				if ($audioConfig === false || !$audioConfig || !$this->checkPermision($audioConfig)) {
					continue;
				}

				$item['src'] = "https://" . $host . "/data/file/?f=" . rawurlencode($audioCode) . "&k=" . rawurlencode($audioConfig['key']) . "&m=play";
			} else if (isset($audioItem['files']) && is_array($audioItem['files'])) {
				$variants = $this->buildVrAudioVariants($audioItem['files'], $host);
				if (!$variants) {
					continue;
				}
				$item['mode'] = isset($audioItem['mode']) && is_string($audioItem['mode']) ? trim($audioItem['mode']) : 'random';
				$item['variants'] = $variants;
			} else {
				continue;
			}
			if (isset($audioItem['volume']) && is_numeric($audioItem['volume'])) {
				$item['volume'] = (float)$audioItem['volume'];
			}
			if (isset($audioItem['position']) && is_array($audioItem['position'])) {
				$item['position'] = array_values($audioItem['position']);
			}

			$items[] = $item;
		}

		return $items;
	}

	protected function buildVrAudioVariants(array $audioFiles, string $host): array
	{
		$variants = [];
		foreach ($audioFiles as $audioFile) {
			if (is_string($audioFile)) {
				$audioFile = ['file' => $audioFile];
			}
			if (!is_array($audioFile)) {
				continue;
			}
			$audioCode = $audioFile['file'] ?? ($audioFile['audio'] ?? ($audioFile['src'] ?? null));
			if (!is_string($audioCode) || trim($audioCode) === '') {
				continue;
			}
			$audioCode = trim($audioCode);
			list($audioType) = $this->parseFileType($audioCode);
			if ($audioType !== 'audio' && $audioType !== 'file' && $audioType !== 'movie') {
				continue;
			}
			$audioConfig = $this->loadFileConfigByCode($audioCode);
			if ($audioConfig === false || !$audioConfig || !$this->checkPermision($audioConfig)) {
				continue;
			}
			$variants[] = [
				'src' => "https://" . $host . "/data/file/?f=" . rawurlencode($audioCode) . "&k=" . rawurlencode($audioConfig['key']) . "&m=play",
				'weight' => isset($audioFile['weight']) && is_numeric($audioFile['weight']) ? max(0.0, (float)$audioFile['weight']) : 1.0,
			];
		}
		return $variants;
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
		$fileName = $this->buildResponseFileName($fileConfig, $filePath);
		
		//ログ
		if($mode == "play"){
			$this->util->addPlayHistory($fileName, $file, $this->member);
		}else if($mode == "download"){
			$this->util->addDownloadHistory($fileName, $file, $this->member);
		}

		if($mode == "play"){
			$this->applyBrowserCacheHeaders($fileConfig, $filePath);
		}else{
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Pragma: no-cache");
			header("Expires: 0");
		}

		// MIMEタイプを自動判別（動画や画像など再生可能なものはブラウザで再生される）
		$mimeType = mime_content_type($filePath);
		$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
		if ($ext === 'mp4') $mimeType = 'video/mp4';
		else if ($ext === 'mp3') $mimeType = 'audio/mpeg';
		else if (!is_string($mimeType) || $mimeType === '') $mimeType = 'application/octet-stream';

		// ヘッダを出力
		header('Content-Description: File Transfer');
		header('Content-Type: ' . $mimeType);
		if($mode == "download"){
			header('Content-Disposition: attachment; filename="' . $fileName . '"');
		}else{
			header('Content-Disposition: inline; filename="' . $fileName . '"');
		}

		header('Content-Transfer-Encoding: binary');
		if($mode == "download"){
			header('Content-Length: ' . filesize($filePath));
			ob_clean();
			flush();
			readfile($filePath);
		}else{
			$this->sendInlineFile($filePath);
		}

		return;
	}

	public function contentzipAction()
	{
		if(!$this->member){
			return $this->notfoundAction();
		}

		if(!$this->checkContentZipReferer()){
			$this->displayNotFound();
			return;
		}

		$post = $this->getGet(
			PCF::useParam()
			->set("c",null, PCV::vString(),PCV::vMaxLength(32))
			->set("k",null, PCV::vString(),PCV::vMaxLength(255))
		);
		$contentId = (int)$post["c"];
		$key = (string)$post["k"];
		if ($contentId <= 0 || $key === '') {
			$this->displayNotFound();
			return;
		}

		$rows = $this->loadContentV2ZipRows($contentId);
		if (!$rows) {
			$this->displayNotFound();
			return;
		}

		$keyOk = false;
		foreach ($rows as $row) {
			if ((string)($row['file_key'] ?? '') === $key) {
				$keyOk = true;
				break;
			}
		}
		if (!$keyOk) {
			$this->displayNotFound();
			return;
		}

		$contentConfig = $this->buildContentV2ConfigFromZipRow($rows[0]);
		if (!$this->checkPermision($contentConfig)) {
			$this->displayNoPermision();
			return;
		}

		$tmp = $this->createContentV2Zip($rows);
		if ($tmp === false) {
			$this->configLoadErrorAction();
			return;
		}

		$zipFileName = $this->buildSafeZipFileName((string)($rows[0]['title'] ?? ('content-' . $contentId)), $contentId, (string)($rows[0]['reg_datetime'] ?? ''));
		$this->util->addDownloadHistory($zipFileName, 'content:' . $contentId . ':zip', $this->member);

		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Pragma: no-cache");
		header("Expires: 0");
		header('Content-Description: File Transfer');
		header('Content-Type: application/zip');
		header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
		header('Content-Transfer-Encoding: binary');
		header('Content-Length: ' . filesize($tmp));
		if (ob_get_level()) {
			@ob_clean();
		}
		flush();
		readfile($tmp);
		@unlink($tmp);
		return;
	}

	protected function checkContentZipReferer(): bool
	{
		$host = isset($_SERVER["HTTP_HOST"]) ? (string)$_SERVER["HTTP_HOST"] : '';
		$referer = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
		$refererHost = $referer !== '' ? parse_url($referer, PHP_URL_HOST) : '';
		if ($refererHost === $host || $refererHost === 'www.patreon.com' || $refererHost === 'patreon.com') {
			return true;
		}
		if ($this->isDebug()) {
			$post = $this->getGet(
				PCF::useParam()
				->setAllowEmpty("debug", 0, PCV::vInArray([0,1,'0','1']))
			);
			return (string)$post["debug"] === '1';
		}
		return false;
	}

	protected function loadContentV2ZipRows(int $contentId): array
	{
		try {
			$fileModel = new ContentFileModel();
			$itemModel = new ContentItemModel();
			$fileModel->setCol([
				'file_id',
				'content_id',
				'file_type',
				'code',
				'file_key',
				'storage_path',
				'original_name',
				'display_name',
				'mime_type',
				'file_size',
				'sort_order',
			]);
			$itemModel->setCol([
				'title',
				'description',
				'plan',
				'status',
				'publish_start_at',
				'publish_end_at',
				'reg_datetime',
			]);
			$fileModel->where('content_file.content_id=?', [$contentId]);
			$fileModel->join('content_id', $itemModel, 'content_id');
			$fileModel->orderBy('content_file.sort_order ASC, content_file.file_id ASC');
			$data = $fileModel->select();
			if ($data && $data->total > 0) {
				return $data->data;
			}
		} catch (Exception $e) {
			return [];
		}
		return [];
	}

	protected function buildContentV2ConfigFromZipRow(array $row): array
	{
		return [
			'key' => (string)($row['file_key'] ?? ''),
			'plan' => (string)($row['plan'] ?? 'paid'),
			'title' => (string)($row['title'] ?? ''),
			'desc' => (string)($row['description'] ?? ''),
			'v2' => true,
			'content_id' => (int)($row['content_id'] ?? 0),
			'file_id' => (int)($row['file_id'] ?? 0),
			'status' => (string)($row['status'] ?? 'draft'),
			'publish_start_at' => $row['publish_start_at'] ?? null,
			'publish_end_at' => $row['publish_end_at'] ?? null,
		];
	}

	protected function createContentV2Zip(array $rows)
	{
		if (!class_exists('ZipArchive')) {
			return false;
		}
		$tmp = tempnam(sys_get_temp_dir(), 'nill_content_zip_');
		if ($tmp === false) {
			return false;
		}
		$zip = new ZipArchive();
		if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			@unlink($tmp);
			return false;
		}

		$added = 0;
		$addedNames = [];
		$base = realpath(PCPath::systemRoot() . 'app/res/content/');
		if ($base === false) {
			$zip->close();
			@unlink($tmp);
			return false;
		}

		foreach ($rows as $row) {
			if (($row['file_type'] ?? '') === 'vr') {
				continue;
			}
			$real = $this->resolveContentV2ZipRealPath((string)($row['storage_path'] ?? ''), $base);
			if ($real === false || !is_file($real)) {
				continue;
			}
			$entryName = $this->buildContentV2ZipEntryName($row);
			$entryName = $this->uniqueContentV2ZipEntryName($entryName, $addedNames);
			if ($zip->addFile($real, $entryName)) {
				$added++;
				$addedNames[$entryName] = true;
			}
		}

		$zip->close();
		if ($added <= 0) {
			@unlink($tmp);
			return false;
		}
		return $tmp;
	}

	protected function resolveContentV2ZipRealPath(string $storagePath, string $base)
	{
		$storagePath = ltrim(str_replace(['../', '..\\', '\\'], ['', '', '/'], $storagePath), '/');
		if ($storagePath === '') {
			return false;
		}
		$path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
		$real = realpath($path);
		if ($real === false || strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
			return false;
		}
		return $real;
	}

	protected function buildContentV2ZipEntryName(array $row): string
	{
		$name = (string)($row['display_name'] ?: ($row['original_name'] ?: basename((string)($row['storage_path'] ?? ''))));
		$name = str_replace(['\\', '/', "\0"], ['_', '_', ''], $name);
		$name = trim($name, " \t\n\r\0\x0B.");
		if ($name === '') {
			$name = 'file-' . (int)($row['file_id'] ?? 0);
		}
		return $name;
	}

	protected function uniqueContentV2ZipEntryName(string $name, array $used): string
	{
		if (!isset($used[$name])) {
			return $name;
		}
		$ext = pathinfo($name, PATHINFO_EXTENSION);
		$base = pathinfo($name, PATHINFO_FILENAME);
		$suffix = $ext !== '' ? '.' . $ext : '';
		$i = 2;
		do {
			$next = $base . '_' . $i . $suffix;
			$i++;
		} while (isset($used[$next]));
		return $next;
	}

	protected function buildSafeZipFileName(string $title, int $contentId, string $regDatetime): string
	{
		$name = trim($title);
		$name = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|', "\0"], '_', $name);
		if ($name === '') {
			$name = 'content';
		}
		$date = $regDatetime !== '' ? date('Ymd', strtotime($regDatetime)) : date('Ymd');
		if (!$date || $date === '19700101') {
			$date = date('Ymd');
		}
		if (function_exists('mb_substr')) {
			$name = mb_substr($name, 0, 120);
		} else {
			$name = substr($name, 0, 120);
		}
		return $date . '_' . $contentId . '_' . $name . '.zip';
	}

	protected function sendInlineFile(string $filePath): void
	{
		$fileSize = filesize($filePath);
		$start = 0;
		$end = $fileSize - 1;

		header('Accept-Ranges: bytes');

		$range = isset($_SERVER['HTTP_RANGE']) ? trim((string)$_SERVER['HTTP_RANGE']) : '';
		if ($range !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches)) {
			if ($matches[1] === '' && $matches[2] !== '') {
				$suffixLength = min((int)$matches[2], $fileSize);
				$start = $fileSize - $suffixLength;
			} else {
				$start = (int)$matches[1];
				if ($matches[2] !== '') {
					$end = min((int)$matches[2], $end);
				}
			}

			if ($start > $end || $start < 0 || $end >= $fileSize) {
				header($_SERVER['SERVER_PROTOCOL'] . ' 416 Range Not Satisfiable');
				header('Content-Range: bytes */' . $fileSize);
				return;
			}

			header($_SERVER['SERVER_PROTOCOL'] . ' 206 Partial Content');
			header('Content-Range: bytes ' . $start . '-' . $end . '/' . $fileSize);
		}

		$length = $end - $start + 1;
		header('Content-Length: ' . $length);

		$handle = fopen($filePath, 'rb');
		if ($handle === false) {
			return;
		}

		ob_clean();
		flush();
		fseek($handle, $start);
		$remaining = $length;
		while ($remaining > 0 && !feof($handle)) {
			$chunk = fread($handle, min(8192, $remaining));
			if ($chunk === false || $chunk === '') {
				break;
			}
			echo $chunk;
			$remaining -= strlen($chunk);
		}
		fclose($handle);
	}

	protected function applyBrowserCacheHeaders(array $fileConfig, string $filePath)
	{
		$cacheValue = isset($fileConfig["cache"]) ? $fileConfig["cache"] : false;
		$cacheSeconds = 0;
		if($cacheValue === true){
			$cacheSeconds = 60 * 60 * 24 * 30;
		}else if(is_numeric($cacheValue)){
			$cacheSeconds = (int)$cacheValue;
		}
		if($cacheSeconds <= 0){
			header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
			header("Pragma: no-cache");
			header("Expires: 0");
			return;
		}

		$mtime = @filemtime($filePath);
		if(!$mtime){
			$mtime = time();
		}

		$lastModified = gmdate('D, d M Y H:i:s', $mtime) . ' GMT';
		header("Cache-Control: private, max-age=".$cacheSeconds);
		header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheSeconds) . ' GMT');
		header("Last-Modified: " . $lastModified);

		$ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? trim((string)$_SERVER['HTTP_IF_MODIFIED_SINCE']) : '';
		if($ifModifiedSince !== '' && strtotime($ifModifiedSince) >= $mtime){
			header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
			exit;
		}
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
		if($type != "movie" && $type != "image" && $type != "audio" && $type != "zip" && $type != "file" && $type != "bookmarklet" && $type != "vr" ){
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
		$v2Config = $this->loadContentV2Config($file, $key, $mode, $type, $realPathBase);
		if ($v2Config !== null) {
			return $v2Config;
		}
		
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
		$node['path'] = $path;
		if ($type === 'vr') {
			return $node;
		}
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
		return $node;
	}

	protected function loadContentV2Config($file, $key, $mode, $type, $realPathBase)
	{
		try {
			$fileModel = new ContentFileModel();
			$itemModel = (new ContentItemModel())->setCol([]);
			$fileModel->where('content_file.code=? and content_file.file_key=?', [$file, $key]);
			$fileModel->join('content_id', $itemModel, 'content_id');
			$fileModel->addCol('content_item.title', 'title');
			$fileModel->addCol('content_item.description', 'description');
			$fileModel->addCol('content_item.content_type', 'content_type');
			$fileModel->addCol('content_item.plan', 'plan');
			$fileModel->addCol('content_item.status', 'status');
			$fileModel->addCol('content_item.publish_start_at', 'publish_start_at');
			$fileModel->addCol('content_item.publish_end_at', 'publish_end_at');
			$fileModel->addCol('content_item.config_json', 'config_json');
			$fileModel->limit(1);
			$data = $fileModel->select();
			$row = ($data && $data->total > 0) ? $data->data[0] : null;
		} catch (Exception $e) {
			return null;
		}

		if (!$row) {
			return null;
		}
		if ($row['file_type'] !== $type || $row['file_key'] !== $key) {
			return null;
		}

		$extra = [];
		if (isset($row['config_json']) && trim((string)$row['config_json']) !== '') {
			$extra = json_decode($row['config_json'], true);
			if ($extra === null && json_last_error() !== JSON_ERROR_NONE) {
				return false;
			}
			if (!is_array($extra)) {
				$extra = [];
			}
		}

		$node = $extra;
		$node['key'] = $row['file_key'];
		$node['plan'] = $row['plan'];
		$node['title'] = $row['title'];
		$node['desc'] = $row['description'];
		$node['path'] = $row['storage_path'];
		$node['original_name'] = $row['original_name'] ?? '';
		$node['display_name'] = $row['display_name'] ?? '';
		$node['v2'] = true;
		$node['content_id'] = $row['content_id'];
		$node['file_id'] = $row['file_id'];
		$node['status'] = $row['status'];
		$node['publish_start_at'] = $row['publish_start_at'];
		$node['publish_end_at'] = $row['publish_end_at'];

		if (isset($row['suffix']) && $row['suffix'] !== '') {
			$node['suffix'] = $row['suffix'];
		}
		if (isset($row['cache_value']) && $row['cache_value'] !== '') {
			$node['cache'] = $this->parseContentV2CacheValue($row['cache_value']);
		}

		if ($type === 'vr') {
			return $node;
		}

		$storagePath = ltrim(str_replace(['../', '..\\', '\\'], ['', '', '/'], (string)$row['storage_path']), '/');
		$filePath = rtrim($realPathBase, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
		$baseReal = realpath($realPathBase);
		$fileReal = realpath($filePath);
		if ($baseReal === false || $fileReal === false || strpos($fileReal, $baseReal . DIRECTORY_SEPARATOR) !== 0) {
			return null;
		}
		if (!is_file($fileReal)) {
			return null;
		}

		$node['file_path'] = $fileReal;
		return $node;
	}

	protected function parseContentV2CacheValue($value)
	{
		$value = trim((string)$value);
		if ($value === '') {
			return false;
		}
		if ($value === '1' || strtolower($value) === 'true') {
			return true;
		}
		if (is_numeric($value)) {
			return (int)$value;
		}
		return false;
	}

	protected function isContentV2Open(array $fileConfig): bool
	{
		if (empty($fileConfig['v2'])) {
			return true;
		}

		$status = isset($fileConfig['status']) ? (string)$fileConfig['status'] : 'draft';
		if ($status !== 'published' && $status !== 'scheduled') {
			return false;
		}

		$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
		$start = $this->parseContentV2Date($fileConfig['publish_start_at'] ?? null);
		$end = $this->parseContentV2Date($fileConfig['publish_end_at'] ?? null);

		if ($start && $now < $start) {
			return false;
		}
		if ($end && $now >= $end) {
			return false;
		}
		return true;
	}

	protected function parseContentV2Date($value)
	{
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		try {
			return new DateTime($value, new DateTimeZone('Asia/Tokyo'));
		} catch (Exception $e) {
			return null;
		}
	}

	protected function getContentV2Notice(array $fileConfig): string
	{
		$status = isset($fileConfig['status']) ? (string)$fileConfig['status'] : 'draft';
		if ($status === 'private') {
			return 'このv2コンテンツは非公開です。管理者のみ表示できます。';
		}
		if ($status === 'draft') {
			return 'このv2コンテンツは下書きです。管理者のみ表示できます。';
		}
		if ($status === 'scheduled') {
			return 'このv2コンテンツは予約公開です。公開日時までは管理者のみ表示できます。';
		}
		return 'このv2コンテンツは現在公開されていません。管理者のみ表示できます。';
	}

	protected function loadFileConfigByCode($file)
	{
		return $this->loadFileConfig($file, $this->extractFileKeyFromConfig($file), 'play');
	}

	protected function extractFileKeyFromConfig($file)
	{
		if (!$file) {
			return null;
		}

		$d = explode(":", $file, 2);
		if (count($d) != 2) {
			return null;
		}

		$type = $d[0];
		$path = trim(trim(trim($d[1]), "./"));
		$path = str_replace('../', '', $path);
		$path = str_replace('//', '/', $path);
		$path = preg_replace('/\/\/+/', '/', $path);
		$path = $type . "/" . $path;

		$v2Key = $this->extractContentV2FileKey($file);
		if ($v2Key) {
			return $v2Key;
		}

		$configPath = PCPath::systemRoot() . "app/res/content/file.json";
		if (!file_exists($configPath) || !is_file($configPath)) {
			return null;
		}

		$config = json_decode(file_get_contents($configPath), true);
		if (!is_array($config)) {
			return null;
		}

		$node = $config;
		foreach (explode("/", $path) as $part) {
			if (!isset($node[$part])) {
				return null;
			}
			$node = $node[$part];
		}

		return isset($node['key']) ? $node['key'] : null;
	}

	protected function extractContentV2FileKey($file)
	{
		try {
			$model = new ContentFileModel();
			$data = $model->where("code=?",[$file])->select();
			if ($data && $data->total > 0) {
				return $data->data[0]['file_key'] ?? null;
			}
			return null;
		} catch (Exception $e) {
			return null;
		}
	}

	protected function extractDisplayNameFromCode($fileCode)
	{
		$parts = explode(':', (string)$fileCode, 2);
		$path = count($parts) === 2 ? $parts[1] : $parts[0];
		return basename($path);
	}

	protected function buildTrackedFileName(array $fileConfig)
	{
		$fileNameSource = $this->buildResponseFileName($fileConfig, isset($fileConfig['file_path']) ? $fileConfig['file_path'] : (string)($fileConfig['path'] ?? ''));
		if ($fileNameSource === '') {
			$fileNameSource = 'content';
		}

		return $fileNameSource;
	}

	protected function buildResponseFileName(array $fileConfig, string $filePath): string
	{
		$fileName = '';
		if (!empty($fileConfig['display_name'])) {
			$fileName = (string)$fileConfig['display_name'];
		} else if (!empty($fileConfig['original_name'])) {
			$fileName = (string)$fileConfig['original_name'];
		} else {
			$fileName = basename($filePath);
		}

		$fileName = trim(str_replace(["\r", "\n", '"', '/', '\\', "\0"], '_', $fileName));
		if ($fileName === '') {
			$fileName = 'content';
		}

		$pathExt = pathinfo($filePath, PATHINFO_EXTENSION);
		if ($pathExt !== '' && pathinfo($fileName, PATHINFO_EXTENSION) === '') {
			$fileName .= '.' . $pathExt;
		}

		if (isset($fileConfig['suffix']) && $fileConfig['suffix'] !== '') {
			$suffix = trim(str_replace(["\r", "\n", '"', '/', '\\', "\0"], '_', (string)$fileConfig['suffix']));
			if ($suffix !== '') {
				$dotPos = strrpos($fileName, '.');
				if ($dotPos !== false) {
					return substr($fileName, 0, $dotPos) . "_" . $suffix . substr($fileName, $dotPos);
				}
				return $fileName . "_" . $suffix;
			}
		}

		return $fileName;
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
		if(!empty($fileConfig["v2"]) && !$this->isContentV2Open($fileConfig)){
			return false;
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
