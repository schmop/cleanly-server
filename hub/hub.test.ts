import { describe, expect, it, vi } from 'vitest';
import request from 'supertest';
import { createApp } from './hub';

const SECRET = 'test-secret';

function appWithFakeWhoami(userId: number | null = 42) {
    return createApp(SECRET, async () => userId);
}

describe('POST /publish', () => {
    it('rejects requests without the shared secret', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app).post('/publish').send({ targets: [1], data: {} });
        expect(res.status).toBe(403);
    });

    it('rejects requests with the wrong secret', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app)
            .post('/publish')
            .set('Authorization', 'Bearer not-the-secret')
            .send({ targets: [1], data: {} });
        expect(res.status).toBe(403);
    });

    it('rejects publish with non-array targets', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app)
            .post('/publish')
            .set('Authorization', `Bearer ${SECRET}`)
            .send({ targets: 'not-an-array', data: {} });
        expect(res.status).toBe(400);
    });

    it('rejects publish with missing data', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app)
            .post('/publish')
            .set('Authorization', `Bearer ${SECRET}`)
            .send({ targets: [1] });
        expect(res.status).toBe(400);
    });

    it('returns 200 when payload is well-formed and there are no listeners', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app)
            .post('/publish')
            .set('Authorization', `Bearer ${SECRET}`)
            .send({ targets: [99], data: { type: 'task_done', payload: { taskId: 1 } } });
        expect(res.status).toBe(200);
        expect(res.text).toBe('ok');
    });
});

describe('GET /events', () => {
    it('rejects connections without an auth token', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app).get('/events');
        expect(res.status).toBe(403);
    });

    it('rejects connections when whoami returns null', async () => {
        const { app } = appWithFakeWhoami(null);
        const res = await request(app).get('/events?token=garbage');
        expect(res.status).toBe(403);
    });
});

describe('client registry', () => {
    it('registers exactly one client per accepted /events connection', async () => {
        const { app, clients } = appWithFakeWhoami(7);
        const server = app.listen(0);
        try {
            const address = server.address();
            const port = typeof address === 'object' && address ? address.port : 0;
            const url = `http://127.0.0.1:${port}/events?token=ok`;

            // Open one SSE connection long enough for the handler to register it.
            const ac = new AbortController();
            const inFlight = fetch(url, { signal: ac.signal }).catch(() => undefined);
            await waitFor(() => Object.keys(clients).length === 1);
            expect(Object.values(clients)[0].userId).toBe(7);

            ac.abort();
            await inFlight;
            await waitFor(() => Object.keys(clients).length === 0);
        } finally {
            server.close();
        }
    });
});

async function waitFor(predicate: () => boolean, timeoutMs = 1000): Promise<void> {
    const start = Date.now();
    while (!predicate()) {
        if (Date.now() - start > timeoutMs) {
            throw new Error('waitFor timed out');
        }
        await new Promise((resolve) => setTimeout(resolve, 10));
    }
    // touch vi to silence "unused import" on builds that strip dead code
    void vi;
}
