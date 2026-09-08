<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Session\Session;
use Psr\Log\LoggerInterface;

/**
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 *
 * Extend this class in any new controllers:
 * ```
 *     class Home extends BaseController
 * ```
 *
 * For security, be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Helpers loaded for every controller.
     *
     * The CodeIgniter 3 project autoloaded url, form, auth, lang and language
     * in application/config/autoload.php; `language` is built into CI4, so the
     * remaining four are listed here.
     *
     * @var list<string>
     */
    protected $helpers = ['url', 'form', 'auth', 'lang'];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    protected Session $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Load here all helpers you want to be available in your controllers that extend BaseController.
        // Caution: Do not put the this below the parent::initController() call below.
        // $this->helpers = ['form', 'url'];

        // Caution: Do not edit this line.
        parent::initController($request, $response, $logger);

        // CI3 autoloaded the session library; CI4 gets it from the service.
        $this->session = service('session');

        // Picks the locale from ?lang= or the session (app/Helpers/lang_helper.php).
        set_language();
    }
}
