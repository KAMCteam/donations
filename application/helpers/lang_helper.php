<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('set_language')) {
    function set_language($default = 'english') {
        $CI =& get_instance();

        // Detect from URL or session
        $lang = $CI->input->get('lang');
        if ($lang) {
            $CI->session->set_userdata('site_lang', $lang);
        }
        $lang = $CI->session->userdata('site_lang') ?? $default;

        // Load language file
        $CI->lang->load('form', $lang);

        // Add RTL class if Arabic
        if ($lang === 'arabic') {
            $CI->rtl_class = 'rtl';
        } else {
            $CI->rtl_class = '';
        }

        return $lang;
    }
}
