'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const { test } = require('node:test');

const source = fs.readFileSync(path.join(__dirname, '../public/app_local/js/app-enhancements.js'), 'utf8');

class StubEvent {
    constructor(type, options = {}) {
        Object.assign(this, { type, bubbles: false, cancelable: false, defaultPrevented: false }, options);
    }
    preventDefault() { if (this.cancelable) this.defaultPrevented = true; }
    stopImmediatePropagation() { this.stopped = true; }
}

class StubElement {
    constructor(tagName, ownerDocument) {
        this.tagName = tagName.toUpperCase();
        this.ownerDocument = ownerDocument;
        this.children = [];
        this.attributes = {};
        this.listeners = {};
        this.value = '';
        this.defaultValue = '';
        this.type = tagName === 'input' ? 'text' : '';
        this.validationMessage = '';
    }
    appendChild(child) {
        this.children.push(child);
        child.parentNode = this;
        return child;
    }
    setAttribute(key, value) {
        this.attributes[key] = String(value);
        if (['id', 'name', 'type'].includes(key)) this[key] = String(value);
        if (key === 'value') this.value = this.defaultValue = String(value);
        if (key === 'required' || key === 'disabled') this[key] = true;
    }
    getAttribute(key) { return this.attributes[key] ?? null; }
    get form() {
        if (this.getAttribute('form')) return this.ownerDocument.getElementById(this.getAttribute('form'));
        let parent = this.parentNode;
        while (parent && parent.tagName !== 'FORM') parent = parent.parentNode;
        return parent || null;
    }
    matches(selector) {
        if (selector === 'input[data-range-start][data-range-end]') {
            return this.tagName === 'INPUT' && this.getAttribute('data-range-start') !== null && this.getAttribute('data-range-end') !== null;
        }
        if (selector.startsWith('.')) return (this.getAttribute('class') || '').split(' ').includes(selector.slice(1));
        return this.tagName === selector.toUpperCase();
    }
    querySelectorAll(selector) {
        return this.children.flatMap(child => [...(child.matches(selector) ? [child] : []), ...child.querySelectorAll(selector)]);
    }
    addEventListener(type, callback, capture = false) {
        (this.listeners[type] ||= []).push({ callback, capture: capture === true || capture.capture === true });
    }
    dispatchEvent(event) {
        event.target = this;
        const ancestors = [];
        for (let parent = this.parentNode; parent; parent = parent.parentNode) ancestors.push(parent);
        const invoke = (element, capture) => {
            for (const listener of element.listeners[event.type] || []) {
                if (listener.capture === capture && !event.stopped) listener.callback.call(element, event);
            }
        };
        [...ancestors].reverse().forEach(element => invoke(element, true));
        invoke(this, true);
        invoke(this, false);
        if (event.bubbles) ancestors.forEach(element => invoke(element, false));
        return !event.defaultPrevented;
    }
    setCustomValidity(message) { this.validationMessage = message; }
    reportValidity() { return !this.validationMessage && !(this.required && !this.value); }
    reset() {
        const event = new StubEvent('reset', { bubbles: true, cancelable: true });
        if (this.dispatchEvent(event)) this.querySelectorAll('input').forEach(el => { el.value = el.defaultValue; });
    }
}

