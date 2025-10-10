<?php

class PTBasePage extends PCMVCBasicPageBase
{
	public $util = null;
	
	protected function init(){
		$this->util = new PTUtil();
	}
	
	protected function preAction(){
		
		if( $this->util->isDebug() && ! in_array( $this->pageName,$this->gestPage) ){
			switch (true) {
				case !isset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']):
				case $_SERVER['PHP_AUTH_USER'] !== 'debug':
				case $_SERVER['PHP_AUTH_PW']   !== 'password':
					header('WWW-Authenticate: Basic realm="Enter username and password."');
					header('Content-Type: text/plain; charset=utf-8');
					die('');
				/*
				case !isset($_SERVER['HTTP_AUTHORIZATION']):
				case explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION']), 6), 2) !== array('debug', 'password'):
					header('WWW-Authenticate: Basic realm="Enter username and password."');
					header('Content-Type: text/plain; charset=utf-8');
					die('');
				*/
			}
		}
	}

}
