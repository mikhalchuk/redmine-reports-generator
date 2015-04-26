redmine-reports-generator
=========================

Generates reports from Redmine

## Install:
```bash
git clone
```

```bash
composer install
```

## Configure:
```bash
cp config/config.php.template config/config.php
```

Then fill copied file by your data

## Working:

change month and days count in config/template.xls

```bash
bin/reports generate
```

## TODO:
* add psr2 pre-commit hook
* change lib for generate excel docs
* refactor command, split to smaller tasks