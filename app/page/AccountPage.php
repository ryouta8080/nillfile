<?php

class AccountPage extends PTUserPage
{
	
	public function logoutAction()
	{
		$this->login->logout();
		$this->redirect("/login");
	}
	
	public function mypageAction()
	{
		if(!$this->member){
			$this->redirect("/login");
			return;
		}
		
		$member_id = $this->member["member_id"];
		
		if( $this->util->isAdmin($this->member) ){
			$config = $this->loadFileConfig();
			if ($config === false) {
				$this->configLoadErrorAction();
				return;
			}
			$fileList = $this->listFilesRecursive($config);
			
			$host = "file.nilwork.net";
			if(isset($_SERVER["HTTP_HOST"])){
				$host = $_SERVER["HTTP_HOST"];
			}
			
			foreach ($fileList as $index => $file) {
				/*
				echo "Path: {$file['path']}\n";
				echo "File: {$file['name']}\n";
				echo "Config:\n";
				print_r($file['config']);
				echo "-------------------------\n";
				*/
				
				$filePath = $file['path'];
				$key = $file['config']['key'];
				$fileType = strtok($filePath, ':');
				
				$videoUrl = "https://".$host."/data/player?f=".$filePath."&k=".$key;
				$file["video"] = $videoUrl;
				
				if ($fileType !== 'vr') {
					$downloadUrl = "https://".$host."/data/file?f=".$filePath."&k=".$key."&m=download";
					$file["download"] = $downloadUrl;
					
					$smUrl = "https://".$host."/data/sm?f=".$filePath."&k=".$key;
					$file["sm"] = $smUrl;
					
					$gifUrl = "https://".$host."/data/gif?f=".$filePath."&k=".$key."&m=download";
					$file["gif"] = $gifUrl;
				} else {
					$file["download"] = "";
					$file["sm"] = "";
					$file["gif"] = "";
				}
				
				
				$playlogUrl = "https://".$host."/account/useuser?a=play&c=".$filePath;
				$file["playlog"] = $playlogUrl;
				
				$dllogUrl = "https://".$host."/account/useuser?a=download&c=".$filePath;
				$file["dllog"] = $dllogUrl;
				
				$fileList[$index] = $file;
			}
			$this->view->fileList = $fileList;
			
			$this->view->countData = null;
			$action = new ActionCountModel();
			$actionData = $action->select();
			if($actionData && $actionData->total > 0){
				$this->view->countData = $actionData->data;
			}
			
			$this->setTemplatePath("account/admin.phtml");
		}
		
		$this->view->title = "マイページ";
		$this->display();
	}
	
	public function useuserAction()
	{
		if(!$this->member){
			$this->redirect("/login");
			return;
		}
		
		$member_id = $this->member["member_id"];
		
		if( $this->util->isAdmin($this->member) ){
			
			$post = $this->getGet(
				PCF::useParam()
				->set("a",null, PCV::vInArray(["play","download"]))
				->set("c",null, PCV::vString(),PCV::vMaxLength(255))
			);
			$action = $post["a"];
			$code = $post["c"];
			
			$model = new ActionHistoryModel();
			$model->where("action=? and code=?",[$action,$code]);
			$patreonModel = new MemberPatreonModel();
			$model->join("target_id",$patreonModel,"id");
			$model->addCol("count(*)","cnt");
			$model->addCol("max(action_history.reg_datetime)","last");
			
			$model->groupBy(["target_id"]);
			$data = $model->select();
			$this->view->list = [];
			if($data && $data->total > 0){
				$this->view->list = $data->data;
			}
			
			$this->view->title = "リスト";
			//$this->setTemplatePath("account/admin.phtml");
			$this->display();
			return;
		}
		
		$this->displayNotFound();
	}

