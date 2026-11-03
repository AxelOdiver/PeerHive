export function initSkillTreeSubject(root = document) {
    const subject = root.getElementById('treeSubject');
    subject?.addEventListener('change', () => subject.form.requestSubmit());
}

if (typeof document !== 'undefined') initSkillTreeSubject();
