# Design Principles

Principles applied and refined during the TypeScript implementation of the Warehouse Desk Kata.

---

## 1. Test before touching

Write full coverage for existing behaviour before modifying it. A failing test suite is the only reliable signal that a refactor preserved correctness; reading code is not enough.

> *Add full test coverage for new features. To protect against regressions, always add full coverage for existing code before modifying it.*

---

## 2. Replace parallel arrays with value objects

Three records keyed by the same identifier (`stockBySku`, `reservedBySku`, `priceBySku`) are a single concept split across three containers. Collapse them into one class (`Product`) so related data travels together and cannot drift out of sync. The same applies to `orderStatus`/`orderSku`/`orderQty` → `Order`.

**Signal:** you find yourself writing `this.stockBySku[sku] ?? 0` repeatedly, or looking up the same key in three different maps in the same method.

---

## 3. Name operations, not mutations

Instead of `this.stockBySku[sku] -= qty`, prefer `product.ship(qty)`. The method name expresses *what is happening in the domain*, not *how the number changes*. Two operations that share the same arithmetic (`restock` and `receive` both add to `onHand`) are still separate methods because they represent different business events.

---

## 4. Use enums for finite status sets

A `string status` that only ever holds four known values is an untyped enum. Replace it with a TypeScript string enum (`OrderStatus`, `ReservationStatus`). The benefits:

- Passing an unknown status becomes a compile-time error, not silent misbehaviour.
- Behaviour specific to the type lives on helper functions or the enum itself.
- `.toString()` / string coercion is used **only at output boundaries** (logs, dump output). Everywhere else, compare enum members directly.

---

## 5. One handler per command

A single method that checks command types with `if`/`else` chains and contains all logic for every command will grow without bound. Extract one private method per command and reduce the dispatcher to a `switch` or lookup map. Each handler is then independently readable, testable, and modifiable.

```typescript
switch (parts[0]) {
  case 'SELL':   return this.handleSell(parts);
  case 'CANCEL': return this.handleCancel(parts[1]);
  // ...
}
```

---

## 6. Inject time; never call `Date.now()` directly

Any logic that branches on the current time is untestable without sleeping. Accept a clock function via the constructor and default it to `() => Date.now()`. Tests inject a fixed or controllable timestamp.

---

## 7. Co-locate data that belongs together

`new Product({ price: 1.5, onHand: 40 })` is easier to read and maintain than two separate entries in two separate maps. When initialising state, ask: *would a change to one of these lines always require a change to the others?* If yes, they belong in the same constructor call.

---

## 8. String form only at output boundaries

Enum members are the internal truth. Convert to string only when crossing the output boundary — a log message, a serialised dump, a test assertion string. Everything inside the domain compares enum members to members (`=== OrderStatus.Shipped`), never strings to strings.

---

## 9. Prefer template literals for log messages

`` `order ${orderId} shipped to ${customer} amount=${amount}` `` is easier to scan than string concatenation. Extract method-call results to a local variable first when they cannot be interpolated directly.
