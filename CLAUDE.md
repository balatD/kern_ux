# EXT:kern_ux — working conventions

`balatd/kern-ux` brings the KERN UX-Standard to TYPO3 13.4 and 14.3. A layer of native
Fluid Components is the **single source of KERN markup**; Content Blocks, the `ext:form`
theme and the page templates all map data onto those same components, because KERN's
accessibility guarantees hang off concrete classes and ARIA attributes rather than off
anything a renderer could infer.

State: **alpha**. Public interfaces may change — but see *Breaking changes* below, because
much of the surface is stored in databases and project files this extension does not own.

## Toolchain: everything runs in DDEV

There is **no `php` and no `composer` on the host.** The repo is bind-mounted at
`/var/www/kern_ux` inside the DDEV web container, so host edits are visible immediately
with no sync step.

```bash
ddev start                                     # PHP 8.3, pdo_sqlite, Composer 2
ddev exec -d /var/www/kern_ux vendor/bin/phpstan analyse
ddev exec -d /var/www/kern_ux vendor/bin/php-cs-fixer fix --dry-run --diff
ddev exec -d /var/www/kern_ux vendor/bin/phpunit -c Build/phpunit/UnitTests.xml
ddev exec -d /var/www/kern_ux env typo3DatabaseDriver=pdo_sqlite \
    vendor/bin/phpunit -c Build/phpunit/FunctionalTests.xml
```

Two traps:

- **`ddev exec` re-serialises argv into a double-quoted string.** So a `$` expands on the
  host and a `|` is read as a pipe by the container's shell. Pass environment through
  `ddev exec … env VAR=val <cmd>`, and put anything with metacharacters in a script file
  invoked as `ddev exec … bash <path>`.
- **`--filter` with an alternation silently half-applies here.**
  `--filter 'ComponentCatalogTest|TemplateSyntaxTest'` ran 253 of 514 functional tests
  instead of 4. Filter on **one** class name, or not at all.

`Build/Scripts/runTests.sh -t 13|14 -s all` is the supported cross-major entry point — a
bare phpunit only tests the last-installed major. Note its header comment claims it
detects DDEV; it does not, so run it as
`ddev exec -d /var/www/kern_ux Build/Scripts/runTests.sh …`. It rewrites `composer.json`
and runs `composer update -W`, so never run two at once.

The KERN distribution is fetched, not committed:
`ddev exec -d /var/www/kern_ux vendor/bin/typo3 kern-ux:assets:install` (pinned 2.7.2,
SHA-512 verified, no CDN — public-sector sites generally cannot accept external requests).

## Components

Live at `Resources/Private/Components/<Group>/<Name>/<Name>.html` and resolve as
`<k:group.name>`. 40 today: 15 Atom, 16 Molecule, 9 Organism. The `k` and `kux` namespaces
are registered globally.

Four authoring conventions. **Only the first two are enforced by tests** — the others are
just as binding:

1. **Chain root-level tags** — `/><f:argument …/><f:variable …/>`. Any text node between
   them lands in the output.
2. **No trailing newline.** A final newline is a root-level text node, so an inline
   component renders "Status: X ." instead of "Status: X.". Same for newlines around an
   inline `<f:slot />`.
3. **No union types in `<f:argument type="…">`.** TYPO3 13 ships Fluid 4.6, which cannot
   parse one — use `type="mixed"`, as `Card`'s `media` argument does.
4. **Semantics separate from appearance.** `level` and `appearance` are two arguments; see
   `atom.heading` and `molecule.contentHeader`.

Match the house style in `Atom/Badge/Badge.html`: `type="bool"` (not `boolean`),
`default="{false}"`, a `description=` on every non-obvious argument, an `<f:comment>`
recording the KERN-specific *why*, and `<f:spaceless>` around the markup.

**A new component must get an entry in `Configuration/Styleguide/Examples.yaml` in the
same commit.** `ComponentCatalogTest` asserts a 1:1 match with the on-disk tree, and the
gallery doubles as the usage documentation.

