<?php


$data['modSystemSetting']=[
    'user'=>[
        'fields'=>[
            'key'=>$config['component']['namespace'].'.api.user',
            'value'=>'',
            'xtype'=>'text-password',
            'namespace'=>$config['component']['namespace'],
            'area'=>$config['component']['namespace'].'.api'
        ],
        'options'=>$config['data_options']['modSystemSetting']
    ],
    'secret'=>[
        'fields'=>[
            'key'=>$config['component']['namespace'].'.api.secret',
            'value'=>'',
            'xtype'=>'text-password',
            'namespace'=>$config['component']['namespace'],
            'area'=>$config['component']['namespace'].'.api'
        ],
        'options'=>$config['data_options']['modSystemSetting']
    ],
];
