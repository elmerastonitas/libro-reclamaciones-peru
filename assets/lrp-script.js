document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('lrp-claim-form');
    if (!form) return;

    const steps = form.querySelectorAll('.lrp-step');
    const progressItems = document.querySelectorAll('.lrp-progress-item');
    const nextBtns = form.querySelectorAll('.lrp-next-btn');
    const prevBtns = form.querySelectorAll('.lrp-prev-btn');

    let currentStep = 0;

    function showStep(stepIndex) {
        steps.forEach((step, index) => {
            step.style.display = (index === stepIndex) ? 'block' : 'none';
        });

        progressItems.forEach((item, index) => {
            if (index <= stepIndex) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });

        const formContainer = document.querySelector('.lrp-form-container');
        if (formContainer) {
            formContainer.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // ── Document validation rules ──
    var docRules = {
        'DNI': { pattern: /^[0-9]{8}$/, maxLength: 8, inputMode: 'numeric', filter: /[^0-9]/g, message: 'DNI inválido. Debe tener 8 dígitos numéricos.' },
        'CE': { pattern: /^[0-9]{9}$/, maxLength: 9, inputMode: 'numeric', filter: /[^0-9]/g, message: 'Carnet de extranjería inválido. Debe tener 9 dígitos numéricos.' },
        'PAS': { pattern: /^[A-Z0-9]{6,10}$/i, maxLength: 10, inputMode: 'text', filter: /[^A-Za-z0-9]/g, message: 'Pasaporte inválido. Debe tener entre 6 y 10 caracteres alfanuméricos.' }
    };

    function setupDocValidation(selectId, inputId) {
        var selectEl = document.getElementById(selectId);
        var inputEl = document.getElementById(inputId);
        if (!selectEl || !inputEl) return;

        function applyRules() {
            var type = selectEl.value.toUpperCase();
            var rule = docRules[type];
            if (rule) {
                inputEl.setAttribute('maxlength', rule.maxLength);
                inputEl.setAttribute('inputmode', rule.inputMode);
                inputEl.value = inputEl.value.replace(rule.filter, '');
                if (type === 'PAS') {
                    inputEl.value = inputEl.value.toUpperCase();
                }
            } else {
                inputEl.removeAttribute('maxlength');
                inputEl.setAttribute('inputmode', 'text');
            }
            inputEl.setCustomValidity('');
        }

        selectEl.addEventListener('change', function () {
            inputEl.value = '';
            applyRules();
        });

        inputEl.addEventListener('input', function () {
            applyRules();
        });

        // Initial setup
        applyRules();
    }

    setupDocValidation('lrp-consumer-doc-type', 'lrp-consumer-doc-number');
    setupDocValidation('lrp-guardian-doc-type', 'lrp-guardian-doc-number');

    // ── Validate document number ──
    function validateDocNumber(selectId, inputId) {
        var selectEl = document.getElementById(selectId);
        var inputEl = document.getElementById(inputId);
        if (!selectEl || !inputEl) return true;
        if (!inputEl.hasAttribute('required') && inputEl.value === '') return true;

        var type = selectEl.value.toUpperCase();
        var rule = docRules[type];
        if (rule && !rule.pattern.test(inputEl.value)) {
            inputEl.setCustomValidity(rule.message);
            inputEl.reportValidity();
            return false;
        }
        inputEl.setCustomValidity('');
        return true;
    }

    // ── Step validation ──
    function validateStep(stepIndex) {
        const currentStepEl = steps[stepIndex];
        const inputs = currentStepEl.querySelectorAll('input:required, select:required, textarea:required');
        let valid = true;

        inputs.forEach(input => {
            if (!input.checkValidity()) {
                input.reportValidity();
                valid = false;
                return;
            }
        });

        // Step 1: Validate document numbers
        if (stepIndex === 0) {
            if (valid && !validateDocNumber('lrp-consumer-doc-type', 'lrp-consumer-doc-number')) {
                valid = false;
            }
            // Guardian doc validation (only if minor is checked and fields visible)
            var minorCheck = document.getElementById('lrp-is-minor-check');
            if (valid && minorCheck && minorCheck.checked) {
                if (!validateDocNumber('lrp-guardian-doc-type', 'lrp-guardian-doc-number')) {
                    valid = false;
                }
            }
        }

        // Step 2: Validate amount >= 1 and date not future
        if (stepIndex === 1) {
            if (valid) {
                var amountInput = currentStepEl.querySelector('input[name="contracted_good_amount"]');
                if (amountInput && parseFloat(amountInput.value) < 1) {
                    amountInput.setCustomValidity('El monto reclamado debe ser mayor o igual a 1.');
                    amountInput.reportValidity();
                    valid = false;
                } else if (amountInput) {
                    amountInput.setCustomValidity('');
                }
            }
            if (valid) {
                var dateInput = currentStepEl.querySelector('input[name="contracted_good_date"]');
                if (dateInput && dateInput.value) {
                    var today = new Date();
                    today.setHours(0, 0, 0, 0);
                    var selected = new Date(dateInput.value + 'T00:00:00');
                    if (selected > today) {
                        dateInput.setCustomValidity('La fecha de compra no puede ser posterior a la fecha actual.');
                        dateInput.reportValidity();
                        valid = false;
                    } else {
                        dateInput.setCustomValidity('');
                    }
                }
            }
        }

        // Step 3: Radio buttons
        if (stepIndex === 2) {
            const radioGroup = currentStepEl.querySelector('input[name="claim_type"]:checked');
            if (!radioGroup) {
                const firstRadio = currentStepEl.querySelector('input[name="claim_type"]');
                if (firstRadio) {
                    firstRadio.setCustomValidity('Seleccione una opción');
                    firstRadio.reportValidity();
                    firstRadio.addEventListener('change', () => firstRadio.setCustomValidity(''));
                }
                valid = false;
            }
        }

        return valid;
    }

    nextBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                currentStep++;
                showStep(currentStep);
            }
        });
    });

    prevBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            currentStep--;
            showStep(currentStep);
        });
    });

    // Turnstile validation on submit
    form.addEventListener('submit', function (e) {
        var turnstileWidget = form.querySelector('.cf-turnstile');
        if (turnstileWidget) {
            var tokenInput = form.querySelector('input[name="cf-turnstile-response"]');
            var token = tokenInput ? tokenInput.value : '';
            var errorDiv = form.querySelector('.lrp-turnstile-error');

            if (!token) {
                e.preventDefault();
                if (errorDiv) {
                    errorDiv.textContent = 'Por favor, complete la verificación anti-spam.';
                    errorDiv.style.display = 'block';
                }
                turnstileWidget.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            } else {
                if (errorDiv) {
                    errorDiv.style.display = 'none';
                }
            }
        }
    });

    // Initialize
    showStep(currentStep);

    // Minor Checkbox Logic
    const minorCheckbox = document.getElementById('lrp-is-minor-check');
    const guardianBox = document.getElementById('guardian_box');

    if (minorCheckbox && guardianBox) {
        const guardianInputs = guardianBox.querySelectorAll('.guardian-input');

        function toggleGuardianFields() {
            if (minorCheckbox.checked) {
                guardianBox.style.display = 'block';
                guardianInputs.forEach(input => input.setAttribute('required', 'required'));
            } else {
                guardianBox.style.display = 'none';
                guardianInputs.forEach(input => input.removeAttribute('required'));
            }
        }

        // Initial check
        toggleGuardianFields();

        // Listener
        minorCheckbox.addEventListener('change', toggleGuardianFields);
    }
});
