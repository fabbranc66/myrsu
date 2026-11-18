<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class ApiErrorHandler
{
    public static function response(Throwable $exception): Response
    {
        $status = $exception instanceof HttpException ? $exception->status() : 500;
        return Response::json(['error' => self::publicError($exception)], $status);
    }

    public static function publicError(Throwable $exception): array
    {
        if ($exception instanceof HttpException && $exception->status() < 500) {
            return ['message' => $exception->getMessage()];
        }

        $errorId = bin2hex(random_bytes(6));
        $trace = array_map(static fn (array $frame): array => [
            'file' => $frame['file'] ?? null,
            'line' => $frame['line'] ?? null,
            'call' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
        ], $exception->getTrace());
        error_log('[MyRSU ' . $errorId . '] ' . json_encode([
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));

        return ['message' => 'Errore interno.', 'error_id' => $errorId];
    }
}
