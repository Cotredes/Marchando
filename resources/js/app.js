const formatDay = (name) => name.charAt(0).toUpperCase() + name.slice(1);

const kdsRoot = document.querySelector('[data-kds-root]');
if (kdsRoot) {
    const connection = kdsRoot.querySelector('[data-kds-connection]');
    let cursor = null;
    let busy = false;
    const timers = () => kdsRoot.querySelectorAll('[data-elapsed-since]').forEach((element) => {
        const seconds = Math.max(0, Math.floor((Date.now() - Date.parse(element.dataset.elapsedSince)) / 1000));
        element.textContent = `${Math.floor(seconds / 60).toString().padStart(2, '0')}:${(seconds % 60).toString().padStart(2, '0')}`;
    });
    const poll = async () => {
        if (busy || document.visibilityState === 'hidden') return;
        busy = true; connection?.classList.remove('kds-connection-online', 'kds-connection-offline'); connection?.classList.add('kds-connection-reconnecting');
        try { const url = new URL(kdsRoot.dataset.feedUrl, window.location.origin); if (cursor) url.searchParams.set('cursor', cursor); const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (!response.ok) throw new Error('feed'); const payload = await response.json(); if (cursor !== null && String(payload.cursor) !== String(cursor)) window.location.reload(); cursor = payload.cursor; connection?.classList.remove('kds-connection-reconnecting', 'kds-connection-offline'); connection?.classList.add('kds-connection-online'); } catch { connection?.classList.remove('kds-connection-reconnecting', 'kds-connection-online'); connection?.classList.add('kds-connection-offline'); } finally { busy = false; }
    };
    setInterval(poll, 3000); setInterval(timers, 1000); timers(); poll(); window.addEventListener('online', poll); document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && poll());
    import('./echo.js').then(() => { if (window.Echo) window.Echo.private(`restaurant.${kdsRoot.dataset.restaurantId || document.body.dataset.restaurantId}.operations`).listen('.kitchen.changed', poll); }).catch(() => {});
}

const publicTracking = document.querySelector('[data-public-tracking]');
if (publicTracking) {
    let busy = false;
    const labels = { pending: 'Pedido recibido', accepted: 'Pedido aceptado', rejected: 'No hemos podido aceptar tu pedido', cancelled: 'Pedido cancelado' };
    const pollTracking = async () => {
        if (busy || document.visibilityState === 'hidden') return;
        busy = true;
        try {
            const response = await fetch(publicTracking.dataset.snapshotUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('tracking');
            const payload = await response.json();
            const title = publicTracking.querySelector('[data-public-status-title]');
            if (title) title.textContent = labels[payload.status] || (payload.fulfillment === 'ready' ? 'Listo' : 'En preparación');
            publicTracking.dataset.status = payload.status;
        } catch { publicTracking.dataset.offline = 'true'; } finally { busy = false; }
    };
    setInterval(pollTracking, 5000);
    pollTracking();
    import('./echo.js').then(() => window.Echo?.channel(publicTracking.dataset.publicChannel).listen('.public.order.changed', pollTracking)).catch(() => {});
    window.addEventListener('online', pollTracking);
    document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && pollTracking());
}

document.querySelectorAll('[data-toggle-target]').forEach((toggle) => {
    const target = document.getElementById(toggle.dataset.toggleTarget);

    const updateTarget = () => {
        target.hidden = !toggle.checked;
        target.querySelectorAll('input, select, textarea').forEach((field) => {
            field.disabled = !toggle.checked;
        });
    };

    toggle.addEventListener('change', updateTarget);
    updateTarget();
});

const scheduleForm = document.querySelector('[data-schedule-form]');

