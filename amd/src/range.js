import * as Str from 'core/str';

const ensureFeedback = (el) => {
    let fb = el.nextElementSibling;
    if (!fb || !fb.classList.contains('invalid-feedback')) {
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        el.insertAdjacentElement('afterend', fb);
    }
    return fb;
};

const boundsOf = (el) => ({
    min: el.dataset.min !== undefined && el.dataset.min !== '' ? parseFloat(el.dataset.min) : null,
    max: el.dataset.max !== undefined && el.dataset.max !== '' ? parseFloat(el.dataset.max) : null,
});

const toNumber = (txt) => {
    if (txt === null) {return NaN;}
    const t = String(txt).trim();
    if (!t) {return NaN;}
    return parseFloat(t.replace(',', '.'));
};

const message = async (state, val, min, max) => {
    if (state === 'required') {
        return await Str.get_string('rangejs_required', 'assignsubmission_formulacheck');
    }
    if (isNaN(val)) {
        return await Str.get_string('rangejs_numeric', 'assignsubmission_formulacheck');
    }
    if (min !== null && max !== null && (val < min || val > max)) {
        return await Str.get_string('rangejs_between', 'assignsubmission_formulacheck', {min, max});
    }
    if (min !== null && val < min) {
        return await Str.get_string('rangejs_min', 'assignsubmission_formulacheck', min);
    }
    if (max !== null && val > max) {
        return await Str.get_string('rangejs_max', 'assignsubmission_formulacheck', max);
    }
    return '';
};

const mark = async (el) => {
    const {min, max} = boundsOf(el);
    const raw = (el.value ?? '').trim();
    const required = raw === '';
    const val = toNumber(raw);

    let ok;
    if (required) {
        ok = false;
    } else {
        ok = !isNaN(val) && (min === null || val >= min) && (max === null || val <= max);
    }

    const fb = ensureFeedback(el);
    el.classList.toggle('is-invalid', !ok);
    el.classList.toggle('is-valid', ok && raw !== '');
    el.setAttribute('aria-invalid', String(!ok));
    fb.textContent = ok ? '' : await message(required ? 'required' : 'range', val, min, max);
    fb.style.display = ok ? 'none' : '';
    return ok;
};

const validateAll = async (fields) => {
    const results = [];
    for (const el of fields) {results.push(await mark(el));}
    return results.every(Boolean);
};

/**
 * Generates a random float between min (inclusive) and max (exclusive)
 * @param {number|null} min
 * @param {number|null} max
 * @returns {number}
 */
const randomFloat = (min, max) => {
    // If no bounds, use a default range
    const lower = min !== null ? min : -10;
    const upper = max !== null ? max : 10;
    // Simple random float in the range [lower, upper]
    return lower + Math.random() * (upper - lower);
};


/**
 * Handles the click event for the random generation button.
 * @param {HTMLButtonElement} buttonEl The button element.
 */
const handleRandomGeneration = async (buttonEl) => {
    // Retrieve the ranges data from the button's data attribute
    const rangesData = JSON.parse(buttonEl.dataset.ranges || '[]');
    // Convert array of objects to a map for easier lookup by field name
    const rangesMap = new Map();
    for (const data of Object.values(rangesData)) {
        rangesMap.set(data.field_name, data);
    }

    let needsUpdate = false;

    for (const [fieldName, range] of rangesMap.entries()) {
        const inputField = document.querySelector(`input[name="${fieldName}"]`);

        if (inputField) {
            // Fix: Explicitly parse min/max as floats.
            // If the value is null or empty string, parseFloat returns NaN, which we handle by coercing to null.
            const minVal = range.min !== null ? parseFloat(range.min) : null;
            const maxVal = range.max !== null ? parseFloat(range.max) : null;

            // Check if minVal or maxVal is NaN (if it was an invalid non-empty string)
            // If they are NaN, they will be treated as null by randomFloat's checks.

            // Generate a random number. Use two decimal places for a more "readable" result.
            const randomVal = randomFloat(minVal, maxVal).toFixed(2);

            // Update the input field
            inputField.value = randomVal;
            needsUpdate = true;
        }
    }

    // Trigger validation and marking after filling the fields
    if (needsUpdate) {
        const allFields = Array.from(document.querySelectorAll('input[name^=assignsubmission_formulacheck_p]'));
        await validateAll(allFields);
    }
};



export const init = async (selector) => {
    const fields = Array.from(document.querySelectorAll(selector));
    if (!fields.length) {return;}

    const form = fields[0].closest('form');
    //const onInput = () => { mark(this); };
    fields.forEach(el => el.addEventListener('input', () => mark(el)));

    if (form) {
        form.addEventListener('submit', async (e) => {
            // Only block if out-of-range or non-numeric. Required+numeric client rules also apply via QuickForm.
            const ok = await validateAll(fields);
            if (!ok) {
                e.preventDefault();
                const firstBad = fields.find(f => f.classList.contains('is-invalid'));
                if (firstBad) {firstBad.focus();}
            }
        });
    }

    // New: Handle the random button click
    const randomButton = document.getElementById("id_assignsubmission_formulacheck_generate_random");
    if (randomButton) {
        randomButton.addEventListener('click', async (e) => {
            e.preventDefault(); // Prevent form submission
            await handleRandomGeneration(randomButton);
        });
    }

    // Initial gentle pass.
    await validateAll(fields);
};
