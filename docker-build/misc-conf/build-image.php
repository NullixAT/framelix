<?php

$buildType = $_SERVER['argv'][1];
$version = file_get_contents("/framelix/system/VERSION");

function runCmd(string $cmd): void
{
    echo "=> CMD: $cmd\n";
    passthru($cmd, $status);
    if ($status) {
        exit($status);
    }
}

if ($buildType === 'dev') {
    $tmpFolder = "/tmp/framelix-tag-$version";
    $targetFolder = "/framelix/appdata/modules/Framelix";
    echo "## Running build requirements for DEV build\n\n";

    echo "## Installing composer vendor\n\n";
    runCmd("export COMPOSER_ALLOW_SUPERUSER=1 && cd /framelix/appdata && composer update");
    echo "Done.\n\n";

    echo "## Installing PhpUnit deps\n\n";
    runCmd("export DEBIAN_FRONTEND=noninteractive && apt install php8.2-xdebug -y && cp /framelix/system/php-xdebug.ini /etc/php/8.2/cli/conf.d/21-xdebug.ini && rm /etc/php/8.2/fpm/conf.d/20-xdebug.ini && mkdir -p /opt/phpstorm-coverage && chmod 0777 /opt/phpstorm-coverage");
    echo "Done.\n\n";

    echo "## Installing Playwright deps\n\n";
    runCmd("cd /framelix/appdata/playwright && npm install -y && npx playwright install-deps && npx playwright install chromium");
    echo "Done.\n\n";

    echo "Dev build process completed.\n\n";
}

if ($buildType === 'docker-hub') {
    $tmpFolder = "/tmp/framelix-tag-$version";
    $targetFolder = "/framelix/appdata/modules/Framelix";
    echo "## Running build requirements for Docker HUB build\n\n";
    echo "# Download framelix for current version $version from Github\n";
    runCmd("rm -rf $tmpFolder");
    runCmd("mkdir -p $tmpFolder");
    runCmd(
        "curl https://github.com/NullixAT/framelix/archive/refs/tags/$version.zip -L --output $tmpFolder/package.zip"
    );
    echo "Done.\n\n";
    echo "# Extracting package and move to appdata\n";
    runCmd("mkdir -p /framelix/appdata/modules");
    runCmd("7zz x $tmpFolder/package.zip -spf -y -o$tmpFolder");
    runCmd("mv $tmpFolder/*/appdata/modules/Framelix $targetFolder");
    runCmd("rm -rf $tmpFolder");
    echo "Done.\n\n";

    echo "# Running npm install and composer install for docker build\n";
    runCmd("framelix_npm_modules_install");
    runCmd("framelix_composer_modules_install");
    echo "Done.\n\n";
    echo "Docker hub build process completed.\n\n";
}