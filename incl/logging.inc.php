<?php
// incl/logging.inc.php

declare(strict_types=1);

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

if (!class_exists(Logger::class)) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

/**
 * Size-rotating file handler:
 * - rotates when file >= $maxBytes
 * - keeps $maxFiles rotated logs (.1 .. .$maxFiles)
 *
 * Uses flock() on a per-logfile lock to avoid rotation races across processes.
 */
final class SizeRotatingFileHandler extends StreamHandler
{
    private string $filename;
    private int $maxBytes;
    private int $maxFiles;

    public function __construct(string $filename, $level = Logger::DEBUG, bool $bubble = true, int $maxBytes = 5242880, int $maxFiles = 5)
    {
        $this->filename = $filename;
        $this->maxBytes = $maxBytes;
        $this->maxFiles = $maxFiles;

        parent::__construct($filename, $level, $bubble);
    }

    protected function write(array $record): void
    {
        $this->maybeRotate();
        parent::write($record);
    }

    private function maybeRotate(): void
    {
        if (!is_file($this->filename)) {
            return;
        }

        clearstatcache(true, $this->filename);
        $size = filesize($this->filename);
        if ($size === false || $size < $this->maxBytes) {
            return;
        }

        $lockHandle = $this->acquireRotationLock();
        if ($lockHandle === null) {
            // If we can't lock, skip rotation; next write will try again.
            return;
        }

        try {
            // Another process may have rotated while we waited; re-check.
            clearstatcache(true, $this->filename);
            $sizeAfterLock = is_file($this->filename) ? filesize($this->filename) : false;
            if ($sizeAfterLock === false || $sizeAfterLock < $this->maxBytes) {
                return;
            }

            // Rotate: file.log.4 -> file.log.5, ..., file.log -> file.log.1
            for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
                $src = $this->filename . '.' . $i;
                $dst = $this->filename . '.' . ($i + 1);
                if (is_file($src)) {
                    @rename($src, $dst);
                }
            }

            @rename($this->filename, $this->filename . '.1');
        } finally {
            $this->releaseRotationLock($lockHandle);
        }
    }

    /**
     * @return resource|null
     */
    private function acquireRotationLock()
    {
        $lockFile = $this->filename . '.lock';
        $h = @fopen($lockFile, 'c');
        if ($h === false) {
            return null;
        }

        // Block until we can rotate safely (Linux-friendly).
        if (!@flock($h, LOCK_EX)) {
            @fclose($h);
            return null;
        }

        return $h;
    }

    /**
     * @param resource $lockHandle
     */
    private function releaseRotationLock($lockHandle): void
    {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
}

final class BBLog
{
    /** @var array<string, Logger> */
    private static array $loggers = array();

    public static function get(string $channel): Logger
    {
        $channel = $channel !== '' ? $channel : 'app';

        if (isset(self::$loggers[$channel])) {
            return self::$loggers[$channel];
        }

        // Config is loaded very early in this app; use it if available.
        $levelName = 'info';
        $combined = true;

        if (isset($GLOBALS['CONFIG'])) {
            if (isset($GLOBALS['CONFIG']->LOG_LEVEL)) {
                $levelName = (string)$GLOBALS['CONFIG']->LOG_LEVEL;
            }
            if (isset($GLOBALS['CONFIG']->LOG_COMBINED)) {
                $combined = (bool)$GLOBALS['CONFIG']->LOG_COMBINED;
            }
        } else {
            if (defined('LOG_LEVEL')) {
                $levelName = (string)LOG_LEVEL;
            }
            if (defined('LOG_COMBINED')) {
                $combined = (bool)LOG_COMBINED;
            }
        }

        $level = Logger::toMonologLevel($levelName);

        $logDir = __DIR__ . '/../data/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $fileBaseName = $combined ? 'bbuddy' : $channel;
        $file = $logDir . '/' . $fileBaseName . '.log';

        $logger = new Logger($channel);

        $handler = new SizeRotatingFileHandler(
            $file,
            $level,
            true,
            5 * 1024 * 1024, // 5 MB
            5               // keep last 5 rotated logs
        );

        // Nice single-line logs, includes channel already via %channel%
        $format = "[%datetime%] %level_name% %channel%: %message% %context% %extra%\n";
        $handler->setFormatter(new LineFormatter($format, null, true, true));

        $logger->pushHandler($handler);

        self::$loggers[$channel] = $logger;
        return $logger;
    }
}

/** Convenience function */
function bb_logger(string $channel): Logger
{
    return BBLog::get($channel);
}
