# Content Blocks und Seiten

## Die 24 Blöcke

Jeder mit Backend-Vorschau, jeder auf denselben
[Components](Components.md) aufgebaut:

Text · Text und Medien · Bild · Bildergalerie · Video oder Audio · Bühne · Karten ·
Schnellzugriff · Dienstleistung · Standort · Akkordeon · Hinweis · Dialog · Downloads ·
Aufgabenübersicht · Fortschritt · Definitionsliste · Tabelle · Liste · Überschrift ·
Schaltflächen · Trenner · Inhaltsverzeichnis · Sitemap

Wie das im Seitenmodul aussieht, zeigen die
[Screenshots](Screenshots.md#backend-das-sehen-redakteure).

## Seiten-Templates

Fünf Templates mit je einem passenden Backend-Layout:

| Template | Wofür | Spalten |
|---|---|---|
| Standard | einspaltige Inhaltsseite | main |
| Startseite | Einstieg mit Bühne und Kartengittern | hero, main, teaser |
| Thema | zweispaltig mit Seitenleiste, etwa für eine Dienstleistung | main, aside |
| Themenseite | Bühne, Inhalt mit Seitenspalte, Teaser-Reihe | hero, main, aside, teaser |
| Antrag | Formularstrecke mit Fortschrittsanzeige | main |

**Themenseite** ist die einzige Anordnung der vier festen `colPos`-Werte, die weder
Startseite (keine Seitenspalte) noch Thema (keine Bühne, keine Teaser-Reihe) ausdrücken
kann. Für eine Dienstleistungsseite gibt es bewusst *kein* eigenes Layout: das wäre
Thema unter zweitem Namen, und zwei ununterscheidbare Einträge in der Layout-Auswahl
sind für Redakteure eine Verschlechterung. Der Dienstleistungs-Block steht in `main`,
der Standort-Block in `aside`.

`Themenseite` löst ihre vier Spalten je einmal in eine Variable auf und gibt sie dann
aus. Die ältere Wächter-Form in `Startpage.html` und `Subject.html` — ein `f:if` über
einen `f:cObject`-Inline-Aufruf, gefolgt von demselben `f:cObject` — rendert die Spalte
zweimal pro Aufruf.

Die Templates liegen unter `Resources/Private/PageView/Pages/`, die Backend-Layouts
unter `Configuration/Sets/KernUx/PageTsConfig/BackendLayouts/`. Beide gehören
zusammen: ein Layout, dessen Spalten das Template nicht rendert, kostet Redakteure
Inhalte, ohne einen Fehler zu erzeugen.

## Bühne

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

## Dienstleistung, Standort und Schnellzugriff

Drei Blöcke bilden die Muster ab, die auf jedem kommunalen Portal wiederkehren.

**Dienstleistung** folgt der Feldfolge des FIM-Bausteins Leistungen: Kurzbeschreibung,
Volltext, Online-Dienst, Voraussetzungen, Erforderliche Unterlagen, Formulare,
Gebühren, Fristen, Bearbeitungsdauer, Rechtsgrundlagen, Rechtsbehelf, Weiterführende
Informationen, Hinweise zur Zuständigkeit. Die Abschnittsüberschriften sind fest und
übersetzt, nicht editierbar — genau das ist der Sinn der Standardisierung: wer von
einer Kommune zur nächsten wechselt, findet dieselben Wörter in derselben Reihenfolge.

Die langen Abschnitte sind **offene Abschnitte, keine Akkordeons**. Eine
Dienstleistungsbeschreibung ist ein Rechtstext, den Menschen mit Strg+F durchsuchen,
und `details`/`summary` versteckt seinen Inhalt vor der Seitensuche. Das
Inhaltsverzeichnis kann ohnehin nicht in einen einzelnen Block hineingreifen.

**Gebühren** ist ein Rich-Text-Feld, damit ein Gebührenverzeichnis eine Tabelle sein
kann — und zwar dieselbe RTE-Tabelle wie überall sonst, siehe *Tabellen* unten.

**Standort** hält Anschrift, Öffnungszeiten, Barrierefreiheitsmerkmale,
Verkehrsanbindungen und Zahlungsmöglichkeiten. Drei Entscheidungen sind bewusst:

- Die Barrierefreiheitsmerkmale sind **Text, keine Piktogramme**. Eine reine
  Symbolangabe ist für genau die Menschen unlesbar, an die sie sich richtet. KERN
  liefert für Aufzug, Rampe oder barrierefreies WC auch gar kein Symbol.
- Die Karte ist ein **Link, keine Einbettung**. Eine eingebettete Karte wäre bei jedem
  Seitenaufruf eine Anfrage an Dritte — dieselbe Zusage, aus der heraus der
  Asset-Installer kein CDN benutzt.
- Kein `schema.org`-JSON-LD. `OpeningHoursSpecification` erwartet den Wochentag als
  Aufzählung, das Feld ist aber Freitext, weil Kommunen „Montag bis Freitag" und
  „Sa, 1. im Monat" schreiben. Korrekte strukturierte Daten hießen also, Redakteuren
  eine Wochentagsliste aufzuzwingen — und falsche Öffnungszeiten im Suchergebnis sind
  schlimmer als gar keine. `<time>` und `<address>` tragen den maschinenlesbaren Teil.

**Schnellzugriff** ist das Kachelgitter, mit dem jedes Portal aufmacht. Es gibt dafür
keine eigene Komponente: eine Kachel ist `molecule.card` mit Symbol und gedehntem Link
in `organism.cardGrid`. Für **Meldungen** trägt das Kartengitter ein Datumsfeld, das im
Fußbereich der Karte als `<time datetime>` erscheint.

## Tabellen

Tabellen gibt es auf **zwei** Wegen, und die Trennung ist gewollt.

Eine Tabelle **innerhalb** eines Fließtextes zeichnet der Redakteur im
Rich-Text-Editor. `lib.kernUx.rte` hängt dort serverseitig `class="kern-table"` an und
legt einen `<div class="kern-table-responsive" tabindex="0">` darum, damit die
Scrollfläche mit der Tastatur erreichbar ist. Diese Klasse wird zur Laufzeit gesetzt
und steht deshalb in keinem Template — wer nur `Resources/Private/` und
`ContentBlocks/` durchsucht, hält das für eine Lücke; siehe
`Configuration/Sets/KernUx/setup.typoscript`.

Weiter reicht dieser Weg aber nicht: CKEditor schreibt weder `kern-table__cell` noch
`scope`, die Zellen werden in `rte.css` nur mit Elementselektoren nachgebildet. Ohne
`scope` kann ein Screenreader zu keinem Wert die zugehörige Überschrift nennen.

Für eine **eigenständige** Tabelle gibt es deshalb den Block **Tabelle**. Er benutzt
den Tabellen-Assistenten des Cores (`renderType: textTable`) — ein echtes Raster im
Backend statt einer zweistufigen Collection — und rendert `molecule.table`, das
`scope="col"`, `scope="row"`, `<tbody>`/`<tfoot>` und eine `<caption>` setzt. Drei
Schalter entscheiden über Kopfzeile, Kopfspalte und Summenzeile; die Beschriftung
übernimmt die Überschrift des Blocks und wird nur für Screenreader ausgegeben, weil
die sichtbare Überschrift direkt darüber schon dasselbe sagt.

Nicht umgesetzt sind `kern-table--small` und `kern-table--striped`. Beide sind je eine
Zeile, aber ein Aussehensschalter, nach dem niemand gefragt hat.

## Backend-Vorschauen

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
