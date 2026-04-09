'use strict';

// CSRF Token aus Meta-Tag
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// Akkordeon
document.addEventListener('click', e => {
    const header = e.target.closest('.accordion-header');
    if (!header || e.target.closest('.handle') || e.target.closest('button')) return;

    const body   = header.nextElementSibling;
    const isOpen = body.classList.contains('open');

    document.querySelectorAll('.accordion-header').forEach(h => h.classList.remove('open'));
    document.querySelectorAll('.accordion-body').forEach(b => b.classList.remove('open'));

    if (!isOpen) {
        header.classList.add('open');
        body.classList.add('open');
    }
});

// AJAX Helfer
async function apiPost(url, data) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
        body: JSON.stringify(data),
    });
    if (!res.ok && res.headers.get('Content-Type')?.includes('application/json') === false) {
        return { success: false, error: `HTTP ${res.status}` };
    }
    return res.json();
}

async function apiPut(url, data) {
    const res = await fetch(url, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf() },
        body: JSON.stringify(data),
    });
    if (!res.ok && res.headers.get('Content-Type')?.includes('application/json') === false) {
        return { success: false, error: `HTTP ${res.status}` };
    }
    return res.json();
}

async function apiDelete(url) {
    const res = await fetch(url, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': csrf() },
    });
    if (!res.ok && res.headers.get('Content-Type')?.includes('application/json') === false) {
        return { success: false, error: `HTTP ${res.status}` };
    }
    return res.json();
}

// Feed löschen — entfernt den Wrapper-Div (inkl. Edit-Zeile)
async function deleteFeed(id) {
    if (!confirm('Feed löschen?')) return;
    const data = await apiDelete(`/api/feed/${id}`);
    if (data.success) document.getElementById(`fwrap-${id}`)?.remove();
}

// Kategorie löschen
async function deleteCategory(id, e) {
    e.stopPropagation();
    if (!confirm('Kategorie und alle zugehörigen Feeds löschen?')) return;
    const data = await apiDelete(`/api/category/${id}`);
    if (data.success) document.querySelector(`.accordion[data-cat-id="${id}"]`).remove();
    else alert('Fehler: ' + (data.error ?? 'Unbekannt'));
}

// XSS-sicheres Einfügen von Strings in DOM
function escHtml(str) {
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
