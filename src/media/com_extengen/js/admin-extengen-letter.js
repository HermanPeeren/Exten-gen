/**
 * @copyright  Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 */

/**
 * A project's name must be letters and nothing else.
 *
 * It becomes the component name, the namespace segment, the table prefix and
 * every class name in the generated extension, so a digit or a space in it
 * does not produce a worse component - it produces one that does not load.
 * That is why the rule is this strict.
 *
 * **The same rule is written twice**, here and in `Rule/LetterRule.php`, which
 * is what the browser checks on the way out and what Joomla checks on the way
 * in. Two copies of one rule is how three separate defects got into this
 * repository, so `letter.test.mjs` reads the PHP and requires the two to
 * agree rather than trusting that they do.
 *
 * The predicate is exported for that test. The registration below it needs a
 * form and a validator and belongs to the browser.
 *
 * @since  1.4.0
 */

/**
 * The pattern itself, so that a test can compare it with the PHP one as text.
 *
 * Exported rather than inlined below because comparing the two rules by
 * running them is not possible: PCRE and JavaScript are different engines, and
 * a test that compiled the PHP pattern with `new RegExp` would be checking
 * JavaScript against JavaScript and calling it agreement.
 *
 * @type {string}
 */
export const LETTER_PATTERN = '^([a-z]+)$';

const LETTER = new RegExp(LETTER_PATTERN, 'i');

/**
 * Whether this value is letters, and at least one of them.
 *
 * @param   {string}  value  What was typed.
 *
 * @return  {boolean}
 */
export function isLetters(value) {
  return LETTER.test(value);
}

// Not in Node, where this file is imported for the rule above.
if (typeof document !== 'undefined') {
  const register = () => {
    // Joomla only puts the validator on a page that has a form to validate.
    if (document.formvalidator) {
      document.formvalidator.setHandler('letter', isLetters);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', register);
  } else {
    register();
  }
}
