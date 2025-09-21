<?php

require_once("BasePageClass.php");

class IndexPage extends BasePageClass
{

	public function indexAction()
	{
		$this->notfoundAction();
	}
	
	public function notfoundAction()
	{ 
		$this->displayNotFound();
	}

}
