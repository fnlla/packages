**DIRECTORY STRUCTURE**

**FRAMEWORK PACKAGE**
```
framework/
|-- src/
|-- docs/
|-- LICENSE
|-- README.md
|-- CREDITS.md
|-- composer.json
```

**TYPICAL APPLICATION**
```
app/
|-- public/
|-- bootstrap/
|-- config/
|-- routes/
|-- resources/
|-- storage/
|-- vendor/
|-- composer.json
|-- .env
```

**PUBLIC VS PRIVATE**
**-** Public: `public/` only.
**-** Private: `config/`, `storage/`, `vendor/`, `bootstrap/`.

**DEPLOYABLE ARTEFACTS**
**-** Application code
**-** Composer dependencies installed on the server
**-** Config and environment variables
