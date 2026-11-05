export function enableSessionDraft(editor, key, hasValidationInput = false) {
    const navigation = performance.getEntriesByType('navigation')[0];
    const initialProject = editor.getProjectData();
    let restoring = false;

    const removeDraft = () => {
        try {
            sessionStorage.removeItem(key);
        } catch (error) {
            // Editing remains available when browser storage is disabled.
        }
    };

    // A draft belongs only to a reload of this editing visit. A fresh visit,
    // including returning after saving, starts with the server's template.
    try {
        const draft = sessionStorage.getItem(key);
        removeDraft();
        if (navigation?.type === 'reload' && draft && !hasValidationInput) {
            editor.loadProjectData(JSON.parse(draft));
        }
    } catch (error) {
        removeDraft();
    }

    const saveDraft = () => {
        if (restoring) return;
        try {
            sessionStorage.setItem(key, JSON.stringify(editor.getProjectData()));
        } catch (error) {
            // Storage limits must not interrupt editing or explicit saving.
        }
    };

    editor.on('update', saveDraft);
    // Flush the current project when refreshing, even before the next update.
    window.addEventListener('pagehide', saveDraft);
    const handlePageShow = event => {
        if (!event.persisted) return;
        // Back/forward cache can restore the entire old editor without running
        // initialization again. Treat this as a new visit, not a refresh.
        restoring = true;
        removeDraft();
        editor.loadProjectData(initialProject);
        restoring = false;
    };
    window.addEventListener('pageshow', handlePageShow);

    editor.on('destroy', () => {
        editor.off('update', saveDraft);
        window.removeEventListener('pagehide', saveDraft);
        window.removeEventListener('pageshow', handlePageShow);
    });
}
