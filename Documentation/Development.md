# Entwicklung

Das Repository bringt einen DDEV-Harness mit, der beide unterstützten TYPO3-Majors
parallel betreibt — nötig, weil eine Codebase beide bedient und ihre
Abhängigkeitsgraphen sich gegenseitig ausschließen (Content Blocks 1.x ist v13-only,
2.x v14-only).

```bash
ddev start
ddev install-all          # oder: ddev install-v13 / ddev install-v14
```

| | |
|---|---|
| Übersicht | <https://kern-ux.ddev.site/> |
| TYPO3 13.4 | <https://v13.kern-ux.ddev.site/typo3/> |
| TYPO3 14.3 | <https://v14.kern-ux.ddev.site/typo3/> |
| Zugang | `admin` / `Joh316!!` |

## Tests

„Tests laufen lassen" heißt hier immer *einen Major festpinnen, auflösen, testen* —
ein nacktes `phpunit` würde nur den zuletzt installierten Major prüfen.

```bash
Build/Scripts/runTests.sh -t 13 -s all
Build/Scripts/runTests.sh -t 14 -s all
Build/Scripts/runTests.sh -t 14 -s functional
Build/Scripts/runTests.sh -t 14 -s cglFix
```

Dieselbe Matrix fährt die [CI](../.github/workflows/ci.yml): statische Analyse
(Lint, `php-cs-fixer`, PHPStan), Unit- und Functional-Tests über TYPO3 13/14 × PHP
8.2/8.3/8.4 plus einen `--prefer-lowest`-Lauf je Major, und der
[axe-Lauf](Accessibility.md) gegen die Galerie.

Markup einer Component ansehen, ohne Browser und ohne Datenbank:

```bash
vendor/bin/typo3 kern-ux:component:render '<k:atom.button icon="arrow-forward">Weiter</k:atom.button>'
```

## Sprachen

Englische Quellsprache, deutsche Übersetzung in `de.*.xlf` — die TYPO3-Konvention.
Das ist nicht kosmetisch: TYPO3 behandelt `en` als Default-Sprachschlüssel und liest
dann die `<source>`-Werte, statt nach einer `en.`-Übersetzung zu suchen. Mit deutscher
Quelle zeigte eine englische Seite auf TYPO3 13 deutsche Texte, während TYPO3 14 die
Übersetzung fand. Bei Beiträgen also bitte englische Quelle, deutsche Übersetzung.

## Beiträge

Was Tests erzwingen und deshalb vorab lohnt zu lesen:

- Konventionen für [Components](Components.md#eigene-components-schreiben) — inklusive
  des Pflichteintrags in `Configuration/Styleguide/Examples.yaml`.
- Der Layout-Zwang in jeder [Backend-Vorschau](ContentBlocks.md#backend-vorschauen).
- Die beiden Partials, in denen die [Formularregeln](Forms.md) sitzen — nicht in den
  Element-Partials.
