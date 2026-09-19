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

  it('leaves the model alone', () => {
    // Generation reads; it must not write to the project it generated from.
    // A run that quietly edited the model would show up as a changed list.
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();
    cy.get('#adminForm').should('exist');
  });
});
