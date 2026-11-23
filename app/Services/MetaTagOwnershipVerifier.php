<?php

namespace App\Services;

use App\Exceptions\WebsiteAuditException;
use DOMDocument;
use DOMXPath;

class MetaTagOwnershipVerifier
{
    public const META_NAME = 'leadradar-verification';

    public function __construct(private WebsiteAuditService $websiteAuditService) {}

    /**
     * Check the site's homepage for <meta name="leadradar-verification" content="{token}">.
     *
     * @throws WebsiteAuditException when the homepage cannot be fetched safely.
     */
    public function verify(string $websiteUrl, string $token): bool
    {
        $html = $this->websiteAuditService->fetchPublicPageHtml($websiteUrl);

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ((new DOMXPath($document))->query('//meta[@name and @content]') as $meta) {
            if (strtolower(trim($meta->getAttribute('name'))) === self::META_NAME
                && hash_equals($token, trim($meta->getAttribute('content')))) {
                return true;
            }
        }

        return false;
    }
}
