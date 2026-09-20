/**
 * The 1.9 acceptance criterion: referring to something you have not saved yet.
 *
 * Before the rework, a reference dropdown was filled with `<option>` tags the
 * server rendered from the database, so an entity added a minute ago could not
 * be pointed at until the whole project had been saved. `<yepr-reference>`
 * merges the index in the page with what the form holds right now.
 *
 * Every other check on that mechanism is a unit test over plain data, which is
 * why they are fast and why they prove nothing about the browser. This is the
 * part that needs one.
 */

describe('reference fields', () => {
  beforeEach(() => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();
  });

  /**
   * Open a project for editing, creating one first on a site that has none.
   *
   * A freshly installed component has an empty projects table, so a spec that
   * assumed a row would fail on the one site where these checks matter most:
   * the clean one somebody just installed onto.
   */
  const openAProject = () => {
    cy.get('body').then(($body) => {
      const links = $body.find('#adminForm a[href*="task=project.edit"]');

      if (links.length > 0) {
        cy.wrap(links.first()).click();
      } else {
        // task=project.add is a POST target; this is the form it
        // redirects to.
        cy.visit('/administrator/index.php?option=com_extengen&view=project&layout=edit&id=0');
      }
    });

    cy.get('#project-form', { timeout: 20000 }).should('exist');
  };

  it('renders the custom element rather than a bare select', () => {
    openAProject();

    cy.get('yepr-reference').should('exist');
    cy.get('yepr-reference select').should('exist');
  });

  it('carries the reference index in the page', () => {
    openAProject();

    // One index for the whole form, put there by the view. Before the rework
    // each dropdown queried the database for the same answer.
    cy.window().then((win) => {
      const options = win.Joomla.getOptions('yepr.references');

      expect(options, 'the reference index is in the page').to.be.an('object');
      expect(options.index, 'it has an index').to.be.an('object');
      expect(options.types, 'and the type descriptors').to.be.an('object');
      expect(Object.keys(options.types)).to.include.members(['Entity', 'Page', 'Field']);
    });
  });

  /**
   * The acceptance criterion for step 1.9, end to end.
   *
   * Add an entity, add a page, and the page's entity dropdown offers the
   * entity - with no save anywhere in between. Before the rework the options
   * were `<option>` tags the server rendered from the database, so this was
   * impossible by construction: the entity did not exist as far as the server
   * was concerned.
   */
  it('offers an entity that has just been added and never saved', () => {
    openAProject();

    const name = `Balloon${Date.now()}`;

    // Add an entity and name it.
    cy.get('joomla-field-subform[name*="datamodel"]').first().within(() => {
      cy.get('button.group-add').first().click({ force: true });
    });

    cy.get('input.entityName').should('have.length.greaterThan', 0);
    cy.get('input.entityName').first().clear({ force: true }).type(name, { force: true }).blur();

    // A page already exists - the pages subform has min="1" - and inside it
    // sits the entity reference. Add a row to that.
    //
    // Forced, because the project form puts each section in a tab and this one
    // is not the open tab. What is under test is where the dropdown's options
    // come from, not whether an accordion opens.
    cy.get('joomla-field-subform[name*="entity_ref"]').first().within(() => {
      cy.get('button.group-add').first().click({ force: true });
    });

    // No save. The dropdown reads the form, not the database.
    cy.get('yepr-reference[type="Entity"] select', { timeout: 10000 })
      .first()
      .find('option')
      .should('contain.text', name);
  });

  it('keeps the value it already held', () => {
    // A dropdown whose options arrive late must not post an empty reference in
    // the meantime, which is why the server renders the held value as a
    // selected option before any script runs.
    openAProject();

    cy.get('yepr-reference select').each(($select) => {
      const backup = $select.attr('id') ? Cypress.$(`#${$select.attr('id')}_id`) : null;

      if (backup && backup.length && backup.val()) {
        expect($select.val(), 'the select agrees with its hidden backup').to.equal(backup.val());
      }
    });
  });
});