function setup(t) {
    let window;
    let document;
    let dom;
    const timers = [];
    if (process.env.FLATPICKR_DOM_MODULE) {
        const { JSDOM } = require(process.env.FLATPICKR_DOM_MODULE);
        dom = new JSDOM('<!doctype html><html><body></body></html>', { runScripts: 'outside-only', pretendToBeVisual: true });
        window = dom.window;
        document = window.document;
        window.eval(fs.readFileSync(path.join(__dirname, '../public/assets/vendor/libs/flatpickr/flatpickr.js'), 'utf8'));
        t.after(() => window.close());
    } else {
        document = new StubElement('document');
        document.ownerDocument = document;
        document.body = document.appendChild(new StubElement('body', document));
        document.createElement = tag => new StubElement(tag, document);
        document.getElementById = id => document.querySelectorAll('input').concat(document.querySelectorAll('form')).find(el => el.id === id) || null;
        window = { document, Event: StubEvent, KeyboardEvent: StubEvent, FocusEvent: StubEvent, addEventListener() {} };
        window.flatpickr = (el, config) => {
            const picker = {
                config,
                l10n: config.locale || {},
                selectedDates: [],
                setDate(dates, notify = false) {
                    this.selectedDates = dates.map(date => new Date(date)).sort((a, b) => a - b);
                    const values = this.selectedDates.map(date => this.formatDate(date, config.dateFormat));
                    el.value = [...new Set(values)].join(' to ');
                    if (notify) this.changed();
                },
                formatDate(date, format) {
                    const pad = value => String(value).padStart(2, '0');
                    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
                        (format === 'Y-m-d H:i' ? ` ${pad(date.getHours())}:${pad(date.getMinutes())}` : '');
                },
                changed() {
                    config.onChange?.(this.selectedDates, el.value, this);
                    el.dispatchEvent(new window.Event('change', { bubbles: true }));
                    el.dispatchEvent(new window.Event('input', { bubbles: true }));
                },
                clear() { this.setDate([], true); },
                close() { config.onClose?.(this.selectedDates, el.value, this); },
                destroy() { delete el._flatpickr; },
            };
            el._flatpickr = picker;
            el.addEventListener('blur', () => { el.value = 'silently normalized'; });
            el.addEventListener('keydown', event => { if (event.keyCode === 13) el.value = 'silently normalized'; });
            return picker;
        };
    }
    const calls = [];
    const flatpickr = window.flatpickr;
    window.flatpickr = (el, config) => {
        calls.push({ el, config });
        return flatpickr(el, config);
    };
    const ready = [];
    const ajax = [];
    const chain = {
        on() { return this; },
        ajaxComplete(callback) { ajax.push(callback); return this; },
        ajaxStart() { return this; },
        ajaxStop() { return this; },
    };
    window.jQuery = callback => {
        if (typeof callback === 'function') ready.push(callback);
        return chain;
    };
    window.jQuery.ajaxPrefilter = () => {};
    window.setTimeout = callback => { timers.push(callback); };
    if (dom) window.eval(source);
    else vm.runInNewContext(source, { window, document, jQuery: window.jQuery, Event: window.Event, setTimeout: window.setTimeout });
    const element = (tag, attributes, parent = document.body) => {
        const el = document.createElement(tag);
        Object.entries(attributes || {}).forEach(([key, value]) => el.setAttribute(key, value));
        parent.appendChild(el);
        return el;
    };
    let count = 0;
    function fixture(options = {}) {
        const suffix = ++count;
        const form = element('form', { id: 'form' + suffix, novalidate: '' });
        const input = element('input', {
            id: 'flatpickr-range', type: 'text', 'data-range-start': '#start' + suffix,
            'data-range-end': '#end' + suffix, ...(options.time ? { 'data-range-time': 'true' } : {}),
            ...(options.required ? { required: '' } : {}), value: options.text || '', class: options.className || '',
        }, form);
        const start = element('input', { type: 'hidden', id: 'start' + suffix, name: 'start_date', value: options.start || '' }, form);
        const end = element('input', { type: 'hidden', id: 'end' + suffix, name: 'end_date', value: options.end || '' }, form);
        return { form, input, start, end };
    }
    return {
        window, document, calls, ready, ajax, element, fixture,
        flush() { while (timers.length) timers.shift()(); },
        type(input, value, event = 'input') {
            input.value = value;
            input.dispatchEvent(new window.Event(event, { bubbles: true }));
        },
        params(form) {
            if (dom) return new URLSearchParams(new window.FormData(form)).toString();
            return new URLSearchParams(form.querySelectorAll('input').filter(el => el.name && !el.disabled).map(el => [el.name, el.value])).toString();
        },
    };
}

function pair(fixture, start, end) {
    assert.equal(fixture.start.value, start);
    assert.equal(fixture.end.value, end);
}