if (scheduleForm) {
    const dialog = document.getElementById('copy-dialog');
    const copyDescription = document.getElementById('copy-description');
    const copyDays = document.getElementById('copy-days');
    let copySource = null;

    const intervalMarkup = (context, weekday, index, values = {}) => `
        <div class="interval-row" data-interval>
            <div><label class="sr-only" for="${context}-${weekday}-${index}-opens">Apertura</label><input id="${context}-${weekday}-${index}-opens" type="time" name="hours[${context}][${weekday}][intervals][${index}][opens_at]" value="${values.opens_at || ''}" required class="form-input"></div>
            <span class="time-separator">a</span>
            <div><label class="sr-only" for="${context}-${weekday}-${index}-closes">Cierre</label><input id="${context}-${weekday}-${index}-closes" type="time" name="hours[${context}][${weekday}][intervals][${index}][closes_at]" value="${values.closes_at || ''}" required class="form-input"></div>
            <button type="button" class="icon-button" data-remove-interval aria-label="Eliminar franja">×</button>
        </div>`;

    const setDayState = (day) => {
        const enabled = day.querySelector('[data-day-toggle]').checked;
        const intervals = day.querySelector('[data-intervals]');
        const actions = day.querySelector('.day-actions');
        const closed = day.querySelector('[data-closed]');

        intervals.hidden = !enabled;
        actions.hidden = !enabled;
        closed.hidden = enabled;
        day.querySelectorAll('[data-interval] input').forEach((field) => {
            field.disabled = !enabled;
        });
    };

    const addInterval = (day, values = {}) => {
        const intervals = day.querySelector('[data-intervals]');
        const row = document.createElement('div');
        const context = day.dataset.context;
        const weekday = day.dataset.weekday;
        row.innerHTML = intervalMarkup(context, weekday, intervals.children.length, values);
        intervals.append(row.firstElementChild);
        const input = intervals.lastElementChild.querySelector('input');
        input.focus();
        setDayState(day);
    };

    scheduleForm.querySelectorAll('[data-day]').forEach((day) => {
        setDayState(day);
        day.querySelector('[data-day-toggle]').addEventListener('change', () => {
            setDayState(day);
            scheduleForm.dispatchEvent(new Event('change'));
        });
        day.querySelector('[data-add-interval]').addEventListener('click', () => addInterval(day));
        day.querySelector('[data-intervals]').addEventListener('click', (event) => {
            if (event.target.matches('[data-remove-interval]')) {
                const row = event.target.closest('[data-interval]');
                const focusTarget = row.previousElementSibling?.querySelector('input') || day.querySelector('[data-add-interval]');
                row.remove();
                focusTarget.focus();
            }
        });
        day.querySelector('[data-copy-day]').addEventListener('click', () => {
            copySource = day;
            const sourceName = day.querySelector('.day-name').textContent;
            copyDescription.textContent = `Selecciona los días a los que quieres copiar el horario del ${sourceName.toLowerCase()}.`;
            copyDays.innerHTML = '';
            scheduleForm.querySelectorAll(`[data-day][data-context="${day.dataset.context}"]`).forEach((destination) => {
                if (destination === day) return;
                const label = destination.querySelector('.day-name').textContent;
                const wrapper = document.createElement('label');
                wrapper.className = 'check-label rounded-lg border border-stone-200 px-3 py-2';
                wrapper.innerHTML = `<input type="checkbox" value="${destination.dataset.weekday}"> ${formatDay(label)}`;
                copyDays.append(wrapper);
            });
            dialog.showModal();
        });
    });

    document.getElementById('copy-confirm').addEventListener('click', (event) => {
        event.preventDefault();
        const selectedDays = [...copyDays.querySelectorAll('input:checked')].map((input) => input.value);
        const sourceIntervals = [...copySource.querySelectorAll('[data-interval]')].map((row) => ({
            opens_at: row.querySelector('input[name*="opens_at"]').value,
            closes_at: row.querySelector('input[name*="closes_at"]').value,
        }));
        selectedDays.forEach((weekday) => {
            const destination = scheduleForm.querySelector(`[data-day][data-context="${copySource.dataset.context}"][data-weekday="${weekday}"]`);
            destination.querySelector('[data-day-toggle]').checked = copySource.querySelector('[data-day-toggle]').checked;
            const intervals = destination.querySelector('[data-intervals]');
            intervals.innerHTML = '';
            sourceIntervals.forEach((values) => addInterval(destination, values));
            setDayState(destination);
        });
        dialog.close();
        copySource.querySelector('[data-copy-day]').focus();
    });
}

const dirtyForms = [...document.querySelectorAll('form[data-dirty-form]')];

dirtyForms.forEach((form) => {
    let baseline = new URLSearchParams(new FormData(form)).toString();
    const dirtyLabel = form.querySelector('.form-dirty-label');
    let submitting = false;

    const updateDirty = () => {
        const dirty = new URLSearchParams(new FormData(form)).toString() !== baseline;
        dirtyLabel?.classList.toggle('hidden', !dirty);
        form.dataset.dirty = dirty ? 'true' : 'false';
    };

    form.addEventListener('input', updateDirty);
    form.addEventListener('change', updateDirty);
    form.addEventListener('submit', (event) => {
        submitting = true;
        const button = event.submitter || form.querySelector('button[type="submit"]');
        if (button) {
            button.disabled = true;
            button.textContent = 'Guardando…';
        }
    });

});

window.addEventListener('beforeunload', (event) => {
    if (dirtyForms.some((form) => form.dataset.dirty === 'true')) {
        event.preventDefault();
        event.returnValue = '';
    }
});

