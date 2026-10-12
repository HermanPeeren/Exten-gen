import { existsSync, readFileSync } from 'node:fs';
import { defineConfig } from 'cypress';

/**
 * Cypress runs against a real Joomla with Exten-gen installed.
 *
 * There is no way around that: everything these specs are for - that a view
 * renders at all, that a dropdown fills itself from the page, that generation
 * produces files - needs the framework the component runs inside. The rest of
 * the suite deliberately has no Joomla in it, which is why a component that
 * built a second SiteApplication on every request passed every check and still
 * showed a blank page.
 *
 * The site and the login come from `cypress.env.json`, which is git-ignored.
 * Copy `cypress.env.json.dist` and fill it in.
 */
export default defineConfig({
  e2e: {
    // Overridden by `baseUrl` in cypress.env.json.
    baseUrl: 'http://localhost/Exten-gen/joomla',
    supportFile: 'tests/cypress/support/e2e.js',
    specPattern: 'tests/cypress/e2e/**/*.cy.js',
    video: false,
    screenshotOnRunFailure: true,

    // Beside the specs, where `.gitignore` has been expecting them. Cypress
    // defaults to `cypress/screenshots` at the repository root, so a failing
    // run left an untracked directory nobody had ignored - which shows up as
    // something to commit the next time anyone looks at `git status`.
    screenshotsFolder: 'tests/cypress/screenshots',
    downloadsFolder: 'tests/cypress/downloads',

    // Joomla's admin is one origin and one session; nothing here talks to a
    // third party, so the browser need not police it.
    chromeWebSecurity: false,

    setupNodeEvents(on, config) {
      on('task', {
        log(message) {
          // eslint-disable-next-line no-console
          console.log(message);

          return null;
        },

        // A file that may or may not be there, as text or null. `cy.readFile`
        // fails a test on a missing file, and some specs read fixtures from a
        // sibling clone that a CI checkout does not have - so they ask first
        // and skip, rather than failing over something that is not this repo.
        readIfExists(path) {
          return existsSync(path) ? readFileSync(path, 'utf8') : null;
        },
      });

      if (config.env.baseUrl) {
        config.baseUrl = config.env.baseUrl;
      }

      if (!config.env.adminUser || !config.env.adminPassword) {
        throw new Error(
          'cypress.env.json needs adminUser and adminPassword. Copy cypress.env.json.dist and fill it in.',
        );
      }

      return config;
    },
  },
});
