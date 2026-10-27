(function () {
    'use strict';

    var DEFAULT_TIME = '10:00';

    function formatDateLabel(value) {
        if (!value) return '';
        var d = new Date(value + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return value;
        return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function openPicker(input) {
        if (!input) return;
        input.focus({ preventScroll: true });
        try {
            if (typeof input.showPicker === 'function') {
                input.showPicker();
            }
        } catch (_) {}
    }

    function paint(input) {
        var text = input.closest('.dt-split-box')?.querySelector('.dt-split-text');
        if (!text) return;
        var placeholder = text.getAttribute('data-placeholder') || '';
        if (!input.value) {
            text.textContent = placeholder;
            text.classList.remove('is-filled');
            return;
        }
        text.textContent = input.getAttribute('data-part') === 'date'
            ? formatDateLabel(input.value)
            : input.value;
        text.classList.add('is-filled');
    }

    function parts(root) {
        return {
            date: root.querySelector('[data-part="date"]'),
            time: root.querySelector('[data-part="time"]'),
            hidden: root.querySelector('[data-datetime-combined]'),
        };
    }

    function combined(root) {
        var p = parts(root);
        if (!p.date?.value || !p.time?.value) return '';
        return p.date.value + 'T' + p.time.value;
    }

    function ensureDefaultTime(timeInput, minTime) {
        if (!timeInput || timeInput.value) return;
        var next = DEFAULT_TIME;
        if (minTime && next < minTime) next = minTime;
        timeInput.value = next;
    }

    function bumpHour(timeValue) {
        var bits = (timeValue || '00:00').split(':').map(Number);
        var d = new Date(2000, 0, 1, bits[0] || 0, (bits[1] || 0) + 60);
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function applyMin(root, minValue) {
        var p = parts(root);
        if (!p.date) return;
        if (!minValue) {
            p.date.removeAttribute('min');
            p.time?.removeAttribute('min');
            root.removeAttribute('data-datetime-min');
            return;
        }
        root.setAttribute('data-datetime-min', minValue);
        var minDate = minValue.slice(0, 10);
        var minTime = minValue.indexOf('T') !== -1 ? minValue.slice(11, 16) : '';
        p.date.min = minDate;
        if (p.date.value && p.date.value < minDate) {
            p.date.value = '';
            if (p.time) p.time.value = '';
        }
        if (p.date.value === minDate && minTime) {
            p.time.min = minTime;
            if (p.time.value && p.time.value < minTime) p.time.value = minTime;
        } else if (p.time) {
            p.time.removeAttribute('min');
        }
    }

    function syncRoot(root, options) {
        options = options || {};
        var p = parts(root);
        if (!p.date || !p.time || !p.hidden) return '';

        var minAttr = root.getAttribute('data-datetime-min') || '';
        if (minAttr) applyMin(root, minAttr);

        if (p.date.value) {
            var minTime = '';
            if (minAttr && p.date.value === minAttr.slice(0, 10) && minAttr.indexOf('T') !== -1) {
                minTime = minAttr.slice(11, 16);
            }
            ensureDefaultTime(p.time, minTime);
        }

        var value = combined(root);
        var previous = p.hidden.value;
        p.hidden.value = value;
        paint(p.date);
        paint(p.time);

        if (!options.silent && previous !== value) {
            p.hidden.dispatchEvent(new Event('input', { bubbles: true }));
            p.hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }

        return value;
    }

    function pairRoots(root) {
        var group = root.getAttribute('data-datetime-pair-group');
        var role = root.getAttribute('data-datetime-pair');
        if (!group || !role) return null;
        var form = root.closest('form') || document;
        var start = form.querySelector('[data-datetime-split][data-datetime-pair-group="' + group + '"][data-datetime-pair="start"]');
        var end = form.querySelector('[data-datetime-split][data-datetime-pair-group="' + group + '"][data-datetime-pair="end"]');
        if (!start || !end) return null;
        return { start: start, end: end };
    }

    var syncingPair = false;

    function syncPair(root) {
        // Guard against listeners that call DateTimeSplit.sync again from
        // the input/change events we dispatch on the combined hidden fields.
        if (syncingPair) return;
        syncingPair = true;

        try {
            var pair = pairRoots(root);
            if (!pair) {
                syncRoot(root);
                return;
            }

            var startHidden = parts(pair.start).hidden;
            var endHidden = parts(pair.end).hidden;
            var prevStart = startHidden ? startHidden.value : '';
            var prevEnd = endHidden ? endHidden.value : '';

            var startValue = syncRoot(pair.start, { silent: true });
            if (startValue) {
                applyMin(pair.end, startValue);
                var endParts = parts(pair.end);
                if (endParts.date.value === parts(pair.start).date.value) {
                    var startTime = parts(pair.start).time.value;
                    if (endParts.time.value && startTime && endParts.time.value <= startTime) {
                        endParts.time.value = bumpHour(startTime);
                    }
                }
            }
            syncRoot(pair.end, { silent: true });

            // Only notify when combined values actually changed.
            if (startHidden && startHidden.value !== prevStart) {
                startHidden.dispatchEvent(new Event('input', { bubbles: true }));
                startHidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (endHidden && endHidden.value !== prevEnd) {
                endHidden.dispatchEvent(new Event('input', { bubbles: true }));
                endHidden.dispatchEvent(new Event('change', { bubbles: true }));
            }
        } finally {
            syncingPair = false;
        }
    }

    function bindRoot(root) {
        if (root.getAttribute('data-datetime-bound') === '1') return;
        root.setAttribute('data-datetime-bound', '1');

        root.querySelectorAll('.dt-split-box').forEach(function (box) {
            box.addEventListener('click', function (event) {
                if (event.target.closest('.dt-split-native')) return;
                var part = box.getAttribute('data-open-part');
                var input = box.querySelector('[data-part="' + part + '"]');
                root.querySelectorAll('.dt-split-box').forEach(function (el) {
                    el.classList.remove('is-active');
                });
                box.classList.add('is-active');
                openPicker(input);
            });
        });

        root.querySelectorAll('.dt-split-native').forEach(function (input) {
            input.addEventListener('change', function () {
                syncPair(root);
            });
            input.addEventListener('input', function () {
                syncPair(root);
            });
            input.addEventListener('focus', function () {
                input.closest('.dt-split-box')?.classList.add('is-active');
            });
            input.addEventListener('blur', function () {
                input.closest('.dt-split-box')?.classList.remove('is-active');
            });
            input.addEventListener('invalid', function (event) {
                event.preventDefault();
                openPicker(input);
            });
        });

        var form = root.closest('form');
        if (form && form.getAttribute('data-datetime-submit-bound') !== '1') {
            form.setAttribute('data-datetime-submit-bound', '1');
            form.addEventListener('submit', function () {
                form.querySelectorAll('[data-datetime-split]').forEach(function (el) {
                    syncPair(el);
                });
            });
        }

        syncRoot(root, { silent: true });
    }

    function init(scope) {
        (scope || document).querySelectorAll('[data-datetime-split]').forEach(bindRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init();
        });
    } else {
        init();
    }

    window.DateTimeSplit = { init: init, sync: syncPair };
})();
