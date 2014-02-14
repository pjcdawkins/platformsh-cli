# Commerce Platform CLI

This is the official CLI for Commerce Platform.

## Requirements

* Drush 5.1 or higher - https://github.com/drush-ops/drush
* PHP 5.3.3 or higher with cURL
* Composer - https://getcomposer.org/doc/00-intro.md

## Installation (TMP)
Clone the Git repository locally
```
git clone git@github.com:commerceguys/platform-cli.git
```

Launch composer
```
cd platform-cli
composer install
```

## Installation
```
composer global require 'commerceguys/platform-cli:*'
```
Make sure you have ~/.composer/vendor/bin/ in your path.

You can then go into a directory
```
cd myprojects
platform init
```
