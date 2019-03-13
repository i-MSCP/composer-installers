<?php

namespace iMSCP\Composer;

class iMSCPInstaller extends AbstractInstaller
{
    protected $locations = [
        'plugin' => 'plugins/{$name}/',
        'theme'  => 'themes/{$name}/',
        'tool'   => 'public/tools/{$name}/',
    ];
}
