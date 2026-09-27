<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reads the tail of storage/logs/laravel.log so developers do not need server access.
 */
class LogController extends Controller
{
    /**
     * Bytes read from the end of the log file.
     */
    private const TAIL_BYTES = 512 * 1024;

    private const MAX_ENTRIES = 200;

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'level' => ['nullable', 'string', 'in:emergency,alert,critical,error,warning,notice,info,debug'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $entries = collect($this->parse($this->tail()))
            ->when($filters['level'] ?? null, fn ($entries, string $level) => $entries->where('level', $level))
            ->when($filters['search'] ?? null, fn ($entries, string $search) => $entries->filter(
                fn (array $entry): bool => Str::contains($entry['message'].$entry['context'], $search, ignoreCase: true),
            ))
            ->take(self::MAX_ENTRIES)
            ->values();

        return Inertia::render('SuperAdmin/Logs', [
            'entries' => $entries,
            'filters' => ['level' => $filters['level'] ?? null, 'search' => $filters['search'] ?? null],
            'file' => [
                'path' => 'storage/logs/laravel.log',
                'size' => File::exists($this->path()) ? File::size($this->path()) : 0,
            ],
            'routes' => ['clear' => route('super.logs.destroy')],
        ]);
    }

    /**
     * Empty the log file.
     */
    public function destroy(): RedirectResponse
    {
        if (File::exists($this->path())) {
            File::put($this->path(), '');
        }

        $this->toast(__('The log file was cleared.'));

        return back();
    }

    private function path(): string
    {
        return storage_path('logs/laravel.log');
    }

    private function tail(): string
    {
        if (! File::exists($this->path())) {
            return '';
        }

        $handle = fopen($this->path(), 'rb');
        $size = filesize($this->path());
        fseek($handle, max(0, $size - self::TAIL_BYTES));
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * Split Monolog's line format into entries, newest first.
     *
     * @return list<array{id: int, date: string, env: string, level: string, message: string, context: string}>
     */
    private function parse(string $content): array
    {
        preg_match_all(
            '/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}[^\]]*)\] (\w+)\.(\w+): (.*?)(?=^\[\d{4}-\d{2}-\d{2}[ T]|\z)/ms',
            $content,
            $matches,
            PREG_SET_ORDER,
        );

        $entries = array_map(function (array $match, int $index): array {
            [$message, $context] = array_pad(explode("\n", trim($match[4]), 2), 2, '');

            return [
                'id' => $index,
                'date' => $match[1],
                'env' => $match[2],
                'level' => strtolower($match[3]),
                'message' => Str::limit($message, 500),
                'context' => Str::limit($context, 4000),
            ];
        }, $matches, array_keys($matches));

        return array_reverse($entries);
    }
}
