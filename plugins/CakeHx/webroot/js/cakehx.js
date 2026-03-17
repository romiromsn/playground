/**
 * CakeHx — htmx-inspired AJAX engine for CakePHP
 *
 * Supported HTML attributes:
 *   data-hx-get="/url"        — issues GET on trigger
 *   data-hx-post="/url"       — issues POST on trigger
 *   data-hx-put="/url"        — issues PUT on trigger
 *   data-hx-patch="/url"      — issues PATCH on trigger
 *   data-hx-delete="/url"     — issues DELETE on trigger
 *   data-hx-trigger="event"   — event that fires the request (default: click / submit)
 *   data-hx-target="#sel"     — CSS selector for the element to update
 *   data-hx-swap="mode"       — how to swap content (innerHTML|outerHTML|beforebegin|afterbegin|beforeend|afterend|none)
 *   data-hx-select="#sel"     — pick a fragment from the response
 *   data-hx-push-url="true"   — push the request URL into browser history
 *   data-hx-confirm="msg"     — show confirm() before request
 *   data-hx-indicator="#sel"  — element to add .hx-request class during loading
 *   data-hx-vals='{"k":"v"}'  — extra JSON values to include
 *   data-hx-headers='{"k":"v"}' — extra request headers
 *   data-hx-boost="true"      — boost all links/forms inside this element
 */
