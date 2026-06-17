document.addEventListener('submit', (event) => {
    const form = event.target;
    const message = form.getAttribute('data-confirm');

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

const appointmentWizard = document.querySelector('[data-appointment-wizard]');

if (appointmentWizard) {
    const stepPanels = [...appointmentWizard.querySelectorAll('[data-step-panel]')];
    const stepItems = [...appointmentWizard.querySelectorAll('.appointment-stepper span')];
    const nextButtons = [...appointmentWizard.querySelectorAll('[data-step-next]')];
    const goButtons = [...appointmentWizard.querySelectorAll('[data-step-go]')];
    const healthOptions = [...appointmentWizard.querySelectorAll('[data-health-option]')];
    const healthPanels = [...appointmentWizard.querySelectorAll('[data-health-panel]')];
    const serviceCategorySelect = appointmentWizard.querySelector('[data-service-category]');
    const serviceSelect = appointmentWizard.querySelector('[name="serviceType"]');
    const durationOutput = appointmentWizard.querySelector('[data-treatment-duration]');
    const priceOutput = appointmentWizard.querySelector('[data-treatment-price]');
    const dentistOptions = [...appointmentWizard.querySelectorAll('[data-dentist-option]')];
    const paymentMethods = [...appointmentWizard.querySelectorAll('[name="paymentMethod"]')];
    const qrPaymentPanel = appointmentWizard.querySelector('[data-qr-payment-panel]');
    const qrMethods = [...appointmentWizard.querySelectorAll('[data-qr-method]')];
    const qrPreview = appointmentWizard.querySelector('[data-qr-preview]');
    const qrPreviewTitle = appointmentWizard.querySelector('[data-qr-preview-title]');
    const qrImage = appointmentWizard.querySelector('[data-qr-image]');
    const qrInstruction = appointmentWizard.querySelector('[data-qr-instruction]');
    const qrDownloadBtn = appointmentWizard.querySelector('[data-qr-download-btn]');
    const receiptUploadSection = appointmentWizard.querySelector('[data-receipt-upload-section]');
    const receiptInput = appointmentWizard.querySelector('[data-receipt-input]');
    const receiptDropZone = appointmentWizard.querySelector('[data-receipt-drop-zone]');
    const receiptPreview = appointmentWizard.querySelector('[data-receipt-preview]');
    const receiptPreviewImg = appointmentWizard.querySelector('[data-receipt-preview-img]');
    const receiptFileName = appointmentWizard.querySelector('[data-receipt-file-name]');
    const receiptRemoveBtn = appointmentWizard.querySelector('[data-receipt-remove]');
    const ageInput = appointmentWizard.querySelector('[name="patientAge"]');
    const patientSelect = appointmentWizard.querySelector('[name="userID"]');
    const currentPatientName = appointmentWizard.querySelector('[data-current-patient-name]');
    const bookingDateInput = appointmentWizard.querySelector('[name="appointmentDate"]');
    const bookingTimeInput = appointmentWizard.querySelector('[name="appointmentTime"]');

    const selectedHealthCategory = () => appointmentWizard.querySelector('[data-health-option]:checked')?.value || 'none';

    const selectedHealthProblems = (category) => [
        ...appointmentWizard.querySelectorAll(`[data-health-problem="${category}"]:checked`),
    ];

    const textOrDash = (value) => {
        const text = String(value || '').trim();
        return text === '' ? '-' : text;
    };

    const selectedOptionText = (select) => textOrDash(select?.selectedOptions?.[0]?.textContent || select?.value);

    const updateText = (selector, value) => {
        const target = appointmentWizard.querySelector(selector);
        if (target) {
            target.textContent = textOrDash(value);
        }
    };

    const selectedTreatment = () => serviceSelect?.selectedOptions?.[0] || null;

    const treatmentLabel = () => textOrDash(selectedTreatment()?.dataset.treatmentLabel || serviceSelect?.value);

    const treatmentDurationLabel = () => textOrDash(selectedTreatment()?.dataset.durationLabel);

    const treatmentPriceLabel = () => textOrDash(selectedTreatment()?.dataset.priceLabel);

    const selectedDentist = () => appointmentWizard.querySelector('[data-dentist-option]:checked');

    const dentistSummary = () => selectedDentist()?.value;

    const selectedPaymentMethod = () => appointmentWizard.querySelector('[name="paymentMethod"]:checked')?.value || 'counter';

    const selectedQrMethod = () => appointmentWizard.querySelector('[data-qr-method]:checked');

    const qrMethodLabel = () => {
        const selected = selectedQrMethod();
        if (!selected) {
            return '';
        }

        return selected.value === 'tng' ? "Touch 'n Go" : 'FPX';
    };

    /* ── Receipt preview helper (must be before syncQrPayment) ── */
    const showReceiptPreview = (file) => {
        if (!receiptPreview) return;

        if (!file) {
            receiptPreview.hidden = true;
            if (receiptDropZone) receiptDropZone.hidden = false;
            if (receiptPreviewImg) receiptPreviewImg.src = '';
            if (receiptFileName) receiptFileName.textContent = '-';
            return;
        }

        if (receiptFileName) receiptFileName.textContent = file.name;

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (receiptPreviewImg) receiptPreviewImg.src = e.target.result;
            };
            reader.readAsDataURL(file);
            if (receiptPreviewImg) receiptPreviewImg.hidden = false;
        } else {
            if (receiptPreviewImg) receiptPreviewImg.hidden = true;
        }

        receiptPreview.hidden = false;
        if (receiptDropZone) receiptDropZone.hidden = true;
    };

    const syncQrPayment = () => {
        const useQr = selectedPaymentMethod() === 'qr';

        if (qrPaymentPanel) {
            qrPaymentPanel.hidden = !useQr;
        }

        qrMethods.forEach((method, index) => {
            method.required = useQr && index === 0;
            method.disabled = !useQr;
            if (!useQr) {
                method.checked = false;
            }
        });

        const label = qrMethodLabel();
        if (qrPreview) {
            qrPreview.hidden = !useQr || label === '';
        }

        if (qrPreviewTitle && label) {
            qrPreviewTitle.textContent = `${label} QR`;
        }

        if (qrImage) {
            if (label === 'FPX') {
                qrImage.src = 'assets/qr-fpx.jpg';
                qrImage.hidden = false;
                if (qrInstruction) {
                    qrInstruction.textContent = 'Scan the FPX DuitNow QR code above with your banking or e-wallet app to pay.';
                }
                if (qrDownloadBtn) {
                    qrDownloadBtn.href = 'assets/qr-fpx.jpg';
                    qrDownloadBtn.style.display = 'inline-flex';
                }
            } else if (label === "Touch 'n Go") {
                qrImage.src = 'assets/qr-tng.jpg';
                qrImage.hidden = false;
                if (qrInstruction) {
                    qrInstruction.textContent = 'Scan the TNG QR code with your Touch \'n Go eWallet app to pay.';
                }
                if (qrDownloadBtn) {
                    qrDownloadBtn.href = 'assets/qr-tng.jpg';
                    qrDownloadBtn.style.display = 'inline-flex';
                }
            } else {
                qrImage.hidden = true;
                if (qrDownloadBtn) {
                    qrDownloadBtn.href = '#';
                    qrDownloadBtn.style.display = 'none';
                }
            }
        }

        if (receiptUploadSection) {
            receiptUploadSection.hidden = !useQr;
            if (!useQr && receiptInput) {
                receiptInput.value = '';
                showReceiptPreview(null);
            }
        }
    };

    const syncTreatmentOptions = () => {
        if (!serviceCategorySelect || !serviceSelect) {
            return;
        }

        const category = serviceCategorySelect.value;
        const visibleOptions = [...serviceSelect.options].filter((option) => option.dataset.category === category);

        [...serviceSelect.options].forEach((option) => {
            const isVisible = option.dataset.category === category;
            option.hidden = !isVisible;
            option.disabled = !isVisible;
        });

        if (!visibleOptions.includes(serviceSelect.selectedOptions[0])) {
            serviceSelect.value = visibleOptions[0]?.value || '';
        }

        if (durationOutput) {
            durationOutput.textContent = treatmentDurationLabel();
        }

        if (priceOutput) {
            priceOutput.textContent = treatmentPriceLabel();
        }
    };

    const healthSummary = () => {
        const category = selectedHealthCategory();

        if (category === 'none') {
            return 'No health problem';
        }

        const optionLabel = appointmentWizard
            .querySelector(`[data-health-option][value="${category}"]`)
            ?.closest('label')
            ?.textContent
            ?.trim() || category;
        const problems = selectedHealthProblems(category).map((input) => input.value).join(', ');

        return `${optionLabel}: ${problems || 'Not selected'}`;
    };

    const scheduleSummary = () => {
        const date = bookingDateInput?.value || '';
        const time = bookingTimeInput?.value || '';

        if (!date && !time) {
            return '-';
        }

        return `${date || '-'} at ${time || '-'}`;
    };

    const updateReview = () => {
        const serviceCategory = selectedOptionText(serviceCategorySelect);
        const service = treatmentLabel();
        const duration = treatmentDurationLabel();
        const price = treatmentPriceLabel();
        const schedule = scheduleSummary();
        const dentist = dentistSummary();
        const patient = patientSelect ? selectedOptionText(patientSelect) : currentPatientName?.value;
        const paymentMethod = selectedPaymentMethod();
        const paymentMethodLabel = paymentMethod === 'qr' ? 'QR Bank / E-wallet' : 'Pay at clinic counter';

        updateText('[data-review-service-category]', serviceCategory);
        updateText('[data-review-service]', service);
        updateText('[data-review-duration]', duration);
        updateText('[data-review-price]', price);
        updateText('[data-review-dentist]', dentist);
        updateText('[data-review-patient]', patient);
        updateText('[data-review-age]', ageInput?.value ? `${ageInput.value} years old` : '');
        updateText('[data-review-schedule]', schedule);
        updateText('[data-review-health]', healthSummary());

        /* Payment card price display */
        updateText('[data-payment-price-counter]', price);
        updateText('[data-payment-price-qr]', price);

        /* Booking receipt */
        updateText('[data-receipt-service]', service);
        updateText('[data-receipt-dentist]', dentist);
        updateText('[data-receipt-patient]', patient);
        updateText('[data-receipt-schedule]', schedule);
        updateText('[data-receipt-duration]', duration);
        updateText('[data-receipt-method]', paymentMethodLabel);
        updateText('[data-receipt-total]', price);
    };

    const setStep = (step) => {
        stepPanels.forEach((panel) => {
            const isActive = panel.dataset.stepPanel === String(step);
            panel.hidden = !isActive;
            panel.classList.toggle('active', isActive);
        });

        stepItems.forEach((item, index) => {
            item.classList.toggle('active', index + 1 === step);
            item.classList.toggle('complete', index + 1 < step);
        });

        if (step >= 3) {
            updateReview();
        }
    };

    const updateHealthPanels = () => {
        const category = selectedHealthCategory();

        healthPanels.forEach((panel) => {
            const isActive = panel.dataset.healthPanel === category;
            panel.hidden = !isActive;

            if (!isActive) {
                panel.querySelectorAll('input[type="checkbox"]').forEach((input) => {
                    input.checked = false;
                });
            }
        });
    };

    const validateFields = (fields) => fields.every((field) => {
        if (!field || field.checkValidity()) {
            return true;
        }

        field.reportValidity();
        return false;
    });

    const canLeaveStep = (step) => {
        if (step === 1) {
            return validateFields([serviceCategorySelect, serviceSelect, dentistOptions[0]]);
        }

        if (step !== 2) {
            if (step === 4 && selectedPaymentMethod() === 'qr' && !selectedQrMethod()) {
                qrMethods[0]?.reportValidity();
                return false;
            }

            if (step === 4 && selectedPaymentMethod() === 'qr') {
                if (!receiptInput || !receiptInput.files || receiptInput.files.length === 0) {
                    window.alert('Please upload your payment receipt (TNG or bank) before submitting the booking.');
                    receiptDropZone?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    return false;
                }
            }

            return true;
        }

        if (patientSelect && !patientSelect.checkValidity()) {
            patientSelect.reportValidity();
            return false;
        }

        if (ageInput && !ageInput.checkValidity()) {
            ageInput.reportValidity();
            return false;
        }

        if (!validateFields([bookingDateInput, bookingTimeInput])) {
            return false;
        }

        const category = selectedHealthCategory();
        if (category !== 'none' && selectedHealthProblems(category).length === 0) {
            window.alert('Please choose at least one health problem.');
            appointmentWizard
                .querySelector(`[data-health-panel="${category}"] input[type="checkbox"]`)
                ?.focus();
            return false;
        }

        return true;
    };

    healthOptions.forEach((option) => option.addEventListener('change', updateHealthPanels));
    appointmentWizard.querySelectorAll('[data-health-problem]').forEach((input) => {
        input.addEventListener('change', updateHealthPanels);
    });

    serviceCategorySelect?.addEventListener('change', () => {
        syncTreatmentOptions();
        updateReview();
    });

    serviceSelect?.addEventListener('change', () => {
        if (durationOutput) {
            durationOutput.textContent = treatmentDurationLabel();
        }
        if (priceOutput) {
            priceOutput.textContent = treatmentPriceLabel();
        }
        updateReview();
    });

    dentistOptions.forEach((option) => {
        option.addEventListener('change', updateReview);
    });

    paymentMethods.forEach((method) => {
        method.addEventListener('change', () => {
            syncQrPayment();
            updateReview();
        });
    });

    qrMethods.forEach((method) => {
        method.addEventListener('change', () => {
            syncQrPayment();
            updateReview();
        });
    });

    nextButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const panel = button.closest('[data-step-panel]');
            const currentStep = Number(panel?.dataset.stepPanel || 1);
            const nextStep = Number(button.dataset.nextStep || currentStep + 1);

            if (canLeaveStep(currentStep)) {
                setStep(nextStep);
            }
        });
    });

    goButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const targetStep = Number(button.dataset.stepGo || 1);

            if (targetStep >= 1) {
                setStep(targetStep);
            }
        });
    });

    appointmentWizard.addEventListener('input', updateReview);
    appointmentWizard.addEventListener('change', updateReview);

    appointmentWizard.addEventListener('submit', (event) => {
        const serviceStepValid = canLeaveStep(1);
        const patientStepValid = serviceStepValid ? canLeaveStep(2) : false;
        const paymentStepValid = serviceStepValid && patientStepValid ? canLeaveStep(4) : false;

        if (!serviceStepValid || !patientStepValid || !paymentStepValid) {
            event.preventDefault();
            setStep(!serviceStepValid ? 1 : (!patientStepValid ? 2 : 4));
        }
    });

    const cancelBtn = appointmentWizard.querySelector('[data-cancel-booking]');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            if (!window.confirm('Cancel this booking? All entered information will be cleared.')) {
                return;
            }
            appointmentWizard.reset();
            if (receiptInput) {
                receiptInput.value = '';
                showReceiptPreview(null);
            }
            syncTreatmentOptions();
            syncQrPayment();
            updateHealthPanels();
            updateReview();
            setStep(1);
        });
    }

    /* ── Receipt event listeners ── */
    if (receiptInput) {
        receiptInput.addEventListener('change', () => {
            const file = receiptInput.files?.[0] || null;
            if (file && file.size > 5 * 1024 * 1024) {
                window.alert('Receipt file must be under 5 MB.');
                receiptInput.value = '';
                showReceiptPreview(null);
                return;
            }
            showReceiptPreview(file);
        });
    }

    if (receiptDropZone) {
        ['dragenter', 'dragover'].forEach((event) => {
            receiptDropZone.addEventListener(event, (e) => {
                e.preventDefault();
                receiptDropZone.classList.add('drag-over');
            });
        });

        ['dragleave', 'drop'].forEach((event) => {
            receiptDropZone.addEventListener(event, (e) => {
                e.preventDefault();
                receiptDropZone.classList.remove('drag-over');
            });
        });

        receiptDropZone.addEventListener('drop', (e) => {
            const file = e.dataTransfer?.files?.[0];
            if (!file || !receiptInput) return;

            const transfer = new DataTransfer();
            transfer.items.add(file);
            receiptInput.files = transfer.files;
            receiptInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    if (receiptRemoveBtn && receiptInput) {
        receiptRemoveBtn.addEventListener('click', () => {
            receiptInput.value = '';
            showReceiptPreview(null);
        });
    }

    syncTreatmentOptions();
    syncQrPayment();
    updateHealthPanels();
    updateReview();
}

const slotPicker = document.querySelector('[data-slot-picker]');
const slotDateInput = document.querySelector('[data-slot-date-input]');
const slotCheckForm = document.querySelector('[data-slot-check-form]');
const bookingDate = document.getElementById('bookingDate');
const bookingTime = document.getElementById('bookingTime');
let activeSlotRequest = 0;

if (slotPicker && bookingDate && bookingTime) {
    const buildSlotButton = (slot) => {
        const button = document.createElement('button');
        button.className = `slot-button ${slot.status}`;
        button.type = 'button';
        button.dataset.slot = slot.time;
        button.dataset.status = slot.status;
        button.dataset.label = slot.label;
        button.setAttribute('aria-label', `${slot.time} ${slot.label}`);
        button.disabled = !slot.available;

        const dot = document.createElement('span');
        dot.className = 'slot-dot';
        dot.setAttribute('aria-hidden', 'true');

        const label = document.createElement('span');
        label.textContent = slot.time;

        button.append(dot, label);

        return button;
    };

    const syncTimeOptions = (slots) => {
        const selectedTime = bookingTime.value;
        let firstAvailable = '';
        bookingTime.querySelector('[data-slot-placeholder]')?.remove();

        [...bookingTime.options].forEach((option) => {
            const slot = slots.find((item) => item.time === option.value);

            if (!slot) {
                return;
            }

            option.disabled = !slot.available;
            option.textContent = slot.available ? slot.time : `${slot.time} - ${slot.label}`;

            if (slot.available && firstAvailable === '') {
                firstAvailable = slot.time;
            }
        });

        if (firstAvailable === '') {
            const placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.textContent = 'No available times for this date';
            placeholder.disabled = true;
            placeholder.selected = true;
            placeholder.dataset.slotPlaceholder = 'true';
            bookingTime.prepend(placeholder);
            bookingTime.value = '';
            return;
        }

        const selectedSlot = slots.find((slot) => slot.time === selectedTime);
        if (!selectedSlot?.available) {
            bookingTime.value = firstAvailable;
        }
    };

    const currentSlotsFromButtons = () => [...slotPicker.querySelectorAll('.slot-button')].map((button) => ({
        time: button.dataset.slot || '',
        status: button.dataset.status || 'available',
        label: button.dataset.label || 'Available',
        available: !button.disabled,
    }));

    const renderSlots = (slots, date) => {
        slotPicker.dataset.slotDate = date;
        slotPicker.replaceChildren(...slots.map(buildSlotButton));
        syncTimeOptions(slots);
    };

    const syncDateFields = (date) => {
        bookingDate.value = date;

        if (slotDateInput) {
            slotDateInput.value = date;
        }
    };

    const refreshSlots = async (date, options = {}) => {
        if (!date) {
            return;
        }

        const requestId = ++activeSlotRequest;
        slotPicker.classList.add('loading');

        try {
            const url = new URL('appointments.php', window.location.href);
            url.searchParams.set('slots', '1');
            url.searchParams.set('date', date);

            const response = await fetch(url, { credentials: 'same-origin' });
            const payload = await response.json();

            if (requestId !== activeSlotRequest || !payload.ok) {
                return;
            }

            syncDateFields(payload.date);
            renderSlots(payload.slots, payload.date);

            if (options.updateHistory !== false) {
                const pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('date', payload.date);
                pageUrl.searchParams.delete('slots');
                window.history.replaceState({}, '', pageUrl);
            }
        } catch (error) {
            slotPicker.classList.add('error');
        } finally {
            if (requestId === activeSlotRequest) {
                slotPicker.classList.remove('loading');
            }
        }
    };

    syncTimeOptions(currentSlotsFromButtons());

    bookingDate.addEventListener('change', () => {
        refreshSlots(bookingDate.value);
    });

    if (slotDateInput) {
        slotDateInput.addEventListener('change', () => {
            syncDateFields(slotDateInput.value);
            refreshSlots(slotDateInput.value);
        });
    }

    const checkSlots = () => {
        const date = slotDateInput ? slotDateInput.value : bookingDate.value;
        syncDateFields(date);
        refreshSlots(date);
    };

    if (slotCheckForm) {
        slotCheckForm.addEventListener('submit', (event) => {
            event.preventDefault();
            checkSlots();
        });
        slotCheckForm.querySelector('[data-slot-check]')?.addEventListener('click', checkSlots);
    }

    slotPicker.addEventListener('click', (event) => {
        const button = event.target.closest('.slot-button.available');

        if (!button || button.disabled) {
            return;
        }

        const slot = button.getAttribute('data-slot');
        const date = slotPicker.getAttribute('data-slot-date');

        if (date) {
            bookingDate.value = date;
            if (slotDateInput) {
                slotDateInput.value = date;
            }
        }

        if (slot) {
            bookingTime.value = slot;
            bookingTime.dispatchEvent(new Event('change', { bubbles: true }));
        }

        slotPicker.querySelectorAll('.slot-button.selected').forEach((selected) => {
            selected.classList.remove('selected');
        });
        button.classList.add('selected');
    });
}

/* ── Profile hero – instant avatar upload on file pick ── */
const quickUploadInput = document.getElementById('avatar-quick-upload');
const quickUploadForm  = document.getElementById('avatar-quick-form');
if (quickUploadInput && quickUploadForm) {
    quickUploadInput.addEventListener('change', () => {
        if (quickUploadInput.files && quickUploadInput.files.length > 0) {
            quickUploadForm.submit();
        }
    });
}

const chatForm = document.getElementById('chatForm');
const chatWindow = document.getElementById('chatWindow');

function appendMessage(text, type, options = {}) {
    if (!chatWindow) {
        return null;
    }

    chatWindow.querySelector('.chat-empty-state')?.remove();

    const message = document.createElement('article');
    message.className = `chat-message ${type}`;
    if (options.loading) {
        message.classList.add('loading');
    }

    const avatar = document.createElement('div');
    avatar.className = `chat-avatar ${type === 'user' ? 'user-avatar' : 'assistant-avatar'}`;

    if (type === 'user') {
        avatar.textContent = 'U';
    } else {
        const image = document.createElement('img');
        image.src = 'assets/detabot-logo.svg';
        image.alt = 'Detabot';
        avatar.appendChild(image);
    }

    const content = document.createElement('div');
    content.className = 'chat-content';
    content.textContent = text;

    message.append(avatar, content);
    chatWindow.appendChild(message);
    chatWindow.scrollTop = chatWindow.scrollHeight;

    return message;
}

function setMessageText(message, text) {
    if (!message) {
        return;
    }

    const content = message.querySelector('.chat-content');
    if (content) {
        content.textContent = text;
    }
    message.classList.remove('loading');
    chatWindow.scrollTop = chatWindow.scrollHeight;
}

function resizeComposer(input) {
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
}

if (chatForm && chatWindow) {
    chatWindow.scrollTop = chatWindow.scrollHeight;
    const input = chatForm.querySelector('[name="messageText"]');
    const sendButton = chatForm.querySelector('button[type="submit"]');

    if (input) {
        resizeComposer(input);
        input.addEventListener('input', () => resizeComposer(input));
        input.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                chatForm.requestSubmit();
            }
        });
    }

    chatForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const message = input.value.trim();
        if (!message) {
            return;
        }

        appendMessage(message, 'user');
        input.value = '';
        resizeComposer(input);
        input.disabled = true;
        if (sendButton) {
            sendButton.disabled = true;
        }

        const thinkingMessage = appendMessage('Detabot is thinking...', 'assistant', { loading: true });

        const body = new FormData();
        body.set('action', 'chat_message');
        body.set('messageText', message);
        body.set('_csrf_token', window.DETABOT_CSRF || '');

        try {
            const response = await fetch(window.location.pathname, {
                method: 'POST',
                body,
                credentials: 'same-origin',
            });
            const payload = await response.json();
            setMessageText(thinkingMessage, payload.ok ? payload.response : 'Sorry, I could not answer that message.');
        } catch (error) {
            setMessageText(thinkingMessage, 'Connection problem. Please try again.');
        } finally {
            input.disabled = false;
            if (sendButton) {
                sendButton.disabled = false;
            }
            input.focus();
        }
    });

}

