export function initTopicPickers(root = document) {
    root.querySelectorAll('[data-topic-picker]').forEach(wrapper => {
        const source = wrapper.querySelector('select[multiple]');
        const picker = wrapper.querySelector('[data-topic-open]');
        const selections = wrapper.querySelector('[data-topic-selections]');
        const limit = wrapper.querySelector('[data-topic-limit]');
        const modal = wrapper.querySelector('[data-topic-modal]');
        const search = modal.querySelector('[data-topic-search]');
        const cards = modal.querySelector('[data-topic-cards]');
        const count = modal.querySelector('[data-topic-count]');
        let draft = new Set();
        // Avoid nesting the dialog in the profile form or layout containers.
        document.body.append(modal);

        function render() {
            const selected = Array.from(source.selectedOptions);
            selections.replaceChildren(...selected.map(option => {
                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'btn btn-sm btn-outline-secondary rounded-pill';
                remove.textContent = option.value + ' ×';
                remove.setAttribute('aria-label', 'Remove ' + option.value);
                remove.addEventListener('click', () => {
                    option.selected = false;
                    source.dispatchEvent(new Event('change', {bubbles: true}));
                });
                return remove;
            }));
            limit.textContent = selected.length ? selected.length + ' of 10 selected' : '';
        }

        function renderCards() {
            const query = search.value.trim().toLocaleLowerCase();
            const groups = new Map();
            for (const option of source.options) {
                const subject = option.parentElement.tagName === 'OPTGROUP' ? option.parentElement.label : 'Previous selections';
                if (!(subject + ' ' + option.textContent).toLocaleLowerCase().includes(query)) continue;
                if (!groups.has(subject)) groups.set(subject, []);
                groups.get(subject).push(option);
            }
            cards.replaceChildren();
            for (const [subject, options] of groups) {
                const section = document.createElement('section');
                section.className = 'mb-4';
                const title = document.createElement('h3');
                title.className = 'fs-6 mb-3';
                title.textContent = subject;
                const grid = document.createElement('div');
                grid.className = 'topic-card-grid';
                for (const option of options) {
                    const selected = draft.has(option.value);
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'topic-choice' + (selected ? ' is-selected' : '');
                    button.setAttribute('aria-pressed', String(selected));
                    button.disabled = !selected && draft.size >= 10;
                    const text = document.createElement('span');
                    text.textContent = option.value;
                    const check = document.createElement('i');
                    check.className = 'bi ' + (selected ? 'bi-check-circle-fill' : 'bi-circle');
                    check.setAttribute('aria-hidden', 'true');
                    button.append(text, check);
                    button.addEventListener('click', () => {
                        if (draft.has(option.value)) draft.delete(option.value);
                        else if (draft.size < 10) draft.add(option.value);
                        renderCards();
                        Array.from(cards.querySelectorAll('button')).find(item => item.firstChild.textContent === option.value)?.focus();
                    });
                    grid.append(button);
                }
                section.append(title, grid);
                cards.append(section);
            }
            modal.querySelector('[data-topic-empty]').hidden = groups.size > 0;
            count.textContent = draft.size + ' of 10 selected' + (draft.size >= 10 ? ' · Limit reached' : '');
        }

        picker.addEventListener('click', () => {
            draft = new Set(Array.from(source.selectedOptions, option => option.value));
            search.value = '';
            renderCards();
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        });
        modal.addEventListener('shown.bs.modal', () => search.focus());
        modal.addEventListener('hidden.bs.modal', () => picker.focus());
        search.addEventListener('input', renderCards);
        modal.querySelector('[data-topic-save]').addEventListener('click', () => {
            for (const option of source.options) option.selected = draft.has(option.value);
            source.dispatchEvent(new Event('change', {bubbles: true}));
            window.bootstrap.Modal.getInstance(modal).hide();
        });
        source.addEventListener('change', render);
        render();
    });
}
