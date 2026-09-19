/**
 * The rules a reference dropdown follows, tested without a browser.
 *
 * Node's own test runner, no dependencies: `referenceOptions` is a function
 * over plain objects precisely so that it needs nothing. The element that
 * calls it reads and writes the DOM and is covered by the Cypress spec in
 * 1.11; what is here is every rule about what beats what.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { referenceOptions, liveEntries, lowerFirst } from
  '../../src/media/com_extengen/js/reference-options.js';

const names = (options) => options.map((o) => o.name);
const ids = (options) => options.map((o) => o.id);

test('with nothing anywhere, there is nothing to choose', () => {
  assert.deepEqual(referenceOptions(), []);
});

test('what the server stored is offered', () => {
  const options = referenceOptions({
    stored: [{ id: 'a', name: 'Speaker' }, { id: 'b', name: 'Talk' }],
  });

  assert.deepEqual(names(options), ['Speaker', 'Talk']);
});

test('an entity added but never saved is offered too', () => {
  // This is the whole point of the rework: before it, the options came from a
  // database query, so a new entity was invisible until the project was saved.
  const options = referenceOptions({
    stored: [{ id: 'a', name: 'Speaker' }],
    live: [{ id: 'a', name: 'Speaker' }, { id: 'new', name: 'Sponsor' }],
  });

  assert.deepEqual(names(options), ['Speaker', 'Sponsor']);
});

test('a rename on screen wins over the stored name', () => {
  const options = referenceOptions({
    stored: [{ id: 'a', name: 'Speaker' }],
    live: [{ id: 'a', name: 'Presenter' }],
  });

  assert.deepEqual(names(options), ['Presenter']);
  assert.deepEqual(ids(options), ['a']);
});

test('an object with no name yet is not offered', () => {
  // It would render as a blank line, indistinguishable from the empty choice.
  const options = referenceOptions({
    live: [{ id: 'a', name: '' }, { id: 'b', name: 'Talk' }],
  });

  assert.deepEqual(names(options), ['Talk']);
});

test('a scoped dropdown shows only its parent\'s children', () => {
  const options = referenceOptions({
    stored: [
      { id: 'f1', name: 'name', parent: 'e1' },
      { id: 'f2', name: 'room', parent: 'e2' },
      { id: 'f3', name: 'starts', parent: 'e1' },
    ],
    parent: 'e1',
  });

  assert.deepEqual(names(options), ['name', 'starts']);
});

test('a scoped dropdown does not show children of no parent at all', () => {
  const options = referenceOptions({
    stored: [{ id: 'f1', name: 'orphan' }, { id: 'f2', name: 'mine', parent: 'e1' }],
    parent: 'e1',
  });

  assert.deepEqual(names(options), ['mine']);
});

test('options are in name order, so a long list can be read', () => {
  const options = referenceOptions({
    stored: [{ id: '1', name: 'Zebra' }, { id: '2', name: 'Aardvark' }, { id: '3', name: 'Moose' }],
  });

  assert.deepEqual(names(options), ['Aardvark', 'Moose', 'Zebra']);
});

test('the held value survives when it has gone out of scope', () => {
  // Changing which entity a field belongs to must not silently rewrite the
  // field reference stored against it.
  const options = referenceOptions({
    stored: [{ id: 'f1', name: 'name', parent: 'e1' }],
    parent: 'e2',
    selected: 'f1',
  });

  assert.deepEqual(ids(options), ['f1']);
  assert.deepEqual(names(options), ['name']);
});

test('a held value nothing knows about reads as missing rather than vanishing', () => {
  const options = referenceOptions({
    stored: [{ id: 'a', name: 'Speaker' }],
    selected: 'deleted-long-ago',
  });

  assert.deepEqual(ids(options), ['a', 'deleted-long-ago']);
  assert.match(names(options)[1], /missing/);
});

test('the held value is not offered twice when it is also in scope', () => {
  const options = referenceOptions({
    stored: [{ id: 'a', name: 'Speaker' }],
    selected: 'a',
  });

  assert.deepEqual(ids(options), ['a']);
});

test('an entry without an id is ignored on both sides', () => {
  const options = referenceOptions({
    stored: [{ id: '', name: 'Half typed' }, { id: 'a', name: 'Speaker' }],
    live: [{ name: 'No id at all' }],
  });

  assert.deepEqual(names(options), ['Speaker']);
});

test('the inputs are not modified', () => {
  const stored = [{ id: 'a', name: 'Speaker' }];
  const live = [{ id: 'a', name: 'Presenter' }];

  referenceOptions({ stored, live });

  assert.deepEqual(stored, [{ id: 'a', name: 'Speaker' }]);
  assert.deepEqual(live, [{ id: 'a', name: 'Presenter' }]);
});

// --- liveEntries, against the smallest thing that answers like a document ---

/**
 * Just enough of a document: elements with an id, a class and a value.
 *
 * The element ids here are the shape Joomla's subform field really produces,
 * because the whole of liveEntries is a claim about that shape.
 */
function fakeDocument(elements) {
  const byId = new Map(elements.map((el) => [el.id, { className: '', value: '', ...el }]));

  return {
    querySelectorAll: (selector) => {
      const wanted = selector.replace(/^\./, '');

      return [...byId.values()].filter((el) => el.className.split(/\s+/).includes(wanted));
    },
    getElementById: (id) => byId.get(id) ?? null,
  };
}

