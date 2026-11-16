const assert = require('node:assert/strict');
const {readFileSync} = require('node:fs');
const {resolve} = require('node:path');
const {test} = require('node:test');
const vm = require('node:vm');

function load(file, dependencies, globals = {}) {
    let definition;
    vm.runInNewContext(readFileSync(resolve(__dirname, '../../bin/backend', file), 'utf8'), {
        Class: function (value) {return value;},
        define(name, names, factory) {definition = factory(...names.map(name => dependencies[name] ?? {}));},
        ...globals
    });
    return definition;
}

function fixture(count = 55) {
    const element = () => ({
        style: {removeProperty() {}}, classList: {add() {}}, attributes: {},
        firstElementChild: {setAttribute() {}}, appendChild() {},
        setAttribute(name, value) {this.attributes[name] = value;},
        getSize: () => ({x: 1000, y: 600})
    });
    class Button {
        constructor(options) {this.options = options;this.element = element();}
        getAttribute(name) {return this.options[name];}
        getElm() {return this.element;}
        enable() {this.options.disabled = false;}
        disable() {this.options.disabled = true;}
    }
    class Grid {
        constructor(container, options) {
            this.options = options;
            this.buttons = options.buttons.map(options => new Button(options));
            this.selected = [];
        }
        getButton(name) {return this.buttons.find(Button => Button.getAttribute('name') === name);}
        getButtons() {return this.buttons;}
        getSelectedIndices() {return this.selected.map((row, index) => index);}
        getSelectedData() {return this.selected;}
        setData(data) {this.data = data;this.options.page = data.page;this.selected = [];}
        addEvents(events) {this.events = events;}
        setHeight(value) {this.height = value;}
        setWidth(value) {this.width = value;}
    }
    const source = Array.from({length: count}, (_, index) => ({
        id: index + 1, priority: index + 1, active: index % 2,
        title: {en: 'Payment ' + (index + 1)}, workingTitle: 'Working ' + (index + 1),
        paymentType: {title: 'Method ' + (index + 1)}
    }));
    const requests = [];
    let records = source;
    let failure = null;
    const pending = [];
    let defer = false;
    const Payments = {
        addEvents() {},removeEvents() {},
        getPayments(refresh) {
            requests.push(refresh);
            if (defer) return new Promise((resolve, reject) => pending.push({resolve, reject}));
            return failure ? Promise.reject(failure) : Promise.resolve(records);
        }
    };
    const definition = load('controls/Payments.js', {
        'controls/grid/Grid': Grid,
        'package/quiqqer/payments/bin/backend/Payments': Payments,
        Locale: {get: (group, key) => key, getCurrent: () => 'en'}
    }, {document: {createElement: element}});
    const root = element();
    const content = element();
    const panel = Object.assign({}, definition, {
        parent() {},setAttributes() {},addEvents() {},
        getElm: () => root,getContent: () => content,$Elm: root,
        Loader: {visible: false,show() {this.visible = true;},hide() {this.visible = false;}}
    });
    panel.initialize({});
    panel.$onCreate();
    return {
        panel, content, source, requests, pending,
        setRecords(value) {records = value;}, fail(error) {failure = error;}, defer() {defer = true;}
    };
}

test('native grid has pagination, descriptive actions, icon-only delete and ordered numeric columns', () => {
    const f = fixture();
    const Grid = f.panel.$Grid;
    assert.equal(f.content.style.padding, '10px');
    assert.equal(Grid.options.pagination, true);
    assert.equal(Grid.options.serverSort, true);
    assert.deepEqual(Array.from(Grid.options.buttons, Button => Button.name), ['add', 'edit', 'delete']);
    assert.equal(Grid.getButton('add').getAttribute('text'), 'panel.button.create');
    assert.equal(Grid.getButton('edit').getAttribute('text'), 'panel.button.edit');
    assert.equal(Grid.getButton('delete').getAttribute('position'), 'right');
    assert.equal(Grid.getButton('delete').getElm().attributes['aria-label'], 'panel.button.delete');
    const [id, priority] = Grid.options.columnModel;
    assert.equal(id.dataIndex, 'id');assert.equal(priority.dataIndex, 'priority');
    assert.ok(id.width >= 80);assert.ok(priority.width >= 110);
    for (const column of [id, priority]) assert.equal(column.styles['justify-content'], 'flex-end');
});

