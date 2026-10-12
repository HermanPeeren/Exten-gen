/**
 * A model imported from LionWeb, saved in the browser, and exported again.
 *
 * The round trip was proved once already, through `getForm()`, `filter()` and
 * `save()` called in code - and the first time anybody did it through the
 * screen, the export refused: "root.tags is not a feature of Blueprint in this
 * language". `FormController::save()` adds `tags` to every post whose form has
 * no tags field, and the model stored everything it was handed. A save made in
 * code never passes the controller, so the only way to see that class of
 * defect is to post the form the way a person does, which is this spec.
 *
 * What is claimed is the round trip as a whole: every node that went in comes
 * out, nothing is added, and the one value edited on screen is the only one
 * that differs.
 *
 * **It needs JCB's language and a JCB blueprint**, neither of which is in this
 * repository: Hello World is 53 nodes from JcbInOut's sample data, read out of
 * the sibling clone, and the JCB metalanguage has to be imported on the site.
 * Where either is missing the test skips rather than fails - a CI checkout has
 * neither, and that says nothing about this code. ER1 cannot stand in: its
 * package predates manifest format 5 and does not know its own LionWeb key, so
 * it has no export to round-trip through yet.
 */

const SOURCE = '../jcbinout/data/hello-world.instance.lionweb.json';
const chunkPath = 'media/cypress-round-trip.json';

const messages = () => cy.get('#system-message-container', { timeout: 30000 }).invoke('text');

/**
 * Post a list task for one project, with the page's own token.
 *
 * A request rather than the toolbar button for the export, because the button
 * answers with a download and a browser that is handed a file does not
 * navigate - Cypress would wait for a page that never comes.
 */
const postTask = (task, id, extra = {}) => cy.window().then((win) => cy.request({
  method: 'POST',
  url: '/administrator/index.php?option=com_extengen&view=projects',
  form: true,
  body: {
    task,
    'cid[]': id,
    [win.Joomla.getOptions('csrf.token')]: 1,
    ...extra,
  },
}));

/**
 * The project written out as a chunk, or the reason it was not.
 *
 * A clean export is JSON; one with anything to report sends the browser back
 * to the list with the report on it, which arrives here as HTML.
 */
const exportProject = (id) => {
  cy.visitExtengen('projects');

  return postTask('projects.exportLionweb', id).then((response) => {
    if (typeof response.body !== 'object') {
      const page = new DOMParser().parseFromString(response.body, 'text/html');
      const said = page.querySelector('#system-message-container')?.textContent.trim();

      throw new Error(`the export did not produce a chunk: ${said || 'no message'}`);
    }

    return response.body;
  });
};

/**
 * Every node by id, as property values keyed by property, and the other two
 * buckets as text - the order a writer lists things in is nobody's business.
 */
const byNode = (chunk) => {
  const nodes = {};

  chunk.nodes.forEach((node) => {
    const properties = {};

    node.properties.forEach((p) => {
      properties[p.property.key] = p.value;
    });

    const sorted = (list) => list.map((entry) => JSON.stringify(entry)).sort();

    nodes[node.id] = {
      classifier: JSON.stringify(node.classifier),
      parent: node.parent ?? null,
      properties,
      containments: sorted(node.containments),
      references: sorted(node.references),
    };
  });

  return nodes;
};

