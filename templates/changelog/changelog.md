# Changelogs - Cleanly

Android: https://play.google.com/store/apps/details?id=de.schmoppo.cleanly

Web: https://schmoppo.de

## 1.31 - 14.04.2026

Finanz-Transaktionen können nun innerhalb von 2 Stunden nach dem Erstellen bearbeitet und gelöscht werden.
Das gilt für alle Haushaltsmitglieder, nicht nur die Person, die die Transaktion erstellt hat.

Außerdem gibt es nun eine Detailansicht für jede Transaktion, die man durch Antippen einer Transaktion in der Ausgabenliste öffnen kann.
Dort sieht man alle Details auf einen Blick, inklusive der genauen Uhrzeit und der Aufteilung unter den Mitgliedern.

![finance-transaction-detail](assets/changelog/finance-transaction-detail.png)

Beim Auswählen der Farbe einer Aufgabe werden nun die bereits im Haushalt verwendeten Farben als Schnellauswahl angezeigt.
So lassen sich Aufgaben einfach in bestehenden Farbschemata halten.

![color-picker-used-colors](assets/changelog/color-picker-used-colors.png)

Außerdem wurde ein Fehler behoben, bei dem das Klicken auf das Farbfeld im Aufgaben-Bearbeitungsformular den Farbwähler nicht öffnete.

Fehler behoben, bei dem Haushalt-Einladungen nicht angenommen werden konnten.

Die Anmelde- und Registrierungsseite wurde neu gestaltet.

![login-redesign](assets/changelog/login-redesign.png)

## 1.29 - 03.12.2025

Man kann seine Finanzen nun über Cleanly im Haushalt managen!

Wenn ihr nun einen Haushalt öffnet, seht ihr einen neuen Tab "Finanzen".
Dort könnt ihr Einnahmen und Ausgaben hinzufügen, Kategorien zuweisen und den Überblick über eure Finanzen behalten.
Es gibt eine Übersichtsseite, die euch sagt, wer wem wie viel Geld schuldet, um alles auszugleichen.

![finanzübersicht](assets/changelog/finanzübersicht.png)

Es gibt auch eine Historie über die Ausgaben:

![ausgabenübersicht](assets/changelog/ausgabenübersicht.png)

Beim Hinzufügen von Transaktionen muss man vorerst auswählen, ob es eine Einnahme, eine Ausgabe oder ein Transfer im Haushalt ist:

![transaktionsarten](assets/changelog/transaktionsarten.png)

Dann stellt man ein, wie es aufgeteilt werden soll:

![aufteilungsformular](assets/changelog/aufteilungsformular.png)

Und nach ein paar weiteren Details ist die Transaktion erstellt!

![transaktionsformular](assets/changelog/transaktionsformular.png)

## 1.28 - 13.07.2025

Fehler behoben, bei dem die Ansicht der App über den Bildschirmrand hinaus begab.
Fehler behoben, bei dem die Android-Bildschirmtastatur die Ansicht der App überlappte.

## 1.27 - 20.06.2025

Checklisten-Einträge werden nun nicht mehr sofort gelöscht, wenn man sie abhakt!

![checklist_abhakbar](assets/changelog/checklist_abhakbar.png)

Damit kann man sie wiederherstellen, nachdem man sie versehentlich abgehakt hat.

![checklist_abgehakt](assets/changelog/checklist_abgehakt.png)

Und natürlich kann man auch wieder aufräumen, mit dem "Abgehakte löschen"-Button!

## 1.26 - 09.02.2025

Abhängigkeiten wurden aus Sicherheitsgründen aktualisiert.

## 1.25 - 23.07.2024

Es wird jetzt die Sprache "Schwäbisch" unterstützt!
![schwobi](assets/changelog/schwobi.png)

## 1.24 - 22.07.2024

Checklisten können nun sortiert werden!

![checklist-sort](assets/changelog/checklist-sort.png)

## 1.23 - 15.07.2024

