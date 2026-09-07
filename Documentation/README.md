# Dokumentation

Der [Überblick und die Installation in Kurzform](../README.md) stehen im
Haupt-README. Hier steht das Detail.

| | |
|---|---|
| [Installation](Installation.md) | Setup, KERN-Assets, Demo-Seitenbaum, Stolperfallen |
| [Konfiguration](Configuration.md) | Site-Settings, Thema, Navigation, Digitale Dachmarke, RTE |
| [Components](Components.md) | Component-Schicht, Galerie, eigene Components schreiben |
| [Content Blocks](ContentBlocks.md) | Die 20 Blöcke, Seiten-Templates, Backend-Vorschauen |
| [Formulare](Forms.md) | `ext:form`-Theme, Fehlerbehandlung, `KernDate` |
| [Barrierefreiheit](Accessibility.md) | axe-Läufe, Prüfumfang, was von Hand bleibt |
| [Entwicklung](Development.md) | DDEV-Harness für beide Majors, Tests, Sprachen |
| [Screenshots](Screenshots.md) | Der Demo-Seitenbaum in Bildern |

Alles gegen KERN **2.7.2** gebaut. Zu den Lizenzen der Fremdkomponenten siehe
[THIRD-PARTY.md](../THIRD-PARTY.md).

## Was die Extension mitbringt

- **40 Fluid Components** — 15 Atome, 16 Moleküle, 9 Organismen: Header mit
  Flyout-Navigation, Footer, Kopfzeile, Bühne, Dialog, Galerie, Kartengitter,
  Aufgabenliste, Akkordeon, Breadcrumb, Medienplayer, Zusammenfassung.
- **20 Content Blocks** für Redakteure, jeder mit Backend-Vorschau.
- **`ext:form`-Theme** — 30 Element-Partials, Fehlerübersicht mit Sprungmarken,
  Fortschrittsanzeige für mehrseitige Formulare, `KernDate` (Datum als drei Felder
  statt `input type="date"`).
- **4 Seiten-Templates mit passenden Backend-Layouts** — Standard, Startseite, Thema,
  Antrag.
- **Site Set** mit Settings für Titel, Logo, Suche, Navigationswurzeln, Dachmarke und
  Fördermarke — keine TypoScript-Handarbeit für den Standardfall.
- **Hell und Dunkel** über `kernUx.theme`; `auto` folgt `prefers-color-scheme`.
- **RTE-Preset `kern_ux`** mit serverseitig gesetzten KERN-Klassen.
- **KERN-Distribution per CLI** — `kern-ux:assets:install` holt die gepinnte Version (CSS und Schriften)
  von npm; nichts davon liegt im Repository.
- **`lib.contentElement`** wird mitgeliefert, `fluid_styled_content` ist also nicht
  nötig.
- **Component-Galerie** unter `/kern-ux-styleguide` — lebende Doku und Prüfziel für
  axe, standardmäßig aus.
- **Demo-Seitenbaum per CLI** — 47 Seiten, 107 Inhaltselemente.

Die Teile der Digitalen Dachmarke — Kopfzeile und Notizzeile im Fuß — sind bewusst
**aus** und liefern keine Marken mit; siehe
[Digitale Dachmarke](Configuration.md#digitale-dachmarke).

## Kommandos

| | |
|---|---|
| `kern-ux:assets:install` | holt die gepinnte KERN-Distribution von npm |
| `kern-ux:demo:install` | legt den Demo-Seitenbaum an |
| `kern-ux:styleguide:dump` | schreibt die Component-Galerie als statisches HTML |
| `kern-ux:component:render` | rendert Fluid-Markup auf der Kommandozeile |
