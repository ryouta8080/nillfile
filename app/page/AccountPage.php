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
				
				$fileList[$index] = $file;
			}
			$this->view->fileList = $fileList;
			
			$this->setTemplatePath("account/admin.phtml");
		}
		
		$this->view->title = "マイページ";
		$this->display();
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
