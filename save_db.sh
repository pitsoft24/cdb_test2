#!/bin/bash

# Sichere die Header der originalen db.txt
grep "^#" db.txt > temp_header.txt

# Lese die neuen Daten von der Standardeingabe
cat > temp_data.txt

# Kombiniere Header und neue Daten
cat temp_header.txt temp_data.txt > db.txt

# Aufräumen
rm temp_header.txt temp_data.txt

echo "Änderungen wurden gespeichert!" 