# ORM 适配层

## 支持能力

- `Fibers::eloquent(object $connection)`：创建 Eloquent 适配器
- `Fibers::fixtures(array $fixtures = [])`：创建 Fixtures 适配器

## 通用接口

- `transaction(callable $callback): mixed`
- `query(string $statement, array $bindings = []): mixed`

## Fixtures 示例

```php
use Kode\Fibers\Fibers;

$orm = Fibers::fixtures([
    'users' => [['id' => 1, 'name' => 'u1']],
]);

$rows = $orm->query('users');
print_r($rows);
```

## Eloquent 示例

```php
use Kode\Fibers\Fibers;

$orm = Fibers::eloquent($connection);
$users = $orm->query('select * from users where id = ?', [1]);
```
