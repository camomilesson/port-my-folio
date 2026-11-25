function renumber(repeater) {
    const entries = repeater.querySelector('[data-entry-list]').children;
    [...entries].forEach((entry, index) => {
        entry.querySelectorAll('[name], [id], [for]').forEach((field) => {
            for (const attribute of ['name', 'id', 'for']) {
                if (field.hasAttribute(attribute)) {
                    field.setAttribute(attribute, field.getAttribute(attribute).replace(/\[(?:\d+|__INDEX__)\]/, `[${index}]`));
                }
            }
        });
        entry.querySelector('[data-entry-up]').disabled = index === 0;
        entry.querySelector('[data-entry-down]').disabled = index === entries.length - 1;
    });
    repeater.querySelector('[data-entry-add]').disabled = entries.length >= Number(repeater.dataset.max);
}

document.querySelectorAll('[data-repeater]').forEach(renumber);
document.addEventListener('click', (event) => {
    const button = event.target.closest('button');
    const repeater = button?.closest('[data-repeater]');
    if (!repeater) return;
    const list = repeater.querySelector('[data-entry-list]');
    const entry = button.closest('[data-entry]');
    if (button.hasAttribute('data-entry-add') && list.children.length < Number(repeater.dataset.max)) {
        const template = repeater.querySelector('template');
        list.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', String(list.children.length)));
        list.lastElementChild.querySelector('input:not([type=hidden]), textarea')?.focus();
    } else if (button.hasAttribute('data-entry-remove')) {
        entry.remove();
    } else if (button.hasAttribute('data-entry-up') && entry.previousElementSibling) {
        list.insertBefore(entry, entry.previousElementSibling);
    } else if (button.hasAttribute('data-entry-down') && entry.nextElementSibling) {
        list.insertBefore(entry.nextElementSibling, entry);
    }
    renumber(repeater);
    repeater.querySelector('[data-repeater-status]').textContent = `${list.children.length} entries. Save the CV draft to apply changes.`;
});
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-current]') && event.target.checked) {
        event.target.closest('[data-entry]').querySelector('[name$="[end_date]"]').value = '';
    }
    if (event.target.matches('[data-decorative]')) {
        const alt = event.target.closest('form').querySelector('[name="alt_text"]');
        alt.required = !event.target.checked;
        if (event.target.checked) alt.value = '';
    }
});
document.querySelectorAll('[data-draft-form]').forEach((form) => {
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    form.addEventListener('click', (event) => { if (event.target.closest('[data-repeater] button')) dirty = true; });
    form.addEventListener('submit', () => { form.querySelectorAll('[data-repeater]').forEach(renumber); dirty = false; });
    window.addEventListener('beforeunload', (event) => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
});


document.querySelectorAll('[data-repositories]').forEach((fieldset) => {
    const list = fieldset.querySelector('[data-repository-list]');
    function updateRepositories() {
        [...list.children].forEach((entry, index) => {
            entry.querySelectorAll('[name], [id], [for]').forEach((field) => {
                for (const attribute of ['name', 'id', 'for']) {
                    if (field.hasAttribute(attribute)) {
                        field.setAttribute(attribute, field.getAttribute(attribute).replace(/\[(?:\d+|__INDEX__)\]/, `[${index}]`));
                    }
                }
            });
            entry.querySelector('[data-repository-remove]').disabled = list.children.length <= 1;
        });
        fieldset.querySelector('[data-repository-add]').disabled = list.children.length >= 5;
        fieldset.querySelector('[data-repository-status]').textContent = `${list.children.length} of 5 repositories.`;
    }
    fieldset.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (button?.hasAttribute('data-repository-add') && list.children.length < 5) {
            list.append(fieldset.querySelector('template').content.cloneNode(true));
        } else if (button?.hasAttribute('data-repository-remove') && list.children.length > 1) {
            button.closest('[data-repository]').remove();
        }
        updateRepositories();
    });
    updateRepositories();
});