describe('a LionWeb model, saved in the browser and exported again', () => {
  let projectId = 0;

  afterEach(() => {
    // Trashed and deleted, so that running this again does not leave another
    // Hello World behind each time.
    if (projectId > 0) {
      cy.visitExtengen('projects');
      postTask('projects.trash', projectId);
      cy.visitExtengen('projects');
      postTask('projects.delete', projectId);
      projectId = 0;
    }
  });

  it('comes out with every node, and the edited value changed', function roundTrip() {
    cy.task('readIfExists', SOURCE).then((source) => {
      if (source === null) {
        cy.task('log', `skipped: no ${SOURCE}`);
        this.skip();
      }

      const original = JSON.parse(source);

      cy.writeFile(`joomla/${chunkPath}`, original);
      cy.loginToAdmin();
      cy.visitExtengen('projects');

      cy.contains('summary', 'Import LionWeb model').then(($summary) => {
        if (!$summary.closest('details').prop('open')) {
          cy.wrap($summary).click();
        }
      });

      cy.get('#chunk').clear().type(chunkPath);
      cy.get('#toolbar').contains('Import LionWeb model').click();

      // On a site without JCB's language the import says so, and that is a
      // site this spec has nothing to say about.
      messages().then((text) => {
        if (text.includes('not imported on this site')) {
          cy.task('log', 'skipped: the JCB metalanguage is not on this site');
          this.skip();
        }
      });

      cy.location('search', { timeout: 60000 }).should('contain', 'layout=edit');
      cy.location('search').then((search) => {
        projectId = Number(new URLSearchParams(search).get('id'));
        expect(projectId, 'the import made a project').to.be.greaterThan(0);
      });

      cy.get('#project-form', { timeout: 60000 }).should('exist');

      // The model as the import stored it, before anything was saved.
      cy.then(() => exportProject(projectId)).then((before) => {
        expect(before.nodes, 'what the import stored exports whole').to.have.length(original.nodes.length);

        cy.visit(`/administrator/index.php?option=com_extengen&task=project.edit&id=${projectId}`);
        cy.get('#project-form', { timeout: 60000 }).should('exist');

        // Any short text box on screen that holds something: which one does
        // not matter, only that exactly it comes back changed.
        cy.get('#project-form input[type="text"]:visible:not(#jform_name)')
          .filter((i, el) => el.value !== '' && el.value.length < 40 && !el.readOnly)
          .first()
          .then(($input) => {
            const edited = `${$input.val()} edited`;

            cy.wrap($input).clear().type(edited);

            cy.window().then((win) => {
              const form = win.document.getElementById('project-form');

              // Said by name when the browser refuses the form, rather than
              // left to the timeout below.
              if (!win.document.formvalidator.isValid(form)) {
                const invalid = [...form.querySelectorAll('.invalid[name]')].map((el) => el.name);

                throw new Error(`the form does not validate: ${invalid.join(', ')}`);
              }

              win.beforeSave = true;
              win.Joomla.submitbutton('project.save');
            });

            cy.window({ timeout: 120000 }).should((win) => {
              expect(win.beforeSave, 'the save has left the edit screen').to.be.undefined;
            });

            messages().should('contain', 'saved');

            cy.then(() => exportProject(projectId)).then((after) => {
              expect(after.nodes, 'every node, and no more').to.have.length(original.nodes.length);

              const was = byNode(before);
              const is = byNode(after);

              expect(Object.keys(is).sort(), 'the same nodes').to.deep.equal(Object.keys(was).sort());

              const changed = [];

              Object.keys(was).forEach((id) => {
                ['classifier', 'parent', 'containments', 'references'].forEach((bucket) => {
                  expect(is[id][bucket], `${id} ${bucket}`).to.deep.equal(was[id][bucket]);
                });

                const keys = new Set([...Object.keys(was[id].properties), ...Object.keys(is[id].properties)]);

                keys.forEach((key) => {
                  if (was[id].properties[key] !== is[id].properties[key]) {
                    changed.push({ id, key, value: is[id].properties[key] });
                  }
                });
              });

              // Two kinds of difference a browser save still makes, both known
              // and both in the plan (step 6.4). A property the chunk did not
              // have comes back with a value, because every control posts one:
              // an empty text box an empty string, a list its first option.
              // And a value with line breaks comes back without them, because
              // an `<input type="text">` cannot hold a line break and the
              // browser drops it. Counted rather than allowed silently, so a
              // third kind - or the edit not arriving - fails.
              const kind = (c) => {
                const before = was[c.id].properties[c.key];

                if (before === undefined) {
                  return c.value === '' ? 'absent became empty' : 'absent became a list default';
                }

                if (typeof before === 'string' && before.replace(/[\r\n]/g, '') === c.value) {
                  return 'line breaks lost';
                }

                return c.value === edited ? 'edited' : 'unexplained';
              };

              // Written down before it is judged, so a failure leaves the
              // differences somewhere to read.
              cy.writeFile(
                'tests/cypress/downloads/round-trip-differences.json',
                changed.map((c) => ({ ...c, was: was[c.id].properties[c.key], kind: kind(c) })),
              ).then(() => {
                const kinds = changed.map(kind);

                expect(kinds.filter((k) => k === 'edited'), 'the edited value, once').to.have.length(1);
                expect(kinds.filter((k) => k === 'unexplained'), 'no other difference').to.deep.equal([]);
              });
            });
          });
      });
    });
  });
});
