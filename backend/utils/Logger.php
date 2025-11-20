<?php
/**
 * WiFight ISP System - Logger Utility
 *
 * Simple logging utility for application events
 */

class Logger {
    private $logPath;
    private $context;

    public function __construct($context = 'APP', $logPath = null) {
        $this->context = $context;
        $this->logPath = $logPath ?: __DIR__ . '/../../storage/logs';

        // Create logs directory if it doesn't exist
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $data
     * @return void
     */
    public function debug($message, $data = []) {
        $this->log('DEBUG', $message, $data);
    }

    /**
     * Log info message
     *
     * @param string $message
     * @param array $data
     * @return void
     */
    public function info($message, $data = []) {
        $this->log('INFO', $message, $data);
    }

    /**
     * Log warning message
     *
     * @param string $message
     * @param array $data
     * @return void
     */
    public function warning($message, $data = []) {
        $this->log('WARNING', $message, $data);
    }

    /**
     * Log error message
     *
     * @param string $message
     * @param array $data
     * @return void
     */
    public function error($message, $data = []) {
        $this->log('ERROR', $message, $data);
    }

    /**
     * Log critical message
     *
     * @param string $message
     * @param array $data
     * @return void
     */
    public function critical($message, $data = []) {
        $this->log('CRITICAL', $message, $data);
    }

    /**
     * Write log entry
     *
     * @param string $level
     * @param string $message
     * @param array $data
     * @return void
     */
    private function log($level, $message, $data = []) {
        $logLevel = getenv('LOG_LEVEL') ?: 'debug';
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3, 'CRITICAL' => 4];

        // Check if current level should be logged
        if ($levels[$level] < $levels[strtoupper($logLevel)]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $logFile = $this->logPath . '/' . date('Y-m-d') . '.log';

        $logEntry = sprintf(
            "[%s] [%s] [%s] %s",
            $timestamp,
            $level,
            $this->context,
            $message
        );

        if (!empty($data)) {
            $logEntry .= ' ' . json_encode($data);
        }

        $logEntry .= PHP_EOL;

        // Write to file
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        // Also write to PHP error log for errors and critical
        if (in_array($level, ['ERROR', 'CRITICAL'])) {
            error_log($logEntry);
        }
    }

    /**
     * Log HTTP request
     *
     * @param array $request
     * @return void
     */
    public function logRequest($request = []) {
        $requestData = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'data' => $request
        ];

        $this->info('HTTP Request', $requestData);
    }

    /**
     * Log database query
     *
     * @param string $query
     * @param float $executionTime
     * @return void
     */
    public function logQuery($query, $executionTime = 0) {
        $this->debug('Database Query', [
            'query' => $query,
            'execution_time' => $executionTime . 'ms'
        ]);
    }

    /**
     * Clear old log files
     *
     * @param int $days Keep logs for this many days
     * @return int Number of files deleted
     */
    public function clearOldLogs($days = 30) {
        $files = glob($this->logPath . '/*.log');
        $deleted = 0;
        $cutoff = time() - ($days * 86400);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
