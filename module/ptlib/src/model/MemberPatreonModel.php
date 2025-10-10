<?php

class MemberPatreonModel extends PTBaseModel
{
	protected $table = array(
		'name'=> 'member_patreon',//テーブル名
		'col'=>array( //アクセス名 => オリジナル名（テーブルカラム名）
			'member_id' => 'member_id',
			'id' => 'id',
			'name' => 'name',
			'status' => 'status',
			'current_tier_id' => 'current_tier_id',
			'token_type' => 'token_type',
			'access_token' => 'access_token',
			'expires_in' => 'expires_in',
			'refresh_token' => 'refresh_token',
			'scope' => 'scope',
			'obtained_at' => 'obtained_at',
			'expires_at' => 'expires_at',
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