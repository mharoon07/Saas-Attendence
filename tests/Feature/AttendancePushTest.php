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

        $response = $this->postJson('/api/attendance-machine-push', $payload, [
            'X-Test-Header' => 'CustomAttendanceMachine',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Data logged successfully',
        ]);

        // Assert that the file was created and contains our logged information
        Storage::disk('local')->assertExists('attendance_device_logs.txt');
        
        $logContents = Storage::disk('local')->get('attendance_device_logs.txt');
        
        $this->assertStringContainsString('CLIENT IP', $logContents);
        $logContents = strtolower($logContents);
        $this->assertStringContainsString('x-test-header', $logContents);
        $this->assertStringContainsString('customattendancemachine', $logContents);
        $this->assertStringContainsString('sn987654321', $logContents);
        $this->assertStringContainsString('fingerprint', $logContents);
    }
}
