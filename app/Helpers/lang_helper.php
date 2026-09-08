<?php

/**
 * Locale helper, migrated from the CodeIgniter 3 application/helpers/lang_helper.php.
 *
 * CI3 loaded a `form_lang.php` file per language and named locales in full
 * ("english"/"arabic"). CI4 resolves `lang('Form.key')` against the request
 * locale, so this only has to pick the locale and keep it in the session.
 */

if (! function_exists('set_language')) {
    /**
     * Reads ?lang= (or the session), applies it as the request locale, and
     * returns the locale code that ended up in use.
     *
     * Accepts the CI3 spellings ("english", "arabic") as well as CI4 locale
     * codes ("en", "ar") so old bookmarks keep working.
     */
    function set_language(string $default = 'en'): string
    {
        $session = session();
        $request = service('request');

        $lang = $request->getGet('lang');

        if (! empty($lang)) {
            $session->set('site_lang', normalize_locale($lang));
        }

        $locale = $session->get('site_lang') ?? normalize_locale($default);

        service('request')->setLocale($locale);
        service('renderer')->setVar('rtl_class', $locale === 'ar' ? 'rtl' : '');

        return $locale;
    }
}

if (! function_exists('normalize_locale')) {
    /**
     * Maps the CodeIgniter 3 language names onto CodeIgniter 4 locale codes.
     */
    function normalize_locale(string $lang): string
    {
        $map = [
            'english' => 'en',
            'arabic'  => 'ar',
        ];

        $lang = $map[strtolower($lang)] ?? strtolower($lang);

        return in_array($lang, config('App')->supportedLocales, true) ? $lang : 'en';
    }
}