Haptisches Feedback wurde eingeführt!
Bei positiver und negativer Rückmeldung von Aktionen wird das Gerät vibrieren. Dies kann über die Android Betriebssystempräferenzen unterbunden werden.
Aufgaben sortieren sich nun mit weichen Animationen um, wenn sie abgehakt wurden.

Aufgaben haben einen Ladeindikator, nachdem man sie abgehakt hat, bevor es eine Erfolgsrückmeldung gibt.

![task-loading-indicator](assets/changelog/task-loading-indicator.png)

Die Animationen vom Umsortieren der Checklisten-Einträge wurde verbessert.

Checklisten sind abonnierbar. Wenn eine Checkliste abonniert ist und ein anderes Haushaltsmitglied diese bearbeitet, dann wird über eine Push-Benachrichtigung darüber informiert. Dies kann einmal alle 30 Minuten passieren.

![checklist-subscription](assets/changelog/checklist-subscription.png)

## 1.22 - 12.07.2024

Nachrichten können mit einer Wischgeste wieder erledigt werden!
<video src="assets/changelog/swipe-to-done.mp4" autoplay loop muted playsinline style="max-width: 100%;"></video>

Lange Checklisten-Einträge werden nun automatisch umgebrochen. 

## 1.20 - 14.03.2024

Pro Haushalt kann es nun mehr als eine Checkliste geben!

![multiple checklists](assets/changelog/multiple-checklist.png)

## 1.19 - 19.04.2023

Vor dem Löschen einer Aufgabe wird nun vorsichtshalber nach Bestätigung gefragt.
Dadurch werden auch alle Aufgabenprotokolle gelöscht.

## 1.18 - 17.04.2023

Die App ist nun robuster gegenüber Verbindungsabbrüchen.
Außerdem lädt die Oberfläche schneller, da der letzte Zustand der geöffneten App jetzt lokal gespeichert wird.
Dadurch kann man zum Beispiel die Checkliste auch im Supermarkt aufrufen und abhaken, auch wenn dort keine
Internetverbindung ist.
Das wird Synchronisiert, sobald die Verbindung wieder hergestellt ist.

Die Statistiken sind im Dark-Mode besser lesbar.

![statistics darkmode](assets/changelog/statistics-darkmode.png)

![punctuality darkmode](assets/changelog/punctuality-darkmode.png)

## 1.17 - 08.01.2023

Aufgaben können nun Mitgliedern zugewiesen werden!

![assignment](assets/changelog/assignment.png)

Außerdem kann man nun einstellen, was passieren soll, wenn man eine Aufgabe erledigt hat, zu der man zugewiesen war.
Die Möglichkeiten sind:

* **Nichts tun** - Die Zuweisung bleibt bestehen und man muss sich manuell darum kümmern.
* **Zuweisung entfernen** - Nachdem etwas erledigt wurde, ist immer niemand mehr zugewiesen.
* **Rotieren** - Die Person, die diese Aufgabe am längsten nicht erledigt hat, wird automatisch zugewiesen.

![reassignment-strategy](assets/changelog/reassignment-strategy.png)

## 1.16 - 28.12.2022

Bei (noch) leeren Ansichten werden Hinweise angezeigt, was man auf den Ansichten sehen könnte.

## 1.15 - 27.12.2022

Pull-To-Refresh wird nicht mehr versehentlich ausgelöst.

Aufgaben können schneller gefunden werden, indem nach ihrem Icon gefiltert werden kann.

![category](assets/changelog/category.png)

## 1.14 - 26.12.2022

Das Wechseln des Farbschemas wird nun auch bei Aufgabenfarben direkt widergespiegelt.

Die den Nutzer zugeordneten Farben in den Haushaltstatistiken sind nun über alle Aufgaben konsistent.

Das Iconset "ionicons" wurde mit dem Iconset "tabler-icons" ersetzt.

## 1.13 - 24.12.2022

Absturz behoben beim Aufrufen der App aus dem Hintergrund bei offener Login-Maske.

