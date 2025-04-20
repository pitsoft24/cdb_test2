#!/bin/bash

# Sichere die Header der originalen db.txt
grep "^#" db.txt > temp_header.txt

# Lese die neuen Daten von der Standardeingabe
cat > temp_data.txt

# Kombiniere Header und neue Daten
cat temp_header.txt temp_data.txt > db.txt

# Erstelle eine Kopie mit Zeitstempel im History-Verzeichnis
timestamp=$(date +"%Y_%m_%d_%H_%M")
cp db.txt history/cdb_${timestamp}.txt

# Aufräumen
rm temp_header.txt temp_data.txt

echo "Änderungen wurden gespeichert und eine Kopie wurde im History-Verzeichnis erstellt!" 