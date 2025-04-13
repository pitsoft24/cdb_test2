#!/bin/bash

# Prüfe ob eine neue db.txt im Download-Ordner existiert
DOWNLOAD_DIR="$HOME/Downloads"
NEW_DB="$DOWNLOAD_DIR/db.txt"
CURRENT_DB="db.txt"

if [ -f "$NEW_DB" ]; then
    # Sichere die alte Datei
    cp "$CURRENT_DB" "${CURRENT_DB}.bak"
    
    # Kopiere die neue Datei
    mv "$NEW_DB" "$CURRENT_DB"
    
    echo "DB wurde erfolgreich aktualisiert!"
    echo "Eine Sicherungskopie wurde unter ${CURRENT_DB}.bak gespeichert."
else
    echo "Keine neue db.txt im Download-Ordner gefunden."
    echo "Bitte speichern Sie zuerst die Änderungen in der Webanwendung."
fi 