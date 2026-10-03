document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
        if (!window.confirm(form.getAttribute('data-confirm'))) event.preventDefault();
    });
});

var password = document.getElementById('password');
var confirmation = document.getElementById('confirm_password');
if (password && confirmation) {
    confirmation.addEventListener('input', function () {
        confirmation.setCustomValidity(password.value === confirmation.value ? '' : 'Passwords do not match');
    });
}

var bookingForm = document.getElementById('booking-form');
if (bookingForm) {
    var serviceInput = document.getElementById('service_id');
    var dateInput = document.getElementById('appointment_date');
    var timeInput = document.getElementById('appointment_time');
    var message = document.getElementById('booking-message');
    var earliestResult = document.getElementById('earliest-result');

    function showMessage(text, type) {
        message.className = 'alert alert-' + type + ' mt-3';
        message.textContent = text;
    }

    function clearMessage() {
        message.className = 'mt-3';
        message.textContent = '';
    }

    function renderEarliest(data) {
        earliestResult.replaceChildren();
        var title = document.createElement('strong');
        title.textContent = 'Earliest available: ' + data.start_time.slice(0, 5) + '–' + data.end_time.slice(0, 5);
        var details = document.createElement('p');
        details.textContent = data.service.name + ' on ' + data.date + ', with ' + data.worker.name;
        var choose = document.createElement('button');
        choose.type = 'button';
        choose.className = 'btn btn-sm btn-outline-dark';
        choose.textContent = 'Use this time';
        choose.addEventListener('click', function () {
            serviceInput.value = data.service.id;
            dateInput.value = data.date;
            timeInput.value = data.start_time.slice(0, 5);
            clearMessage();
            showMessage('Time selected. Confirm your appointment when ready.', 'success');
            timeInput.focus();
        });
        earliestResult.append(title, details, choose);
        earliestResult.hidden = false;
    }

    document.getElementById('find-earliest').addEventListener('click', async function () {
        clearMessage();
        earliestResult.hidden = true;
        if (!serviceInput.value || !dateInput.value) {
            showMessage('Select a service and date first.', 'warning');
            return;
        }
        var query = new URLSearchParams({ service_id: serviceInput.value, date: dateInput.value });
        try {
            var response = await fetch(bookingForm.dataset.earliestUrl + '?' + query.toString());
            var data = await response.json();
            if (!response.ok) throw new Error(data.error || 'No available time was found.');
            renderEarliest(data);
        } catch (error) {
            showMessage(error.message, 'warning');
        }
    });

    document.getElementById('check-availability').addEventListener('click', async function () {
        clearMessage();
        if (!serviceInput.value || !dateInput.value || !timeInput.value) {
            showMessage('Select a service, date, and time first.', 'warning');
            return;
        }
        var query = new URLSearchParams({ service_id: serviceInput.value, date: dateInput.value, time: timeInput.value });
        try {
            var response = await fetch(bookingForm.dataset.checkUrl + '?' + query.toString());
            var data = await response.json();
            if (!response.ok) throw new Error(data.error || 'That time is unavailable.');
            showMessage('Available with ' + data.worker.name + ' until ' + data.end_time.slice(0, 5) + '.', 'success');
        } catch (error) {
            showMessage(error.message, 'warning');
        }
    });

    bookingForm.addEventListener('submit', async function (event) {
        event.preventDefault();
        clearMessage();
        var submitButton = bookingForm.querySelector('[type="submit"]');
        submitButton.disabled = true;
        try {
            var response = await fetch(bookingForm.dataset.createUrl, { method: 'POST', body: new FormData(bookingForm) });
            var data = await response.json();
            if (!response.ok) throw new Error(data.error || 'The booking could not be saved.');
            var appointment = data.appointment;
            showMessage(data.message + ' ' + appointment.number + ' · ' + appointment.service + ' · ' + appointment.date + ' · ' + appointment.start_time.slice(0, 5) + ' · ' + appointment.worker + '.', 'success');
            earliestResult.hidden = true;
            bookingForm.reset();
        } catch (error) {
            showMessage(error.message, 'danger');
        } finally {
            submitButton.disabled = false;
        }
    });
}
