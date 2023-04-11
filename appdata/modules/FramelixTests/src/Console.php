<?php

namespace Framelix\FramelixTests;

use Framelix\Framelix\Framelix;
use Framelix\Framelix\Utils\Shell;

use function file_exists;

use const FRAMELIX_MODULE;

class Console extends \Framelix\Framelix\Console
{
    /**
     * Called when the application is warmup, during every docker container start
     * Override this function to provide your own update/upgrade path
     * @return int Status Code, 0 = success
     */
    public static function appWarmup(): int
    {
        $userConfigFile = \Framelix\Framelix\Config::getUserConfigFilePath();
        if (!file_exists($userConfigFile)) {
            Framelix::createInitialUserConfig(
                FRAMELIX_MODULE,
                'test',
                '127.0.0.1:' . \Framelix\Framelix\Config::$environmentConfig["moduleAccessPoints"][FRAMELIX_MODULE]['port'],
                ''
            );
        }
        return 0;
    }
}