# WHMCS Cloudflare Turnstile Manager

A free and open-source WHMCS Addon Module that replaces Google reCAPTCHA with [Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/), a privacy-friendly and user-centric alternative.

## Features

- Updated for **WHMCS 9.0.2** and **PHP 8.2**.
- **Seamless Integration**: Works with standard WHMCS themes and custom themes.
- **Admin Dashboard**: Configuration interface directly inside WHMCS Addons.
- **Page Control**: Enable/Disable Turnstile specifically for:
  - Login Page
  - Registration Page
  - Password Reset
  - Contact Us
  - Support Ticket Submission
  - Checkout Page
- **Checkout-Only Cart Logic**:
  - Does **not** display on `cart.php?a=view`
  - Does display on `cart.php?a=checkout`
- **Theme Support**: Choose between `Auto`, `Light`, or `Dark` widgets.
- **Advanced Selectors**: Define custom selectors to inject the widget into supported forms without editing template files.
- **Smarty Tag Support**: Use `{display_turnstile}` in your `.tpl` files for manual placement.
- **Duplicate Injection Protection**: Prevents the widget from being inserted multiple times on dynamically updated pages such as WHMCS checkout.
- **WHMCS 9 Compatibility Improvements**:
  - Improved page detection
  - More resilient checkout injection logic
  - Better handling for dynamic checkout rendering

## Compatibility

- Tested target: **WHMCS 9.0.2**
- Runtime target: **PHP 8.2**
- Recommended environment: **PHP 8.2.x** with current ionCube Loader compatible with your WHMCS installation

## Installation

1. Download the module archive.
2. Upload the folder `megabre_turnstile` to your WHMCS installation at:
   `/modules/addons/megabre_turnstile/`
3. Log in to your WHMCS Admin Area.
4. Go to **System Settings > Addon Modules**.
5. Find **Cloudflare Turnstile Manager** and click **Activate**.
6. Click **Configure** to grant access permissions to your admin role group.

## Configuration

1. Go to **Addons > Cloudflare Turnstile Manager**.
2. Enter your Cloudflare **Site Key** and **Secret Key**.
3. Choose the pages where you want Turnstile to appear.
4. For checkout, the module is designed to appear on:
   - `cart.php?a=checkout`
   and not on:
   - `cart.php?a=view`
5. (Optional) If using a custom theme that is not auto-detected, enter a custom selector for the submit/checkout button in the **Advanced: Custom Selectors** section.
6. Click **Save Configuration**.

## Admin Area Notes

The admin wording has been updated to reflect current behavior:

- **Enable on Checkout**
- **Checkout Selector**

For backward compatibility, some internal setting keys may still use older names, but the admin area now reflects the intended checkout-only behavior.

## Important Note

To avoid conflicts, disable the default WHMCS Captcha:

1. Go to **System Settings > General Settings > Security**
2. Set **Captcha Form Protection** > **Captcha Type** to **Always Off**

This module may attempt to hide legacy captcha output where possible, but disabling the built-in WHMCS captcha is strongly recommended.

## Manual Usage (Developers)

If you prefer to place the widget manually in your template files, you can use the Smarty tag:

```html
<form method="post" action="login.php">
    ...
    {display_turnstile}
    <button type="submit">Login</button>
</form>