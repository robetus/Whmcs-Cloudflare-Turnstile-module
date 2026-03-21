<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

function megabre_turnstile_config()
{
    return [
        'name' => 'Cloudflare Turnstile Manager',
        'description' => 'Replaces legacy CAPTCHA output with Cloudflare Turnstile and adds WHMCS 9 / PHP 8.2-safe handling.',
        'author' => 'Megabre',
        'language' => 'english',
        'version' => '1.2.0',
        'fields' => [
            'site_key' => ['FriendlyName' => 'Site Key', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'secret_key' => ['FriendlyName' => 'Secret Key', 'Type' => 'password', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'theme' => ['FriendlyName' => 'Theme', 'Type' => 'dropdown', 'Options' => 'auto,light,dark', 'Default' => 'auto', 'Description' => 'Managed via main interface'],
            'enable_login' => ['FriendlyName' => 'Enable on Login', 'Type' => 'yesno', 'Description' => 'Managed via main interface'],
            'enable_register' => ['FriendlyName' => 'Enable on Register', 'Type' => 'yesno', 'Description' => 'Managed via main interface'],
            'enable_pwreset' => ['FriendlyName' => 'Enable on Password Reset', 'Type' => 'yesno', 'Description' => 'Managed via main interface'],
            'enable_contact' => ['FriendlyName' => 'Enable on Contact', 'Type' => 'yesno', 'Description' => 'Managed via main interface'],
            'enable_ticket' => ['FriendlyName' => 'Enable on Ticket Submit', 'Type' => 'yesno', 'Description' => 'Managed via main interface'],
            'enable_cart' => ['FriendlyName' => 'Enable on Shopping Cart', 'Type' => 'yesno', 'Description' => 'Managed via main interface'],
            'custom_login_sel' => ['FriendlyName' => 'Login Selector', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'custom_register_sel' => ['FriendlyName' => 'Register Selector', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'custom_pwreset_sel' => ['FriendlyName' => 'PW Reset Selector', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'custom_contact_sel' => ['FriendlyName' => 'Contact Selector', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'custom_ticket_sel' => ['FriendlyName' => 'Ticket Selector', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
            'custom_cart_sel' => ['FriendlyName' => 'Cart Selector', 'Type' => 'text', 'Size' => '50', 'Description' => 'Managed via main interface'],
        ],
    ];
}

function megabre_turnstile_activate()
{
    return ['status' => 'success', 'description' => 'Cloudflare Turnstile Manager activated successfully.'];
}

function megabre_turnstile_deactivate()
{
    return ['status' => 'success', 'description' => 'Cloudflare Turnstile Manager deactivated successfully.'];
}

function megabre_turnstile_admin_escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function megabre_turnstile_output($vars)
{
    $moduleName = 'megabre_turnstile';
    $moduleLink = isset($vars['modulelink']) ? (string) $vars['modulelink'] : 'addonmodules.php?module=megabre_turnstile';
    $validSettings = [
        'site_key',
        'secret_key',
        'theme',
        'enable_login',
        'enable_register',
        'enable_pwreset',
        'enable_contact',
        'enable_ticket',
        'enable_cart',
        'custom_login_sel',
        'custom_register_sel',
        'custom_pwreset_sel',
        'custom_contact_sel',
        'custom_ticket_sel',
        'custom_cart_sel',
    ];

    if (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string) $_SERVER['REQUEST_METHOD']) === 'POST' && ($_POST['action'] ?? '') === 'save') {
        foreach ($validSettings as $setting) {
            $value = isset($_POST[$setting]) ? trim((string) $_POST[$setting]) : '';

            if (strpos($setting, 'enable_') === 0) {
                $value = $value === 'on' ? 'on' : '';
            }

            if ($setting === 'theme' && !in_array($value, ['auto', 'light', 'dark'], true)) {
                $value = 'auto';
            }

            Capsule::table('tbladdonmodules')->updateOrInsert(
                ['module' => $moduleName, 'setting' => $setting],
                ['value' => $value]
            );
        }

        echo '<div class="alert alert-success">Settings saved successfully.</div>';
    }

    $settings = [];
    foreach ($validSettings as $key) {
        $value = Capsule::table('tbladdonmodules')
            ->where('module', $moduleName)
            ->where('setting', $key)
            ->value('value');
        $settings[$key] = is_string($value) ? $value : '';
    }

    echo '<style>
        .megabre-card { background:#fff; padding:24px; border-radius:8px; border:1px solid #dfe3e8; margin-bottom:20px; box-shadow:0 2px 8px rgba(15,23,42,.04); }
        .megabre-card h3 { margin:0 0 18px; padding-bottom:12px; border-bottom:1px solid #eef2f7; color:#1f2937; font-size:18px; }
        .megabre-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:20px; }
        .megabre-field { margin-bottom:16px; }
        .megabre-field label { display:block; font-weight:600; margin-bottom:8px; color:#374151; }
        .megabre-field input[type="text"], .megabre-field input[type="password"], .megabre-field select { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; font-size:14px; }
        .megabre-field input[type="text"]:focus, .megabre-field input[type="password"]:focus, .megabre-field select:focus { border-color:#2563eb; outline:none; box-shadow:0 0 0 3px rgba(37,99,235,.12); }
        .megabre-help { color:#6b7280; font-size:12px; margin-top:6px; }
        .megabre-toggle-row { display:flex; align-items:center; justify-content:space-between; gap:16px; padding:12px 0; border-bottom:1px solid #f3f4f6; }
        .megabre-toggle-row:last-child { border-bottom:none; }
        .megabre-switch { position:relative; display:inline-block; width:46px; height:26px; }
        .megabre-switch input { opacity:0; width:0; height:0; }
        .megabre-slider { position:absolute; inset:0; cursor:pointer; background:#cbd5e1; transition:.2s; border-radius:999px; }
        .megabre-slider:before { position:absolute; content:""; height:20px; width:20px; left:3px; bottom:3px; background:#fff; transition:.2s; border-radius:50%; }
        .megabre-switch input:checked + .megabre-slider { background:#22c55e; }
        .megabre-switch input:checked + .megabre-slider:before { transform:translateX(20px); }
        .megabre-actions { margin-top:20px; display:flex; justify-content:flex-end; }
        .megabre-btn { background:#2563eb; color:#fff; border:none; border-radius:6px; padding:12px 20px; font-size:14px; font-weight:600; cursor:pointer; }
        .megabre-btn:hover { background:#1d4ed8; }
        .megabre-note { margin:0 0 18px; color:#4b5563; }
        @media (max-width: 900px) { .megabre-grid { grid-template-columns:1fr; } }
    </style>';

    echo '<form method="post" action="' . megabre_turnstile_admin_escape($moduleLink) . '">
        <input type="hidden" name="action" value="save">

        <div class="megabre-card">
            <h3>API Configuration</h3>
            <p class="megabre-note">Built for WHMCS 9.x and PHP 8.2. Turnstile is intended for the checkout step only on cart.php?a=checkout. For Nexus Cart or a custom theme, you can override the default selectors below.</p>
            <div class="megabre-grid">
                <div class="megabre-field">
                    <label for="megabre-site-key">Site Key</label>
                    <input id="megabre-site-key" type="text" name="site_key" value="' . megabre_turnstile_admin_escape($settings['site_key']) . '" placeholder="0x4AAAAAA..." autocomplete="off">
                </div>
                <div class="megabre-field">
                    <label for="megabre-secret-key">Secret Key</label>
                    <input id="megabre-secret-key" type="password" name="secret_key" value="' . megabre_turnstile_admin_escape($settings['secret_key']) . '" placeholder="0x4AAAAAA..." autocomplete="off">
                </div>
            </div>
            <div class="megabre-field" style="max-width:220px;">
                <label for="megabre-theme">Theme</label>
                <select id="megabre-theme" name="theme">
                    <option value="auto" ' . ($settings['theme'] === 'auto' ? 'selected' : '') . '>Auto</option>
                    <option value="light" ' . ($settings['theme'] === 'light' ? 'selected' : '') . '>Light</option>
                    <option value="dark" ' . ($settings['theme'] === 'dark' ? 'selected' : '') . '>Dark</option>
                </select>
            </div>
        </div>

        <div class="megabre-grid">
            <div class="megabre-card">
                <h3>Page Visibility Settings</h3>
                <div class="megabre-toggle-row"><span>Enable on Login</span><label class="megabre-switch"><input type="checkbox" name="enable_login" ' . ($settings['enable_login'] === 'on' ? 'checked' : '') . '><span class="megabre-slider"></span></label></div>
                <div class="megabre-toggle-row"><span>Enable on Register</span><label class="megabre-switch"><input type="checkbox" name="enable_register" ' . ($settings['enable_register'] === 'on' ? 'checked' : '') . '><span class="megabre-slider"></span></label></div>
                <div class="megabre-toggle-row"><span>Enable on Password Reset</span><label class="megabre-switch"><input type="checkbox" name="enable_pwreset" ' . ($settings['enable_pwreset'] === 'on' ? 'checked' : '') . '><span class="megabre-slider"></span></label></div>
                <div class="megabre-toggle-row"><span>Enable on Contact</span><label class="megabre-switch"><input type="checkbox" name="enable_contact" ' . ($settings['enable_contact'] === 'on' ? 'checked' : '') . '><span class="megabre-slider"></span></label></div>
                <div class="megabre-toggle-row"><span>Enable on Ticket Submit</span><label class="megabre-switch"><input type="checkbox" name="enable_ticket" ' . ($settings['enable_ticket'] === 'on' ? 'checked' : '') . '><span class="megabre-slider"></span></label></div>
                <div class="megabre-toggle-row"><span>Enable on Checkout</span><label class="megabre-switch"><input type="checkbox" name="enable_cart" ' . ($settings['enable_cart'] === 'on' ? 'checked' : '') . '><span class="megabre-slider"></span></label></div>
            </div>

            <div class="megabre-card">
                <h3>Advanced: Custom Selectors</h3>
                <p class="megabre-help" style="margin-bottom:16px;">Leave these empty to use auto-detection. Fill them only if your custom theme or modified WHMCS 9 checkout needs a specific checkout submit button selector.</p>
                <div class="megabre-field"><label>Login Form Selector</label><input type="text" name="custom_login_sel" value="' . megabre_turnstile_admin_escape($settings['custom_login_sel']) . '"></div>
                <div class="megabre-field"><label>Register Form Selector</label><input type="text" name="custom_register_sel" value="' . megabre_turnstile_admin_escape($settings['custom_register_sel']) . '"></div>
                <div class="megabre-field"><label>Password Reset Selector</label><input type="text" name="custom_pwreset_sel" value="' . megabre_turnstile_admin_escape($settings['custom_pwreset_sel']) . '"></div>
                <div class="megabre-field"><label>Contact Form Selector</label><input type="text" name="custom_contact_sel" value="' . megabre_turnstile_admin_escape($settings['custom_contact_sel']) . '"></div>
                <div class="megabre-field"><label>Ticket Form Selector</label><input type="text" name="custom_ticket_sel" value="' . megabre_turnstile_admin_escape($settings['custom_ticket_sel']) . '"></div>
                <div class="megabre-field"><label>Checkout Selector</label><input type="text" name="custom_cart_sel" value="' . megabre_turnstile_admin_escape($settings['custom_cart_sel']) . '"></div>
            </div>
        </div>

        <div class="megabre-actions">
            <button type="submit" class="megabre-btn">Save Configuration</button>
        </div>
    </form>';
}
