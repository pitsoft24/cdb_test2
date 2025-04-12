class TemplateEditor {
    constructor() {
        this.templateBody = document.getElementById('templateBody');
        this.addRowBtn = document.getElementById('addRow');
        this.saveTemplateBtn = document.getElementById('saveTemplate');
        this.templates = JSON.parse(localStorage.getItem('templates')) || [];
        
        this.initializeEventListeners();
        this.loadTemplates();
    }

    initializeEventListeners() {
        this.addRowBtn.addEventListener('click', () => this.addNewRow());
        this.saveTemplateBtn.addEventListener('click', () => this.saveTemplates());
    }

    addNewRow() {
        const row = document.createElement('tr');
        const id = Date.now();
        
        row.innerHTML = `
            <td>${id}</td>
            <td><textarea class="template-text"></textarea></td>
            <td class="action-buttons">
                <button class="edit-btn">Bearbeiten</button>
                <button class="delete-btn">Löschen</button>
            </td>
        `;

        this.templateBody.appendChild(row);

        // Event Listener für die Buttons hinzufügen
        row.querySelector('.delete-btn').addEventListener('click', () => this.deleteRow(row));
        row.querySelector('.edit-btn').addEventListener('click', () => this.toggleEdit(row));
    }

    deleteRow(row) {
        if (confirm('Möchten Sie diese Zeile wirklich löschen?')) {
            row.remove();
        }
    }

    toggleEdit(row) {
        const textarea = row.querySelector('textarea');
        const isEditing = textarea.readOnly;
        
        textarea.readOnly = !isEditing;
        const editBtn = row.querySelector('.edit-btn');
        editBtn.textContent = isEditing ? 'Bearbeiten' : 'Speichern';
    }

    saveTemplates() {
        const templates = [];
        const rows = this.templateBody.querySelectorAll('tr');
        
        rows.forEach(row => {
            const id = row.querySelector('td:first-child').textContent;
            const text = row.querySelector('textarea').value;
            
            templates.push({
                id,
                text
            });
        });

        localStorage.setItem('templates', JSON.stringify(templates));
        alert('Vorlage wurde erfolgreich gespeichert!');
    }

    loadTemplates() {
        this.templates.forEach(template => {
            const row = document.createElement('tr');
            
            row.innerHTML = `
                <td>${template.id}</td>
                <td><textarea class="template-text">${template.text}</textarea></td>
                <td class="action-buttons">
                    <button class="edit-btn">Bearbeiten</button>
                    <button class="delete-btn">Löschen</button>
                </td>
            `;

            this.templateBody.appendChild(row);

            // Event Listener für die Buttons hinzufügen
            row.querySelector('.delete-btn').addEventListener('click', () => this.deleteRow(row));
            row.querySelector('.edit-btn').addEventListener('click', () => this.toggleEdit(row));
        });
    }
}

// Initialisiere den Editor wenn das DOM geladen ist
document.addEventListener('DOMContentLoaded', () => {
    new TemplateEditor();
}); 