/**
 * Generators: listed, imported, chosen, downloaded, installed - steps 5.4 to 5.6.
 *
 * `ImportedGeneratorTest` reads a package and runs its rules with the
 * framework replaced by two constants. This is the rest: the upload, the
 * token, the table, the chooser in the modal, the download a person clicks,
 * and the install button that must only be there when an administrator has
 * asked for it.
 *
 * The packages come from `tools/make-test-generator.php`. The good one is this
 * component's own rules without the LICENSE rule, which makes "the imported
 * rules are what ran" visible in the log: one file fewer, and that file.
 */

const PACKAGE = 'tests/cypress/fixtures/generator.zip';
const BROKEN = 'tests/cypress/fixtures/broken-generator.zip';
const IMPORTED = 'imported.joomla-6-without-a-licence-file';

/**
 * The generate link of the first ER1 project in the list, as the modal gets it.
 */
const generateLinkOfAnEr1Project = () => {
  cy.visit('/administrator/index.php?option=com_extengen&view=projects&filter[metalanguage]=&list[limit]=0');

  return cy.get('#extengenProjects td.project-metalanguage[data-binding^="ER1|"]').first()
    .closest('tr').find('a[data-href*="view=generate"]').invoke('attr', 'data-href');
};

/**
 * How many rules the good package holds, as the tool that built it says.
 *
 * Read rather than written here: it is the target's rule count minus one, and
 * the target gains rules - the number in this spec went stale the day it
 * gained its site router.
 */
let packageRules = 0;

describe('generators', () => {
  before(() => {
    cy.exec('php tools/make-test-generator.php').its('stdout').then((out) => {
      packageRules = Number((out.match(/^generator\.zip: (\d+) rules$/m) || [])[1]);

      expect(packageRules, 'the tool says how many rules it packed').to.be.greaterThan(0);
    });
    cy.exec('php tools/set-option.php allow_install 0');
  });

  after(() => {
    cy.exec('php tools/set-option.php allow_install 0');
  });

  beforeEach(() => {
    cy.loginToAdmin();
  });

  it('lists the generator each target has built in', () => {
    cy.visitExtengen('generators');
    cy.shouldHaveRendered();
    cy.shouldShowNoRawConstants();

    ['joomla6', 'wordpress', 'drupal'].forEach((id) => {
      cy.get(`#generatorList tr[data-id="${id}"]`).should('contain.text', 'Built in').and('contain.text', 'ER1');
    });
  });

  it('refuses a package whose rules name a template the target has not got', () => {
    cy.visitExtengen('generators');
    cy.get('input[name="package"]').selectFile(BROKEN);
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'is not a template of joomla6');
    cy.get('#generatorList').should('not.contain.text', 'Broken generator');
  });

  it('imports a package from Gen-gen and lists it', () => {
    cy.visitExtengen('generators');
    cy.get('input[name="package"]').selectFile(PACKAGE);
    cy.get('button[type="submit"]').click();

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', `${packageRules} rules`);
    cy.get(`#generatorList tr[data-id="${IMPORTED}"]`)
      .should('contain.text', 'Joomla 6 without a licence file')
      .and('contain.text', 'Joomla component')
      .and('contain.text', String(packageRules));
  });

  it('offers the imported generator for an ER1 project, and runs its rules', () => {
    generateLinkOfAnEr1Project().then((href) => {
      cy.visit(href);
      cy.get(`#generate-choose-form input[value="${IMPORTED}"]`).check();
      cy.get('#generate-run').click();

      cy.get('#generate-result', { timeout: 60000 }).should('contain.text', 'files');
      cy.get('#generate-result').should('contain.text', 'Joomla 6 without a licence file');
      cy.get('#generate-result').should('not.contain.text', 'LICENSE.txt');
      cy.get('#generate-download').should('exist');

      // The built-in one, for comparison, writes the file the rule is for.
      cy.visit(`${href}&generator=joomla6`);
      cy.get('#generate-result', { timeout: 60000 }).should('contain.text', 'LICENSE.txt');
    });
  });

  it('does not offer to install unless the options allow it, and refuses when asked anyway', () => {
    generateLinkOfAnEr1Project().then((href) => {
      cy.visit(`${href}&generator=joomla6`);
      cy.get('#generate-install').should('not.exist');

      // The task checks for itself. A button that is not shown is not a check.
      cy.get('#generate-download').invoke('attr', 'href').sitePath().then((download) => {
        cy.request({ url: download.replace('generate.download', 'generate.install'), failOnStatusCode: false })
          .its('status').should('eq', 403);
      });
    });
  });

  it('offers to install a Joomla extension once the options allow it, and only that', () => {
    cy.exec('php tools/set-option.php allow_install 1');

    generateLinkOfAnEr1Project().then((href) => {
      cy.visit(`${href}&generator=joomla6`);
      cy.get('#generate-install', { timeout: 60000 }).should('exist');

      cy.visit(`${href}&generator=wordpress`);
      cy.get('#generate-result', { timeout: 60000 }).should('contain.text', 'files');
      cy.get('#generate-install').should('not.exist');
    });

    cy.exec('php tools/set-option.php allow_install 0');
  });

  it('forgets an imported generator', () => {
    cy.visitExtengen('generators');
    // Visited rather than clicked: in the test's viewport the administrator's
    // sticky toolbar lies over the button, which is Cypress's problem and not
    // a person's.
    cy.get(`#generatorList tr[data-id="${IMPORTED}"] a.btn-danger`).invoke('attr', 'href').sitePath()
      .then((href) => cy.visit(href));

    cy.get('#system-message-container', { timeout: 30000 }).should('contain.text', 'no longer offered');
    cy.get(`#generatorList tr[data-id="${IMPORTED}"]`).should('not.exist');
  });
});
