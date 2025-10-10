<?php

class MemberModel extends PTBaseModel
{
	protected $table = array(
		'name'=> 'member',//テーブル名
		'col'=>array( //アクセス名 => オリジナル名（テーブルカラム名）
			'member_id' => 'member_id',
			'uid' => 'uid',
			'nickname' => 'nickname',
			'debug_level' => 'debug_level',
			'debug_option' => 'debug_option',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary'=>array( 'member_id' ), //オリジナル名を指定
		'del_flag'=>'del_flag', //オリジナル名を指定
		'del_flag_default'=>0,	//初期値（有効なカラムを表す）
	);
	
	protected function getValidateParam(){
		return PCF::useParam()
			->set("nickname",null, $this->vReqText())
			;
	}
	
	public function __construct(){
		$table = $this->table;
		parent::__construct($table);
	}
	
}