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

/**
 * No language constant shows as itself: step 5.1.
 *
 * Joomla prints an undefined constant in capitals and logs nothing, so this
 * reads what a person would read - the visible text, menu included - rather
 * than the source. It is the only check that sees a constant built at run
 * time, or one that is in the `.ini` but not in the `.sys.ini` the menu reads.
 */
Cypress.Commands.add('shouldShowNoRawConstants', () => {
  cy.get('body').invoke('prop', 'innerText').should((text) => {
    const raw = [...new Set(text.match(/\b(?:COM|J|YEPR|LIB|PLG|MOD)[A-Z0-9]*_[A-Z0-9_]*[A-Z0-9]\b/g) || [])];

    expect(raw, 'constants shown untranslated').to.deep.equal([]);
  });
});

/**
 * A link the page built, as a path Cypress can visit or request.
 *
 * Joomla's routed links already carry the site's own path
 * (`/Exten-gen/joomla/administrator/...`), and Cypress puts `baseUrl` in front
 * of whatever it is given, so the link is cut back to `/administrator/...`.
 */
Cypress.Commands.add('sitePath', { prevSubject: true }, (href) => `/administrator/${href.split('/administrator/').pop()}`);

/**
 * Log in to the frontend as the test visitor: step 5.7.
 *
 * Not the administrator. The visitor is made, and given a fresh password, by
 * `tools/seed-site-user.php`, which writes it to a git-ignored fixture -
 * `cypress.env.json` holds the administrator's login and is not ours to write.
 */
Cypress.Commands.add('loginToSite', () => {
  cy.readFile('tests/cypress/fixtures/site-user.json').then(({ username, password }) => {
    cy.session(
      ['joomla-site', username, password],
      () => {
        cy.visit('/index.php/component/users/login');
        cy.get('#com-users-login__form #username').type(username);
        cy.get('#com-users-login__form #password').type(password, { log: false });
        cy.get('#com-users-login__form button[type="submit"]').click();
        cy.get('#com-users-login__form').should('not.exist');
      },
    );
  });
});
