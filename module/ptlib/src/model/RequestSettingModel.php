<?php

class RequestSettingModel extends PTBaseModel
{
	protected $table = array(
		'name' => 'request_setting',
		'col' => array(
			'setting_id' => 'setting_id',
			'accept_flag' => 'accept_flag',
			'description_text' => 'description_text',
			'thanks_text' => 'thanks_text',
			'max_length' => 'max_length',
			'monthly_limit' => 'monthly_limit',
			'cooldown_minutes' => 'cooldown_minutes',
			'paid_only_flag' => 'paid_only_flag',
			'admin_bypass_flag' => 'admin_bypass_flag',
			'attachment_enabled_flag' => 'attachment_enabled_flag',
			'attachment_max_size_mb' => 'attachment_max_size_mb',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary' => array('setting_id'),
		'del_flag' => 'del_flag',
		'del_flag_default' => 0,
	);

	public function __construct(){
		parent::__construct($this->table);
	}
}
