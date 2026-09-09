# Installation

## Paket und Assets

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
mitgeliefert (siehe [THIRD-PARTY.md](../THIRD-PARTY.md)). Das Kommando lädt eine
festgepinnte, per SHA-512 geprüfte Version von `@kern-ux/native` nach
`Resources/Public/Vendor/KernUx/` — einmalig zur Installationszeit. Zur Laufzeit
werden **keine** Fremd-Requests ausgeführt und kein CDN eingebunden: Behördenseiten
können externe Requests in der Regel nicht abnehmen.

Das Verzeichnis ist nicht eingecheckt, der Schritt gehört also in jedes Deployment.

## Site Set

In der Site das Set **KERN UX-Standard** auswählen. Damit stehen Seiten-Templates,
Backend-Layouts, das RTE-Preset und die `kernUx.*`-Settings bereit; für den
Standardfall ist danach keine Zeile TypoScript nötig. Was sich einstellen lässt, steht
in der [Konfiguration](Configuration.md).

## Demo-Inhalte

Zum Ansehen und Prüfen gibt es einen Seitenbaum auf Kommando:

```bash
vendor/bin/typo3 kern-ux:demo:install --configure-navigation
```

Das legt 47 Seiten und 107 Inhaltselemente an: vier Beispielseiten, wie sie in einer
Verwaltung vorkommen, und **eine Seite je Inhaltstyp**, damit sich jedes Element
einzeln prüfen lässt. Dazu die drei Navigationsbäume für Hilfs-, Fußbereichs- und
Rechtsnavigation — ohne die bleiben diese Menüs abgeschaltet und der Seitenrahmen ist
nie vollständig zu sehen. Deren Wurzelseiten stehen auf „nicht im Menü": eine Seite,
die ein Menü speist, gehört nicht selbst in eines. So sieht der Baum aus in den
[Screenshots](Screenshots.md).

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

## Bekannte Falle: die Startseite von `typo3 setup`

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

## Projekte ohne `fluid_styled_content`

Diese Extension ersetzt FSC und liefert deshalb `lib.contentElement` selbst mit. Ohne
diese Definition rendert *jedes* Extbase-Plugin — auch das Formular-Plugin — als
leerer String, ohne Fehler. Wer FSC parallel betreibt, sollte wissen, dass beide
dieselbe Definition beanspruchen.