test('refresh pages the full list and preserves the API cache and string titles', async () => {
    const f = fixture();
    f.panel.$Grid.options.perPage = 10;
    f.panel.$Grid.options.page = 2;
    await f.panel.refresh();
    assert.equal(f.requests[0], true);
    const data = f.panel.$Grid.data;
    assert.equal(data.total, 55);assert.equal(data.page, 2);assert.equal(data.data.length, 10);
    assert.equal(data.data[0].id, 11);assert.equal(data.data[9].id, 20);
    assert.equal(data.data[0].title, 'Payment 11');
    assert.equal(data.data[0].workingTitle, 'Working 11');
    assert.deepEqual(f.source[10].title, {en: 'Payment 11'});
    assert.equal(f.source[10].status, undefined);
    assert.equal(f.panel.Loader.visible, false);
    assert.equal(f.panel.$Grid.width, 980);assert.equal(f.panel.$Grid.height, 580);
});

test('sorting applies before pagination and shorter lists clamp the last page', async () => {
    const f = fixture();
    const Grid = f.panel.$Grid;
    Grid.options.perPage = 10;Grid.options.sortOn = 'id';Grid.options.sortBy = 'DESC';
    await f.panel.refresh();
    assert.equal(Grid.data.data[0].id, 55);assert.equal(Grid.data.data[9].id, 46);
    Grid.options.page = 6;
    f.setRecords(f.source.slice(0, 12));
    await f.panel.refresh();
    assert.equal(Grid.data.page, 2);assert.equal(Grid.data.total, 12);
    assert.deepEqual(Array.from(Grid.data.data, row => row.id), [2, 1]);
    f.setRecords([]);
    await f.panel.refresh();
    assert.equal(Grid.data.page, 1);assert.equal(Grid.data.total, 0);
});

test('edit and delete require one selected payment method and reload clears actions', async () => {
    const f = fixture();
    const Grid = f.panel.$Grid;
    for (const selected of [[], [{id: 1}], [{id: 1}, {id: 2}]]) {
        Grid.selected = selected;
        f.panel.$refreshButtonStatus();
        assert.equal(Grid.getButton('edit').getAttribute('disabled'), selected.length !== 1);
        assert.equal(Grid.getButton('delete').getAttribute('disabled'), selected.length !== 1);
    }
    Grid.selected = [{id: 1}];
    await f.panel.refresh();
    assert.equal(Grid.getButton('edit').getAttribute('disabled'), true);
    assert.equal(Grid.getButton('delete').getAttribute('disabled'), true);
});

test('failed refresh clears the loader and obsolete replies cannot replace current data', async () => {
    const f = fixture();
    f.fail(new Error('offline'));
    await assert.rejects(f.panel.refresh(), /offline/);
    assert.equal(f.panel.Loader.visible, false);
    f.defer();
    const old = f.panel.refresh();const current = f.panel.refresh();
    f.pending[1].resolve(f.source.slice(0, 1));await current;
    f.pending[0].resolve(f.source);await old;
    assert.equal(f.panel.$Grid.data.total, 1);
    const closing = f.panel.refresh();
    f.panel.$onDestroy();f.pending[2].resolve(f.source);await closing;
    assert.equal(f.panel.$Grid.data.total, 1);
});

test('payment handler can bypass its cache for the native refresh action', async () => {
    const requests = [];
    const definition = load('classes/Handler.js', {Ajax: {get(name, resolve) {requests.push(resolve);}}});
    const cached = [{id: 1}];
    const handler = Object.assign({}, definition, {$payments: cached});
    assert.equal(await handler.getPayments(), cached);
    assert.equal(requests.length, 0);
    const fresh = [{id: 2}];
    const loading = handler.getPayments(true);
    requests[0](fresh);
    assert.equal(await loading, fresh);
    assert.equal(await handler.getPayments(), fresh);
});
