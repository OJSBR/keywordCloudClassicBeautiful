#!/bin/bash

set -e

npx cypress run  --headless --browser chrome  --config '{"specPattern":["plugins/blocks/keywordCloudClassicBeautiful/cypress/tests/functional/*.cy.js"]}'
