/**
 * What a project may be called, checked on both sides of the wire.
 *
 * A project's name becomes the component name, the namespace segment, the
 * table prefix and every class name in the generated extension. A digit or a
 * space in it does not produce a worse component; it produces one that does
 * not load. So the rule is strict, and it is applied twice: the browser
 * refuses it on the way out, Joomla refuses it on the way in.
 *
 * **Twice is the problem.** Two copies of one rule drifting apart is how the
 * discriminator, the subtype payload key and `reference_id` each got in, and
 * each time the copies agreed on every case anybody had tried. So the last
 * test here does not check the JavaScript against a list of examples somebody
 * wrote down - it reads the regular expression out of `LetterRule.php` and
 * requires it to be the same one.
 */

import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

import { isLetters, LETTER_PATTERN } from '../../src/media/com_extengen/js/admin-extengen-letter.js';

test('letters are a name', () => {
  assert.equal(isLetters('Conference'), true);
  assert.equal(isLetters('conference'), true);
  assert.equal(isLetters('CONFERENCE'), true);
  assert.equal(isLetters('a'), true);
});

test('nothing is not a name', () => {
  // `required` is what says a name must be there; this says what it may be.
  // A rule that accepted the empty string would let the two disagree about
  // which of them is refusing it.
  assert.equal(isLetters(''), false);
});

test('a digit is not a letter', () => {
  // The case that actually comes up: a second version of something.
  assert.equal(isLetters('Conference2'), false);
  assert.equal(isLetters('2Conference'), false);
});

test('and neither is a space, a hyphen or an underscore', () => {
  assert.equal(isLetters('My Conference'), false);
  assert.equal(isLetters('My-Conference'), false);
  assert.equal(isLetters('My_Conference'), false);
});

const NEWLINE = String.fromCharCode(10);

test('a newline does not sneak past the end', () => {
  // JavaScript's `$` matches only at the end of the input, so this side is the
  // strict one. PCRE's `$` also matches *before* a final newline unless told
  // otherwise, which is the classic way a `^...$` check lets something
  // through - and which the last test in this file is what caught.
  assert.equal(isLetters('Conference' + NEWLINE), false);
  assert.equal(isLetters(NEWLINE + 'Conference'), false);
});

test('the browser applies the rule Joomla applies', () => {
  // As text, deliberately. Compiling the PHP pattern with `new RegExp` and
  // running both would compare JavaScript with JavaScript and report agreement
  // whatever PCRE does - and PCRE is where they actually differed: its `$` also
  // matches before a final newline, so `LetterRule` accepted `Conference\n`
  // while this side refused it. That is what the `D` below is for, and a test
  // that emulated the server would never have shown it.
  const php = readFileSync(
    new URL('../../src/administrator/components/com_extengen/src/Rule/LetterRule.php', import.meta.url),
    'utf8'
  );

  const pattern = php.match(/\$regex\s*=\s*'([^']*)'/);
  const modifiers = php.match(/\$modifiers\s*=\s*'([^']*)'/);

  assert.ok(pattern, 'LetterRule declares a regex');
  assert.ok(modifiers, 'LetterRule declares its modifiers');

  assert.equal(pattern[1], LETTER_PATTERN, 'the two copies of the pattern have drifted apart');

  // `i` because the rule is case-insensitive on both sides; `D` because PCRE
  // needs telling to mean by `$` what JavaScript already means by it. Pinned,
  // so that dropping either has to be a decision rather than an edit.
  assert.equal(modifiers[1], 'iD', 'the server rule no longer matches the whole value, case-insensitively');
});
