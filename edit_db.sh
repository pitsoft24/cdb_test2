#!/bin/bash

# Funktion zum Anzeigen des Menüs
show_menu() {
    clear
    echo "DB Editor"
    echo "=========="
    echo "1) Zeige alle Einträge"
    echo "2) Neuen Eintrag hinzufügen"
    echo "3) Eintrag löschen"
    echo "4) Eintrag bearbeiten"
    echo "5) Beenden"
    echo
}

# Funktion zum Anzeigen aller Einträge
show_entries() {
    clear
    echo "Aktuelle Einträge:"
    echo "================="
    grep -v "^#" db.txt | nl
    echo
    read -p "Drücke ENTER zum Fortfahren..."
}

# Funktion zum Hinzufügen eines neuen Eintrags
add_entry() {
    clear
    echo "Neuen Eintrag hinzufügen"
    echo "======================"
    
    read -p "Name (6-9 Zeichen): " name
    read -p "IP-Adresse: " ip
    read -p "Username [scr-user]: " username
    username=${username:-scr-user}
    read -p "Password [scr-pass]: " password
    password=${password:-scr-pass}
    read -p "Enable Password [none]: " enable_pwd
    enable_pwd=${enable_pwd:-none}
    
    echo "OS Type (IOS/COS/NOS/JUNOS/EW/ASA/FGT):"
    select os_type in "IOS" "COS" "NOS" "JUNOS" "EW" "ASA" "FGT"; do
        if [ -n "$os_type" ]; then
            break
        fi
    done
    
    echo "Access Type:"
    select access in "SSH" "TEL"; do
        if [ -n "$access" ]; then
            break
        fi
    done
    
    echo "Clear Status:"
    select clear in "CL" "NOCL" "OFF"; do
        if [ -n "$clear" ]; then
            break
        fi
    done
    
    read -p "Poll Interval (1-59) [15]: " poll
    poll=${poll:-15}
    read -p "Location ID: " llid
    read -p "Info: " info
    read -p "Ticket ID: " tid
    
    # Füge den neuen Eintrag zur Datei hinzu
    echo "${name}:${ip}:${username}:${password}:${enable_pwd}:${os_type}:${access}:${clear}:${poll}:${llid}:${info}:${tid}" >> db.txt
    
    echo "Eintrag wurde hinzugefügt!"
    read -p "Drücke ENTER zum Fortfahren..."
}

# Funktion zum Löschen eines Eintrags
delete_entry() {
    clear
    echo "Eintrag löschen"
    echo "=============="
    grep -v "^#" db.txt | nl
    
    read -p "Nummer des zu löschenden Eintrags (0 zum Abbrechen): " number
    
    if [ "$number" -gt 0 ]; then
        # Sichere die Header
        grep "^#" db.txt > temp_db.txt
        # Füge alle Zeilen außer der zu löschenden hinzu
        grep -v "^#" db.txt | sed "${number}d" >> temp_db.txt
        mv temp_db.txt db.txt
        echo "Eintrag wurde gelöscht!"
    fi
    
    read -p "Drücke ENTER zum Fortfahren..."
}

# Funktion zum Bearbeiten eines Eintrags
edit_entry() {
    clear
    echo "Eintrag bearbeiten"
    echo "================"
    grep -v "^#" db.txt | nl
    
    read -p "Nummer des zu bearbeitenden Eintrags (0 zum Abbrechen): " number
    
    if [ "$number" -gt 0 ]; then
        # Hole den ausgewählten Eintrag
        entry=$(grep -v "^#" db.txt | sed -n "${number}p")
        IFS=':' read -r name ip username password enable_pwd os_type access clear poll llid info tid <<< "$entry"
        
        clear
        echo "Aktuelle Werte (Enter = keine Änderung):"
        read -p "Name [$name]: " new_name
        name=${new_name:-$name}
        read -p "IP [$ip]: " new_ip
        ip=${new_ip:-$ip}
        read -p "Username [$username]: " new_username
        username=${new_username:-$username}
        read -p "Password [$password]: " new_password
        password=${new_password:-$password}
        read -p "Enable Password [$enable_pwd]: " new_enable_pwd
        enable_pwd=${new_enable_pwd:-$enable_pwd}
        read -p "OS Type [$os_type]: " new_os_type
        os_type=${new_os_type:-$os_type}
        read -p "Access [$access]: " new_access
        access=${new_access:-$access}
        read -p "Clear [$clear]: " new_clear
        clear=${new_clear:-$clear}
        read -p "Poll [$poll]: " new_poll
        poll=${new_poll:-$poll}
        read -p "LLID [$llid]: " new_llid
        llid=${new_llid:-$llid}
        read -p "Info [$info]: " new_info
        info=${new_info:-$info}
        read -p "TID [$tid]: " new_tid
        tid=${new_tid:-$tid}
        
        # Sichere die Header
        grep "^#" db.txt > temp_db.txt
        # Füge alle Zeilen außer der zu bearbeitenden hinzu
        grep -v "^#" db.txt | sed "${number}d" >> temp_db.txt
        # Füge die bearbeitete Zeile hinzu
        echo "${name}:${ip}:${username}:${password}:${enable_pwd}:${os_type}:${access}:${clear}:${poll}:${llid}:${info}:${tid}" >> temp_db.txt
        mv temp_db.txt db.txt
        echo "Eintrag wurde aktualisiert!"
    fi
    
    read -p "Drücke ENTER zum Fortfahren..."
}

# Hauptprogramm
while true; do
    show_menu
    read -p "Wähle eine Option (1-5): " choice
    
    case $choice in
        1) show_entries ;;
        2) add_entry ;;
        3) delete_entry ;;
        4) edit_entry ;;
        5) echo "Programm wird beendet."; exit 0 ;;
        *) echo "Ungültige Option!" ;;
    esac
done 