/**
 * Searchable select enhancer (NDFIS SelectField pattern).
 * Native <select> stays in the form; UI upgrades when options exceed the threshold.
 */
(function (window, document) {
    'use strict';

    var DEFAULT_THRESHOLD = 8;
    var PAGE_SIZE = 8;
    var instances = new WeakMap();
    var stylesInjected = false;

    function ensureStyles() {
        if (stylesInjected || document.getElementById('searchable-select-styles')) {
            stylesInjected = true;
            return;
        }
        var style = document.createElement('style');
        style.id = 'searchable-select-styles';
        style.textContent = ''
            + '[data-enhance-select].is-select-loading [data-ss-chevron],'
            + '[data-enhance-select].is-select-loading [data-ss-clear]{visibility:hidden;}'
            + '[data-enhance-select].is-select-loading [data-ss-trigger]{pointer-events:none;cursor:wait;}'
            + '[data-select-spinner][hidden]{display:none!important;}'
            + '[data-ss-option-icon]{width:1.75rem;height:1.75rem;flex:0 0 1.75rem;border-radius:0.375rem;overflow:hidden;display:inline-flex;align-items:center;justify-content:center;background:#F8FAFC;border:1px solid #E5E7EB;color:#6B7280;font-size:0.75rem;font-weight:600;line-height:1;}'
            + '[data-ss-option-icon].is-logo{background:transparent;border-color:transparent;}'
            + '[data-ss-option-icon] img{width:100%;height:100%;object-fit:contain;padding:0;display:block;}'
            + '[data-ss-trigger] [data-ss-icon]:not([hidden]){display:inline-flex;align-items:center;}';
        document.head.appendChild(style);
        stylesInjected = true;
    }

    function text(el, key, fallback) {
        if (!el) return fallback;
        return el.getAttribute('data-i18n-' + key) || fallback;
    }

    function optionList(select) {
        return Array.prototype.slice.call(select.options || []).map(function (opt) {
            return {
                value: opt.value,
                label: (opt.textContent || '').trim(),
                logo: opt.getAttribute('data-logo') || '',
                description: (opt.getAttribute('data-description') || '').trim(),
                disabled: !!opt.disabled,
                isPlaceholder: opt.value === ''
            };
        });
    }

    function showOptionLogos(select, options) {
        if (select.hasAttribute('data-option-logos')) return true;
        return options.some(function (opt) { return !!opt.logo; });
    }

    function optionInitial(label) {
        var match = String(label || '').match(/[A-Za-z0-9]/);
        return match ? match[0].toUpperCase() : '?';
    }

    function createOptionIcon(opt) {
        var wrap = document.createElement('span');
        wrap.setAttribute('data-ss-option-icon', '');
        wrap.setAttribute('aria-hidden', 'true');

        if (opt.logo) {
            wrap.className = 'is-logo';
            var img = document.createElement('img');
            img.src = opt.logo;
            img.alt = '';
            img.loading = 'lazy';
            img.decoding = 'async';
            img.addEventListener('error', function () {
                wrap.textContent = optionInitial(opt.label);
            });
            wrap.appendChild(img);
            return wrap;
        }

        wrap.textContent = optionInitial(opt.label);
        return wrap;
    }

    function selectableOptions(options) {
        return options.filter(function (opt) { return !opt.isPlaceholder && !opt.disabled; });
    }

    function shouldEnhance(select, options) {
        if (select.getAttribute('data-searchable') === 'false') return false;
        if (select.getAttribute('data-searchable') === 'true') return true;
        var threshold = parseInt(select.getAttribute('data-searchable-threshold') || String(DEFAULT_THRESHOLD), 10);
        return selectableOptions(options).length > threshold;
    }

    function destroy(select) {
        var inst = instances.get(select);
        if (!inst) return;
        if (inst.onDocClick) document.removeEventListener('mousedown', inst.onDocClick);
        if (inst.onKey) document.removeEventListener('keydown', inst.onKey);
        if (inst.onScroll) {
            window.removeEventListener('resize', inst.onScroll);
            window.removeEventListener('scroll', inst.onScroll, true);
        }
        if (inst.panel && inst.panel.parentNode) inst.panel.parentNode.removeChild(inst.panel);
        if (inst.wrap && inst.wrap.parentNode) {
            inst.wrap.parentNode.insertBefore(select, inst.wrap);
            inst.wrap.parentNode.removeChild(inst.wrap);
        }
        select.classList.remove('sr-only');
        select.removeAttribute('tabindex');
        select.removeAttribute('aria-hidden');
        select.style.position = '';
        select.style.width = '';
        select.style.height = '';
        select.style.opacity = '';
        select.style.pointerEvents = '';
        instances.delete(select);
    }

    function positionPanel(inst) {
        var rect = inst.trigger.getBoundingClientRect();
        var vw = window.innerWidth;
        var vh = window.innerHeight;
        var pad = 8;
        var gap = 4;
        var width = Math.min(Math.max(rect.width, 280), vw - pad * 2);
        var left = rect.left;
        if (left + width > vw - pad) left = Math.max(pad, vw - pad - width);

        var spaceBelow = Math.max(0, vh - rect.bottom - gap - pad);
        var spaceAbove = Math.max(0, rect.top - gap - pad);
        var placement = spaceBelow >= 200 || spaceBelow >= spaceAbove ? 'bottom' : 'top';
        var maxHeight = Math.max(160, Math.min(480, placement === 'bottom' ? spaceBelow : spaceAbove));

        inst.panel.style.position = 'fixed';
        inst.panel.style.left = left + 'px';
        inst.panel.style.width = width + 'px';
        inst.panel.style.maxHeight = maxHeight + 'px';
        inst.panel.style.zIndex = '120';
        if (placement === 'bottom') {
            inst.panel.style.top = (rect.bottom + gap) + 'px';
            inst.panel.style.bottom = 'auto';
        } else {
            inst.panel.style.bottom = (vh - rect.top + gap) + 'px';
            inst.panel.style.top = 'auto';
        }
    }

    function setOpen(inst, open) {
        inst.open = open;
        inst.panel.hidden = !open;
        inst.panel.style.display = open ? 'flex' : 'none';
        inst.trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        inst.trigger.classList.toggle('ring-2', open);
        inst.trigger.classList.toggle('ring-brand/20', open && !inst.hasError);
        inst.trigger.classList.toggle('border-brand', open && !inst.hasError);
        inst.trigger.classList.toggle('ring-danger/20', open && inst.hasError);
        if (open) {
            inst.searchTerm = '';
            inst.page = 1;
            if (inst.searchInput) inst.searchInput.value = '';
            positionPanel(inst);
            renderList(inst);
            setTimeout(function () { inst.searchInput && inst.searchInput.focus(); }, 20);
        } else {
            renderTrigger(inst);
        }
    }

    function renderTrigger(inst) {
        var current = inst.options.find(function (opt) { return opt.value === inst.select.value; });
        var labelEl = inst.trigger.querySelector('[data-ss-label]');
        var iconSlot = inst.trigger.querySelector('[data-ss-icon]');
        var clearBtn = inst.trigger.querySelector('[data-ss-clear]');
        var chevron = inst.trigger.querySelector('[data-ss-chevron]');

        if (iconSlot) {
            iconSlot.innerHTML = '';
            if (inst.showLogos && current && !current.isPlaceholder && current.value !== '') {
                iconSlot.hidden = false;
                iconSlot.appendChild(createOptionIcon(current));
            } else {
                iconSlot.hidden = true;
            }
        }

        if (!current || current.isPlaceholder || current.value === '') {
            labelEl.textContent = inst.placeholder;
            labelEl.classList.add('text-muted');
            labelEl.classList.remove('text-ink', 'font-medium');
            if (clearBtn) clearBtn.hidden = true;
        } else {
            labelEl.textContent = current.label;
            labelEl.classList.remove('text-muted');
            labelEl.classList.add('text-ink', 'font-medium');
            if (clearBtn) clearBtn.hidden = !!inst.select.disabled || !!inst.select.required;
            // Always allow clear unless required and empty is not allowed — match NDFIS: show clear when selected
            if (clearBtn) clearBtn.hidden = !!inst.select.disabled;
        }

        if (chevron) {
            chevron.innerHTML = inst.open
                ? '<path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7"/>'
                : '<path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>';
        }
    }

    function filtered(inst) {
        var term = (inst.searchTerm || '').trim().toLowerCase();
        var list = selectableOptions(inst.options);
        if (!term) return list;
        return list.filter(function (opt) {
            var haystack = (opt.label + ' ' + (opt.description || '') + ' ' + String(opt.value)).toLowerCase();
            return haystack.indexOf(term) !== -1;
        });
    }

    function renderList(inst) {
        var list = filtered(inst);
        var totalPages = Math.max(1, Math.ceil(list.length / PAGE_SIZE));
        if (inst.page > totalPages) inst.page = totalPages;
        var start = (inst.page - 1) * PAGE_SIZE;
        var pageItems = list.slice(start, start + PAGE_SIZE);

        var body = inst.panel.querySelector('[data-ss-body]');
        var meta = inst.panel.querySelector('[data-ss-meta]');
        var pager = inst.panel.querySelector('[data-ss-pager]');
        var prev = inst.panel.querySelector('[data-ss-prev]');
        var next = inst.panel.querySelector('[data-ss-next]');
        var pageLabel = inst.panel.querySelector('[data-ss-page-label]');

        body.innerHTML = '';
        if (pageItems.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'p-4 text-center text-sm text-muted';
            empty.textContent = inst.searchTerm
                ? text(inst.select, 'no-match', 'No matching options')
                : text(inst.select, 'no-options', 'No options available');
            body.appendChild(empty);
            if (meta) meta.hidden = true;
        } else {
            pageItems.forEach(function (opt) {
                var row = document.createElement('button');
                row.type = 'button';
                row.className = 'w-full text-left px-4 py-3 text-sm border-b border-line last:border-b-0 hover:bg-surface transition-colors flex items-center gap-3 '
                    + (opt.value === inst.select.value ? 'bg-brand-light/60 text-brand-dark font-semibold' : 'text-ink');
                if (inst.showLogos) {
                    row.appendChild(createOptionIcon(opt));
                }
                var labelWrap = document.createElement('span');
                labelWrap.className = 'min-w-0 flex-1';
                var label = document.createElement('span');
                label.className = 'block truncate';
                label.textContent = opt.label;
                labelWrap.appendChild(label);
                if (opt.description) {
                    var desc = document.createElement('span');
                    desc.className = 'block truncate text-xs font-normal text-muted mt-0.5';
                    desc.textContent = opt.description;
                    labelWrap.appendChild(desc);
                }
                row.appendChild(labelWrap);
                row.addEventListener('click', function () {
                    inst.select.value = opt.value;
                    inst.select.dispatchEvent(new Event('change', { bubbles: true }));
                    renderTrigger(inst);
                    setOpen(inst, false);
                });
                body.appendChild(row);
            });
            if (meta) {
                meta.hidden = false;
                meta.textContent = text(inst.select, 'showing', 'Showing :from–:to of :total')
                    .replace(':from', String(start + 1))
                    .replace(':to', String(Math.min(start + PAGE_SIZE, list.length)))
                    .replace(':total', String(list.length));
            }
        }

        if (pager) {
            pager.hidden = totalPages <= 1;
            if (pageLabel) {
                pageLabel.textContent = text(inst.select, 'page', 'Page :current of :total')
                    .replace(':current', String(inst.page))
                    .replace(':total', String(totalPages));
            }
            if (prev) prev.disabled = inst.page <= 1;
            if (next) next.disabled = inst.page >= totalPages;
        }
    }

    function build(select) {
        destroy(select);

        var options = optionList(select);
        if (!shouldEnhance(select, options)) {
            return null;
        }

        var hasError = select.getAttribute('aria-invalid') === 'true';
        var isCompact = select.getAttribute('data-compact') === 'true';
        var placeholderOpt = options.find(function (opt) { return opt.isPlaceholder; });
        var placeholder = placeholderOpt ? placeholderOpt.label : text(select, 'placeholder', '—');

        var wrap = document.createElement('div');
        wrap.className = 'relative w-full min-w-0';
        wrap.setAttribute('data-searchable-select-root', '');

        select.parentNode.insertBefore(wrap, select);
        wrap.appendChild(select);

        // Keep select in tab order for form libs, but hide visually.
        select.style.position = 'absolute';
        select.style.width = '1px';
        select.style.height = '1px';
        select.style.opacity = '0';
        select.style.pointerEvents = 'none';
        select.setAttribute('tabindex', '-1');
        select.setAttribute('aria-hidden', 'true');

        var trigger = document.createElement('div');
        trigger.setAttribute('role', 'button');
        trigger.setAttribute('tabindex', select.disabled ? '-1' : '0');
        trigger.setAttribute('data-ss-trigger', '');
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.className = (isCompact ? 'h-10 min-h-10 ' : 'h-12 min-h-12 ')
            + 'min-w-0 px-3 sm:px-4 border rounded-xl text-sm transition-all duration-200 w-full max-w-full bg-white shadow-sm hover:shadow-md flex items-center gap-2 '
            + (hasError ? 'border-danger' : 'border-line')
            + (select.disabled ? ' opacity-70 cursor-not-allowed' : ' cursor-pointer');
        trigger.innerHTML = ''
            + '<span data-ss-icon hidden class="shrink-0"></span>'
            + '<span data-ss-label class="min-w-0 flex-1 truncate text-left"></span>'
            + '<span class="ml-auto flex shrink-0 items-center gap-2">'
            +   '<button type="button" data-ss-clear hidden class="text-muted hover:text-ink transition-colors p-0.5" title="' + text(select, 'clear', 'Clear') + '" aria-label="' + text(select, 'clear', 'Clear') + '">'
            +     '<svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>'
            +   '</button>'
            +   '<svg data-ss-chevron xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>'
            + '</span>';

        wrap.appendChild(trigger);

        var panel = document.createElement('div');
        panel.hidden = true;
        panel.setAttribute('data-ss-panel', '');
        panel.className = 'bg-white border border-line rounded-xl shadow-lg overflow-hidden flex flex-col min-h-0';
        // Keep closed panels out of the accessibility tree / layout.
        panel.style.display = 'none';
        panel.innerHTML = ''
            + '<div class="p-3 border-b border-line shrink-0">'
            +   '<div class="relative">'
            +     '<svg xmlns="http://www.w3.org/2000/svg" class="absolute left-3 top-1/2 -translate-y-1/2 h-3.5 w-3.5 text-muted pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>'
            +     '<input data-ss-search type="text" class="w-full pl-10 pr-4 py-2 border border-line rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand/20 focus:border-brand bg-white text-ink" placeholder="' + text(select, 'search', 'Search...') + '" />'
            +   '</div>'
            + '</div>'
            + '<div data-ss-body class="flex-1 min-h-0 overflow-y-auto overscroll-contain"></div>'
            + '<div data-ss-meta class="px-4 py-2 text-xs text-muted bg-surface text-center border-t border-line"></div>'
            + '<div data-ss-pager hidden class="p-3 border-t border-line flex min-w-0 shrink-0 flex-wrap items-center justify-between gap-2">'
            +   '<button type="button" data-ss-prev class="px-3 py-1 text-sm text-muted hover:text-ink disabled:opacity-50 disabled:cursor-not-allowed">' + text(select, 'previous', 'Previous') + '</button>'
            +   '<span data-ss-page-label class="text-sm text-muted"></span>'
            +   '<button type="button" data-ss-next class="px-3 py-1 text-sm text-muted hover:text-ink disabled:opacity-50 disabled:cursor-not-allowed">' + text(select, 'next', 'Next') + '</button>'
            + '</div>';

        document.body.appendChild(panel);

        var inst = {
            select: select,
            wrap: wrap,
            trigger: trigger,
            panel: panel,
            searchInput: panel.querySelector('[data-ss-search]'),
            options: options,
            placeholder: placeholder,
            showLogos: showOptionLogos(select, options),
            hasError: hasError,
            open: false,
            searchTerm: '',
            page: 1
        };

        trigger.addEventListener('click', function () {
            if (select.disabled || inst.loading) return;
            setOpen(inst, !inst.open);
        });
        trigger.addEventListener('keydown', function (e) {
            if (select.disabled || inst.loading) return;
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setOpen(inst, !inst.open);
            }
        });

        var clearBtn = trigger.querySelector('[data-ss-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                if (select.disabled) return;
                select.value = '';
                select.dispatchEvent(new Event('change', { bubbles: true }));
                renderTrigger(inst);
                if (inst.open) renderList(inst);
            });
        }

        inst.searchInput.addEventListener('input', function () {
            inst.searchTerm = inst.searchInput.value;
            inst.page = 1;
            renderList(inst);
        });

        panel.querySelector('[data-ss-prev]').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            inst.page = Math.max(1, inst.page - 1);
            renderList(inst);
        });
        panel.querySelector('[data-ss-next]').addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            inst.page += 1;
            renderList(inst);
        });

        inst.onDocClick = function (e) {
            if (!inst.open) return;
            if (wrap.contains(e.target) || panel.contains(e.target)) return;
            setOpen(inst, false);
        };
        document.addEventListener('mousedown', inst.onDocClick);

        inst.onKey = function (e) {
            if (e.key === 'Escape' && inst.open) setOpen(inst, false);
        };
        document.addEventListener('keydown', inst.onKey);

        inst.onScroll = function () {
            if (inst.open) positionPanel(inst);
        };
        window.addEventListener('resize', inst.onScroll);
        window.addEventListener('scroll', inst.onScroll, true);

        select.addEventListener('change', function () {
            renderTrigger(inst);
        });

        instances.set(select, inst);
        renderTrigger(inst);
        if (select.getAttribute('data-loading') === 'true') {
            applyLoading(inst, true);
        }
        return inst;
    }

    function applyLoading(inst, loading) {
        inst.loading = !!loading;
        var labelEl = inst.trigger.querySelector('[data-ss-label]');
        var iconSlot = inst.trigger.querySelector('[data-ss-icon]');
        var chevron = inst.trigger.querySelector('[data-ss-chevron]');
        var clearBtn = inst.trigger.querySelector('[data-ss-clear]');

        inst.trigger.setAttribute('aria-busy', loading ? 'true' : 'false');
        inst.trigger.classList.toggle('pointer-events-none', !!loading);
        inst.trigger.classList.toggle('cursor-wait', !!loading);

        if (chevron) chevron.classList.toggle('invisible', !!loading);
        if (clearBtn && loading) clearBtn.hidden = true;
        if (iconSlot && loading) {
            iconSlot.hidden = true;
            iconSlot.innerHTML = '';
        }

        if (loading) {
            if (inst.open) setOpen(inst, false);
            if (labelEl) {
                labelEl.textContent = text(inst.select, 'loading', 'Loading…');
                labelEl.classList.add('text-muted');
                labelEl.classList.remove('text-ink', 'font-medium');
            }
            inst.trigger.tabIndex = -1;
            return;
        }

        inst.trigger.tabIndex = inst.select.disabled ? '-1' : '0';
        inst.trigger.classList.toggle('opacity-70', !!inst.select.disabled);
        inst.trigger.classList.toggle('cursor-not-allowed', !!inst.select.disabled);
        inst.trigger.classList.toggle('cursor-pointer', !inst.select.disabled);
        renderTrigger(inst);
    }

    function setLoading(select, loading) {
        if (!select) return;
        ensureStyles();

        var wrap = select.closest('[data-enhance-select]');
        var spinner = wrap ? wrap.querySelector('[data-select-spinner]') : null;

        if (loading) {
            select.setAttribute('data-loading', 'true');
            select.setAttribute('aria-busy', 'true');
            select.disabled = true;
        } else {
            select.removeAttribute('data-loading');
            select.removeAttribute('aria-busy');
            select.disabled = false;
        }

        if (wrap) {
            wrap.classList.toggle('is-select-loading', !!loading);
            if (loading) wrap.setAttribute('aria-busy', 'true');
            else wrap.removeAttribute('aria-busy');
        }
        if (spinner) {
            spinner.hidden = !loading;
            spinner.classList.toggle('hidden', !loading);
            spinner.classList.toggle('flex', !!loading);
        }

        var inst = instances.get(select);
        if (inst) {
            applyLoading(inst, loading);
        } else if (!loading) {
            // Ensure a rebuilt/native select never keeps a stuck spinner.
            if (spinner) {
                spinner.hidden = true;
                spinner.classList.add('hidden');
                spinner.classList.remove('flex');
            }
            if (wrap) {
                wrap.classList.remove('is-select-loading');
                wrap.removeAttribute('aria-busy');
            }
        }
    }

    function refresh(selectOrRoot) {
        var select = selectOrRoot;
        if (select && select.matches && !select.matches('select')) {
            select = select.querySelector('select');
            if (!select) {
                var root = selectOrRoot.closest ? selectOrRoot.closest('[data-searchable-select-root]') : null;
                select = root ? root.querySelector('select') : null;
            }
        }
        if (!select || select.tagName !== 'SELECT') return;
        destroy(select);
        build(select);
    }

    function enhanceAll(root) {
        ensureStyles();
        var scope = root || document;
        scope.querySelectorAll('select[data-searchable], select[data-searchable-threshold], [data-enhance-select] select').forEach(function (select) {
            build(select);
        });
    }

    window.SearchableSelect = {
        enhanceAll: enhanceAll,
        refresh: refresh,
        destroy: destroy,
        setLoading: setLoading
    };

    document.addEventListener('DOMContentLoaded', function () {
        enhanceAll(document);
    });
})(window, document);
