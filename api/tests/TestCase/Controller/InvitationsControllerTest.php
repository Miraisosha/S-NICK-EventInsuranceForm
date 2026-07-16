<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

class InvitationsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public function testHealth(): void
    {
        $this->get('/api/health');

        $this->assertResponseOk();
        $this->assertHeader('Content-Type', 'application/json; charset=UTF-8');
        $this->assertResponseContains('"status":"ok"');
    }

    public function testHealthRejectsPost(): void
    {
        $this->post('/api/health');

        $this->assertResponseCode(404);
    }
}
