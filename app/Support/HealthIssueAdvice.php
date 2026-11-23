<?php

namespace App\Support;

/**
 * Plain-language precautions shown next to each health-check finding.
 */
final class HealthIssueAdvice
{
    private const ADVICE = [
        'horizontal_overflow' => [
            'title' => 'Page is wider than the screen',
            'fix' => 'Find the listed elements and give them max-width: 100% (or a percentage width) at this breakpoint. In Webflow, check fixed pixel widths, negative margins and sliders/carousels on the tablet and mobile views.',
        ],
        'browser_navigation_failed' => [
            'title' => 'Page did not load',
            'fix' => 'Open the page yourself. If it is slow or down, check hosting status, large images and third-party scripts that block loading.',
        ],
        'browser_http_error' => [
            'title' => 'Page returns an error status',
            'fix' => 'Restore the page or add a 301 redirect to its replacement, and update any menu or button that still links to it.',
        ],
        'browser_javascript_error' => [
            'title' => 'JavaScript error on the page',
            'fix' => 'A script on the page crashed, so forms, sliders or menus that depend on it may not work. Check custom code embeds and the order in which libraries are loaded.',
        ],
        'browser_resource_failed' => [
            'title' => 'Image, CSS or script failed to load',
            'fix' => 'Re-upload or replace the missing file, or remove the reference to it. Missing CSS/JS can break the layout or features.',
        ],
        'broken_link' => [
            'title' => 'Broken link',
            'fix' => 'Update the link to the correct address, or remove it. For pages you deleted, add a 301 redirect to the closest live page.',
        ],
        'internal_page_unavailable' => [
            'title' => 'Internal page could not be loaded',
            'fix' => 'Check that the page is published and not redirecting in a loop. Fix or remove links that point to it.',
        ],
        'broken_page_anchors' => [
            'title' => 'In-page link points nowhere',
            'fix' => 'Links like #section must match an element ID on the same page. Rename the ID or update the link.',
        ],
        'mobile_viewport_missing' => [
            'title' => 'Mobile viewport tag missing',
            'fix' => 'Add <meta name="viewport" content="width=device-width, initial-scale=1"> to the page head.',
        ],
        'images_missing_alt' => [
            'title' => 'Images without alt text',
            'fix' => 'Add a short description (alt text) to each image. It helps SEO and screen-reader users.',
        ],
        'https_not_used' => [
            'title' => 'Page is not on HTTPS',
            'fix' => 'Enable SSL for the domain and redirect all HTTP traffic to HTTPS.',
        ],
        'missing_hsts' => [
            'title' => 'HSTS header missing',
            'fix' => 'Send Strict-Transport-Security: max-age=31536000 so browsers always use HTTPS. In Webflow, enable "Enable HSTS" under Site settings → Publishing → Advanced.',
        ],
        'missing_csp' => [
            'title' => 'Content-Security-Policy missing',
            'fix' => 'A CSP limits which scripts can run and reduces the damage of an injected script. Add it at your CDN/proxy (for example Cloudflare) if your host does not support custom headers.',
        ],
        'missing_nosniff' => [
            'title' => 'X-Content-Type-Options missing',
            'fix' => 'Send X-Content-Type-Options: nosniff from the server or CDN.',
        ],
        'missing_frame_protection' => [
            'title' => 'Clickjacking protection missing',
            'fix' => 'Send X-Frame-Options: SAMEORIGIN or a CSP frame-ancestors rule so other sites cannot embed your pages in a hidden frame.',
        ],
        'possible_defacement_text' => [
            'title' => 'Possible defacement text',
            'fix' => 'Open the page immediately. If it shows hacker messages, change all admin/CMS passwords, enable two-factor login, restore a clean backup and review who has editor access.',
        ],
        'spam_content' => [
            'title' => 'Spam content found',
            'fix' => 'Search the site for the listed words. If you did not add them, the site may be hacked: change passwords, enable two-factor login, remove the injected content and check custom code embeds.',
        ],
        'hidden_external_links' => [
            'title' => 'Hidden links to other sites',
            'fix' => 'Hidden outbound links are a classic SEO-spam hack. Remove them, then review custom code, CMS items and editor accounts for anything you did not add.',
        ],
        'obfuscated_script' => [
            'title' => 'Encoded / packed script',
            'fix' => 'Find the inline script in the page source. If nobody on your team added it, remove it and rotate CMS and hosting passwords.',
        ],
        'offsite_redirect' => [
            'title' => 'Page tried to send visitors to another site',
            'fix' => 'Unexpected redirects are a common sign of malware. Check custom code, tag manager tags and recently added scripts.',
        ],
        'ssl_invalid' => [
            'title' => 'SSL certificate problem',
            'fix' => 'Renew or reissue the certificate for this exact domain. Browsers show a security warning until it is fixed.',
        ],
        'ssl_expiring' => [
            'title' => 'SSL certificate expiring soon',
            'fix' => 'Confirm automatic renewal is working with your host or CDN, or renew the certificate manually before it expires.',
        ],
    ];

    public static function title(string $code): string
    {
        return self::ADVICE[$code]['title'] ?? ucfirst(str_replace('_', ' ', $code));
    }

    public static function fix(string $code): ?string
    {
        return self::ADVICE[$code]['fix'] ?? null;
    }
}
