<?php

class RequestTypeSettingModel extends PTBaseModel
{
	protected $table = array(
		'name' => 'request_type_setting',
		'col' => array(
			'type_code' => 'type_code',
			'type_label' => 'type_label',
			'enabled_flag' => 'enabled_flag',
			'sort_order' => 'sort_order',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary' => array('type_code'),
		'del_flag' => 'del_flag',
		'del_flag_default' => 0,
	);

	public function __construct(){
		parent::__construct($this->table);
	}
}
