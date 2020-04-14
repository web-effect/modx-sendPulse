<?php
if($hook){
	if($hook->formit)$controller=&$hook->formit;
	if($hook->controller&&$hook->controller->login)$controller=&$hook->controller->login;
	if(!$controller)return false;
	
	$config=$controller->config;
	$fields=$hook->fields;
	
	$success=true;
	$service=$modx->getService('sendpulse','sendPulse',MODX_CORE_PATH.'components/sendpulse/model/sendpulse/');
	$service->initialize();
	
	$actions=$config['sendPulse'];
	if(!is_array($actions))$actions = $hook->modx->fromJSON($actions)?:[];
	
	$success=$service->processActions($hook,$fields,$actions);
	
	return $success;
}else{
	$service=$modx->getService('sendpulse','sendPulse',MODX_CORE_PATH.'components/sendpulse/model/sendpulse/');
	$service->initialize();
	
	$actions=$scriptProperties['actions'];
	if(!is_array($actions))$actions = $modx->fromJSON($actions)?:[];
	
	$success=$service->processActions(new stdClass(),array(),$actions);
}

