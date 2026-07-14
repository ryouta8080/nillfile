<?php

class RequestIdeaModel extends PTBaseModel
{
	protected $table = array(
		'name' => 'request_idea',
		'col' => array(
			'request_id' => 'request_id',
			'member_id' => 'member_id',
			'patreon_id' => 'patreon_id',
			'patron_name' => 'patron_name',
			'patron_status_at_request' => 'patron_status_at_request',
			'tier_id_at_request' => 'tier_id_at_request',
			'is_paid_at_request' => 'is_paid_at_request',
			'request_text' => 'request_text',
			'category' => 'category',
			'request_type' => 'request_type',
			'is_nsfw' => 'is_nsfw',
			'favorite_flag' => 'favorite_flag',
			'done_flag' => 'done_flag',
			'withdrawn_flag' => 'withdrawn_flag',
			'hidden_flag' => 'hidden_flag',
			'admin_memo' => 'admin_memo',
			'content_id' => 'content_id',
			'reply_text' => 'reply_text',
			'reply_visible_flag' => 'reply_visible_flag',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary' => array('request_id'),
		'del_flag' => 'del_flag',
		'del_flag_default' => 0,
	);

	public function __construct(){
		parent::__construct($this->table);
	}
}
