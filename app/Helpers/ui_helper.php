<?php

use App\Libraries\UiStore;

/**
 * View helpers for the transplant UI.
 *
 * Only two things live here, and both are things HTML cannot express on its
 * own: the inline SVG icon set (copied path-for-path from the prototype's
 * `utils.js`) and two tiny lookups that turn a value into a CSS class name.
 * Everything else about the screens is written as literal HTML in
 * `app/Views/ui/`.
 */
if (! function_exists('ui_icon')) {
    /**
     * Inline SVG icon, rendered at exactly the size the prototype used.
     *
     * Returns raw markup, so echo it unescaped: `<?= ui_icon('plus') ?>`.
     */
    function ui_icon(string $name): string
    {
        static $icons = [
            'menu' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#15508A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>',

            'close' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',

            'dashboard' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>',

            'users' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',

            'heart' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',

            'link17' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',

            'search' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',

            'clipboard' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><line x1="8" y1="12" x2="16" y2="12"/><line x1="8" y1="16" x2="13" y2="16"/></svg>',

            'userPlus' => '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',

            'signOut' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',

            'pulse' => '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',

            'plus' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',

            'back' => '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>',

            'link14' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',

            'download' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',

            'trash' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>',

            'unlink' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.84 12.25l1.72-1.71a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M5.17 11.75l-1.71 1.71a5 5 0 0 0 7.07 7.07l1.71-1.71"/><line x1="8" y1="2" x2="8" y2="5"/><line x1="2" y1="8" x2="5" y2="8"/><line x1="16" y1="19" x2="16" y2="22"/><line x1="19" y1="16" x2="22" y2="16"/></svg>',

            'shuffle14' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>',

            'shuffle' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>',

            'printer' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>',

            // The fold on a card that opens and closes. It points down when
            // the card is shut and CSS turns it when it opens, so there is one
            // of them rather than two that could disagree.
            'chevron' => '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
            // The sidebar's own pair: each points the way it would move it.
            // `currentColor`, unlike `menu`, which is drawn in the sidebar's
            // own blue for the white bar it was made for and would be
            // invisible on the rail.
            'chevronLeft' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>',
            'chevronRight' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>',

            'edit' => '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',

            'check' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
            'calendar' => '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        ];

        return $icons[$name] ?? '';
    }
}

if (! function_exists('ui_tone')) {
    /**
     * CSS tone class for a badge: ui_tone('labStatus', 'flagged') === 'tone-amber'.
     *
     * @param 'status'|'labStatus' $kind
     */
    function ui_tone(string $kind, string $value): string
    {
        $map = match ($kind) {
            'status'     => UiStore::STATUS_TONE,
            'labStatus'  => UiStore::RESULT_TONE,
            default      => [],
        };

        return $map[$value] ?? '';
    }
}

if (! function_exists('ui_plural')) {
    /** "1 pair" / "2 pairs" — the `count !== 1 ? "s" : ""` of the source. */
    function ui_plural(int $count, string $singular): string
    {
        return $count . ' ' . $singular . ($count === 1 ? '' : 's');
    }
}

if (! function_exists('ui_back')) {
    /**
     * The back arrow: where somebody actually came from.
     *
     * Each screen passes the destination it would have named anyway, and gets
     * that one back whenever there is nothing better — a record opened from a
     * bookmark, the first screen of a session, a reload after signing in. When
     * there *is* something better, which is most of the time, it is the screen
     * before this one, with the filters and the search it had on it.
     *
     * {@see \App\Filters\TrailFilter} is what remembers, and the rule that
     * makes it behave: "before" means a different screen, so opening a card,
     * saving it or switching a tab does not become the thing the arrow goes
     * back to.
     *
     * A screen with a back that means something particular — Add Donor opened
     * for a pair, which must return to that pair — passes it as `$fixed` and
     * gets it honoured.
     *
     * @return array{url: string, label: string}
     */
    function ui_back(string $fallbackUrl, string $fallbackLabel, bool $fixed = false): array
    {
        if ($fixed) {
            return ['url' => $fallbackUrl, 'label' => $fallbackLabel];
        }

        // The trail is written *after* a response, so while this screen is
        // being rendered `ui_here` is still the screen before it — which is
        // exactly the one the arrow wants. `ui_back` is the one before that,
        // and it is what the arrow wants when this screen is answering a
        // second time: a card opened for editing, a save that came back, a
        // donor tab. Then `ui_here` is this screen, and going "back" to it
        // would be going nowhere.
        $here    = (array) (session('ui_here') ?? []);
        $current = trim(service('request')->getPath(), '/');
        $to      = ($here['path'] ?? null) === $current ? (array) (session('ui_back') ?? []) : $here;

        if (($to['url'] ?? '') === '' || ($to['path'] ?? null) === $current) {
            return ['url' => $fallbackUrl, 'label' => $fallbackLabel];
        }

        return ['url' => (string) $to['url'], 'label' => (string) $to['label']];
    }
}

if (! function_exists('ui_filter_values')) {
    /**
     * A filter chip row's set, read off the address.
     *
     * The chips were one-at-a-time: pressing B replaced A, so "A and B" was a
     * question the lists could not be asked. They are a set now, written in
     * the address as `?bt=A,B`, and the empty set means all — which is the
     * same thing as no filter and so leaves the address alone.
     *
     * Only values the screen actually offers survive, so a hand-typed address
     * narrows the list rather than breaking the query behind it.
     *
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    function ui_filter_values(?string $param, array $allowed): array
    {
        $param = trim((string) $param);

        // `all` is what the All chip puts in the address where a screen's own
        // default is something narrower; everywhere else it is the absence of
        // the parameter, and both mean the same thing here.
        if ($param === '' || $param === 'all') {
            return [];
        }

        return array_values(array_intersect($allowed, array_map('trim', explode(',', $param))));
    }
}

if (! function_exists('ui_filter_toggle')) {
    /**
     * The set a chip row would hold with this chip pressed.
     *
     * In if it was out, out if it was in — which is what makes one press add a
     * second blood group and a second press on the same chip take it off
     * again. The order the screen lists them in is kept, so the address reads
     * the same whichever order they were pressed.
     *
     * @param list<string> $chosen
     * @param list<string> $allowed
     *
     * @return list<string>
     */
    function ui_filter_toggle(array $chosen, string $value, array $allowed): array
    {
        $chosen = in_array($value, $chosen, true)
            ? array_values(array_diff($chosen, [$value]))
            : [...$chosen, $value];

        return array_values(array_intersect($allowed, $chosen));
    }
}

if (! function_exists('ui_filter_param')) {
    /** A chip row's set as the address writes it, '' for the empty one. */
    function ui_filter_param(array $chosen): string
    {
        return implode(',', $chosen);
    }
}
