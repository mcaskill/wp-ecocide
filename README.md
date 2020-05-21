# Ecocide — WordPress Feature Disabler

<!-- [![Code Quality][scrutinizer-ci-badge]][scrutinizer-ci.com] -->
<!-- [![Latest Stable Version][release-badge]][packagist.org] -->
<!-- [![Software License][license-badge]](LICENSE.md) -->

A bundle of lightweight modules to apply theme-agnostic modifications to WordPress.

## Installation

Require package in your theme project with [Composer](https://getcomposer.org/):

```console
composer require mcaskill/wp-ecocide
```

## Usage

Ecocide isn't started until an instance of its `Ecocide\Modules` class is created and booted:

```php
$ecocide = new \Ecocide\Modules();

$ecocide->boot('disable-author-template');
```

## License

MIT

[scrutinizer-ci-badge]: https://scrutinizer-ci.com/g/mcaskill/wp-ecocide/badges/quality-score.png?b=master
[release-badge]:        https://img.shields.io/github/tag/mcaskill/wp-ecocide.svg
[license-badge]:        https://poser.pugx.org/mcaskill/wp-ecocide/license

[scrutinizer-ci.com]:   https://scrutinizer-ci.com/g/mcaskill/wp-ecocide/?branch=master
[packagist.org]:        https://packagist.org/packages/mcaskill/wp-ecocide
