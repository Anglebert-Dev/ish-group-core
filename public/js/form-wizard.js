(function () {
    'use strict';

    function initFileInputs(root) {
        (root || document).querySelectorAll('[data-file-input]').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';

            var input = wrap.querySelector('[data-file-field]');
            var dropzone = wrap.querySelector('[data-dropzone]');
            var label = wrap.querySelector('[data-dropzone-label]');
            var preview = wrap.querySelector('[data-file-preview]');
            var multiple = wrap.dataset.multiple === '1';
            var maxMb = parseFloat(wrap.dataset.maxSizeMb || '0');
            var defaultLabel = label ? label.textContent : '';

            function renderPreview(files) {
                if (!preview) return;
                preview.innerHTML = '';
                if (!files || !files.length) {
                    preview.classList.add('hidden');
                    if (label) label.textContent = defaultLabel;
                    return;
                }

                preview.classList.remove('hidden');
                if (label) {
                    label.textContent = files.length === 1
                        ? files[0].name
                        : files.length + ' files selected';
                }

                Array.prototype.forEach.call(files, function (file) {
                    if (!file.type || file.type.indexOf('image/') !== 0) {
                        var chip = document.createElement('span');
                        chip.className = 'inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-surface border border-line text-ink';
                        chip.textContent = file.name;
                        preview.appendChild(chip);
                        return;
                    }

                    var img = document.createElement('img');
                    img.className = 'h-16 w-auto max-w-[8rem] object-contain rounded border border-line bg-surface p-1';
                    img.alt = file.name;
                    img.src = URL.createObjectURL(file);
                    preview.appendChild(img);
                });
            }

            function applyFiles(fileList) {
                if (!fileList || !fileList.length) return;

                var files = Array.prototype.slice.call(fileList);
                if (maxMb > 0) {
                    files = files.filter(function (file) {
                        return file.size <= maxMb * 1024 * 1024;
                    });
                }

                if (!files.length) return;

                if (typeof DataTransfer !== 'undefined') {
                    var dt = new DataTransfer();
                    (multiple ? files : [files[0]]).forEach(function (file) {
                        dt.items.add(file);
                    });
                    input.files = dt.files;
                    renderPreview(input.files);
                } else {
                    renderPreview(files);
                }
            }

            input.addEventListener('change', function () {
                renderPreview(input.files);
            });

            ['dragenter', 'dragover'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-brand', 'bg-brand-light');
                });
            });

            ['dragleave', 'drop'].forEach(function (evt) {
                dropzone.addEventListener(evt, function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-brand', 'bg-brand-light');
                });
            });

            dropzone.addEventListener('drop', function (e) {
                applyFiles(e.dataTransfer.files);
            });
        });
    }

    function fieldLabel(field) {
        if (field.labels && field.labels[0]) return field.labels[0].textContent.trim();
        var wrap = field.closest('div');
        var label = wrap ? wrap.querySelector('label') : null;
        return label ? label.textContent.trim() : (field.name || field.id || 'Field');
    }

    function fieldDisplayValue(field) {
        if (!field) return '—';

        if (field.type === 'checkbox' || field.type === 'radio') {
            var group = field.form.querySelectorAll('[name="' + field.name + '"]');
            var selected = [];
            group.forEach(function (el) {
                if (el.checked) {
                    var lbl = el.closest('label');
                    selected.push(lbl ? lbl.textContent.trim() : el.value);
                }
            });
            return selected.length ? selected.join(', ') : '—';
        }

        if (field.tagName === 'SELECT') {
            var opt = field.options[field.selectedIndex];
            return opt && opt.value ? opt.textContent.trim() : '—';
        }

        if (field.type === 'file') {
            if (!field.files || !field.files.length) return '—';
            return Array.prototype.map.call(field.files, function (f) { return f.name; }).join(', ');
        }

        var value = (field.value || '').trim();
        if (!value) return '—';

        var reviewLabel = field.getAttribute('data-review-label');
        if (reviewLabel) return reviewLabel;

        if (field.type === 'datetime-local' || field.type === 'date' || field.type === 'time') {
            return value.replace('T', ' ');
        }

        if (/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/.test(value)) {
            return value.replace('T', ' ');
        }

        return value;
    }

    function refreshReview(form) {
        form.querySelectorAll('[data-review-for]').forEach(function (el) {
            var name = el.getAttribute('data-review-for');
            var field = form.querySelector('[name="' + name + '"]');
            if (!field && name.indexOf('[]') === -1) {
                field = form.querySelector('[name="' + name + '[]"]');
            }
            el.textContent = fieldDisplayValue(field);
        });
    }

    function setStep(form, step) {
        var total = parseInt(form.dataset.wizardStepCount || '1', 10);
        step = Math.max(1, Math.min(total, step));
        form.dataset.wizardCurrent = String(step);

        form.querySelectorAll('[data-wizard-panel]').forEach(function (panel) {
            var panelStep = parseInt(panel.getAttribute('data-wizard-panel'), 10);
            if (panelStep === step) {
                panel.hidden = false;
            } else {
                panel.hidden = true;
            }
        });

        var reached = parseInt(form.dataset.wizardReached || '1', 10);
        if (step > reached) {
            form.dataset.wizardReached = String(step);
            reached = step;
        }

        form.querySelectorAll('[data-wizard-step-btn]').forEach(function (btn) {
            var btnStep = parseInt(btn.getAttribute('data-wizard-step-btn'), 10);
            var badge = btn.querySelector('[data-wizard-step-badge]');
            var isCurrent = btnStep === step;
            var isDone = btnStep < step;
            var clickable = btnStep <= reached;

            btn.disabled = !clickable;
            btn.className = 'w-full flex flex-col items-center gap-1 px-1 py-2 rounded-lg text-center transition-all';

            if (isCurrent) {
                btn.className += ' bg-brand text-white shadow-md';
            } else if (isDone) {
                btn.className += ' bg-brand-light text-brand-dark cursor-pointer hover:opacity-90';
            } else if (clickable) {
                btn.className += ' bg-white border border-line text-body cursor-pointer hover:border-brand';
            } else {
                btn.className += ' bg-white border border-line text-muted opacity-60 cursor-not-allowed';
            }

            if (badge) {
                badge.className = 'flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-full text-xs sm:text-sm font-bold shrink-0 '
                    + (isCurrent ? 'bg-white/20' : isDone ? 'bg-brand/20' : 'bg-surface');
                badge.innerHTML = isDone
                    ? '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>'
                    : String(btnStep);
            }
        });

        if (step === total) {
            refreshReview(form);
        }

        form.dispatchEvent(new CustomEvent('wizard:step', {
            bubbles: true,
            detail: { step: step, total: total, isLast: step === total },
        }));

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function isInsideHidden(el, panelRoot) {
        if (!el) {
            return false;
        }
        var node = el;
        while (node && node !== document.body) {
            if (panelRoot && node === panelRoot) {
                return false;
            }
            var isHidden = node.hidden
                || (node.classList && node.classList.contains('hidden'))
                || (node.style && node.style.display === 'none');
            if (isHidden) {
                return true;
            }
            node = node.parentElement;
        }
        return false;
    }

    function clearClientErrors(panel) {
        panel.querySelectorAll('[data-client-error]').forEach(function (el) {
            el.remove();
        });
        panel.querySelectorAll('[data-client-invalid]').forEach(function (el) {
            el.removeAttribute('data-client-invalid');
            el.classList.remove('border-danger', 'date-field--error');
            if (el.classList.contains('dt-split-box')) {
                el.classList.remove('ring-1', 'ring-danger');
            }
        });
    }

    function fieldMessage(field, fallback) {
        if (field.validationMessage) return field.validationMessage;
        return fallback || (document.documentElement.getAttribute('data-field-required') || 'This field is required.');
    }

    function showClientError(target, message) {
        if (!target) return;
        target.setAttribute('data-client-invalid', '1');
        if (target.matches('input, select, textarea')) {
            target.classList.add('border-danger');
            if (target.classList.contains('date-field')) {
                target.classList.add('date-field--error');
            }
        }
        if (target.classList.contains('dt-split-box')) {
            target.classList.add('ring-1', 'ring-danger');
        }

        var host = target.closest('.flex.min-w-0.flex-col, [data-datetime-split], .dt-split') || target.parentElement;
        if (!host) return;
        var existing = host.querySelector('[data-client-error]');
        if (existing) {
            existing.textContent = message;
            return;
        }
        var p = document.createElement('p');
        p.className = 'text-xs text-danger';
        p.setAttribute('role', 'alert');
        p.setAttribute('data-client-error', '1');
        p.textContent = message;
        host.appendChild(p);
    }

    function syncDatetimeFields(scope) {
        if (!window.DateTimeSplit || typeof window.DateTimeSplit.sync !== 'function') {
            return;
        }
        (scope || document).querySelectorAll('[data-datetime-split]').forEach(function (el) {
            if (isInsideHidden(el, scope && scope.hasAttribute && scope.hasAttribute('data-wizard-panel') ? scope : null)) {
                return;
            }
            window.DateTimeSplit.sync(el);
        });
    }

    function parseShowWhen(attr) {
        if (!attr) {
            return null;
        }
        var idx = attr.indexOf(':');
        if (idx === -1) {
            return null;
        }
        return {
            name: attr.slice(0, idx),
            value: attr.slice(idx + 1),
        };
    }

    function fieldGroupValue(form, name) {
        var checked = form.querySelector('[name="' + name + '"]:checked');
        if (checked) {
            return checked.value;
        }
        var field = form.querySelector('[name="' + name + '"]');
        return field ? (field.value || '') : '';
    }

    function markWizardRequiredFields(form) {
        form.querySelectorAll('[data-wizard-show-when]').forEach(function (container) {
            container.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (field.required && field.type !== 'hidden') {
                    field.setAttribute('data-wizard-was-required', '1');
                }
            });
        });
    }

    function syncWizardVisibility(form) {
        form.querySelectorAll('[data-wizard-show-when]').forEach(function (container) {
            var spec = parseShowWhen(container.getAttribute('data-wizard-show-when'));
            if (!spec) {
                return;
            }
            var visible = fieldGroupValue(form, spec.name) === spec.value;
            container.hidden = !visible;

            container.querySelectorAll('input, select, textarea').forEach(function (field) {
                if (field.type === 'hidden') {
                    return;
                }
                field.disabled = !visible;
                if (field.getAttribute('data-wizard-was-required') === '1') {
                    field.required = visible;
                }
                if (!visible && field.tagName === 'SELECT') {
                    field.selectedIndex = 0;
                }
            });
        });
    }

    function initWizardVisibility(form) {
        if (form.dataset.wizardVisibilityBound === '1') {
            return;
        }
        form.dataset.wizardVisibilityBound = '1';
        markWizardRequiredFields(form);
        syncWizardVisibility(form);
        form.addEventListener('change', function (event) {
            if (event.target && event.target.name) {
                syncWizardVisibility(form);
            }
        });
    }

    function validatePasswordFields(panel, messages) {
        var ok = true;
        var passwordWeakMsg = messages.passwordWeak || 'Password does not meet the requirements.';

        panel.querySelectorAll('[data-password-strength]').forEach(function (input) {
            if (input.disabled || isInsideHidden(input, panel)) {
                return;
            }
            var value = input.value || '';
            if (input.required && !value.trim()) {
                showClientError(input, fieldMessage(input, messages.fieldRequired));
                if (ok) {
                    input.focus();
                }
                ok = false;
                return;
            }
            if (!value) {
                return;
            }
            var rules = passwordRules(value);
            if (!(rules.min && rules.upper && rules.lower && rules.number && rules.symbol)) {
                showClientError(input, passwordWeakMsg);
                if (ok) {
                    input.focus();
                }
                ok = false;
            }
        });

        panel.querySelectorAll('[data-password-confirm-for]').forEach(function (input) {
            if (input.disabled || isInsideHidden(input, panel)) {
                return;
            }
            if (!input.required) {
                return;
            }
            var sourceId = input.getAttribute('data-password-confirm-for');
            var source = sourceId ? document.getElementById(sourceId) : null;
            var value = (input.value || '').trim();
            if (!value) {
                showClientError(input, fieldMessage(input, messages.fieldRequired));
                if (ok) {
                    input.focus();
                }
                ok = false;
                return;
            }
            if (source && value !== source.value) {
                var mismatch = input.closest('.flex')?.querySelector('[data-password-match-message]');
                showClientError(input, mismatch?.getAttribute('data-match-bad') || 'Passwords do not match.');
                if (ok) {
                    input.focus();
                }
                ok = false;
            }
        });

        return ok;
    }

    function validatePhoneFields(panel, messages) {
        var ok = true;
        panel.querySelectorAll('[data-phone-input]').forEach(function (wrap) {
            if (isInsideHidden(wrap, panel)) {
                return;
            }
            var full = wrap.querySelector('[data-phone-full]');
            if (!full || !full.required) {
                return;
            }
            var national = wrap.querySelector('[data-phone-national]');
            var digits = (national?.value || '').replace(/\D+/g, '');
            if (digits.length < 6) {
                showClientError(national || wrap.querySelector('[data-phone-shell]') || wrap, fieldMessage(full, messages.fieldRequired));
                if (ok && national) {
                    national.focus();
                }
                ok = false;
            }
        });
        return ok;
    }

    function validateDatetimeSplit(root, messages) {
        var date = root.querySelector('[data-part="date"]');
        var time = root.querySelector('[data-part="time"]');
        var hidden = root.querySelector('[data-datetime-combined]');
        var required = (date && date.required) || (time && time.required) || (hidden && hidden.hasAttribute('required'));
        if (!required && !(date && date.value) && !(time && time.value)) {
            return true;
        }

        if (!date || !date.value || !time || !time.value) {
            showClientError(date && !date.value ? date.closest('.dt-split-box') || date : time.closest('.dt-split-box') || time, messages.datesIncomplete);
            (date && !date.value ? date : time)?.focus();
            return false;
        }

        return true;
    }

    function validatePanel(panel, messages) {
        if (!panel) return true;
        var form = panel.closest('[data-form-wizard]');
        if (form) {
            syncWizardVisibility(form);
        }
        clearClientErrors(panel);
        syncDatetimeFields(panel);

        var splits = panel.querySelectorAll('[data-datetime-split]');
        for (var s = 0; s < splits.length; s++) {
            if (isInsideHidden(splits[s], panel)) continue;
            if (!validateDatetimeSplit(splits[s], messages)) return false;
        }

        var fields = panel.querySelectorAll('input, select, textarea');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            if (field.disabled || field.type === 'hidden' || field.type === 'submit' || field.type === 'button') {
                continue;
            }
            if (field.closest('[data-datetime-split]')) {
                continue;
            }
            if (isInsideHidden(field, panel)) {
                continue;
            }
            if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                showClientError(field, fieldMessage(field, messages.fieldRequired));
                field.focus();
                return false;
            }
        }

        if (!validatePasswordFields(panel, messages)) {
            return false;
        }

        if (!validatePhoneFields(panel, messages)) {
            return false;
        }

        return true;
    }

    function tryGoToStep(form, targetStep) {
        targetStep = parseInt(targetStep, 10);
        var current = parseInt(form.dataset.wizardCurrent || '1', 10);
        if (targetStep <= current) {
            setStep(form, targetStep);
            return Promise.resolve(true);
        }

        var step = current;

        function validateCurrentAndAdvance() {
            var panel = form.querySelector('[data-wizard-panel="' + step + '"]');
            if (!validatePanel(panel, wizardMessages(form))) {
                setStep(form, step);
                return Promise.resolve(false);
            }

            var hookResult = typeof form._wizardBeforeNext === 'function'
                ? form._wizardBeforeNext(step)
                : true;

            return Promise.resolve(hookResult).then(function (hookOk) {
                if (hookOk === false) {
                    return false;
                }
                step += 1;
                if (step >= targetStep) {
                    setStep(form, targetStep);
                    return true;
                }
                return validateCurrentAndAdvance();
            });
        }

        return validateCurrentAndAdvance();
    }

    function validateVisibleStep(form) {
        var panel = form.querySelector('[data-wizard-panel]:not([hidden])');
        return validatePanel(panel, wizardMessages(form));
    }

    function validateAllSteps(form) {
        var messages = wizardMessages(form);
        var panels = form.querySelectorAll('[data-wizard-panel]');
        for (var i = 0; i < panels.length; i++) {
            var panel = panels[i];
            var step = parseInt(panel.getAttribute('data-wizard-panel'), 10);
            if (!validatePanel(panel, messages)) {
                setStep(form, step);
                return false;
            }
        }
        return true;
    }

    function wizardMessages(form) {
        return {
            fieldRequired: form.getAttribute('data-msg-required') || 'This field is required.',
            datesIncomplete: form.getAttribute('data-msg-dates') || 'Choose both a date and a time.',
            passwordWeak: form.getAttribute('data-msg-password-weak') || 'Password does not meet the requirements.',
        };
    }

    function firstErrorStep(form) {
        var panels = form.querySelectorAll('[data-wizard-panel]');
        for (var i = 0; i < panels.length; i++) {
            if (panels[i].querySelector('.text-danger, [aria-invalid="true"], .border-danger, .date-field--error')) {
                return parseInt(panels[i].getAttribute('data-wizard-panel'), 10);
            }
        }
        return 1;
    }

    function initWizard(form) {
        if (form.dataset.wizardBound === '1') return;
        form.dataset.wizardBound = '1';
        form.dataset.wizardReached = '1';

        var startStep = parseInt(form.getAttribute('data-wizard-start') || '1', 10);
        if (! startStep || startStep < 1) startStep = 1;

        var errorStep = firstErrorStep(form);
        if (errorStep > 1) {
            startStep = errorStep;
        }

        form.dataset.wizardReached = String(Math.max(startStep, parseInt(form.dataset.wizardReached || '1', 10)));
        initWizardVisibility(form);
        setStep(form, startStep);

        form.addEventListener('click', function (e) {
            var next = e.target.closest('[data-wizard-next]');
            if (next && form.contains(next)) {
                e.preventDefault();
                var current = parseInt(form.dataset.wizardCurrent || '1', 10);
                tryGoToStep(form, current + 1);
                return;
            }

            var back = e.target.closest('[data-wizard-back]');
            if (back && form.contains(back)) {
                e.preventDefault();
                var cur = parseInt(form.dataset.wizardCurrent || '1', 10);
                setStep(form, cur - 1);
                return;
            }

            var stepBtn = e.target.closest('[data-wizard-step-btn]');
            if (stepBtn && form.contains(stepBtn) && !stepBtn.disabled) {
                e.preventDefault();
                tryGoToStep(form, parseInt(stepBtn.getAttribute('data-wizard-step-btn'), 10));
            }
        });

        form.addEventListener('submit', function (e) {
            syncDatetimeFields(form);
            syncWizardVisibility(form);
            if (!validateAllSteps(form)) {
                e.preventDefault();
            }
        });

        form.addEventListener('change', function () {
            if (parseInt(form.dataset.wizardCurrent || '1', 10) === parseInt(form.dataset.wizardStepCount || '1', 10)) {
                refreshReview(form);
            }
        });
    }

    function initPasswordToggles(root) {
        (root || document).querySelectorAll('[data-password-toggle]').forEach(function (btn) {
            if (btn.dataset.bound === '1') return;
            btn.dataset.bound = '1';
            btn.addEventListener('click', function () {
                var wrap = btn.closest('.relative') || btn.parentElement;
                var input = wrap ? wrap.querySelector('[data-password-field]') : null;
                if (!input) return;
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                var showIcon = btn.querySelector('[data-password-show]');
                var hideIcon = btn.querySelector('[data-password-hide]');
                if (showIcon) showIcon.classList.toggle('hidden', show);
                if (hideIcon) hideIcon.classList.toggle('hidden', !show);
            });
        });
    }

    function passwordRules(value) {
        return {
            min: value.length >= 12,
            upper: /[A-Z]/.test(value),
            lower: /[a-z]/.test(value),
            number: /[0-9]/.test(value),
            symbol: /[^A-Za-z0-9]/.test(value),
        };
    }

    function setRuleState(item, met, active) {
        var dot = item.querySelector('[data-rule-dot]');
        item.classList.remove('text-muted', 'text-danger', 'text-success', 'font-medium');
        if (dot) {
            dot.classList.remove('bg-muted/50', 'bg-danger', 'bg-success');
        }
        if (! active) {
            item.classList.add('text-muted');
            if (dot) dot.classList.add('bg-muted/50');
            return;
        }
        if (met) {
            item.classList.add('text-success');
            if (dot) dot.classList.add('bg-success');
        } else {
            item.classList.add('text-danger', 'font-medium');
            if (dot) dot.classList.add('bg-danger');
        }
    }

    function updatePasswordStrength(input) {
        var panel = document.querySelector('[data-password-strength-panel][data-for="' + input.id + '"]');
        if (! panel) return;

        var value = input.value || '';
        var active = value.length > 0;
        var rules = passwordRules(value);
        var allMet = rules.min && rules.upper && rules.lower && rules.number && rules.symbol;
        var message = panel.querySelector('[data-password-strength-message]');
        var ok = panel.querySelector('[data-password-strength-ok]');
        var serverError = null;
        var fieldWrap = input.closest('.flex');
        if (fieldWrap) {
            serverError = fieldWrap.querySelector('[data-password-server-error]');
        }

        panel.hidden = ! active;
        panel.classList.toggle('hidden', ! active);

        panel.querySelectorAll('[data-password-rule]').forEach(function (item) {
            var key = item.getAttribute('data-password-rule');
            setRuleState(item, !! rules[key], active);
        });

        if (message) {
            message.hidden = ! active || allMet;
        }
        if (ok) {
            ok.hidden = ! active || ! allMet;
        }
        if (serverError && active) {
            serverError.hidden = true;
        }

        input.classList.remove('is-password-weak', 'is-password-strong', 'border-danger', 'border-success', 'border-line');
        if (active && ! allMet) {
            input.classList.add('is-password-weak', 'border-danger');
        } else if (active && allMet) {
            input.classList.add('is-password-strong', 'border-success');
        } else {
            input.classList.add('border-line');
        }
        if (active) {
            input.setAttribute('aria-invalid', allMet ? 'false' : 'true');
        } else if (! serverError) {
            input.removeAttribute('aria-invalid');
        }

        document.querySelectorAll('[data-password-confirm-for="' + input.id + '"]').forEach(updatePasswordMatch);
    }

    function updatePasswordMatch(input) {
        var sourceId = input.getAttribute('data-password-confirm-for');
        var source = sourceId ? document.getElementById(sourceId) : null;
        var message = null;
        var fieldWrap = input.closest('.flex');
        if (fieldWrap) {
            message = fieldWrap.querySelector('[data-password-match-message]');
        }
        if (! source || ! message) return;

        var value = input.value || '';
        var active = value.length > 0;
        var match = active && value === source.value;

        message.hidden = ! active;
        message.classList.toggle('hidden', ! active);
        message.classList.remove('text-danger', 'text-success');

        if (! active) {
            input.classList.remove('is-password-weak', 'is-password-strong', 'border-danger', 'border-success');
            input.classList.add('border-line');
            return;
        }

        if (match) {
            message.textContent = message.getAttribute('data-match-ok') || 'Passwords match';
            message.classList.add('text-success');
            input.classList.remove('is-password-weak', 'border-danger', 'border-line');
            input.classList.add('is-password-strong', 'border-success');
        } else {
            message.textContent = message.getAttribute('data-match-bad') || 'Passwords do not match';
            message.classList.add('text-danger');
            input.classList.add('is-password-weak', 'border-danger');
            input.classList.remove('is-password-strong', 'border-success', 'border-line');
        }
    }

    function initPasswordStrength(root) {
        (root || document).querySelectorAll('[data-password-strength]').forEach(function (input) {
            if (input.dataset.strengthBound === '1') return;
            input.dataset.strengthBound = '1';
            ['input', 'change', 'blur'].forEach(function (evt) {
                input.addEventListener(evt, function () { updatePasswordStrength(input); });
            });
            updatePasswordStrength(input);
        });

        (root || document).querySelectorAll('[data-password-confirm-for]').forEach(function (input) {
            if (input.dataset.matchBound === '1') return;
            input.dataset.matchBound = '1';
            ['input', 'change', 'blur'].forEach(function (evt) {
                input.addEventListener(evt, function () { updatePasswordMatch(input); });
            });
            updatePasswordMatch(input);
        });
    }

    function initPhoneInputs(root) {
        (root || document).querySelectorAll('[data-phone-input]').forEach(function (wrap) {
            if (wrap.dataset.bound === '1') return;
            wrap.dataset.bound = '1';

            var toggle = wrap.querySelector('[data-phone-toggle]');
            var dropdown = wrap.querySelector('[data-phone-dropdown]');
            var search = wrap.querySelector('[data-phone-search]');
            var options = wrap.querySelectorAll('[data-phone-option]');
            var empty = wrap.querySelector('[data-phone-empty]');
            var full = wrap.querySelector('[data-phone-full]');
            var national = wrap.querySelector('[data-phone-national]');
            var dialEl = wrap.querySelector('[data-phone-dial]');
            var flagEl = wrap.querySelector('[data-phone-flag]');
            var chevron = wrap.querySelector('[data-phone-chevron]');
            var dial = dialEl ? dialEl.textContent.trim() : '+250';
            var maxDigits = parseInt(wrap.getAttribute('data-phone-max-digits') || '9', 10) || 9;

            function clampNational(value) {
                var cleaned = (value || '').replace(/[^\d\s()-]/g, '');
                var digitCount = 0;
                var out = '';
                for (var i = 0; i < cleaned.length; i += 1) {
                    var ch = cleaned.charAt(i);
                    if (/\d/.test(ch)) {
                        if (digitCount >= maxDigits) continue;
                        digitCount += 1;
                    }
                    out += ch;
                }
                return out;
            }

            function applyMaxDigits() {
                if (!national) return;
                national.value = clampNational(national.value);
                wrap.setAttribute('data-phone-max-digits', String(maxDigits));
            }

            function syncFull() {
                if (!full || !national) return;
                var digits = (national.value || '').replace(/\D+/g, '').slice(0, maxDigits);
                full.value = digits ? (dial + digits) : '';
            }

            function setOpen(open) {
                if (!dropdown || !toggle) return;
                dropdown.hidden = !open;
                dropdown.classList.toggle('hidden', !open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (chevron) chevron.classList.toggle('rotate-180', open);
                if (open && search) {
                    search.value = '';
                    filterOptions('');
                    setTimeout(function () { search.focus(); }, 0);
                }
            }

            function filterOptions(query) {
                var q = (query || '').trim().toLowerCase();
                var visible = 0;
                options.forEach(function (btn) {
                    var name = (btn.getAttribute('data-name') || '').toLowerCase();
                    var code = (btn.getAttribute('data-dial') || '').toLowerCase();
                    var iso = (btn.getAttribute('data-iso') || '').toLowerCase();
                    var match = !q || name.indexOf(q) !== -1 || code.indexOf(q) !== -1 || iso.indexOf(q) !== -1;
                    var item = btn.closest('li');
                    if (item) item.hidden = !match;
                    if (match) visible += 1;
                });
                if (empty) {
                    empty.hidden = visible > 0;
                    empty.classList.toggle('hidden', visible > 0);
                }
            }

            function selectCountry(btn) {
                dial = btn.getAttribute('data-dial') || dial;
                maxDigits = parseInt(btn.getAttribute('data-digits') || String(maxDigits), 10) || maxDigits;
                if (dialEl) dialEl.textContent = dial;
                if (flagEl) flagEl.textContent = btn.getAttribute('data-flag') || '';
                options.forEach(function (opt) {
                    var selected = opt === btn;
                    opt.setAttribute('aria-selected', selected ? 'true' : 'false');
                    opt.classList.toggle('bg-brand-light/50', selected);
                    var check = opt.querySelector('[data-phone-check]');
                    if (check) check.classList.toggle('invisible', !selected);
                });
                applyMaxDigits();
                syncFull();
                setOpen(false);
                if (national) national.focus();
            }

            toggle?.addEventListener('click', function (e) {
                e.preventDefault();
                setOpen(dropdown.hidden);
            });

            search?.addEventListener('input', function () {
                filterOptions(search.value);
            });

            options.forEach(function (btn) {
                btn.addEventListener('click', function () {
                    selectCountry(btn);
                });
            });

            national?.addEventListener('input', function () {
                applyMaxDigits();
                syncFull();
            });

            document.addEventListener('click', function (e) {
                if (!wrap.contains(e.target)) setOpen(false);
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setOpen(false);
            });

            applyMaxDigits();
            syncFull();
        });
    }

    function formatAmountValue(raw) {
        var str = String(raw || '');
        var negative = str.charAt(0) === '-';
        str = str.replace(/[^\d.]/g, '');
        var parts = str.split('.');
        var intPart = (parts[0] || '').replace(/^0+(?=\d)/, '');
        if (intPart === '' && (parts[0] === '0' || parts.length > 1)) intPart = '0';
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        var out = intPart;
        if (parts.length > 1) {
            out += '.' + parts.slice(1).join('').replace(/\D/g, '').slice(0, 2);
        }
        return (negative && out !== '' ? '-' : '') + out;
    }

    function initAmountInputs(root) {
        (root || document).querySelectorAll('[data-amount-input]').forEach(function (input) {
            if (input.dataset.amountBound === '1') return;
            input.dataset.amountBound = '1';

            function applyFormat() {
                var start = input.selectionStart;
                var before = input.value;
                var formatted = formatAmountValue(before);
                if (formatted === before) return;
                input.value = formatted;
                if (document.activeElement === input && typeof start === 'number') {
                    var diff = formatted.length - before.length;
                    var pos = Math.max(0, start + diff);
                    try { input.setSelectionRange(pos, pos); } catch (e) {}
                }
            }

            input.addEventListener('input', applyFormat);
            input.addEventListener('blur', applyFormat);
            applyFormat();

            var form = input.closest('form');
            if (form && form.dataset.amountSubmitBound !== '1') {
                form.dataset.amountSubmitBound = '1';
                form.addEventListener('submit', function () {
                    form.querySelectorAll('[data-amount-input]').forEach(function (el) {
                        el.value = String(el.value || '').replace(/,/g, '');
                    });
                });
            }
        });
    }

    function boot(root) {
        initFileInputs(root || document);
        initPasswordToggles(root || document);
        initPasswordStrength(root || document);
        initPhoneInputs(root || document);
        initAmountInputs(root || document);
        (root || document).querySelectorAll('[data-form-wizard]').forEach(initWizard);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { boot(document); });
    } else {
        boot(document);
    }

    window.FormUi = {
        boot: boot,
        initFileInputs: initFileInputs,
        initPasswordStrength: initPasswordStrength,
        initPhoneInputs: initPhoneInputs,
        initAmountInputs: initAmountInputs,
        formatAmountValue: formatAmountValue,
        syncWizardVisibility: syncWizardVisibility,
        tryGoToStep: tryGoToStep,
    };
})();
