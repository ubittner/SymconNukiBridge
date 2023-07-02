[![Image](../imgs/NUKI_Logo.png)](https://nuki.io/de/)
### Discovery (Bridge API)
[![Image](../imgs/NUKI_Bridge.png)]()  

Dieses Modul kann die mit dem Nuki Server verbundenen Nuki Bridges ermitteln.  

Für dieses Modul besteht kein Anspruch auf Fehlerfreiheit, Weiterentwicklung, sonstige Unterstützung oder Support.  
Bevor das Modul installiert wird, sollte unbedingt ein Backup von IP-Symcon durchgeführt werden.  
Der Entwickler haftet nicht für eventuell auftretende Datenverluste oder sonstige Schäden.  
Der Nutzer stimmt den o.a. Bedingungen, sowie den Lizenzbedingungen ausdrücklich zu.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Listet die mit dem Nuki Server verbundenen Nuki Bridges auf
* Automatisches Anlegen der ausgewählten NUKI Bridge

### 2. Voraussetzungen

- IP-Symcon ab Version 6.0
- Internetverbindung
- Nuki Bridge
- Aktivierte HTTP API Funktion der Nuki Bridge mittels der Nuki iOS / Android App

[![Image](../imgs/NUKI_Bridge_HTTP_API.PNG)]()

### 3. Software-Installation

* Bei kommerzieller Nutzung (z.B. als Einrichter oder Integrator) wenden Sie sich bitte zunächst an den Autor.
* Über den Module Store das `Nuki Bridge`-Modul installieren.

### 4. Einrichten der Instanzen in IP-Symcon

- In IP-Symcon an beliebiger Stelle `Instanz hinzufügen` auswählen und `Nuki Discovery (Bridge API)` auswählen, welches unter dem Hersteller `NUKI` aufgeführt ist.
- Es wird eine neue `Nuki Discovery (Bridge API)` Konfigurator Instanz unter der Kategorie `Discovery Instanzen` angelegt.


__Konfigurationsseite__:

| Name    | Beschreibung                                               |
|---------|------------------------------------------------------------|
| Bridges | Liste die mit dem Nuki Server verbundenen Nuki Bridges auf |

__Schaltflächen__:

| Name           | Beschreibung                                                      |
|----------------|-------------------------------------------------------------------|
| Alle erstellen | Erstellt für alle aufgelisteten Nuki Bridges jeweils eine Instanz |
| Erstellen      | Erstellt für die ausgewählte Nuki Bridge eine Instanz             |

__Vorgehensweise__:

Über die Schaltfläche `AKTUALISIEREN` können Sie die Liste der verfügbaren Nuki Bridges jederzeit aktualisieren.  
Wählen Sie `ALLE ERSTELLEN` oder wählen Sie eine Nuki Bridge aus der Liste aus und drücken dann die Schaltfläche `ERSTELLEN`, um die Nuki Bridge automatisch anzulegen.  

Sobald die `Nuki Splitter (Bridge API)` Instanz erstellt wurde, nehmen Sie bitte die Konfiguration der `Nuki Splitter (Bridge API)` Instanz vor.  
Ergänzen Sie den API Token.  
Bei der Ersteinrichtung der Nuki Bridge mittels der NUKI iOS / Android App auf dem Smartphone wurden Ihnen die Daten angezeigt.  
Andere API Token, wie z.B. ein Nuki Web-API Token funktionieren nicht.  
Alternativ können Sie den API Token in der `Nuki Splitter (Bridge API)` Instanz über `BRIDGE API TOKEN ABRUFEN` automatisch ermitteln.  
Wenn Sie Ihren API Token bereits kennen, können Sie im Entwicklerbereich diesen manuell hinzufügen.

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt.  
Das Löschen einzelner kann zu Fehlfunktionen führen.

##### Statusvariablen

Es werden keine Statusvariablen angelegt.

##### Profile:

Es werden keine Profile verwendet.

### 6. WebFront

Die Nuki Discovery Bridge API Instanz hat im WebFront keine Funktionalität.

### 7. PHP-Befehlsreferenz

```text
Verbundene Nuki Bridges ermitteln: 

NUKIDB_DiscoverBridges(integer $InstanzID);  
Ermittelt die mit dem Nuki Server verbundenen Nuki Bridges.  
Diese Funktion liefert als Rückgabewert ein Array der verbundenden Nuki Bridges.  

Beispiel:  
$bridges = NUKIDB_DiscoverBridges(12345);  
//Ausgabe der Daten als Array
print_r($bridges);  
```