# Konfiguration

Alles steht in den Site-Settings unter `kernUx.*` — im Backend unter *Sites → Settings*
oder direkt in `config/sites/<site>/settings.yaml`. Für den Standardfall ist keine
Zeile TypoScript nötig.

## Site

| Setting | Bedeutung | Default |
|---|---|---|
| `kernUx.site.title` | Name der Organisation. Wird im Header gezeigt, wenn kein Logo gesetzt ist, damit der Header nie leer ist | leer |
| `kernUx.site.logo` | `EXT:`- oder absoluter Pfad zum Logo. **Nicht** die Bildwortmarke der Digitalen Dachmarke, außer mit Freigabe | leer |
| `kernUx.site.searchUrl` | Zielseite der Header-Suche. Leer versteckt die Suche samt Mobil-Umschalter | leer |

## Thema

| Setting | Bedeutung | Default |
|---|---|---|
| `kernUx.theme` | `auto`, `light` oder `dark`. `auto` folgt `prefers-color-scheme` | `auto` |

Die Farben sind KERNs eigene Tokens, keine zweite Palette. Wie das dunkle Thema
aussieht, zeigen die [Screenshots](Screenshots.md#dunkles-thema).

## Assets

| Setting | Bedeutung | Default |
|---|---|---|
| `kernUx.assets.basePath` | Wohin `kern-ux:assets:install` geschrieben hat. Umbiegen, um die Dateien aus einem projekteigenen Ort zu liefern | `EXT:kern_ux/Resources/Public/Vendor/KernUx/` |
| `kernUx.assets.includeCss` | Nur abschalten, wenn das Projekt `kern.css` in seinen eigenen Build bündelt | `true` |
| `kernUx.assets.includeFonts` | Fira Sans, KERNs Hausschrift. Nur abschalten, wenn das Projekt sie selbst hostet — KERN sieht ohne sie falsch aus | `true` |
| `kernUx.assets.includeNotoSans` | Optionale Zweitschrift, die KERN nur für bestimmte Inhalte braucht | `false` |

## Navigation

Zwei Bänder: Marke und Servicelinks in der ersten Zeile, Hauptnavigation in der zweiten.
Die Servicelinks können ein Symbol tragen — Seitenfeld `tx_kernux_nav_icon`, beschränkt
auf die Icons, die KERN wirklich ausliefert.

| Setting | Bedeutung | Default |
|---|---|---|
| `kernUx.navigation.helpRootPage` | Kinder dieser Seite werden die Hilfsnavigation (Sprachwahl, Leichte Sprache, Gebärdensprache). `0` schaltet sie ab | `0` |
| `kernUx.navigation.footerRootPage` | Jedes Kind wird eine Fußbereichsspalte, dessen Kinder die Links darin. `0` schaltet sie ab | `0` |
| `kernUx.navigation.metaRootPage` | Kinder werden die Rechtszeile: Impressum, Datenschutz, Barrierefreiheitserklärung. `0` schaltet sie ab | `0` |

`kern-ux:demo:install --configure-navigation` schreibt diese drei Werte selbst; siehe
[Installation](Installation.md#demo-inhalte).

### Wie das Flyout aufgeht

Die zweite Menüebene ist ein Panel, das bei **Hover und bei Tastaturfokus** aufgeht,
beides in CSS (`:hover`, `:focus-within`). WCAG 1.4.13 verlangt für Inhalte, die so
erscheinen, zusätzlich *dismissible* — das ist der einzige Grund, aus dem
`navigation.js` hier überhaupt eingreift: Escape schließt das offene Panel, und die
Markierung fällt weg, sobald Zeiger oder Fokus weiterziehen.

Der Menü-Prozessor läuft mit `expandAll = 1`, **jeder** Punkt trägt also seine
Unterpunkte im Markup. Das ist die Voraussetzung dafür, dass sich das Panel überall
öffnet und nicht nur über dem Zweig, in dem der Besucher gerade steht. Der Preis sind
ein paar Kilobyte HTML, die mit der Seite gecacht werden — dafür braucht das Aufklappen
weder JavaScript noch einen Request. Panel und Mobilmenü sind höhenbegrenzt und
scrollen, damit ein Abschnitt mit vielen Seiten nicht unten aus dem Bild läuft.

Die Tiefe ist eine Konfigurationszeile (`levels` am Menü-Prozessor), keine
Template-Grenze — `molecule.navigationList` rendert sich pro Ebene selbst. Der Default
ist 2; drei Ebenen wurden ausprobiert und verworfen, weil ein Abschnitt mit vielen
Seiten den Header unbrauchbar hoch macht.

## Digitale Dachmarke

Die KERN-Kopfzeile („Offizielle Website – Bundesrepublik Deutschland") ist Teil der
Digitalen Dachmarke und **ausschließlich Angeboten von Bund, Ländern und Kommunen
vorbehalten**. Sie ist daher **standardmäßig deaktiviert** und muss bewusst
eingeschaltet werden. Die Bildwortmarke wird nicht mitgeliefert. Freigaben erteilt
`dachmarke@digitalservice.bund.de`.

Dasselbe gilt für die Notizzeile im Fußbereich. Die Struktur ist da — Marke links, Text
daneben, Fördermarke rechts —, aber **weder Bildmarke noch Wortlaut werden
mitgeliefert**: die Marke ist geschützt, der offizielle Text gehört der Dachmarke. Beide
kommen aus Site-Settings, und ohne Text erscheint die Zeile gar nicht:

| Setting | Bedeutung | Default |
|---|---|---|
| `kernUx.dachmarke.kopfzeileEnable` | schaltet die Kopfzeile ein. Nur mit Berechtigung | `false` |
| `kernUx.dachmarke.kopfzeileLabel` | abweichender Wortlaut. Leer heißt: der offizielle | leer |
| `kernUx.dachmarke.noteText` | Wortlaut der Notiz im Fuß. Leer heißt: keine Zeile | leer |
| `kernUx.dachmarke.noteLogo` | Pfad zur Bund/Länder/Kommunen-Marke | leer |
| `kernUx.footer.fundingLogo` | Pfad zu einer Fördermarke, etwa der EU-Flagge | leer |
| `kernUx.footer.fundingLabel` | Alternativtext dazu — benennt das Förderprogramm, nicht das Bild | leer |

## Rich Text

Das Preset `kern_ux` hat eine schmale Toolbar, weil KERN fast nichts nach Element
gestaltet. Die KERN-Klassen setzt `lib.kernUx.rte` serverseitig — kein Redakteur kann
sie versehentlich entfernen. Ohne `typo3/cms-rte-ckeditor` fallen RTE-Felder auf ein
schlichtes Textarea zurück.

## Component-Galerie

`kernUx.styleguide.enable` und `kernUx.styleguide.path` steuern die Galerie. Weil dort
noch eine Bedingung für den Kontext `Production` hängt, steht das bei den
[Components](Components.md#component-galerie).
