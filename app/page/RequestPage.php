<?php

class RequestPage extends PTUserPage
{
	public function indexAction()
	{
		if (!$this->checkRequestLogin()) {
			return;
		}
		$this->renderRequestPage();
	}

	public function formAction()
	{
		$this->indexAction();
	}

	public function submitAction()
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$this->redirect('/request');
			return;
		}
		if (!$this->checkRequestLogin()) {
			return;
		}

		$errors = [];
		if (!$this->checkRequestCsrf((string)($_POST['_csrf'] ?? ''))) {
			$errors[] = '画面の有効期限が切れました。再読み込みしてから送信してください。';
		}

		$setting = $this->loadRequestSetting();
		$requestTypes = $this->loadRequestTypes(true);
		$patreon = $this->member['patreon'] ?? [];
		$status = trim((string)($patreon['status'] ?? ''));
		$isPaid = $status === 'active_patron';
		$adminBypass = !empty($setting['admin_bypass_flag']) && $this->util->isAdmin($this->member);
		$requestText = trim((string)($_POST['request_text'] ?? ''));
		$maxLength = max(1, min(16000, (int)$setting['max_length']));

		if (empty($setting['accept_flag'])) {
			$errors[] = '現在、リクエストの受付を停止しています。';
		}
		if (!$adminBypass && !empty($setting['paid_only_flag']) && !$isPaid) {
			$errors[] = '現在のプランではリクエストを送信できません。';
		}
		if ($requestText === '') {
			$errors[] = 'リクエスト内容を入力してください。';
		} elseif (mb_strlen($requestText, 'UTF-8') > $maxLength) {
			$errors[] = 'リクエスト内容は' . $maxLength . '文字以内で入力してください。';
		}

		$memberId = (int)($this->member['member_id'] ?? 0);
		if ($memberId <= 0) {
			$errors[] = '会員情報を確認できませんでした。';
		}

		$patreonId = trim((string)($patreon['id'] ?? ''));
		if ($patreonId === '') {
			$errors[] = 'Patreon連携情報を確認できませんでした。';
		}

		$requestTypesByCode = [];
		foreach ($requestTypes as $type) {
			$requestTypesByCode[(string)$type['type_code']] = $type;
		}
		$allowedTypeCodes = array_keys($requestTypesByCode);
		$requestType = (string)($_POST['request_type'] ?? ($allowedTypeCodes[0] ?? ''));
		if (!$allowedTypeCodes) {
			$errors[] = '現在、受付可能なリクエスト種別がありません。';
		} elseif (!in_array($requestType, $allowedTypeCodes, true)) {
			$errors[] = '選択されたリクエスト種別は現在受け付けていません。';
		} elseif (
			!$adminBypass
			&& $memberId > 0
			&& !empty($setting['accept_flag'])
			&& (empty($setting['paid_only_flag']) || $isPaid)
		) {
			$restriction = $this->getRequestTypeRestriction($memberId, $requestTypesByCode[$requestType], $setting);
			foreach ($restriction['messages'] as $message) {
				$errors[] = $message;
			}
		}

		$attachments = [];
		try {
			$attachments = $this->validateRequestAttachmentUploads($setting);
		} catch (RuntimeException $e) {
			$errors[] = $e->getMessage();
		}

		if ($errors) {
			$this->renderRequestPage($errors, $_POST);
			return;
		}

		$model = new RequestIdeaModel();
		$requestId = 0;
		$movedPaths = [];
		$transactionError = '';
		$saved = $model->edit(function() use (
			$model,
			$memberId,
			$patreonId,
			$patreon,
			$status,
			$isPaid,
			$requestText,
			$requestType,
			$attachments,
			&$requestId,
			&$movedPaths,
			&$transactionError
		) {
			try {
				$requestId = (int)$model->save([
					'member_id' => $memberId,
					'patreon_id' => mb_substr($patreonId, 0, 255, 'UTF-8'),
					'patron_name' => mb_substr(trim((string)($patreon['name'] ?? '')), 0, 255, 'UTF-8'),
					'patron_status_at_request' => mb_substr($status, 0, 64, 'UTF-8'),
					'tier_id_at_request' => mb_substr(trim((string)($patreon['current_tier_id'] ?? '')), 0, 255, 'UTF-8'),
					'is_paid_at_request' => $isPaid ? 1 : 0,
					'request_text' => $requestText,
					'category' => '',
					'request_type' => $requestType,
					'is_nsfw' => isset($_POST['is_nsfw']) ? 1 : 0,
					'attachment_status' => 'none',
				]);
				if ($requestId <= 0) {
					throw new RuntimeException('リクエストの保存に失敗しました。');
				}

				foreach ($attachments as $index => $attachment) {
					$stored = $this->storeRequestAttachment($requestId, $attachment);
					$movedPaths[] = $stored['absolute_path'];
					$attachmentModel = new RequestAttachmentModel();
					$attachmentId = (int)$attachmentModel->save([
						'request_id' => $requestId,
						'sort_order' => $index + 1,
						'attachment_status' => 'stored',
						'storage_path' => $stored['relative_path'],
						'mime_type' => $stored['mime'],
						'file_size' => $stored['size'],
					]);
					if ($attachmentId <= 0) {
						throw new RuntimeException('添付画像情報の保存に失敗しました。');
					}
				}
				if ($attachments) {
					$summaryModel = new RequestIdeaModel();
					$attachmentSaved = $summaryModel->update([
						'request_id' => $requestId,
						'attachment_status' => 'stored',
						'attachment_path' => null,
						'attachment_mime' => null,
						'attachment_size' => null,
						'attachment_deleted_datetime' => null,
					]);
					if (!$attachmentSaved) {
						throw new RuntimeException('添付画像情報の保存に失敗しました。');
					}
				}
				return true;
			} catch (Exception $e) {
				$transactionError = $e->getMessage();
				return false;
			}
		});
		if (!$saved) {
			$attachmentDir = '';
			foreach ($movedPaths as $movedPath) {
				if ($movedPath !== '' && is_file($movedPath)) {
					@unlink($movedPath);
					$attachmentDir = dirname($movedPath);
				}
			}
			if ($attachmentDir !== '') @rmdir($attachmentDir);
			$message = $transactionError ?: 'リクエストの保存に失敗しました。時間をおいて再度お試しください。';
			$this->renderRequestPage([$message], $_POST);
			return;
		}

		$this->setRequestFlash('submitted');
		$this->redirect('/request');
	}

	public function withdrawAction()
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			$this->redirect('/request');
			return;
		}
		if (!$this->checkRequestLogin()) {
			return;
		}
		if (!$this->checkRequestCsrf((string)($_POST['_csrf'] ?? ''))) {
			http_response_code(400);
			echo 'Invalid CSRF token';
			return;
		}

		$requestId = (int)($_POST['request_id'] ?? 0);
		$memberId = (int)($this->member['member_id'] ?? 0);
		if ($requestId > 0 && $memberId > 0) {
			$model = new RequestIdeaModel();
			$model->update(
				['request_id' => $requestId, 'withdrawn_flag' => 1],
				'member_id=? and withdrawn_flag=0',
				[$memberId]
			);
		}
		$this->setRequestFlash('withdrawn');
		$this->redirect('/request');
	}

	private function checkRequestLogin(): bool
	{
		if ($this->member) return true;

		$forwardedProto = strtolower(trim(explode(',', (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));
		$isHttps = $forwardedProto === 'https'
			|| (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off');
		$scheme = $isHttps ? 'https' : 'http';
		$host = (string)($_SERVER['HTTP_HOST'] ?? '');
		$returnUrl = $host === '' ? '/request' : $scheme . '://' . $host . '/request';

		$this->redirect('/login/patreon', ['url' => $returnUrl]);
		return false;
	}

	private function renderRequestPage(array $errors = [], array $formValues = []): void
	{
		$setting = $this->loadRequestSetting();
		$allRequestTypes = $this->loadRequestTypes();
		$memberId = (int)($this->member['member_id'] ?? 0);
		$patreon = $this->member['patreon'] ?? [];
		$isPaid = (($patreon['status'] ?? '') === 'active_patron');
		$adminBypass = !empty($setting['admin_bypass_flag']) && $this->util->isAdmin($this->member);
		$checkTypeRestrictions = !$adminBypass
			&& $memberId > 0
			&& !empty($setting['accept_flag'])
			&& (empty($setting['paid_only_flag']) || $isPaid);
		$globalRestriction = $checkTypeRestrictions
			? $this->getGlobalRequestRestriction($memberId, $setting)
			: null;
		$requestTypes = [];
		$requestTypeLabels = [];
		foreach ($allRequestTypes as $type) {
			$requestTypeLabels[(string)$type['type_code']] = (string)$type['type_label'];
			if (empty($type['enabled_flag'])) continue;

			$type['available_flag'] = 1;
			$type['restriction_message'] = '';
			$type['restriction_label'] = '';
			$type['limit_reached'] = 0;
			$type['cooldown_remaining_seconds'] = 0;
			if ($checkTypeRestrictions) {
				$restriction = $this->getRequestTypeRestriction($memberId, $type, $setting, $globalRestriction);
				$type['available_flag'] = $restriction['available'] ? 1 : 0;
				$type['restriction_message'] = implode(' ', $restriction['messages']);
				$type['restriction_label'] = implode('・', $restriction['labels']);
				$type['limit_reached'] = $restriction['limit_reached'] ? 1 : 0;
				$type['cooldown_remaining_seconds'] = $restriction['cooldown_remaining_seconds'];
			}
			$requestTypes[] = $type;
		}
		$page = max(1, (int)($_GET['page'] ?? 1));
		$perPage = 20;
		$model = new RequestIdeaModel();
		$model->where('member_id=? and withdrawn_flag=0', [$memberId]);
		$total = (int)($model->count() ?: 0);
		$totalPages = max(1, (int)ceil($total / $perPage));
		if ($page > $totalPages) $page = $totalPages;
		$model
			->orderBy('request_idea.reg_datetime desc')
			->limit(($page - 1) * $perPage, $perPage);
		$result = $model->select();

		$flash = $this->consumeRequestFlash();
		$this->view->csrf = $this->requestCsrfToken();
		$this->view->setting = $setting;
		$this->view->requestTypes = $requestTypes;
		$this->view->requestTypeLabels = $requestTypeLabels;
		$this->view->rows = ($result && $result->total > 0) ? $result->data : [];
		$this->view->pagination = [
			'page' => $page,
			'per_page' => $perPage,
			'total' => $total,
			'total_pages' => $totalPages,
		];
		$this->view->isPaid = $isPaid;
		$this->view->adminBypass = $adminBypass;
		$this->view->errors = $errors;
		$this->view->formValues = $formValues;
		$this->view->requestFlash = $flash;
		$this->view->title = 'リクエスト';
		$this->setTemplatePath('request/index.phtml');
		$this->display();
	}

	private function loadRequestSetting(): array
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
		if ($result && $result->total > 0) {
			$setting = array_merge($setting, $result->data[0]);
		}
		return $setting;
	}

	private function loadRequestTypes(bool $enabledOnly = false): array
	{
		$model = new RequestTypeSettingModel();
		if ($enabledOnly) $model->where('enabled_flag=1', []);
		$result = $model->orderBy('request_type_setting.sort_order, request_type_setting.type_code')->select();
		return ($result && $result->total > 0) ? $result->data : [];
	}

	private function validateRequestAttachmentUploads(array $setting): array
	{
		$upload = $_FILES['attachments'] ?? ($_FILES['attachment'] ?? null);
		if ($upload === null) return [];
		if (!is_array($upload)) {
			throw new RuntimeException('添付画像を確認できませんでした。');
		}

		$files = [];
		$names = $upload['name'] ?? null;
		if (is_array($names)) {
			foreach (array_keys($names) as $key) {
				$file = [];
				foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
					if (!isset($upload[$field]) || !is_array($upload[$field]) || is_array($upload[$field][$key] ?? null)) {
						throw new RuntimeException('添付画像を確認できませんでした。');
					}
					$file[$field] = $upload[$field][$key];
				}
				if ((int)$file['error'] !== UPLOAD_ERR_NO_FILE) $files[] = $file;
			}
		} else {
			if (is_array($upload['tmp_name'] ?? null)) {
				throw new RuntimeException('添付画像を確認できませんでした。');
			}
			if ((int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) $files[] = $upload;
		}

		if (!$files) return [];
		if (count($files) > 4) {
			throw new RuntimeException('添付できる画像は4枚までです。');
		}
		if (empty($setting['attachment_enabled_flag'])) {
			throw new RuntimeException('現在、画像を添付できません。');
		}

		$attachments = [];
		foreach ($files as $file) {
			$attachments[] = $this->validateRequestAttachmentUpload($file, $setting);
		}
		return $attachments;
	}

	private function validateRequestAttachmentUpload(array $file, array $setting): array
	{
		$error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
		if ($error !== UPLOAD_ERR_OK) {
			if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
				throw new RuntimeException('添付画像の容量が上限を超えています。');
			}
			throw new RuntimeException('添付画像のアップロードに失敗しました。');
		}

		$tmp = (string)($file['tmp_name'] ?? '');
		if ($tmp === '' || !is_uploaded_file($tmp)) {
			throw new RuntimeException('添付画像を確認できませんでした。');
		}

		$maxSizeMb = max(1, min(50, (int)($setting['attachment_max_size_mb'] ?? 10)));
		$maxBytes = $maxSizeMb * 1024 * 1024;
		$size = @filesize($tmp);
		if ($size === false || $size <= 0) {
			throw new RuntimeException('添付画像が空です。');
		}
		if ($size > $maxBytes) {
			throw new RuntimeException('添付画像は' . $maxSizeMb . 'MB以内にしてください。');
		}

		if (!function_exists('finfo_open')) {
			throw new RuntimeException('サーバーで添付画像の形式を確認できません。');
		}
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$mime = $finfo ? (string)finfo_file($finfo, $tmp) : '';
		if ($finfo) finfo_close($finfo);

		$allowedTypes = [
			'image/jpeg' => 'jpg',
			'image/png' => 'png',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
		];
		if (!isset($allowedTypes[$mime])) {
			throw new RuntimeException('添付できる形式はJPEG、PNG、WebP、GIFです。');
		}

		$imageInfo = @getimagesize($tmp);
		if (!$imageInfo || (int)($imageInfo[0] ?? 0) <= 0 || (int)($imageInfo[1] ?? 0) <= 0) {
			throw new RuntimeException('添付ファイルは有効な画像ではありません。');
		}
		$width = (int)$imageInfo[0];
		$height = (int)$imageInfo[1];
		if ($width > 12000 || $height > 12000 || ($width * $height) > 40000000) {
			throw new RuntimeException('添付画像の縦横サイズが大きすぎます。');
		}

		return [
			'tmp_name' => $tmp,
			'mime' => $mime,
			'extension' => $allowedTypes[$mime],
			'size' => (int)$size,
		];
	}

	private function storeRequestAttachment(int $requestId, array $attachment): array
	{
		$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
		$month = $now->format('Ym');
		$relativeDir = 'request_attachment/' . $month . '/' . $requestId;
		$absoluteDir = PCPath::systemRoot() . 'app/res/content/' . $relativeDir;
		if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
			throw new RuntimeException('添付画像の保存先を作成できませんでした。');
		}
		$storageRoot = realpath(PCPath::systemRoot() . 'app/res/content');
		$resolvedDir = realpath($absoluteDir);
		$normalizedRoot = $storageRoot === false ? '' : rtrim(str_replace('\\', '/', $storageRoot), '/') . '/';
		$normalizedDir = $resolvedDir === false ? '' : rtrim(str_replace('\\', '/', $resolvedDir), '/') . '/';
		if ($normalizedRoot === '' || $normalizedDir === '' || strpos($normalizedDir, $normalizedRoot) !== 0) {
			throw new RuntimeException('添付画像の保存先を確認できませんでした。');
		}
		$absoluteDir = rtrim($resolvedDir, '/\\');

		$fileName = bin2hex(random_bytes(16)) . '.' . (string)$attachment['extension'];
		$absolutePath = $absoluteDir . '/' . $fileName;
		if (!move_uploaded_file((string)$attachment['tmp_name'], $absolutePath)) {
			@rmdir($absoluteDir);
			throw new RuntimeException('添付画像を保存できませんでした。');
		}

		return [
			'absolute_path' => $absolutePath,
			'relative_path' => $relativeDir . '/' . $fileName,
			'mime' => (string)$attachment['mime'],
			'size' => (int)$attachment['size'],
		];
	}

	private function getGlobalRequestRestriction(int $memberId, array $setting): array
	{
		$monthlyLimit = max(0, min(1000, (int)($setting['monthly_limit'] ?? 0)));
		$limitReached = false;
		if ($monthlyLimit > 0) {
			$limitReached = $this->countCurrentMonthRequests($memberId) >= $monthlyLimit;
		}

		$cooldownMinutes = max(0, min(525600, (int)($setting['cooldown_minutes'] ?? 0)));
		$cooldownRemainingSeconds = 0;
		if ($cooldownMinutes > 0) {
			$cooldownRemainingSeconds = $this->getRequestCooldownRemainingSeconds($memberId, $cooldownMinutes);
		}

		return [
			'limit_reached' => $limitReached,
			'cooldown_remaining_seconds' => $cooldownRemainingSeconds,
		];
	}

	private function getRequestTypeRestriction(int $memberId, array $type, array $setting, ?array $globalRestriction = null): array
	{
		$typeCode = (string)($type['type_code'] ?? '');
		if ($globalRestriction === null) {
			$globalRestriction = $this->getGlobalRequestRestriction($memberId, $setting);
		}

		$limitReached = !empty($globalRestriction['limit_reached']);
		$cooldownRemainingSeconds = max(0, (int)($globalRestriction['cooldown_remaining_seconds'] ?? 0));

		$typeMonthlyLimit = max(0, min(1000, (int)($type['monthly_limit'] ?? 0)));
		if ($typeMonthlyLimit > 0 && $this->countCurrentMonthRequests($memberId, $typeCode) >= $typeMonthlyLimit) {
			$limitReached = true;
		}

		$typeCooldownMinutes = max(0, min(525600, (int)($type['cooldown_minutes'] ?? 0)));
		if ($typeCooldownMinutes > 0) {
			$typeCooldownRemainingSeconds = $this->getRequestCooldownRemainingSeconds($memberId, $typeCooldownMinutes, $typeCode);
			$cooldownRemainingSeconds = max($cooldownRemainingSeconds, $typeCooldownRemainingSeconds);
		}

		$messages = [];
		$labels = [];
		if ($limitReached) {
			$messages[] = 'リクエストの上限に達しています。';
			$labels[] = '上限に達しています';
		} elseif ($cooldownRemainingSeconds > 0) {
			$remaining = $this->formatRequestCooldown($cooldownRemainingSeconds);
			$messages[] = '連続投稿制限中です。次の投稿まで' . $remaining . 'お待ちください。';
			$labels[] = 'あと' . $remaining;
		}

		return [
			'available' => !$limitReached && $cooldownRemainingSeconds <= 0,
			'messages' => $messages,
			'labels' => $labels,
			'limit_reached' => $limitReached,
			'cooldown_remaining_seconds' => $cooldownRemainingSeconds,
		];
	}

	private function countCurrentMonthRequests(int $memberId, ?string $requestType = null): int
	{
		$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
		$start = $now->format('Y-m-01 00:00:00');
		$model = new RequestIdeaModel();
		if ($requestType === null) {
			$model->where('member_id=? and reg_datetime>=?', [$memberId, $start]);
		} else {
			$model->where('member_id=? and request_type=? and reg_datetime>=?', [$memberId, $requestType, $start]);
		}
		$count = $model->count();
		return $count === false ? 0 : (int)$count;
	}

	private function getRequestCooldownRemainingSeconds(int $memberId, int $cooldownMinutes, ?string $requestType = null): int
	{
		$model = new RequestIdeaModel();
		if ($requestType === null) {
			$model->where('member_id=?', [$memberId]);
		} else {
			$model->where('member_id=? and request_type=?', [$memberId, $requestType]);
		}
		$result = $model
			->orderBy('request_idea.reg_datetime desc')
			->limit(1)
			->select();
		if (!$result || $result->total === 0) return 0;

		$lastValue = trim((string)($result->data[0]['reg_datetime'] ?? ''));
		if ($lastValue === '') return 0;
		try {
			$timezone = new DateTimeZone('Asia/Tokyo');
			$lastAt = new DateTime($lastValue, $timezone);
			$availableAt = $lastAt->getTimestamp() + ($cooldownMinutes * 60);
			return max(0, $availableAt - time());
		} catch (Exception $e) {
			return 0;
		}
	}

	private function formatRequestCooldown(int $remainingSeconds): string
	{
		$totalMinutes = max(1, (int)ceil($remainingSeconds / 60));
		$hours = intdiv($totalMinutes, 60);
		$minutes = $totalMinutes % 60;
		if ($hours <= 0) return $minutes . '分';
		if ($minutes === 0) return $hours . '時間';
		return $hours . '時間' . $minutes . '分';
	}

	private function requestCsrfToken(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		if (empty($_SESSION['request_csrf'])) $_SESSION['request_csrf'] = bin2hex(random_bytes(16));
		return $_SESSION['request_csrf'];
	}

	private function setRequestFlash(string $type): void
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		$_SESSION['request_flash'] = $type;
	}

	private function consumeRequestFlash(): string
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		$type = (string)($_SESSION['request_flash'] ?? '');
		unset($_SESSION['request_flash']);
		return $type;
	}

	private function checkRequestCsrf(string $token): bool
	{
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		return isset($_SESSION['request_csrf']) && hash_equals($_SESSION['request_csrf'], $token);
	}
}
