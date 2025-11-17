<?php
// safe logger factory — uses Monolog if installed, otherwise falls back to a simple file logger.

// Include composer autoload (adjust path if file moved)
require_once __DIR__ . '/../../vendor/autoload.php';

function get_logger($name = 'app') {
    // Use Monolog if available
    if (class_exists('Monolog\\Logger')) {
        $logger = new Monolog\Logger($name);
        $logPath = __DIR__ . '/../../storage/logs/' . $name . '.log';
        $handler = new Monolog\Handler\StreamHandler($logPath, Monolog\Logger::DEBUG);
        $logger->pushHandler($handler);
        return $logger;
    }

    // Fallback: simple file logger
    return new class($name) {
        private $name;
        private $path;
        public function __construct($name) {
            $this->name = $name;
            $this->path = __DIR__ . '/../../storage/logs/' . $name . '.log';
        }
        public function info($msg) { $this->write('INFO', $msg); }
        public function debug($msg) { $this->write('DEBUG', $msg); }
        public function warning($msg) { $this->write('WARN', $msg); }
        public function error($msg) { $this->write('ERROR', $msg); }
        private function write($level, $msg) {
            $line = date('c') . " [{$level}] {$msg}\n";
            @file_put_contents($this->path, $line, FILE_APPEND);
        }
    };
}