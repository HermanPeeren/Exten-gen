/**
 * A saved project opens with its model on the screen, and still has it after.
 *
 * Two defects, one cause: the component knew which project it was looking at
 * only on the request that renders, and only after Joomla had stopped
 * listening.
 *
 * **Rendering.** `loadForm()` binds the stored data *inside itself*, and the
 * metalanguage's forms were merged in after it returned. So when Joomla looked
 * for somewhere to put `datamodel`, `pages` and `extensions`, the form held
 * only `project_chrome.xml` and had no such fields. `Form::bindLevel()` does
 * not complain about that - a key it has no field for is treated as a group and
 * recursed into, and when nothing matches there either it returns. No warning,
 * no exception, nothing in the log. The screen showed a project's name and its
 * language and no model at all.
 *
 * **Saving.** Worse, and not the same fix. `FormController` builds the model
 * with `ignore_request => true`, which suppresses `populateState()` - so on the
 * one request that writes to the database, `getItem()` came back empty and the
 * merge was skipped for a different reason. The validation form had no model
 * half, `Form::filter()` dropped everything it did not recognise, and `save()`
 * serialised what was left. A project went from 18941 bytes to 63.
 *
 * The only thing standing between that and ordinary use was `component_name`
 * being required: Joomla refused the empty form. Filling in the one field it
 * had marked red - the obvious thing to do - completed the save and destroyed
 * the model.
 *
 * The unit suite cannot see any of this. It reads models off disk and never
 * builds a Joomla `Form`; both defects live entirely in when two framework
 * calls happen relative to a third. So this belongs here, and it is the note at
 * the top of CLAUDE.md being right again.
 *
 * Needs the conference project on the site, which `beforeEach` sees to:
 *
 *   php tools/seed-project.php conference --saved
 */

const PROJECT = 'MyConference';

// What the fixture holds, so the assertions below are about this model and not
// about "some rows appeared".
const ENTITIES = ['Speaker', 'Talk', 'Room', 'Program'];

/**
 * Put the project back the way the fixture has it, before every test.
 *
 * Not tidiness. The last test in this file saves the project, and while the
 * defect was live that save destroyed it - so a second run of this spec was
 * measuring the wreckage of the first and reporting a different set of
 * failures. A spec about data loss has to own its data.
 */
beforeEach(() => {
  cy.exec('php tools/seed-project.php conference --saved');
});

const openTheProject = () => {
  cy.visitExtengen('projects');
  cy.shouldHaveRendered();

  cy.get('#adminForm')
    .contains('a[href*="task=project.edit"]', PROJECT)
    .should('exist', `the ${PROJECT} project is on the site - run tools/seed-project.php conference --saved`)
    .click();

  cy.get('#project-form', { timeout: 20000 }).should('exist');
};

/**
 * The entity names the form is actually showing.
 *
 * Read off the live inputs, not out of the row templates: a template is what
 * Joomla clones and proves nothing about whether anything was bound. That
 * distinction is the whole subject of this file.
 */
const entityNamesOnScreen = (doc) =>
  [...doc.querySelectorAll('#project-form [name^="jform[datamodel]"][name$="[entity_name]"]')]
    .map((input) => input.value)
    .filter((value) => value !== '');

describe('a saved project opens with its model', () => {
  it('renders a row per entity', () => {
    openTheProject();

    cy.document().then((doc) => {
      const names = entityNamesOnScreen(doc);

      expect(names, 'the entities of the stored model').to.have.members(ENTITIES);
    });
  });

  it('renders the fields inside those entities', () => {
    openTheProject();

    cy.document().then((doc) => {
      // Nested one level deeper, and bound through a second subform - which is
      // the part that would still be empty if only the outer level were fixed.
      const fields = [
        ...doc.querySelectorAll('#project-form [name^="jform[datamodel]"][name*="[field]"][name$="[field_name]"]'),
      ].filter((input) => input.value !== '');

      expect(fields.length, 'fields are on the screen too').to.be.greaterThan(5);
    });
  });

  it('renders the half that is not repeatable either', () => {
    openTheProject();

    // `extensions` is a plain subform rather than a repeatable one, and it was
    // just as unbound. Worth its own assertion because the two go through
    // different Joomla layouts and only one of them was ever looked at.
    cy.get('#project-form [name="jform[extensions][component][component_name]"]').should(
      'have.value',
      'Conference'
    );
  });

  /**
   * And the one that matters: opening and saving does not destroy the model.
   *
   * The consequence rather than the cause, and the reason this was worth
   * stopping for. It is also the half that survived the first fix: with the
   * render path repaired the screen looked completely normal, 216 inputs and
   * all, and saving still wrote four keys over the project - because the save
   * request builds its own model and that one still could not tell which
   * record it was.
   *
   * Asserted by reopening rather than by reading the database, because what is
   * claimed is that the project is still usable, not that some bytes are still
   * somewhere.
   */
  it('still has its model after being opened and saved', () => {
    openTheProject();

    // A marker on the window, so the wait below is for *this* document to be
    // replaced rather than for a selector that exists on both pages. Without
    // it the assertions ran against the page that had not navigated yet, and
    // the test passed while the save was destroying the project behind it.
    cy.window().then((win) => {
      win.__beforeSave = true;
      win.Joomla.submitbutton('project.apply');
    });

    cy.window({ timeout: 20000 }).should((win) => {
      expect(win.__beforeSave, 'the save has reloaded the page').to.be.undefined;
    });

    cy.get('body').should('not.contain', 'Fatal error');

    // And read it back from a fresh request, not from the form that was just
    // posted. What is being claimed is that the *stored* project is intact.
    openTheProject();

    cy.document().then((doc) => {
      const names = entityNamesOnScreen(doc);

      expect(names, 'the model survived the round trip').to.have.members(ENTITIES);
    });
  });
});
