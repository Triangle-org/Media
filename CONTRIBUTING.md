# Contributing to Triangle Media

[Документ на русском языке](CONTRIBUTING_ru.md)

Thank you for helping improve the Triangle Media plugin.

Before opening a change, read the [Code of Conduct](CODE_OF_CONDUCT.md), [Security policy](SECURITY.md), and [project documentation](README.md).

## Local checks

Use PHP 8.1 or newer with the required `curl`, `fileinfo`, and `imagick` extensions. Run:

```sh
composer validate --strict --no-check-publish --no-interaction
composer run check --no-interaction
composer audit --locked --no-interaction
```

Changes to image upload, URL fetching, storage paths, or filters should include a focused regression test. Do not add credentials, production data, or generated image files to a pull request.

## Pull requests

Keep the change focused, describe any compatibility impact, and include the commands and results of the checks you ran. GitHub Actions tests PHP 8.1 and 8.5 with lowest and latest allowed dependencies.

For general questions, use GitHub Issues. Report suspected vulnerabilities privately using GitHub's **Report a vulnerability** feature; do not publish exploit details in an issue.
