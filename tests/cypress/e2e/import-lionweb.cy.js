/**
 * Reading a model written in LionWeb in as a project, on a real site.
 *
 * The conversion is pinned in the library, against a language small enough to
 * read, and separately against JCB's real one by a script. Neither of those
 * boots Joomla. Everything between the button and the converter is untested by
 * them: the route, the token, the controller task, whether the path field
 * reaches the model, whether the language is found by a key spelled
 * differently from the chunk's, whether a row is stored, and whether what
 * comes back is a project the edit screen can open.
 *
 * The chunk is written here rather than committed, for the same reason
 * Meta-gen's is: the point of the path field is that it reads a file another
 * component left on the same site, so the spec leaves one where JcbInOut
 * would.
 */

/**
 * A model as LionWeb serialises one, in a language this site has.
 *
 * Deliberately not JCB's blueprint: 53 nodes against a 126-concept language
 * proves the converter and tells you nothing about the screen. Testlang is
 * already installed by the metalanguages spec's fixtures, and one concept with
 * one property is enough to show a model arriving.
 */
const chunk = (language, version, concept, property, value) => ({
  serializationFormatVersion: '2024.1',
  languages: [{ key: language, version }],
  nodes: [{
    id: 'imported-thing',
    classifier: { language, version, key: concept },
    properties: [{
      property: { language, version, key: property },
      value,
    }],
    containments: [],
    references: [],
    annotations: [],
    parent: null,
  }],
});

const chunkPath = 'media/cypress-model.json';

/**
 * Open the import panel.
 *
 * It is a `<details>`, open only when JcbInOut has left a blueprint on this
 * site - which on this site it has not. Clicking it the way a person would,
 * rather than reaching around it: a spec that types into a hidden input would
 * keep passing if the panel stopped opening. Opened only when closed, because
 * clicking an open one shuts it - the trap the Meta-gen spec fell into.
 */
const openImportPanel = () => {
  cy.contains('summary', 'Import LionWeb model').then(($summary) => {
    if (!$summary.closest('details').prop('open')) {
      cy.wrap($summary).click();
    }
  });

  cy.get('#chunk').should('be.visible');
};

const messages = () => cy.get('#system-message-container', { timeout: 30000 }).invoke('text');

describe('importing a project from LionWeb', () => {
  beforeEach(() => {
    cy.loginToAdmin();
    cy.visitExtengen('projects');
  });

  it('offers the import, with the path JcbInOut would write to', () => {
    cy.shouldHaveRendered();

    cy.get('#toolbar').contains('Import LionWeb model').should('be.visible');

    openImportPanel();
    cy.get('#chunk')
      .invoke('attr', 'placeholder')
      .should('contain', 'com_jcbinout')
      .and('contain', 'blueprint.instance.lionweb.json');
  });

  // -- when it cannot -------------------------------------------------------

  it('says so when the path is empty', () => {
    openImportPanel();
    cy.get('#chunk').clear();
    cy.get('#toolbar').contains('Import LionWeb model').click();

    messages().should('contain', 'Give the path of a LionWeb chunk');
  });

  it('says so when there is no file there', () => {
    openImportPanel();
    cy.get('#chunk').clear().type('media/there-is-no-such-file.json');
    cy.get('#toolbar').contains('Import LionWeb model').click();

    messages().should('contain', 'There is no file at');
  });

  /**
   * The field reads a file under the site and nothing else. An administrator
   * can reach the filesystem by other means, so this is not a wall - but a
   * field that reads any path the web server can is a worse habit than one
   * that does not.
   */
  it('refuses a path outside the site', () => {
    openImportPanel();
    cy.get('#chunk').clear().type('../../../etc/hosts');
    cy.get('#toolbar').contains('Import LionWeb model').click();

    messages().should('match', /has to be a file under this site|There is no file at/);
  });

  /**
   * A model in a language this site does not have is the mistake worth
   * catching. Read against the wrong language it would not fail - it would
   * quietly produce fields that are not there - so it has to be refused by
   * name.
   */
  it('refuses a model in a language that is not imported', () => {
    cy.writeFile(
      `joomla/${chunkPath}`,
      chunk('NoSuchLanguage', '9.9', 'thing', 'thing-k-name', 'orphan')
    );

    openImportPanel();
    cy.get('#chunk').clear().type(chunkPath);
    cy.get('#toolbar').contains('Import LionWeb model').click();

    messages()
      .should('contain', 'NoSuchLanguage')
      .and('contain', 'not imported on this site');
  });

  // -- and back out ---------------------------------------------------------

  /**
   * The export needs a project chosen, unlike the import, which makes one.
   *
   * What the download contains is checked where it can be: written back out
   * and compared against the chunk it was imported from, node by node. What
   * cannot be checked there is that the button is on screen and that it will
   * not fire without a project - which `listCheck` does by disabling it, so
   * nothing is ever asked to export nothing.
   */
  it('offers the export, and will not fire without a project', () => {
    cy.get('#toolbar').contains('Export LionWeb model').should('be.disabled');

    cy.get('#extengenProjects tbody input[type="checkbox"]').first().check();

    cy.get('#toolbar').contains('Export LionWeb model').should('be.enabled');
  });

  it('says so when the chunk holds no model', () => {
    cy.writeFile(`joomla/${chunkPath}`, {
      serializationFormatVersion: '2024.1',
      languages: [{ key: 'Testlang', version: '1.0' }],
      nodes: [],
    });

    openImportPanel();
    cy.get('#chunk').clear().type(chunkPath);
    cy.get('#toolbar').contains('Import LionWeb model').click();

    messages().should('match', /holds no model|does not say which language/);
  });
});
