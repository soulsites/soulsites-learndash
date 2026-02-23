# SoulSites LearnDash for Elementor

Ein professionelles WordPress-Plugin, das Elementor mit LearnDash erweitert: Display Conditions, Dynamic Tags, Widgets und Query-Filter für LearnDash-Kurse – alles nahtlos in den Elementor-Editor integriert.

---

## Systemanforderungen

| Abhängigkeit     | Mindestversion |
|------------------|----------------|
| WordPress        | 5.8            |
| PHP              | 7.4            |
| Elementor        | aktuell        |
| Elementor Pro    | aktuell        |
| LearnDash LMS    | aktuell        |

---

## Installation

1. Plugin-Verzeichnis in `/wp-content/plugins/` hochladen.
2. Plugin über das WordPress-Menü **Plugins** aktivieren.
3. Sicherstellen, dass Elementor, Elementor Pro und LearnDash installiert und aktiv sind.

---

## Features im Überblick

### 1. Display Conditions

Display Conditions steuern, wann ein Elementor-Template oder -Widget angezeigt wird.

#### Login Status

| Condition  | Beschreibung                          |
|------------|---------------------------------------|
| Logged In  | Nur für eingeloggte Benutzer          |
| Logged Out | Nur für nicht eingeloggte Benutzer    |

#### Course Enrollment

Wirkt nur auf Kurs-Seiten (`sfwd-courses` Post-Type).

| Condition    | Beschreibung                                                   |
|--------------|----------------------------------------------------------------|
| Is Enrolled  | Benutzer ist im aktuellen Kurs eingeschrieben                  |
| Not Enrolled | Benutzer ist **nicht** eingeschrieben (oder nicht eingeloggt)  |

> **Hinweis:** Beide Enrollment-Conditions greifen nur auf echten Kurs-Seiten. Auf anderen Seiten (Blog, Seiten, etc.) wird die Bedingung als nicht erfüllt (`false`) gewertet.

---

### 2. Dynamic Tags

Alle Tags befinden sich in der **LearnDash**-Gruppe des Dynamic-Tag-Auswahldialogs.

#### Kurs Kaufstatus

Zeigt einen konfigurierbaren Text basierend darauf, ob der Benutzer Zugang zum Kurs hat.

| Einstellung          | Beschreibung                        | Standard          |
|----------------------|-------------------------------------|-------------------|
| Text wenn gekauft    | Anzeige bei vorhandenem Zugang      | „Bereits gekauft" |
| Text wenn nicht gekauft | Anzeige ohne Zugang             | „Noch nicht gekauft" |

#### Kurs Preis

Zeigt den formatierten Kurs-Preis. Bei bereits eingeschriebenen Benutzern kann ein alternativer Text angezeigt oder das Feld leer gelassen werden.

| Einstellung               | Beschreibung                                        | Standard     |
|---------------------------|-----------------------------------------------------|--------------|
| Währungssymbol anzeigen   | Prepends das LearnDash-Währungssymbol              | Ja           |
| Text für kostenlose Kurse | Anzeige bei offenem/kostenlosem Kurs               | „Kostenlos"  |
| Text wenn bereits gekauft | Anzeige bei vorhandenem Zugang (leer = nichts)     | (leer)       |

> Preise werden immer mit zwei Dezimalstellen formatiert (`number_format`), unabhängig davon, ob das Währungssymbol aktiviert ist.

#### Kurs Einschreibungsstatus

Zeigt den aktuellen Einschreibungsstatus des Benutzers.

| Einstellung                | Standard              |
|----------------------------|-----------------------|
| Text wenn eingeschrieben   | „Eingeschrieben"      |
| Text wenn nicht eingeschrieben | „Nicht eingeschrieben" |
| Text wenn nicht eingeloggt | „Bitte einloggen"     |

#### Kurs Fortschritt

Gibt den Fortschritt des Benutzers im aktuellen Kurs als Prozentzahl aus.

| Einstellung                    | Standard |
|--------------------------------|----------|
| Prozentzeichen anzeigen        | Ja       |
| Text wenn nicht eingeschrieben | „0"      |

Kategorie: **Text** und **Number** – verwendbar z. B. auch als Zahl in Elementor Countern.

