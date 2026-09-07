# Barrierefreiheit prüfen

axe läuft gegen die [Component-Galerie](Components.md#component-galerie), ohne
Webserver und ohne Datenbank:

```bash
vendor/bin/typo3 kern-ux:assets:install
vendor/bin/typo3 kern-ux:styleguide:dump --target=var/styleguide
cd Tests/A11y && npm install && npx playwright install chromium && npm test
```

## Prüfumfang

Geprüft wird gegen `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`, `wcag22aa` — die
Mengen, die KERNs eigener Anspruch (BITV 2.0 AA über EN 301 549, zusätzlich gegen
WCAG 2.2 getestet) aufspannt — und zusätzlich gegen `best-practice`. Letzteres ist
keine Kür: `heading-order`, `region`, `landmark-unique`, `landmark-one-main`,
`page-has-heading-one` und `skip-link` tragen in axe-core **keinen** `wcag`-Tag,
sondern nur diesen. Genau diese sechs sind aber der Grund, aus dem ganze Seiten geprüft
werden; ohne den Tag beantwortete der Lauf eine andere Frage als die behauptete.

Jede Seite läuft in drei Durchgängen: helles Thema auf 1280px, dunkles Thema auf
1280px, helles Thema auf 390px. Kontrast hängt am Thema, `target-size` und `reflow`
hängen an der Breite — ein einzelner Desktop-Durchgang lässt das dunkle Thema und das
gesamte Mobil-Layout samt Navigationspanel ungeprüft.

Der Lauf bricht auch ab, wenn das KERN-Stylesheet nicht geladen wurde: ohne CSS
überspringt axe still alle Kontrastregeln und der Test wäre aus dem falschen Grund
grün.

## Ganze Seiten

Der Galerie-Lauf beantwortet nur die halbe Frage: ob jede Component **einzeln**
barrierefrei ist. Ob sie es **zusammen** noch sind, zeigt erst eine vollständige Seite —
Überschriftenordnung über eine ganze Seite, Eindeutigkeit der Landmarken und Kontrast
im echten Layout sind Eigenschaften, die eine Component allein nicht haben kann. Dafür
nimmt derselbe Lauf beliebig viele echte Seiten:

```bash
vendor/bin/typo3 kern-ux:demo:install --configure-navigation
cd Tests/A11y
node axe.mjs --sitemap https://v14.kern-ux.ddev.site kern-ux-demo kern-ux-demo/elemente/hinweis …
```

Fünf der Fehler in dieser Extension sind genau so gefunden worden und nicht von den
Unit- oder Markup-Tests: sie lagen alle *zwischen* den geprüften Einheiten.

## Was von Hand bleibt

Das ersetzt keine manuelle Prüfung. Was Automatisierung nicht fängt und deshalb von
Hand abgenommen werden muss: Tastaturbedienung durch mehrstufige Formulare mit
Fehlern, Screenreader-Ausgabe bei Feldern mit Hinweis *und* Fehler, und die
Überschriftenordnung ganzer Seiten.
