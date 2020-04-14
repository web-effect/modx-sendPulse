<?php

class sendPulse
{
    const NAMESPACE='sendpulse';
    public $modx;
    public $authenticated = false;
    public $errors = array();

    function __construct(modX &$modx, array $config = array())
    {
        $this->modx =& $modx;
        
        $localPath='components/'.static::NAMESPACE.'/';
        $corePath = $this->modx->getOption(static::NAMESPACE.'.core_path', $config, $this->modx->getOption('core_path') . $localPath);
        $assetsPath = $this->modx->getOption(static::NAMESPACE.'.assets_path', $config, $this->modx->getOption('assets_path') . $localPath);
        $assetsUrl = $this->modx->getOption(static::NAMESPACE.'.assets_url', $config, $this->modx->getOption('assets_url') . $localPath);
        $connectorUrl = $assetsUrl . 'connector.php';
        $context_path = $this->modx->context->get('key')=='mgr'?'mgr':'web';

        $this->config = array_merge(array(
            'assetsUrl' => $assetsUrl,
            'cssUrl' => $assetsUrl . $context_path . '/css/',
            'jsUrl' => $assetsUrl . $context_path . '/js/',
            'jsPath' => $assetsPath . $context_path . '/js/',
            'imagesUrl' => $assetsUrl . $context_path . '/img/',
            'connectorUrl' => $connectorUrl,
            'corePath' => $corePath,
            'modelPath' => $corePath . 'model/',
            'servicePath' => $corePath . 'model/'.static::NAMESPACE.'/',
            'chunksPath' => $corePath . 'elements/chunks/',
            'templatesPath' => $corePath . 'elements/templates/',
            'chunkSuffix' => '.chunk.tpl',
            'snippetsPath' => $corePath . 'elements/snippets/',
            'processorsPath' => $corePath . 'processors/',
            'vendorPath' => $corePath . 'vendor/',
        ), $config);

        $this->modx->lexicon->load(static::NAMESPACE.':default');
        $this->authenticated = $this->modx->user->isAuthenticated($this->modx->context->get('key'));
        $this->loadModel();
        
        spl_autoload_register(array($this,'autoload'));
        if(is_dir($this->config['vendorPath']))require $this->config['vendorPath'].'autoload.php';
    }

    public function initialize($scriptProperties = array(),$ctx = 'web')
    {
        $this->config['options'] = $scriptProperties;
        $this->config['ctx'] = $ctx;
        
        $this->api = new Sendpulse\RestApi\ApiClient(
            base64_decode($this->modx->getOption(static::NAMESPACE.'.api.user',$scriptProperties,$this->modx->getOption(static::NAMESPACE.'.api.user'),true)),
            base64_decode($this->modx->getOption(static::NAMESPACE.'.api.secret',$scriptProperties,$this->modx->getOption(static::NAMESPACE.'.api.secret'),true)),
            new Sendpulse\RestApi\Storage\FileStorage()
        );
        return true;
    }
    
    public function autoload($class){
        $class = explode('/',str_replace("\\", "/", $class));
        $className = array_pop($class);
        $classPath = strtolower(implode('/',$class));
        
        $path = $this->config['modelPath'].'/'.$classPath.'/'.$className.'.php';
        if(!file_exists($path))return false;
        include $path;
    }
    
    public function loadAssets($ctx){
        if(!$this->modx->controller)return false;
        $this->modx->controller->addLexiconTopic(static::NAMESPACE.':default');
        switch($ctx){
            case 'mgr':{
                $this->modx->controller->addJavascript($this->config['assetsUrl'].'mgr/js/'.static::NAMESPACE.'.js');
            }
        }
    }
    
    public function loadModel(){
        //Ищем файл metadata
        $metadata=$this->config['servicePath']."metadata.".$this->modx->config['dbtype'].'.php';
        if(file_exists($metadata))$this->modx->addPackage(static::NAMESPACE, $this->config['modelPath']);
    }
    
    
    
    public function processActions(&$hook,$fields=array(),$actions,$response=array()){
        $success=true;
        foreach($actions as $name=>$options){
            $options=$this->prepareAction($hook,$fields,$name,$options,$response);
            if(!$options)continue;
            
            $_options=$options;
            unset($_options['success']);
            unset($_options['failure']);
            unset($_options['_method']);
            unset($_options['log']);
            //$this->modx->log(1,print_r($_options,1));
            $response=$this->callApi($options['_method'],$_options);
            if($options['log'])$this->modx->log(MODX_LOG_LEVEL_ERROR,print_r($response,1));
            
            if($response->is_error){
                if(method_exists($hook,'addError'))$hook->addError(static::NAMESPACE,$response->message?:$response->http_code);
                $this->modx->log(MODX_LOG_LEVEL_ERROR,print_r($response,1));
                $success=false;
                break;
            }else{
                if(!empty($response->result)&&$options['success']){
                    $success=$this->processActions($hook,$fields,$options['success'],json_decode(json_encode($response),true));
                }
                if(empty($response->result)&&$options['failure']){
                    $success=$this->processActions($hook,$fields,$options['failure'],json_decode(json_encode($response),true));
                }
                if(!$success)break;
            }
        }
        return $success;
    }
    
    public function prepareAction(&$hook,$fields,$name,$options,$response=array()){
        if(is_scalar($options)&&strpos($options,'@SNIPPET')===0){
            $options=$this->modx->runSnippet(trim(substr($options,8)),['name'=>$name,'hook'=>$hook]);
            if(!is_array($options))$options=json_decode($options,true);
            if(!$options)return false;
        }
        if(!$options['_method'])$options['_method']=$name;
        //$this->modx->log(1,print_r($fields,1));
        $options=$this->processOptions($options,array_merge($response,$fields));
        return $options;
    }
    
    public function processOptions($options,$placeholders){
        $this->modx->getParser();
        $maxIterations = (integer) $this->modx->getOption('parser_max_iterations', null, 10);
        foreach($options as $key=>&$option){
            if($key==='success'||$key==='failure')continue;
            if(is_scalar($option)){
                if($this->modx->parser instanceof pdoParser)$option = $this->modx->parser->pdoTools->getChunk('@INLINE '.$option, $placeholders);
                else $option = $chunk->process($placeholders,$option);
                $this->modx->parser->processElementTags('', $option, false, false, '[[', ']]', array(), $maxIterations);
                $this->modx->parser->processElementTags('', $option, true, true, '[[', ']]', array(), $maxIterations);
            }elseif(is_array($option)){
                $option=$this->processOptions($option,$placeholders);
            }
        }
        return $options;
    }
    
    
    
    
    public function callApi($method,$args){
        $response=call_user_func_array(array($this->api,$method),$args);
        /*if($response->is_error){
            $error=$response->message?:$response->http_code;
            $this->addError($error);
        }*/
        return $response;
    }
    
    public function addError($message){
        $this->errors[]=$message;
    }
    public function hasErrors(){
        return !empty($this->errors);
    }
}
