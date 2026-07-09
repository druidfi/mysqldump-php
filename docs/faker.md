# Using Faker for anonymization

The transform hooks accept any callable, so [FakerPHP](https://fakerphp.org/) plugs straight
into `Anonymizer::columnMap()` when you want realistic-looking fake data instead of the
masked/hashed output of the built-in helpers. Faker is not a dependency of this library —
add it to your own project:

```bash
composer require --dev fakerphp/faker
```

## Basic usage

Create the Faker generator once, outside the hook — the hook runs for every dumped row:

```php
use Druidfi\Mysqldump\Anonymizer;
use Druidfi\Mysqldump\Mysqldump;
use Faker\Factory;

$faker = Factory::create('fi_FI');

$dumper = new Mysqldump('mysql:host=localhost;dbname=testdb', 'username', 'password');

$dumper->setTransformTableRowHook(Anonymizer::columnMap([
    'customers' => [
        'name'    => fn () => $faker->name(),
        'phone'   => fn () => $faker->phoneNumber(),
        'address' => fn () => $faker->streetAddress(),
        // Faker and the built-in helpers mix freely in one map
        'social_security_number' => Anonymizer::fixed('REDACTED'),
    ],
]));

$dumper->start('storage/work/dump.sql');
```

Transformers are called as `function (mixed $value, array $row): mixed`; PHP ignores the
extra arguments passed to zero-argument closures, so plain `fn () => $faker->name()` works.

The same works without `columnMap()` in a plain hook, which is how Faker has typically been
combined with this library:

```php
$dumper->setTransformTableRowHook(function (string $tableName, array $row) use ($faker) {
    if ($tableName === 'customers') {
        $row['name'] = $faker->name();
    }

    return $row;
});
```

## Preserving NULLs

Unlike the built-in helpers, a zero-argument Faker closure replaces `NULL` with a fake value,
silently changing nullability semantics. Wrap it when that matters:

```php
'name' => fn ($value) => $value === null ? null : $faker->name(),
```

## Deterministic output (referential integrity)

Faker output is random: if the same email appears in `customers` and `orders`, each
occurrence gets a different fake value and the join is gone. Seeding Faker from the original
value makes the mapping deterministic — same input, same fake output, across tables and
across dumps:

```php
$fakeEmail = function ($value) use ($faker) {
    if ($value === null) {
        return null;
    }
    $faker->seed(crc32('my-secret-salt' . $value));

    return $faker->safeEmail();
};

$dumper->setTransformTableRowHook(Anonymizer::columnMap([
    'customers' => ['email' => $fakeEmail],
    'orders'    => ['customer_email' => $fakeEmail],
]));
```

This gives Faker's realistic output with the same determinism guarantee as
`Anonymizer::email()` — including the same caveat: deterministic output of guessable values
(names, phone numbers) can be re-identified by seeding the same way, so keep a secret salt
in the seed.

## Unique values

`$faker->unique()->safeEmail()` throws an `OverflowException` once it cannot find a fresh
value (it retries 10,000 times), which bites on large tables. The seeded approach above
sidesteps the problem: distinct inputs produce distinct seeds, and `crc32` collisions are
rare enough for test data. Where a unique index must hold strictly, derive the value from
the row's primary key instead:

```php
'email' => fn ($value, array $row) => $value === null ? null : sprintf('user-%d@example.com', $row['id']),
```

## Row-dependent output

The second argument is the full (untransformed) row, so fake data can depend on other
columns — for example locale-appropriate names:

```php
use Faker\Factory;

$generators = [];

$fakeName = function ($value, array $row) use (&$generators) {
    if ($value === null) {
        return null;
    }
    $locale = $row['locale'] ?? 'en_US';
    $generators[$locale] ??= Factory::create($locale);

    return $generators[$locale]->name();
};
```

(Generators are cached per locale — `Factory::create()` is far too expensive to call per row.)
