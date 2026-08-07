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

    it('rejects publish without a body', async () => {
        const { app } = appWithFakeWhoami();
        const res = await request(app)
            .post('/publish')
            .set('Authorization', `Bearer ${SECRET}`);
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

    it('returns 200 and forwards the payload to a connected recipient', async () => {
        const { app, clients } = appWithFakeWhoami(7);
        const server = app.listen(0);
        try {
            const address = server.address();
            const port = typeof address === 'object' && address ? address.port : 0;

            // Kick off the SSE request — but don't await its `response` event.
            // The hub calls `writeHead` but never flushes until the first
            // `response.write`, so headers don't reach the client until the
            // publish below actually triggers a payload write.
            let sseStream: IncomingMessage | null = null;
            const req = http.get(`http://127.0.0.1:${port}/events?token=ok`);
            req.on('response', (res) => { sseStream = res; });

            await waitFor(() => Object.keys(clients).length === 1);

            const payload = { type: 'task_done', payload: { taskId: 99 } };
            const publishRes = await postJson(
                `http://127.0.0.1:${port}/publish`,
                { Authorization: `Bearer ${SECRET}` },
                { targets: [7], data: payload },
            );
            expect(publishRes.status).toBe(200);
            expect(publishRes.body).toBe('ok');

            // The publish flushed headers + the data frame; collect the frame.
            await waitFor(() => sseStream !== null);
            const received = await readNextSseFrame(sseStream!);
            expect(received).toBe(`data: ${JSON.stringify(payload)}\n\n`);

            req.destroy();
            await waitFor(() => Object.keys(clients).length === 0);
        } finally {
            server.close();
        }
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

import http, { IncomingMessage } from 'node:http';

function postJson(url: string, headers: Record<string, string>, body: unknown): Promise<{ status: number; body: string }> {
    return new Promise((resolve, reject) => {
        const data = JSON.stringify(body);
        const req = http.request(
            url,
            {
                method: 'POST',
                headers: { ...headers, 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(data).toString() },
            },
            (res) => {
                const chunks: Buffer[] = [];
                res.on('data', (c: Buffer) => chunks.push(c));
                res.on('end', () => resolve({ status: res.statusCode ?? 0, body: Buffer.concat(chunks).toString('utf8') }));
            },
        );
        req.on('error', reject);
        req.write(data);
        req.end();
    });
}

function readNextSseFrame(stream: IncomingMessage, timeoutMs = 2000): Promise<string> {
    return new Promise((resolve, reject) => {
        const decoder = new TextDecoder();
        let buf = '';
        const timer = setTimeout(() => {
            stream.removeListener('data', onData);
            reject(new Error('readNextSseFrame timed out'));
        }, timeoutMs);
        const onData = (chunk: Buffer) => {
            buf += decoder.decode(chunk, { stream: true });
            const idx = buf.indexOf('\n\n');
            if (idx === -1) return;
            clearTimeout(timer);
            stream.removeListener('data', onData);
            resolve(buf.slice(0, idx + 2));
        };
        stream.on('data', onData);
    });
}

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
