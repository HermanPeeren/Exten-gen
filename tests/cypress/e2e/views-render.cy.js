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

  /**
   * Every link the administrator menu carries for this component opens.
   *
   * The test above visits `option=com_extengen` with no view, which falls back
   * to DisplayController's default. The menu does not: Joomla writes its links
   * into `#__menu` from the manifest at install time, and the component's own
   * entry said `view=extengen` for as long as this component has existed - a
   * `View\Extengen` that was never written, so the one link a person clicks to
   * reach it returned "View not found [name, type, prefix]: extengen, html,
   * Administrator". A submenu entry pointed at `view=projectforms` for as long
   * as it took that view to move to Meta-gen.
   *
   * **The hrefs are read and visited, not clicked**, and that is not a
   * shortcut. The component's own entry renders with `class="has-arrow"`,
   * because it has a submenu - so Joomla's menu script swallows the click to
   * open the dropdown and the browser never goes anywhere. A spec that clicked
   * it passed against a manifest that was broken, which is how this test came
   * to be written twice.
   */
  it('opens every link the administrator menu carries', () => {
    cy.loginToAdmin();
    cy.visit('/administrator/index.php');

    cy.get('#sidebarmenu a[href*="option=com_extengen"]').then(($links) => {
      const urls = [...new Set([...$links].map((a) => a.getAttribute('href')))];

      expect(urls, 'the menu carries links for this component').to.not.be.empty;

      urls.forEach((url) => {
        cy.visit(`/administrator/${url}`, { failOnStatusCode: false });
        cy.get('body').should('not.contain', 'View not found');
        cy.get('body').should('not.contain', 'An error has occurred');
        cy.shouldHaveRendered();
      });
    });
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
