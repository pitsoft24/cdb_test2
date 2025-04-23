class TemplateEditor {
    constructor() {
        this.templateBody = document.getElementById('templateBody');
        this.addRowBtn = document.getElementById('addRow');
        this.saveTemplateBtn = document.getElementById('saveTemplate');
        this.templates = JSON.parse(localStorage.getItem('templates')) || [];
        this.globalSearch = document.getElementById('globalSearch');
        
        this.initializeEventListeners();
        this.loadTemplates();
    }

    initializeEventListeners() {
        this.addRowBtn.addEventListener('click', () => this.addNewRow());
        this.saveTemplateBtn.addEventListener('click', () => this.saveTemplates());
        
        // Add event listener for global search
        if (this.globalSearch) {
            this.globalSearch.addEventListener('input', (e) => this.performGlobalSearch(e.target.value));
        }
    }

    performGlobalSearch(searchTerm) {
        const rows = this.templateBody.querySelectorAll('tr');
        const searchTermLower = searchTerm.toLowerCase();

        rows.forEach(row => {
            const cells = row.querySelectorAll('td');
            let found = false;

            cells.forEach(cell => {
                const cellText = cell.textContent.toLowerCase();
                if (cellText.includes(searchTermLower)) {
                    found = true;
                }
            });

            // Show/hide row based on search result
            row.style.display = found ? '' : 'none';
        });
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

function displayEntries(entries) {
    const tbody = document.querySelector('#entriesTable tbody');
    tbody.innerHTML = '';
    
    entries.forEach((entry, index) => {
        const row = document.createElement('tr');
        row.setAttribute('data-line-number', entry.lineNumber);
        row.setAttribute('data-display-number', index + 1);
        
        row.innerHTML = `
            <td><input type="checkbox" class="entry-checkbox" onchange="updateSelectedCount()"></td>
            <td>${index + 1}</td>
            <td>${entry.name || ''}</td>
            <td>${entry.ip || ''}</td>
            <td>${entry.username || ''}</td>
            <td>${entry.password || ''}</td>
            <td>${entry.enablePassword || ''}</td>
            <td>${entry.osType || ''}</td>
            <td>${entry.access || ''}</td>
            <td>${entry.clear || ''}</td>
            <td>${entry.pollInterval || ''}</td>
            <td>${entry.locationId || ''}</td>
            <td>${entry.info || ''}</td>
            <td>${entry.ticketId || ''}</td>
        `;
        tbody.appendChild(row);
    });
    updateSelectedCount();
}

function toggleAllCheckboxes(checkbox) {
    const checkboxes = document.querySelectorAll('.entry-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const selectedCount = document.querySelectorAll('.entry-checkbox:checked').length;
    const infoSpan = document.getElementById('selectedRowsInfo');
    const countSpan = document.getElementById('selectedCount');
    
    countSpan.textContent = selectedCount;
    infoSpan.style.display = selectedCount > 0 ? 'inline' : 'none';
}

async function deleteSelectedEntries() {
    const selectedRows = Array.from(document.querySelectorAll('.entry-checkbox:checked')).map(checkbox => checkbox.closest('tr'));
    
    if (selectedRows.length === 0) {
        alert('Please select at least one entry to delete.');
        return;
    }

    const confirmation = confirm(`Are you sure you want to delete ${selectedRows.length} selected entries?`);
    if (!confirmation) return;

    console.log('Starting deletion process...');
    let successCount = 0;
    let failureCount = 0;

    try {
        const deletePromises = selectedRows.map(async (row) => {
            const lineNumber = row.getAttribute('data-line-number');
            const displayNumber = row.getAttribute('data-display-number');
            
            console.log(`Attempting to delete line ${lineNumber} (display #${displayNumber})`);
            
            try {
                const response = await fetch(`api.php?action=delete&line=${lineNumber}`);
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                const result = await response.json();
                
                if (result.success) {
                    console.log(`Successfully deleted line ${lineNumber}`);
                    successCount++;
                    return true;
                } else {
                    console.error(`Failed to delete line ${lineNumber}: ${result.message}`);
                    failureCount++;
                    return false;
                }
            } catch (error) {
                console.error(`Error deleting line ${lineNumber}:`, error);
                failureCount++;
                return false;
            }
        });

        await Promise.all(deletePromises);
        
        // Refresh the entries table
        await fetchAndDisplayEntries();
        
        // Show results to user
        const message = `Deletion complete:\n${successCount} entries deleted successfully${failureCount > 0 ? `\n${failureCount} entries failed to delete` : ''}`;
        alert(message);
        
    } catch (error) {
        console.error('Error during deletion process:', error);
        alert('An error occurred during the deletion process. Please check the console for details.');
    }
} 