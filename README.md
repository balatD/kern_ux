# KERN UX-Standard für TYPO3

Bringt den [KERN UX-Standard](https://www.kern-ux.de/) nach TYPO3 13.4 und 14.3 —
als Fluid Components, Content Blocks und Formular-Templates für `ext:form`.

> **Unabhängige Community-Integration.** Dieses Projekt gehört nicht zum KERN-Team
> und ist kein offizielles KERN-Kit. „KERN" und die Digitale Dachmarke für Deutschland
> sind Kennzeichen ihrer jeweiligen Inhaber; dieses Projekt beansprucht keine Rechte
> daran und wird von ihnen nicht unterstützt oder geprüft.

> **Status: alpha.** In aktiver Entwicklung, noch nicht für Produktivbetrieb geeignet.
> Öffentliche Schnittstellen können sich ohne Vorwarnung ändern.

📸 **[Screenshots ansehen](Documentation/Screenshots.md)** — Seiten, Formularstrecke,
Mobilmenü, dunkles Thema, Component-Galerie und die Redakteurs-Sicht im Backend, alles
aus dem mitgelieferten Demo-Seitenbaum.

## Was drin ist

Eine Schicht **nativer Fluid Components** ist die einzige Quelle für KERN-Markup.
Content Blocks, `ext:form`-Templates und Seiten-Templates rufen dieselben Components
auf und bilden nur Daten darauf ab. Die Barrierefreiheits-Zusagen von KERN hängen an
konkreten Klassen und ARIA-Attributen — deshalb existiert dieses Markup genau einmal
und wird durch Tests festgenagelt, die unter *beiden* TYPO3-Majors laufen.

Nach [Installation](#installation) sofort nutzbar, alles gegen KERN **2.7.2** gebaut:

- **40 Fluid Components** — 15 Atome, 16 Moleküle, 9 Organismen: Header mit
  Flyout-Navigation, Footer, Kopfzeile, Bühne, Dialog, Galerie, Kartengitter,
  Aufgabenliste, Akkordeon, Breadcrumb, Medienplayer, Zusammenfassung.
- **20 Content Blocks** für Redakteure, jeder mit Backend-Vorschau: Text, Text und
  Medien, Bild, Bildergalerie, Video oder Audio, Bühne, Karten, Akkordeon, Hinweis,
  Dialog, Downloads, Aufgabenübersicht, Fortschritt, Definitionsliste, Liste,
  Überschrift, Schaltflächen, Trenner, Inhaltsverzeichnis, Sitemap.
- **`ext:form`-Theme** — über 30 Element-Partials, Fehlerübersicht mit Sprungmarken,
  Fortschrittsanzeige für mehrseitige Formulare, `KernDate` (Datum als drei Felder
  statt `input type="date"`).
- **4 Seiten-Templates mit passenden Backend-Layouts** — Standard, Startseite, Thema,
  Antrag.
- **Site Set** mit Settings für Titel, Logo, Suche, Navigationswurzeln, Dachmarke und
  Fördermarke — keine TypoScript-Handarbeit für den Standardfall.
- **Hell und Dunkel** über `kernUx.theme`; `auto` folgt `prefers-color-scheme`.
- **RTE-Preset `kern_ux`** — schmale Toolbar, weil KERN fast nichts nach Element
  gestaltet. Die KERN-Klassen setzt `lib.kernUx.rte` serverseitig, kein Redakteur
  kann sie versehentlich entfernen.
- **KERN-Distribution per CLI** — `kern-ux:assets:install` holt die gepinnte Version
  (CSS, Schriften, Kopfzeilen-JS) von npm; nichts davon liegt im Repository.
- **`lib.contentElement`** wird mitgeliefert, `fluid_styled_content` ist also nicht
  nötig.
- **Component-Galerie** unter `/kern-ux-styleguide` — lebende Doku und Prüfziel für
  axe, standardmäßig aus.
- **Demo-Seitenbaum per CLI** — 47 Seiten, 107 Inhaltselemente: vier realistische
  Verwaltungsseiten plus eine Seite je Inhaltstyp; so sieht er aus in den
  [Screenshots](Documentation/Screenshots.md).

Die Teile der Digitalen Dachmarke — Kopfzeile und Notizzeile im Fuß — sind bewusst
**aus** und liefern keine Marken mit; siehe [Digitale Dachmarke](#digitale-dachmarke).

## Voraussetzungen

| | |
|---|---|
| TYPO3 | 13.4 LTS oder 14.3 LTS |
| PHP | 8.2 – 8.5 |
| Content Blocks | `friendsoftypo3/content-blocks` (1.x unter v13, 2.x unter v14) |
| `ext:form` | optional, nur für die Formular-Templates |

## Installation

Das Paket liegt auf [Packagist](https://packagist.org/packages/balatd/kern-ux).
Veröffentlicht ist bisher nur eine Vorabversion, deshalb braucht Composer die
Stabilitätsangabe `@alpha` — ein Projekt mit dem üblichen `minimum-stability: stable`
findet das Paket sonst nicht:

```bash
composer require balatd/kern-ux:^1.0@alpha
vendor/bin/typo3 extension:setup
```

Sobald eine stabile Version getaggt ist, genügt `composer require balatd/kern-ux`.

Danach die KERN-Assets holen:

```bash
vendor/bin/typo3 kern-ux:assets:install
```

**Dieser Schritt ist erforderlich.** Die KERN-Distribution wird bewusst *nicht*
mitgeliefert (siehe [THIRD-PARTY.md](THIRD-PARTY.md)). Das Kommando lädt eine
festgepinnte, per SHA-512 geprüfte Version von `@kern-ux/native` nach
`Resources/Public/Vendor/KernUx/` — einmalig zur Installationszeit. Zur Laufzeit
werden **keine** Fremd-Requests ausgeführt und kein CDN eingebunden: Behördenseiten
können externe Requests in der Regel nicht abnehmen.

Das Verzeichnis ist nicht eingecheckt, der Schritt gehört also in jedes Deployment.

### Demo-Inhalte

Zum Ansehen und Prüfen gibt es einen Seitenbaum auf Kommando:

```bash
vendor/bin/typo3 kern-ux:demo:install --configure-navigation
```

Das legt 47 Seiten und 107 Inhaltselemente an: vier Beispielseiten, wie sie in einer
Verwaltung vorkommen, und **eine Seite je Inhaltstyp**, damit sich jedes Element
einzeln prüfen lässt. Dazu die drei Navigationsbäume für Hilfs-, Fußbereichs- und
Rechtsnavigation — ohne die bleiben diese Menüs abgeschaltet und der Seitenrahmen ist
nie vollständig zu sehen. Deren Wurzelseiten stehen auf „nicht im Menü": eine Seite,
die ein Menü speist, gehört nicht selbst in eines.

| Option | Wirkung |
|---|---|
| `--force` | löscht einen früher installierten Demo-Baum vorher, statt einen zweiten daneben zu legen |
| `--configure-navigation` | schreibt die drei Navigations-Seiten in die `settings.yaml` der Site |
| `--site=<id>` | nötig, wenn mehr als eine Site existiert |

Alles liegt unter der Seite „KERN UX Demo" und ist über `--force` jederzeit wieder weg.
Die neun Beispieldateien (Bilder, PDF, Audio, Untertitel) werden erzeugt, nicht
mitgeliefert: so bleibt kein Binärmaterial im Repository, und die Demo nutzt trotzdem
echte FAL-Referenzen mit echter Bildverarbeitung.

Der Inhalt ist deutsch, weil das die Sprache der Verwaltungen ist, für die KERN
gemacht ist — und weil die deutschen Übersetzungen sonst nur von Tests berührt werden.

### Bekannte Falle: die Startseite von `typo3 setup`

`typo3 setup` legt eine Willkommensseite an, deren TypoScript **alles überschreibt,
was ein Site Set liefert** — und zwar in beiden Majors unterschiedlich:

| TYPO3 | Was angelegt wird | Wirkung |
|---|---|---|
| 13.4 | `sys_template`-Datensatz mit `clear = 3` | löscht Konstanten *und* Setup aus allen Site Sets |
| 14.3 | `config/sites/<id>/setup.typoscript` | lädt *nach* den Sets und überschreibt `page.10` |

Solange das steht, rendert die Seite TYPO3s Standardausgabe und nicht dieses
Sitepackage. Vor dem ersten Seitenaufruf entfernen:

```bash
# TYPO3 14
rm -f config/sites/<site>/setup.typoscript
# TYPO3 13: den sys_template-Datensatz löschen oder in der TypoScript-Verwaltung
# die Haken bei "Clear" für Constants und Setup entfernen
```

### Digitale Dachmarke

Die KERN-Kopfzeile („Offizielle Website – Bundesrepublik Deutschland") ist Teil der
Digitalen Dachmarke und **ausschließlich Angeboten von Bund, Ländern und Kommunen
vorbehalten**. Sie ist daher **standardmäßig deaktiviert** und muss bewusst
eingeschaltet werden. Die Bildwortmarke wird nicht mitgeliefert. Freigaben erteilt
`dachmarke@digitalservice.bund.de`.

Dasselbe gilt für die Notizzeile im Fußbereich. Die Struktur ist da — Marke links, Text
daneben, Fördermarke rechts —, aber **weder Bildmarke noch Wortlaut werden
mitgeliefert**: die Marke ist geschützt, der offizielle Text gehört der Dachmarke. Beide
kommen aus Site-Settings, und ohne Text erscheint die Zeile gar nicht:

| Setting | Bedeutung |
|---|---|
| `kernUx.dachmarke.noteText` | Wortlaut der Notiz. Leer heißt: keine Zeile |
| `kernUx.dachmarke.noteLogo` | Pfad zur Bund/Länder/Kommunen-Marke |
| `kernUx.footer.fundingLogo` | Pfad zu einer Fördermarke, etwa der EU-Flagge |
| `kernUx.footer.fundingLabel` | Alternativtext dazu — benennt das Förderprogramm, nicht das Bild |

### Navigation

Zwei Bänder: Marke und Servicelinks in der ersten Zeile, Hauptnavigation in der zweiten.
Die Servicelinks können ein Symbol tragen — Seitenfeld `tx_kernux_nav_icon`, beschränkt
auf die Icons, die KERN wirklich ausliefert.

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

### Bühne

Der Inhaltstyp **Bühne** ist der Einstiegsblock einer Seite: Dachzeile, Überschrift
(Voreinstellung h1 in KERNs größter Stufe), ein kurzer Vorspann, ein Hinweis und ein
Bild daneben. Er hat drei Eigenheiten, die keine Einstellung sind:

- Der Text steht **immer vor dem Bild im Quelltext**, auch wenn das Bild links gezeigt
  wird. Die Seite ist eine Grid-Reihenfolge, damit die Lesereihenfolge dem Inhalt folgt
  und nicht dem Layout.
- Das Bild trägt **keine Bildunterschrift** und ist keine `figure`. Ein Bühnenbild
  illustriert; was ein Besucher wirklich braucht, steht im Vorspann. Ein Bild mit eigener
  Aussage gehört in *Bild* oder *Text und Medien*.
- Der Hinweis ist eine KERN-Hinweiszeile **ohne Überschrift** (`<p class="kern-title">`
  statt `<h2>`). Ein Satz wie „Neue Registrierungen kosten 30 Euro" ist eine Aussage,
  kein Abschnitt, und hätte in der Dokumentgliederung nichts zu suchen.

### Backend-Vorschauen

Jede `backend-preview.html` deklariert `<f:layout name="Preview" />` und genau einen
`<f:section name="Content">`. Das ist keine Stilfrage: das Seitenmodul fragt drei Teile
ab — Kopf, Inhalt, Fuß — und Content Blocks beantwortet alle drei, indem es die Datei
durch ein Layout schickt, das den passenden Abschnitt herausholt. Eine Vorschau **ohne**
Layout hat keine Abschnitte, gibt also dreimal dieselbe Ausgabe zurück, und der
Redakteur sieht jede Vorschau dreifach untereinander. Es gibt dabei keinen Fehler und
keinen Logeintrag.

Nur `Content` ist definiert. Fehlt ein Abschnitt, fällt Content Blocks auf TYPO3s eigene
Implementierung zurück — und deren Kopfzeile ist besser als eine eigene: sie verlinkt
das Bearbeitungsformular und markiert eine verborgene Überschrift. Deshalb wiederholt
keine Vorschau das `header`-Feld. Ein Test hält beides fest.

## Entwicklung

Das Repository bringt einen DDEV-Harness mit, der beide unterstützten TYPO3-Majors
parallel betreibt — nötig, weil eine Codebase beide bedient und ihre
Abhängigkeitsgraphen sich gegenseitig ausschließen.

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

### Tests

„Tests laufen lassen" heißt hier immer *einen Major festpinnen, auflösen, testen* —
ein nacktes `phpunit` würde nur den zuletzt installierten Major prüfen.

```bash
Build/Scripts/runTests.sh -t 13 -s all
Build/Scripts/runTests.sh -t 14 -s all
Build/Scripts/runTests.sh -t 14 -s functional
Build/Scripts/runTests.sh -t 14 -s cglFix
```

Markup einer Component ansehen, ohne Browser und ohne Datenbank:

```bash
vendor/bin/typo3 kern-ux:component:render '<k:atom.button icon="arrow-forward">Weiter</k:atom.button>'
```

### Formulare

`ext:form` wird über ein Form Set konfiguriert
(`Configuration/Form/KernUx/config.yaml`, `priority: 200`). Auf TYPO3 14 wird es
automatisch gefunden; auf 13 gibt es noch keine Form Sets, dort registriert
`ext_localconf.php` dieselbe Datei über `yamlConfigurations` — versionsgeschützt,
weil 14.2 diese API deprecated hat.

Die KERN-Formularregeln sitzen in **zwei** Partials, nicht in den 33 Element-Partials:
`Field/Field.html` für die `kern-form-input`-Familie und `Field/Group.html` für
Checkbox- und Radio-Gruppen. Was dort einmal implementiert ist:

- **Optionale Felder werden markiert, nicht die Pflichtfelder** — die Umkehrung der
  `ext:form`-Konvention. Pflicht wird über `aria-required` vermittelt, nicht über das
  native `required`-Attribut.
- **Drei Fehlersignale gleichzeitig**: Modifier am Wrapper, Modifier am Feld,
  `aria-invalid`. Nur eines davon wäre Zustand allein durch Farbe (WCAG 1.4.1).
- **`aria-describedby` in der Reihenfolge Hinweis, dann Fehler** — so macht es KERNs
  Plain-Kit. Das React-Kit macht es umgekehrt; wir folgen dem Plain-Kit.
- **Bei Gruppen** trägt das `fieldset` das `aria-describedby`, aber **jeder**
  Kind-Input zusätzlich `aria-invalid` und die Fehlerklasse.

Dazu kommen eine Fehlerübersicht mit Sprungmarken (`kern-alert--danger` mit
`role="alert"` — hier korrekt, weil das Markup erst nach einem fehlgeschlagenen
Absenden existiert), eine Fortschrittsanzeige für mehrseitige Formulare und das
Element **`KernDate`**: ein Datum als drei Felder, wie KERN es vorschreibt. Kein
`<input type="date">` und kein JavaScript-Datepicker — und TYPO3 14 hat sein eigenes
DatePicker-Element ohnehin deprecated (#109152).

> **Wichtig für Projekte ohne fluid_styled_content:** Diese Extension ersetzt FSC und
> liefert deshalb `lib.contentElement` selbst mit. Ohne diese Definition rendert
> *jedes* Extbase-Plugin — auch das Formular-Plugin — als leerer String, ohne Fehler.

### Component-Galerie

Eine Seite, die jede Component in ihren dokumentierten Zuständen zeigt — lebende
Doku, Sichtprüfung und Ziel der Barrierefreiheitstests in einem. Sie ist
**standardmäßig aus**, weil sie ein Entwicklungs- und Prüfwerkzeug ist und nicht
Seiteninhalt:

```yaml
# config/sites/<site>/settings.yaml
kernUx:
  styleguide:
    enable: true
```

Danach unter `/kern-ux-styleguide` erreichbar (Pfad konfigurierbar). Der Pfad wird
gegen die Adresse *innerhalb* der Site verglichen, funktioniert also auch bei einer
Unterverzeichnis-Installation oder einem Sprachpräfix wie `/de/`.

Im Kontext `Production` genügt die Einstellung allein **nicht**: dort wird die Galerie
nur an eine angemeldete Backend-Sitzung ausgeliefert, sonst antwortet die Seite wie bei
einem unbekannten Pfad. Ein Schalter in den Site-Settings ist zu wenig, um auf einer
Produktivseite eine zusätzliche öffentliche Route zu öffnen — im Kontext `Development`
ist sie ohne Weiteres erreichbar, denn dort wird sie benutzt.

Die Beispiele stehen in `Configuration/Styleguide/Examples.yaml`. Jede Component
**muss** dort auftauchen — ein Test vergleicht die Datei mit dem Component-Baum und
schlägt fehl, wenn etwas fehlt. Eine Galerie, die stillschweigend Components
auslässt, ist schlimmer als keine: sie liest sich als „das ist alles".

### Barrierefreiheit prüfen

axe läuft gegen die Galerie, ohne Webserver und ohne Datenbank:

```bash
vendor/bin/typo3 kern-ux:assets:install
vendor/bin/typo3 kern-ux:styleguide:dump --target=var/styleguide
cd Tests/A11y && npm install && npx playwright install chromium && npm test
```

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

Der Lauf bricht auch ab, wenn das KERN-Stylesheet nicht
geladen wurde: ohne CSS überspringt axe still alle Kontrastregeln und der Test wäre
aus dem falschen Grund grün.

Der Galerie-Lauf beantwortet aber nur die halbe Frage: ob jede Component **einzeln**
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

Das ersetzt keine manuelle Prüfung. Was Automatisierung nicht fängt und deshalb von
Hand abgenommen werden muss: Tastaturbedienung durch mehrstufige Formulare mit
Fehlern, Screenreader-Ausgabe bei Feldern mit Hinweis *und* Fehler, und die
Überschriftenordnung ganzer Seiten.

### Components schreiben

Components liegen unter `Resources/Private/Components/` mit einem Ordner pro
Component, `<k:atom.button>` löst also auf `Atom/Button/Button.html` auf. Der
Namespace `k` ist global registriert — Templates brauchen **kein** `xmlns`.

Zwei Konventionen, die Tests erzwingen:

1. **Root-Level-Tags verketten.** `<f:argument>` und `<f:variable>` müssen auf
   Root-Ebene stehen, und jeder Zeilenumbruch *zwischen* ihnen landet im HTML.
   Daher `/><f:argument` direkt aneinander, Umbrüche nur *innerhalb* der Tags.
2. **Keine Union-Types in `<f:argument>`.** `type="int|float"` gibt es erst ab
   Fluid 5, TYPO3 13 fährt Fluid 4.6.
3. **Kein Zeilenumbruch am Dateiende** und keiner um einen Inline-Slot. Beides sind
   Textknoten und landen im Markup — bei einer Inline-Component als Leerzeichen vor
   dem nächsten Zeichen („Status: X ." statt „Status: X."). `f:spaceless` hilft dabei
   nicht: es räumt nur zwischen Tags auf, nicht um Textknoten.
4. **Semantik und Optik trennen.** Überschriftenstufe ist ein Argument, die visuelle
   Größe ein zweites. KERN verlangt das ausdrücklich, damit eine Component in jede
   Dokumentstruktur passt, ohne ihr Aussehen zu ändern.

## Sprachen

Englische Quellsprache, deutsche Übersetzung in `de.*.xlf` — die TYPO3-Konvention.
Das ist nicht kosmetisch: TYPO3 behandelt `en` als Default-Sprachschlüssel und liest
dann die `<source>`-Werte, statt nach einer `en.`-Übersetzung zu suchen. Mit deutscher
Quelle zeigte eine englische Seite auf TYPO3 13 deutsche Texte, während TYPO3 14 die
Übersetzung fand. Bei Beiträgen also bitte englische Quelle, deutsche Übersetzung.

## Lizenz

GPL-2.0-or-later. Zu den Lizenzen der zur Installationszeit geholten KERN-Assets
siehe [THIRD-PARTY.md](THIRD-PARTY.md).
