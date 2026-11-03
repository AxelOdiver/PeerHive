const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function setup(ok) {
    const values = new Map([['#ratingConversationId', '12']]);
    const form = {fields: {stars: '5', comment: 'Patient explanations.'}};
    let submit, sent, hidden = false;
    function $(element) {
        return {
            on(event, selector, handler) { submit = handler; },
            val() { return values.get(element); },
            prop(name, value) { values.set(element + ':' + name, value); return this; },
            text(value) { values.set(element, value); return this; },
            attr() { return 'csrf-token'; }, remove() {},
        };
    }
    const context = {
        $, document: {getElementById: () => ({})}, pendingRating: {}, refreshSessionPanel() {},
        FormData: class { constructor(form) { this.get = field => form.fields[field] ?? null; } },
        window: {bootstrap: {Modal: {getInstance: () => ({hide() { hidden = true; }})}}},
        fetch: async (url, options) => {
            sent = {url, ...JSON.parse(options.body)};
            return {ok, json: async () => ok ? {message: 'Saved'} : {errors: {comment: ['Review is too long.']}}};
        },
    };
    const source = fs.readFileSync('resources/js/pages/messages.js', 'utf8');
    const start = source.indexOf("  $(document).on('submit', '#rateTeacherForm'");
    vm.runInNewContext(source.slice(start, source.indexOf('  function applySessionPanel(', start)), context);
    return {submit: () => submit.call(form, {preventDefault() {}}), form, values, sent: () => sent, hidden: () => hidden};
}

test('the existing rating form submits the optional comment alongside stars', async () => {
    const page = setup(true);
    await page.submit();
    assert.deepEqual(page.sent(), {url: '/messages/conversations/12/rating', stars: 5, comment: 'Patient explanations.'});
    assert.equal(page.hidden(), true);
    assert.equal(page.values.get('#submitRatingBtn:disabled'), false);
});

test('a failed review preserves the comment draft and lets the learner try again', async () => {
    const page = setup(false);
    await page.submit();
    assert.equal(page.form.fields.comment, 'Patient explanations.');
    assert.equal(page.values.get('#ratingError'), 'Review is too long.');
    assert.equal(page.hidden(), false);
    assert.equal(page.values.get('#submitRatingBtn:disabled'), false);
});
