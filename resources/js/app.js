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


document.querySelectorAll('[data-generation-progress]').forEach((panel) => {
    async function pollGeneration() {
        try {
            const response = await fetch(panel.dataset.url, { headers: { Accept: 'application/json' }, cache: 'no-store' });
            if (!response.ok) return;
            const result = await response.json();
            panel.querySelector('[data-generation-label]').textContent = result.label;
            panel.querySelector('[data-generation-retry]').textContent = result.retry_at && result.status === 'queued' ? `Next processing time: ${new Date(result.retry_at).toLocaleString()}` : '';
            if (['failed', 'succeeded'].includes(result.status)) {
                if (panel.dataset.status !== result.status) window.location.reload();
                return;
            }
            if (result.status) window.setTimeout(pollGeneration, 3000);
        } catch {
            panel.querySelector('[data-generation-retry]').textContent = 'Could not refresh progress. Retrying shortly.';
            window.setTimeout(pollGeneration, 6000);
        }
    }
    pollGeneration();
});


async function refreshLivePortfolio() {
    const current = document.querySelector('[data-live-portfolio]');
    if (!current || current.dataset.active !== '1') return;
    try {
        const response = await fetch(window.location.href, { headers: { Accept: 'text/html' }, cache: 'no-store' });
        if (!response.ok || response.redirected) return;
        const html = new DOMParser().parseFromString(await response.text(), 'text/html');
        const replacement = html.querySelector('[data-live-portfolio]');
        if (replacement && replacement.dataset.revision !== current.dataset.revision) {
            const focused = document.activeElement;
            const focusedHref = focused?.getAttribute('href');
            current.replaceWith(document.importNode(replacement, true));
            document.title = html.title;
            if (focusedHref) {
                [...document.querySelectorAll('[data-live-portfolio] a')].find((link) => link.getAttribute('href') === focusedHref)?.focus({ preventScroll: true });
            }
        }
    } catch {
        // Keep completed content on screen; the next poll will retry.
    }
    window.setTimeout(refreshLivePortfolio, 2500);
}
refreshLivePortfolio();


document.querySelector('[data-create-portfolio]')?.addEventListener('submit', (event) => {
    const button = event.target.querySelector('button[type="submit"], button:not([type])');
    button.disabled = true;
    button.textContent = 'Preparing your portfolio…';
});

const dirtyInlineForms = new Set();

function cancelInline(form) {
    form.reset();
    dirtyInlineForms.delete(form);
    form.querySelector('[data-inline-error]').textContent = '';
    const editor = form.closest('details');
    editor.open = false;
    editor.querySelector('summary').focus();
}

async function refreshInlineContent(savedForm) {
    const response = await fetch(window.location.href, { headers: { Accept: 'text/html' }, cache: 'no-store' });
    if (!response.ok || response.redirected) throw new Error('Saved. Reload the page to see the latest content.');
    const html = new DOMParser().parseFromString(await response.text(), 'text/html');
    const savedKey = savedForm.closest('[data-inline-key]')?.dataset.inlineKey;
    if (savedForm.matches('[data-photo-form]')) {
        document.querySelector('[data-portfolio-photo]').replaceWith(document.importNode(html.querySelector('[data-portfolio-photo]'), true));
        document.querySelector('[data-photo-choose]')?.focus({ preventScroll: true });
    }
    if (savedForm.matches('[data-upload-form]')) {
        const gallery = document.querySelector('[data-inline-gallery]');
        const replacement = document.importNode(html.querySelector('[data-inline-gallery]'), true);
        const existingIds = new Set([...gallery.querySelectorAll('[data-carousel-slide]')].map((slide) => slide.dataset.carouselSlide));
        const selected = replacement.querySelectorAll('[data-carousel-slide]');
        const preferredId = [...selected].find((slide) => !existingIds.has(slide.dataset.carouselSlide))?.dataset.carouselSlide
            ?? gallery.querySelector('[data-carousel-slide][data-current]')?.dataset.carouselSlide;
        const carousel = replacement.querySelector('[data-carousel]');
        if (carousel && preferredId) {
            const index = [...carousel.querySelectorAll('[data-carousel-slide]')].findIndex((slide) => slide.dataset.carouselSlide === preferredId);
            showCarouselSlide(carousel, Math.max(0, index));
        }
        gallery.replaceWith(replacement);
        (replacement.querySelector('[data-gallery-choose]') ?? replacement.querySelector('[data-current] [data-carousel-direction="1"]'))?.focus({ preventScroll: true });
    }
    html.querySelectorAll('[data-inline-key]').forEach((fresh) => {
        const current = document.querySelector(`[data-inline-key="${fresh.dataset.inlineKey}"]`);
        if (current && !current.querySelector('details[open]')) {
            current.replaceWith(document.importNode(fresh, true));
        }
    });
    document.querySelectorAll('[data-portfolio-name]').forEach((name, index) => {
        name.textContent = html.querySelectorAll('[data-portfolio-name]')[index].textContent;
    });
    document.querySelectorAll('[data-inline-gallery] img').forEach((image, index) => {
        image.alt = html.querySelectorAll('[data-inline-gallery] img')[index]?.alt ?? image.alt;
    });
    document.title = html.title;
    if (savedKey) document.querySelector(`[data-inline-key="${savedKey}"] summary`)?.focus({ preventScroll: true });
}

