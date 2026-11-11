export function replaceTemplateVariables(content, variables) {
    for (const [key, value] of Object.entries(variables || {})) {
        const escapedKey = key.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const text = value === null || value === undefined || value === '' ? 'N/A' : String(value);
        const escaped = text.replace(/[&<>"']/g, char => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[char]));
        const token = new RegExp(`(?:\\{|&#123;|&lcub;){1,2}[\\s\\u200B-\\u200D\\uFEFF]*${escapedKey}[\\s\\u200B-\\u200D\\uFEFF]*(?:\\}|&#125;|&rcub;){1,2}`, 'gi');
        content = content.replace(token, () => escaped);
    }
    return content;
}