test('range contract initializes before unchanged singles and remains idempotent on AJAX', t => {
    const h = setup(t);
    const single = h.element('input', { class: 'flatpickr-date' });
    const datetime = h.element('input', { class: 'flatpickr-datetime' });
    const f = h.fixture({ className: 'flatpickr-date flatpickr-datetime', start: '2026-09-01', end: '2026-09-17' });
    f.start.setAttribute('class', 'flatpickr-date');
    h.ready.forEach(callback => callback());
    const config = h.calls[0].config;
    assert.equal(h.calls[0].el, f.input);
    assert.equal(config.mode, 'range');
    assert.equal(config.locale.rangeSeparator, ' to ');
    assert.equal(config.dateFormat, 'Y-m-d');
    assert.equal(config.allowInput, true);
    assert.equal(config.disableMobile, true);
    assert.equal(h.calls.find(call => call.el === single).config.dateFormat, 'Y-m-d');
    assert.equal(h.calls.find(call => call.el === datetime).config.enableTime, true);
    assert.equal(h.calls.find(call => call.el === single).config.disableMobile, undefined);
    h.ajax.forEach(callback => callback());
    h.window.initFlatpickr(f.form);
    assert.equal(h.calls.length, 3);
    assert.equal(f.input.value, '2026-09-01 to 2026-09-17');
    assert.equal(h.params(f.form), 'start_date=2026-09-01&end_date=2026-09-17');
});

test('complete ranges publish both parameters atomically, without duplicate notifications', t => {
    const h = setup(t);
    const f = h.fixture();
    h.window.initFlatpickr();
    const observed = [];
    [f.start, f.end].forEach(el => el.addEventListener('change', () => {
        observed.push(h.params(f.form));
        assert.equal(h.window.syncFlatpickrRange(f.input), true);
    }));
    h.type(f.input, '2026-09-02 to 2026-09-18');
    assert.deepEqual(observed, Array(2).fill('start_date=2026-09-02&end_date=2026-09-18'));
    assert.equal(h.window.syncFlatpickrRange(f.input), true);
    assert.equal(observed.length, 2);
    h.type(f.input, '2026-09-02');
    pair(f, '', '');
    h.type(f.input, '');
    assert.equal(observed.length, 2);
    assert.equal(h.params(f.form), 'start_date=&end_date=');
});

test('strict manual parsing rejects malformed, impossible, partial, and reversed ranges', t => {
    const h = setup(t);
    const f = h.fixture();
    h.window.initFlatpickr();
    for (const value of [
        '2026-09-17', '2026-09-17 to ', ' to 2026-09-17', '2026-09-18 to 2026-09-17',
        '2026-02-29 to 2026-03-01', '2026-04-31 to 2026-05-01', '2026-13-01 to 2027-01-01',
        '2026-00-01 to 2026-01-01', '2026-09-00 to 2026-09-01', '0000-01-01 to 2026-09-17',
        '2026-9-01 to 2026-09-17', '2026-09-01 - 2026-09-17', ' 2026-09-01 to 2026-09-17',
        '2026-09-01 to 2026-09-17 ', '2026-09-01 to 2026-09-17 to 2026-09-18', 'garbage',
    ]) {
        h.type(f.input, '2026-09-01 to 2026-09-17');
        h.type(f.input, value);
        assert.equal(h.window.syncFlatpickrRange(f.input), false, value);
        pair(f, '', '');
        f.input.dispatchEvent(new h.window.FocusEvent('blur'));
        assert.equal(f.input.value, value);
        assert.ok(f.input.validationMessage);
    }
    h.type(f.input, '2024-02-29 to 2024-02-29');
    assert.equal(h.window.syncFlatpickrRange(f.input), true);
});

test('manual same-day range retains explicit endpoints through blur, Enter, and close', t => {
    const h = setup(t);
    const f = h.fixture({ required: true });
    h.window.initFlatpickr();
    const value = '2026-09-17 to 2026-09-17';
    h.type(f.input, value);
    f.input.dispatchEvent(new h.window.KeyboardEvent('keydown', { key: 'Enter', keyCode: 13, bubbles: true, cancelable: true }));
    f.input.dispatchEvent(new h.window.FocusEvent('blur'));
    f.input._flatpickr.close();
    h.flush();
    assert.equal(f.input.value, value);
    assert.equal(f.input._flatpickr.selectedDates.length, 2);
    pair(f, '2026-09-17', '2026-09-17');
});

