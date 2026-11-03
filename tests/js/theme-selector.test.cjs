const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const initialSource = fs.readFileSync('resources/views/layouts/partials/theme-init.blade.php', 'utf8').match(/<script[^>]*>([\s\S]*?)<\/script>/)[1];
const utils = fs.readFileSync('resources/js/utils.js', 'utf8');
const selectorSource = utils.slice(utils.indexOf('export function getPreferredTheme()'), utils.indexOf('export function showModal(')).replaceAll('export ', '');

function setup({saved = null, dark = false, blocked = false, dashboard = false} = {}) {
    const attributes = {}, listeners = {};
    const media = {matches: dark, addEventListener(event, handler) { listeners[event] = handler; }};
    const icon = {}, switcher = {querySelector: () => icon, setAttribute: (key, value) => { switcher[key] = value; }};
    const toggles = ['light', 'dark', 'auto'].map(mode => {
        const handlers = {}, classes = new Set();
        return {
            mode, classes, handlers,
            classList: {toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); }},
            getAttribute: () => mode, setAttribute(key, value) { this[key] = value; },
            querySelector: () => null, addEventListener(event, handler) { handlers[event] = handler; },
        };
    });
    const context = {
        document: {
            documentElement: {setAttribute: (key, value) => { attributes[key] = value; }},
            querySelector: () => dashboard ? switcher : null, querySelectorAll: () => toggles,
        },
        window: {matchMedia: () => media},
        localStorage: {
            getItem(key) { assert.equal(key, 'theme'); if (blocked) throw Error('Unavailable'); return saved; },
            setItem(key, value) { assert.equal(key, 'theme'); if (blocked) throw Error('Unavailable'); saved = value; },
        },
    };
    vm.createContext(context);
    vm.runInContext(initialSource, context);
    const firstPaint = attributes['data-bs-theme'];
    vm.runInContext(selectorSource, context);
    context.initThemeSelector();
    return {
        firstPaint, toggles, switcher, icon, saved: () => saved, theme: () => attributes['data-bs-theme'],
        click(mode) { toggles.find(toggle => toggle.mode === mode).handlers.click(); },
        systemDark(value) { media.matches = value; listeners.change(); },
    };
}

test('auth and dashboard apply the same saved theme before styles load', () => {
    for (const path of ['auth', 'dashboard']) {
        const layout = fs.readFileSync(`resources/views/layouts/${path}.blade.php`, 'utf8');
        assert(layout.indexOf("@include('layouts.partials.theme-init')") < layout.indexOf('@vite('));
    }
    assert.equal(setup({saved: 'dark', dark: false}).firstPaint, 'dark');
    assert.equal(setup({saved: 'light', dark: true}).firstPaint, 'light');
});

test('Auto stays selected while following changes to the device appearance', () => {
    const page = setup();
    assert.equal(page.theme(), 'light');
    assert.equal(page.toggles.find(toggle => toggle.mode === 'auto')['aria-pressed'], 'true');
    page.systemDark(true);
    assert.equal(page.theme(), 'dark');
    assert.equal(page.toggles.find(toggle => toggle.mode === 'auto')['aria-pressed'], 'true');
    page.systemDark(false);
    assert.equal(page.theme(), 'light');
});

test('an explicit theme survives navigation to the dashboard and ignores device changes', () => {
    const auth = setup();
    auth.click('dark');
    assert.equal(auth.theme(), 'dark');
    const dashboard = setup({saved: auth.saved(), dashboard: true});
    assert.equal(dashboard.firstPaint, 'dark');
    assert.equal(dashboard.theme(), 'dark');
    assert.match(dashboard.icon.className, /bi-moon-fill/);
    dashboard.systemDark(false);
    assert.equal(dashboard.theme(), 'dark');
    dashboard.click('light');
    dashboard.systemDark(true);
    assert.equal(dashboard.theme(), 'light');
    dashboard.click('auto');
    assert.equal(dashboard.saved(), 'auto');
    assert.match(dashboard.icon.className, /bi-circle-half/);
    assert.equal(dashboard.switcher['aria-label'], 'Theme (auto, currently dark)');
});

test('unavailable storage and invalid old preferences still allow theme controls to work', () => {
    const invalid = setup({saved: 'unexpected', dark: true});
    assert.equal(invalid.firstPaint, 'dark');
    assert.equal(invalid.toggles.find(toggle => toggle.mode === 'auto')['aria-pressed'], 'true');
    const blocked = setup({blocked: true});
    blocked.click('dark');
    assert.equal(blocked.theme(), 'dark');
    blocked.systemDark(false);
    assert.equal(blocked.theme(), 'dark');
    blocked.click('auto');
    blocked.systemDark(true);
    assert.equal(blocked.theme(), 'dark');
});