document.addEventListener('toggle', (event) => {
    if (!event.target.matches('[data-inline-editor]')) return;
    const form = event.target.querySelector('form');
    if (form.dataset.saving && !event.target.open) {
        event.target.open = true;
        return;
    }
    if (event.target.open) {
        form.querySelector('textarea, input:not([type=hidden])')?.focus({ preventScroll: true });
    } else {
        form.reset();
        dirtyInlineForms.delete(form);
        form.querySelector('[data-inline-error]').textContent = '';
    }
}, true);

document.addEventListener('input', (event) => {
    const form = event.target.closest('[data-inline-form]');
    if (form && !form.matches('[data-upload-form], [data-photo-form]')) dirtyInlineForms.add(form);
});
window.addEventListener('beforeunload', (event) => {
    if ([...dirtyInlineForms].some((form) => form.isConnected)) {
        event.preventDefault();
        event.returnValue = '';
    }
});

document.addEventListener('click', (event) => {
    const choose = event.target.closest('[data-gallery-choose], [data-photo-choose]');
    if (choose) choose.closest('form').querySelector('input[type="file"]').click();
    const cancel = event.target.closest('[data-inline-cancel]');
    if (cancel) cancelInline(cancel.closest('form'));
});
document.addEventListener('keydown', (event) => {
    const form = event.target.closest('[data-inline-form]');
    if (event.key === 'Escape' && form?.closest('details') && !form.dataset.saving) {
        event.preventDefault();
        cancelInline(form);
    }
});

document.addEventListener('change', (event) => {
    if (event.target.matches('[data-gallery-files], [data-gallery-single], [data-photo-file]') && event.target.files.length) {
        event.target.form.requestSubmit();
    }
});

document.addEventListener('submit', async (event) => {
    const form = event.target;
    if (!form.matches('[data-inline-form]')) return;
    event.preventDefault();
    if (form.dataset.saving) return;
    const data = new FormData(form);
    if (form.matches('[data-upload-form], [data-photo-form]')) dirtyInlineForms.add(form);
    const controls = [...form.querySelectorAll('input, textarea, button')];
    controls.forEach((control) => { control.disabled = true; });
    form.dataset.saving = '1';
    form.setAttribute('aria-busy', 'true');
    const submit = form.querySelector('[data-gallery-choose], [data-photo-choose], button[type="submit"]');
    const submitLabel = submit.textContent;
    submit.textContent = form.matches('[data-upload-form]') ? 'Adding images…' : 'Saving…';
    const error = form.querySelector('[data-inline-error]');
    error.textContent = '';
    const uploadStatus = form.querySelector('[data-upload-status]');
    if (uploadStatus) uploadStatus.textContent = form.matches('[data-photo-form]') ? 'Uploading photo…' : 'Uploading images…';
    try {
        const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'application/json' } });
        const result = await response.json();
        if (!response.ok) {
            error.textContent = result.errors ? Object.values(result.errors).flat().join(' ') : (result.message || 'Could not save. Try again.');
            return;
        }
        dirtyInlineForms.delete(form);
        if (uploadStatus) uploadStatus.textContent = form.matches('[data-photo-form]') ? 'Photo saved to your draft.' : 'Images added to your draft.';
        if (form.matches('[data-upload-form], [data-photo-form]')) {
            form.reset();
        }
        delete form.dataset.saving;
        const editor = form.closest('details');
        if (editor) editor.open = false;
        const status = document.querySelector('[data-inline-status]');
        status.textContent = result.message;
        try {
            await refreshInlineContent(form);
        } catch {
            status.textContent = 'Saved. Reload the page to see the latest content.';
        }
    } catch {
        error.textContent = 'Could not confirm the save. Check your connection and reload before retrying.';
    } finally {
        delete form.dataset.saving;
        form.removeAttribute('aria-busy');
        if (form.matches('[data-upload-form], [data-photo-form]')) {
            dirtyInlineForms.delete(form);
            form.reset();
            if (form.isConnected) uploadStatus.textContent = '';
        }
        submit.textContent = submitLabel;
        controls.forEach((control) => { control.disabled = false; });
    }
});

