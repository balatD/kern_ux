<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\SourceKeyword;
use TYPO3\CMS\Core\Type\Map;

/*
 * What this extension's own assets need, and nothing else.
 *
 * TYPO3 builds the frontend policy from an empty one and lets extensions contribute, so
 * a site that switches on `security.frontend.enforceContentSecurityPolicy` gets whatever
 * the installed extensions declare. Without this file a project with a restrictive
 * baseline would have to rediscover, one blocked request at a time, that KERN needs its
 * stylesheet, its two scripts and its self-hosted fonts.
 *
 * Extend, never Reduce. gsb_core narrows core's defaults - it can, because it is a
 * distribution and owns the whole site. A reusable extension that quietly removed a
 * source from an integrator's policy would be a bug, and one they would debug in the
 * wrong place.
 *
 * Everything here is 'self' because that is the whole point of the design: the KERN
 * distribution is fetched at install time and served from the site, the fonts with it,
 * and the two scripts are files rather than inline handlers. There is no CDN and no
 * 'unsafe-inline' to ask for. Public-sector sites generally cannot make external
 * requests at all, which is why it was built this way.
 */
return Map::fromEntries([
    Scope::frontend(),
    new MutationCollection(
        // navigation.js and dialog.js.
        new Mutation(MutationMode::Extend, Directive::ScriptSrcElem, SourceKeyword::self),
        // kern.min.css, kernt3.css, rte.css and the font stylesheets.
        new Mutation(MutationMode::Extend, Directive::StyleSrcElem, SourceKeyword::self),
        // Fira Sans and Noto Sans, self-hosted as woff2.
        new Mutation(MutationMode::Extend, Directive::FontSrc, SourceKeyword::self),
        // Logos, content images and the image derivatives TYPO3 generates.
        new Mutation(MutationMode::Extend, Directive::ImgSrc, SourceKeyword::self),
        // The media content block, which plays uploaded files rather than embeds.
        new Mutation(MutationMode::Extend, Directive::MediaSrc, SourceKeyword::self),
    ),
]);
