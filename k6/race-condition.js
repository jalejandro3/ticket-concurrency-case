import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

// Custom metrics
const reservedOk  = new Counter('tickets_reserved_ok');
const reservedFail = new Counter('tickets_no_availability');

// Test configuration:
// 200 virtual users trying to reserve simultaneously
// for 10 seconds — simulates a popular event launch
export const options = {
    vus: 200,
    duration: '10s',
    thresholds: {
        'checks{check:no server errors}': ['rate>0.99'],
    },
};

export default function () {
    const payload = JSON.stringify({
        event_id: 1,
        user_id: Math.floor(Math.random() * 100000),
    });

    const res = http.post('http://localhost:8080/reserve', payload, {
        headers: { 'Content-Type': 'application/json' },
    });

    if (res.status === 201) {
        reservedOk.add(1);
    } else if (res.status === 409) {
        reservedFail.add(1);
    }

    check(res, {
        'no server errors': (r) => r.status < 500,
    });
}

// How to interpret the results:
//
// tickets_reserved_ok → how many tickets were created
// tickets_no_availability → how many requests were rejected due to no stock
//
// After running this script, call: curl http://localhost:8080/status?event_id=1
//
// If tickets_created > total_capacity → the bug is confirmed.
// The difference is the overselling amount.
