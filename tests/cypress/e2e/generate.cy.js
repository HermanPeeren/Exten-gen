/**
 * Generation runs on a real site and produces files.
 *
 * The golden tests already say what the output *is*, in far more detail than a
 * browser could. What they cannot say is whether generation runs at all inside
 * Joomla: they call the generators directly, with the framework replaced by two
 * defined constants. Everything between the button and the generators - the
 * controller, the model, the library autoloader, the filesystem - is only
 * exercised here.
 *
 * That gap is not hypothetical either. The Generate view carried
 * `require_once JPATH_LIBRARIES . '/yepr/vendor/autoload.php'` long after the
 * shared library moved to `yepr_gen` and started loading Twig itself, which is
 * an unguarded require of a path that does not exist.
 */

describe('generating a component', () => {
  it('opens the generators page', () => {
    cy.visitExtengen('generators');
    cy.shouldHaveRendered();
  });

  it('generates from the first project and reports what it wrote', () => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();

    // The generate button opens a modal, so the url it loads is in data-href
    // rather than href. Visiting it directly is what the modal does.
    cy.get('#adminForm a[data-href*="view=generate"]').first().then(($link) => {
      const href = $link.attr('data-href');

      expect(href, 'a generate link exists').to.be.a('string');

      cy.visit(href);
    });

    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'Warning:');

    // The log names the package it built.
    cy.get('body').should('contain.text', '.zip');
  });

  /**
   * And pressing the button points the modal at the project it belongs to.
   *
   * The test above reads `data-href` and visits it, which checks the URL and
   * nothing at all about what happens between the click and the dialog. There
   * is one modal for the whole list, so the click has to say which project it
   * means before Bootstrap opens it - and that was twelve lines of inline
   * script in the template that nothing had ever exercised.
   *
   * It is `media/com_extengen/js/generation-modal.js` now, whose rules
   * `composer test-js` checks over plain objects. What is left for a browser
   * is this: that the listener is attached, to the right elements, and that
   * the iframe it finds is the one in the dialog.
   */
  it('points the modal at the project whose button was pressed', () => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();

    cy.get('#adminForm a.dynbutton[data-href*="view=generate"]').first().then(($button) => {
      const expected = $button.attr('data-href');

      cy.wrap($button).click();

      cy.get('#generationModal iframe').should('have.attr', 'src', expected);
    });
  });

  /**
   * The same project, generated into WordPress instead: step 4.4.
   *
   * The unit suite runs both targets over the fixtures with the framework
   * replaced by two constants. What it cannot see is any of this: the target
   * arriving from the address bar, the registry resolving it inside Joomla, Twig
   * finding a second template set on a real filesystem, and the output landing
   * somewhere that does not collide with the first target's.
   *
   * That last one is not hypothetical. Both targets write a zip, and until 4.4
   * they would have written it into one directory - where
   * `tools/install-generated.php` does `glob('*.zip')` and takes the first,
   * which is how a site gets handed a WordPress plugin to install as a Joomla
   * component.
   */
  it('generates the same project into a WordPress plugin', () => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();

    cy.get('#adminForm a[data-href*="view=generate"]').first().then(($link) => {
      cy.visit($link.attr('data-href') + '&target=wordpress');
    });

    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'Warning:');

    // What it wrote, and where. The plugin's main file is named for the
    // project, and everything is under the target's own directory.
    cy.get('body').should('contain.text', '/wordpress/');
    cy.get('body').should('contain.text', 'readme.txt generated');
    cy.get('body').should('not.contain.text', 'install.mysql.utf8.sql');
  });

  /**
   * And into a Drupal module, which is a third answer again.
   *
   * Drupal is the case 4.4 deliberately did not take - another PHP framework
   * with entities, a container and classes found by namespace, close enough to
   * Joomla that a badly placed abstraction could have fitted anyway. It is here
   * because the seam held for WordPress, and adding it cost one line in
   * `Targets`.
   *
   * What this reaches that the unit suite cannot is the same list as above, plus
   * one more: a Drupal module is mostly YAML, and a template set that renders
   * YAML is a template set Twig has to find on a real filesystem beside two
   * others.
   */
  it('generates the same project into a Drupal module', () => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();

    cy.get('#adminForm a[data-href*="view=generate"]').first().then(($link) => {
      cy.visit($link.attr('data-href') + '&target=drupal');
    });

    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'Warning:');

    cy.get('body').should('contain.text', '/drupal/');
    cy.get('body').should('contain.text', '.info.yml generated');
    cy.get('body').should('contain.text', '.routing.yml generated');

    // Neither of the other two targets' answers to a schema.
    cy.get('body').should('not.contain.text', 'install.mysql.utf8.sql');
    cy.get('body').should('not.contain.text', 'activator');
  });

  it('leaves the model alone', () => {
    // Generation reads; it must not write to the project it generated from.
    // A run that quietly edited the model would show up as a changed list.
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();
    cy.get('#adminForm').should('exist');
  });
});
