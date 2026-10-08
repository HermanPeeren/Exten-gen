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
 *
 * **It installs through the web server, with Exten-gen's own Install button.**
 * It used Joomla's CLI, and that made this spec fail now and then in CI with a
 * bare 500 on the first visit: the CLI is another process, Joomla rewrites its
 * namespace map on install and clears the opcode cache for it - in the process
 * that wrote it, which was not the server. For a moment the server ran the old
 * map, without the new component in it. Installed in the server, the cache is
 * cleared where it is used, as on a real site, and the Install button gets the
 * end-to-end test it did not have.
 */

const COMPONENT = 'com_balloonplanning';
const VIEW = 'flights';
const MENU_ALIAS = 'flights';

describe('a generated component', () => {
  after(() => {
    cy.exec('php tools/set-option.php allow_install 0');
  });

  before(() => {
    cy.exec('php tools/set-option.php allow_install 1');

    // Generate through the component, as a person would - the BalloonPlanning
    // project by name, because that is the component the rest of this checks.
    cy.visitExtengen('projects&filter[metalanguage]=&list[limit]=0');
    cy.contains('#extengenProjects tr', 'BalloonPlanning').find('a[data-href*="view=generate"]').then(($link) => {
      cy.visit(`${$link.attr('data-href')}&generator=joomla6`);
    });

    cy.get('#generate-result', { timeout: 60000 }).should('contain.text', '.zip');

    // And install what it produced, on this site, with the button the result
    // offers once the option allows it. Its confirmation is answered yes.
    cy.on('window:confirm', () => true);
    cy.get('#generate-install').click();

    cy.get('#system-message-container', { timeout: 120000 }).should('contain.text', 'was installed on this site');
    cy.exec('php tools/set-option.php allow_install 0');

    // A menu item for one of its views, which only writes to the database.
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

  /**
   * The menu links to it, which needs the component's router.
   *
   * The tests above type the address. A visitor follows the menu, and until
   * the generator wrote a site router the menu could not build that link:
   * the component says it routes its own links, Joomla's router factory
   * found no router class, `Route::_()` returned null, and the menu entry
   * pointed at the home page - with a PHP deprecation from mod_menu above
   * it, the only sign anything was wrong.
   */
  it('is reached from its menu item, as a visitor reaches it', () => {
    cy.visit('/');

    cy.contains('a', 'Flights').should('have.attr', 'href').and('match', new RegExp(`/${MENU_ALIAS}$`));
    cy.get('body').should('not.contain', 'Deprecated');

    cy.contains('a', 'Flights').click();
    cy.location('pathname').should('match', new RegExp(`/${MENU_ALIAS}$`));
    cy.get(`#${VIEW}List`).should('exist');
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
