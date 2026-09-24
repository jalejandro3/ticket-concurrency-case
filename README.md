# Solution: Pessimistic Locking

> Branch: `solution/pessimistic-locking`
> Fixes the race condition documented in [`main`](../../tree/main) by serialising access to the event row.

---

## The Idea

Pessimistic locking assumes conflict is likely and prevents it up front: the first transaction to touch the event row **locks it**, and every other transaction that wants that same row **waits** until the lock is released.

The race condition on `main` exists because the read and the write are not atomic. `SELECT ... FOR UPDATE` closes that window: the row cannot be read by anyone else until the transaction commits.

---

## The Fix

The four-step reservation becomes a single transaction with an exclusive row lock:

```sql
START TRANSACTION;

-- 1. Read AND lock the row. Any other transaction asking for this row waits here.
SELECT available_capacity
FROM events
WHERE id = ?
FOR UPDATE;

-- 2. Guard: if there is no capacity, roll back and reject.

-- 3. Create the ticket
INSERT INTO tickets (event_id, status, expires_at) VALUES (?, 'reserved', ?);

-- 4. Decrement. Nobody else could have read a stale value.
UPDATE events SET available_capacity = available_capacity - 1 WHERE id = ?;

COMMIT;
```

Two details that matter:

- The `UPDATE` uses `available_capacity - 1` rather than writing back the value read in step 1. Even with the lock, computing in the database instead of in PHP removes a whole class of mistakes.
- Everything lives inside one transaction. If the `INSERT` fails, the decrement never happens — atomicity, the **A** in ACID.

---

## Running It

```bash
git checkout solution/pessimistic-locking
docker compose up -d
```

Wait 10–15 seconds for MySQL, then check the starting state:

```bash
curl http://localhost:8080/status?event_id=1
```

Run the same load test that breaks `main`:

```bash
docker compose --profile testing run k6 run /scripts/race-condition.js
```

Check the result:

```bash
curl http://localhost:8080/status?event_id=1
```

Reset between runs:

```bash
curl http://localhost:8080/reset?event_id=1
```

---

## Results

| Metric | `main` (buggy) | This branch |
| --- | --- | --- |
| Total capacity | 3,000 | 3,000 |
| Tickets created | > 3,000 (overselling) | 3,000 |
| Overselling | Yes | **None** |
| Failed requests | — | Rejected cleanly once capacity reached |

Under the same k6 load, the lock serialises every reservation on the event row and capacity is never exceeded.

---

## Trade-offs

**What it buys you**

- Correctness is guaranteed by the database, not by application logic.
- No retry logic to write, tune or reason about.
- The reasoning is local: read the transaction and you know what happens.

**What it costs you**

- **Throughput.** Every reservation for the same event queues behind the previous one. The lock turns a parallel workload into a serial one on that row.
- **Lock contention and wait time.** Under heavy load, requests spend time waiting rather than working. Watch `innodb_lock_wait_timeout`.
- **Deadlock risk** if other code paths lock the same rows in a different order. Always lock in a consistent order.
- **It does not scale horizontally.** Adding application servers does not help: the bottleneck is one row in one database.

---

## When To Use It

Pessimistic locking is the right default when **contention is high and the cost of being wrong is high**: inventory, seat allocation, account balances, anything where a duplicate is a real-world liability rather than an inconvenience.

The serialisation it imposes is a feature, not a bug. In a ticket launch you would rather have a slower queue than 247 people holding tickets for seats that do not exist.

Compare with [`solution/optimistic-locking`](../../tree/solution/optimistic-locking), which makes the opposite bet.