Wenn man Mitglied in exakt einem Haushalt ist, wird dieser als Startseite genommen, statt der Übersicht aller Haushalte.

In der Übersicht aller Haushalte wurden die redundanten Aktionen hinter dem Kontextmenü entfernt.

Changelogs sind nun nicht mehr in der App ausgeliefert, sondern über https://cleanly.schmoppo.de/changelog erreichbar.
Das reduziert die App-Größe nachhaltig und verschnellert das aktualisieren der App.

Die Aufgabenfarben können nun nach fließenden Farbtönen eingestellt werden. Außerdem sind die Kontraste der Farben im
Lightmode und im Darkmode so angepasst, dass sie lesbar bleiben.

![color-picker](assets/changelog/color-picker.png)

## 1.12 - 23.12.2022

Die erzwungene Wartezeit zwischen dem mehrmaligen Erledigen derselben Aufgabe ist nun benutzerbezogen.

Einladungen werden ohne aktives Neuladen in der Oberfläche angezeigt.

Das Aufrufen der App aus dem Hintergrund aktualisiert nun den Zustand aller Haushalte.

Fehler behoben, bei dem man die Login-Maske durch Aktualisieren der Seite vor dem Einloggen umgehen konnte.

Die Fortschrittsanzeigen bei Aufgaben mit Wiederholungsintervall wurden durch farbige Balken mit einstellbaren Farben
ersetzt.

![task-colors](assets/changelog/task-colors.png)

## 1.11 - 22.12.2022

Eine Webhook-Funktionalität pro Haushalt wurde hinzugefügt!
Bei dieser kann man seine eigenen Services über erledigte Aufgaben informieren lassen.
Mehr dazu hier: http://cleanly.schmoppo.de/webhook/doc

![webhooks](assets/changelog/webhooks.png)

Icon-Namen sind nun übersetzt.

Die App weist auf Aktualisierungen hin.

Push-Benachrichtigungsrechte werden wieder korrekt angefragt.

Kollaborativen Arbeiten in der Checkliste repariert.

## 1.9 - 12.12.2022

Es gibt ein neuer Tab in Haushalten - **Statistiken**.

![statistics](assets/changelog/statistics.png)

Dort kann man sich anschauen, wie *Pünktlich* man die Aufgaben im Schnitt in der Vergangenheit erledigt hat.

![punctuality](assets/changelog/punctuality.png)

Zusätzlich kann man die Beitragsverhältnisse der Mitglieder an den jeweiligen Aufgaben ablesen.

![participation](assets/changelog/participation.png)

## 1.8.1 - 11.12.2022

Push-Benachrichtigungen, die man bei laufender App erhalten hat, können nun auch angeklickt werden.
Wie gewohnt öffnen sie darauf die App und werden entfernt.

## 1.8 - 10.12.2022

Aufgaben können nun ohne Wiederholungsdatum eingestellt werden!

Diese Aufgaben senden keine Push-Benachrichtigungen, wenn sie lange nicht erledigt werden, denn sie können nie
*dringlich* werden.

![nonrepeating](assets/changelog/nonrepeating.png)

## 1.7 - 08.12.2022

Das Ändern der Sterne einer Aufgabe ändert die erarbeiteten Sterne nicht mehr rückwirkend für alle Mitglieder des
Haushaltes.

Der Aktivitäts-Tab lädt nicht mehr alle Aktivitätseinträge auf einmal, sondern lädt beim Scrollen in die Vergangenheit
Einträge nach.

![Infinite scroll](assets/changelog/infinite-scroll.png)

Erfolgs-, Warnungs- und Fehlermeldungen können nun frühzeitig ausgeblendet werden.

![Dismiss](assets/changelog/dismiss.png)

## 1.6 - 04.12.2022

Beim Erledigen einer Aufgabe wurde die Wischgeste mit einem Antippen ersetzt.

![Mark done swipe](assets/changelog/mark-done-swipe.png)

Neue Aufgaben können aus der Aufgabenübersicht heraus erstellt werden.

