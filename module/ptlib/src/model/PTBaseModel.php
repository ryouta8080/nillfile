<?php

class PTBaseModel extends PCModel
{
	protected $valError = false;
	
	protected function getValidateParam(){
		return false;
	}
	public function validation($data){
		if(!$data || ! is_array($data) || count($data)==0 ){
			return false;
		}
		$validate = $this->getValidateParam();
		if( ! $validate){
			return true;
		}else{
			$ret = $this->getParam($data, $validate);
			$this->valError = PCF::getFormError();
			return $ret;
		}
	}
	public function getValidationError(){
		return $this->valError;
	}
	
	public function vId(){
		return PCV::vFunc( function($value, $param, $v){
			if($value==null || $value==""){
				return false;
			}
			return true;
		});
	}
	public function vCardNumber(){
		return PCV::vFunc( function($value, $param, $v){
			if($value==null || $value==""){
				return false;
			}
			return true;
		});
	}
	public function vReqText(){
		return PCV::vFunc( function($value, $param, $v){
			if($value==null || $value==""){
				return false;
			}
			return true;
		});
	}
	public function vColor(){
		return PCV::vInArray(array("blue","green","yellow","red","purple",));
	}
	
	public function getParam($data,PCFormParam $call_pcf_useParam)
	{
		$pcform = (new PCForm())->setupParam($data, $call_pcf_useParam);
		$result = $pcform->getValues();
		PCF::setFormErrorInfo( $pcform->getErrorInfo() );
		PCF::setFormErrorCode( $pcform->getCode() );
		return $result;
	}
	
}