#### Kurs Abschlussdatum

Zeigt das Datum, an dem der Benutzer den Kurs abgeschlossen hat.

| Einstellung                | Standard               |
|----------------------------|------------------------|
| Datumsformat               | TT.MM.JJJJ             |
| Text wenn nicht abgeschlossen | „Noch nicht abgeschlossen" |
| Text wenn nicht eingeschrieben | (leer)              |

Verfügbare Datumsformate:

| Format   | Beispiel           |
|----------|--------------------|
| d.m.Y    | 15.01.2024         |
| d. F Y   | 15. Januar 2024    |
| j. F Y   | 15. Januar 2024    |
| F j, Y   | January 15, 2024   |
| Y-m-d    | 2024-01-15         |

---

### 3. Elementor Widgets

#### Kurs Fortschrittsanzeige

Ein visueller Fortschrittsbalken, der den Kursfortschritt des eingeloggten Benutzers anzeigt.

**Inhalts-Optionen:**

| Option                           | Beschreibung                                | Standard |
|----------------------------------|---------------------------------------------|----------|
| Prozentzahl anzeigen             | Prozentzahl ein-/ausblenden                 | Ja       |
| Position der Prozentzahl         | Im Balken / Oberhalb / Unterhalb            | Im Balken |
| Label anzeigen                   | Optionalen Beschriftungstext einblenden     | Nein     |
| Label Text                       | Eigener Text für das Label                  | „Kursfortschritt" |
| Text wenn nicht eingeschrieben   | Anzeige bei fehlendem Zugang               | „Nicht eingeschrieben" |
| Ausblenden wenn nicht eingeschrieben | Widget vollständig verstecken           | Nein     |

**Style-Optionen:**

- Höhe des Balkens (5–100 px)
- Eckenradius
- Hintergrundfarbe der Leiste
- Farbe des Fortschrittsbereichs
- Farbe und Typografie der Prozentzahl
- Farbe und Typografie des Labels
- Farbe des Nicht-eingeschrieben-Textes

**Barrierefreiheit:** Der Balken enthält `role="progressbar"` mit `aria-valuenow`, `aria-valuemin` und `aria-valuemax` für Screenreader.

**Editor-Vorschau:** Im Elementor-Editor wird ein Demo-Balken bei 65 % angezeigt, der alle konfigurierten Optionen (Position, Label, etc.) widerspiegelt.

---

#### Kurs Inhalt (Nur Text)

Gibt den reinen Post-Content des Kurses aus – ohne den LearnDash-Fortschrittsbalken und die automatisch eingefügte Lektionsliste.

| Option                           | Beschreibung                                                                  | Standard |
|----------------------------------|-------------------------------------------------------------------------------|----------|
| WordPress Content-Filter anwenden | Wendet `the_content`-Filter an (ohne LearnDash-spezifische Filter)          | Ja       |

**Style-Optionen:**

- Textfarbe
- Typografie
- Textausrichtung (links / zentriert / rechts / Blocksatz, responsiv)

---

### 4. Loop-Query-Filter

Ermöglicht das Filtern von LearnDash-Kursen in **Elementor Loop Grid** und **Loop Carousel** Widgets nach dem Kaufstatus des aktuell eingeloggten Benutzers.

**Aktivierung:** Im Query-Bereich des Loop-Widgets erscheint eine neue Dropdown-Option „LearnDash Kurs-Filter":

| Wert                    | Beschreibung                                              |
|-------------------------|-----------------------------------------------------------|
| Keine Filterung         | Alle Kurse werden angezeigt (Standard)                   |
| Nur gekaufte Kurse      | Nur Kurse, in die der Benutzer eingeschrieben ist        |
| Nur nicht gekaufte Kurse | Nur Kurse ohne Einschreibung des Benutzers              |

> Nicht eingeloggte Benutzer sehen beim Filter „Nur gekaufte Kurse" keine Kurse. Beim Filter „Nur nicht gekaufte Kurse" werden alle Kurse angezeigt.

---

## Verwendung

### Display Conditions