const ENTITY = {
  selector: 'entityName',
  nameToken: 'entity_name',
  idToken: 'entity_id',
};

const FIELD = {
  selector: 'fieldName',
  nameToken: 'field_name',
  idToken: 'field_id',
  parentToken: 'entity_id',
  parentCut: '_field__field',
};

test('a row with no id yet is given one, and keeps it', () => {
  const root = fakeDocument([
    { id: 'jform_datamodel__datamodel0_entity_name', className: 'entityName', value: 'Speaker' },
    { id: 'jform_datamodel__datamodel0_entity_id', value: '' },
  ]);

  const entries = liveEntries({ root, ...ENTITY, newId: () => 'made-up' });

  assert.deepEqual(entries, [{ id: 'made-up', name: 'Speaker' }]);
  assert.equal(root.getElementById('jform_datamodel__datamodel0_entity_id').value, 'made-up');
});

test('a row that already has an id is left alone', () => {
  const root = fakeDocument([
    { id: 'jform_datamodel__datamodel0_entity_name', className: 'entityName', value: 'Speaker' },
    { id: 'jform_datamodel__datamodel0_entity_id', value: 'kept' },
  ]);

  const entries = liveEntries({ root, ...ENTITY, newId: () => 'made-up' });

  assert.deepEqual(entries, [{ id: 'kept', name: 'Speaker' }]);
});

test('a child row reports the parent held in its own hidden field', () => {
  const root = fakeDocument([
    {
      id: 'jform_datamodel__datamodel0_field__field0_field_name',
      className: 'fieldName',
      value: 'room',
    },
    { id: 'jform_datamodel__datamodel0_field__field0_field_id', value: 'f1' },
    { id: 'jform_datamodel__datamodel0_field__field0_entity_id', value: 'e1' },
  ]);

  const entries = liveEntries({ root, ...FIELD, newId: () => 'x' });

  assert.deepEqual(entries, [{ id: 'f1', name: 'room', parent: 'e1' }]);
});

test('a child row added just now finds its parent by position', () => {
  // A newly added field has no entity_id yet. Before this, the fields dropdown
  // for that entity simply did not list it.
  const root = fakeDocument([
    {
      id: 'jform_datamodel__datamodel0_field__field0_field_name',
      className: 'fieldName',
      value: 'room',
    },
    { id: 'jform_datamodel__datamodel0_field__field0_field_id', value: 'f1' },
    { id: 'jform_datamodel__datamodel0_field__field0_entity_id', value: '' },
    { id: 'jform_datamodel__datamodel0_entity_id', value: 'e1' },
  ]);

  const entries = liveEntries({ root, ...FIELD, newId: () => 'x' });

  assert.deepEqual(entries, [{ id: 'f1', name: 'room', parent: 'e1' }]);
});

test('finding the parent by position writes it back, so the form submits it too', () => {
  const root = fakeDocument([
    {
      id: 'jform_datamodel__datamodel0_field__field0_field_name',
      className: 'fieldName',
      value: 'room',
    },
    { id: 'jform_datamodel__datamodel0_field__field0_field_id', value: 'f1' },
    { id: 'jform_datamodel__datamodel0_field__field0_entity_id', value: '' },
    { id: 'jform_datamodel__datamodel0_entity_id', value: 'e1' },
  ]);

  liveEntries({ root, ...FIELD, newId: () => 'x' });

  assert.equal(
    root.getElementById('jform_datamodel__datamodel0_field__field0_entity_id').value,
    'e1',
  );
});

test('a child whose parent has no id either reports no parent rather than throwing', () => {
  const root = fakeDocument([
    {
      id: 'jform_datamodel__datamodel0_field__field0_field_name',
      className: 'fieldName',
      value: 'room',
    },
    { id: 'jform_datamodel__datamodel0_field__field0_field_id', value: 'f1' },
    { id: 'jform_datamodel__datamodel0_field__field0_entity_id', value: '' },
  ]);

  assert.deepEqual(liveEntries({ root, ...FIELD, newId: () => 'x' }), [
    { id: 'f1', name: 'room', parent: '' },
  ]);
});

test('a name input with no id input beside it is skipped rather than throwing', () => {
  // One malformed row must not take down every other dropdown on the page.
  const root = fakeDocument([
    { id: 'lonely_entity_name', className: 'entityName', value: 'Nobody' },
    { id: 'jform_datamodel__datamodel0_entity_name', className: 'entityName', value: 'Speaker' },
    { id: 'jform_datamodel__datamodel0_entity_id', value: 'e1' },
  ]);

  assert.deepEqual(liveEntries({ root, ...ENTITY, newId: () => 'x' }), [
    { id: 'e1', name: 'Speaker' },
  ]);
});

test('only rows carrying the class are read', () => {
  const root = fakeDocument([
    { id: 'jform_pages__pages0_page_name', className: 'pageName', value: 'Talks' },
    { id: 'jform_pages__pages0_page_id', value: 'p1' },
    { id: 'jform_datamodel__datamodel0_entity_name', className: 'entityName', value: 'Speaker' },
    { id: 'jform_datamodel__datamodel0_entity_id', value: 'e1' },
  ]);

  assert.deepEqual(liveEntries({ root, ...ENTITY, newId: () => 'x' }), [
    { id: 'e1', name: 'Speaker' },
  ]);
});

test('the class a type looks for starts lower case', () => {
  assert.equal(lowerFirst('Entity'), 'entity');
  assert.equal(lowerFirst('Field'), 'field');
});