	public function contentAction()
	{
		if(!$this->member){
			$this->redirect("/login");
			return;
		}
		if(!$this->util->isAdmin($this->member)){
			$this->displayNotFound();
			return;
		}

		$errors = [];
		$infos = [];
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			if (!$this->checkContentCsrf($_POST['_csrf'] ?? '')) {
				$errors[] = 'Invalid CSRF token.';
			} else {
				try {
					$op = (string)($_POST['operation'] ?? '');
					if ($op === 'upload') {
						$this->createContentV2();
						$this->redirect('/account/content?created=1');
						return;
					} else if ($op === 'update') {
						$this->updateContentV2();
						$this->redirect('/account/content?updated=1');
						return;
					} else if ($op === 'delete_item') {
						$this->deleteContentV2Item();
						$this->redirect('/account/content?deleted=1');
						return;
					} else if ($op === 'delete_file') {
						$this->deleteContentV2File();
						$this->redirect('/account/content?file_deleted=1');
						return;
					}
					$errors[] = 'Unknown operation.';
				} catch (Exception $e) {
					$errors[] = $e->getMessage();
				}
			}
		}

		if (isset($_GET['created'])) $infos[] = 'Content was created.';
		if (isset($_GET['updated'])) $infos[] = 'Content was updated.';
		if (isset($_GET['deleted'])) $infos[] = 'Content was deleted.';
		if (isset($_GET['file_deleted'])) $infos[] = 'File was deleted.';

