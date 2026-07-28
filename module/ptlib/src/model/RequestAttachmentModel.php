<?php

class RequestAttachmentModel extends PTBaseModel
{
	protected $table = array(
		'name' => 'request_attachment',
		'col' => array(
			'attachment_id' => 'attachment_id',
			'request_id' => 'request_id',
			'sort_order' => 'sort_order',
			'attachment_status' => 'attachment_status',
			'storage_path' => 'storage_path',
			'mime_type' => 'mime_type',
			'file_size' => 'file_size',
			'deleted_datetime' => 'deleted_datetime',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary' => array('attachment_id'),
		'del_flag' => 'del_flag',
		'del_flag_default' => 0,
	);

	public function __construct(){
		parent::__construct($this->table);
	}
}
