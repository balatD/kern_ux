# Content Blocks und Seiten

## Die 20 Blöcke

Jeder mit Backend-Vorschau, jeder auf denselben
[Components](Components.md) aufgebaut:

Text · Text und Medien · Bild · Bildergalerie · Video oder Audio · Bühne · Karten ·
Akkordeon · Hinweis · Dialog · Downloads · Aufgabenübersicht · Fortschritt ·
Definitionsliste · Liste · Überschrift · Schaltflächen · Trenner ·
Inhaltsverzeichnis · Sitemap

Wie das im Seitenmodul aussieht, zeigen die
[Screenshots](Screenshots.md#backend-das-sehen-redakteure).

## Seiten-Templates

Vier Templates mit je einem passenden Backend-Layout:

| Template | Wofür |
|---|---|
| Standard | einspaltige Inhaltsseite |
| Startseite | Einstieg mit Bühne und Kartengittern |
| Thema | zweispaltig mit Seitenleiste, etwa für eine Dienstleistung |
| Antrag | Formularstrecke mit Fortschrittsanzeige |

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
