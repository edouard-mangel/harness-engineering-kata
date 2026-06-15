# Design Principles

Principles applied and refined during the PHP implementation of the Warehouse Desk Kata.

---

## 1. Test before touching

Write full coverage for existing behaviour before modifying it. A failing test suite is the only reliable signal that a refactor preserved correctness; reading code is not enough.

> *Add full test coverage for new features. To protect against regressions, always add full coverage for existing code before modifying it.*

---

## 2. Replace parallel arrays with value objects

Three arrays keyed by the same identifier (`stockBySku`, `reservedBySku`, `priceBySku`) are a single concept split across three containers. Collapse them into one object (`Product`) so related data travels together and cannot drift out of sync. The same applies to `orderStatus`/`orderSku`/`orderQty` → `Order`.

**Signal:** you find yourself writing `$map[$key] ?? 0` repeatedly, or passing the same key into three different arrays in the same method.

---

## 3. Name operations, not mutations

Instead of `$this->stockBySku[$sku] -= $qty`, prefer `$product->ship($qty)`. The method name expresses *what is happening in the domain*, not *how the number changes*. Two operations that share the same arithmetic (`restock` and `receive` both add to `onHand`) are still separate methods because they represent different business events.

---

## 4. Use enums for finite status sets

A `string $status` that only ever holds four known values is an untyped enum. Replace it with a backed string enum (`OrderStatus`, `ReservationStatus`). The benefits:

- Passing an unknown status becomes a static/runtime error, not silent misbehaviour.
- Behaviour specific to the type lives on the enum (`isCancelled()`, `isActive()`).
- `->value` is used **only at output boundaries** (logs, dump output). Everywhere else, compare cases directly.

---

## 5. One handler per command

A single method that pattern-matches on a command type and contains all the logic for every command will grow without bound. Extract one private method per command and reduce the dispatcher to a `match` expression. Each handler is then independently readable, testable, and modifiable.

```php
match ($parts[0]) {
    'SELL'  => $this->handleSell($parts),
    'CANCEL' => $this->handleCancel($parts[1]),
    // ...
};
```

---

## 6. Inject time; never call `time()` directly

Any logic that branches on the current time is untestable without sleeping. Accept a `\Closure` clock via `setClock()` and default it to `time()`. Tests inject a fixed or controllable timestamp.

---

## 7. Co-locate data that belongs together

`new Product(price: 1.5, onHand: 40)` is easier to read and maintain than two separate array entries in two separate maps. When initialising state, ask: *would a change to one of these lines always require a change to the others?* If yes, they belong in the same constructor call.

---

## 8. `->value` only at output boundaries

Enum cases are the internal truth. Convert to string (`->value`) only when crossing the output boundary — a log message, a serialised dump, a test assertion string. Everything inside the domain compares cases to cases (`=== OrderStatus::Shipped`), never strings to strings.

---

## 9. Prefer string interpolation for log messages

`"order $orderId shipped to $customer amount=$amount"` is easier to scan than `'order ' . $orderId . ' shipped to ' . $customer . ' amount=' . $amount`. Extract method-call results to a local variable first when they cannot be interpolated directly.
