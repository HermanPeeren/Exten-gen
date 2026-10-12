/**
 * A project form that PHP cut short is refused, with the reason on screen.
 *
 * PHP reads at most `max_input_vars` variables out of a post and drops the
 * rest without telling anybody. A model imported from JCB puts some 24,000
 * inputs on the edit screen; with the task and the token at the bottom of the
 * form, the save landed on the projects list, nothing was stored, and nothing
 * said so.
 *
 * Nothing here changes `max_input_vars` - that is the server's, and a spec
 * that reconfigured PHP would be testing the reconfiguring. What a truncated
 * post looks like when it arrives is known exactly: everything up to some
 * point, nothing after it. So this sends one - the task, the token, the count
 * the browser fills in, a field, and not the sentinel the layout puts last -
 * and checks that the save is refused by name, and that the project is as it
 * was.
 *
 * Needs the conference project, which `beforeEach` sees to.
 */

const PROJECT = 'MyConference';

beforeEach(() => {
  cy.exec('php tools/seed-project.php conference --saved');
});

describe('a project form cut short by max_input_vars', () => {
  it('is refused, says why, and leaves the project as it was', () => {
    cy.visitExtengen('projects');

    cy.get('#adminForm')
      .contains('a[href*="task=project.edit"]', PROJECT)
      .should('exist', `the ${PROJECT} project is on the site - run tools/seed-project.php conference --saved`)
      .click();

    cy.get('#project-form', { timeout: 20000 }).should('exist');

    // The layout's own promise, before relying on it: the sentinel is the
    // last thing in the form, and the task and the token come first.
    cy.get('#project-form input[name]').last().should('have.attr', 'name', 'extengen_complete');
    cy.get('#project-form input[name]').first().should('have.attr', 'name', 'task');

    cy.location('search').then((search) => {
      const id = new URLSearchParams(search).get('id');

      cy.window().then((win) => cy.request({
        method: 'POST',
        url: `/administrator/index.php?option=com_extengen&layout=edit&id=${id}`,
        form: true,
        body: {
          task: 'project.apply',
          [win.Joomla.getOptions('csrf.token')]: 1,
          extengen_inputs: '24105',
          'jform[name]': 'Truncated',
        },
      })).then((response) => {
        const page = new DOMParser().parseFromString(response.body, 'text/html');
        const said = page.querySelector('#system-message-container')?.textContent ?? '';

        expect(said).to.contain('Not saved');
        expect(said).to.contain('max_input_vars');
        expect(said).to.contain('24,105');
      });

      // And nothing was stored: the name the post carried is not the name.
      cy.visit(`/administrator/index.php?option=com_extengen&task=project.edit&id=${id}`);
      cy.get('#jform_name', { timeout: 20000 }).should('have.value', PROJECT);

      cy.window().then((win) => win.Joomla.submitbutton('project.cancel'));
      cy.get('#adminForm', { timeout: 20000 }).should('exist');
    });
  });
});
