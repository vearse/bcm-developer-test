<?php

// Helper function  to filter get parameters from the url
function bcm_page_param(array $get): int
{
    $raw = $get['page'] ?? null;
    if (!is_scalar($raw)) {
        return 1;
    }
    $n = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);
    return $n === false ? 1 : $n;
}

// Helper function  to filter search parameters from the url
function bcm_search_param(array $get): string
{
    $raw = $get['s'] ?? '';
    if (!is_string($raw)) {
        return '';
    }
    return trim(sanitize_text_field(wp_unslash($raw)));
}

// Literal [ad] in content becomes a div the front end can fill in. Slot numbers run across the whole page.
function bcm_inject_ads(string $html, int &$slot = 0): string
{
    return preg_replace_callback(
        '#<p>\s*\[ad\]\s*</p>|\[ad\]#',
        function () use (&$slot): string {
            $slot++;
            return sprintf('<div class="ad ad-%1$d" data-ad-slot="%1$d"></div>', $slot);
        },
        $html
    ) ?? $html;
}
