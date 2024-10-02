<?php

use PHPUnit\Framework\TestCase;
use Aslamhus\WordpressHMR\Install;

class TestEnqueueAssets extends TestCase
{
    public function testPostPackageInstall()
    {
        Install::postPackageInstall('');
    }
}