![Create tasks](assets/changelog/new-task-from-overview.png)

Es gibt zusätzlich zu den Rollen "Administrator" und "Mitglied" nun die Rolle "Moderator".
Diese Rolle kann Aufgaben anlegen und bearbeiten.

![Moderator](assets/changelog/moderator.png)

Interaktionen mit Aufgaben (Erstellen, Erledigen, Löschen, Editieren) geben (Miss-)Erfolgsrückmeldung.

## 1.5 - 15.08.2022

Man kann die Sprache nun wechseln. Es stehen die Sprachen "Deutsch" und "Englisch" zur Verfügung.

![Language settings](assets/changelog/language-settings.png)

Es wurde ein Sternesystem eingeführt, mit dem Aufgaben im Wert gewichtet werden können.

![Stars overview](assets/changelog/stars-overview.png)

Dadurch können Mitglieder eine Übersicht darüber bekommen, wer wie viel in der Vergangenheit erledigt hat.

![Stars member](assets/changelog/stars-member.png)

## 1.4.2 - 12.08.2022

Registrationen müssen ab sofort mit der bestätigung der E-Mail-Adresse abgeschlossen werden.

Benachrichtigungseinstellungen hinzugefügt, die einem erlauben Push-Benachrichtigungen für explizite Ereignisse
feingranular zu deaktivieren.

![Notification settings](assets/changelog/notification-settings.png)

Man wird nun benachrichtigt, wenn Aufgaben dringend werden.

Kleinere Fehlerbehebungen:

* "Aufgabe bearbeiten" repariert.
* Die Checkliste kann wieder sortiert werden.

## 1.4.1 - 30.07.2022

Fehler behoben, bei dem

* man regelmäßig zu früh ausgeloggt wurde
* sich das "Mitglieder einladen"-Fenster nicht mehr schloß
* man keine Rückmeldung beim Abhaken von Aufgaben bekam
* die Checkliste nicht mehr speicherte

## 1.4 - 28.07.2022

Änderungen werden in der App nun unter dem Menüeintrag "Änderungen" veröffentlicht.

![Changelog](assets/changelog/changelogs.png)

Man kann Zugriff zum Account bei verlorenem Passwort über eine "Passwort vergessen"-Funktion wiedererlangen.
Dies ist bislang nur über den folgenden direkt Link erreichbar: https://cleanly.schmoppo.de/reset-password

Unterstützung für Push-Benachrichtigungen wurde hinzugefügt! Damit wird man jetzt informiert, wenn Aufgaben in einem
Haushalt erledigt wurden oder wenn man zu einem Haushalt eingeladen wurde!

![Push notifications](assets/changelog/push.jpg)

Beim Erstellen eines Checklisteneintrages wird dieser direkt fokussiert.

## 1.3.2 - 23.07.2022

Aktivitäten innerhalb eines Haushalts sind jetzt unter dem Tab "Aktivitäten" einsehbar.

*Aktivitäten:*
![Activity](assets/changelog/activity.png)

Aktionen in den Haushalteinstellungen werden nun ausgeblendet, wenn die Rechte fehlen um diese auszuführen.

*Perspektive als Besitzer:*
![Normal settings](assets/changelog/normal-settings.png)

*Perspektive als Mitglied:*
![Hidden settings](assets/changelog/hidden-settings.png)

Fehler behoben, bei dem der Home-Button manchmal nicht funktioniert hat.

## 1.3.1 - 18.07.2022

Aktuelle Änderungen anderer Nutzer in der geteilten Checkliste werden in echtzeit angezeigt.

Änderungen in der geteilten Checkliste haben jetzt Animationen.

## 1.3.0 - 07.07.2022

Geteilte Checkliste für Haushalte hinzugefügt.

Besitzrechte eines Haushalts können nun übertragen werden.

Aufgaben können nun auch nach der Erstellung bearbeitet werden.

Haushalte können verlassen werden.

Die Sitzung läuft nicht mehr nach 90 Tagen ab, sondern erneuert sich regelmäßig.~~
