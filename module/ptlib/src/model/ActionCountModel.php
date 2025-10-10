<?php

class ActionCountModel extends PTBaseModel
{
	protected $table = array(
		'name'=> 'action_count',//テーブル名
		'col'=>array( //アクセス名 => オリジナル名（テーブルカラム名）
			"action" => "action",
			"code" => "code",
			"name" => "name",
			"cnt" => "cnt",
			"upd_datetime" => "upd_datetime",
			"reg_datetime" => "reg_datetime",
		),
		'primary'=>array( 'action','code' ), //オリジナル名を指定
		'del_flag'=>false, //オリジナル名を指定
		'del_flag_default'=>0,	//初期値（有効なカラムを表す）
	);
	
	public function __construct(){
		$table = $this->table;
		parent::__construct($table);
	}
	
}