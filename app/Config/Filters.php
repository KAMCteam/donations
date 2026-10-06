<?php

namespace Config;

use App\Filters\AdminFilter;
use App\Filters\AuthFilter;
use App\Filters\RoleFilter;
use App\Filters\TrailFilter;
use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,

        // Signing in, and what a role may reach once it has. Put on routes in
        // Config\Routes rather than on URI patterns here: the routes file is
        // where it can be read next to the address it guards, and a route
        // added to a guarded group is guarded by being there.
        'auth'          => AuthFilter::class,
        'role'          => RoleFilter::class,
        // The permission over the role: the screens that look after the
        // register, open to a doctor or a coordinator who has it.
        'admin'         => AdminFilter::class,
        // Remembers the screen somebody was on, for the back arrows.
        'trail'         => TrailFilter::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',   // Web Page Caching
            'performance', // Performance Metrics
            'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array{
     *     before: array<string, array{except: list<string>|string}>|list<string>,
     *     after: array<string, array{except: list<string>|string}>|list<string>
     * }
     */
    public array $globals = [
        'before' => [
            // The `organ` guard was removed with the screens it protected: the
            // transplant UI carries its own login and programme picker and
            // keeps the choice in the `ui_organ` session key.
            //
            // Every form on every screen already emits csrf_field(); until the
            // delete buttons went in, nothing checked the token. A page on
            // another site could not read this one, but it could post to it,
            // and one of the things it can post to now removes a patient.
            //
            // `login` used to be excepted, from when the screen let anybody
            // through and there was nothing behind it worth forging. It is a
            // real sign-in now, so it is checked like every other post: the
            // token comes from the login page itself, so an honest attempt
            // always carries one.
            'csrf' => [],
        ],
        'after' => [
            // After every screen, because what it records is the screen that
            // was served. It writes a session key and nothing else.
            'trail',
        ],
    ];


    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];
}