1. Template oder Seite in Elementor öffnen.
2. **Anzeigebedingungen** aufrufen.
3. Unter **Allgemein** die gewünschte Bedingung wählen:
   - `Login Status → Logged In / Logged Out`
   - `Course Enrolled → Is Enrolled / Not Enrolled`

### Dynamic Tags

1. Loop Grid/Carousel für LearnDash-Kurse erstellen.
2. Text-Widget hinzufügen.
3. Auf das Dynamic-Tag-Icon klicken.
4. Aus der **LearnDash**-Gruppe den gewünschten Tag wählen.

### Query-Filter (Loop Widget)

1. Loop Grid oder Loop Carousel anlegen.
2. Post-Type auf `sfwd-courses` setzen.
3. Im Query-Bereich des Widgets unter **LearnDash Kurs-Filter** den gewünschten Filter wählen.
4. Query-ID des Widgets auf `course_purchase_filter` setzen.

---

## Beispiel-Anwendungsfälle

### 1. Preis nur für nicht gekaufte Kurse anzeigen

```
Dynamic Tag: „Kurs Preis"
  → Währungssymbol: Ja
  → Text für kostenlose Kurse: „Kostenlos"
  → Text wenn bereits gekauft: (leer)

Display Condition: Not Enrolled
```

### 2. Unterschiedliche CTAs für eingeschriebene / nicht eingeschriebene Nutzer

```
Button 1 (Display Condition: Not Enrolled)
  Text: „Jetzt kaufen – [Kurs Preis]"

Button 2 (Display Condition: Is Enrolled)
  Text: „Weiter lernen – [Kurs Fortschritt] abgeschlossen"
```

### 3. Fortschrittsanzeige nur für eingeschriebene Nutzer

```
Widget: „Kurs Fortschrittsanzeige"
  → Ausblenden wenn nicht eingeschrieben: Ja
```

### 4. Abschlussdatum im Kurs-Loop anzeigen

```
Dynamic Tag: „Kurs Abschlussdatum"
  → Datumsformat: TT.MM.JJJJ
  → Text wenn nicht abgeschlossen: „–"
```

### 5. Loop-Grid nur mit gekauften Kursen

```
Loop Grid Widget
  Post-Type: sfwd-courses
  Query-ID: course_purchase_filter
  LearnDash Kurs-Filter: Nur gekaufte Kurse
```

---

## Mögliche Erweiterungen (Roadmap)

Die folgenden Funktionen sind für künftige Versionen geplant oder könnten bei Bedarf ergänzt werden:

### Display Conditions

- **Kurs abgeschlossen / nicht abgeschlossen** – Template nur für Nutzer anzeigen, die den Kurs beendet haben.
- **Benutzerrolle** – Bedingung basierend auf WordPress-Benutzerrollen (z. B. `subscriber`, `editor`).
- **LearnDash-Gruppe** – Bedingung basierend auf der Zugehörigkeit zu einer LearnDash-Gruppe.
- **Quiz bestanden / nicht bestanden** – Bedingung für abgelegte oder nicht abgelegte Quiz.

### Dynamic Tags

- **Anzahl Lektionen** – Gesamtanzahl oder abgeschlossene Lektionen eines Kurses.
- **Nächste Lektion (URL)** – Link zur nächsten nicht abgeschlossenen Lektion.
- **Kurs-Zertifikat-Link** – Direktlink zum Kurs-Zertifikat nach Abschluss.
- **Kurs-Instructor** – Name und/oder Link zum Kursleiter.
- **Quiz-Ergebnis** – Letzte oder beste Punktzahl eines Benutzers in einem Quiz.
- **Kurs-Bewertung** – Durchschnittliche Bewertung des Kurses (sofern LearnDash Ratings aktiviert).

### Widgets

- **Kurs-Lektionsliste** – Anpassbare Lektionsübersicht mit eigenem Design (ohne Standard-LearnDash-Optik).
- **Quiz-Fortschritts-Widget** – Visueller Fortschrittsbalken für einzelne Quiz.
- **Kurs-Ablaufdatum-Widget** – Anzeige des Zugangs-Ablaufdatums, wenn Kurszugang begrenzt ist.
- **Nächste-Lektion-Button** – Button der automatisch auf die nächste Lektion verlinkt.

### Query-Filter

