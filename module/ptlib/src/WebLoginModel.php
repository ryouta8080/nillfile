<?php

class WebLoginModel extends PCModel
{
	private $table = array(
		'name'=> 'login',//テーブル名
		'col'=>array( //アクセス名 => オリジナル名（テーブルカラム名）
			'login_id'=>'login_id',//内部ユーザID
			'id'=>'id',//内部ユーザID
			'l_token'=>'l_token',//ログイントークン
			'l_key'=>'l_key',//キー
			'l_expire'=>'l_expire',//有効期限
			'reg_datetime'=>'reg_datetime',
		),
		'primary'=>array( 'login_id' ), //オリジナル名を指定
		'del_flag'=>false, //オリジナル名を指定
		'del_flag_default'=>0,	//初期値（有効なカラムを表す）
	);
	
	public function __construct( $prefix="c_" ){
		$this->table['name'] = $prefix . $this->table['name'];
		parent::__construct($this->table);
	}
	
}