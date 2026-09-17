#!/bin/bash

# The plugin's own tests inside PKP's continuous integration: the browser tests
# first, then the unit tests against the database of the matrix. Neither may be
# skipped because the other failed, so the status of both is kept and the worst
# one is returned.

set +e
status=0

npx cypress run  --headless --browser chrome  --config '{"specPattern":["plugins/blocks/keywordCloudClassicBeautiful/cypress/tests/functional/*.cy.js"]}'
cypress=$?
[ $cypress -ne 0 ] && status=$cypress

php lib/pkp/lib/vendor/bin/phpunit --configuration lib/pkp/tests/phpunit.xml --no-coverage plugins/blocks/keywordCloudClassicBeautiful/tests
phpunit=$?
[ $phpunit -ne 0 ] && status=$phpunit

echo "tests.sh: cypress=$cypress phpunit=$phpunit"
return $status
