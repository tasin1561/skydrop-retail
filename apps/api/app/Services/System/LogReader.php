<?php

namespace App\Services\System;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Read-only access to storage/logs for Admin → System → Settings: the last N lines of the newest log with secrets
 * masked, total log size and retention cleanup.
 */
class LogReader
{
    /** @var array{0: int, 1: list<string>}|null [app instance id, configured secret values to blank out] */
    private static ?array $secrets = null;

    public static function dir(): string
    {
        return storage_path('logs');
    }

    /** @return list<string> *.log files, newest first */
    public static function files(): array
    {
        $files = glob(self::dir().'/*.log') ?: [];
        usort($files, fn ($a, $b) => (@filemtime($b) ?: 0) <=> (@filemtime($a) ?: 0));

        return array_values($files);
    }

    public static function totalBytes(): int
    {
        return (int) array_sum(array_map(fn ($f) => @filesize($f) ?: 0, self::files()));
    }

    /** @return array{file: ?string, lines: list<string>} */
    public static function tail(int $lines = 200, ?string $file = null): array
    {
        $file ??= self::files()[0] ?? null;
        if (! $file || ! is_file($file) || ! str_starts_with(realpath($file) ?: '', realpath(self::dir()) ?: "\0")) {
            return ['file' => null, 'lines' => []];
        }
        $out = [];
        try {
            $fh = fopen($file, 'rb');
            $size = filesize($file) ?: 0;
            $chunk = 65536;
            $buffer = '';
            $pos = $size;
            while ($pos > 0 && substr_count($buffer, "\n") <= $lines) {
                $read = min($chunk, $pos);
                $pos -= $read;
                fseek($fh, $pos);
                $buffer = fread($fh, $read).$buffer;
                if (strlen($buffer) > 4_000_000) {
                    break;
                }
            }
            fclose($fh);
            $all = preg_split('/\r?\n/', rtrim($buffer, "\n")) ?: [];
            foreach (array_slice($all, -$lines) as $line) {
                $out[] = self::mask(mb_strimwidth($line, 0, 2000, '…'));
            }
        } catch (Throwable) {
            return ['file' => basename($file), 'lines' => []];
        }

        return ['file' => basename($file), 'lines' => $out];
    }

    /** Masks credentials: configured secret values, Bearer tokens, key=value pairs with secret-like names, long tokens. */
    public static function mask(string $text): string
    {
        foreach (self::secrets() as $secret) {
            $text = str_replace($secret, '••••', $text);
        }
        $text = preg_replace('/(Bearer|Basic)\s+[A-Za-z0-9\-._~+\/=]{8,}/i', '$1 ••••', $text) ?? $text;
        $text = preg_replace('/(["\']?(?:[a-z_]*(?:token|secret|password|passwd|api[_-]?key|app[_-]?key|private[_-]?key|signature|authorization))["\']?\s*[:=]\s*["\']?)([^"\'\s,&}]{4,})/i',
            '$1••••', $text) ?? $text;
        $text = preg_replace('/\bbase64:[A-Za-z0-9+\/=]{20,}/', 'base64:••••', $text) ?? $text;

        return preg_replace('/\b[a-f0-9]{40,}\b|\b[A-Za-z0-9_\-]{48,}\b/', '••••', $text) ?? $text;
    }

    /** Deletes *.log files older than $days (0 = keep forever). @return int files deleted */
    public static function prune(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }
        $n = 0;
        $cutoff = now()->subDays($days)->getTimestamp();
        foreach (self::files() as $f) {
            if ((@filemtime($f) ?: PHP_INT_MAX) < $cutoff && File::delete($f)) {
                $n++;
            }
        }

        return $n;
    }

    /** @return list<string> */
    private static function secrets(): array
    {
        $scope = spl_object_id(app());
        if (self::$secrets !== null && self::$secrets[0] === $scope) {
            return self::$secrets[1];
        }
        $out = [(string) config('app.key')];
        foreach (['retail', 'security', 'messaging', 'finance', 'assistant', 'services', 'database.connections', 'mail.mailers', 'system'] as $root) {
            foreach (Arr::dot((array) config($root, [])) as $k => $v) {
                if (is_string($v) && strlen($v) >= 6 && Setting::isSecret((string) $k)) {
                    $out[] = $v;
                }
            }
        }
        $out = array_values(array_unique(array_filter($out, fn ($s) => strlen($s) >= 6)));
        usort($out, fn ($a, $b) => strlen($b) <=> strlen($a));

        self::$secrets = [$scope, $out];

        return $out;
    }
}
