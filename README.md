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
* add current date to report file name
* add psr2 pre-commit hook
* change lib for generate excel docs
* refactor curl request (use guzzle for example)
* refactor command, split to smaller tasks
* move out the app config
* add tests for Client object
