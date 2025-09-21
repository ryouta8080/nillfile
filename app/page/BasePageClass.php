<?php

class BasePageClass extends PCMVCBasicPageBase
{

	public function indexAction()
	{
		$this->displayNotFound();
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}
	
	protected function preAction(){
		if($this->isDebug()){
			if(!isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'])){
				header('WWW-Authenticate: Basic realm="Enter username and password."');
				header('Content-Type: text/plain; charset=utf-8');
				die('');
			}
			if(
				( $_SERVER['PHP_AUTH_USER'] === 'debug' && $_SERVER['PHP_AUTH_PW']   === 'password' )
			){
				//ok
			}else{
				header('WWW-Authenticate: Basic realm="Enter username and password."');
				header('Content-Type: text/plain; charset=utf-8');
				die('');
			}
		}
	}
	
	public function isDebug(){
		if( ! isset($_SERVER["HTTP_HOST"])){
			//batch
			return true;
		}
		if( $_SERVER["HTTP_HOST"] == "dev-file.nilwork.net"){
			return true;
		}
		return false;
	}
	
	public function loadScript($path)
	{
		$realPath = PCPath::systemRoot() . "app/res/js/".$path;
		$script = file_get_contents($realPath);
		$script = str_replace('PHP_ACTIVE_COMMENT->*/', '', $script);
		$script = str_replace('/*<-PHP_ACTIVE_COMMENT', '', $script);
		$script = str_replace('/*PHP_DELETE_COMMENT', '', $script);
		$script = str_replace('PHP_DELETE_COMMENT*/', '', $script);
		echo($script);
	}

}
