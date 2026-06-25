<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendancePushTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_attendance_push_endpoint_accepts_any_post_payload_and_logs_to_file()
    {
        $payload = [
            'device_sn' => 'SN987654321',
            'events' => [
                [
                    'uid' => 1,
                    'card_no' => '99887766',
                    'timestamp' => '2026-05-20 12:00:00',
                    'verify_type' => 'fingerprint',
                ]
            ]
        ];

        $response = $this->post('/attendance-machine-push', $payload, [
            'X-Test-Header' => 'CustomAttendanceMachine',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('OK', $response->getContent());

        // Assert that a log file was created in device_logs/
        $files = Storage::disk('local')->files('device_logs');
        $this->assertNotEmpty($files);
        
        $logFile = $files[0];
        $logContents = Storage::disk('local')->get($logFile);
        
        $logContents = strtolower($logContents);
        $this->assertStringContainsString('x-test-header', $logContents);
        $this->assertStringContainsString('customattendancemachine', $logContents);
        $this->assertStringContainsString('sn987654321', $logContents);
        $this->assertStringContainsString('fingerprint', $logContents);
    }
}
