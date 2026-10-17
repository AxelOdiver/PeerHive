export function createMemberSelection() {
  const members = new Map();
  return {
    add(user) { members.set(String(user.id), user); },
    remove(id) { members.delete(String(id)); },
    has(id) { return members.has(String(id)); },
    users() { return [...members.values()]; },
    ids() { return [...members.values()].map(user => user.id); },
    clear() { members.clear(); },
  };
}

export function initGroupMemberSearch(doc) {
  const input = doc.getElementById('newGroupSearchInput');
  const results = doc.getElementById('groupMembersList');
  const selected = doc.getElementById('groupSelectedMembers');
  const status = doc.getElementById('groupSearchStatus');
  const create = doc.getElementById('createGroupBtn');
  const selection = createMemberSelection();
  let matches = [], timer, controller, version = 0;

  function render() {
    results.replaceChildren();
    selected.replaceChildren();
    for (const user of matches) {
      const row = doc.getElementById('groupMemberResultTemplate').content.firstElementChild.cloneNode(true);
      row.querySelector('.member-name').textContent = user.name;
      row.querySelector('.member-username').textContent = user.username ? '@' + user.username : '';
      row.disabled = selection.has(user.id);
      row.querySelector('.member-action').textContent = row.disabled ? 'Selected' : 'Add';
      row.addEventListener('click', () => { selection.add(user); render(); });
      results.append(row);
    }
    for (const user of selection.users()) {
      const row = doc.getElementById('groupSelectedMemberTemplate').content.firstElementChild.cloneNode(true);
      row.querySelector('.member-name').textContent = user.name;
      row.querySelector('.member-username').textContent = user.username ? '@' + user.username : '';
      const remove = row.querySelector('button');
      remove.setAttribute('aria-label', 'Remove ' + user.name);
      remove.addEventListener('click', () => { selection.remove(user.id); render(); });
      selected.append(row);
    }
    doc.getElementById('groupSelectedCount').textContent = selection.ids().length;
    create.disabled = selection.ids().length < 2;
  }

  input.addEventListener('input', () => {
    clearTimeout(timer);
    controller?.abort();
    const requestVersion = ++version;
    const query = input.value.trim();
    matches = [];
    render();
    if (query.length < 3) {
      status.textContent = 'Enter a full email address or at least 3 username characters.';
      return;
    }
    status.textContent = 'Searching…';
    timer = setTimeout(async () => {
      controller = new AbortController();
      try {
        const response = await fetch('/messages/member-search?' + new URLSearchParams({q: query}), {
          signal: controller.signal, headers: {Accept: 'application/json'},
        });
        if (!response.ok) throw new Error('Search failed');
        const data = await response.json();
        if (requestVersion !== version) return;
        matches = data.users;
        status.textContent = matches.length ? 'Select Add next to each person. Up to 10 matches shown.' : 'No matching members. Check the email or username.';
        render();
      } catch (error) {
        if (requestVersion === version && error.name !== 'AbortError') {
          status.textContent = 'Could not search. Try again in a moment.';
        }
      }
    }, 300);
  });

  function clear() {
    ++version;
    clearTimeout(timer);
    controller?.abort();
    selection.clear();
    matches = [];
    input.value = '';
    status.textContent = 'Search to find someone to add.';
    render();
  }
  doc.getElementById('newGroupModal').addEventListener('hidden.bs.modal', clear);
  doc.getElementById('newGroupModal').addEventListener('shown.bs.modal', () => input.focus());
  render();
  return {ids: selection.ids, clear};
}