**Never invent a `kern-*` class.** KERN documentation, examples and Figma frames are not
evidence — only a selector in the fetched `Resources/Public/Vendor/KernUx/kern.css` is.
`Badge.html` records why: KERN's own badge example writes `kern-icon--sm`, which 2.7.2
does not define at all. Classes KERN does not ship live in
`Resources/Public/Css/kernt3.css` under the `kernt3-` prefix, with a comment naming the
gap.

## Content Blocks

`ContentBlocks/ContentElements/<name>/` with `config.yaml`, `assets/icon.svg`,
`language/labels.xlf` + `language/de.labels.xlf`, `templates/frontend.html`,
`templates/backend-preview.html`. Shared field groups live in
`ContentBlocks/Basics/KernUx/{Heading,Spacing}.yaml`.

Every `backend-preview.html` needs `<f:layout name="Preview" />` and exactly one
`<f:section name="Content">`. Otherwise the editor sees each preview three times — with no
error and no log entry.

## Tests

`AbstractComponentTestCase` is the base for markup tests. They are the KERN-conformance
contract: assert the **exact** markup, because the accessibility guarantees hang off
specific classes and ARIA attributes.

```php
final class CardTest extends AbstractComponentTestCase
{
    #[Test]
    public function keepsTheBodyWrapperAroundPlainText(): void
    {
        $rendered = $this->renderSource('<k:molecule.card title="X" text="Y" />');

        self::assertStringContainsString('<p class="kern-body">', $rendered);
        self::assertNoStrayWhitespace($rendered);
    }
}
```

`final class`, a class docblock explaining why the markup is a *contract* rather than an
implementation detail, `#[Test]` with long behavioural method names, and
`assertNoStrayWhitespace()` in every method that renders. For branch-free atoms prefer the
`SimpleAtomsTest` shape: `#[DataProvider]` + `assertSame()` on the full markup.

Both phpunit configs set `failOnDeprecation`, `failOnNotice`, `failOnRisky` and
`failOnWarning` to `true`. A deprecation is a red build, and the fix is never to edit the
config.

## Language

English source XLIFF plus a German `de.*.xlf` counterpart, both halves always —
`LanguageCoverageTest` fails on either one missing. English is the source because a German
`<source>` made an English page on TYPO3 13 render German: TYPO3 treats `en` as the
default key and reads `<source>` directly.

## Breaking changes

Renaming any of these breaks integrators silently, because the value lives in their
database or project config, not here: `k:` tag and argument names (a changed *default* is
worst — no error, different output everywhere); the `Group/Name/Name.html` path shape
(integrator overrides via `EXTCONF.kern_ux.componentRootPaths` resolve by path);
`lib.kernUx.*` and `lib.contentElement`; **`colPos` 0=main, 1=hero, 2=aside, 3=teaser**;
backend layout names; the 19 `kernUx.*` setting keys; `tx_kernux_*` columns; the
`kern-ux/<name>` CTypes; the 57 `kernt3-*` classes (they look private, but projects
override them); XLIFF trans-unit ids; and the `data-kernt3-*` JS hooks.

`lib.contentElement` deserves its own warning: this extension replaces
`fluid_styled_content`, so without it **every** Extbase plugin — the form plugin included
— renders an empty string with no error.

`ext_emconf.php` is kept in sync with `composer.json` **by hand**. Change one and change
the other in the same commit.

## Commits

Conventional commits, with the component or area as scope:
`fix(header): render the Kopfzeile inside the banner landmark`. Subject ≤72 chars,
lowercase imperative, no trailing period. The body is one paragraph of at most three
sentences: what was wrong, the mechanism, and why this shape rather than the obvious
alternative.

## Known trap: the `typo3 setup` welcome page

Its TypoScript overrides everything a Site Set provides, differently per major — v13
writes a `sys_template` record with `clear = 3`; v14 writes
`config/sites/<id>/setup.typoscript`, which loads after the sets. Remove it before the
first page hit. `.ddev/scripts/install-typo3.sh` already does.
