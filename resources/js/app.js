/*
 * Show a field only while another field in the same form has one of the given values:
 *   <div data-show-when="type:electric"> ... </div>
 *   <div data-show-when="vehicle_type:car,electric_car"> ... </div>
 * Hidden fields are also disabled, so the browser does not submit them.
 * This is display only: the server validates every field again.
 */
const CONDITION_SEPARATOR = ':';
const VALUE_SEPARATOR = ',';

function currentValue(form, fieldName) {
    const fields = form.querySelectorAll(`[name="${fieldName}"]`);

    for (const field of fields) {
        const isChoice = field.type === 'radio' || field.type === 'checkbox';

        if (! isChoice) {
            return field.value;
        }

        if (field.checked) {
            return field.value;
        }
    }

    return '';
}

function isConditionMet(wrapper) {
    const [fieldName, values] = wrapper.dataset.showWhen.split(CONDITION_SEPARATOR);

    return values.split(VALUE_SEPARATOR).includes(currentValue(wrapper.closest('form'), fieldName));
}

function refreshDependentFields() {
    document.querySelectorAll('[data-show-when]').forEach((wrapper) => {
        wrapper.hidden = ! isConditionMet(wrapper);
    });

    // An input is enabled only when none of the sections around it is hidden (sections can be nested).
    document.querySelectorAll('[data-show-when] :is(input, select, textarea)').forEach((input) => {
        input.disabled = input.closest('[data-show-when][hidden]') !== null;
    });
}

refreshDependentFields();

document.addEventListener('change', (event) => {
    if (event.target.form) {
        refreshDependentFields();
    }
});

/*
 * Ask before submitting a form that cannot be undone:
 *   <form data-confirm="Tolak pendaftaran ini?"> ... </form>
 */
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;

    if (message && ! window.confirm(message)) {
        event.preventDefault();
    }
});

/*
 * Rental days field (data-rental-days), driven by the start date and the rental type:
 *   daily   -> editable, at most (days in the start month - 1)
 *   monthly -> read-only, filled with the days in the start month
 * The read-only end date (data-rental-end-date) shows start + days - 1, or stays empty
 * while the days are not valid. Display only: the server recalculates both and checks the limit.
 */
function daysInMonth(isoDate) {
    const [year, month] = isoDate.split('-').map(Number);

    return new Date(year, month, 0).getDate();
}

function lastRentedDay(isoDate, days) {
    const [year, month, day] = isoDate.split('-').map(Number);

    // UTC avoids a daylight-saving hour shifting the date.
    return new Date(Date.UTC(year, month - 1, day + days - 1)).toISOString().slice(0, 10);
}

function showEndDate(form, startDate, daysInput) {
    const endDateInput = form.querySelector('[data-rental-end-date]');

    if (! endDateInput) {
        return;
    }

    const days = Number(daysInput.value);
    const isValid = Number.isInteger(days) && days >= Number(daysInput.min)
        && (daysInput.max === '' || days <= Number(daysInput.max));

    endDateInput.value = isValid ? lastRentedDay(startDate, days) : '';
}

function syncRentalDays(form) {
    const daysInput = form.querySelector('[data-rental-days]');
    const startDate = form.querySelector('[name="start_date"]')?.value;

    if (! daysInput || ! startDate) {
        return;
    }

    const hint = form.querySelector('[data-rental-days-hint]');
    const monthDays = daysInMonth(startDate);
    const isMonthly = currentValue(form, 'rental_type') === daysInput.dataset.monthlyValue;

    if (isMonthly) {
        if (! daysInput.readOnly) {
            daysInput.dataset.dailyDays = daysInput.value;
        }
        daysInput.readOnly = true;
        daysInput.removeAttribute('max');
        daysInput.value = monthDays;
        hint.textContent = daysInput.dataset.monthlyHint.replace(':days', monthDays);
        showEndDate(form, startDate, daysInput);

        return;
    }

    if (daysInput.readOnly) {
        daysInput.value = daysInput.dataset.dailyDays ?? '';
    }
    daysInput.readOnly = false;
    daysInput.max = monthDays - 1;
    hint.textContent = daysInput.dataset.dailyHint.replace(':max', monthDays - 1);
    showEndDate(form, startDate, daysInput);
}

document.querySelectorAll('[data-rental-days]').forEach((daysInput) => syncRentalDays(daysInput.form));

['change', 'input'].forEach((eventName) => {
    document.addEventListener(eventName, (event) => {
        if (event.target.form?.querySelector('[data-rental-days]')) {
            syncRentalDays(event.target.form);
        }
    });
});
