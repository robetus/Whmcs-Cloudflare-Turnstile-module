<?php

use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

function megabre_turnstile_verify($response)
{
    $secretKey = Capsule::table('tbladdonmodules')->where('module', 'megabre_turnstile')->where('setting', 'secret_key')->value('value');
    if (!$secretKey || !$response) {
        return false;
    }

    $remoteIp = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secretKey,
        'response' => $response,
        'remoteip' => $remoteIp,
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $result = curl_exec($ch);
    if ($result === false) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);

    $json = json_decode($result, true);

    return is_array($json) && !empty($json['success']);
}

function megabre_turnstile_get_setting($name)
{
    return Capsule::table('tbladdonmodules')->where('module', 'megabre_turnstile')->where('setting', $name)->value('value');
}

function megabre_turnstile_is_enabled($pageSetting)
{
    return megabre_turnstile_get_setting($pageSetting) === 'on';
}

function megabre_turnstile_get_site_key()
{
    return megabre_turnstile_get_setting('site_key');
}

function megabre_turnstile_render_widget_html($siteKey, $theme)
{
    $safeSiteKey = htmlspecialchars((string) $siteKey, ENT_QUOTES, 'UTF-8');
    $safeTheme = htmlspecialchars((string) $theme, ENT_QUOTES, 'UTF-8');

    return '<div class="cf-turnstile" data-sitekey="' . $safeSiteKey . '" data-theme="' . $safeTheme . '" style="margin: 15px 0;"></div>';
}

function megabre_turnstile_js_before($selector, $html)
{
    return 'var _mtTarget=jQuery(' . json_encode((string) $selector) . ');if(_mtTarget.length){_mtTarget.first().before(' . json_encode((string) $html) . ');}';
}

/**
 * Register Smarty function: {display_turnstile}
 * Compatible with WHMCS 8.13.x.
 */
add_hook('ClientAreaPage', 1, function ($vars) {
    if (!isset($GLOBALS['smarty']) || !is_object($GLOBALS['smarty']) || !method_exists($GLOBALS['smarty'], 'registerPlugin')) {
        return;
    }

    $GLOBALS['smarty']->registerPlugin('function', 'display_turnstile', function ($params, $smarty) {
        $siteKey = megabre_turnstile_get_site_key();
        if (!$siteKey) {
            return '';
        }

        $theme = megabre_turnstile_get_setting('theme') ?: 'auto';

        return megabre_turnstile_render_widget_html($siteKey, $theme);
    });
});

/**
 * Inject Cloudflare Turnstile Script
 */
add_hook('ClientAreaHeadOutput', 1, function ($vars) {
    if (!megabre_turnstile_get_site_key()) {
        return;
    }

    return '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
});

/**
 * Inject Widget into Forms via Footer JS
 */
