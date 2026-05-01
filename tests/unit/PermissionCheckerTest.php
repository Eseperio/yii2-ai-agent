<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\dto\PermissionContext;
use eseperio\aiagent\services\PermissionChecker;
use PHPUnit\Framework\TestCase;

class PermissionCheckerTest extends TestCase
{
    public function testViewPermissionsDefaultToAllowed(): void
    {
        $checker = new PermissionChecker();

        $this->assertTrue($checker->canViewChat(new PermissionContext('view')));
    }
}
