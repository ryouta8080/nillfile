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

	public function requestAction()
	{
		if(!$this->member){
			$this->redirect('/login');
			return;
		}
		if(!$this->util->isAdmin($this->member)){
			$this->displayNotFound();
			return;
		}
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string)($_POST['operation'] ?? '') === 'update_request_flag') {
			$this->updateRequestAdminFlagResponse();
			return;
		}

		$errors = [];
		if ($_SERVER['REQUEST_METHOD'] === 'POST') {
			if (!$this->checkRequestAdminCsrf((string)($_POST['_csrf'] ?? ''))) {
				$errors[] = '画面の有効期限が切れました。再読み込みしてから更新してください。';
			} else {
				$operation = (string)($_POST['operation'] ?? '');
				if ($operation === 'update_request') {
					if ($this->updateRequestAdmin()) {
						$this->redirect('/account/request?updated=1');
						return;
					}
					$errors[] = 'リクエストの更新に失敗しました。';
				} elseif ($operation === 'save_setting') {
					if ($this->saveRequestSettingAdmin()) {
						$this->redirect('/account/request?setting_saved=1');
						return;
					}
					$errors[] = '受付設定の保存に失敗しました。';
				} elseif ($operation === 'delete_request_attachment') {
					if ($this->deleteRequestAttachmentAdmin()) {
						$this->redirect('/account/request?attachment_deleted=1');
						return;
					}
					$errors[] = '添付画像の削除に失敗しました。';
				} else {
					$errors[] = '不明な操作です。';
				}
			}
		}

		$state = (string)($_GET['state'] ?? 'open');
		if (!in_array($state, ['open', 'done', 'withdrawn', 'hidden', 'all'], true)) $state = 'open';
		$favorite = isset($_GET['favorite']) && (string)$_GET['favorite'] === '1';
		$keyword = trim((string)($_GET['q'] ?? ''));
		$page = max(1, (int)($_GET['page'] ?? 1));
		$perPage = 50;

		$model = new RequestIdeaModel();
		if ($state === 'open') {
			$model->where('done_flag=0 and withdrawn_flag=0 and hidden_flag=0', []);
		} elseif ($state === 'done') {
			$model->where('done_flag=1 and hidden_flag=0', []);
		} elseif ($state === 'withdrawn') {
			$model->where('withdrawn_flag=1 and hidden_flag=0', []);
		} elseif ($state === 'hidden') {
			$model->where('hidden_flag=1', []);
		}
		if ($favorite) $model->where('favorite_flag=1', []);
		if ($keyword !== '') {
			$like = '%' . $keyword . '%';
			$model->where('(request_text like ? or patron_name like ? or patreon_id like ?)', [$like, $like, $like]);
		}
		$total = (int)($model->count() ?: 0);
		$totalPages = max(1, (int)ceil($total / $perPage));
		if ($page > $totalPages) $page = $totalPages;
		$model
			->orderBy('request_idea.reg_datetime desc')
			->limit(($page - 1) * $perPage, $perPage);
		$result = $model->select();
		$rows = ($result && $result->total > 0) ? $result->data : [];
		$rows = $this->loadRequestAdminMetadata($rows);

		$newRequestIds = [];
		foreach ($rows as $row) {
			if (empty($row['admin_viewed_datetime'])) {
				$newRequestIds[] = (int)$row['request_id'];
			}
		}
		if ($newRequestIds) {
			$placeholders = implode(',', array_fill(0, count($newRequestIds), '?'));
			$viewedAt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
			$viewedModel = new RequestIdeaModel();
			$viewedModel->update(
				['admin_viewed_datetime' => $viewedAt->format('Y-m-d H:i:s')],
				'admin_viewed_datetime is null and request_id in (' . $placeholders . ')',
				$newRequestIds,
				null,
				true
			);
		}

		$contentModel = new ContentItemModel();
		$contentResult = $contentModel->orderBy('content_item.reg_datetime desc')->select();

		$infos = [];
		if (isset($_GET['updated'])) $infos[] = 'リクエストを更新しました。';
		if (isset($_GET['setting_saved'])) $infos[] = '受付設定を保存しました。';
		if (isset($_GET['attachment_deleted'])) $infos[] = '添付画像を削除しました。';

		$this->view->rows = $rows;
		$this->view->contents = ($contentResult && $contentResult->total > 0) ? $contentResult->data : [];
		$this->view->setting = $this->loadRequestSettingAdmin();
		$this->view->requestTypes = $this->loadRequestTypesAdmin();
		$this->view->filters = ['state' => $state, 'favorite' => $favorite, 'q' => $keyword];
		$this->view->pagination = [
			'page' => $page,
			'per_page' => $perPage,
			'total' => $total,
			'total_pages' => $totalPages,
		];
		$this->view->csrf = $this->requestAdminCsrfToken();
		$this->view->errors = $errors;
		$this->view->infos = $infos;
		$this->view->title = 'リクエスト管理';
		$this->setTemplatePath('account/request.phtml');
		$this->display();
	}

	private function loadRequestAdminMetadata(array $rows): array
	{
		if (!$rows) return [];

		$requestIds = [];
		$memberIds = [];
		foreach ($rows as $row) {
			$requestIds[] = (int)$row['request_id'];
			$memberIds[] = (int)$row['member_id'];
		}
		$requestIds = array_values(array_unique(array_filter($requestIds)));
		$memberIds = array_values(array_unique(array_filter($memberIds)));

		$attachmentsByRequest = [];
		if ($requestIds) {
			$attachmentModel = new RequestAttachmentModel();
			$attachmentResult = $attachmentModel
				->whereIn('request_id', $requestIds)
				->orderBy('request_attachment.request_id, request_attachment.sort_order, request_attachment.attachment_id')
				->select();
			if ($attachmentResult && $attachmentResult->total > 0) {
				foreach ($attachmentResult->data as $attachment) {
					$attachmentRequestId = (int)$attachment['request_id'];
					if (!isset($attachmentsByRequest[$attachmentRequestId])) {
						$attachmentsByRequest[$attachmentRequestId] = [];
					}
					$attachment['legacy_flag'] = 0;
					$attachmentsByRequest[$attachmentRequestId][] = $attachment;
				}
			}
		}

		$lastRequestByMember = [];
		if ($memberIds) {
			$lastRequestModel = new RequestIdeaModel();
			$lastRequestResult = $lastRequestModel
				->setCol(['member_id'])
				->addCol('max(request_idea.reg_datetime)', 'last_request_datetime')
				->whereIn('member_id', $memberIds)
				->groupBy(['member_id'])
				->select();
			if ($lastRequestResult && $lastRequestResult->total > 0) {
				foreach ($lastRequestResult->data as $lastRequestRow) {
					$lastRequestByMember[(int)$lastRequestRow['member_id']] = (string)$lastRequestRow['last_request_datetime'];
				}
			}
		}

		$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Tokyo'));
		$currentMonthCountByMember = [];
		if ($memberIds) {
			$currentMonthModel = new RequestIdeaModel();
			$currentMonthResult = $currentMonthModel
				->setCol(['member_id'])
				->addCol('count(*)', 'current_month_request_count')
				->whereIn('member_id', $memberIds)
				->where('reg_datetime>=?', [$now->format('Y-m-01 00:00:00')])
				->groupBy(['member_id'])
				->select();
			if ($currentMonthResult && $currentMonthResult->total > 0) {
				foreach ($currentMonthResult->data as $currentMonthRow) {
					$currentMonthCountByMember[(int)$currentMonthRow['member_id']] = (int)$currentMonthRow['current_month_request_count'];
				}
			}
		}

		foreach ($rows as &$row) {
			$requestId = (int)$row['request_id'];
			$row['attachments'] = $attachmentsByRequest[$requestId] ?? [];
			if (!$row['attachments'] && in_array((string)($row['attachment_status'] ?? 'none'), ['stored', 'deleted'], true)) {
				$row['attachments'][] = [
					'attachment_id' => 0,
					'request_id' => $requestId,
					'sort_order' => 1,
					'attachment_status' => (string)$row['attachment_status'],
					'storage_path' => $row['attachment_path'] ?? null,
					'mime_type' => $row['attachment_mime'] ?? null,
					'file_size' => $row['attachment_size'] ?? null,
					'deleted_datetime' => $row['attachment_deleted_datetime'] ?? null,
					'legacy_flag' => 1,
				];
			}

			$lastRequestDatetime = $lastRequestByMember[(int)$row['member_id']] ?? (string)($row['reg_datetime'] ?? '');
			$row['last_request_datetime'] = $lastRequestDatetime;
			$row['last_request_elapsed_days'] = 0;
			$row['current_month_request_count'] = $currentMonthCountByMember[(int)$row['member_id']] ?? 0;
			if ($lastRequestDatetime !== '') {
				try {
					$lastRequestAt = new DateTimeImmutable($lastRequestDatetime, new DateTimeZone('Asia/Tokyo'));
					if ($lastRequestAt < $now) {
						$row['last_request_elapsed_days'] = (int)$lastRequestAt->diff($now)->format('%a');
					}
				} catch (Exception $e) {
					$row['last_request_elapsed_days'] = 0;
				}
			}
		}
		unset($row);

		return $rows;
	}

	private function loadRequestAttachment(array $requestRow, int $attachmentId): ?array
	{
		$requestId = (int)($requestRow['request_id'] ?? 0);
		if ($requestId <= 0) return null;

		if ($attachmentId > 0) {
			$model = new RequestAttachmentModel();
			$result = $model
				->where('attachment_id=? and request_id=?', [$attachmentId, $requestId])
				->select();
			return ($result && $result->total > 0) ? $result->data[0] : null;
		}

		if (
			(string)($requestRow['attachment_status'] ?? 'none') === 'stored'
			&& (string)($requestRow['attachment_path'] ?? '') !== ''
		) {
			return [
				'attachment_id' => 0,
				'request_id' => $requestId,
				'sort_order' => 1,
				'attachment_status' => 'stored',
				'storage_path' => (string)$requestRow['attachment_path'],
				'mime_type' => $requestRow['attachment_mime'] ?? null,
				'file_size' => $requestRow['attachment_size'] ?? null,
				'legacy_flag' => 1,
			];
		}

		$model = new RequestAttachmentModel();
		$result = $model
			->where('request_id=? and attachment_status=?', [$requestId, 'stored'])
			->orderBy('request_attachment.sort_order, request_attachment.attachment_id')
			->limit(1)
			->select();
		return ($result && $result->total > 0) ? $result->data[0] : null;
	}

	public function requestattachmentAction()
	{
		if (!$this->member || !$this->util->isAdmin($this->member)) {
			$this->displayNotFound();
			return;
		}

		$requestId = (int)($_GET['request_id'] ?? 0);
		$attachmentId = (int)($_GET['attachment_id'] ?? 0);
		if ($requestId <= 0) {
			$this->displayNotFound();
			return;
		}

		$model = new RequestIdeaModel();
		$result = $model->where('request_id=?', [$requestId])->select();
		if (!$result || $result->total === 0) {
			$this->displayNotFound();
			return;
		}
		$row = $result->data[0];

		$attachment = $this->loadRequestAttachment($row, $attachmentId);
		if ($attachment === null || (string)($attachment['attachment_status'] ?? 'none') !== 'stored') {
			$this->displayNotFound();
			return;
		}

		$filePath = $this->resolveRequestAttachmentPath((string)($attachment['storage_path'] ?? ''));
		if ($filePath === null || !is_file($filePath)) {
			$this->displayNotFound();
			return;
		}

		$mime = '';
		if (function_exists('finfo_open')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			$mime = $finfo ? (string)finfo_file($finfo, $filePath) : '';
			if ($finfo) finfo_close($finfo);
		}
		if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
			$this->displayNotFound();
			return;
		}

		$extension = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
		if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) $extension = 'img';
		$fileNumber = max(1, (int)($attachment['sort_order'] ?? 1));
		header('Content-Type: ' . $mime);
		header('Content-Length: ' . filesize($filePath));
		header('Content-Disposition: inline; filename="request-' . $requestId . '-' . $fileNumber . '.' . $extension . '"');
		header('Cache-Control: private, no-store, max-age=0');
		header('Pragma: no-cache');
		header('X-Content-Type-Options: nosniff');
		if (ob_get_level()) @ob_clean();
		readfile($filePath);
		return;
	}

	private function updateRequestAdmin(): bool
	{
		$requestId = (int)($_POST['request_id'] ?? 0);
		if ($requestId <= 0) return false;

		$contentId = (int)($_POST['content_id'] ?? 0);
		if ($contentId > 0) {
			$contentModel = new ContentItemModel();
			$contentResult = $contentModel->where('content_id=?', [$contentId])->select();
			if (!$contentResult || $contentResult->total === 0) $contentId = 0;
		}

		$replyText = mb_substr(trim((string)($_POST['reply_text'] ?? '')), 0, 16000, 'UTF-8');
		$model = new RequestIdeaModel();
		return (bool)$model->update([
			'request_id' => $requestId,
			'favorite_flag' => isset($_POST['favorite_flag']) ? 1 : 0,
			'done_flag' => isset($_POST['done_flag']) ? 1 : 0,
			'hidden_flag' => isset($_POST['hidden_flag']) ? 1 : 0,
			'admin_memo' => mb_substr(trim((string)($_POST['admin_memo'] ?? '')), 0, 16000, 'UTF-8'),
			'content_id' => $contentId > 0 ? $contentId : null,
			'reply_text' => $replyText,
			'reply_visible_flag' => $replyText !== '' && isset($_POST['reply_visible_flag']) ? 1 : 0,
		]);
	}

	private function deleteRequestAttachmentAdmin(): bool
	{
		$requestId = (int)($_POST['request_id'] ?? 0);
		$attachmentId = (int)($_POST['attachment_id'] ?? 0);
		if ($requestId <= 0) return false;

		$model = new RequestIdeaModel();
		$result = $model->where('request_id=?', [$requestId])->select();
		if (!$result || $result->total === 0) return false;
		$row = $result->data[0];

		if ($attachmentId > 0) {
			$attachmentModel = new RequestAttachmentModel();
			$attachmentResult = $attachmentModel
				->where('attachment_id=? and request_id=?', [$attachmentId, $requestId])
				->select();
			if (!$attachmentResult || $attachmentResult->total === 0) return false;
			$attachment = $attachmentResult->data[0];
			if ((string)($attachment['attachment_status'] ?? 'none') !== 'stored') return false;

			$relativePath = (string)($attachment['storage_path'] ?? '');
			if (!$this->isSafeRequestAttachmentPath($relativePath)) return false;
			$filePath = $this->resolveRequestAttachmentPath($relativePath);
			if ($filePath !== null && is_file($filePath) && !@unlink($filePath)) return false;

			$remainingModel = new RequestAttachmentModel();
			$remainingStored = (int)($remainingModel
				->where('request_id=? and attachment_status=? and attachment_id<>?', [$requestId, 'stored', $attachmentId])
				->count() ?: 0);
			$legacyPath = (string)($row['attachment_path'] ?? '');
			$legacyRemains = (string)($row['attachment_status'] ?? 'none') === 'stored'
				&& $legacyPath !== ''
				&& $legacyPath !== $relativePath;
			$parentStatus = ($remainingStored > 0 || $legacyRemains) ? 'stored' : 'deleted';
			$deletedAt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
			$transactionModel = new RequestAttachmentModel();
			$saved = $transactionModel->edit(function() use (
				$attachmentId,
				$requestId,
				$relativePath,
				$legacyPath,
				$parentStatus,
				$deletedAt
			) {
				$childModel = new RequestAttachmentModel();
				if (!$childModel->update([
					'attachment_id' => $attachmentId,
					'attachment_status' => 'deleted',
					'storage_path' => null,
					'mime_type' => null,
					'file_size' => null,
					'deleted_datetime' => $deletedAt->format('Y-m-d H:i:s'),
				])) {
					return false;
				}

				$parentData = [
					'request_id' => $requestId,
					'attachment_status' => $parentStatus,
				];
				if ($legacyPath === $relativePath) {
					$parentData['attachment_path'] = null;
					$parentData['attachment_mime'] = null;
					$parentData['attachment_size'] = null;
				}
				if ($parentStatus === 'deleted') {
					$parentData['attachment_deleted_datetime'] = $deletedAt->format('Y-m-d H:i:s');
				}
				$parentModel = new RequestIdeaModel();
				return (bool)$parentModel->update($parentData);
			});
			if (!$saved) return false;

			if ($filePath !== null) @rmdir(dirname($filePath));
			return true;
		}

		if ((string)($row['attachment_status'] ?? 'none') !== 'stored') return false;

		$relativePath = (string)($row['attachment_path'] ?? '');
		if (!$this->isSafeRequestAttachmentPath($relativePath)) return false;
		$filePath = $this->resolveRequestAttachmentPath($relativePath);
		if ($filePath !== null && is_file($filePath) && !@unlink($filePath)) return false;

		$deletedAt = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
		$updateModel = new RequestIdeaModel();
		$saved = $updateModel->update([
			'request_id' => $requestId,
			'attachment_status' => 'deleted',
			'attachment_path' => null,
			'attachment_mime' => null,
			'attachment_size' => null,
			'attachment_deleted_datetime' => $deletedAt->format('Y-m-d H:i:s'),
		]);
		if (!$saved) return false;

		if ($filePath !== null) @rmdir(dirname($filePath));
		return true;
	}

	private function isSafeRequestAttachmentPath(string $relativePath): bool
	{
		if ($relativePath === '' || strpos($relativePath, '\\') !== false) return false;
		if (strpos($relativePath, 'request_attachment/') !== 0) return false;
		if (strpos($relativePath, "\0") !== false) return false;
		return !preg_match('~(^|/)\.{1,2}(/|$)~', $relativePath);
	}

	private function resolveRequestAttachmentPath(string $relativePath): ?string
	{
		if (!$this->isSafeRequestAttachmentPath($relativePath)) return null;
		$root = realpath(PCPath::systemRoot() . 'app/res/content');
		$filePath = realpath(PCPath::systemRoot() . 'app/res/content/' . $relativePath);
		if ($root === false || $filePath === false) return null;

		$normalizedRoot = rtrim(str_replace('\\', '/', $root), '/') . '/';
		$normalizedPath = str_replace('\\', '/', $filePath);
		if (strpos($normalizedPath, $normalizedRoot) !== 0) return null;
		return $filePath;
	}

	private function updateRequestAdminFlagResponse(): void
	{
		if (!$this->checkRequestAdminCsrf((string)($_POST['_csrf'] ?? ''))) {
			$this->requestAdminJson(['success' => false, 'message' => '画面の有効期限が切れました。'], 400);
			return;
		}

		$requestId = (int)($_POST['request_id'] ?? 0);
		$field = (string)($_POST['field'] ?? '');
		$value = ((string)($_POST['value'] ?? '0') === '1') ? 1 : 0;
		if ($requestId <= 0 || !in_array($field, ['favorite_flag', 'done_flag', 'hidden_flag'], true)) {
			$this->requestAdminJson(['success' => false, 'message' => '更新内容が正しくありません。'], 400);
			return;
		}

		$checkModel = new RequestIdeaModel();
		$checkResult = $checkModel->where('request_id=?', [$requestId])->select();
		if (!$checkResult || $checkResult->total === 0) {
			$this->requestAdminJson(['success' => false, 'message' => 'リクエストが見つかりません。'], 404);
			return;
		}

		$model = new RequestIdeaModel();
		$saved = $model->update([
			'request_id' => $requestId,
			$field => $value,
		]);
		if (!$saved) {
			$this->requestAdminJson(['success' => false, 'message' => '更新に失敗しました。'], 500);
			return;
		}

		$this->requestAdminJson([
			'success' => true,
			'request_id' => $requestId,
			'field' => $field,
			'value' => $value,
		]);
	}

	private function requestAdminJson(array $data, int $status = 200): void
	{
		http_response_code($status);
		header('Content-Type: application/json; charset=UTF-8');
		echo json_encode($data, JSON_UNESCAPED_UNICODE);
	}

	private function saveRequestSettingAdmin(): bool
	{
		$maxLength = max(1, min(16000, (int)($_POST['max_length'] ?? 2000)));
		$monthlyLimit = max(0, min(1000, (int)($_POST['monthly_limit'] ?? 0)));
		$cooldownMinutes = max(0, min(525600, (int)($_POST['cooldown_minutes'] ?? 0)));
		$attachmentMaxSizeMb = max(1, min(50, (int)($_POST['attachment_max_size_mb'] ?? 10)));
		$requestTypes = $this->loadRequestTypesAdmin();
		$enabledCodes = $_POST['request_type_enabled'] ?? [];
		if (!is_array($enabledCodes)) $enabledCodes = [];
		$enabledCodes = array_values(array_unique(array_map('strval', $enabledCodes)));
		$knownCodes = array_column($requestTypes, 'type_code');
		$enabledCodes = array_values(array_intersect($enabledCodes, $knownCodes));
		if (!$requestTypes || !$enabledCodes) return false;

		$postedTypeMonthlyLimits = $_POST['request_type_monthly_limit'] ?? [];
		$postedTypeCooldownMinutes = $_POST['request_type_cooldown_minutes'] ?? [];
		if (!is_array($postedTypeMonthlyLimits)) $postedTypeMonthlyLimits = [];
		if (!is_array($postedTypeCooldownMinutes)) $postedTypeCooldownMinutes = [];
		$typeLimits = [];
		foreach ($requestTypes as $type) {
			$typeCode = (string)$type['type_code'];
			$typeLimits[$typeCode] = [
				'monthly_limit' => $this->normalizeRequestTypeLimitAdmin($postedTypeMonthlyLimits, $typeCode, 1000),
				'cooldown_minutes' => $this->normalizeRequestTypeLimitAdmin($postedTypeCooldownMinutes, $typeCode, 525600),
			];
		}

		$settingModel = new RequestSettingModel();
		return (bool)$settingModel->edit(function() use ($settingModel, $maxLength, $monthlyLimit, $cooldownMinutes, $attachmentMaxSizeMb, $requestTypes, $enabledCodes, $typeLimits) {
			$saved = $settingModel->save([
				'setting_id' => 1,
				'accept_flag' => isset($_POST['accept_flag']) ? 1 : 0,
				'description_text' => mb_substr(trim((string)($_POST['description_text'] ?? '')), 0, 16000, 'UTF-8'),
				'thanks_text' => mb_substr(trim((string)($_POST['thanks_text'] ?? '')), 0, 16000, 'UTF-8'),
				'max_length' => $maxLength,
				'monthly_limit' => $monthlyLimit,
				'cooldown_minutes' => $cooldownMinutes,
				'paid_only_flag' => isset($_POST['paid_only_flag']) ? 1 : 0,
				'admin_bypass_flag' => isset($_POST['admin_bypass_flag']) ? 1 : 0,
				'attachment_enabled_flag' => isset($_POST['attachment_enabled_flag']) ? 1 : 0,
				'attachment_max_size_mb' => $attachmentMaxSizeMb,
			]);
			if (!$saved) return false;

			foreach ($requestTypes as $type) {
				$typeCode = (string)$type['type_code'];
				$typeModel = new RequestTypeSettingModel();
				$typeSaved = $typeModel->save([
					'type_code' => $typeCode,
					'type_label' => (string)$type['type_label'],
					'enabled_flag' => in_array($typeCode, $enabledCodes, true) ? 1 : 0,
					'monthly_limit' => $typeLimits[$typeCode]['monthly_limit'],
					'cooldown_minutes' => $typeLimits[$typeCode]['cooldown_minutes'],
					'sort_order' => (int)$type['sort_order'],
				]);
				if (!$typeSaved) return false;
			}
			return true;
		});
	}

	private function normalizeRequestTypeLimitAdmin(array $values, string $typeCode, int $maximum): ?int
	{
		if (!array_key_exists($typeCode, $values)) return null;
		$value = trim((string)$values[$typeCode]);
		if ($value === '') return null;
		return max(0, min($maximum, (int)$value));
	}

	private function loadRequestSettingAdmin(): array
	{
		$setting = [
			'setting_id' => 1,
			'accept_flag' => 1,
			'description_text' => '',
			'thanks_text' => 'リクエストを受け付けました。',
			'max_length' => 2000,
			'monthly_limit' => 0,
			'cooldown_minutes' => 0,
			'paid_only_flag' => 0,
			'admin_bypass_flag' => 1,
			'attachment_enabled_flag' => 1,
			'attachment_max_size_mb' => 10,
		];
		$model = new RequestSettingModel();
		$result = $model->where('setting_id=?', [1])->select();
		if ($result && $result->total > 0) $setting = array_merge($setting, $result->data[0]);
		return $setting;
	}

	private function loadRequestTypesAdmin(): array
	{
		$model = new RequestTypeSettingModel();
		$result = $model->orderBy('request_type_setting.sort_order, request_type_setting.type_code')->select();
		return ($result && $result->total > 0) ? $result->data : [];
	}

	private function requestAdminCsrfToken(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		if (empty($_SESSION['request_admin_csrf'])) $_SESSION['request_admin_csrf'] = bin2hex(random_bytes(16));
		return $_SESSION['request_admin_csrf'];
	}

	private function checkRequestAdminCsrf(string $token): bool
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		return isset($_SESSION['request_admin_csrf']) && hash_equals($_SESSION['request_admin_csrf'], $token);
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
