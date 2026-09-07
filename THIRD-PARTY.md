# Fremdkomponenten und Lizenzen

Dieses Repository enthält **keinen** KERN-Code und keine KERN-Assets. Die
KERN-Distribution wird zur Installationszeit von der npm-Registry geholt
(`vendor/bin/typo3 kern-ux:assets:install`) und nach
`Resources/Public/Vendor/KernUx/` geschrieben. Dieses Verzeichnis ist nicht
eingecheckt.

## Warum nicht mitgeliefert

| Grund | |
|---|---|
| Lizenztrennung | KERN steht unter EUPL-1.2, dieses Projekt unter GPL-2.0-or-later. Getrennt zu halten vermeidet die Frage der Weiterverbreitung eines EUPL-Werks in einem GPL-Paket. |
| Fehlende Font-Lizenztexte | `@kern-ux/native` liefert seine Font-Binaries ohne die von der OFL-1.1 geforderten Copyright- und Lizenztexte aus. Wer sie weiterverbreitet, übernimmt diese Lücke. |
| Größe | Die Fira-Sans-Schnitte machen den Großteil der Distribution aus. |

## Beim Installationsschritt geholt

### KERN UX-Standard (`@kern-ux/native`)

- Festgepinnte Version: **2.7.2** (siehe `KernAssetInstaller::PINNED_VERSION`)
- Lizenz: **EUPL-1.2**
- Quelle: <https://gitlab.opencode.de/kern-ux/kern-ux-plain>, npm `@kern-ux/native`
- Download wird gegen den Integritätswert der Registry geprüft. Der Algorithmus wird
  dabei nicht der Registry-Antwort entnommen, sondern gegen eine Positivliste
  (`sha512`, `sha384`, `sha256`) geprüft — sonst könnte eine manipulierte Antwort die
  Prüfung auf ein gebrochenes Verfahren herabsetzen. Geladen wird ausschließlich von
  `https://registry.npmjs.org`, Weiterleitungen werden abgelehnt.
- Kopiert werden: `kern.css`, `kern.min.css` und die Schriftdateien unter `fonts/**`,
  beschränkt auf `.css`, `.woff2` und `.woff`
- **Nicht** kopiert: `js/kern-kopfzeile.js` und die SCSS-Quellen der Schriften. Die
  Kopfzeile wird als CSS-Variante gerendert (siehe `Organism/Kopfzeile`), das Skript
  würde also nur unbenutzt in einem web-erreichbaren Verzeichnis liegen; SCSS-Quellen
  gehören ohnehin nicht dorthin.

Die Version ist bewusst festgepinnt: KERN ändert Komponenten-Markup zwischen
Minor-Versionen, und die Component-Tests dieses Projekts prüfen exaktes Markup.

### Schriften

Von KERN mitgeliefert und in dessen `fonts/`-Verzeichnis enthalten:

| Schrift | Lizenz |
|---|---|
| Fira Sans | SIL Open Font License 1.1 |
| Noto Sans | SIL Open Font License 1.1 |

Die OFL-1.1 verlangt, dass Copyright-Hinweis und Lizenztext der Weiterverbreitung
beiliegen. `@kern-ux/native` 2.7.2 legt sie nicht bei. Wer die Fonts selbst
weiterverbreitet — etwa in einem Deployment-Artefakt — muss sie ergänzen. Die
Lizenztexte stehen bei den Upstream-Projekten
([Fira Sans](https://github.com/mozilla/Fira),
[Noto](https://github.com/notofonts/notofonts.github.io)).

### Icons

KERN kompiliert seine Icons als Data-URI-SVGs direkt in das Stylesheet; es gibt
keine separaten Icon-Dateien. Sie sind von den Google Material Symbols abgeleitet
(Apache-2.0).

## Texte und Grafiken von KERN

Die Dokumentation, Texte und Grafiken von KERN stehen unter **CC BY-NC-SA**. Die
NC-Klausel ist der Grund, warum die Dokumentation dieses Projekts vollständig selbst
geschrieben ist und keine KERN-Prosa, keine Screenshots und keine Diagramme übernimmt.
Bitte bei Beiträgen ebenso vorgehen und stattdessen auf <https://www.kern-ux.de/>
verlinken.

## Digitale Dachmarke

Die Bildwortmarke der Digitalen Dachmarke für Deutschland ist **nicht** Open Source
und ausschließlich Angeboten von Bund, Ländern und Kommunen vorbehalten. Sie wird von
diesem Projekt nicht mitgeliefert. Die KERN-Kopfzeile, die zur Dachmarke gehört, ist
standardmäßig deaktiviert. Freigaben: `dachmarke@digitalservice.bund.de`.
