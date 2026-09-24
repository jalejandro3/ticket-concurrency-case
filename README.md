# TicketFlow — Case Study: Race Condition in Ticket Reservation

> A real-world case study on concurrency in high-demand backend systems.  
> Built as a structured learning exercise and technical portfolio artifact.

---

## The Problem

On a Monday morning, the product team sends this message:

> *"On Friday we launched tickets for Festival Estereo Picnic. We had 3,000 tickets available. We sold 3,247. There are 247 people with a paid ticket for an event that cannot accommodate them."*

This repository documents the diagnosis, bug reproduction, and two solutions with different trade-offs.

---

## Repository Structure

```
main                           ← production system with the bug
├── solution/pessimistic-locking
└── solution/optimistic-locking
```

---

## How to Reproduce the Problem

### Requirements

- Docker and Docker Compose

### Start the system

```bash
docker compose up -d
```

Wait 10–15 seconds for MySQL to be ready.

### Verify initial state

```bash
curl http://localhost:8080/status?event_id=1
```

You should see `total_capacity: 3000` and `tickets_created: 0`.

### Run the concurrency test

```bash
docker compose --profile testing run k6 run /scripts/race-condition.js
```

### Verify the overselling

```bash
curl http://localhost:8080/status?event_id=1
```

If `tickets_created` exceeds `total_capacity`, the bug is confirmed.

### Reset and repeat

```bash
curl http://localhost:8080/reset?event_id=1
```

---

## Diagnosis

### Root cause: race condition

The reservation process has four steps:

```
1. SELECT available_capacity FROM events WHERE id = ?
2. IF available_capacity <= 0 → reject
3. INSERT INTO tickets ...
4. UPDATE events SET available_capacity = value_read_in_step_1 - 1
```

The problem is that steps 1 and 4 **are not atomic**. Between the read and the write, another process can read the same value.

**Sequence that produces overselling:**

```
Time    │ User A                           │ User B
────────┼──────────────────────────────────┼──────────────────────────────────
t1      │ SELECT → available_capacity = 1  │
t2      │                                  │ SELECT → available_capacity = 1
t3      │ IF 1 > 0 → proceed ✓             │
t4      │                                  │ IF 1 > 0 → proceed ✓
t5      │ INSERT ticket A                  │
t6      │                                  │ INSERT ticket B
t7      │ UPDATE → available_capacity = 0  │
t8      │                                  │ UPDATE → available_capacity = 0
────────┴──────────────────────────────────┴──────────────────────────────────
Result: 2 tickets created, available_capacity = 0
        Real capacity should be: -1
```

Both users read `available_capacity = 1`, both passed the guard, and both wrote `available_capacity = 0`. The last writer overwrote the first.

### Secondary bug: phantom inventory

Tickets in `reserved` status that are never paid block real capacity without generating a confirmed sale. The system has no process to release those tickets when `expires_at` is reached.

Effect: fewer possible sales than there should be. The opposite problem to overselling, but equally damaging in a high-demand event launch.

---

## Diagnostic Query

```sql
SELECT 
    e.name AS event_name,
    e.total_capacity,
    COUNT(t.id) AS tickets_created,
    SUM(t.status = 'paid') AS tickets_paid,
    e.total_capacity - COUNT(t.id) AS difference
FROM events e
LEFT JOIN tickets t ON t.event_id = e.id
WHERE e.id = 1
GROUP BY e.id, e.name, e.total_capacity;
```

If `difference` is negative, overselling has occurred.

---

## Solutions

See the branches:

- `solution/pessimistic-locking` — pessimistic locking with `SELECT ... FOR UPDATE`
- `solution/optimistic-locking` — optimistic locking with versioning

Each branch documents its own trade-offs in its corresponding README.

---

## Stack

- PHP 8.2 (no framework, plain PDO)
- MySQL 8.0
- Docker / Docker Compose
- k6 (load testing)

---

## Learning Context

This case study is part of a structured learning path on high-demand backend systems. The goal is to understand concurrency problems from first principles, without frameworks abstracting away what is actually happening at the database level.
