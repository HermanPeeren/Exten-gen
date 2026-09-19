/**
 * A generated component's front end, installed and visited.
 *
 * This is the one spec that leaves Exten-gen entirely. It generates a
 * component, installs that component onto the same Joomla, points a menu item
 * at one of its site views, and looks at the page a visitor would see.
 *
 * Until 1.12 nothing did. The site part was generated and the generated
 * manifest did not install it, so the output could be - and was - broken in
 * five separate ways at once, each of which only appeared once the one before
 * it was fixed:
 *
 *   - the manifest's front-end <files> block was commented out
 *   - no tmpl/<view>/default.xml, so no view could be put on a menu
 *   - the layout asked the asset manager for `com_x.admin`, an administrator
 *     asset that no generated component declares, which Joomla throws for
 *   - it rendered Joomla's search tools, which read a filter form a front-end
 *     list model does not build
 *   - its language strings were registered into the administrator language
 *     file, so the site showed the keys
 *
 * None of that is visible to the golden files: the bytes were stable the whole
 * time, and stably wrong.
 */

const COMPONENT = 'com_balloonplanning';
const VIEW = 'flights';
const MENU_ALIAS = 'flights';

describe('a generated component', () => {
  before(() => {
    // Generate through the component, as a person would.
    cy.visitExtengen('projects');
    cy.get('#adminForm a[data-href*="view=generate"]').first().then(($link) => {
      cy.visit($link.attr('data-href'));
    });

    cy.get('body', { timeout: 60000 }).should('contain.text', '.zip');

    // Install what it produced, and give one of its views a menu item. Both
    // through Joomla's CLI, which needs no login.
    cy.exec('php tools/install-generated.php BalloonPlanning', { timeout: 120000 })
      .its('exitCode')
      .should('eq', 0);

    cy.exec(`php tools/seed-menu-item.php ${COMPONENT} ${VIEW} Flights`, { timeout: 60000 })
      .its('exitCode')
      .should('eq', 0);
  });

  it('installs its site files', () => {
    cy.exec(`php -r "echo is_dir('joomla/components/${COMPONENT}') ? 'yes' : 'no';"`)
      .its('stdout')
      .should('eq', 'yes');
  });

  it('serves its list view to a visitor', () => {
    cy.visit(`/index.php/${MENU_ALIAS}`);

    cy.get(`#${VIEW}List`).should('exist');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'Call to a member function');
    cy.get('body').should('not.contain', 'There is no ');
  });

  it('shows translated column headings rather than language keys', () => {
    cy.visit(`/index.php/${MENU_ALIAS}`);

    cy.get(`#${VIEW}List thead th`).should('have.length.greaterThan', 0);

    // The strings a site layout uses have to be registered into the *site*
    // language file. Registered into the administrator's, which is where they
    // went, the page shows COM_BALLOONPLANNING_TABLE_... to every visitor.
    cy.get(`#${VIEW}List thead`).should('not.contain', 'COM_');
  });

  it('does not put administrator controls on a public page', () => {
    cy.visit(`/index.php/${MENU_ALIAS}`);

    cy.get(`#${VIEW}List input[name="checkall-toggle"]`).should('not.exist');
    cy.get(`#${VIEW}List a[href*="task="]`).should('not.exist');
  });
});
