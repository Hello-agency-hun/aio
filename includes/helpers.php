<?php
/**
 * Általános segédfüggvények.
 *
 * Ezek kis, újrafelhasználható műveletek: kimenet escape-elése,
 * JSON válasz küldése, URL normalizálás és biztonsági ellenőrzések.
 */

declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function normalize_url(string $url): ?string
{
    $url = trim($url);

    if ($url === '') {
        return null;
    }

    if (preg_match('~\s~u', $url)) {
        return null;
    }

    // A felhasználók gyakran csak domaint írnak be: www.pelda.hu vagy pelda.hu.
    // Ilyenkor https előtaggal normalizálunk, de minden végső ellenőrzés itt marad.
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    } elseif (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) && !preg_match('~^https?://~i', $url)) {
        return null;
    } elseif (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }

    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || empty($parts['scheme'])) {
        return null;
    }

    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    $host = strtolower($parts['host']);
    if (preg_match('~[\s/@]~', $host) || str_starts_with($host, '.') || str_ends_with($host, '.')) {
        return null;
    }

    $path = $parts['path'] ?? '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    return $scheme . '://' . $host . $path . $query;
}

function is_public_target_url(string $url): bool
{
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    $host = strtolower($host);
    if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
        return false;
    }

    $records = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if (!$records) {
        return false;
    }

    foreach ($records as $ip) {
        $isPublic = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
        if ($isPublic === false) {
            return false;
        }
    }

    return true;
}

function same_host(string $candidate, string $root): bool
{
    $candidateHost = parse_url($candidate, PHP_URL_HOST);
    $rootHost = parse_url($root, PHP_URL_HOST);

    return is_string($candidateHost)
        && is_string($rootHost)
        && strtolower($candidateHost) === strtolower($rootHost);
}

function absolute_url(string $href, string $baseUrl): ?string
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($href === '' || str_starts_with($href, '#') || preg_match('~^(mailto|tel|javascript):~i', $href)) {
        return null;
    }

    if (preg_match('~^https?://~i', $href)) {
        return normalize_url($href);
    }

    $base = parse_url($baseUrl);
    if (!$base || empty($base['scheme']) || empty($base['host'])) {
        return null;
    }

    $scheme = $base['scheme'];
    $host = $base['host'];
    $basePath = $base['path'] ?? '/';

    if (str_starts_with($href, '//')) {
        return normalize_url($scheme . ':' . $href);
    }

    if (str_starts_with($href, '/')) {
        return normalize_url($scheme . '://' . $host . $href);
    }

    $directory = preg_replace('~/[^/]*$~', '/', $basePath) ?: '/';
    return normalize_url($scheme . '://' . $host . $directory . $href);
}

function canonicalize_for_queue(string $url): string
{
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }

    $path = $parts['path'] ?? '/';
    $path = preg_replace('~/+~', '/', $path) ?: '/';
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';

    return strtolower($parts['scheme'] . '://' . $parts['host']) . rtrim($path, '/') . $query;
}

function score_label(int $score): string
{
    if ($score >= 85) {
        return 'Rendben van';
    }

    if ($score >= 65) {
        return 'Javítható';
    }

    return 'Sok teendő';
}

function text_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function count_words(string $value): int
{
    $normalized = trim(preg_replace('~\s+~u', ' ', strip_tags($value)) ?: '');
    if ($normalized === '') {
        return 0;
    }

    preg_match_all('~[\p{L}\p{N}][\p{L}\p{N}\-]*~u', $normalized, $matches);
    return count($matches[0] ?? []);
}

function text_excerpt(string $value, int $limit = 220): string
{
    $value = trim(preg_replace('~\s+~u', ' ', $value) ?: '');
    if (text_length($value) <= $limit) {
        return $value;
    }

    $cut = function_exists('mb_substr') ? mb_substr($value, 0, $limit, 'UTF-8') : substr($value, 0, $limit);
    return rtrim($cut) . '...';
}
