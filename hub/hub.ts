import express, {Express} from 'express';
import cors from 'cors';
import { v4 } from 'uuid';
import { Request, Response } from 'express-serve-static-core';
import {UserId, whoami as defaultWhoami} from './whoami';

type Uuid = string;

export type ClientRegistry = Record<Uuid, {response: Response, userId: UserId}>;
type WhoamiFn = (token: string) => Promise<UserId | null>;

export interface HubAppHandle {
    app: Express;
    clients: ClientRegistry;
}

export function createApp(publishSecret: string, whoamiFn: WhoamiFn = defaultWhoami): HubAppHandle {
    const app: Express = express();
    app.use(cors());
    app.use(express.json());

    const clients: ClientRegistry = {};

    function forbidden(response: Response, text: string) {
        console.error(text);
        response.status(403).send(text);
    }

    function badRequest(response: Response, text: string, ...extraLoggingArgs: unknown[]) {
        console.error(text, ...extraLoggingArgs);
        response.status(400).send(text);
    }

    async function eventsHandler(request: Request, response: Response) {
        const headers = {
            'Content-Type': 'text/event-stream',
            'Connection': 'keep-alive',
            'Access-Control-Allow-Origin': '*',
            'Cache-Control': 'no-cache'
        };
        const auth = request.query.token;
        if (typeof auth !== 'string') {
            return forbidden(response, 'No authorization token given!');
        }
        const userId = await whoamiFn(auth);
        if (null == userId) {
            return forbidden(response, 'No valid user found!');
        }
        response.writeHead(200, headers);

        const clientId = v4();
        clients[clientId] = {response, userId};
        console.info(`${clientId} (user ${userId}) connected!`);

        request.on('close', () => {
            console.info(`${clientId} (user ${userId}) disconnected`);
            delete clients[clientId];
        });
    }

    function publish(request: Request, response: Response) {
        const auth = request.header('Authorization');
        if (auth?.toLowerCase() !== `bearer ${publishSecret}`) {
            return forbidden(response, 'No valid sse push secret given!');
        }
        const targets: number[] = request.body.targets;
        const data: any = request.body.data;
        if (targets == null || data == null || !Array.isArray(targets)) {
            return badRequest(response, 'Invalid publish, data or targets invalid!', {targets, data});
        }
        targets.forEach((target) => sendDataToUser(target, data));
        response.send('ok');
    }

    function sendDataToClient(uuid: Uuid, data: any) {
        if (!(uuid in clients)) {
            console.error(`Trying to send data to non-existent client ${uuid}`);
            return;
        }
        clients[uuid].response.write(`data: ${JSON.stringify(data)}\n\n`);
    }

    function sendDataToUser(target: number, data: any) {
        const receivingClients = Object.entries(clients)
            .filter((([, {userId}]) => target === userId))
            .map(([uuid]) => uuid);
        if (receivingClients.length === 0) {
            return;
        }
        receivingClients.forEach((client: Uuid) => sendDataToClient(client, data));
    }

    app.get('/events', eventsHandler);
    app.post('/publish', publish);

    return { app, clients };
}

// Only boot the listener when invoked as the entry point — keeps the module
// importable from tests without colliding on the bound port.
if (require.main === module) {
    const publishSecret = process.env.SSE_PUBLISH_SECRET;
    if (publishSecret === undefined) {
        console.error('No SSE_PUBLISH_SECRET environment variable set! Exiting!');
        process.exit(1);
    }
    const PORT = 3334;
    const { app } = createApp(publishSecret);
    app.listen(PORT, () => {
        console.info(`Cleanly hub listening at http://localhost:${PORT}`);
    });
}
