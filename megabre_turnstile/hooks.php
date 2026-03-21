<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function megabre_turnstile_get_setting(string $name): string
{
    $value = Capsule::table('tbladdonmodules')
        ->where('module', 'megabre_turnstile')
        ->where('setting', $name)
        ->value('value');

    return is_string($value) ? $value : '';
}

function megabre_turnstile_is_enabled(string $pageSetting): bool
{
    return megabre_turnstile_get_setting($pageSetting) === 'on';
}

function megabre_turnstile_get_site_key(): string
{
    return megabre_turnstile_get_setting('site_key');
}

function megabre_turnstile_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function megabre_turnstile_render_widget_html(string $siteKey, string $theme): string
{
    return sprintf(
        '<div class="cf-turnstile megabre-turnstile-widget" data-sitekey="%s" data-theme="%s" style="margin: 15px 0;"></div>',
        megabre_turnstile_escape($siteKey),
        megabre_turnstile_escape($theme)
    );
}

function megabre_turnstile_verify(?string $response): bool
{
    $secretKey = megabre_turnstile_get_setting('secret_key');
    if ($secretKey === '' || empty($response)) {
        return false;
    }

    $remoteIp = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';

    $ch = curl_init();
    if ($ch === false) {
        return false;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'secret' => $secretKey,
            'response' => $response,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    if (!is_string($result) || $result === '') {
        return false;
    }

    $json = json_decode($result, true);

    return is_array($json) && !empty($json['success']);
}

function megabre_turnstile_get_page_context(array $vars): array
{
    $action = '';
    if (isset($_GET['a'])) {
        $action = strtolower(trim((string) $_GET['a']));
    } elseif (isset($_POST['a'])) {
        $action = strtolower(trim((string) $_POST['a']));
    }

    return [
        'templatefile' => isset($vars['templatefile']) ? (string) $vars['templatefile'] : '',
        'filename' => isset($vars['filename']) ? (string) $vars['filename'] : '',
        'template' => isset($vars['template']) ? (string) $vars['template'] : '',
        'action' => $action,
    ];
}

function megabre_turnstile_should_inject(array $pageContext): bool
{
    $templatefile = $pageContext['templatefile'];
    $filename = $pageContext['filename'];
    $action = $pageContext['action'];

    if ($templatefile === 'login' && megabre_turnstile_is_enabled('enable_login')) {
        return true;
    }

    if (in_array($templatefile, ['clientregister', 'register'], true) && megabre_turnstile_is_enabled('enable_register')) {
        return true;
    }

    if (
        in_array($templatefile, ['password-reset-container', 'password-reset', 'pwreset'], true)
        && megabre_turnstile_is_enabled('enable_pwreset')
    ) {
        return true;
    }

    if (($templatefile === 'contact' || $filename === 'contact') && megabre_turnstile_is_enabled('enable_contact')) {
        return true;
    }

    if (
        in_array($templatefile, ['supportticketsubmit-stepone', 'supportticketsubmit-steptwo', 'submitticket'], true)
        && megabre_turnstile_is_enabled('enable_ticket')
    ) {
        return true;
    }

    if (
        ($action === 'checkout' || strpos($templatefile, 'checkout') !== false)
        && megabre_turnstile_is_enabled('enable_cart')
    ) {
        return true;
    }

    return false;
}

function megabre_turnstile_get_targets(array $pageContext): array
{
    $templatefile = $pageContext['templatefile'];
    $filename = $pageContext['filename'];
    $action = $pageContext['action'];
    $targets = [];

    if ($templatefile === 'login' && megabre_turnstile_is_enabled('enable_login')) {
        $custom = trim(megabre_turnstile_get_setting('custom_login_sel'));
        $targets[] = [
            'selector' => $custom !== '' ? $custom : 'form[action*="dologin"] button[type="submit"], form[action*="login"] button[type="submit"], #login',
            'mode' => 'before',
        ];
    }

    if (in_array($templatefile, ['clientregister', 'register'], true) && megabre_turnstile_is_enabled('enable_register')) {
        $custom = trim(megabre_turnstile_get_setting('custom_register_sel'));
        $targets[] = [
            'selector' => $custom !== '' ? $custom : '#btnRegister, form[action*="register"] button[type="submit"], form[action*="register"] input[type="submit"]',
            'mode' => 'before',
        ];
    }

    if (
        in_array($templatefile, ['password-reset-container', 'password-reset', 'pwreset'], true)
        && megabre_turnstile_is_enabled('enable_pwreset')
    ) {
        $custom = trim(megabre_turnstile_get_setting('custom_pwreset_sel'));
        $targets[] = [
            'selector' => $custom !== '' ? $custom : 'form[action*="pwreset"] button[type="submit"], form[action*="password/reset"] button[type="submit"], form[action*="pwreset"] input[type="submit"]',
            'mode' => 'before',
        ];
    }

    if (($templatefile === 'contact' || $filename === 'contact') && megabre_turnstile_is_enabled('enable_contact')) {
        $custom = trim(megabre_turnstile_get_setting('custom_contact_sel'));
        $targets[] = [
            'selector' => $custom !== '' ? $custom : 'form[action*="contact"] button[type="submit"], form[action*="contact"] input[type="submit"]',
            'mode' => 'before',
        ];
    }

    if (
        in_array($templatefile, ['supportticketsubmit-stepone', 'supportticketsubmit-steptwo', 'submitticket'], true)
        && megabre_turnstile_is_enabled('enable_ticket')
    ) {
        $custom = trim(megabre_turnstile_get_setting('custom_ticket_sel'));
        $targets[] = [
            'selector' => $custom !== '' ? $custom : '#openTicketSubmit, form[action*="submitticket"] button[type="submit"], form[action*="submitticket"] input[type="submit"]',
            'mode' => 'before',
        ];
    }

    if (
        ($action === 'checkout' || strpos($templatefile, 'checkout') !== false)
        && megabre_turnstile_is_enabled('enable_cart')
    ) {
        $custom = trim(megabre_turnstile_get_setting('custom_cart_sel'));
        $targets[] = [
            'selector' => $custom !== '' ? $custom : '#btnCompleteOrder, button[type="submit"][name="checkout"], [data-role="complete-order"], .checkout-submit button[type="submit"], #frmCheckout button[type="submit"], form[action*="a=checkout"] button[type="submit"]',
            'mode' => 'before',
        ];
    }

    return $targets;
}

function megabre_turnstile_validation_error(bool $array = false)
{
    $message = 'Turnstile verification failed. Please try again.';

    return $array ? [$message] : $message;
}

add_hook('ClientAreaPage', 1, static function () {
    if (!isset($GLOBALS['smarty']) || !is_object($GLOBALS['smarty']) || !method_exists($GLOBALS['smarty'], 'registerPlugin')) {
        return;
    }

    $GLOBALS['smarty']->registerPlugin('function', 'display_turnstile', static function () {
        $siteKey = megabre_turnstile_get_site_key();
        if ($siteKey === '') {
            return '';
        }

        $theme = megabre_turnstile_get_setting('theme') ?: 'auto';

        return megabre_turnstile_render_widget_html($siteKey, $theme);
    });
});

add_hook('ClientAreaHeadOutput', 1, static function (array $vars) {
    $siteKey = megabre_turnstile_get_site_key();
    if ($siteKey === '') {
        return;
    }

    if (!megabre_turnstile_should_inject(megabre_turnstile_get_page_context($vars))) {
        return;
    }

    return <<<'HTML'
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<style>
.g-recaptcha,
#google-recaptcha-domainchecker,
.recaptcha-container,
[data-google-recaptcha],
[data-recaptcha],
div[class*="captcha"] .g-recaptcha {
    display: none !important;
}
.megabre-turnstile-container {
    display: inline-block;
    width: 100%;
    margin: 0;
}
.megabre-turnstile-widget {
    display: block !important;
}
</style>
HTML;
});

add_hook('ClientAreaFooterOutput', 1, static function (array $vars) {
    $siteKey = megabre_turnstile_get_site_key();
    if ($siteKey === '') {
        return;
    }

    $pageContext = megabre_turnstile_get_page_context($vars);
    if (!megabre_turnstile_should_inject($pageContext)) {
        return;
    }

    $targets = megabre_turnstile_get_targets($pageContext);
    if ($targets === []) {
        return;
    }

    $theme = megabre_turnstile_get_setting('theme') ?: 'auto';
    $widgetHtml = '<div class="megabre-turnstile-container">' . megabre_turnstile_render_widget_html($siteKey, $theme) . '</div>';

    $payload = [
        'widgetHtml' => $widgetHtml,
        'targets' => array_values($targets),
    ];

    return '<script>(function(){'
        . 'var config=' . json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';'
        . 'function ensureTurnstileBefore(target){'
        . 'if(!target||!target.parentNode){return false;}'
        . 'if(target.previousElementSibling&&target.previousElementSibling.classList&&target.previousElementSibling.classList.contains("megabre-turnstile-container")){return true;}'
        . 'var wrapper=document.createElement("div");wrapper.innerHTML=config.widgetHtml;var node=wrapper.firstChild;if(!node){return false;}target.parentNode.insertBefore(node,target);'
        . 'if(window.turnstile&&typeof window.turnstile.render==="function"){var widgets=node.querySelectorAll(".cf-turnstile");for(var i=0;i<widgets.length;i++){if(!widgets[i].firstChild){try{window.turnstile.render(widgets[i]);}catch(e){}}}}'
        . 'return true;'
        . '}'
        . 'function resolveTarget(selector){try{return document.querySelector(selector);}catch(e){return null;}}'
        . 'function runInject(){for(var i=0;i<config.targets.length;i++){var item=config.targets[i]||{};var target=resolveTarget(item.selector||"");if(target){ensureTurnstileBefore(target);}}}'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",runInject);}else{runInject();}'
        . 'var observer=new MutationObserver(function(){runInject();});observer.observe(document.documentElement,{childList:true,subtree:true});'
        . '})();</script>';
});

add_hook('UserLoginVerification', 1, static function () {
    if (!megabre_turnstile_is_enabled('enable_login')) {
        return;
    }

    if (!megabre_turnstile_verify($_POST['cf-turnstile-response'] ?? null)) {
        return megabre_turnstile_validation_error(false);
    }
});

add_hook('ClientDetailsValidation', 1, static function () {
    if (!isset($_SESSION['uid']) && megabre_turnstile_is_enabled('enable_register')) {
        if (!megabre_turnstile_verify($_POST['cf-turnstile-response'] ?? null)) {
            return megabre_turnstile_validation_error(true);
        }
    }
});

add_hook('ShoppingCartValidateCheckout', 1, static function () {
    if (!megabre_turnstile_is_enabled('enable_cart')) {
        return;
    }

    if (!megabre_turnstile_verify($_POST['cf-turnstile-response'] ?? null)) {
        return megabre_turnstile_validation_error(false);
    }
});

add_hook('TicketOpenValidation', 1, static function () {
    if (!megabre_turnstile_is_enabled('enable_ticket')) {
        return;
    }

    if (!megabre_turnstile_verify($_POST['cf-turnstile-response'] ?? null)) {
        return megabre_turnstile_validation_error(false);
    }
});

add_hook('ContactForm', 1, static function () {
    if (!megabre_turnstile_is_enabled('enable_contact')) {
        return;
    }

    if (!megabre_turnstile_verify($_POST['cf-turnstile-response'] ?? null)) {
        return megabre_turnstile_validation_error(false);
    }
});

add_hook('ClientAreaPagePasswordReset', 1, static function () {
    if (!megabre_turnstile_is_enabled('enable_pwreset')) {
        return;
    }

    $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string) $_SERVER['REQUEST_METHOD']) : 'GET';
    if ($requestMethod !== 'POST') {
        return;
    }

    if (!isset($_POST['email']) && !isset($_POST['answer']) && !isset($_POST['code'])) {
        return;
    }

    if (!megabre_turnstile_verify($_POST['cf-turnstile-response'] ?? null)) {
        return ['errormessage' => 'Turnstile verification failed. Please try again.'];
    }
});
