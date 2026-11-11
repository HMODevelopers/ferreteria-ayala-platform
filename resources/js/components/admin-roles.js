export function initAdminRoles(root = document) {
  root.querySelectorAll('[data-role-editor]').forEach(editor => {
    if (editor.dataset.roleEditorInitialized === 'true') return;
    editor.dataset.roleEditorInitialized = 'true';
    const search = editor.querySelector('[data-permission-search]');
    const permissions = [...editor.querySelectorAll('[data-permission-item]')];
    const groups = [...editor.querySelectorAll('[data-permission-group]')];
    const count = editor.querySelector('[data-permission-count]');
    const empty = editor.querySelector('[data-permission-empty]');
    const normalize = value => value.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    const syncCount = () => {
      const selected = permissions.filter(item => item.querySelector('input[name="permission_ids[]"]')?.checked).length;
      if (count) count.textContent = String(selected);
      groups.forEach(group => {
        const boxes = [...group.querySelectorAll('input[name="permission_ids[]"]')];
        const toggle = group.querySelector('[data-permission-group-toggle]');
        if (!toggle || boxes.length === 0) return;
        const checked = boxes.filter(box => box.checked).length;
        const groupCount = group.querySelector('[data-permission-group-count]');
        if (groupCount) groupCount.textContent = `${checked}/${boxes.length}`;
        toggle.checked = checked === boxes.length;
        toggle.indeterminate = checked > 0 && checked < boxes.length;
      });
    };
    const filter = () => {
      const query = normalize(search?.value.trim() || '');
      let visible = 0;
      groups.forEach(group => {
        let groupVisible = 0;
        group.querySelectorAll('[data-permission-item]').forEach(item => {
          const matches = query === '' || normalize(item.textContent).includes(query);
          item.hidden = !matches;
          if (matches) groupVisible++;
        });
        group.hidden = groupVisible === 0;
        if (groupVisible && query !== '') group.open = true;
        if (groupVisible) visible += groupVisible;
      });
      if (empty) empty.hidden = visible !== 0;
    };
    groups.forEach(group => {
      group.querySelector('[data-permission-group-toggle]')?.addEventListener('change', event => {
        group.querySelectorAll('input[name="permission_ids[]"]').forEach(box => { if (!box.closest('[data-permission-item]').hidden) box.checked = event.target.checked; });
        syncCount();
      });
    });
    permissions.forEach(item => item.querySelector('input')?.addEventListener('change', syncCount));
    search?.addEventListener('input', filter);
    syncCount();
  });
}
