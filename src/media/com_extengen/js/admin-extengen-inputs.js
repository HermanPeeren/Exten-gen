/**
 * @copyright  Copyright (C) Yepr, Herman Peeren. All rights reserved.
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 */

/**
 * Tell the server how many inputs the project form is about to post.
 *
 * PHP drops everything past `max_input_vars` without a word, and a model
 * imported from JCB puts some 24,000 inputs on this screen. The server notices
 * a cut-short post by a field the layout puts last (`PostLimit::SENTINEL`);
 * what it cannot know is how many inputs there were, because those are exactly
 * the ones it never received. So the number is counted here, at the moment of
 * submitting - subform rows may have been added since the page loaded - and
 * written into a field near the top of the form, which survives.
 *
 * The count is what a form serialises, not what the page holds: a disabled
 * input and an unchecked box post nothing, and PHP counts what is posted.
 *
 * @since  1.6.5
 */

/**
 * How many variables this form posts, the way PHP will count them.
 *
 * @param   {HTMLFormElement}  form
 *
 * @return  {number}
 */
export function countInputs(form) {
  return [...new FormData(form).keys()].length;
}

// Not in Node, where this file is imported for the count above.
if (typeof document !== 'undefined') {
  const attach = () => {
    const form = document.getElementById('project-form');
    const count = form ? form.querySelector('input[name="extengen_inputs"]') : null;

    if (count) {
      // Before Joomla's own submit handling reads the form, which is the only
      // moment the count is right.
      form.addEventListener('submit', () => {
        count.value = String(countInputs(form));
      }, true);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', attach);
  } else {
    attach();
  }
}
