# SoulSites LearnDash for Elementor

Ein professionelles WordPress-Plugin, das Elementor mit LearnDash erweitert und Display Conditions sowie Dynamic Tags für LearnDash-Kurse hinzufügt.

## Features

### Display Conditions

#### Login Status
- **Logged In** - Zeigt Inhalte nur für eingeloggte Benutzer an
- **Logged Out** - Zeigt Inhalte nur für ausgeloggte Benutzer an

#### Course Enrollment
- **Is Enrolled** - Zeigt Inhalte nur für Benutzer an, die im aktuellen Kurs eingeschrieben sind
- **Not Enrolled** - Zeigt Inhalte nur für Benutzer an, die NICHT im aktuellen Kurs eingeschrieben sind

### Dynamic Tags

Alle Dynamic Tags sind in der neuen **LearnDash**-Gruppe in Elementor verfügbar.

#### 1. Kurs Kaufstatus
Zeigt an, ob der Benutzer den Kurs bereits gekauft hat oder nicht.

**Einstellungen:**
- Text wenn gekauft
- Text wenn nicht gekauft

**Anwendungsbeispiel:**
```
In Loop-Elementen: Zeige "Bereits gekauft" oder "Noch nicht gekauft"
```

#### 2. Kurs Preis
Zeigt den Preis des Kurses an (nur wenn nicht gekauft).

**Einstellungen:**
- Währungssymbol anzeigen (Ja/Nein)
- Text für kostenlose Kurse
- Text wenn bereits gekauft (leer lassen um nichts anzuzeigen)

**Anwendungsbeispiel:**
```
In Loop-Elementen: Zeige "€ 99,00" wenn nicht gekauft, oder nichts wenn bereits gekauft
```

#### 3. Kurs Einschreibungsstatus
Zeigt den Einschreibungsstatus des Benutzers an.

**Einstellungen:**
- Text wenn eingeschrieben
- Text wenn nicht eingeschrieben
- Text wenn nicht eingeloggt

#### 4. Kurs Fortschritt
Zeigt den Fortschritt des Benutzers im Kurs in Prozent an.

**Einstellungen:**
- Prozentzeichen anzeigen (Ja/Nein)
- Text wenn nicht eingeschrieben

**Anwendungsbeispiel:**
```
Zeige: "75%" oder "0%" wenn nicht eingeschrieben
```

#### 5. Kurs Abschlussdatum
Zeigt das Datum an, an dem der Benutzer den Kurs abgeschlossen hat.

**Einstellungen:**
- Datumsformat (TT.MM.JJJJ, TT. Monat JJJJ, etc.)
- Text wenn nicht abgeschlossen
- Text wenn nicht eingeschrieben

**Anwendungsbeispiel:**
```
Zeige: "15.01.2024" oder "Noch nicht abgeschlossen"
```

## Installation

1. Lade das Plugin-Verzeichnis in `/wp-content/plugins/` hoch
2. Aktiviere das Plugin über das 'Plugins'-Menü in WordPress
3. Stelle sicher, dass Elementor, Elementor Pro und LearnDash installiert sind

## Systemanforderungen

- WordPress 5.8 oder höher
- PHP 7.4 oder höher
- Elementor (aktuelle Version)
- Elementor Pro (aktuelle Version)
- LearnDash LMS (aktuelle Version)

## Verwendung in Elementor

### Display Conditions verwenden

1. Bearbeite ein Template oder eine Seite in Elementor
2. Gehe zu den Display Conditions
3. Wähle unter "General" die gewünschte Condition:
   - **Login Status** → Logged In / Logged Out
   - **Course Enrolled** → Is Enrolled / Not Enrolled

### Dynamic Tags verwenden

1. Erstelle ein Loop Grid/Carousel für LearnDash-Kurse
2. Füge ein Text-Widget hinzu
3. Klicke auf das Dynamic Tag Icon
4. Wähle aus der **LearnDash**-Gruppe den gewünschten Tag:
   - Kurs Kaufstatus
   - Kurs Preis
   - Kurs Einschreibungsstatus
   - Kurs Fortschritt
   - Kurs Abschlussdatum

## Beispiel-Anwendungsfälle

### 1. Preis nur für nicht gekaufte Kurse anzeigen

```
Dynamic Tag: "Kurs Preis"
Einstellungen:
- Währungssymbol: Ja
- Text für kostenlose Kurse: "Kostenlos"
- Text wenn bereits gekauft: (leer lassen)
```

### 2. Unterschiedliche CTAs für eingeschriebene/nicht eingeschriebene Benutzer

```
Button 1 (mit Display Condition "Not Enrolled"):
Text: "Jetzt kaufen für [Kurs Preis]"

Button 2 (mit Display Condition "Is Enrolled"):
Text: "Fortschritt: [Kurs Fortschritt] - Weiter lernen"
```

### 3. Fortschrittsanzeige in Kursübersicht

```
Text Widget:
"Du hast [Kurs Fortschritt] abgeschlossen"

Display Condition: Is Enrolled
```

## Support

Bei Fragen oder Problemen:
- E-Mail: support@soulsites.de
- Website: https://soulsites.de

## Changelog

### 1.0.0 (2024-01-15)
- Initiales Release
- Login Status Display Conditions
- Course Enrollment Display Conditions
- 5 LearnDash Dynamic Tags (Kaufstatus, Preis, Einschreibungsstatus, Fortschritt, Abschlussdatum)

## Lizenz

GPL v2 oder höher

## Autor

Christian Wedel - SoulSites
https://soulsites.de
