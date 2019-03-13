<?php

namespace iMSCP\Composer;

class iMSCPInstaller extends AbstractInstaller
{
    protected $locations = [
        'plugin' => 'plugins/{$name}/',
        'theme'  => 'theme/{$name}/',
        'tool'   => 'public/tools/{$name}/',
    ];
}
