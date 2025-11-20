<?php
/**
 * Logger Utility Test
 */

namespace Tests\Unit;

use Tests\TestCase;

require_once __DIR__ . '/../../backend/utils/Logger.php';

class LoggerTest extends TestCase
{
    private \Logger $logger;
    private string $testLogPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary log directory for testing
        $this->testLogPath = sys_get_temp_dir() . '/wifight_test_logs_' . uniqid();
        mkdir($this->testLogPath, 0755, true);

        $this->logger = new \Logger('TEST', $this->testLogPath);
    }

    protected function tearDown(): void
    {
        // Clean up test log files
        if (is_dir($this->testLogPath)) {
            $files = glob($this->testLogPath . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->testLogPath);
        }

        parent::tearDown();
    }

    public function testLogDirectoryCreation()
    {
        $this->assertDirectoryExists($this->testLogPath);
        $this->assertTrue(is_writable($this->testLogPath));
    }

    public function testDebugLogging()
    {
        $this->logger->debug('Debug message', ['key' => 'value']);

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString('[TEST]', $content);
        $this->assertStringContainsString('Debug message', $content);
        $this->assertStringContainsString('"key":"value"', $content);
    }

    public function testInfoLogging()
    {
        $this->logger->info('Info message');

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    public function testWarningLogging()
    {
        $this->logger->warning('Warning message');

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[WARNING]', $content);
        $this->assertStringContainsString('Warning message', $content);
    }

    public function testErrorLogging()
    {
        $this->logger->error('Error message', ['error_code' => 500]);

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[ERROR]', $content);
        $this->assertStringContainsString('Error message', $content);
        $this->assertStringContainsString('"error_code":500', $content);
    }

    public function testCriticalLogging()
    {
        $this->logger->critical('Critical error');

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[CRITICAL]', $content);
        $this->assertStringContainsString('Critical error', $content);
    }

    public function testLogRequestLogging()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/v1/users';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_USER_AGENT'] = 'PHPUnit Test';

        $this->logger->logRequest(['email' => 'test@example.com']);

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[INFO]', $content);
        $this->assertStringContainsString('HTTP Request', $content);
        $this->assertStringContainsString('POST', $content);
        $this->assertStringContainsString('/api/v1/users', $content);
        $this->assertStringContainsString('127.0.0.1', $content);
    }

    public function testLogQueryLogging()
    {
        $this->logger->logQuery('SELECT * FROM users WHERE id = ?', 15.5);

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[DEBUG]', $content);
        $this->assertStringContainsString('Database Query', $content);
        $this->assertStringContainsString('SELECT * FROM users', $content);
        $this->assertStringContainsString('15.5ms', $content);
    }

    public function testTimestampFormat()
    {
        $this->logger->info('Test message');

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        // Check timestamp format: [YYYY-MM-DD HH:MM:SS]
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testLogFileNaming()
    {
        $this->logger->info('Test message');

        $expectedFilename = date('Y-m-d') . '.log';
        $expectedPath = $this->testLogPath . '/' . $expectedFilename;

        $this->assertFileExists($expectedPath);
    }

    public function testMultipleLogEntries()
    {
        $this->logger->info('First message');
        $this->logger->warning('Second message');
        $this->logger->error('Third message');

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $lines = explode("\n", trim($content));
        $this->assertCount(3, $lines);
    }

    public function testClearOldLogs()
    {
        // Create some old log files
        $oldFile1 = $this->testLogPath . '/2020-01-01.log';
        $oldFile2 = $this->testLogPath . '/2020-01-02.log';
        $recentFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';

        touch($oldFile1, time() - (40 * 86400)); // 40 days old
        touch($oldFile2, time() - (35 * 86400)); // 35 days old
        touch($recentFile); // Today

        $deleted = $this->logger->clearOldLogs(30);

        $this->assertEquals(2, $deleted);
        $this->assertFileDoesNotExist($oldFile1);
        $this->assertFileDoesNotExist($oldFile2);
        $this->assertFileExists($recentFile);
    }

    public function testContextInLogs()
    {
        $logger = new \Logger('API', $this->testLogPath);
        $logger->info('Test message');

        $logFile = $this->testLogPath . '/' . date('Y-m-d') . '.log';
        $content = file_get_contents($logFile);

        $this->assertStringContainsString('[API]', $content);
    }
}
