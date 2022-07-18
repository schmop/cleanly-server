"use strict";
var __awaiter = (this && this.__awaiter) || function (thisArg, _arguments, P, generator) {
    function adopt(value) { return value instanceof P ? value : new P(function (resolve) { resolve(value); }); }
    return new (P || (P = Promise))(function (resolve, reject) {
        function fulfilled(value) { try { step(generator.next(value)); } catch (e) { reject(e); } }
        function rejected(value) { try { step(generator["throw"](value)); } catch (e) { reject(e); } }
        function step(result) { result.done ? resolve(result.value) : adopt(result.value).then(fulfilled, rejected); }
        step((generator = generator.apply(thisArg, _arguments || [])).next());
    });
};
var __importDefault = (this && this.__importDefault) || function (mod) {
    return (mod && mod.__esModule) ? mod : { "default": mod };
};
Object.defineProperty(exports, "__esModule", { value: true });
const express_1 = __importDefault(require("express"));
const cors_1 = __importDefault(require("cors"));
const uuid_1 = require("uuid");
const whoami_1 = require("./whoami");
const app = (0, express_1.default)();
app.use((0, cors_1.default)());
app.use(express_1.default.json());
// app.use(express.urlencoded({ extended: false }));
const PORT = 3333;
const clients = {};
function forbidden(response, text) {
    console.error(text);
    response.status(403).send(text);
}
function badRequest(response, text) {
    console.error(text);
    response.status(400).send(text);
}
function eventsHandler(request, response) {
    return __awaiter(this, void 0, void 0, function* () {
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
        const userId = yield (0, whoami_1.whoami)(auth);
        if (null == userId) {
            return forbidden(response, 'No valid user found!');
        }
        response.writeHead(200, headers);
        const clientId = (0, uuid_1.v4)();
        clients[clientId] = { response, userId };
        console.log(`${clientId} (user ${userId}) connected!`);
        request.on('close', () => {
            console.log(`${clientId} (user ${userId}) disconnected`);
            delete clients[clientId];
        });
    });
}
function publish(request, response) {
    return __awaiter(this, void 0, void 0, function* () {
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
        const targets = request.body.targets;
        const data = request.body.data;
        if (targets == null || data == null || !Array.isArray(targets)) {
            return badRequest(response, 'Invalid publish, data or targets invalid!');
        }
        targets.forEach((target) => sendDataToUser(target, data));
        response.send('ok');
    });
}
function sendDataToClient(uuid, data) {
    if (!(uuid in clients)) {
        console.error(`Trying to send data to non-existent client ${uuid}`);
        return;
    }
    clients[uuid].response.write(`data: ${JSON.stringify(data)}\n\n`);
}
function sendDataToUser(target, data) {
    const receivingClients = Object.entries(clients)
        .filter((([, { userId }]) => target === userId))
        .map(([uuid]) => uuid);
    if (receivingClients.length === 0) {
        return;
    }
    receivingClients.forEach((client) => sendDataToClient(client, data));
}
app.listen(PORT, () => {
    console.log(`Cleanly hub listening at http://localhost:${PORT}`);
});
app.get('/events', eventsHandler);
app.post('/publish', publish);
