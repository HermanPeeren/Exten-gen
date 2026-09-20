/**
 * Every view of the component renders.
 *
 * This is the spec the rest of the suite cannot be: it boots Joomla. Five
 * steps of sweeping and refactoring passed every static check while
 * `ExtengenComponent::boot()` asked the container for a SiteApplication on
 * every administrator request, so each page built a second, front-end
 * application before it could show anything, and the projects list came back
 * as 200 OK with an empty body. Nothing that reads source could see that, and
 * nothing that checks a status code would have either.
 *
 * So the assertion is deliberately about what arrived on screen, not about
 * whether the request succeeded.
 */

describe('the component renders', () => {
  const views = [
    ['projects', 'the list of projects'],
    ['generators', 'the generators page'],
    ['info', 'the info page'],
  ];

  views.forEach(([view, description]) => {
    it(`shows ${description}`, () => {
      cy.visitExtengen(view);
      cy.shouldHaveRendered();
    });
  });

  it('reaches the component from its own menu item, as a person would', () => {
    // Not the same as visiting the url directly: this is the route the left
    // hand menu takes, which is how the blank page was found.
    cy.loginToAdmin();
    cy.visit('/administrator/index.php?option=com_extengen');
    cy.shouldHaveRendered();
  });

  it('renders its views without a JavaScript error', () => {
    // A page that renders can still be broken in the browser. The generators
    // view built a full list toolbar - every button with listCheck(true) -
    // on a page that has no form, and Joomla's own script then threw
    // "The form adminForm is required" on every visit. Nothing server-side
    // could see that.
    const errors = [];

    cy.on('uncaught:exception', (err) => {
      errors.push(err.message);

      return false;
    });

    views.forEach(([view]) => {
      cy.visitExtengen(view);
      cy.shouldHaveRendered();
    });

    cy.then(() => {
      expect(errors, 'no page threw').to.deep.equal([]);
    });
  });
});
