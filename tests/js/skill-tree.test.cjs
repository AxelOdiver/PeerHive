const {test} = require('node:test');
const assert = require('node:assert/strict');

test('changing the Skill Tree subject submits its form immediately', async () => {
    const {initSkillTreeSubject} = await import('../../resources/js/pages/skill-tree.js');
    const subject = new EventTarget();
    let submissions = 0;
    subject.form = {requestSubmit() { submissions++; }};
    initSkillTreeSubject({getElementById: id => id === 'treeSubject' ? subject : null});
    subject.dispatchEvent(new Event('change'));
    assert.equal(submissions, 1);
    subject.dispatchEvent(new Event('change'));
    assert.equal(submissions, 2);
    initSkillTreeSubject({getElementById: () => null});
});
