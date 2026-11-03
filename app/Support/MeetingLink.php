<?php

namespace App\Support;

class MeetingLink
{
    public const HELP = 'For online meetings, paste an HTTPS Google Meet, Zoom, or Microsoft Teams link. Shortened links and other websites are not supported.';

    public static function url(?string $value): ?string
    {
        $url = trim($value ?? '');
        if (!filter_var($url, FILTER_VALIDATE_URL) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) return null;
        $parts = parse_url($url);
        if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https'
            || isset($parts['user']) || isset($parts['pass'])
            || (isset($parts['port']) && $parts['port'] !== 443)) return null;
        $host = strtolower($parts['host'] ?? '');
        $allowed = in_array($host, ['meet.google.com', 'zoom.us', 'teams.microsoft.com', 'teams.live.com', 'teams.cloud.microsoft'], true)
            || preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+zoom\.us$/D', $host);
        return $allowed ? $url : null;
    }
}