test('calendar first selection remains available for a second selection and same-day completion', t => {
    const h = setup(t);
    const f = h.fixture({ start: '2026-09-01', end: '2026-09-17' });
    h.window.initFlatpickr();
    const picker = f.input._flatpickr;
    let changes = 0;
    f.start.addEventListener('change', () => changes++);
    picker.clear();
    const day = picker.parseDate ? picker.parseDate('2026-09-17', 'Y-m-d') : new Date(2026, 8, 17);
    if (picker.daysContainer) {
        picker.jumpToDate(day);
        const clickDay = () => Array.from(picker.daysContainer.querySelectorAll('.flatpickr-day')).find(el => el.dateObj.getTime() === day.getTime()).click();
        clickDay();
        pair(f, '', '');
        assert.equal(picker.selectedDates.length, 1);
        assert.equal(changes, 0);
        clickDay();
    } else {
        picker.setDate([day], true);
        pair(f, '', '');
        assert.equal(picker.selectedDates.length, 1);
        assert.equal(changes, 0);
        picker.setDate([day, day], true);
    }
    pair(f, '2026-09-17', '2026-09-17');
    assert.equal(f.input.value, '2026-09-17 to 2026-09-17');
    assert.equal(changes, 1);
});

test('initial datetime endpoints keep distinct times and normalize seconds to minutes', t => {
    const h = setup(t);
    for (const sameDay of [false, true]) {
        const lastDay = sameDay ? '17' : '18';
        const f = h.fixture({ time: true, start: '2026-09-17 08:12:59', end: `2026-09-${lastDay} 19:43:01` });
        h.window.initFlatpickr(f.input);
        pair(f, '2026-09-17 08:12', `2026-09-${lastDay} 19:43`);
        assert.equal(f.input.value, `2026-09-17 08:12 to 2026-09-${lastDay} 19:43`);
        assert.equal(f.input._flatpickr.selectedDates[0].getHours(), 8);
        assert.equal(f.input._flatpickr.selectedDates[1].getHours(), 19);
        assert.equal(f.input._flatpickr.selectedDates[1].getSeconds(), 0);
        assert.equal(f.input._flatpickr.config.dateFormat, 'Y-m-d H:i');
        h.ajax.forEach(callback => callback());
        pair(f, '2026-09-17 08:12', `2026-09-${lastDay} 19:43`);
    }
});

test('manual same-day datetime preserves independent times and rejects reversed times and seconds', t => {
    const h = setup(t);
    const f = h.fixture({ time: true });
    h.window.initFlatpickr();
    const value = '2026-09-17 08:15 to 2026-09-17 18:45';
    h.type(f.input, value);
    assert.equal(h.window.syncFlatpickrRange(f.input), true);
    f.input._flatpickr.close();
    h.flush();
    assert.equal(f.input.value, value);
    pair(f, '2026-09-17 08:15', '2026-09-17 18:45');
    for (const invalid of [
        '2026-09-17 18:45 to 2026-09-17 08:15', '2026-09-17 24:00 to 2026-09-18 08:00',
        '2026-09-17 08:60 to 2026-09-18 08:00', '2026-09-17 8:00 to 2026-09-18 08:00',
        '2026-09-17 08:00:00 to 2026-09-18 08:00:00', '2026-09-17T08:00 to 2026-09-18T08:00',
    ]) {
        h.type(f.input, invalid);
        assert.equal(h.window.syncFlatpickrRange(f.input), false);
        pair(f, '', '');
        assert.equal(f.input.value, invalid);
    }
});

