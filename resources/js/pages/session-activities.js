let busy = false;
let activityUrl = null;
let loadVersion = 0;

export async function openSessionActivities(url) {
    if (busy) return;
    const version = ++loadVersion;
    activityUrl = url;
    $('#sessionActivitiesBody').text('Loading activities…');
    $('#sessionActivitiesError').empty();
    window.bootstrap.Modal.getOrCreateInstance(document.getElementById('sessionActivitiesModal')).show();
    try {
        const response = await fetch(url, {headers: {Accept: 'application/json'}});
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Could not load activities.');
        if (version === loadVersion) $('#sessionActivitiesBody').html(data.html);
    } catch (error) {
        if (version === loadVersion) $('#sessionActivitiesBody').text(error.message);
    }
}

export function initSessionActivities() {
    $(document).on('click', '[data-session-activities]', function (event) {
        event.preventDefault();
        openSessionActivities(this.href);
    });
    $('#sessionActivitiesModal').on('submit', '[data-activity-form]', async function (event) {
        event.preventDefault();
        if (busy) return;
        const data = new FormData(this);
        const submitter = event.originalEvent?.submitter;
        if (submitter?.name) data.set(submitter.name, submitter.value);
        const form = this;
        busy = true;
        $(form).find('button[type="submit"]').prop('disabled', true);
        $('#sessionActivitiesError').empty();
        try {
            const response = await fetch(form.action, {method: 'POST', headers: {Accept: 'application/json'}, body: data});
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'Could not save activity.');
            busy = false;
            await openSessionActivities(activityUrl);
            window.toast?.('success', result.message);
        } catch (error) {
            $('#sessionActivitiesError').text(error.message);
        } finally {
            busy = false;
            $(form).find('button[type="submit"]').prop('disabled', false);
        }
    });
}
