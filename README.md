# Solution: Optimistic Locking

> Branch: `solution/optimistic-locking`
> Fixes the race condition documented in [`main`](../../tree/main) by detecting conflict at write time instead of preventing it.

---

## The Idea

Optimistic locking assumes conflict is **unlikely**. Nothing is locked. Every transaction proceeds as if it were alone, and the conflict is caught at the moment of writing: if the row changed since it was read, the write fails and the operation is retried.

The bet is that most of the time nobody else touched the row, so paying the cost of a lock on every request is wasteful.

---

## The Fix

A `version` column is added to `events`. The read captures the version; the write only succeeds if the version is still the same.

```sql
-- 1. Read capacity AND version. No lock.
SELECT available_capacity, version
FROM events
WHERE id = ?;

-- 2. Guard: if there is no capacity, reject.

-- 3. Write, conditioned on the version not having changed.
UPDATE events
SET available_capacity = available_capacity - 1,
    version = version + 1
WHERE id = ?
  AND version = ?;   -- the version read in step 1
```

If `affected_rows = 0`, someone else committed first. The read was stale, the transaction is rolled back and the whole operation is retried from step 1.

```
attempt 1 → version mismatch → retry
attempt 2 → version mismatch → retry
attempt 3 → success
```

Retries are bounded. When the limit is exhausted the request fails rather than looping forever.

---

## Running It

```bash
git checkout solution/optimistic-locking
docker compose up -d
```

Wait 10–15 seconds for MySQL, then:

```bash
curl http://localhost:8080/status?event_id=1
docker compose --profile testing run k6 run /scripts/race-condition.js
curl http://localhost:8080/status?event_id=1
```

Reset between runs:

```bash
curl http://localhost:8080/reset?event_id=1
```

---

## Results

| Metric | Pessimistic | This branch |
| --- | --- | --- |
| Total capacity | 3,000 | 3,000 |
| Tickets created | 3,000 | ~1,004 |
| Overselling | None | **None** |
| Failures | Rejected at capacity | Rejected by **retry exhaustion** |

**This is the finding that matters.** Both strategies are correct — neither oversells. But under the contention this load test produces, optimistic locking never sold the remaining tickets: attempts kept colliding on the same row and exhausted their retries before succeeding.

Correctness was preserved. Throughput was not. Roughly two thirds of the inventory went unsold, not because capacity ran out, but because the strategy gave up.

---

## Trade-offs

**What it buys you**

- **No locks and no waiting.** Under low contention it is faster than pessimistic locking, because the cost of conflict is only paid when conflict actually happens.
- **No deadlocks.** There is nothing to deadlock on.
- **Scales better horizontally** when writes to the same row are rare.

**What it costs you**

- **It collapses under contention**, as the numbers above show. The higher the contention, the more retries, and retries are wasted work.
- **Retry policy becomes a design decision**: how many attempts, how long to back off between them, whether to add jitter to stop clients retrying in lockstep.
- **Failure is harder to explain to a user.** "Sold out" is understandable; "we could not complete your request, try again" is not, especially when tickets are still available.
- **More application logic** — and therefore more places to get it wrong.

---

## When To Use It

Optimistic locking fits where **writes to the same row are rare and reads are frequent**: editing a profile, updating a document, back-office operations, anything where two people touching the same record at the same instant is the exception.

It is the wrong choice for a ticket launch. This branch exists precisely to demonstrate that: the same strategy that is elegant in a CRUD application becomes a throughput problem when three thousand people press *buy* at the same second.

**There is no better strategy in the abstract — it depends on the level of contention.** That is the conclusion of the case study.

Compare with [`solution/pessimistic-locking`](../../tree/solution/pessimistic-locking).