function showCarouselSlide(carousel, index) {
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    if (!slides.length) return;
    index = (index + slides.length) % slides.length;
    slides.forEach((slide, position) => { slide.toggleAttribute('data-current', position === index); });
    const counter = carousel.querySelector('[data-carousel-count]');
    if (counter) counter.textContent = `${index + 1} / ${slides.length}`;
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-carousel-direction]');
    if (!button) return;
    const carousel = button.closest('[data-carousel]');
    const slides = [...carousel.querySelectorAll('[data-carousel-slide]')];
    const current = slides.findIndex((slide) => slide.hasAttribute('data-current'));
    showCarouselSlide(carousel, current + Number(button.dataset.carouselDirection));
    carousel.querySelector(`[data-current] [data-carousel-direction="${button.dataset.carouselDirection}"]`)?.focus({ preventScroll: true });
});


document.addEventListener('keydown', (event) => {
    const carousel = event.target.closest('[data-carousel]');
    if (!carousel || !['ArrowLeft', 'ArrowRight'].includes(event.key) || event.target.closest('input, textarea, select')) return;
    const direction = event.key === 'ArrowLeft' ? '-1' : '1';
    const button = carousel.querySelector(`[data-current] [data-carousel-direction="${direction}"]`);
    if (button) {
        event.preventDefault();
        button.click();
    }
});

const pendingAppearance = new Map();
let appearanceSaveTimer;
let appearanceSaving = false;
let appearanceSaveFailed = false;

function previewAppearance(form, field, value) {
    if (field === 'preset') {
        document.body.dataset.portfolioPreset = value;
        form.querySelectorAll('[data-appearance-preset]').forEach((radio) => { radio.checked = radio.value === value; });
    } else {
        document.body.style.setProperty('--portfolio-accent', value);
        form.querySelector('[data-appearance-color]').value = value;
        form.querySelector('[data-appearance-hex]').value = value;
        form.querySelectorAll('[data-appearance-swatch]').forEach((swatch) => {
            swatch.setAttribute('aria-pressed', String(swatch.dataset.appearanceSwatch === value));
        });
        form.querySelector('[data-appearance-color-error]').textContent = '';
        form.querySelector('[data-appearance-hex]').setCustomValidity('');
    }
}

function queueAppearance(form, field, value, delay = 0) {
    previewAppearance(form, field, value);
    pendingAppearance.set(field, value);
    appearanceSaveFailed = false;
    dirtyInlineForms.add(form);
    form.querySelector('[data-appearance-status]').textContent = 'Saving appearance…';
    form.querySelector('[data-appearance-retry]').hidden = true;
    window.clearTimeout(appearanceSaveTimer);
    appearanceSaveTimer = window.setTimeout(() => saveAppearance(form), delay);
}

