# Changelog

Notable changes per release, newest first. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

Releases are tagged `1.0.0-<stability>.<n>` while the extension metadata stays at `1.0.0`
in both `composer.json` and `ext_emconf.php`: the TER version field has no pre-release
syntax, so `state` in `ext_emconf.php` carries it instead.

## [Unreleased]

## [1.0.0-beta.1] - 2026-09-24

First beta. The public surface listed under *Breaking changes* in `CLAUDE.md` is now
expected to hold, and it is defended by tests rather than by intent. Two KERN gaps
closed, three accessibility defects fixed, and the package finally packaged.

### Added
- A **table** content block and `molecule.table`. KERN's `kern-table` was reachable only
  through the rich-text editor, which can set a class on the table element and nothing
  else — CKEditor writes no `scope` and no cell classes, so an RTE table was visually
  approximated rather than KERN conformant. The block uses the core table wizard, so an
  editor gets a real grid instead of a two-level collection.
- A **unit on a form field**, via the `kernUxPrefix` and `kernUxSuffix` form-element
  properties. `kern-input-group-text` had no way in before, so a value given in euros or
  kilometres had to say so in its label. The unit is always announced, in front of hint
  and error, because it qualifies the value rather than advising about it.
- The styleguide **reports which KERN component families nothing here implements**, and
  CI fails when that list changes unexpectedly. Answering that question used to mean
  reading 12,921 lines of `kern.css` by hand.
- `ExtensionManifestTest` derives the `ext_emconf.php` constraints from `composer.json`
  and fails when the two hand-synced manifests disagree.
- CI validates `composer.json`, and runs axe against **whole demo pages** — one per page
  template, three viewport and theme passes each — on every push to `main`. That mode had
  been a manual step, and it is the one that finds defects living *between* components.

### Fixed
- A **numbered list rendered as an unordered one**. The block chose its tag with an
  inline `f:if` whose condition is a comparison, which Fluid does not parse as a
  ViewHelper at all: it emitted the expression as literal text, so `atom.list` fell back
  to `<ul>` while still carrying `kern-list--number`. The result looked numbered and was
  announced as unordered.
- The **header announced the organisation twice**. The brand link carries its own
  `aria-label`, so the logo's `alt` contributed to no accessible name — it was simply
  read as content beside a visible title saying the same words. The logo is now
  decorative whenever the title is there.
- The **hero block emitted a stray newline** into the page, the only one of 24 to do so.
- `ext_emconf.php` no longer refuses `content_blocks` 2.5 and up, which `composer.json`
  has always allowed; a TER dash range cannot express the `^1.6 || ^2.4` disjunction, so
  it now spans the outer hull.
- `rte_ckeditor` is suggested in `ext_emconf.php` too. A non-Composer install was never
  told that the RTE preset needs it.
- The TYPO3 floor in `ext_emconf.php` was `13.4.19` while Composer and the
  `--prefer-lowest` CI leg both accept `13.4.0`.
- PHPStan was failing under TYPO3 13 and nobody saw it: CI's static job resolves highest,
  so it only ever analysed against Fluid 5.

### Changed
- The Composer dist tarball dropped from 2.46 MB to 1.08 MB. `.gitattributes` marks the
  development apparatus `export-ignore`; the documentation screenshots went with it, so
  their image references resolve on GitHub but not inside an installed copy.
- Test coverage roughly doubled: every content block is rendered rather than only parsed,
  the thirteen components that had no markup contract now have one, and the four
  commands, the demo installer and the table-of-contents query are covered for the first
  time.
- `ext-gd` is suggested, naming `kern-ux:demo:install` as what needs it.

### Known gaps
- `kern-dropdown` is not implemented. KERN's own stylesheet marks the region as still in
  development and not part of the framework, so freezing a tag name and its arguments
  against it would be promising something upstream has not. The coverage check reports it
  on every CI run.
- `kern-sr-only-mobile` is unimplemented; it belongs as an argument on `atom.button`.
- Rich-text, link and image fields cannot be exercised by the block tests, because all
  three need a frontend request. They are covered by static analysis of the templates and
  by the whole-page axe run.

## [1.0.0-alpha.2] - 2026-09-23

### Added
- The `openingHours` molecule; an `icon` argument plus `subline` and `date` on `card`;
  the location, quicklinks and service content blocks; a Landing page template with its
  backend layout.

### Fixed
- Accessibility hardening across the `ext:form` theme: error-summary links point at
  focusable controls, a required group is marked required on the fieldset, both honeypot
  shapes core defines render, the summary page lists every submitted value, and
  RTE-edited labels run through core's sanitize chain.
- The Kopfzeile moved inside the `banner` landmark, and every page declares a viewport
  meta tag.
- The RTE gained a body-scoped stylesheet for its editing view, because CKEditor's
  `contentsCss` prefixer left every `.ck-content` rule unmatched. Rich-text headings now
  sit on KERN's heading scale, so the dropdown is a semantic choice rather than a visual
  one.

## [1.0.0-alpha] - 2026-08-28

First public pre-release: the component layer, the content blocks, the `ext:form` theme
and the page templates, built against KERN UX 2.7.2.

[Unreleased]: https://github.com/balatD/kern_ux/compare/1.0.0-beta.1...HEAD
[1.0.0-beta.1]: https://github.com/balatD/kern_ux/compare/1.0.0-alpha.2...1.0.0-beta.1
[1.0.0-alpha.2]: https://github.com/balatD/kern_ux/compare/1.0.0-alpha...1.0.0-alpha.2
[1.0.0-alpha]: https://github.com/balatD/kern_ux/releases/tag/1.0.0-alpha
