# Mayflower Assets Integration

CircleCI is configured for [massgov/mayflower](https://github.com/massgov/mayflower) to build the production artifact on every build but to deploy the artifact only when a release is cut. The deployment script copies the built artifact into a clone of [massgov/mayflower-artifacts](https://github.com/massgov/mayflower-artifacts) and commits all the branches and tags with the same release version of [massgov/mayflower](https://github.com/massgov/mayflower).

See [https://github.com/massgov/mayflower-patternlab/pull/49](https://github.com/massgov/mayflower-patternlab/pull/49) and [https://github.com/massgov/mayflower-patternlab/pull/50](https://github.com/massgov/mayflower-patternlab/pull/50) for this work.

[massgov/mayflower-artifacts](https://github.com/massgov/mayflower-artifacts) is configured with a [composer.json](https://github.com/massgov/mayflower-artifacts/blob/master/composer.json).

Last, [massgov/mass](https://github.com/massgov/mass) includes [massgov/mayflower-artifacts](https://github.com/massgov/mayflower-artifacts) as a composer dependency.

See [https://github.com/massgov/mass/pull/7](https://github.com/massgov/mass/pull/7) for this work.