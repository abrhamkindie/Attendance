<?php

namespace App\Services\Attendance;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Turns any Google Maps link into coordinates WITHOUT a Google API key.
 *
 * Long links already contain the coordinates, so they are parsed locally and
 * never touch the network. Short share links (maps.app.goo.gl/xxxx) are plain
 * HTTP redirects that a browser cannot read cross-origin, so the server
 * follows the redirect chain itself - one host-whitelisted hop at a time,
 * never blindly - and reads the pin out of the URL it lands on.
 */
class GoogleMapsLinkResolver
{
    private const MAX_REDIRECTS = 6;

    private const TIMEOUT_SECONDS = 10;

    private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36';

    /**
     * Order matters: the !3d!4d pair is the real dropped pin, while @lat,lng
     * is only the viewport centre and can be several hundred metres off.
     */
    private const PATTERNS = [
        '/!3d(-?\d{1,3}(?:\.\d+)?)!4d(-?\d{1,3}(?:\.\d+)?)/',
        '/[?&](?:q|ll|daddr|destination|center)=(-?\d{1,3}(?:\.\d+)?)(?:,|%2C)(-?\d{1,3}(?:\.\d+)?)/i',
        '/\/place\/(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
        '/\[null,null,(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)\]/',
        '/@(-?\d{1,3}(?:\.\d+)?),(-?\d{1,3}(?:\.\d+)?)/',
        '/^\s*(-?\d{1,3}(?:\.\d+)?)\s*,\s*(-?\d{1,3}(?:\.\d+)?)\s*$/',
    ];

    /** Hosts that only ever issue a redirect and carry no coordinates. */
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl'];

    /**
     * @return array{latitude: float, longitude: float, resolved_url: string}
     */
    public function resolve(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            throw new InvalidArgumentException(
                'Paste a Google Maps link or a pair like "9.0204692, 38.8024029".'
            );
        }

        // Coordinates are already readable here: nothing to fetch.
        if (! $this->isShortLink($input)) {
            $direct = $this->extract($input);

            if ($direct !== null) {
                return $direct + ['resolved_url' => $input];
            }
        }

        if (! $this->isAllowedUrl($input)) {
            throw new InvalidArgumentException(
                'That does not look like a Google Maps link or a coordinate pair.'
            );
        }

        return $this->followRedirects($input);
    }

    /**
     * Pull the first plausible coordinate pair out of any text.
     *
     * @return array{latitude: float, longitude: float}|null
     */
    public function extract(string $text): ?array
    {
        $candidates = [
            $text,
            html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            urldecode($text),
        ];

        foreach ($candidates as $candidate) {
            foreach (self::PATTERNS as $pattern) {
                if (! preg_match($pattern, $candidate, $match)) {
                    continue;
                }

                $latitude = (float) $match[1];
                $longitude = (float) $match[2];

                if (abs($latitude) > 90 || abs($longitude) > 180) {
                    continue;
                }

                // (0, 0) is the "null island" default, never a real office.
                if ($latitude === 0.0 && $longitude === 0.0) {
                    continue;
                }

                return ['latitude' => $latitude, 'longitude' => $longitude];
            }
        }

        return null;
    }

    /**
     * Only Google hosts are ever contacted, on the first hop and on every
     * redirect after it. This is what keeps the endpoint from being usable as
     * an open proxy against internal addresses.
     */
    public function isAllowedUrl(string $url): bool
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);

        if (in_array($host, self::SHORT_HOSTS, true)) {
            return true;
        }

        // maps.google.com, www.google.com, google.co.uk, google.com.et, ...
        return (bool) preg_match('/^(?:[a-z0-9-]+\.)*google\.(?:[a-z]{2,3}\.)*[a-z]{2,3}$/', $host);
    }

    private function isShortLink(string $url): bool
    {
        $host = parse_url(trim($url), PHP_URL_HOST);

        return is_string($host) && in_array(strtolower($host), self::SHORT_HOSTS, true);
    }

    /**
     * @return array{latitude: float, longitude: float, resolved_url: string}
     */
    private function followRedirects(string $url): array
    {
        $current = $url;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $found = $this->extract($current);

            if ($found !== null) {
                return $found + ['resolved_url' => $current];
            }

            $response = $this->fetch($current);
            $location = (string) $response->header('Location');

            if ($location !== '') {
                $next = $this->absolute($current, $location);

                if (! $this->isAllowedUrl($next)) {
                    throw new InvalidArgumentException(
                        'That link redirects to an unknown site, so it was rejected.'
                    );
                }

                $current = $next;

                continue;
            }

            // Landed on the final page: the coordinates usually sit in its
            // canonical URL, but occasionally only in the body.
            $found = $this->extract((string) $response->body());

            if ($found !== null) {
                return $found + ['resolved_url' => $current];
            }

            break;
        }

        throw new InvalidArgumentException(
            'Google did not return coordinates for that link. Open it in Google Maps, then use Share > Copy link again.'
        );
    }

    private function fetch(string $url)
    {
        try {
            return Http::withOptions([
                'allow_redirects' => false,
                'timeout'         => self::TIMEOUT_SECONDS,
                'connect_timeout' => self::TIMEOUT_SECONDS,
            ])->withHeaders([
                'User-Agent'      => self::USER_AGENT,
                'Accept'          => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'en',
            ])->get($url);
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Could not reach Google Maps. Check the server internet connection and try again.'
            );
        }
    }

    private function absolute(string $base, string $next): string
    {
        $next = trim($next);

        if (preg_match('#^https?://#i', $next)) {
            return $next;
        }

        try {
            return (string) UriResolver::resolve(new Uri($base), new Uri($next));
        } catch (Throwable $e) {
            return $next;
        }
    }
}