(function () {
    'use strict';

    const VERBS = ['get', 'post', 'put', 'patch', 'delete'];
    const DEFAULT_SWAP = 'innerHTML';
    const HX_REQUEST_HEADER = 'X-HX-Request';
    const HX_TRIGGER_HEADER = 'X-HX-Trigger';
    const HX_TARGET_HEADER = 'X-HX-Target';
    const HX_CURRENT_URL_HEADER = 'X-HX-Current-URL';
    const CSRF_HEADER = 'X-CSRF-Token';

    /**
     * Read the CSRF token from CakePHP's meta tag or cookie.
     */
    function csrfToken() {
        const meta = document.querySelector('meta[name="csrfToken"]');
        if (meta) return meta.getAttribute('content');

        const match = document.cookie.match(/csrfToken=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    /**
     * Resolve the target element for swapping.
     */
    function resolveTarget(el) {
        const sel = el.getAttribute('data-hx-target');
        if (sel === 'this') return el;
        if (sel) return document.querySelector(sel);
        return el;
    }

    /**
     * Determine which event should trigger this element.
     */
    function resolveTrigger(el) {
        const attr = el.getAttribute('data-hx-trigger');
        if (attr) return attr.split(' ')[0]; // simple: first token is event name
        if (el.tagName === 'FORM') return 'submit';
        if (el.tagName === 'INPUT' || el.tagName === 'SELECT' || el.tagName === 'TEXTAREA') return 'change';
        return 'click';
    }

    /**
     * Swap response HTML into the target element.
     */
    function swapContent(target, html, mode) {
        switch (mode) {
            case 'outerHTML':
                target.outerHTML = html;
                break;
            case 'beforebegin':
                target.insertAdjacentHTML('beforebegin', html);
                break;
            case 'afterbegin':
                target.insertAdjacentHTML('afterbegin', html);
                break;
            case 'beforeend':
                target.insertAdjacentHTML('beforeend', html);
                break;
            case 'afterend':
                target.insertAdjacentHTML('afterend', html);
                break;
            case 'none':
                break;
            case 'innerHTML':
            default:
                target.innerHTML = html;
                break;
        }
    }

    /**
     * Pick a fragment from an HTML string using a CSS selector.
     */
    function selectFragment(html, selector) {
        if (!selector) return html;
        const doc = new DOMParser().parseFromString(html, 'text/html');
        const frag = doc.querySelector(selector);
        return frag ? frag.outerHTML : html;
    }

    /**
     * Collect form data or extra vals.
     */
    function collectBody(el) {
        if (el.tagName === 'FORM') {
            return new FormData(el);
        }

        // Look for a closest form (input inside a form)
        const form = el.closest('form');
        if (form) {
            return new FormData(form);
        }

        return null;
    }

    /**
     * Parse extra JSON values from data-hx-vals.
     */
    function extraVals(el) {
        const raw = el.getAttribute('data-hx-vals');
        if (!raw) return {};
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('[CakeHx] Invalid JSON in data-hx-vals:', raw);
            return {};
        }
    }

    /**
     * Parse extra headers from data-hx-headers.
     */
    function extraHeaders(el) {
        const raw = el.getAttribute('data-hx-headers');
        if (!raw) return {};
        try {
            return JSON.parse(raw);
        } catch (e) {
            console.warn('[CakeHx] Invalid JSON in data-hx-headers:', raw);
            return {};
        }
    }

    /**
     * Set / remove the loading indicator class.
     */
    function setIndicator(el, active) {
        const sel = el.getAttribute('data-hx-indicator');
        const indicator = sel ? document.querySelector(sel) : null;
        if (indicator) {
            indicator.classList.toggle('hx-request', active);
        }
        el.classList.toggle('hx-request', active);
    }

    /**
     * Core: issue the AJAX request for a given element.
     */
    function issueRequest(el, verb, url) {
        // Confirm
        const confirmMsg = el.getAttribute('data-hx-confirm');
        if (confirmMsg && !window.confirm(confirmMsg)) return;

        const target = resolveTarget(el);
        const swapMode = el.getAttribute('data-hx-swap') || DEFAULT_SWAP;
        const selector = el.getAttribute('data-hx-select');
        const pushUrl = el.getAttribute('data-hx-push-url') === 'true';

        // Build headers
        const headers = {
            [HX_REQUEST_HEADER]: 'true',
            [HX_CURRENT_URL_HEADER]: window.location.href,
            ...extraHeaders(el),
        };

        if (target && target.id) {
            headers[HX_TARGET_HEADER] = target.id;
        }

        const triggerName = el.getAttribute('data-hx-trigger') || resolveTrigger(el);
        headers[HX_TRIGGER_HEADER] = triggerName;

        const token = csrfToken();
        if (token && verb !== 'get') {
            headers[CSRF_HEADER] = token;
        }

        // Build body
        let body = null;
        if (verb !== 'get') {
            body = collectBody(el);
            const vals = extraVals(el);
            if (Object.keys(vals).length) {
                if (!(body instanceof FormData)) body = new FormData();
                for (const [k, v] of Object.entries(vals)) {
                    body.append(k, v);
                }
            }
        } else {
            // Append extra vals as query params
            const vals = extraVals(el);
            if (Object.keys(vals).length) {
                const u = new URL(url, window.location.origin);
                for (const [k, v] of Object.entries(vals)) {
                    u.searchParams.set(k, v);
                }
                url = u.toString();
            }
        }

        setIndicator(el, true);

        // Dispatch before-request event
        const beforeEvent = new CustomEvent('cakehx:beforeRequest', {
            bubbles: true,
            cancelable: true,
            detail: { el, verb, url, headers },
        });
        if (!el.dispatchEvent(beforeEvent)) {
            setIndicator(el, false);
            return;
        }

        const fetchOptions = {
            method: verb.toUpperCase(),
            headers,
            credentials: 'same-origin',
        };
        if (body) fetchOptions.body = body;

        fetch(url, fetchOptions)
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('CakeHx request failed: ' + response.status);
                }
                return response.text();
            })
            .then(function (html) {
                setIndicator(el, false);

                const fragment = selectFragment(html, selector);

                if (target) {
                    swapContent(target, fragment, swapMode);
                    processElement(target);
                }

                if (pushUrl) {
                    window.history.pushState({}, '', url);
                }

                // Dispatch after-swap event
                el.dispatchEvent(new CustomEvent('cakehx:afterSwap', {
                    bubbles: true,
                    detail: { el, target, html: fragment },
                }));
            })
            .catch(function (err) {
                setIndicator(el, false);
                console.error('[CakeHx]', err);
                el.dispatchEvent(new CustomEvent('cakehx:error', {
                    bubbles: true,
                    detail: { el, error: err },
                }));
            });
    }

    /**
     * Attach event listeners to a single element.
     */
    function attachElement(el) {
        if (el._cakehxAttached) return;

        let verb = null;
        let url = null;

        for (const v of VERBS) {
            const attr = el.getAttribute('data-hx-' + v);
            if (attr) {
                verb = v;
                url = attr;
                break;
            }
        }

        if (!verb) return;

        const trigger = resolveTrigger(el);
        el.addEventListener(trigger, function (evt) {
            if (trigger === 'submit') evt.preventDefault();
            if (trigger === 'click' && el.tagName === 'A') evt.preventDefault();
            issueRequest(el, verb, url);
        });

        el._cakehxAttached = true;
    }

    /**
     * Handle boosted elements: all <a> and <form> inside data-hx-boost
     */
    function attachBoosted(container) {
        container.querySelectorAll('a[href]').forEach(function (a) {
            if (a._cakehxAttached) return;
            if (a.getAttribute('data-hx-boost') === 'false') return;
            a.setAttribute('data-hx-get', a.getAttribute('href'));
            a.setAttribute('data-hx-push-url', 'true');
            if (!a.getAttribute('data-hx-target')) {
                a.setAttribute('data-hx-target', 'body');
            }
            attachElement(a);
        });

        container.querySelectorAll('form[action]').forEach(function (form) {
            if (form._cakehxAttached) return;
            if (form.getAttribute('data-hx-boost') === 'false') return;
            const method = (form.getAttribute('method') || 'get').toLowerCase();
            form.setAttribute('data-hx-' + method, form.getAttribute('action'));
            if (!form.getAttribute('data-hx-target')) {
                form.setAttribute('data-hx-target', 'body');
            }
            attachElement(form);
        });
    }

    /**
     * Process an element and all descendants.
     */
    function processElement(root) {
        if (!root || !root.querySelectorAll) return;

        // Boost containers
        root.querySelectorAll('[data-hx-boost="true"]').forEach(attachBoosted);
        if (root.getAttribute && root.getAttribute('data-hx-boost') === 'true') {
            attachBoosted(root);
        }

        // Direct HX elements
        VERBS.forEach(function (v) {
            root.querySelectorAll('[data-hx-' + v + ']').forEach(attachElement);
        });
        attachElement(root);
    }

    /**
     * Observe DOM for dynamically added elements.
     */
    function observeDOM() {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (m) {
                m.addedNodes.forEach(function (node) {
                    if (node.nodeType === 1) processElement(node);
                });
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

    /**
     * Handle back/forward navigation for push-url.
     */
    window.addEventListener('popstate', function () {
        fetch(window.location.href, {
            headers: { [HX_REQUEST_HEADER]: 'true' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.text(); })
            .then(function (html) {
                document.body.innerHTML = html;
                processElement(document.body);
            });
    });

    /**
     * Boot.
     */
    function init() {
        processElement(document.body);
        observeDOM();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Public API
    window.CakeHx = {
        process: processElement,
        version: '1.0.0',
    };
})();
