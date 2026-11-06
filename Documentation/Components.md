# Components

Eine Schicht **nativer Fluid Components** ist die einzige Quelle für KERN-Markup.
Content Blocks, `ext:form`-Templates und Seiten-Templates rufen dieselben Components
auf und bilden nur Daten darauf ab. Die Barrierefreiheits-Zusagen von KERN hängen an
konkreten Klassen und ARIA-Attributen — deshalb existiert dieses Markup genau einmal
und wird durch Tests festgenagelt, die unter *beiden* TYPO3-Majors laufen.

42 Components, gebaut gegen KERN 2.7.2:

| Ebene | |
|---|---|
| **Atome** (15) | Badge, Body, Button, Divider, Error, Heading, Hint, Icon, Label, Link, List, Loader, Preline, Progress, Subline |
| **Moleküle** (18) | AccordionItem, Alert, Breadcrumb, ButtonGroup, Card, ContentHeader, DescriptionList, DownloadList, Figure, Hgroup, MediaPlayer, NavigationList, OpeningHours, Section, SkipLink, SummaryItem, Table, TaskListItem |
| **Organismen** (9) | CardGrid, Dialog, Footer, Gallery, Header, Hero, Kopfzeile, TaskList, TaskListGroup |

Components liegen unter `Resources/Private/Components/` mit einem Ordner pro
Component, `<k:atom.button>` löst also auf `Atom/Button/Button.html` auf. Der
Namespace `k` ist global registriert — Templates brauchen **kein** `xmlns`.

## Markup ansehen

Ohne Browser und ohne Datenbank:

```bash
vendor/bin/typo3 kern-ux:component:render '<k:atom.button icon="arrow-forward">Weiter</k:atom.button>'
```

## Component-Galerie

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

Danach unter `/kern-ux-styleguide` erreichbar (Pfad über `kernUx.styleguide.path`
konfigurierbar). Der Pfad wird gegen die Adresse *innerhalb* der Site verglichen,
funktioniert also auch bei einer Unterverzeichnis-Installation oder einem
Sprachpräfix wie `/de/`.

Im Kontext `Production` genügt die Einstellung allein **nicht**: dort wird die Galerie
nur an eine angemeldete Backend-Sitzung ausgeliefert, sonst antwortet die Seite wie bei
einem unbekannten Pfad. Ein Schalter in den Site-Settings ist zu wenig, um auf einer
Produktivseite eine zusätzliche öffentliche Route zu öffnen — im Kontext `Development`
ist sie ohne Weiteres erreichbar, denn dort wird sie benutzt.

Die Beispiele stehen in `Configuration/Styleguide/Examples.yaml`. Jede Component
**muss** dort auftauchen — ein Test vergleicht die Datei mit dem Component-Baum und
schlägt fehl, wenn etwas fehlt. Eine Galerie, die stillschweigend Components
auslässt, ist schlimmer als keine: sie liest sich als „das ist alles".

Als statisches HTML, etwa für den [axe-Lauf](Accessibility.md):

```bash
vendor/bin/typo3 kern-ux:styleguide:dump --target=var/styleguide
```

## Was KERN nicht abdeckt

Diese Schicht erfindet keine `kern-*`-Klasse. Als Beleg gilt allein ein Selektor in der
geladenen `Resources/Public/Vendor/KernUx/kern.css` — nicht die KERN-Dokumentation, nicht
ein Beispiel, nicht ein Figma-Frame. KERNs eigenes Badge-Beispiel schreibt
`kern-icon--sm`, eine Klasse, die 2.7.2 überhaupt nicht definiert.

Für diese Muster liefert KERN 2.7.2 **null** Klassen. Sie zu bauen hieße, öffentliche
Gestaltungsfläche zu erfinden, und das tut diese Extension nicht:

Pagination · Tabs · Tag · Tooltip · Toggle · Stepper · Seitennavigation ·
Sprachumschalter · Avatar · Chip · Teaser

Wo ein Bedarf trotzdem besteht, wird er anders beantwortet — und zwar so:

| Bedarf | Antwort hier |
|---|---|
| Tabelle | Rich-Text-Editor; `lib.kernUx.rte` hängt `kern-table` an und legt den Scroll-Container darum. Bewusst **kein** Content Block, siehe [Content Blocks](ContentBlocks.md#tabellen). |
| Meldungs-Teaser | `molecule.card` mit Datum im Fußbereich-Slot |
| Kachel im Schnellzugriff | `molecule.card` mit `icon` in `organism.cardGrid` |
| Öffnungszeiten | `molecule.openingHours` — KERNs Definitionsliste plus `<time datetime>` |
| Brotkrumen, Header, Footer, Bühne, Figure, Downloadliste, Mediaplayer, Skip-Link, Navigation | eigene Komponenten auf der `kernt3-`-Schicht |

Klassen, die KERN nicht liefert, leben in `Resources/Public/Css/kernt3.css` unter dem
Präfix `kernt3-`, jeweils mit einem Kommentar, der die Lücke benennt. Sie sehen privat
aus, sind es aber nicht: Projekte überschreiben sie, deshalb stehen sie auf der Liste
der Breaking Changes.

KERN selbst dokumentiert unter *Patterns* bisher nur **Formulare** und **Templates**,
und unter Templates nur *Header* und *Frageseite*. Seitenvorlagen für kommunale Auftritte
sind also nicht KERNs Lücke, sondern die Aufgabe dieser Schicht.

## Eigene Components schreiben

Vier Konventionen, die Tests erzwingen:

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

Eine neue Component braucht außerdem einen Eintrag in
`Configuration/Styleguide/Examples.yaml`, sonst schlägt der Galerie-Test fehl.
