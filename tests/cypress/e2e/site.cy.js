/**
 * The frontend, as somebody who is not an administrator uses it: step 5.7.
 *
 * The SaaS case. A visitor with an ordinary account sees their own projects
 * and nobody else's, models one with the same forms as the administrator,
 * generates it with a generator they choose, and downloads the result. They
 * cannot open somebody else's project, generate it or download it, and they
 * can never install anything, whatever the component's options say.
 *
 * `tools/seed-site-user.php` makes the visitor, lets Registered users create
 * and edit their own projects, and adds a "My projects" menu item.
 * `seed-project.php --owner=` gives the visitor a complete model to generate
 * from, because typing a component into nested subforms is not what this
 * spec is about - `project-binds.cy.js` covers those forms.
 */

const MINE = 'VisitorConference';

/**
 * The id of a project by name, from the database the specs seed.
 */
const projectId = (name) => cy.exec(`php tools/project-id.php ${name}`).its('stdout').then(Number);

describe('the frontend', () => {
  before(() => {
    cy.exec('php tools/forget-test-projects.php');
    cy.exec('php tools/seed-site-user.php');
    cy.exec(`php tools/seed-project.php conference --saved --owner=extengen-visitor --name=${MINE}`);
    cy.exec('php tools/seed-project.php conference --saved');
    cy.exec('php tools/set-option.php allow_install 0');
  });

  after(() => {
    cy.exec('php tools/set-option.php allow_install 0');
  });

  it('sends a visitor who is not logged in to the login form, and back', () => {
    cy.clearCookies();
    cy.visit('/index.php/my-projects');

    cy.location('pathname').should('contain', '/component/users/login');
    cy.location('search').should('contain', 'return=');
  });

  it('lists the visitor\'s own projects and nobody else\'s', () => {
    cy.loginToSite();
    cy.visit('/index.php/my-projects');

    cy.get('#extengen-site-projects h1').should('contain.text', 'My projects');
    cy.get('#extengenSiteProjects').should('contain.text', MINE);
    cy.get('#extengenSiteProjects').should('not.contain.text', 'MyConference');
    cy.get('#extengen-new-project').should('exist');
    cy.shouldShowNoRawConstants();
  });

  it('opens the visitor\'s project on its language\'s tabs, without the administrator\'s fields', () => {
    cy.loginToSite();
    cy.visit('/index.php/my-projects');
    cy.contains('#extengenSiteProjects a', MINE).click();

    ['entities', 'pages', 'extensions'].forEach((tab) => {
      cy.get(`joomla-tab#myTab joomla-tab-element#${tab}`).should('exist');
    });
    cy.get('#project-form [name^="jform[datamodel]"]').should('exist');

    // Not the visitor's to set, and not in the form at all.
    ['published', 'catid', 'access'].forEach((field) => {
      cy.get(`#project-form [name="jform[${field}]"]`).should('not.exist');
    });

    // And no ERD: it is an administrator view.
    cy.get('a[href="#ERDModal"]').should('not.exist');
    cy.shouldShowNoRawConstants();
  });

  it('starts a new project, which is then the visitor\'s', () => {
    cy.loginToSite();
    cy.visit('/index.php/my-projects');
    cy.get('#extengen-new-project').click();

    cy.get('#jform_name', { timeout: 20000 }).type('VisitorProject');
    cy.get('#jform_metalanguage option').then(($options) => {
      const er1 = [...$options].find((o) => o.value.startsWith('ER1|1.2'));

      expect(er1, 'ER1 1.2 is offered').to.exist;
      cy.get('#jform_metalanguage').select(er1.value);
    });
    cy.get('#extengen-save').click();

    cy.location('search', { timeout: 30000 }).should('match', /[?&]id=[1-9]\d*/);
    cy.get('#jform_datamodel-lbl').should('exist');
    cy.get('#extengen-cancel').click();

    cy.get('#extengenSiteProjects').should('contain.text', 'VisitorProject');
  });

  it('generates the visitor\'s project with a chosen generator and downloads it', () => {
    cy.loginToSite();
    cy.visit('/index.php/my-projects');
    cy.contains('#extengenSiteProjects tr', MINE).find('a.extengen-generate').click();

    cy.get('#generate-choose-form input[value="joomla6"]').should('be.checked');
    cy.get('#generate-run').click();

    cy.get('#generate-result', { timeout: 60000 }).should('contain.text', 'files');

    // Nothing about where on the server it went.
    cy.get('#generate-result').should('not.contain.text', 'package: ');
    cy.get('#generate-result').should('not.contain.text', 'unpacked: ');
    cy.get('#generate-result').should('not.contain.text', 'administrator/components/com_extengen/generated');

    // And no install, even with the option off - see the next test for on.
    cy.get('#generate-install').should('not.exist');

    cy.get('#generate-download').invoke('attr', 'href').then((href) => {
      cy.request({ url: href.replace(/^.*?\/index\.php/, '/index.php'), encoding: 'binary' }).then((response) => {
        expect(response.status).to.eq(200);
        expect(response.headers['content-type']).to.contain('application/zip');
        expect(response.body.slice(0, 2)).to.eq('PK');
      });
    });
  });

  it('never installs from the frontend, whatever the options say', () => {
    cy.exec('php tools/set-option.php allow_install 1');
    cy.loginToSite();

    projectId(MINE).then((id) => {
      cy.visit(`/index.php?option=com_extengen&view=generate&project_id=${id}&generator=joomla6`);
      cy.get('#generate-result', { timeout: 60000 }).should('contain.text', 'files');
      cy.get('#generate-install').should('not.exist');

      cy.get('#generate-download').invoke('attr', 'href').then((href) => {
        const install = href.replace('generate.download', 'generate.install').replace(/^.*?\/index\.php/, '/index.php');

        cy.request({ url: install, failOnStatusCode: false }).its('status').should('eq', 404);
      });
    });

    cy.exec('php tools/set-option.php allow_install 0');
  });

  /**
   * Two projects of one component name have packages of their own.
   *
   * MyConference and the visitor's VisitorConference both model a component
   * called Conference. Output went to a folder named for the component, so
   * the projects list offered MyConference's package under both rows. It is a
   * folder per project now, and the visitor's output is in a folder of their
   * own besides.
   */
  it('does not list one project\'s package under another of the same component name', () => {
    cy.loginToAdmin();

    projectId('MyConference').then((id) => {
      cy.visit(`/administrator/index.php?option=com_extengen&view=generate&tmpl=component&project_id=${id}&generator=joomla6`);
      cy.get('#generate-result', { timeout: 60000 }).should('contain.text', 'files');
    });

    cy.visit('/administrator/index.php?option=com_extengen&view=projects&filter[metalanguage]=&list[limit]=0');
    cy.contains('#extengenProjects tr', 'MyConference').find('td.project-packages a[data-generator="joomla6"]')
      .should('exist');
    cy.contains('#extengenProjects tr', MINE).find('td.project-packages a').should('not.exist');
  });

  it('does not let the visitor reach somebody else\'s project', () => {
    cy.loginToSite();

    projectId('MyConference').then((id) => {
      // Not in the list, and every way in by id is "not found".
      cy.request({ url: `/index.php?option=com_extengen&view=generate&project_id=${id}`, failOnStatusCode: false })
        .its('status').should('eq', 404);

      cy.request({
        url: `/index.php?option=com_extengen&view=generate&project_id=${id}&generator=joomla6`,
        failOnStatusCode: false,
      }).its('status').should('eq', 404);

      cy.visit(`/index.php?option=com_extengen&task=project.edit&id=${id}`, { failOnStatusCode: false });
      cy.get('#project-form').should('not.exist');
    });
  });
});
