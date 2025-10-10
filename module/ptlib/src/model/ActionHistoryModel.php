<?php

class ActionHistoryModel extends PTBaseModel
{
	protected $table = array(
		'name'=> 'action_history',//テーブル名
		'col'=>array( //アクセス名 => オリジナル名（テーブルカラム名）
			"history_id" => "history_id",
			"action" => "action",
			"target_id" => "target_id",
			"deck_id" => "deck_id",
			"title_id" => "title_id",
			"code" => "code",
			"name" => "name",
			"keyword" => "keyword",
			"rule_type" => "rule_type",
			"policy" => "policy",
			"card_number" => "card_number",
			"card_name" => "card_name",
			"page" => "page",
			"page_order" => "page_order",
			"os" => "os",
			"mid" => "mid",
			"device_id" => "device_id",
			"lang" => "lang",
			"ver" => "ver",
			"param" => "param",
			"reg_datetime" => "reg_datetime",
		),
		'primary'=>array( 'history_id' ), //オリジナル名を指定
		'del_flag'=>false, //オリジナル名を指定
		'del_flag_default'=>0,	//初期値（有効なカラムを表す）
	);
	
	public function __construct(){
		$table = $this->table;
		parent::__construct($table);
	}
	
}