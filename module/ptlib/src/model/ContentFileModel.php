<?php

class ContentFileModel extends PTBaseModel
{
	protected $table = array(
		'name'=> 'content_file',
		'col'=>array(
			'file_id' => 'file_id',
			'content_id' => 'content_id',
			'file_type' => 'file_type',
			'code' => 'code',
			'file_key' => 'file_key',
			'storage_path' => 'storage_path',
			'original_name' => 'original_name',
			'display_name' => 'display_name',
			'mime_type' => 'mime_type',
			'file_size' => 'file_size',
			'suffix' => 'suffix',
			'cache_value' => 'cache_value',
			'is_primary' => 'is_primary',
			'sort_order' => 'sort_order',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary'=>array( 'file_id' ),
		'del_flag'=>'del_flag',
		'del_flag_default'=>0,
	);

	public function __construct(){
		parent::__construct($this->table);
	}
}
