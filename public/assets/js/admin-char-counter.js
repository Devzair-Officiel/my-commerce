/**
 * Compteur de caractères pour les champs texte du formulaire produit (EasyAdmin).
 *
 * Champs couverts :
 *   - Product_description       (Trix, min 10)
 *   - Product_more_description  (Textarea, max 5000)
 *   - Product_additional_infos  (Trix, max 5000)
 */

const TRIX_FIELDS = {
    'Product_description':      { min: 10 },
    'Product_additional_infos': { max: 5000 },
};

document.addEventListener('DOMContentLoaded', () => {
    initTextareaCounter('Product_more_description', { max: 5000 });

    // Trix déjà initialisé : on parcourt les éditeurs présents dans le DOM
    document.querySelectorAll('trix-editor').forEach((editor) => {
        const inputId = editor.getAttribute('input');
        if (inputId && TRIX_FIELDS[inputId]) {
            initTrixCounter(editor, TRIX_FIELDS[inputId]);
        }
    });
});

// Trix pas encore initialisé au DOMContentLoaded : on écoute l'événement
document.addEventListener('trix-initialize', (e) => {
    const inputId = e.target.getAttribute('input');
    if (inputId && TRIX_FIELDS[inputId]) {
        initTrixCounter(e.target, TRIX_FIELDS[inputId]);
    }
});

// ---------------------------------------------------------------------------

function initTextareaCounter(id, limits) {
    const textarea = document.getElementById(id);
    if (!textarea) return;

    const counter = createCounter();
    textarea.parentNode.insertBefore(counter, textarea.nextSibling);

    renderCounter(counter, textarea.value.length, limits);
    textarea.addEventListener('input', () => renderCounter(counter, textarea.value.length, limits));
}

function initTrixCounter(trixEditor, limits) {
    if (trixEditor.nextSibling?.classList?.contains('ea-char-counter')) return;

    const counter = createCounter();
    trixEditor.parentNode.insertBefore(counter, trixEditor.nextSibling);

    // Trix ajoute un \n de fin de document, on le soustrait
    const getLength = () => trixEditor.editor.getDocument().toString().length - 1;

    renderCounter(counter, getLength(), limits);
    trixEditor.addEventListener('trix-change', () => renderCounter(counter, getLength(), limits));
}

function createCounter() {
    const el = document.createElement('div');
    el.className = 'ea-char-counter';
    el.style.cssText = 'font-size: 0.8rem; margin-top: 4px; text-align: right;';
    return el;
}

function renderCounter(el, length, { max, min } = {}) {
    if (max) {
        const remaining = max - length;
        el.textContent = `${fmt(length)} / ${fmt(max)} caractères`;
        el.style.color = remaining < 0 ? '#dc3545' : remaining < 200 ? '#fd7e14' : '#6c757d';
    } else {
        el.textContent = `${fmt(length)} caractères`;
        el.style.color = (min && length < min) ? '#fd7e14' : '#6c757d';
    }
}

function fmt(n) {
    return n.toLocaleString('fr-FR');
}