/* ── Dental Charting & File Upload Component ── */
function addFileUploadRow() {
    const container = document.getElementById('file-uploads-container');
    if (!container) return;
    const row = document.createElement('div');
    row.className = 'upload-row';
    row.style.display = 'flex';
    row.style.gap = '10px';
    row.style.marginBottom = '10px';
    row.style.alignItems = 'center';
    
    row.innerHTML = `
        <select name="fileTypes[]" style="width:150px;">
            <option value="xray">X-Ray</option>
            <option value="cbct">CBCT Scan</option>
            <option value="intraoral">Intraoral Photo</option>
            <option value="document">Document</option>
            <option value="other">Other</option>
        </select>
        <input type="file" name="files[]" accept=".pdf,image/jpeg,image/png,image/webp">
        <button type="button" class="btn ghost small" onclick="this.parentElement.remove()" style="padding: 4px 8px;">X</button>
    `;
    container.appendChild(row);
}

function initDentalChart() {
    const chartUi = document.getElementById('dental-chart-ui');
    const chartInput = document.getElementById('dentalChartData');
    if (!chartUi || !chartInput) return;

    // Standard FDI adult teeth numbering
    const upperTeeth = [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28];
    const lowerTeeth = [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38];
    
    // Statuses
    const statuses = ['Healthy', 'Decay', 'Filling', 'Missing', 'Crown'];
    let chartData = {};
    try {
        chartData = JSON.parse(chartInput.value || '{}');
    } catch (e) {}

    const renderTooth = (toothNum) => {
        const t = document.createElement('div');
        t.className = 'tooth';
        t.dataset.tooth = toothNum;
        
        let currentStatus = chartData[toothNum] || 'Healthy';
        t.dataset.status = currentStatus;
        
        t.innerHTML = `
            <div class="tooth-num">${toothNum}</div>
            <div class="tooth-shape"></div>
            <div class="tooth-status">${currentStatus}</div>
        `;
        
        t.addEventListener('click', () => {
            let idx = statuses.indexOf(t.dataset.status);
            idx = (idx + 1) % statuses.length;
            const newStatus = statuses[idx];
            t.dataset.status = newStatus;
            t.querySelector('.tooth-status').innerText = newStatus;
            
            if (newStatus === 'Healthy') {
                delete chartData[toothNum];
            } else {
                chartData[toothNum] = newStatus;
            }
            chartInput.value = JSON.stringify(chartData);
        });
        
        return t;
    };

    const upperRow = document.createElement('div');
    upperRow.className = 'tooth-row';
    upperTeeth.forEach(n => upperRow.appendChild(renderTooth(n)));
    
    const lowerRow = document.createElement('div');
    lowerRow.className = 'tooth-row';
    lowerTeeth.forEach(n => lowerRow.appendChild(renderTooth(n)));
    
    chartUi.appendChild(upperRow);
    chartUi.appendChild(lowerRow);
}

document.addEventListener('DOMContentLoaded', () => {
    initDentalChart();
    
    // Render read-only charts in the timeline
    document.querySelectorAll('.saved-dental-chart').forEach(el => {
        try {
            const data = JSON.parse(el.dataset.chart || '{}');
            const statuses = Object.keys(data);
            if (statuses.length === 0) return;
            
            const list = document.createElement('div');
            list.className = 'saved-chart-list';
            statuses.forEach(t => {
                const badge = document.createElement('span');
                badge.className = `tooth-badge status-${data[t].toLowerCase()}`;
                badge.innerText = `Tooth ${t}: ${data[t]}`;
                list.appendChild(badge);
            });
            el.appendChild(list);
        } catch (e) {}
    });
});

