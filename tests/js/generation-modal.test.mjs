/**
 * The generate button's rules, tested without a browser.
 *
 * Node's own test runner and nothing else. `modalSource` and `showInModal`
 * take what they work on as arguments precisely so that this file can hand
 * them plain objects: a fake is an object with `closest` and `getAttribute`,
 * which is all they ask for. The wiring that listens for the click reads the
 * real DOM and is covered by `generate.cy.js`.
 *
 * The module under test is byte-identical to Meta-gen's, because the script was
 * - the same twelve lines with the same note asking to be moved out. So is
 * most of this file. That duplication is deliberate and temporary: both belong
 * in `lib_yepr_gen` beside `reference.js`, and putting them there is a library
 * release rather than a tidy-up.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';

import { modalSource, showInModal } from '../../src/media/com_extengen/js/generation-modal.js';

/**
 * A stand-in for a clicked element.
 *
 * `closest` is the only thing the code asks of it, and it answers the way the
 * DOM does: itself if it matches, otherwise the nearest ancestor that does.
 */
const clicked = ({ href = null, inside = null } = {}) => {
  const button = href === null && inside === null
    ? null
    : { getAttribute: (name) => (name === 'data-href' ? (inside ?? href) : null) };

  return {
    closest: (selector) => (selector === '.dynbutton' && button !== null ? button : null),
  };
};

/** A stand-in for the page, holding one iframe. */
const page = ({ hasFrame = true } = {}) => {
  const frame = { src: null, setAttribute: (name, value) => { if (name === 'src') frame.src = value; } };

  return {
    frame,
    querySelector: (selector) =>
      (selector === '#generationModal iframe' && hasFrame ? frame : null),
  };
};

test('a click on the button names the project it belongs to', () => {
  assert.equal(modalSource(clicked({ href: '/generate?project_id=7' })), '/generate?project_id=7');
});

test('a click on something inside the button names it too', () => {
  // The reason `closest` is used at all. The old inline version read
  // `event.target` directly, so an icon inside the button - which is how every
  // other button on these screens is built - would have returned null and left
  // the modal showing whatever it had last time.
  const icon = clicked({ inside: '/generate?project_id=7' });

  assert.equal(modalSource(icon), '/generate?project_id=7');
});

test('a click somewhere else names nothing', () => {
  assert.equal(modalSource(clicked()), null);
});

test('a button with an empty data-href names nothing', () => {
  assert.equal(modalSource(clicked({ href: '' })), null);
});

test('nothing at all names nothing, rather than throwing', () => {
  // A listener is attached to the document, and not everything that reaches it
  // is an element with a `closest`.
  assert.equal(modalSource(null), null);
  assert.equal(modalSource({}), null);
});

test('the iframe is pointed at what was asked for', () => {
  const root = page();

  assert.equal(showInModal(clicked({ href: '/generate?project_id=2' }), root), true);
  assert.equal(root.frame.src, '/generate?project_id=2');
});

test('a click that names nothing leaves the iframe alone', () => {
  // Deliberately not blanked. A modal showing the previous language is wrong;
  // a modal showing an empty frame is wrong in a way that looks like the page
  // itself has broken.
  const root = page();

  root.frame.src = '/generate?project_id=1';

  assert.equal(showInModal(clicked(), root), false);
  assert.equal(root.frame.src, '/generate?project_id=1');
});

test('a page with no modal in it is not an error', () => {
  assert.equal(showInModal(clicked({ href: '/generate' }), page({ hasFrame: false })), false);
  assert.equal(showInModal(clicked({ href: '/generate' }), null), false);
});
