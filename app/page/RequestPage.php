<?php

class RequestPage extends PTUserPage
{
	// 既存FWのCSRFヘルパがあれば差し替え可。なければ簡易実装。
	private function csrfToken(): string {
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
		return $_SESSION['csrf'];
	}
	private function checkCsrf(string $t): bool {
		if (session_status() !== PHP_SESSION_ACTIVE) session_start();
		return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $t);
	}
	
	/** ユーザー向けフォーム */
	public function formAction()
	{
	    if (!$this->member) { $this->redirect('/login'); return; }

	    $this->view->csrf = $this->csrfToken();
	    $this->view->patreon = $this->member['patreon'] ?? [];
	    $this->setTemplatePath('request/form.phtml');
	    $this->display();
	}

	/** フォーム送信 */
	public function submitAction()
	{
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/request'); return; }
		if (!$this->member) { $this->redirect('/login'); return; }

		if (!$this->checkCsrf($_POST['_csrf'] ?? '')) {
			http_response_code(400); echo "Invalid CSRF token"; return;
		}

		$reqText = trim((string)($_POST['request_text'] ?? ''));
		$isNsfw  = isset($_POST['is_nsfw']) ? 1 : 0;
		$wantVid = isset($_POST['want_video']) ? 1 : 0;

		if ($reqText === '') {
			$this->view->error = 'リクエスト内容を入力してください。';
			return $this->formAction();
		}

	    // Patreon関連は既存の member 構造を利用
		$patreon     = $this->member['patreon'] ?? [];
		$patreonId   = (string)($patreon['id'] ?? '');
		$patronName  = (string)($patreon['name'] ?? ($this->member['name'] ?? ''));
		$tierId      = (string)($this->member['current_tier_id'] ?? ($patreon['current_tier_id'] ?? ''));
		$statusRaw   = $this->member['status'] ?? ($patreon['status'] ?? null);
		$patronStat  = ($statusRaw === 'active_patron') ? 'paid' : 'free';

		if ($patreonId === '') {
			$this->view->error = 'Patreon連携が必要です。';
			return $this->formAction();
		}

		$sql = "INSERT INTO request_ideas
	            (member_id, patreon_id, patron_name, patron_status, tier_id, request_text, is_nsfw, want_video)
	            VALUES (:member_id, :patreon_id, :patron_name, :patron_status, :tier_id, :request_text, :is_nsfw, :want_video)";
		$stmt = $this->db()->prepare($sql);
		$stmt->execute([
			':member_id'     => $this->member['member_id'] ?? null,
			':patreon_id'    => $patreonId,
			':patron_name'   => $patronName,
			':patron_status' => $patronStat,
			':tier_id'       => $tierId ?: null,
			':request_text'  => $reqText,
			':is_nsfw'       => $isNsfw,
			':want_video'    => $wantVid,
		]);

		$this->redirect('/request?ok=1');
	}

	/** 管理一覧 */
	public function adminListAction()
	{
		if (!$this->util->isAdmin($this->member)) { http_response_code(403); echo 'Forbidden'; return; }

		$q = "SELECT * FROM request_ideas ORDER BY created_at DESC";
		$rows = $this->db()->query($q)->fetchAll(PDO::FETCH_ASSOC);

		$this->view->rows = $rows;
		$this->view->csrf = $this->csrfToken();
		$this->setTemplatePath('request/admin_list.phtml');
		$this->display();
	}

	/** 管理更新 */
	public function adminUpdateAction()
	{
		if (!$this->util->isAdmin($this->member)) { http_response_code(403); echo 'Forbidden'; return; }
		if ($_SERVER['REQUEST_METHOD'] !== 'POST') { $this->redirect('/admin/requests'); return; }
		if (!$this->checkCsrf($_POST['_csrf'] ?? '')) { http_response_code(400); echo 'Invalid CSRF token'; return; }

		$id     = (int)($_POST['id'] ?? 0);
		$status = (string)($_POST['status'] ?? 'unhandled');
		$memo   = trim((string)($_POST['memo'] ?? ''));

		$allow = ['unhandled','adopted','done'];
		if (!in_array($status, $allow, true)) $status = 'unhandled';

		$sql = "UPDATE request_ideas SET status=:status, memo=:memo WHERE id=:id";
		$stmt = $this->db()->prepare($sql);
		$stmt->execute([':status' => $status, ':memo' => $memo, ':id' => $id]);

		$this->redirect('/admin/requests');
	}
	
	
	
	
	
	
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
				
				$videoUrl = "https://".$host."/data/player?f=".$filePath."&k=".$key;
				$file["video"] = $videoUrl;
				
				$downloadUrl = "https://".$host."/data/file?f=".$filePath."&k=".$key."&m=download";
				$file["download"] = $downloadUrl;
				
				$smUrl = "https://".$host."/data/sm?f=".$filePath."&k=".$key;
				$file["sm"] = $smUrl;
				
				$gifUrl = "https://".$host."/data/gif?f=".$filePath."&k=".$key."&m=download";
				$file["gif"] = $gifUrl;
				
				
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
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
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