test('capture guard blocks required empty and optional partial before AJAX handlers', t => {
    const h = setup(t);
    const required = h.fixture({ required: true });
    const optional = h.fixture();
    let ajaxCalls = 0;
    [required, optional].forEach(f => f.form.addEventListener('submit', event => { ajaxCalls++; event.preventDefault(); }));
    h.window.initFlatpickr();
    const submit = f => f.form.dispatchEvent(new h.window.Event('submit', { bubbles: true, cancelable: true }));
    submit(required);
    assert.equal(ajaxCalls, 0);
    submit(optional);
    assert.equal(ajaxCalls, 1);
    optional.input.value = '2026-09-17';
    submit(optional);
    assert.equal(ajaxCalls, 1);
    required.input.value = '2026-09-17 to 2026-09-17';
    submit(required);
    assert.equal(ajaxCalls, 2);
    pair(required, '2026-09-17', '2026-09-17');
    assert.equal(required.input.validationMessage, '');
});

test('forced sync handles direct assignments, missing elements, and invalid bindings safely', t => {
    const h = setup(t);
    const f = h.fixture();
    f.input.value = '2026-09-17 to 2026-09-18';
    assert.equal(h.window.syncFlatpickrRange(f.input), true);
    pair(f, '2026-09-17', '2026-09-18');
    assert.equal(h.window.syncFlatpickrRange(null), false);
    assert.equal(h.window.syncFlatpickrRange(f.start), false);
    f.input.setAttribute('data-range-end', '#missing');
    assert.equal(h.window.syncFlatpickrRange(f.input), false);
    assert.equal(f.start.value, '');
    f.input.setAttribute('data-range-end', '#start1');
    assert.equal(h.window.syncFlatpickrRange(f.input), false);
});

test('reset restores initial complete or empty state without emitting hidden changes', t => {
    const h = setup(t);
    for (const options of [
        { start: '2026-09-01', end: '2026-09-17' },
        { text: '2026-09-02 to 2026-09-18' },
        { time: true, start: '2026-09-17 08:00:12', end: '2026-09-17 18:00:59' },
        {}, { required: true },
    ]) {
        const f = h.fixture(options);
        h.window.initFlatpickr(f.form);
        const initial = [f.input.value, f.start.value, f.end.value, f.input.validationMessage];
        h.type(f.input, options.time ? '2026-09-20 07:00 to 2026-09-21 08:00' : '2026-09-20 to 2026-09-21');
        let changes = 0;
        f.start.addEventListener('change', () => changes++);
        f.form.reset();
        h.flush();
        assert.deepEqual([f.input.value, f.start.value, f.end.value, f.input.validationMessage], initial);
        assert.equal(changes, 0);
    }
});

test('cancelled reset preserves current edits', t => {
    const h = setup(t);
    const f = h.fixture();
    h.window.initFlatpickr();
    h.type(f.input, '2026-09-17 to 2026-09-18');
    f.form.addEventListener('reset', event => event.preventDefault());
    f.form.reset();
    h.flush();
    pair(f, '2026-09-17', '2026-09-18');
});

test('AJAX rescans initialize all pairs, including a root input, without clobbering partial edits', t => {
    const h = setup(t);
    const first = h.fixture();
    h.window.initFlatpickr(first.input);
    h.type(first.input, '2026-09-17');
    const second = h.fixture({ start: '2026-10-01', end: '2026-10-02' });
    h.ajax.forEach(callback => callback());
    h.ajax.forEach(callback => callback());
    assert.equal(h.calls.length, 2);
    assert.equal(first.input.value, '2026-09-17');
    pair(first, '', '');
    pair(second, '2026-10-01', '2026-10-02');
});

test('invalid initial endpoints clear both hidden values and remain invalid', t => {
    const h = setup(t);
    for (const options of [
        { start: '2026-09-18', end: '2026-09-17' },
        { start: '2026-09-17' },
        { time: true, start: '2026-09-17 08:00:99', end: '2026-09-18 09:00:00' },
    ]) {
        const f = h.fixture(options);
        h.window.initFlatpickr(f.form);
        pair(f, '', '');
        assert.equal(h.window.syncFlatpickrRange(f.input), false);
    }
});

