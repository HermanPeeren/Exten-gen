/**
 * Importing a metalanguage, and writing a project in one: step 3.4.
 *
 * The unit suite installs a package into a temp directory and checks what came
 * out. It cannot see any of this: the route, the upload, the token, the
 * screen, the dropdown on a project, or whether a project bound to an imported
 * language opens with that language's forms rather than ER1's. That last one
 * is the step's own acceptance criterion, and no other gate reaches it.
 *
 * The package is built by `tools/make-test-package.php` rather than committed
 * or exported from Meta-gen - see that file for why.
 */

const PACKAGE = 'tests/cypress/fixtures/metalanguage.zip';

/**
 * The project the Testlang tests below make, by id.
 *
 * By id and not by name, because this site accumulates them: every run leaves
 * another `WrittenInTestlang` behind, and the oldest ones date from before a
 * binding was stored at all - so the install script stamped them ER1. Picking
 * "the row that says WrittenInTestlang" found one of those, which is written in
 * ER1, which generates, which is the opposite of what the test below is for.
 */
let testlangProject = null;

describe('metalanguages', () => {
  before(() => {
    // Built fresh, so a spec cannot pass against a package left over from a
    // format two changes ago.
    // cy.exec already fails the run on a non-zero exit; what is asserted here
    // is that a package actually landed, because a tool that writes nothing
    // and exits 0 would otherwise leave every test below failing for the
    // wrong reason.
    cy.exec('php tools/make-test-package.php');
    cy.readFile(PACKAGE, null).should((buffer) => {
      expect(buffer.length, 'the package has bytes in it').to.be.greaterThan(200);
    });
  });

  beforeEach(() => {
    cy.loginToAdmin();
  });

  it('renders, with ER1 in the list as an imported language', () => {
    cy.visit('/administrator/index.php?option=com_extengen&view=metalanguages');

    cy.get('#metalanguageList', { timeout: 20000 }).should('exist');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'View not found');

    // 3.4 put ER1 in this list as the language the component shipped, with a
    // "Built in" badge beside it. 3.5 modelled it in LionCore M3 and it ships
    // as a package now, installed on update like any import - so the badge is
    // gone and the row is an ordinary one. That the row looks like every other
    // row is the step having landed, not a detail.
    cy.get('#metalanguageList').should('contain.text', 'ER1');
    cy.get('#metalanguageList').should('not.contain.text', 'Built in');

    // And it says what a project written in it opens at.
    cy.get('#metalanguageList').should('contain.text', 'Project');
  });

  it('imports a package and lists what came out of it', () => {
    cy.visit('/administrator/index.php?option=com_extengen&view=metalanguages');

    cy.get('input[name="package"]').selectFile(PACKAGE);
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'Testlang');

    cy.get('#metalanguageList').should('contain.text', 'Testlang');

    // The root classifier, which is what a project in it opens at.
    cy.get('#metalanguageList').should('contain.text', 'Thing');
  });

  it('refuses something that is not a package, and says so', () => {
    cy.visit('/administrator/index.php?option=com_extengen&view=metalanguages');

    cy.get('input[name="package"]').selectFile({
      contents: Cypress.Buffer.from('holiday photos'),
      fileName: 'notapackage.zip',
      mimeType: 'application/zip',
    });
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 20000 }).should('exist');
    cy.get('#metalanguageList').should('not.contain.text', 'notapackage');
  });

  it('offers the imported language when a project is created', () => {
    cy.visit('/administrator/index.php?option=com_extengen&view=project&layout=edit&id=0');

    cy.get('#jform_metalanguage', { timeout: 20000 }).should('exist');
    cy.get('#jform_metalanguage option').then(($options) => {
      const texts = [...$options].map((o) => o.textContent.trim());

      expect(texts, 'the built-in is offered').to.include('ER1 1.0');
      expect(texts, 'and the imported one').to.include('Testlang 1.0');
    });
  });

  /**
   * An existing project cannot be moved to another language.
   *
   * The binding decides which forms open the project, so changing it under a
   * model that is already stored means the forms on screen stop describing
   * what is in the database - fields that post nothing, and a save that drops
   * whatever the new forms have no field for.
   */
  it('does not let an existing project change language', () => {
    // Opened from the list, which is how the other specs reach a saved
    // project: a direct visit with an id does not check the record out, and a
    // spec that made up a url would keep passing after the link beside it
    // broke.
    cy.visit('/administrator/index.php?option=com_extengen&view=project&layout=edit&id=0');

    cy.visitExtengen('projects');
    cy.get('#adminForm a[href*="task=project.edit"]').first().click();

    cy.get('#jform_metalanguage', { timeout: 20000 })
      .should('satisfy', ($el) => $el.is(':disabled') || $el.attr('readonly') !== undefined);
  });

  /**
   * The step's own acceptance criterion: *Exten-gen edits a project through
   * imported forms rather than through forms it ships*.
   *
   * A project bound to Testlang has Testlang's fields on it - `thingName` and
   * a repeating `parts` - and none of ER1's. Nothing in this component knows
   * those names: they come out of the package, through a root form the
   * manifest named, merged onto the Joomla half.
   *
   * The labels are the other half of it. They are defined only in the
   * package's own language file, so reading "What this thing is called" rather
   * than YEPR_TESTLANG_THING_FIELD_THINGNAME_LABEL says that file was found
   * and loaded from the path the manifest gave.
   */
  it('edits a project through the imported language rather than ER1', () => {
    cy.visit('/administrator/index.php?option=com_extengen&view=project&layout=edit&id=0');

    cy.get('#jform_name', { timeout: 20000 }).clear();
    cy.get('#jform_name').type('WrittenInTestlang');
    cy.get('#jform_metalanguage').select('Testlang 1.0');

    cy.window().then((w) => w.Joomla.submitbutton('project.apply'));

    // Testlang's own fields, which this component has never heard of.
    cy.get('#jform_thingName', { timeout: 30000 }).should('exist');

    // Which project this made, for the test after next. Non-zero deliberately:
    // the url before the save says id=0, and reading it too early captured
    // that and then looked for a generate button for project nought.
    cy.url().should('match', /[?&]id=[1-9]\d*/).then((url) => {
      testlangProject = Number(url.match(/[?&]id=(\d+)/)[1]);
    });
    cy.get('#jform_parts-lbl').should('exist');

    // And its own strings, from the package's language file.
    cy.get('#jform_thingName-lbl').should('contain.text', 'What this thing is called');

    // ER1's fields are not on this project at all.
    cy.get('#jform_datamodel-lbl').should('not.exist');
  });

  /**
   * ...and refuses to generate from it: step 3.6.
   *
   * A project bound to Testlang is one this component can open, edit and save
   * perfectly well - the test above is that - and cannot generate from. The
   * rule file and `Joomla6Derivations` are about ER1 by name, and a selector
   * following a reference needs a reference table this project's language does
   * not share.
   *
   * Running anyway is the failure worth preventing, and it is a quiet one:
   * every selector returns nothing, every rule fires zero times, and out comes
   * a zip with a manifest and almost no files. Nothing about it looks wrong
   * until somebody installs it.
   *
   * Only reachable here. The golden tests call the generators directly with a
   * model they were handed, so there is no project, no binding and no database
   * row to disagree with.
   */
  it('refuses to generate from a project written in another language', () => {
    expect(testlangProject, 'the Testlang project was created above').to.be.a('number');

    // Every run of the spec above leaves another project behind, so this site
    // has more of them than a page holds and the newest - the only one written
    // in Testlang - is not on the first one.
    cy.visit('/administrator/index.php?option=com_extengen&view=projects&list[limit]=0');

    // The link beside that project, rather than a url made up here: the modal
    // carries it in data-href, and a spec that built its own would keep passing
    // after the button next to it broke.
    cy.get(`#adminForm a[data-href$="project_id=${testlangProject}"]`)
      .then(($link) => {
        // The refusal is an exception, so Joomla answers 500 and Cypress would
        // fail the visit before anything could be read off the page.
        cy.visit($link.attr('data-href'), { failOnStatusCode: false });
      });

    // What it says, not merely that it stopped. A refusal nobody can act on is
    // the same dead end as the empty package, one screen earlier.
    cy.get('body', { timeout: 60000 }).should('contain.text', 'Testlang');
    cy.get('body').should('contain.text', 'written for ER1');

    // And it stopped before writing: no file count, no package.
    cy.get('body').should('not.contain.text', '.zip');
  });

  /**
   * The project this site already has still opens, through ER1.
   *
   * Every project made before 3.4 carries no binding at all, and reading that
   * as "no language" would have made all of them unopenable on the day this
   * shipped. It reads as ER1, which is what they are written in - and the
   * model half now arrives as project_er1.xml, merged onto the chrome.
   */
  it('still opens a project that names no language', () => {
    cy.visitExtengen('projects');
    cy.get('#adminForm a[href*="task=project.edit"]').first().click();

    cy.get('body').should('not.contain', 'Fatal error');

    // The label, not the field: `datamodel` is a repeating subform, so what
    // carries the bare name is `jform_datamodel-lbl` and the rows underneath
    // are `jform_datamodel__...`. A project with an empty model has the label
    // and no rows, which is the case this test is about.
    cy.get('#jform_datamodel-lbl', { timeout: 20000 }).should('exist');
  });
});
