/**
 * A field is a property or a reference, and both halves have to agree.
 *
 * This is the browser half of a defect that was live and silent for months.
 * 3.5 made ER1 a generated language, and a generated form spells the choice
 * between an abstract concept's subtypes with the *concept names* - so the
 * radio writes `Property` or `EntityReferenceField`, and the subform stores
 * what was typed under the concept name with a small first letter. The
 * hand-written forms it replaced had said `property` and `reference`, and every
 * generator went on comparing against those. A project modelled through
 * Exten-gen's own screens since 3.5 generated a component with no relations in
 * it: no foreign key, no join, no dropdown, and no error anywhere.
 *
 * Nothing caught it because no model in this repository was in the new
 * spelling. The fixtures, the seeded projects, the specs - all written before
 * 3.5, all still passing. The unit suite now runs every fixture through both
 * spellings and compares the bytes, which is far more thorough than anything
 * here could be, and still cannot see either of the two things below:
 *
 * - what the *installed* form says, after Joomla has loaded it through
 *   `Form::loadFile` with the prefixes it collects on the way. The package on
 *   disk is not evidence about the form on screen.
 * - whether a project stored that way survives the whole route - controller,
 *   model, library autoloader, Twig, filesystem - and comes out with its
 *   relations still in it.
 *
 * Needs the conference project seeded in the new spelling:
 *
 *   php tools/seed-project.php conference --saved
 *
 * The spec asserts that it was, rather than skipping, because the only thing
 * worse than not running this check is believing it ran.
 */

const PROJECT = 'MyConference';

// Where generation writes its tree, beside the zip. `Conference` is the
// component name in the model; the directory below it is the target.
const GENERATED =
  'joomla/administrator/components/com_extengen/generated/Conference/joomla6/conference';

/**
 * Every element matching `selector`, including the ones inside row templates.
 *
 * A repeatable subform keeps the markup for one row in a `<template>`, and
 * Joomla clones it for every row there is. Its content is a DocumentFragment,
 * so `querySelectorAll` on the document walks straight past it - and the
 * templates nest, because a field sits inside an entity.
 *
 * This is where the names have to be read. The form for one field is that
 * template: it is what every rendered row is a copy of, and it is what the Add
 * button produces when somebody adds a field by hand.
 */
const including = (root, selector, found = []) => {
  found.push(...root.querySelectorAll(selector));

  for (const template of root.querySelectorAll('template')) {
    including(template.content, selector, found);
  }

  return found;
};

describe("a field's kind, as the screens spell it", () => {
  /**
   * Open the seeded conference project, by name.
   *
   * By name and not "the first project", which is what the other specs take,
   * because here it matters *which* one: any other project on the site is in
   * the old spelling and would pass every assertion below without testing
   * anything.
   */
  const openTheProject = () => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();

    cy.get('#adminForm')
      .contains('a[href*="task=project.edit"]', PROJECT)
      .should(
        'exist',
        `the ${PROJECT} project is on the site - run tools/seed-project.php conference --saved`
      )
      .click();

    cy.get('#project-form', { timeout: 20000 }).should('exist');
  };

  it('offers the concept names on the real form', () => {
    openTheProject();

    cy.document().then((doc) => {
      const inputs = including(doc.querySelector('#project-form'), '[name*="[field_type]"]');

      expect(inputs, 'the form has the field the generators read').to.not.be.empty;

      const values = [...new Set(inputs.map((i) => i.getAttribute('value')))];

      expect(values, 'the two subtypes, spelled as concepts').to.include.members([
        'Property',
        'EntityReferenceField',
      ]);

      // And not the names the hand-written forms used. If these ever came
      // back, the generators would quietly work again and this spec would be
      // the only thing saying the language had changed underneath them.
      expect(values, 'no pre-3.5 spelling').to.not.include('reference');
      expect(values, 'no pre-3.5 spelling').to.not.include('property');
    });
  });

  it('stores a reference under the name the subform carries', () => {
    openTheProject();

    cy.document().then((doc) => {
      // `entityReferenceField`, derived from the concept name - the key the
      // generators read the payload from, and the one that was wrong.
      const payload = including(
        doc.querySelector('#project-form'),
        '[name*="[entityReferenceField]"]'
      );

      expect(payload, 'the subform a reference is stored in').to.not.be.empty;
    });
  });

  /**
   * And the whole way through: a project in that spelling still has relations.
   *
   * This is the assertion that failed before the fix, and it failed harder than
   * the reproduction had predicted. The conference model has three reference
   * fields and generating it used to produce three foreign-key columns; spelled
   * the way the screens spell it, the generated schema came out as four tables
   * with nothing in them but `id`. Not only the references had gone - every
   * property had too, because a field that is neither subtype is no column at
   * all. The log said the same file count, and nothing anywhere said a word.
   *
   * Which is why this reads the content of a generated file rather than the
   * report above it. The report was never wrong.
   */
  it('generates the relations of a project stored that way', () => {
    cy.visitExtengen('projects');
    cy.shouldHaveRendered();

    cy.get('#adminForm')
      .contains('tr', PROJECT)
      .find('a[data-href*="view=generate"]')
      .then(($link) => {
        cy.visit($link.attr('data-href'));
      });

    cy.get('body', { timeout: 60000 }).should('contain.text', 'files');
    cy.get('body').should('not.contain', 'Fatal error');
    cy.get('body').should('not.contain', 'Warning:');

    cy.readFile(
      `${GENERATED}/administrator/components/com_conference/sql/install.mysql.utf8.sql`
    ).then((sql) => {
      // The three references.
      for (const column of ['speaker_id', 'talk_id', 'room_id']) {
        expect(sql, `${column} is a column`).to.contain(column);
      }

      // And a property, which is the half of the failure the reproduction had
      // not expected and which a check on references alone would have missed.
      expect(sql, 'properties are columns too').to.contain('`name`');
    });

    // A reference is not only a column: it is also the dropdown that fills
    // itself from the referred table. Both came from the same read, and both
    // disappeared together.
    cy.readFile(`${GENERATED}/administrator/components/com_conference/forms/talk.xml`).then(
      (xml) => {
        expect(xml, 'the reference renders as a query-backed field').to.contain('type="sql"');
      }
    );
  });
});
