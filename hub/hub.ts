import express, {Express} from 'express';
import cors from 'cors';
import { v4 } from 'uuid';
import { Request, Response } from 'express-serve-static-core';
import { whoami } from './whoami';

type Uuid = string;

const app: Express = express();

app.use(cors());
app.use(express.json());
// app.use(express.urlencoded({ extended: false }));

const PORT = 3334;
const clients = {} as Record<Uuid, {response: Response, userId: number}>;

function forbidden(response: Response, text: string) {
    console.error(text);
    response.status(403).send(text);
}

function badRequest(response: Response, text: string) {
    console.error(text);
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
    const userId = await whoami(auth);
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

async function publish(request: Request, response: Response) {
    const addr = request.socket.remoteAddress;
    const allowedIps = [
        '127.0.0.1',
        'localhost',
        '::1',
        '::ffff:127.0.0.1',
    ];
    if (addr == null || !allowedIps.includes(addr)) {
        return forbidden(response, `Remote ${addr} tried to publish data!`);
    }
    const targets: number[] = request.body.targets;
    const data: any = request.body.data;
    if (targets == null || data == null || !Array.isArray(targets)) {
        return badRequest(response, 'Invalid publish, data or targets invalid!');
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


app.listen(PORT, () => {
    console.info(`Cleanly hub listening at http://localhost:${PORT}`)
});

app.get('/events', eventsHandler);
app.post('/publish', publish);
