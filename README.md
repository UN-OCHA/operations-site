# Reliefweb Response

## Drupal 10

### Issues

- [ ] https://www.drupal.org/project/components/issues/3278984 and https://www.drupal.org/project/components/issues/3299770
- [ ] https://www.drupal.org/project/csv_serialization/issues/3294354
- [ ] https://www.drupal.org/project/fullcalendar_api/issues/3340591
- [ ] https://www.drupal.org/project/linkchecker/issues/3271896 and https://www.drupal.org/project/linkchecker/issues/3335586
- [ ] https://www.drupal.org/project/maintenance200/issues/3288424
- [ ] https://www.drupal.org/project/override_node_options/issues/3269901
- [ ] https://www.drupal.org/project/subgroup/issues/3305343
- [ ] https://www.drupal.org/project/theme_switcher/issues/3290031

### Manual updates

- [ ] `composer require 'drupal/dynamic_entity_reference:^3.0'` is D10 only
- [ ] `group` and `subgroup` needs to be updated to at least `2.x`

## Testing

Intergration tests using existing site/config.

```sh {name=runtests}
XDEBUG_MODE=coverage ./vendor/bin/phpunit --testsuite Existing --verbose
```


## Drupal check

```sh {name=drupalcheck}
php vendor/bin/drupal-check -ad -e *Widget.php  html/modules/custom/hr_paragraphs/src
```

## Conventional changelog

- `composer run changelog` to create changelog
- `composer run release` to create new release
- `composer run release:patch` to create new release and bump patch version
- `composer run release:minor` to create new release and bump minor version
- `composer run release:major` to create new release and bump major version

## New release

On develop branch run the following

```sh {name=changelog}
git fetch --all
today=$(date +%d-%m-%Y)
git checkout -b $today-prep-release
composer run release:patch
git add composer.json
git add CHANGELOG.md
git commit -m "chore: $today prep release"
git push origin $today-prep-release
gh_pr
```

- Merge to dev
- [create PR to merge to main](https://github.com/UN-OCHA/response-site/compare/main...develop)
- Merge to main
- [Tag a new release](./gh_release)
- Deploy

### Commit messages

Full info availabel at https://www.conventionalcommits.org/en/v1.0.0/

Example

```txt
fix|feat|BREAKING CHANGE|docs|chore<optional>(scope)</optional>: <short title>

<optional>more information</optional>

Refs: #issue_number
<optional>BREAKING CHANGE: what will be broken by new feature</optional>
```