if (process.env.FLATPICKR_BROWSER_MODULE) {
    test('browser calendar reselection, manual validation, AJAX capture, and datetime preservation', async t => {
        const { chromium } = require(process.env.FLATPICKR_BROWSER_MODULE);
        const browser = await chromium.launch({ executablePath: process.env.FLATPICKR_BROWSER_PATH, headless: true });
        t.after(() => browser.close());
        const page = await browser.newPage();
        await page.setContent('<form novalidate><input id="flatpickr-range" data-range-start="#start" data-range-end="#end" required><input type="hidden" id="start" name="start_date"><input type="hidden" id="end" name="end_date"><button type="submit">Submit</button></form><button id="outside">Outside</button>');
        await page.addScriptTag({ path: path.join(__dirname, '../public/assets/vendor/libs/jquery/jquery.js') });
        await page.addScriptTag({ path: path.join(__dirname, '../public/assets/vendor/libs/flatpickr/flatpickr.js') });
        await page.addScriptTag({ content: source });
        await page.evaluate(() => {
            window.initFlatpickr();
            window.submissions = [];
            $('form').on('submit', event => {
                event.preventDefault();
                window.submissions.push($(event.target).serialize());
            });
        });
        const input = page.locator('#flatpickr-range');
        const values = () => page.evaluate(() => [document.querySelector('#start').value, document.querySelector('#end').value]);
        const select = async (first, last) => {
            await input.click();
            await page.evaluate(() => document.querySelector('#flatpickr-range')._flatpickr.jumpToDate('2026-09-01'));
            await page.locator(`.flatpickr-day[aria-label="September ${first}, 2026"]`).click();
            assert.deepEqual(await values(), ['', '']);
            await page.locator(`.flatpickr-day[aria-label="September ${last}, 2026"]`).click();
            assert.deepEqual(await values(), [`2026-09-${first}`, `2026-09-${last}`]);
        };
        await select(17, 18);
        await select(20, 21);
        await select(22, 22);
        await input.fill('2026-09-18 to 2026-09-17');
        await page.locator('button[type="submit"]').click();
        assert.deepEqual(await values(), ['', '']);
        assert.equal(await input.inputValue(), '2026-09-18 to 2026-09-17');
        assert.deepEqual(await page.evaluate(() => window.submissions), []);
        await input.fill('2026-09-17 to 2026-09-17');
        await page.locator('button[type="submit"]').click();
        assert.deepEqual(await page.evaluate(() => window.submissions), ['start_date=2026-09-17&end_date=2026-09-17']);
        await page.evaluate(() => {
            const form = document.createElement('form');
            form.innerHTML = '<input id="datetime-range" data-range-start="#time-start" data-range-end="#time-end" data-range-time="true"><input type="hidden" id="time-start" value="2026-09-17 08:15:59"><input type="hidden" id="time-end" value="2026-09-17 18:45:01">';
            document.body.appendChild(form);
            window.initFlatpickr(form);
        });
        const datetime = page.locator('#datetime-range');
        assert.equal(await datetime.inputValue(), '2026-09-17 08:15 to 2026-09-17 18:45');
        await datetime.click();
        await datetime.fill('2026-09-17 07:12 to 2026-09-17 19:34');
        await page.locator('#outside').click();
        assert.equal(await datetime.inputValue(), '2026-09-17 07:12 to 2026-09-17 19:34');
        assert.deepEqual(await page.evaluate(() => [document.querySelector('#time-start').value, document.querySelector('#time-end').value]), ['2026-09-17 07:12', '2026-09-17 19:34']);
    });
}

if (process.env.FLATPICKR_DOM_MODULE) {
    test('real Flatpickr outside-click parsing cannot normalize invalid manual text', t => {
        const h = setup(t);
        const f = h.fixture({ time: true });
        h.window.initFlatpickr();
        const picker = f.input._flatpickr;
        for (const value of ['2026-09-18 08:00 to 2026-09-17 09:00', '2026-02-30 08:00 to 2026-03-01 09:00', '2026-09-17 08:00']) {
            picker.open();
            h.type(f.input, value);
            h.document.body.dispatchEvent(new h.window.MouseEvent('mousedown', { bubbles: true }));
            h.flush();
            assert.equal(f.input.value, value);
            assert.equal(h.window.syncFlatpickrRange(f.input), false);
            pair(f, '', '');
        }
    });
}
