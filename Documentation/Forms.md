# Formulare

`ext:form` wird über ein Form Set konfiguriert
(`Configuration/Form/KernUx/config.yaml`, `priority: 200`). Auf TYPO3 14 wird es
automatisch gefunden; auf 13 gibt es noch keine Form Sets, dort registriert
`ext_localconf.php` dieselbe Datei über `yamlConfigurations` — versionsgeschützt,
weil 14.2 diese API deprecated hat.

## Zwei Partials tragen die Regeln

Die KERN-Formularregeln sitzen in **zwei** Partials, nicht in den 30 Element-Partials:
`Field/Field.html` für die `kern-form-input`-Familie und `Field/Group.html` für
Checkbox- und Radio-Gruppen. Was dort einmal implementiert ist:

- **Optionale Felder werden markiert, nicht die Pflichtfelder** — die Umkehrung der
  `ext:form`-Konvention. Pflicht wird über `aria-required` vermittelt, nicht über das
  native `required`-Attribut.
- **Drei Fehlersignale gleichzeitig**: Modifier am Wrapper, Modifier am Feld,
  `aria-invalid`. Nur eines davon wäre Zustand allein durch Farbe (WCAG 1.4.1).
- **`aria-describedby` in der Reihenfolge Hinweis, dann Fehler** — so macht es KERNs
  Plain-Kit. Das React-Kit macht es umgekehrt; wir folgen dem Plain-Kit.
- **Bei Gruppen** trägt das `fieldset` das `aria-describedby` *und* das
  `aria-required` — es bildet `role="group"` ab, und die `legend` ist der zugängliche
  Name. An jedem einzelnen Kind zu wiederholen hieße, jede Option sei für sich
  erforderlich; das stimmt weder für eine Radio-Gruppe noch für eine Checkbox-Gruppe,
  die mindestens eine Auswahl verlangt. Jeder Kind-Input trägt aber zusätzlich
  `aria-invalid` und die Fehlerklasse.
- **`fluidAdditionalAttributes` des Elements werden durchgereicht**, mit den
  ARIA-Attributen darüber. Ohne das fiele alles weg, was der Formular-Editor in diese
  Eigenschaft schreibt — vor allem `autocomplete`, ohne das WCAG 1.3.5 überhaupt nicht
  erfüllbar ist, dazu `placeholder`, `minlength`/`maxlength`, `min`/`max`, `step` und
  `pattern`. Was die Attribute des Kontrakts selbst überschreiben würde
  (`aria-describedby`, `aria-invalid`, `aria-required`) wird verworfen: kein Redakteur
  soll die Zusagen von Hand aushängen können.

## Strecke und Zusammenfassung

Dazu kommen eine Fehlerübersicht mit Sprungmarken (`kern-alert--danger` mit
`role="alert"` — hier korrekt, weil das Markup erst nach einem fehlgeschlagenen
Absenden existiert) und eine Fortschrittsanzeige für mehrseitige Formulare.

Die Zusammenfassungsseite rendert `formvh:renderAllFormValues`; ein `Fieldset` wird
dabei zu einer Gruppe mit `kern-summary-group__header`. Alles davon hält
`Tests/Functional/Form/FormMarkupTest.php` fest, indem es ganze Formulare rendert —
ein Parse-Test kann das nicht: eine Variable, die es nicht gibt, ist gültiges Fluid und
rendert stillschweigend nichts.

## `KernDate`

Ein Datum als drei Felder, wie KERN es vorschreibt. Kein `<input type="date">` und kein
JavaScript-Datepicker — und TYPO3 14 hat sein eigenes DatePicker-Element ohnehin
deprecated (#109152).

Der eingegebene Wert bleibt ein Array aus Tag, Monat und Jahr, weil Property Mapping
vor der Validierung läuft: mit `DateTime` als Ziel würde aus „31.02." ein
Mapping-Fehler, und `KernDateValidator` verlöre seine eigenen, genauen Fehlercodes.
Angezeigt wird das Datum deshalb über `kux:formDateValue` — `ext:form`s eigener Ausweg
(`StringableFormElementInterface`) greift hier nicht, weil `RenderFormValueViewHelper`
ihn nur für *Objekte* aufruft und ein Array unverändert zurückgibt.

> [!IMPORTANT]
> **Projekte ohne `fluid_styled_content`:** Diese Extension ersetzt FSC und liefert
> deshalb `lib.contentElement` selbst mit. Ohne diese Definition rendert *jedes*
> Extbase-Plugin — auch das Formular-Plugin — als leerer String, ohne Fehler.
