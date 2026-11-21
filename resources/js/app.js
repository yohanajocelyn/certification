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

function applyCondition(wrapper) {
    const [fieldName, values] = wrapper.dataset.showWhen.split(CONDITION_SEPARATOR);
    const isShown = values.split(VALUE_SEPARATOR).includes(currentValue(wrapper.closest('form'), fieldName));

    wrapper.hidden = ! isShown;
    wrapper.querySelectorAll('input, select, textarea').forEach((input) => {
        input.disabled = ! isShown;
    });
}

const dependentFields = document.querySelectorAll('[data-show-when]');

dependentFields.forEach(applyCondition);

document.addEventListener('change', (event) => {
    dependentFields.forEach((wrapper) => {
        if (wrapper.closest('form') === event.target.form) {
            applyCondition(wrapper);
        }
    });
});
