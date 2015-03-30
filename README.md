# NOD-php-environment
A php library for detecting and configuring environment with dotenv

##Installation:
#####With [Composer](https://getcomposer.org):
```bash
composer require nod/environment
```

##Usage
```php
//First include composer autoloader if it's not already included
$loader = require_once 'vendor/autoload.php';

//Get Instance
use NOD\Environment;
$env = new Nod\Environment;

//To test a value
echo $env->getEnv(name);
//or
echo getenv(name);

//EXCLUDE variables filtered JSON
echo $env->toJson();
```

##TODOs
- Imrove documentation
- Add optional hostname check to detection

##Contact:
[hey@nod.st](mailto:hey@nod.st)