async function saveAppearance(form) {
    if (appearanceSaving) return;
    appearanceSaving = true;
    form.setAttribute('aria-busy', 'true');
    const status = form.querySelector('[data-appearance-status]');
    const retry = form.querySelector('[data-appearance-retry]');
    retry.disabled = true;
    try {
        while (pendingAppearance.size && !appearanceSaveFailed) {
            const [field, value] = pendingAppearance.entries().next().value;
            pendingAppearance.delete(field);
            try {
                const response = await fetch(form.action, {
                    method: 'PATCH',
                    headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value },
                    body: JSON.stringify({ field, value }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.errors ? Object.values(result.errors).flat().join(' ') : 'Could not save appearance.');
                if (field === 'preset') form.dataset.savedPreset = result.appearance.preset;
                else form.dataset.savedAccent = result.appearance.accent_color;
            } catch (error) {
                if (!pendingAppearance.has(field)) pendingAppearance.set(field, value);
                appearanceSaveFailed = true;
                status.textContent = `${error.message || 'Could not save appearance.'} Your preview has not been confirmed saved.`;
                document.querySelector('[data-inline-status]').textContent = 'Appearance changes have not been confirmed saved. Open Appearance to retry.';
                retry.hidden = false;
            }
        }
        if (!pendingAppearance.size) {
            dirtyInlineForms.delete(form);
            status.textContent = 'Appearance saved. Publish to update your public portfolio.';
            document.querySelector('[data-inline-status]').textContent = status.textContent;
        }
    } finally {
        appearanceSaving = false;
        form.removeAttribute('aria-busy');
        retry.disabled = false;
    }
}

function updateAppearanceColor(input, showError, delay = 350) {
    const form = input.closest('[data-appearance-form]');
    const value = input.value.toLowerCase();
    if (/^#[a-f0-9]{6}$/.test(value)) {
        queueAppearance(form, 'accent_color', value, delay);
    } else if (showError) {
        const message = 'Enter a hex color like #2563eb.';
        form.querySelector('[data-appearance-color-error]').textContent = message;
        input.setCustomValidity(message);
    }
}

document.addEventListener('input', (event) => {
    if (event.target.matches('[data-appearance-color], [data-appearance-hex]')) updateAppearanceColor(event.target, false);
});
document.addEventListener('change', (event) => {
    const form = event.target.closest('[data-appearance-form]');
    if (!form) return;
    if (event.target.matches('[data-appearance-preset]')) queueAppearance(form, 'preset', event.target.value);
    else if (event.target.matches('[data-appearance-color], [data-appearance-hex]')) updateAppearanceColor(event.target, true, 0);
});
document.addEventListener('click', (event) => {
    const swatch = event.target.closest('[data-appearance-swatch]');
    if (swatch) queueAppearance(swatch.closest('form'), 'accent_color', swatch.dataset.appearanceSwatch);
    const retry = event.target.closest('[data-appearance-retry]');
    if (retry) {
        appearanceSaveFailed = false;
        retry.hidden = true;
        retry.closest('form').querySelector('[data-appearance-status]').textContent = 'Saving appearance…';
        saveAppearance(retry.closest('form'));
    }
});
document.addEventListener('submit', (event) => {
    if (event.target.matches('[data-appearance-form]')) {
        event.preventDefault();
        updateAppearanceColor(event.target.querySelector('[data-appearance-hex]'), true, 0);
    }
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && event.target.closest('[data-appearance]')) {
        event.preventDefault();
        const panel = event.target.closest('[data-appearance]');
        panel.open = false;
        panel.querySelector('summary').focus();
    }
});


document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-appearance]')) {
        const menu = document.querySelector('[data-appearance]');
        if (menu) menu.open = false;
    }
});

document.querySelectorAll('[data-welcome-preview]').forEach((preview) => {
    const presets = Object.entries(JSON.parse(preview.dataset.presets));
    const accents = JSON.parse(preview.dataset.accents);
    const portfolio = preview.querySelector('[data-portfolio-preset]');
    const controls = preview.querySelector('[data-preview-controls]');
    const pauseButton = preview.querySelector('[data-preview-pause]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0;
    let paused = reducedMotion.matches;
    let timer;
    let accent;

    function showPreset(nextIndex) {
        index = nextIndex;
        const [key, preset] = presets[index];
        const availableAccents = accents.filter((color) => color !== accent);
        accent = availableAccents[Math.floor(Math.random() * availableAccents.length)] ?? accents[0];
        portfolio.dataset.portfolioPreset = key;
        portfolio.style.setProperty('--portfolio-accent', accent);
        preview.querySelector('[data-preview-label]').textContent = preset.label;
        preview.querySelectorAll('[data-preview-preset]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.previewPreset === key));
        });
    }

    function scheduleCycle() {
        window.clearInterval(timer);
        pauseButton.textContent = paused ? 'Play' : 'Pause';
        if (!paused && !document.hidden) {
            timer = window.setInterval(() => showPreset((index + 1) % presets.length), 3000);
        }
    }

    controls.hidden = false;
    showPreset(0);
    scheduleCycle();
    preview.querySelectorAll('[data-preview-preset]').forEach((button) => {
        button.addEventListener('click', () => {
            showPreset(presets.findIndex(([key]) => key === button.dataset.previewPreset));
            paused = true;
            scheduleCycle();
        });
    });
    pauseButton.addEventListener('click', () => {
        paused = !paused;
        scheduleCycle();
    });
    reducedMotion.addEventListener('change', () => {
        paused = reducedMotion.matches;
        scheduleCycle();
    });
    document.addEventListener('visibilitychange', scheduleCycle);
});
