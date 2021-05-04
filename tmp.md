# Release Day at Mass.gov
Every Tuesday night, a small but mighty [engineering team](https://github.com/massgov/openmass/graphs/contributors) releases a new version of [mass.gov](https://www.mass.gov) to the world. Our process has been tuned over the years, allowing for a sweet balance between automation and human oversight.

The principal roles involved are:
- *Release Manager*. An engineer who owns the release process and performs the shell commands and CI approvals that are needed to execute the release.
- *Quality Approver*. This person is in charge of testing the release branch as a whole and certifying that it is ready for production. This is a semi-technical role, usually performed by our Product Owner.

## Tuesday, 1pm
A [scheduled trigger in our CircleCI config](https://github.com/massgov/openmass/blob/350566451f7158fb0099a56e875595eaa3d21ad5/.circleci/config.yml#L605-L614) automatically creates a [release branch](https://github.com/massgov/openmass/pull/700) from our mainline `develop` branch. We could have left this step for release managers to do manually, but this automation provides a vital non-technical function - organizational discipline. Release managers are empowered and expected to reject all feature additions available to merge after 1pm. Last-minute merges are a threat to the stability of the site. We still have a flurry of merges on Tuesday morning, but the afternoon is allocated to polishing the release branch before deployment to Production.

## Tuesday, 1-5pm
By 5pm, the Quality Approver for the release has exercised the release branch, focusing on the changes noted in the changelog. Developers are asked to quickly fix anything minor that arises. In addition to our [automated test suite](https://app.circleci.com/pipelines/github/massgov/openmass/7697/workflows/a2eadc7b-b9d2-49bc-b047-5339cbf316ca), the approver relies on these jobs:
1. Visual regression tests powered by [Backstop](https://github.com/garris/BackstopJS) highlights visual changes to the site. The list of changes needs to be reviewed, and any unintentional changes are resolved.
2. A [Nightcrawler](https://github.com/massgov/openmass/tree/develop/.circleci/nightcrawler) job fetches a representative sample of pages on the site and assures that each page returns both a 200 status and a response time that is within policy.

<img width="814" alt="image" src="https://user-images.githubusercontent.com/7740/116416724-918be000-a808-11eb-8a4b-f4935a96a5ea.png">
<img width="613" alt="image" src="https://user-images.githubusercontent.com/7740/116416821-aff1db80-a808-11eb-84c6-f638f01dcf00.png">

 
## Tuesday, 6pm
With QA approval in hand, the Release Manager merges the release branch into `master`. This kicks off more release automation, which cuts a GitHub tag and then builds an artifact for deployment. The automation holds until ...

## Tuesday, 8pm
The Release Manager performs these steps:

1. Verifies that we have a recent Production DB backup
2. Approves the hold in the CircleCI release workflow, which instructs a Drush command called `ma:deploy` to update the code in Production and run a few commands, namely [drush deploy](https://www.drush.org/latest/deploycommand/).
3. Smoke tests the release for end users and authors (e.g. verify that login works, review a couple Views, edit some content, etc.)
4. Announces via email and Slack that the release is complete

<img width="804" alt="image" src="https://user-images.githubusercontent.com/7740/116417915-b0d73d00-a809-11eb-86f4-96ca294cb8aa.png">