		$this->view->errors = $errors;
		$this->view->infos = $infos;
		$this->view->csrf = $this->contentCsrfToken();
		$this->view->rows = $this->loadContentV2Rows();
		$this->view->title = 'Content v2';
		$this->setTemplatePath("account/content.phtml");
		$this->display();
	}

	private function contentCsrfToken(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		if (empty($_SESSION['content_v2_csrf'])) $_SESSION['content_v2_csrf'] = bin2hex(random_bytes(16));
		return $_SESSION['content_v2_csrf'];
	}

	private function checkContentCsrf(string $token): bool
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		return isset($_SESSION['content_v2_csrf']) && hash_equals($_SESSION['content_v2_csrf'], $token);
	}

	private function createContentV2(): void
	{
		$type = $this->normalizeContentV2Choice($_POST['content_type'] ?? 'movie', ['movie','image','audio','zip','file','bookmarklet','vr'], 'movie');
		$plan = $this->normalizeContentV2Choice($_POST['plan'] ?? 'paid', ['paid','free'], 'paid');
		$status = $this->normalizeContentV2Choice($_POST['status'] ?? 'draft', ['draft','private','published','scheduled'], 'draft');
		$title = trim((string)($_POST['title'] ?? ''));
		if ($title === '') {
			$title = 'Untitled content';
		}
		$configJson = $this->normalizeContentV2ConfigJson($_POST['config_json'] ?? '');
		$startAt = $this->normalizeContentV2Date($_POST['publish_start_at'] ?? '');
		$endAt = $this->normalizeContentV2Date($_POST['publish_end_at'] ?? '');

		$files = $_FILES['files'] ?? ['name' => [], 'error' => []];
		if (!isset($files['name']) || !is_array($files['name'])) {
			throw new RuntimeException('Upload files are required.');
		}

		$uploadIndexes = [];
		foreach ($files['name'] as $i => $name) {
			if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
				$uploadIndexes[] = $i;
			}
		}
		if (!$uploadIndexes && $type !== 'vr') {
			throw new RuntimeException('Upload files are required.');
		}
		$uploadDisplayNames = $this->parseUploadDisplayNames($_POST['upload_display_names'] ?? []);
		$uploadFileNames = $this->buildUploadFileNames($files, $uploadIndexes, $uploadDisplayNames);

		$itemModel = new ContentItemModel();
		$contentId = 0;
		$transactionError = '';
		$movedPaths = [];
		$result = $itemModel->edit(function() use ($itemModel, $title, $type, $plan, $status, $startAt, $endAt, $configJson, $files, $uploadIndexes, $uploadDisplayNames, $uploadFileNames, &$contentId, &$transactionError, &$movedPaths) {
			try {
				$contentId = (int)$itemModel->save([
					'title' => $title,
					'description' => trim((string)($_POST['description'] ?? '')),
					'content_type' => $type,
					'plan' => $plan,
					'status' => $status,
					'publish_start_at' => $startAt,
					'publish_end_at' => $endAt,
					'config_json' => $configJson,
					'memo' => trim((string)($_POST['memo'] ?? '')),
				]);
				if ($contentId <= 0) {
					$transactionError = 'Failed to save content.';
					return false;
				}
				$order = 0;
				foreach ($uploadIndexes as $i) {
					$this->saveContentV2UploadedFile($contentId, $type, $files, $i, $order, $uploadDisplayNames, $uploadFileNames, $movedPaths);
					$order++;
				}
				if ($type === 'vr' && !$uploadIndexes) {
					$this->saveContentV2VirtualVrFile($contentId, $title);
				}
				return true;
			} catch (Exception $e) {
				$transactionError = $e->getMessage();
				return false;
			}
		});
		if (!$result) {
			foreach ($movedPaths as $path) {
				if (is_file($path)) {
					@unlink($path);
				}
			}
			throw new RuntimeException($transactionError ?: ($itemModel->getLastErrorMessage() ?: 'Failed to create content.'));
		}
	}

	private function saveContentV2VirtualVrFile(int $contentId, string $title): void
	{
		$year = date('Y');
		$month = date('m');
		$path = 'v2/' . $year . '/' . $month . '/content-' . $contentId;
		$code = 'vr:' . $path;
		$dupModel = new ContentFileModel();
		$dupData = $dupModel->where('code=?', [$code])->select(false);
		if ($dupData && $dupData->total > 0) {
			throw new RuntimeException('File code already exists: ' . $code);
		}

		$displayName = $this->normalizeContentDisplayName($title);
		if ($displayName === '') {
			$displayName = 'VR content ' . $contentId;
		}

		$fileModel = new ContentFileModel();
		$saved = $fileModel->save([
			'content_id' => $contentId,
			'file_type' => 'vr',
			'code' => $code,
			'file_key' => $this->generateContentV2Key(),
			'storage_path' => 'vr/' . $path,
			'original_name' => $displayName,
			'display_name' => $displayName,
			'mime_type' => 'application/json',
			'file_size' => 0,
			'suffix' => '',
			'cache_value' => '',
			'is_primary' => 1,
			'sort_order' => 0,
		]);
		if (!$saved) {
			throw new RuntimeException($fileModel->getLastErrorMessage() ?: 'Failed to save VR URL.');
		}
	}

	private function saveContentV2UploadedFile(int $contentId, string $type, array $files, int $index, int $order, array $uploadDisplayNames, array $uploadFileNames, array &$movedPaths): void
	{
		$error = (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK) {
			throw new RuntimeException('Upload failed. error=' . $error);
		}
		$tmp = (string)$files['tmp_name'][$index];
		if (!is_uploaded_file($tmp)) {
			throw new RuntimeException('Invalid upload file.');
		}

		$originalName = (string)$files['name'][$index];
		$year = date('Y');
		$month = date('m');
		$dir = PCPath::systemRoot() . 'app/res/content/' . $type . '/v2/' . $year . '/' . $month;
		if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
			throw new RuntimeException('Failed to create upload directory.');
		}

		$fileName = $uploadFileNames[$order] ?? $this->buildContentStorageFileName('', $originalName);
		$dest = $dir . '/' . $fileName;
		if (file_exists($dest)) {
			throw new RuntimeException('File already exists on server: ' . $fileName);
		}
		if (!move_uploaded_file($tmp, $dest)) {
			throw new RuntimeException('Failed to move upload file.');
		}
		$movedPaths[] = $dest;

		$relativePath = $type . '/v2/' . $year . '/' . $month . '/' . $fileName;
		$code = $type . ':v2/' . $year . '/' . $month . '/' . $fileName;
		$dupModel = new ContentFileModel();
		$dupData = $dupModel->where('code=?', [$code])->select(false);
		if ($dupData && $dupData->total > 0) {
			throw new RuntimeException('File code already exists: ' . $code);
		}
		$fileKey = $this->generateContentV2Key();
		$displayName = $fileName;
		$mime = @mime_content_type($dest);
		if (!$mime) {
			$mime = 'application/octet-stream';
		}

		$fileModel = new ContentFileModel();
		$saved = $fileModel->save([
			'content_id' => $contentId,
			'file_type' => $type,
			'code' => $code,
			'file_key' => $fileKey,
			'storage_path' => $relativePath,
			'original_name' => $fileName,
			'display_name' => $displayName,
			'mime_type' => $mime,
			'file_size' => (int)filesize($dest),
			'suffix' => trim((string)($_POST['suffix'] ?? '')),
			'cache_value' => trim((string)($_POST['cache_value'] ?? '')),
			'is_primary' => $order === 0 ? 1 : 0,
			'sort_order' => $order,
		]);
		if (!$saved) {
			throw new RuntimeException($fileModel->getLastErrorMessage() ?: 'Failed to save file.');
		}
	}

	private function updateContentV2(): void
	{
		$contentId = (int)($_POST['content_id'] ?? 0);
		if ($contentId <= 0) {
			throw new RuntimeException('content_id is required.');
		}
		$plan = $this->normalizeContentV2Choice($_POST['plan'] ?? 'paid', ['paid','free'], 'paid');
		$status = $this->normalizeContentV2Choice($_POST['status'] ?? 'draft', ['draft','private','published','scheduled'], 'draft');
		$configJson = $this->normalizeContentV2ConfigJson($_POST['config_json'] ?? '');
		$itemModel = new ContentItemModel();
		$itemModel->save([
			'content_id' => $contentId,
			'title' => trim((string)($_POST['title'] ?? 'Untitled content')),
			'description' => trim((string)($_POST['description'] ?? '')),
			'plan' => $plan,
			'status' => $status,
			'publish_start_at' => $this->normalizeContentV2Date($_POST['publish_start_at'] ?? ''),
			'publish_end_at' => $this->normalizeContentV2Date($_POST['publish_end_at'] ?? ''),
			'config_json' => $configJson,
			'memo' => trim((string)($_POST['memo'] ?? '')),
		]);

		$fileIds = $_POST['file_id'] ?? [];
		if (is_array($fileIds)) {
			$fileModel = new ContentFileModel();
			foreach ($fileIds as $fileIdRaw) {
				$fileId = (int)$fileIdRaw;
				if ($fileId <= 0) continue;
				$fileRow = $this->loadContentFileRow($fileId);
				if (!$fileRow || (int)$fileRow['content_id'] !== $contentId) {
					throw new RuntimeException('Invalid file_id.');
				}
				$displayName = $this->normalizeContentDisplayName($_POST['file_display_name'][$fileId] ?? '');
				$renamed = $this->renameContentFileIfNeeded($fileRow, $displayName);
				$saved = $fileModel->save([
					'file_id' => $fileId,
					'original_name' => $renamed['display_name'],
					'display_name' => $renamed['display_name'],
					'storage_path' => $renamed['storage_path'],
					'code' => $renamed['code'],
					'suffix' => trim((string)($_POST['file_suffix'][$fileId] ?? '')),
					'cache_value' => trim((string)($_POST['file_cache_value'][$fileId] ?? '')),
					'sort_order' => (int)($_POST['file_sort_order'][$fileId] ?? 0),
				]);
				if (!$saved) {
					throw new RuntimeException($fileModel->getLastErrorMessage() ?: 'Failed to save file.');
				}
			}
		}
	}

	private function deleteContentV2Item(): void
	{
		$contentId = (int)($_POST['content_id'] ?? 0);
		if ($contentId <= 0) {
			throw new RuntimeException('content_id is required.');
		}
		$fileModel = new ContentFileModel();
		$fileData = $fileModel->where("content_id=?",[$contentId])->select();
		if ($fileData && $fileData->total > 0) {
			foreach ($fileData->data as $file) {
				$this->deleteContentFilePhysical($file);
				$fileModel->delete(['file_id' => $file['file_id']]);
			}
		}
		$itemModel = new ContentItemModel();
		$itemModel->delete(['content_id' => $contentId]);
	}

	private function deleteContentV2File(): void
	{
		$fileId = (int)($_POST['delete_file_id'] ?? 0);
		if ($fileId <= 0) {
			throw new RuntimeException('file_id is required.');
		}
		$file = $this->loadContentFileRow($fileId);
		if (!$file) {
			throw new RuntimeException('File not found.');
		}
		$this->deleteContentFilePhysical($file);
		$fileModel = new ContentFileModel();
		$fileModel->delete(['file_id' => $fileId]);
	}

	private function loadContentV2Rows(): array
	{
		try {
			$fileModel = new ContentFileModel();
			$itemModel = new ContentItemModel();
			$playCountModel = (new ActionCountModel())->setTableName('pc')->setCol([]);
			$downloadCountModel = (new ActionCountModel())->setTableName('dc')->setCol([]);
			$zipDownloadCountModel = (new ActionCountModel())->setTableName('zc')->setCol([]);

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
				'suffix',
				'cache_value',
				'is_primary',
			]);
			$fileModel->addCol('content_file.sort_order', 'file_sort_order');
			$itemModel->setCol([
				'content_id',
				'title',
				'description',
				'content_type',
				'plan',
				'status',
				'publish_start_at',
				'publish_end_at',
				'sort_order',
				'config_json',
				'memo',
				'del_flag',
				'upd_datetime',
				'reg_datetime',
			]);
			$fileModel->join('content_id', $itemModel, 'content_id');
			$fileModel->leftJoinWhere($playCountModel, "pc.action='play' and pc.code=content_file.code");
			$fileModel->leftJoinWhere($downloadCountModel, "dc.action='download' and dc.code=content_file.code");
			$fileModel->leftJoinWhere($zipDownloadCountModel, "zc.action='download' and zc.code=CONCAT('content:', content_item.content_id, ':zip')");
			$fileModel->addCol('COALESCE(pc.cnt, 0)', 'play_count');
			$fileModel->addCol('COALESCE(dc.cnt, 0)', 'download_count');
			$fileModel->addCol('COALESCE(zc.cnt, 0)', 'content_zip_download_count');
			$fileModel->orderBy('content_item.reg_datetime DESC, content_file.sort_order ASC, content_file.file_id ASC');

			$data = $fileModel->select();
			if ($data && $data->total > 0) {
				return $data->data;
			}
			return [];
		} catch (Exception $e) {
			$this->view->errors = array_merge($this->view->errors ?? [], ['content tables are not ready. Please apply database/content.sql.']);
			return [];
		}
	}

	private function normalizeContentV2Choice($value, array $allowed, string $default): string
	{
		$value = (string)$value;
		return in_array($value, $allowed, true) ? $value : $default;
	}

	private function normalizeContentV2ConfigJson($value)
	{
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		json_decode($value, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			throw new RuntimeException('config_json is invalid JSON: ' . json_last_error_msg());
		}
		return $value;
	}

	private function parseUploadDisplayNames($value): array
	{
		if (is_array($value)) {
			return array_map(function($name) {
				return $this->normalizeContentDisplayName($name);
			}, array_values($value));
		}
		$value = str_replace(["\r\n", "\r"], "\n", (string)$value);
		if (trim($value) === '') {
			return [];
		}
		return array_map(function($name) {
			return $this->normalizeContentDisplayName($name);
		}, explode("\n", $value));
	}

	private function buildUploadFileNames(array $files, array $uploadIndexes, array $displayNames): array
	{
		$names = [];
		$seen = [];
		$order = 0;
		foreach ($uploadIndexes as $index) {
			$originalName = (string)($files['name'][$index] ?? '');
			$fileName = $this->buildContentStorageFileName($displayNames[$order] ?? '', $originalName);
			$key = strtolower($fileName);
			if (isset($seen[$key])) {
				throw new RuntimeException('Duplicate upload filename: ' . $fileName);
			}
			$seen[$key] = true;
			$names[$order] = $fileName;
			$order++;
		}
		return $names;
	}

	private function normalizeContentDisplayName($value): string
	{
		$value = trim((string)$value);
		$value = str_replace(["\r", "\n", '/', '\\', "\0"], '', $value);
		return function_exists('mb_substr') ? mb_substr($value, 0, 255) : substr($value, 0, 255);
	}

	private function buildContentStorageFileName(string $desiredName, string $fallbackName): string
	{
		$name = $this->normalizeContentDisplayName($desiredName);
		if ($name === '') {
			$name = $this->normalizeContentDisplayName($fallbackName);
		}
		$name = basename($name);
		$name = trim($name, ' .');
		if ($name === '') {
			$name = 'content.bin';
		}

		$fallbackExt = pathinfo($fallbackName, PATHINFO_EXTENSION);
		if ($fallbackExt !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
			$name .= '.' . $fallbackExt;
		}

		if (strlen($name) > 180) {
			$ext = pathinfo($name, PATHINFO_EXTENSION);
			$base = pathinfo($name, PATHINFO_FILENAME);
			$extPart = $ext !== '' ? '.' . substr($ext, 0, 16) : '';
			$name = substr($base, 0, 180 - strlen($extPart)) . $extPart;
		}
		return $name;
	}

	private function loadContentFileRow(int $fileId)
	{
		$model = new ContentFileModel();
		$data = $model->where("file_id=?",[$fileId])->select(false);
		if ($data && $data->total > 0) {
			return $data->data[0];
		}
		return null;
	}

	private function renameContentFileIfNeeded(array $fileRow, string $displayName): array
	{
		if (($fileRow['file_type'] ?? '') === 'vr') {
			$newDisplayName = $this->normalizeContentDisplayName($displayName);
			if ($newDisplayName === '') {
				$newDisplayName = (string)($fileRow['display_name'] ?: ($fileRow['original_name'] ?: $fileRow['code']));
			}
			return [
				'display_name' => $newDisplayName,
				'storage_path' => (string)($fileRow['storage_path'] ?? ''),
				'code' => (string)($fileRow['code'] ?? ''),
			];
		}

		$currentStorage = (string)($fileRow['storage_path'] ?? '');
		$currentReal = $this->resolveContentRealPath($currentStorage);
		if (!$currentReal || !is_file($currentReal)) {
			throw new RuntimeException('Server file not found.');
		}

		$newFileName = $this->buildContentStorageFileName($displayName, basename($currentStorage));
		$currentFileName = basename($currentStorage);
		$newStorage = str_replace('\\', '/', dirname($currentStorage)) . '/' . $newFileName;
		$newStorage = ltrim($newStorage, './');
		$newReal = dirname($currentReal) . DIRECTORY_SEPARATOR . $newFileName;

		if ($newFileName !== $currentFileName) {
			if (file_exists($newReal)) {
				throw new RuntimeException('File already exists on server: ' . $newFileName);
			}
			$fileType = (string)($fileRow['file_type'] ?? '');
			$codePath = $newStorage;
			if ($fileType !== '' && strpos($codePath, $fileType . '/') === 0) {
				$codePath = substr($codePath, strlen($fileType) + 1);
			}
			$newCode = $fileType . ':' . $codePath;
			$dupModel = new ContentFileModel();
			$dupData = $dupModel->where('code=? and file_id<>?', [$newCode, (int)$fileRow['file_id']])->select(false);
			if ($dupData && $dupData->total > 0) {
				throw new RuntimeException('File code already exists: ' . $newCode);
			}
			if (!rename($currentReal, $newReal)) {
				throw new RuntimeException('Failed to rename server file.');
			}
		}

		$fileType = (string)($fileRow['file_type'] ?? '');
		$codePath = $newStorage;
		if ($fileType !== '' && strpos($codePath, $fileType . '/') === 0) {
			$codePath = substr($codePath, strlen($fileType) + 1);
		}

		return [
			'display_name' => $newFileName,
			'storage_path' => $newStorage,
			'code' => $fileType . ':' . $codePath,
		];
	}

	private function deleteContentFilePhysical(array $fileRow): void
	{
		$realPath = $this->resolveContentRealPath((string)($fileRow['storage_path'] ?? ''));
		if ($realPath && is_file($realPath) && !unlink($realPath)) {
			throw new RuntimeException('Failed to delete server file.');
		}
	}

	private function resolveContentRealPath(string $storagePath)
	{
		$base = realpath(PCPath::systemRoot() . 'app/res/content/');
		if ($base === false) {
			return false;
		}
		$storagePath = ltrim(str_replace(['../', '..\\', '\\'], ['', '', '/'], $storagePath), '/');
		$path = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storagePath);
		$full = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
		if (strpos($full, $base . DIRECTORY_SEPARATOR) !== 0) {
			return false;
		}
		$real = realpath($path);
		if ($real === false) {
			return $path;
		}
		if (strpos($real, $base . DIRECTORY_SEPARATOR) !== 0) {
			return false;
		}
		return $real;
	}

	private function normalizeContentV2Date($value)
	{
		$value = trim((string)$value);
		if ($value === '') {
			return null;
		}
		$value = str_replace('T', ' ', $value);
		if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
			$value .= ':00';
		}
		if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
			throw new RuntimeException('Invalid datetime format.');
		}
		return $value;
	}

	private function sanitizeContentV2FileName(string $name): string
	{
		$name = basename($name);
		$name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $name);
		$name = trim($name, '._-');
		if ($name === '') {
			return 'content.bin';
		}
		if (strlen($name) > 180) {
			$ext = pathinfo($name, PATHINFO_EXTENSION);
			$base = pathinfo($name, PATHINFO_FILENAME);
			$extPart = $ext !== '' ? '.' . substr($ext, 0, 16) : '';
			$name = substr($base, 0, 180 - strlen($extPart)) . $extPart;
		}
		return $name;
	}

	private function generateContentV2Key(int $length = 32): string
	{
		$alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
		$key = '';
		for ($i = 0; $i < $length; $i++) {
			$key .= $alphabet[random_int(0, strlen($alphabet) - 1)];
		}
		return $key;
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}

	protected function configLoadErrorAction()
	{
		header('Content-Type: text/plain; charset=UTF-8');
		echo "file.json の読み込みに失敗しました。JSON構文を確認してください。";
	}

	public function loadFileConfig(){
		
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

		return $config;
	}

	/**
	 * JSONを再帰的に探索してファイル情報を列挙
	 */
	function listFilesRecursive(array $node, string $basePath = '', bool $isRoot = true): array {
		$files = [];

		foreach ($node as $name => $value) {

			if ($isRoot) {
				// 最上位（例: movie）
				$currentPath = $name;
			} else {
				// basePath の末尾が ":" の場合は "/" を付けずに結合
				if (substr($basePath, -1) === ':') {
					$currentPath = $basePath . $name;
				} else {
					$currentPath = $basePath . '/' . $name;
				}
			}

			if (is_array($value)) {
				// ファイル情報かフォルダか判定
				$isFile = isset($value['plan']) || isset($value['key']) || isset($value['suffix']);

				if ($isFile) {
					// path にファイル名を含めて格納
					$files[] = [
						'path' => $currentPath, // ファイル名まで含む完全パス
						'name' => $name,
						'config' => $value
					];
				} else {
					// フォルダの場合は再帰処理
					$newBase = $isRoot ? $name . ':' : $currentPath;
					$files = array_merge($files, $this->listFilesRecursive($value, $newBase, false));
				}
			}
		}

		return $files;
	}

}
