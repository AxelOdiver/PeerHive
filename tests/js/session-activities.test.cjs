const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');

function setup(fetch) {
    const handlers = new Map(), values = new Map();
    const document = {getElementById: () => ({})};
    const form = {action: '/activities/1/review', fields: {feedback: 'Please revise', revision: '1'}};
    let disabled = false;
    function $(target) {
        return {
            on(event, selector, handler) { handlers.set(event + selector, handler); return this; },
            text(value) { values.set(target, value); return this; },
            html(value) { values.set(target, value); return this; },
            empty() { values.set(target, ''); return this; },
            find() { return this; },
            prop(name, value) { if (target === form && name === 'disabled') disabled = value; return this; },
        };
    }
    class FormData {
        constructor(form) { this.fields = new Map(Object.entries(form.fields)); }
        set(name, value) { this.fields.set(name, value); }
        get(name) { return this.fields.get(name); }
    }
    const context = {document, $, FormData, fetch, window: {bootstrap: {Modal: {getOrCreateInstance: () => ({show(){}})}}, toast(){}}};
    vm.createContext(context);
    vm.runInContext(fs.readFileSync('resources/js/pages/session-activities.js', 'utf8').replaceAll('export ', ''), context);
    context.initSessionActivities();
    const submit = () => handlers.get('submit[data-activity-form]').call(form, {preventDefault(){}, originalEvent: {submitter: {name: 'decision', value: 'revision'}}});
    return {context, values, submit, disabled: () => disabled};
}

test('activity review sends the chosen decision and prevents concurrent duplicate submissions', async () => {
    let finish, posts = 0, decision;
    const env = setup(async (url, options) => {
        if (options.method === 'POST') {
            posts++; decision = options.body.get('decision');
            await new Promise(resolve => { finish = resolve; });
            return {ok: true, json: async () => ({message: 'Revision requested'})};
        }
        return {ok: true, json: async () => ({html: '<p>Activity history</p>'})};
    });
    await env.context.openSessionActivities('/activities');
    const first = env.submit();
    assert.equal(env.disabled(), true);
    await env.submit();
    assert.equal(posts, 1);
    assert.equal(decision, 'revision');
    finish(); await first;
    assert.equal(env.disabled(), false);
    assert.equal(env.values.get('#sessionActivitiesBody'), '<p>Activity history</p>');
});

test('a failed activity submission preserves the form and displays the server validation message', async () => {
    const env = setup(async (url, options) => options.method === 'POST'
        ? {ok: false, json: async () => ({errors: {feedback: ['Feedback is required.']}})}
        : {ok: true, json: async () => ({html: '<form>Draft feedback</form>'})});
    await env.context.openSessionActivities('/activities');
    await env.submit();
    assert.equal(env.values.get('#sessionActivitiesBody'), '<form>Draft feedback</form>');
    assert.equal(env.values.get('#sessionActivitiesError'), 'Feedback is required.');
    assert.equal(env.disabled(), false);
});