add_hook('ClientAreaFooterOutput', 1, function ($vars) {
    $siteKey = megabre_turnstile_get_site_key();
    if (!$siteKey) {
        return;
    }

    $templatefile = isset($vars['templatefile']) ? (string) $vars['templatefile'] : '';
    $filename = isset($vars['filename']) ? (string) $vars['filename'] : '';

    $theme = megabre_turnstile_get_setting('theme') ?: 'auto';
    $widgetHtml = megabre_turnstile_render_widget_html($siteKey, $theme);

    $css = '<style>
        .g-recaptcha, #google-recaptcha-domainchecker, .recaptcha-container { display: none !important; }
        div[class*="captcha"] { display: none !important; }
        .cf-turnstile { display: block !important; }
    </style>';

    $jsCode = '';

    if ($templatefile === 'login' && megabre_turnstile_is_enabled('enable_login')) {
        $custom = trim((string) megabre_turnstile_get_setting('custom_login_sel'));
        if ($custom !== '') {
            $jsCode .= megabre_turnstile_js_before($custom, $widgetHtml);
        } else {
            $jsCode .= 'if(jQuery(".megabre-login-wrap").length){var _mtBtn=jQuery(".megabre-login-wrap form button[type=\'submit\']");if(_mtBtn.length){_mtBtn.first().before(' . json_encode($widgetHtml) . ');}}else{var _mtDef=jQuery("form[action*=\'dologin\'] button[type=\'submit\']").closest("div.form-group, div.mb-3");if(_mtDef.length){_mtDef.first().before(' . json_encode($widgetHtml) . ');}}';
        }
    }

    if ($templatefile === 'clientregister' && megabre_turnstile_is_enabled('enable_register')) {
        $custom = trim((string) megabre_turnstile_get_setting('custom_register_sel'));
        if ($custom !== '') {
            $jsCode .= megabre_turnstile_js_before($custom, $widgetHtml);
        } else {
            $jsCode .= 'if(jQuery(".megabre-register-wrap").length){var _mtRegBtn=jQuery(".megabre-register-wrap form button[type=\'submit\']");if(_mtRegBtn.length){_mtRegBtn.first().before(' . json_encode($widgetHtml) . ');}}else{var _mtRegDef=jQuery("#btnRegister").closest("div.form-group, div.mb-3");if(_mtRegDef.length){_mtRegDef.first().before(' . json_encode($widgetHtml) . ');}}';
        }
    }

    if ($templatefile === 'password-reset-container' && megabre_turnstile_is_enabled('enable_pwreset')) {
        $custom = trim((string) megabre_turnstile_get_setting('custom_pwreset_sel'));
        if ($custom !== '') {
            $jsCode .= megabre_turnstile_js_before($custom, $widgetHtml);
        } else {
            $jsCode .= 'var _mtPw=jQuery("form[action*=\'pwreset\'] button[type=\'submit\']").closest("div");if(_mtPw.length){_mtPw.first().before(' . json_encode($widgetHtml) . ');}';
        }
    }

    if (($templatefile === 'supportticketsubmit-stepone' || $templatefile === 'supportticketsubmit-steptwo') && megabre_turnstile_is_enabled('enable_ticket')) {
        $custom = trim((string) megabre_turnstile_get_setting('custom_ticket_sel'));
        if ($custom !== '') {
            $jsCode .= megabre_turnstile_js_before($custom, $widgetHtml);
        } else {
            $jsCode .= 'var _mtTicket=jQuery("#openTicketSubmit").closest("p, div.form-group");if(_mtTicket.length){_mtTicket.first().before(' . json_encode($widgetHtml) . ');}';
        }
    }

    if ($templatefile === 'contact' && megabre_turnstile_is_enabled('enable_contact')) {
        $custom = trim((string) megabre_turnstile_get_setting('custom_contact_sel'));
        if ($custom !== '') {
            $jsCode .= megabre_turnstile_js_before($custom, $widgetHtml);
        } else {
            $jsCode .= 'var _mtContact=jQuery("form[action*=\'contact\'] button[type=\'submit\']").closest("p, div.text-center");if(_mtContact.length){_mtContact.first().before(' . json_encode($widgetHtml) . ');}';
        }
    }

    if ((strpos($templatefile, 'checkout') !== false || $filename === 'cart') && megabre_turnstile_is_enabled('enable_cart')) {
        $custom = trim((string) megabre_turnstile_get_setting('custom_cart_sel'));
        if ($custom !== '') {
            $jsCode .= megabre_turnstile_js_before($custom, $widgetHtml);
        } else {
            $jsCode .= 'var _mtCart=jQuery("#btnCompleteOrder").closest("div");if(_mtCart.length){_mtCart.first().before(' . json_encode($widgetHtml) . ');}';
        }
    }

    if ($jsCode !== '') {
        return $css . '<script>jQuery(function(){' . $jsCode . '});</script>';
    }
});

/**
 * Validation Hooks
 */
add_hook('UserLoginVerification', 1, function ($vars) {
    if (megabre_turnstile_is_enabled('enable_login')) {
        if (!isset($_POST['cf-turnstile-response']) || !megabre_turnstile_verify($_POST['cf-turnstile-response'])) {
            return 'Turnstile verification failed. Please try again.';
        }
    }
});

add_hook('ClientDetailsValidation', 1, function ($vars) {
    if (!isset($_SESSION['uid']) && megabre_turnstile_is_enabled('enable_register')) {
        if (!isset($_POST['cf-turnstile-response']) || !megabre_turnstile_verify($_POST['cf-turnstile-response'])) {
            return ['Turnstile verification failed.'];
        }
    }
});

add_hook('ShoppingCartValidateCheckout', 1, function ($vars) {
    if (megabre_turnstile_is_enabled('enable_cart')) {
        if (!isset($_POST['cf-turnstile-response']) || !megabre_turnstile_verify($_POST['cf-turnstile-response'])) {
            return 'Turnstile verification failed. Please try again.';
        }
    }
});

add_hook('TicketOpenValidation', 1, function ($vars) {
    if (megabre_turnstile_is_enabled('enable_ticket')) {
        if (!isset($_POST['cf-turnstile-response']) || !megabre_turnstile_verify($_POST['cf-turnstile-response'])) {
            return 'Turnstile verification failed.';
        }
    }
});

add_hook('ContactForm', 1, function ($vars) {
    if (megabre_turnstile_is_enabled('enable_contact')) {
        if (!isset($_POST['cf-turnstile-response']) || !megabre_turnstile_verify($_POST['cf-turnstile-response'])) {
            return 'Turnstile verification failed.';
        }
    }
});

add_hook('ClientAreaPagePasswordReset', 1, function ($vars) {
    if (!megabre_turnstile_is_enabled('enable_pwreset')) {
        return;
    }

    if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string) $_SERVER['REQUEST_METHOD']) !== 'POST') {
        return;
    }

    if (!isset($_POST['email'])) {
        return;
    }

    if (!isset($_POST['cf-turnstile-response']) || !megabre_turnstile_verify($_POST['cf-turnstile-response'])) {
        return [
            'errormessage' => 'Turnstile verification failed. Please try again.',
        ];
    }
});