- **Filter nach Kategorie/Schlagwort** – Kurse nach LearnDash-Taxonomien filtern.
- **Filter nach Abschlussstatus** – Nur abgeschlossene oder nur nicht abgeschlossene Kurse anzeigen.
- **Filter nach Gruppe** – Kurse einer bestimmten LearnDash-Gruppe anzeigen.

### Allgemeine Verbesserungen

- **Transient-Cache** – Eingeschriebene Kurse per WordPress Transients cachen für bessere Performance bei vielen Nutzern.
- **RTL-Unterstützung** – Vollständige Unterstützung von Rechts-nach-links-Sprachen im Fortschrittsbalken.
- **Übersetzungsdateien** – `.po`/`.mo`-Dateien für Deutsch und weitere Sprachen.

---

## Fehlersuche

**Plugin wird nicht geladen / Fehlermeldung im Admin:**
- Sicherstellen, dass Elementor, Elementor Pro und LearnDash alle aktiv sind.
- PHP-Version muss 7.4 oder höher sein.
- WordPress-Version muss 5.8 oder höher sein.

**Dynamic Tags erscheinen nicht im Editor:**
- Seite nach Plugin-Aktivierung neu laden.
- Elementor-Cache leeren (Elementor → Tools → Cache leeren).

**Query-Filter funktioniert nicht:**
- Query-ID des Loop Widgets muss exakt `course_purchase_filter` lauten.
- Post-Type des Widgets muss auf `sfwd-courses` gesetzt sein.
- Im Elementor-Editor wird der Filter absichtlich nicht angewendet (nur im Frontend aktiv).

**Fortschrittsbalken zeigt 0 % obwohl Fortschritt vorhanden:**
- Prüfen, ob der Benutzer wirklich im Kurs eingeschrieben ist (`sfwd_lms_has_access`).
- LearnDash-Caches leeren (falls vorhanden).

---

## Support

- **E-Mail:** support@soulsites.de
- **Website:** https://soulsites.de

---

## Changelog

### 1.2.0 (2025-02-23)

**Bugfixes:**
- `soulsites-learndash.php`: Admin-Notice-Ausgabe auf `wp_kses_post()` umgestellt (XSS-Prävention bei `printf`).
- `soulsites-learndash.php`: Elementor-Pro-Erkennung auf `ELEMENTOR_PRO_VERSION`-Konstante umgestellt (robuster als Funktionsprüfung).
- `class-course-content.php`: `echo $content` auf `wp_kses_post()` umgestellt.
- `class-course-enrolled-condition.php`: `Course_Not_Enrolled_Condition` gibt nun `false` zurück, wenn die aktuelle Seite kein Kurs ist (vorher `true` → falscher Positiv).
- `class-course-progress-bar.php`: Editor-Demo berücksichtigt jetzt alle Percentage-Positionen (oberhalb/unterhalb/im Balken) sowie das Label.
- `class-course-price.php`: Preis wird nun immer mit zwei Dezimalstellen formatiert (`number_format`), auch wenn das Währungssymbol deaktiviert ist.
- `class-course-purchase-query.php`: Redundanter `elementor_pro/query/query_args`-Filter entfernt (Doppelausführung der Filterlogik).

**Verbesserungen:**
- `class-course-progress-bar.php`: ARIA-Attribute (`role="progressbar"`, `aria-valuenow`, `aria-valuemin`, `aria-valuemax`) für Barrierefreiheit hinzugefügt.
- `languages/soulsites-learndash.pot`: POT-Datei für Übersetzungen angelegt.

### 1.1.0

- Kurs Fortschrittsanzeige Widget hinzugefügt
- Kurs Inhalt Widget hinzugefügt
- Loop-Query-Filter für Kaufstatus hinzugefügt

### 1.0.0 (2024-01-15)

- Initiales Release
- Login Status Display Conditions
- Course Enrollment Display Conditions
- 5 LearnDash Dynamic Tags (Kaufstatus, Preis, Einschreibungsstatus, Fortschritt, Abschlussdatum)

---

## Lizenz

GPL v2 oder höher – https://www.gnu.org/licenses/gpl-2.0.html

## Autor

Christian Wedel – SoulSites
https://soulsites.de
