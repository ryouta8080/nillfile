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
		$monthlyLimit = max(0, (int)$setting['monthly_limit']);
		$cooldownMinutes = max(0, (int)$setting['cooldown_minutes']);
		if ($memberId <= 0) {
			$errors[] = '会員情報を確認できませんでした。';
		} elseif (!$adminBypass && $monthlyLimit > 0 && $this->countCurrentMonthRequests($memberId) >= $monthlyLimit) {
			$errors[] = '今月のリクエスト上限（' . $monthlyLimit . '件）に達しています。';
		}
		if (!$adminBypass && $memberId > 0 && $cooldownMinutes > 0) {
			$remainingSeconds = $this->getRequestCooldownRemainingSeconds($memberId, $cooldownMinutes);
			if ($remainingSeconds > 0) {
				$errors[] = '連続投稿制限中です。次の投稿まで' . $this->formatRequestCooldown($remainingSeconds) . 'お待ちください。';
			}
		}

		$patreonId = trim((string)($patreon['id'] ?? ''));
		if ($patreonId === '') {
			$errors[] = 'Patreon連携情報を確認できませんでした。';
		}

		$allowedTypeCodes = array_column($requestTypes, 'type_code');
		$requestType = (string)($_POST['request_type'] ?? ($allowedTypeCodes[0] ?? ''));
		if (!$allowedTypeCodes) {
			$errors[] = '現在、受付可能なリクエスト種別がありません。';
		} elseif (!in_array($requestType, $allowedTypeCodes, true)) {
			$errors[] = '選択されたリクエスト種別は現在受け付けていません。';
		}

		if ($errors) {
			$this->renderRequestPage($errors, $_POST);
			return;
		}

		$model = new RequestIdeaModel();
		$saved = $model->save([
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
		]);
		if (!$saved) {
			$this->renderRequestPage(['リクエストの保存に失敗しました。時間をおいて再度お試しください。'], $_POST);
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
		$requestTypes = [];
		$requestTypeLabels = [];
		foreach ($allRequestTypes as $type) {
			$requestTypeLabels[(string)$type['type_code']] = (string)$type['type_label'];
			if (!empty($type['enabled_flag'])) $requestTypes[] = $type;
		}
		$memberId = (int)($this->member['member_id'] ?? 0);
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

		$patreon = $this->member['patreon'] ?? [];
		$adminBypass = !empty($setting['admin_bypass_flag']) && $this->util->isAdmin($this->member);
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
		$this->view->isPaid = (($patreon['status'] ?? '') === 'active_patron');
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

	private function countCurrentMonthRequests(int $memberId): int
	{
		$now = new DateTime('now', new DateTimeZone('Asia/Tokyo'));
		$start = $now->format('Y-m-01 00:00:00');
		$model = new RequestIdeaModel();
		$count = $model->where('member_id=? and reg_datetime>=?', [$memberId, $start])->count();
		return $count === false ? 0 : (int)$count;
	}

	private function getRequestCooldownRemainingSeconds(int $memberId, int $cooldownMinutes): int
	{
		$model = new RequestIdeaModel();
		$result = $model
			->where('member_id=?', [$memberId])
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
