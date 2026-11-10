    const lockStatusInputs = document.querySelectorAll('input[name="lock_status"]');
    const outcomeInputs = document.querySelectorAll('input[name="outcome"]');
    const outcomeSection = document.getElementById('outcome-section');
    const spareLockSection = document.getElementById('spare-lock-section');
    const spareLockSelect = document.getElementById('spare_lock_id');

    function updateOutFields() {
        const selectedLockStatus = document.querySelector('input[name="lock_status"]:checked')?.value
            ?? document.querySelector('input[name="lock_status"][type="hidden"]')?.value;
        const isOut = selectedLockStatus === 'out';
        const isOutstanding = document.querySelector('input[name="outcome"]:checked')?.value === 'outstanding';
        outcomeSection.classList.toggle('hidden', !isOut);
        outcomeInputs.forEach((input) => {
            input.required = isOut;
            if (!isOut) input.checked = false;
        });
        if (spareLockSection && spareLockSelect) {
            spareLockSection.classList.toggle('hidden', !isOut || !isOutstanding);
            spareLockSelect.required = isOut && isOutstanding;
            spareLockSelect.disabled = !isOut || !isOutstanding;
            if (!isOut || !isOutstanding) spareLockSelect.value = '';
        }

    }

    lockStatusInputs.forEach((input) => input.addEventListener('change', updateOutFields));
    outcomeInputs.forEach((input) => input.addEventListener('change', updateOutFields));
    updateOutFields();
