<?php

class ContentItemModel extends PTBaseModel
{
	protected $table = array(
		'name'=> 'content_item',
		'col'=>array(
			'content_id' => 'content_id',
			'title' => 'title',
			'description' => 'description',
			'content_type' => 'content_type',
			'plan' => 'plan',
			'status' => 'status',
			'publish_start_at' => 'publish_start_at',
			'publish_end_at' => 'publish_end_at',
			'sort_order' => 'sort_order',
			'config_json' => 'config_json',
			'memo' => 'memo',
			'del_flag' => 'del_flag',
			'upd_datetime' => 'upd_datetime',
			'reg_datetime' => 'reg_datetime',
		),
		'primary'=>array( 'content_id' ),
		'del_flag'=>'del_flag',
		'del_flag_default'=>0,
	);

	public function __construct(){
		parent::__construct($this->table);
	}
}
