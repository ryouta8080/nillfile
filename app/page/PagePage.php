<?php

class PagePage extends PTUserPage
{
	public function indexAction()
	{
		$this->notfoundAction();
	}
	
	public function policyAction()
	{
		$this->view->title = "プライバシーポリシー";
		$this->display();
	}
	
	public function termsAction()
	{
		$this->view->title = "利用規約";
		$this->display();
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}

}
