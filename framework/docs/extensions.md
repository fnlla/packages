**EXTENSIONS**

Extensions are Composer packages that integrate with fnlla (finella) via service providers.

**CREATING A PACKAGE**
**-** Create a Composer package.
**-** Add PSR-4 autoloading for your namespace.
**-** Implement a service provider extending `Fnlla\\Support\ServiceProvider`.
**-** Add providers to `extra.fnlla.providers`.

Example `composer.json`:
```json
{
  "name": "fnlla/acme-example",
  "type": "library",
  "require": {
    "php": ">=8.5",
    "fnlla/framework": "^3.0"
  },
  "autoload": {
    "psr-4": {
      "Fnlla\\\Acme\\": "src/"
    }
  },
  "extra": {
    "fnlla (finella)": {
      "providers": [
        "Fnlla\\\Acme\\AcmeServiceProvider"
      ]
    }
  }
}
```

**VERSIONING**
Follow SemVer and keep compatibility with the framework major version.
