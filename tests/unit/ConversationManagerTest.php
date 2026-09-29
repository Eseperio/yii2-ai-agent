<?php

namespace eseperio\aiagent\tests\unit;

use eseperio\aiagent\Module;
use PHPUnit\Framework\TestCase;

class ConversationManagerTest extends TestCase
{
    public function testForceNewSkipsReusableEmptyConversation(): void
    {
        $manager = Module::resolveActive()->getConversationManager();
        $createdBy = 'force-new-test-' . bin2hex(random_bytes(6));

        $first = $manager->createConversation(createdBy: $createdBy);
        $reused = $manager->createConversation(createdBy: $createdBy);
        $forced = $manager->createConversation(createdBy: $createdBy, forceNew: true);

        $this->assertSame((int)$first->id, (int)$reused->id);
        $this->assertNotSame((int)$first->id, (int)$forced->id);
    }
}
