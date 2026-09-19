/**
 * Logging in, and the one assertion every spec needs.
 */

/**
 * Sign in to the administrator, once per spec run.
 *
 * The credentials come from `cypress.env.json`, which is git-ignored. They are
 * never written into a spec: a login committed to a repository is a login
 * somebody will reuse somewhere that matters.
 *
 * `cy.session` caches the cookies, so the login form is filled in once rather
 * than before every test.
 */
Cypress.Commands.add('loginToAdmin', () => {
  const user = Cypress.env('adminUser');
  const password = Cypress.env('adminPassword');

  cy.session(
    ['joomla-admin', user],
    () => {
      cy.visit('/administrator/index.php');

      cy.get('#mod-login-username').type(user);
      cy.get('#mod-login-password').type(password, { log: false });
      cy.get('#btn-login-submit').click();

      cy.get('#sidebarmenu, .header', { timeout: 20000 }).should('exist');
    },
    {
      validate() {
        cy.request('/administrator/index.php').its('status').should('eq', 200);
      },
    },
  );
});

/**
 * Open one of the component's views.
 */
Cypress.Commands.add('visitExtengen', (view) => {
  cy.loginToAdmin();
  cy.visit(`/administrator/index.php?option=com_extengen&view=${view}`);
});

/**
 * The page rendered something, and Joomla is not showing an error instead.
 *
 * This is the assertion the blank projects list would have failed: the request
 * returned 200 with an empty body, so anything that only checked the status
 * would have passed.
 */
Cypress.Commands.add('shouldHaveRendered', () => {
  // Deliberately not "has a #j-main-container": six of this component's ten
  // views have no layout file at all and echo their markup from display(), so
  // a rule written for a list view would report those as broken when they are
  // merely different. What every view must do is arrive with something in it.
  cy.get('body').should('not.be.empty');
  cy.get('body').invoke('text').should((text) => {
    expect(text.trim().length, 'the page has content').to.be.greaterThan(200);
  });

  cy.get('body').should('not.contain', 'Fatal error');
  cy.get('body').should('not.contain', 'Class "');
  cy.get('body').should('not.contain', 'Warning:');
  cy.get('.alert-danger, #system-message-container .alert-error').should('not.exist');
});
